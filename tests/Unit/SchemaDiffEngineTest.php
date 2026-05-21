<?php

namespace MigrAlign\Tests\Unit;

use MigrAlign\Diff\SchemaDiffEngine;
use MigrAlign\Risk\RiskAnalyzer;
use MigrAlign\Tests\TestCase;
use MigrAlign\ValueObjects\ChangeOperation;
use MigrAlign\ValueObjects\ColumnDefinition;
use MigrAlign\ValueObjects\SchemaIntent;
use PHPUnit\Framework\Attributes\Test;

class SchemaDiffEngineTest extends TestCase
{
    private SchemaDiffEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new SchemaDiffEngine(new RiskAnalyzer);
    }

    #[Test]
    public function it_detects_missing_columns(): void
    {
        $expected = new SchemaIntent;
        $table = $expected->getOrCreateTable('users');
        $table->addColumn(new ColumnDefinition('email', 'varchar', 255));

        $actual = new SchemaIntent;
        $actual->getOrCreateTable('users');

        $changes = $this->engine->diff($expected, $actual);

        $this->assertCount(1, $changes);
        $this->assertSame(ChangeOperation::AddColumn, $changes[0]->operation);
        $this->assertSame('email', $changes[0]->column);
    }

    #[Test]
    public function it_detects_extra_columns_as_drop(): void
    {
        $expected = new SchemaIntent;
        $expected->getOrCreateTable('users');

        $actual = new SchemaIntent;
        $table = $actual->getOrCreateTable('users');
        $table->addColumn(new ColumnDefinition('legacy', 'varchar', 50));

        $changes = $this->engine->diff($expected, $actual);

        $this->assertCount(1, $changes);
        $this->assertSame(ChangeOperation::DropColumn, $changes[0]->operation);
    }

    #[Test]
    public function it_detects_create_table(): void
    {
        $expected = new SchemaIntent;
        $table = $expected->getOrCreateTable('posts');
        $table->addColumn(new ColumnDefinition('title', 'varchar', 255));

        $actual = new SchemaIntent;

        $changes = $this->engine->diff($expected, $actual);

        $this->assertCount(1, $changes);
        $this->assertSame(ChangeOperation::CreateTable, $changes[0]->operation);
        $this->assertArrayHasKey('title', $changes[0]->tableColumns);
    }
}
