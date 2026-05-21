<?php

namespace MigrAlign\Tests\Unit;

use MigrAlign\Apply\SchemaChangeApplier;
use MigrAlign\Risk\RiskAnalyzer;
use MigrAlign\Tests\TestCase;
use MigrAlign\ValueObjects\ChangeOperation;
use MigrAlign\ValueObjects\ColumnDefinition;
use MigrAlign\ValueObjects\RiskLevel;
use MigrAlign\ValueObjects\SchemaChange;
use PHPUnit\Framework\Attributes\Test;

class SchemaChangeApplierTest extends TestCase
{
    #[Test]
    public function it_generates_add_column_sql(): void
    {
        $applier = new SchemaChangeApplier($this->app['db']->connection());
        $analyzer = new RiskAnalyzer;

        $change = $analyzer->buildAddColumnChange('users', new ColumnDefinition(
            name: 'nickname',
            type: 'varchar',
            length: 100,
            nullable: true,
        ));

        $sql = $applier->toSql($change);

        $this->assertStringContainsString('ALTER TABLE `users` ADD COLUMN', $sql);
        $this->assertStringContainsString('`nickname`', $sql);
        $this->assertStringContainsString('NULL', $sql);
    }

    #[Test]
    public function it_generates_modify_column_sql(): void
    {
        $applier = new SchemaChangeApplier($this->app['db']->connection());
        $analyzer = new RiskAnalyzer;

        $change = $analyzer->buildModifyColumnChange(
            'users',
            new ColumnDefinition('bio', 'varchar', 500, nullable: true),
            new ColumnDefinition('bio', 'varchar', 255, nullable: true),
        );

        $sql = $applier->toSql($change);

        $this->assertStringContainsString('ALTER TABLE `users` MODIFY COLUMN', $sql);
        $this->assertStringContainsString('varchar(500)', strtolower($sql));
    }

    #[Test]
    public function it_generates_create_table_sql(): void
    {
        $applier = new SchemaChangeApplier($this->app['db']->connection());

        $change = new SchemaChange(
            operation: ChangeOperation::CreateTable,
            table: 'posts',
            risk: RiskLevel::Safe,
            tableColumns: [
                'title' => new ColumnDefinition('title', 'varchar', 255, nullable: false),
            ],
        );

        $sql = $applier->toSql($change);

        $this->assertStringContainsString('CREATE TABLE `posts`', $sql);
        $this->assertStringContainsString('`title`', $sql);
    }

    #[Test]
    public function it_generates_valid_auto_increment_primary_key_for_create_table(): void
    {
        $applier = new SchemaChangeApplier($this->app['db']->connection());

        $change = new SchemaChange(
            operation: ChangeOperation::CreateTable,
            table: 'patients',
            risk: RiskLevel::Safe,
            tableColumns: [
                'id' => new ColumnDefinition(
                    name: 'id',
                    type: 'bigInteger',
                    nullable: false,
                    unsigned: true,
                    autoIncrement: true,
                ),
                'created_at' => new ColumnDefinition('created_at', 'timestamp', nullable: true),
                'updated_at' => new ColumnDefinition('updated_at', 'timestamp', nullable: true),
            ],
        );

        $sql = $applier->toSql($change);

        $this->assertStringContainsString('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql);
        $this->assertStringNotContainsString('`id` BIGINT UNSIGNED NULL', $sql);
    }
}
