<?php

namespace MigrAlign\ValueObjects;

class SchemaChange
{
    public function __construct(
        public readonly ChangeOperation $operation,
        public readonly string $table,
        public readonly RiskLevel $risk,
        public readonly ?string $column = null,
        public readonly ?ColumnDefinition $expected = null,
        public readonly ?ColumnDefinition $actual = null,
        public readonly ?string $renameTo = null,
        public readonly string $reason = '',
        public readonly ?string $precheckSql = null,
        public readonly ?string $remediationSql = null,
        /** @var array<string, ColumnDefinition>|null */
        public readonly ?array $tableColumns = null,
    ) {}

    public function description(): string
    {
        return match ($this->operation) {
            ChangeOperation::AddColumn => "Add column `{$this->column}` to `{$this->table}`",
            ChangeOperation::ModifyColumn => "Modify column `{$this->column}` on `{$this->table}`",
            ChangeOperation::DropColumn => "Drop column `{$this->column}` from `{$this->table}`",
            ChangeOperation::RenameColumn => "Rename column `{$this->column}` to `{$this->renameTo}` on `{$this->table}`",
            ChangeOperation::CreateTable => "Create table `{$this->table}`",
            ChangeOperation::DropTable => "Drop table `{$this->table}`",
        };
    }
}
