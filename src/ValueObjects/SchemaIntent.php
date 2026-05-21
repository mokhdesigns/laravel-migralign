<?php

namespace MigrAlign\ValueObjects;

class SchemaIntent
{
    /** @var array<string, TableDefinition> */
    public array $tables = [];

    public function getOrCreateTable(string $name): TableDefinition
    {
        if (! isset($this->tables[$name])) {
            $this->tables[$name] = new TableDefinition($name);
        }

        return $this->tables[$name];
    }

    public function getTable(string $name): ?TableDefinition
    {
        return $this->tables[$name] ?? null;
    }

    public function dropTable(string $name): void
    {
        unset($this->tables[$name]);
    }

    /**
     * @return array<string, TableDefinition>
     */
    public function tableMap(): array
    {
        return $this->tables;
    }
}
