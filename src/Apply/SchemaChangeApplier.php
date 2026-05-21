<?php

namespace MigrAlign\Apply;

use Illuminate\Database\Connection;
use MigrAlign\ValueObjects\ChangeOperation;
use MigrAlign\ValueObjects\ColumnDefinition;
use MigrAlign\ValueObjects\SchemaChange;

class SchemaChangeApplier
{
    public function __construct(
        protected Connection $connection,
    ) {}

    public function apply(SchemaChange $change): void
    {
        $sql = $this->toSql($change);

        if ($sql === null) {
            throw new \RuntimeException("Cannot generate SQL for: {$change->description()}");
        }

        $this->connection->statement($sql);
    }

    public function toSql(SchemaChange $change): ?string
    {
        return match ($change->operation) {
            ChangeOperation::AddColumn => $this->addColumnSql($change),
            ChangeOperation::ModifyColumn => $this->modifyColumnSql($change),
            ChangeOperation::DropColumn => $this->dropColumnSql($change),
            ChangeOperation::RenameColumn => $this->renameColumnSql($change),
            ChangeOperation::CreateTable => $this->createTableSql($change),
            ChangeOperation::DropTable => "DROP TABLE `{$change->table}`",
            default => null,
        };
    }

    protected function addColumnSql(SchemaChange $change): ?string
    {
        $column = $change->expected;

        if (! $column) {
            return null;
        }

        return sprintf(
            'ALTER TABLE `%s` ADD COLUMN %s',
            $change->table,
            $this->formatColumnDefinition($column)
        );
    }

    protected function modifyColumnSql(SchemaChange $change): ?string
    {
        $column = $change->expected;

        if (! $column) {
            return null;
        }

        return sprintf(
            'ALTER TABLE `%s` MODIFY COLUMN %s',
            $change->table,
            $this->formatColumnDefinition($column)
        );
    }

    protected function dropColumnSql(SchemaChange $change): ?string
    {
        if (! $change->column) {
            return null;
        }

        return sprintf(
            'ALTER TABLE `%s` DROP COLUMN `%s`',
            $change->table,
            $change->column
        );
    }

    protected function renameColumnSql(SchemaChange $change): ?string
    {
        if (! $change->column || ! $change->renameTo) {
            return null;
        }

        $column = $change->expected ?? $change->actual;

        if (! $column) {
            return sprintf(
                'ALTER TABLE `%s` RENAME COLUMN `%s` TO `%s`',
                $change->table,
                $change->column,
                $change->renameTo
            );
        }

        return sprintf(
            'ALTER TABLE `%s` CHANGE `%s` `%s` %s',
            $change->table,
            $change->column,
            $change->renameTo,
            $this->formatColumnType($column)
        );
    }

    protected function createTableSql(SchemaChange $change): ?string
    {
        if ($change->tableColumns === null || $change->tableColumns === []) {
            return sprintf(
                'CREATE TABLE `%s` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY)',
                $change->table
            );
        }

        $definitions = [];

        foreach ($change->tableColumns as $column) {
            $definitions[] = $this->formatColumnDefinition($column);
        }

        return sprintf(
            'CREATE TABLE `%s` (%s)',
            $change->table,
            implode(', ', $definitions)
        );
    }

    protected function formatColumnDefinition(ColumnDefinition $column): string
    {
        $parts = [
            '`'.$column->name.'`',
            $this->formatColumnType($column),
        ];

        $nullable = $column->nullable && ! $column->autoIncrement;

        if (! $nullable) {
            $parts[] = 'NOT NULL';
        } else {
            $parts[] = 'NULL';
        }

        if ($column->default !== null) {
            $default = $this->formatDefault($column->default);
            $parts[] = "DEFAULT {$default}";
        } elseif ($nullable) {
            $parts[] = 'DEFAULT NULL';
        }

        if ($column->autoIncrement) {
            $parts[] = 'AUTO_INCREMENT PRIMARY KEY';
        }

        return implode(' ', $parts);
    }

    protected function formatColumnType(ColumnDefinition $column): string
    {
        $type = strtoupper($column->fullType());

        return trim($type);
    }

    protected function formatDefault(mixed $default): string
    {
        if ($default === null) {
            return 'NULL';
        }

        if (is_numeric($default)) {
            return (string) $default;
        }

        if (in_array(strtoupper((string) $default), ['CURRENT_TIMESTAMP', 'NULL'], true)) {
            return strtoupper((string) $default);
        }

        return "'".str_replace("'", "''", (string) $default)."'";
    }
}
