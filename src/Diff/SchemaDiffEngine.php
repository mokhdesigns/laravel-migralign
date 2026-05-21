<?php

namespace MigrAlign\Diff;

use MigrAlign\Risk\RiskAnalyzer;
use MigrAlign\ValueObjects\ChangeOperation;
use MigrAlign\ValueObjects\ColumnDefinition;
use MigrAlign\ValueObjects\SchemaChange;
use MigrAlign\ValueObjects\SchemaIntent;

class SchemaDiffEngine
{
    public function __construct(
        protected RiskAnalyzer $riskAnalyzer,
    ) {}

    /**
     * @return list<SchemaChange>
     */
    public function diff(SchemaIntent $expected, SchemaIntent $actual, ?string $tableFilter = null): array
    {
        $changes = [];

        $tableNames = array_unique(array_merge(
            array_keys($expected->tableMap()),
            array_keys($actual->tableMap())
        ));

        sort($tableNames);

        foreach ($tableNames as $tableName) {
            if ($tableFilter !== null && $tableName !== $tableFilter) {
                continue;
            }

            $expectedTable = $expected->getTable($tableName);
            $actualTable = $actual->getTable($tableName);

            if ($expectedTable && ! $actualTable) {
                $changes[] = new SchemaChange(
                    operation: ChangeOperation::CreateTable,
                    table: $tableName,
                    risk: $this->riskAnalyzer->assessCreateTable(),
                    reason: 'Table exists in migrations but not in database.',
                    tableColumns: $expectedTable->columnMap(),
                );

                continue;
            }

            if (! $expectedTable && $actualTable) {
                $changes[] = new SchemaChange(
                    operation: ChangeOperation::DropTable,
                    table: $tableName,
                    risk: $this->riskAnalyzer->assessDropTable(),
                    reason: 'Table exists in database but not in migrations.',
                );

                continue;
            }

            if (! $expectedTable || ! $actualTable) {
                continue;
            }

            $changes = array_merge(
                $changes,
                $this->diffColumns($tableName, $expectedTable->columnMap(), $actualTable->columnMap())
            );
        }

        return $changes;
    }

    /**
     * @param  array<string, ColumnDefinition>  $expectedColumns
     * @param  array<string, ColumnDefinition>  $actualColumns
     * @return list<SchemaChange>
     */
    protected function diffColumns(string $table, array $expectedColumns, array $actualColumns): array
    {
        $changes = [];

        foreach ($expectedColumns as $name => $expected) {
            if (! isset($actualColumns[$name])) {
                $changes[] = $this->riskAnalyzer->buildAddColumnChange($table, $expected);

                continue;
            }

            $actual = $actualColumns[$name];

            if ($this->columnsDiffer($expected, $actual)) {
                $changes[] = $this->riskAnalyzer->buildModifyColumnChange($table, $expected, $actual);
            }
        }

        foreach ($actualColumns as $name => $actual) {
            if (! isset($expectedColumns[$name])) {
                $changes[] = $this->riskAnalyzer->buildDropColumnChange($table, $actual);
            }
        }

        return $changes;
    }

    protected function columnsDiffer(ColumnDefinition $expected, ColumnDefinition $actual): bool
    {
        if ($expected->normalizedType() !== $actual->normalizedType()) {
            return true;
        }

        if ($expected->effectiveLength() !== $actual->effectiveLength()) {
            return true;
        }

        if ($expected->nullable !== $actual->nullable) {
            return true;
        }

        if ($expected->unsigned !== $actual->unsigned) {
            return true;
        }

        if ($this->defaultsDiffer($expected, $actual)) {
            return true;
        }

        if ($expected->enumValues !== null && $actual->enumValues !== null) {
            return $expected->enumValues !== $actual->enumValues;
        }

        return false;
    }

    protected function defaultsDiffer(ColumnDefinition $expected, ColumnDefinition $actual): bool
    {
        $expectedDefault = $this->normalizeDefault($expected->default);
        $actualDefault = $this->normalizeDefault($actual->default);

        return $expectedDefault !== $actualDefault;
    }

    protected function normalizeDefault(mixed $default): ?string
    {
        if ($default === null) {
            return null;
        }

        if ($default instanceof \Illuminate\Database\Query\Expression) {
            return (string) $default;
        }

        return (string) $default;
    }
}
