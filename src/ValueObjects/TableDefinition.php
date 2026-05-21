<?php

namespace MigrAlign\ValueObjects;

class TableDefinition
{
    /** @var array<string, ColumnDefinition> */
    public array $columns = [];

    public function __construct(
        public readonly string $name,
    ) {}

    public function addColumn(ColumnDefinition $column): void
    {
        $this->columns[$column->name] = $column;
    }

    public function removeColumn(string $name): void
    {
        unset($this->columns[$name]);
    }

    public function renameColumn(string $from, string $to): void
    {
        if (! isset($this->columns[$from])) {
            return;
        }

        $column = $this->columns[$from];
        $this->columns[$to] = new ColumnDefinition(
            name: $to,
            type: $column->type,
            length: $column->length,
            precision: $column->precision,
            scale: $column->scale,
            nullable: $column->nullable,
            default: $column->default,
            unsigned: $column->unsigned,
            autoIncrement: $column->autoIncrement,
            comment: $column->comment,
            enumValues: $column->enumValues,
        );
        unset($this->columns[$from]);
    }

    public function getColumn(string $name): ?ColumnDefinition
    {
        return $this->columns[$name] ?? null;
    }

    /**
     * @return array<string, ColumnDefinition>
     */
    public function columnMap(): array
    {
        return $this->columns;
    }
}
