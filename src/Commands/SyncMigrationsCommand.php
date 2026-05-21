<?php

namespace MigrAlign\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use MigrAlign\Apply\SchemaChangeApplier;
use MigrAlign\Console\InteractiveGuide;
use MigrAlign\Diff\SchemaDiffEngine;
use MigrAlign\Risk\RiskAnalyzer;
use MigrAlign\Scanning\MigrationScanner;
use MigrAlign\Schema\MySqlSchemaIntrospector;
use MigrAlign\ValueObjects\ChangeOperation;
use MigrAlign\ValueObjects\RiskLevel;
use MigrAlign\ValueObjects\SchemaChange;
use MigrAlign\ValueObjects\SyncReport;

class SyncMigrationsCommand extends Command
{
    protected $signature = 'migralign:sync
                            {--dry-run : Report differences without applying changes}
                            {--force : Apply risky changes after warnings without interactive prompts}
                            {--table= : Limit sync to a single table}
                            {--migration= : Limit scan to migrations matching this string}
                            {--connection= : Database connection name}';

    protected $description = 'Align live MySQL schema with Laravel migration definitions (MigrAlign)';

    public function handle(
        MigrationScanner $scanner,
        RiskAnalyzer $riskAnalyzer,
    ): int {
        $connectionName = $this->option('connection') ?? config('migralign.connection') ?? config('database.default');
        $connection = DB::connection($connectionName);

        if ($connection->getDriverName() !== 'mysql') {
            $this->error('MigrAlign currently supports MySQL/MariaDB only.');

            return self::FAILURE;
        }

        $ignored = config('migralign.ignored_tables', []);
        $migrationsPath = config('migralign.migrations_path', database_path('migrations'));
        $tableFilter = $this->option('table');
        $migrationFilter = $this->option('migration');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $autoApplySafe = (bool) config('migralign.auto_apply_safe', true);

        $this->info('Scanning migration files...');
        $expected = $scanner->scan($migrationsPath, $migrationFilter, $ignored, $connection);

        $this->info('Introspecting live database schema...');
        $introspector = new MySqlSchemaIntrospector($connection);
        $actual = $introspector->introspect($tableFilter, $ignored);

        $diffEngine = new SchemaDiffEngine($riskAnalyzer);
        $changes = $diffEngine->diff($expected, $actual, $tableFilter);

        if ($changes === []) {
            $this->info('No schema differences found. Database matches migration intent.');

            return self::SUCCESS;
        }

        $this->table(
            ['Operation', 'Table', 'Column', 'Risk', 'Description'],
            array_map(fn (SchemaChange $c) => [
                $c->operation->value,
                $c->table,
                $c->column ?? '-',
                $c->risk->value,
                $c->description(),
            ], $changes)
        );

        if ($dryRun) {
            $this->warn('Dry run mode — no changes applied.');

            return self::SUCCESS;
        }

        $applier = new SchemaChangeApplier($connection);
        $guide = new InteractiveGuide($introspector);
        $report = new SyncReport;

        $this->info('Applying changes...');

        // MySQL DDL (CREATE/ALTER/DROP) implicitly commits; a single wrapping transaction
        // fails with "There is no active transaction" after the first DDL statement.
        try {
            foreach ($changes as $change) {
                $this->processChange($change, $applier, $guide, $introspector, $report, $autoApplySafe, $force);
            }
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'Sync aborted by user.') {
                $this->warn($e->getMessage());
            } else {
                $report->addError($e->getMessage());
                $this->error($e->getMessage());
            }
        }

        $this->printReport($report);

        return $report->errors === [] ? self::SUCCESS : self::FAILURE;
    }

    protected function processChange(
        SchemaChange $change,
        SchemaChangeApplier $applier,
        InteractiveGuide $guide,
        MySqlSchemaIntrospector $introspector,
        SyncReport $report,
        bool $autoApplySafe,
        bool $force,
    ): void {
        if ($guide->requiresConfirmation($change)) {
            if (! $guide->confirmChange($this->output, $change, $force)) {
                $report->addSkipped($change);
                $this->line("  Skipped: {$change->description()}");

                return;
            }
        } elseif (! $guide->shouldAutoApply($change, $autoApplySafe)) {
            if (! $guide->confirmChange($this->output, $change, $force)) {
                $report->addSkipped($change);

                return;
            }
        }

        if ($change->precheckSql && ! $force && $change->operation !== ChangeOperation::AddColumn) {
            $violations = $introspector->countViolations($change->precheckSql);
            if ($violations > 0 && $change->risk !== RiskLevel::Safe) {
                $this->warn("  Blocked: {$violations} violating row(s) for {$change->description()}");
                $report->addPending($change);

                return;
            }
        }

        try {
            $sql = $applier->toSql($change);

            if ($sql === null) {
                $report->addPending($change);
                $this->warn("  Manual action required: {$change->description()}");

                return;
            }

            $applier->apply($change);
            $report->addApplied($change);
            $this->line("  <info>Applied:</info> {$change->description()}");
        } catch (\Throwable $e) {
            $report->addError($change->description().': '.$e->getMessage());
            throw $e;
        }
    }

    protected function printReport(SyncReport $report): void
    {
        $this->newLine();
        $this->info('Sync report');
        $this->line('  Applied: '.count($report->applied));
        $this->line('  Skipped: '.count($report->skipped));
        $this->line('  Pending manual: '.count($report->pending));

        if ($report->errors !== []) {
            $this->error('  Errors: '.count($report->errors));
            foreach ($report->errors as $error) {
                $this->line('    - '.$error);
            }
        }
    }
}
