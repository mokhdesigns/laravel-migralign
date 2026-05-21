<?php

namespace MigrAlign\Risk;

use MigrAlign\ValueObjects\ChangeOperation;
use MigrAlign\ValueObjects\ColumnDefinition;
use MigrAlign\ValueObjects\RiskLevel;
use MigrAlign\ValueObjects\SchemaChange;

class RiskAnalyzer
{
    public function assessCreateTable(): RiskLevel
    {
        return RiskLevel::Safe;
    }

    public function assessDropTable(): RiskLevel
    {
        return RiskLevel::Destructive;
    }

    public function buildAddColumnChange(string $table, ColumnDefinition $expected): SchemaChange
    {
        $risk = $expected->nullable ? RiskLevel::Safe : RiskLevel::Risky;
        $reason = $expected->nullable
            ? 'Adding a nullable column is safe.'
            : 'Adding a NOT NULL column without default may fail on existing rows.';

        $precheck = ! $expected->nullable
            ? "SELECT COUNT(*) AS violations FROM `{$table}`"
            : null;

        $remediation = ! $expected->nullable
            ? "-- Table has existing rows; add a default in the migration or backfill after adding the column:\nALTER TABLE `{$table}` ADD COLUMN `{$expected->name}` ... NULL;\nUPDATE `{$table}` SET `{$expected->name}` = <value>;\nALTER TABLE `{$table}` MODIFY COLUMN `{$expected->name}` ... NOT NULL;"
            : null;

        return new SchemaChange(
            operation: ChangeOperation::AddColumn,
            table: $table,
            risk: $risk,
            column: $expected->name,
            expected: $expected,
            reason: $reason,
            precheckSql: $precheck,
            remediationSql: $remediation,
        );
    }

    public function buildModifyColumnChange(string $table, ColumnDefinition $expected, ColumnDefinition $actual): SchemaChange
    {
        [$risk, $reason, $precheck, $remediation] = $this->assessModification($table, $expected, $actual);

        return new SchemaChange(
            operation: ChangeOperation::ModifyColumn,
            table: $table,
            risk: $risk,
            column: $expected->name,
            expected: $expected,
            actual: $actual,
            reason: $reason,
            precheckSql: $precheck,
            remediationSql: $remediation,
        );
    }

    public function buildDropColumnChange(string $table, ColumnDefinition $actual): SchemaChange
    {
        return new SchemaChange(
            operation: ChangeOperation::DropColumn,
            table: $table,
            risk: RiskLevel::Destructive,
            column: $actual->name,
            actual: $actual,
            reason: 'Dropping a column permanently removes data.',
            remediationSql: "-- Backup column data if needed:\n-- CREATE TABLE `{$table}_{$actual->name}_backup` AS SELECT id, `{$actual->name}` FROM `{$table}`;",
        );
    }

    /**
     * @return array{0: RiskLevel, 1: string, 2: ?string, 3: ?string}
     */
    protected function assessModification(string $table, ColumnDefinition $expected, ColumnDefinition $actual): array
    {
        $reasons = [];
        $risk = RiskLevel::Safe;
        $precheckParts = [];
        $remediationParts = [];

        if ($this->isTypeNarrowing($expected, $actual)) {
            $risk = RiskLevel::Risky;
            $reasons[] = 'Column type or length is being reduced.';
            $precheckParts[] = $this->lengthOverflowPrecheck($table, $expected, $actual);
            $remediationParts[] = $this->truncateRemediation($table, $expected);
        } elseif ($expected->normalizedType() !== $actual->normalizedType()) {
            $risk = RiskLevel::Risky;
            $reasons[] = 'Column data type is changing.';
            $remediationParts[] = "-- Verify values convert cleanly:\n-- SELECT `{$expected->name}`, COUNT(*) FROM `{$table}` GROUP BY `{$expected->name}` LIMIT 20;";
        }

        if ($actual->nullable && ! $expected->nullable) {
            $risk = $this->higherRisk($risk, RiskLevel::Risky);
            $reasons[] = 'Column will become NOT NULL.';
            $precheckParts[] = "SELECT COUNT(*) AS violations FROM `{$table}` WHERE `{$expected->name}` IS NULL";
            $remediationParts[] = "UPDATE `{$table}` SET `{$expected->name}` = <default> WHERE `{$expected->name}` IS NULL;";
        }

        if ($this->isEnumContraction($expected, $actual)) {
            $risk = RiskLevel::Risky;
            $reasons[] = 'ENUM values are being removed.';
            $allowed = implode("','", $expected->enumValues ?? []);
            $precheckParts[] = "SELECT COUNT(*) AS violations FROM `{$table}` WHERE `{$expected->name}` NOT IN ('{$allowed}')";
            $remediationParts[] = "UPDATE `{$table}` SET `{$expected->name}` = '<allowed_value>' WHERE `{$expected->name}` NOT IN ('{$allowed}');";
        }

        if ($risk === RiskLevel::Safe && $expected->effectiveLength() > ($actual->effectiveLength() ?? 0)) {
            $reasons[] = 'Widening column — safe to apply.';
        }

        if ($reasons === []) {
            $reasons[] = 'Minor column attribute change.';
        }

        return [
            $risk,
            implode(' ', $reasons),
            $precheckParts !== [] ? $precheckParts[0] : null,
            $remediationParts !== [] ? implode("\n", $remediationParts) : null,
        ];
    }

    protected function higherRisk(RiskLevel $a, RiskLevel $b): RiskLevel
    {
        $order = [RiskLevel::Safe->value => 0, RiskLevel::Risky->value => 1, RiskLevel::Destructive->value => 2];

        return ($order[$a->value] ?? 0) >= ($order[$b->value] ?? 0) ? $a : $b;
    }

    protected function isTypeNarrowing(ColumnDefinition $expected, ColumnDefinition $actual): bool
    {
        $expectedLen = $expected->effectiveLength();
        $actualLen = $actual->effectiveLength();

        if ($expectedLen !== null && $actualLen !== null && $expectedLen < $actualLen) {
            return true;
        }

        $typeRank = ['tinyint' => 1, 'smallint' => 2, 'int' => 3, 'bigint' => 4];
        $e = $typeRank[$expected->normalizedType()] ?? null;
        $a = $typeRank[$actual->normalizedType()] ?? null;

        if ($e !== null && $a !== null && $e < $a) {
            return true;
        }

        return false;
    }

    protected function isEnumContraction(ColumnDefinition $expected, ColumnDefinition $actual): bool
    {
        if ($expected->enumValues === null || $actual->enumValues === null) {
            return false;
        }

        return count(array_diff($actual->enumValues, $expected->enumValues)) > 0;
    }

    protected function lengthOverflowPrecheck(string $table, ColumnDefinition $expected, ColumnDefinition $actual): string
    {
        $length = $expected->effectiveLength() ?? 255;

        if (in_array($expected->normalizedType(), ['varchar', 'char', 'string'], true)) {
            return "SELECT COUNT(*) AS violations FROM `{$table}` WHERE CHAR_LENGTH(`{$expected->name}`) > {$length}";
        }

        return "SELECT COUNT(*) AS violations FROM `{$table}` WHERE `{$expected->name}` IS NOT NULL";
    }

    protected function truncateRemediation(string $table, ColumnDefinition $expected): string
    {
        $length = $expected->effectiveLength() ?? 255;

        return "UPDATE `{$table}` SET `{$expected->name}` = LEFT(`{$expected->name}`, {$length}) WHERE CHAR_LENGTH(`{$expected->name}`) > {$length};";
    }
}
