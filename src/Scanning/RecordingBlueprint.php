<?php

namespace MigrAlign\Scanning;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition as LaravelColumnDefinition;
use Illuminate\Support\Fluent;

class RecordingBlueprint extends Blueprint
{
    public static function fromBuilder(Connection $connection, string $table, ?Closure $callback = null): self
    {
        if (BlueprintCompatibility::blueprintTakesConnectionFirst()) {
            return new self($connection, $table, $callback);
        }

        return new self($table, $callback);
    }

    public function getTableName(): string
    {
        return $this->table;
    }

    /**
     * @return list<LaravelColumnDefinition>
     */
    public function schemaColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function commandList(): array
    {
        return array_map(function (Fluent $command): array {
            return $command->getAttributes();
        }, $this->commands);
    }
}
