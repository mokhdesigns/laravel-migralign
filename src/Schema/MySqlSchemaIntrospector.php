<?php

namespace MigrAlign\Schema;

use Illuminate\Database\Connection;
use MigrAlign\ValueObjects\ColumnDefinition;
use MigrAlign\ValueObjects\SchemaIntent;
use MigrAlign\ValueObjects\TableDefinition;

class MySqlSchemaIntrospector
{
    public function __construct(
        protected Connection $connection,
    ) {}

    /**
     * @param  list<string>  $ignoredTables
     */
    public function introspect(?string $tableFilter = null, array $ignoredTables = []): SchemaIntent
    {
        $intent = new SchemaIntent;
        $database = $this->connection->getDatabaseName();

        $tables = $this->connection->select(
            'SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = \'BASE TABLE\'',
            [$database]
        );

        foreach ($tables as $row) {
            $tableName = $row->TABLE_NAME;

            if (in_array($tableName, $ignoredTables, true)) {
                continue;
            }

            if ($tableFilter !== null && $tableName !== $tableFilter) {
                continue;
            }

            $table = new TableDefinition($tableName);

            foreach ($this->fetchColumns($database, $tableName) as $column) {
                $table->addColumn($column);
            }

            $intent->tables[$tableName] = $table;
        }

        return $intent;
    }

    public function tableExists(string $table): bool
    {
        $database = $this->connection->getDatabaseName();

        $result = $this->connection->selectOne(
            'SELECT COUNT(*) as count FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [$database, $table]
        );

        return (int) ($result->count ?? 0) > 0;
    }

    public function countViolations(string $sql): int
    {
        $result = $this->connection->selectOne($sql);

        if ($result === null) {
            return 0;
        }

        $values = (array) $result;

        return (int) (reset($values) ?: 0);
    }

    /**
     * @return list<ColumnDefinition>
     */
    protected function fetchColumns(string $database, string $table): array
    {
        $rows = $this->connection->select(
            'SELECT COLUMN_NAME as column_name,
                    DATA_TYPE as data_type,
                    COLUMN_TYPE as column_type,
                    IS_NULLABLE as is_nullable,
                    COLUMN_DEFAULT as column_default,
                    CHARACTER_MAXIMUM_LENGTH as character_maximum_length,
                    NUMERIC_PRECISION as numeric_precision,
                    NUMERIC_SCALE as numeric_scale,
                    COLUMN_COMMENT as column_comment
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$database, $table]
        );

        return array_map(
            fn (object $row) => ColumnDefinition::fromInformationSchema($row),
            $rows
        );
    }
}
