<?php

namespace MigrAlign\Scanning;

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MigrAlign\ValueObjects\ColumnDefinition;
use MigrAlign\ValueObjects\SchemaIntent;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class MigrationScanner
{
    public function __construct(
        protected Filesystem $files,
    ) {}

    /**
     * Build cumulative schema intent by replaying migration up() methods.
     *
     * @param  list<string>  $ignoredTables
     */
    public function scan(
        string $migrationsPath,
        ?string $migrationFilter = null,
        array $ignoredTables = [],
        ?Connection $connection = null,
    ): SchemaIntent {
        $intent = new SchemaIntent;
        $connection ??= $this->resolveConnection();

        foreach ($this->resolveMigrationFiles($migrationsPath, $migrationFilter) as $file) {
            $this->applyMigrationFile($file, $intent, $ignoredTables, $connection);
        }

        return $intent;
    }

    protected function resolveConnection(): Connection
    {
        $name = config('migralign.connection') ?? config('database.default');

        if (App::getFacadeRoot() !== null) {
            return DB::connection($name);
        }

        throw new \RuntimeException('MigrAlign requires a Laravel application context to scan migrations.');
    }

    /**
     * @return list<string>
     */
    protected function resolveMigrationFiles(string $path, ?string $filter): array
    {
        if (! $this->files->isDirectory($path)) {
            return [];
        }

        $files = [];

        foreach ((new Finder)->in($path)->files()->name('*.php')->sortByName() as $file) {
            $basename = $file->getFilename();

            if ($filter !== null && ! Str::contains($basename, $filter)) {
                continue;
            }

            $files[] = $file->getRealPath();
        }

        return $files;
    }

    /**
     * @param  list<string>  $ignoredTables
     */
    protected function applyMigrationFile(string $path, SchemaIntent $intent, array $ignoredTables, Connection $connection): void
    {
        $migration = $this->resolveMigration($path);

        if ($migration === null) {
            return;
        }

        $builder = new RecordingSchemaBuilder($connection);

        $originalSchema = null;

        try {
            $originalSchema = \Illuminate\Support\Facades\Schema::getFacadeRoot();
        } catch (\Throwable) {
            $originalSchema = null;
        }

        \Illuminate\Support\Facades\Schema::swap($builder);

        try {
            if (method_exists($migration, 'up')) {
                $migration->up();
            }
        } catch (\Throwable) {
            // Migrations with DB-dependent logic may fail; still process recorded blueprints.
        } finally {
            if ($originalSchema !== null) {
                \Illuminate\Support\Facades\Schema::swap($originalSchema);
            }
        }

        foreach ($builder->blueprints as $blueprint) {
            $this->applyBlueprintOperations($blueprint, $intent, $ignoredTables);
        }
    }

    protected function resolveMigration(string $path): ?object
    {
        $migration = include $path;

        if (is_object($migration) && $migration instanceof Migration) {
            return $migration;
        }

        $class = $this->getMigrationClass($path);

        if ($class === null || ! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return null;
        }

        if (! $reflection->isSubclassOf(Migration::class) && ! $reflection->hasMethod('up')) {
            return null;
        }

        return $reflection->newInstanceWithoutConstructor();
    }

    protected function getMigrationClass(string $path): ?string
    {
        $contents = $this->files->get($path);

        if (preg_match('/class\s+(\w+)\s+extends\s+Migration/', $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  list<string>  $ignoredTables
     */
    protected function applyBlueprintOperations(RecordingBlueprint $blueprint, SchemaIntent $intent, array $ignoredTables): void
    {
        $tableName = $blueprint->getTableName();

        if (in_array($tableName, $ignoredTables, true)) {
            return;
        }

        $hasCreate = false;

        foreach ($blueprint->commandList() as $command) {
            $action = $command['name'] ?? null;

            match ($action) {
                'create' => $hasCreate = true,
                'drop' => $intent->dropTable($tableName),
                'rename' => $this->handleRenameTable($intent, $tableName, $command),
                'dropColumn' => $this->handleDropColumn($intent, $tableName, $command),
                default => null,
            };
        }

        if ($hasCreate) {
            $this->handleCreate($intent, $tableName, $blueprint);

            return;
        }

        $this->applyAlterTableColumns($intent, $tableName, $blueprint);
    }

    /**
     * Schema::table() migrations store new/changed columns on the blueprint columns stack (Laravel 12).
     */
    protected function applyAlterTableColumns(SchemaIntent $intent, string $tableName, RecordingBlueprint $blueprint): void
    {
        foreach ($blueprint->schemaColumns() as $column) {
            $attributes = $column->getAttributes();

            if (! $this->isColumnAttributeSet($attributes)) {
                continue;
            }

            if (! empty($attributes['change'])) {
                $this->handleChangeColumn($intent, $tableName, $attributes);

                continue;
            }

            $this->handleAddColumn($intent, $tableName, $attributes);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function isColumnAttributeSet(array $attributes): bool
    {
        return isset($attributes['name'], $attributes['type'])
            && ! in_array($attributes['type'], ['index', 'unique', 'primary', 'foreign', 'dropColumn'], true);
    }

    protected function handleCreate(SchemaIntent $intent, string $tableName, RecordingBlueprint $blueprint): void
    {
        $table = $intent->getOrCreateTable($tableName);

        foreach ($blueprint->schemaColumns() as $column) {
            $table->addColumn(ColumnDefinition::fromSchemaColumn($column));
        }
    }

    protected function handleAddColumn(SchemaIntent $intent, string $tableName, array $command): void
    {
        $column = $command['name'] ?? $command['column'] ?? null;

        if (! $column) {
            return;
        }

        $table = $intent->getOrCreateTable($tableName);
        $table->addColumn(ColumnDefinition::fromBlueprintAttributes($column, $command));
    }

    protected function handleChangeColumn(SchemaIntent $intent, string $tableName, array $command): void
    {
        $column = $command['name'] ?? $command['column'] ?? null;

        if (! $column) {
            return;
        }

        $table = $intent->getOrCreateTable($tableName);
        $table->addColumn(ColumnDefinition::fromBlueprintAttributes($column, $command));
    }

    protected function handleDropColumn(SchemaIntent $intent, string $tableName, array $command): void
    {
        $columns = $command['columns'] ?? [$command['column'] ?? null];

        $table = $intent->getTable($tableName);

        if (! $table) {
            return;
        }

        foreach ((array) $columns as $column) {
            if ($column) {
                $table->removeColumn($column);
            }
        }
    }

    protected function handleRenameTable(SchemaIntent $intent, string $from, array $command): void
    {
        $to = $command['to'] ?? null;

        if (! $to || ! isset($intent->tables[$from])) {
            return;
        }

        $intent->tables[$to] = $intent->tables[$from];
        unset($intent->tables[$from]);
    }
}
