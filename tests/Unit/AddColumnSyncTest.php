<?php

namespace MigrAlign\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use MigrAlign\Apply\SchemaChangeApplier;
use MigrAlign\Diff\SchemaDiffEngine;
use MigrAlign\Risk\RiskAnalyzer;
use MigrAlign\Scanning\MigrationScanner;
use MigrAlign\Tests\TestCase;
use MigrAlign\ValueObjects\ChangeOperation;
use MigrAlign\ValueObjects\ColumnDefinition;
use MigrAlign\ValueObjects\RiskLevel;
use MigrAlign\ValueObjects\SchemaIntent;
use PHPUnit\Framework\Attributes\Test;

class AddColumnSyncTest extends TestCase
{
    #[Test]
    public function it_detects_new_column_from_schema_table_migration(): void
    {
        $scanner = new MigrationScanner(new Filesystem);
        $expected = $scanner->scan(__DIR__.'/../fixtures/migrations', null, ['migrations'], $this->app['db']->connection('testing'));

        $this->assertNotNull($expected->getTable('users')->getColumn('phone'));
        $this->assertSame('string', $expected->getTable('users')->getColumn('phone')->type);
        $this->assertSame(20, $expected->getTable('users')->getColumn('phone')->length);
    }

    #[Test]
    public function it_diffs_missing_column_as_add_column_change(): void
    {
        $scanner = new MigrationScanner(new Filesystem);
        $expected = $scanner->scan(__DIR__.'/../fixtures/migrations', null, ['migrations'], $this->app['db']->connection('testing'));

        $actual = new SchemaIntent;
        $table = $actual->getOrCreateTable('users');
        $table->addColumn(new ColumnDefinition('name', 'varchar', 255));
        $table->addColumn(new ColumnDefinition('email', 'varchar', 255));
        $table->addColumn(new ColumnDefinition('bio', 'varchar', 500, nullable: true));

        $changes = (new SchemaDiffEngine(new RiskAnalyzer))->diff($expected, $actual);

        $addPhone = collect($changes)->first(
            fn ($c) => $c->operation === ChangeOperation::AddColumn && $c->column === 'phone'
        );

        $this->assertNotNull($addPhone);
        $this->assertSame(RiskLevel::Safe, $addPhone->risk);
    }

    #[Test]
    public function it_generates_alter_table_add_column_sql(): void
    {
        $analyzer = new RiskAnalyzer;
        $change = $analyzer->buildAddColumnChange('users', new ColumnDefinition(
            name: 'phone',
            type: 'string',
            length: 20,
            nullable: true,
        ));

        $sql = (new SchemaChangeApplier($this->app['db']->connection()))->toSql($change);

        $this->assertStringContainsString('ALTER TABLE `users` ADD COLUMN', $sql);
        $this->assertStringContainsString('`phone`', $sql);
        $this->assertStringContainsString('varchar(20)', strtolower($sql));
    }

    #[Test]
    public function it_auto_applies_nullable_add_column_without_precheck_block(): void
    {
        $analyzer = new RiskAnalyzer;
        $change = $analyzer->buildAddColumnChange('users', new ColumnDefinition(
            name: 'phone',
            type: 'string',
            length: 20,
            nullable: true,
        ));

        $this->assertSame(RiskLevel::Safe, $change->risk);
        $this->assertNull($change->precheckSql);
    }
}
