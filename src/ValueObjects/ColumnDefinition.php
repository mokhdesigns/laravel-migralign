<?php

namespace MigrAlign\ValueObjects;

use Illuminate\Database\Schema\ColumnDefinition as LaravelColumnDefinition;

class ColumnDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly bool $nullable = true,
        public readonly mixed $default = null,
        public readonly bool $unsigned = false,
        public readonly bool $autoIncrement = false,
        public readonly ?string $comment = null,
        public readonly ?array $enumValues = null,
    ) {}

    public function fullType(): string
    {
        $type = strtolower($this->type);

        return match ($type) {
            'string', 'varchar', 'char' => $this->length
                ? sprintf('%s(%d)', $type === 'string' ? 'varchar' : $type, $this->length)
                : 'varchar(255)',
            'decimal' => $this->precision !== null
                ? sprintf('decimal(%d,%d)', $this->precision, $this->scale ?? 0)
                : 'decimal(8,2)',
            'enum' => $this->enumValues
                ? 'enum('.implode(',', array_map(fn ($v) => "'{$v}'", $this->enumValues)).')'
                : 'enum()',
            'integer', 'int' => 'int'.($this->unsigned ? ' unsigned' : ''),
            'biginteger', 'bigint' => 'bigint'.($this->unsigned ? ' unsigned' : ''),
            'smallinteger', 'smallint' => 'smallint'.($this->unsigned ? ' unsigned' : ''),
            'tinyinteger', 'tinyint' => 'tinyint'.($this->unsigned ? ' unsigned' : ''),
            'boolean', 'bool' => 'tinyint(1)',
            'text', 'mediumtext', 'longtext' => $type,
            'json' => 'json',
            'datetime', 'timestamp', 'date', 'time' => $type,
            'float', 'double' => $type,
            'uuid' => 'char(36)',
            'id' => 'bigint unsigned',
            'foreignid' => 'bigint unsigned',
            default => $type,
        };
    }

    public function normalizedType(): string
    {
        $type = strtolower($this->type);

        return match ($type) {
            'string' => 'varchar',
            'integer', 'int' => 'int',
            'biginteger', 'bigint', 'id', 'foreignid' => 'bigint',
            'smallinteger' => 'smallint',
            'tinyinteger', 'bool', 'boolean' => 'tinyint',
            default => $type,
        };
    }

    public function effectiveLength(): ?int
    {
        if ($this->length !== null) {
            return $this->length;
        }

        return match ($this->normalizedType()) {
            'varchar' => 255,
            'int' => 10,
            'bigint' => 20,
            'tinyint' => 3,
            'smallint' => 5,
            default => null,
        };
    }

    public static function fromSchemaColumn(LaravelColumnDefinition $column): self
    {
        $attributes = $column->getAttributes();

        return self::fromBlueprintAttributes((string) ($attributes['name'] ?? ''), $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromBlueprintAttributes(string $name, array $attributes): self
    {
        $name = $name !== '' ? $name : (string) ($attributes['name'] ?? '');

        return new self(
            name: $name,
            type: (string) ($attributes['type'] ?? 'string'),
            length: isset($attributes['length']) ? (int) $attributes['length'] : null,
            precision: isset($attributes['precision']) ? (int) $attributes['precision'] : null,
            scale: isset($attributes['scale']) ? (int) $attributes['scale'] : null,
            nullable: (bool) ($attributes['nullable'] ?? true),
            default: $attributes['default'] ?? null,
            unsigned: (bool) ($attributes['unsigned'] ?? false),
            autoIncrement: (bool) ($attributes['autoIncrement'] ?? false),
            comment: $attributes['comment'] ?? null,
            enumValues: $attributes['allowed'] ?? null,
        );
    }

    /**
     * @param  object{column_name: string, data_type: string, column_type: string, is_nullable: string, column_default: ?string, character_maximum_length: ?int, numeric_precision: ?int, numeric_scale: ?int, column_comment: ?string}  $row
     */
    public static function fromInformationSchema(object $row): self
    {
        $type = strtolower($row->data_type);
        $length = $row->character_maximum_length !== null ? (int) $row->character_maximum_length : null;
        $enumValues = null;

        if ($type === 'enum' && preg_match('/^enum\((.*)\)$/i', $row->column_type, $matches)) {
            $enumValues = array_map(
                fn (string $v) => trim($v, "'\""),
                str_getcsv($matches[1], ',', "'")
            );
        }

        $unsigned = str_contains(strtolower($row->column_type), 'unsigned');

        return new self(
            name: $row->column_name,
            type: $type,
            length: $length,
            precision: $row->numeric_precision !== null ? (int) $row->numeric_precision : null,
            scale: $row->numeric_scale !== null ? (int) $row->numeric_scale : null,
            nullable: strtoupper($row->is_nullable) === 'YES',
            default: $row->column_default,
            unsigned: $unsigned,
            autoIncrement: str_contains(strtolower($row->column_type), 'auto_increment'),
            comment: $row->column_comment ?? null,
            enumValues: $enumValues,
        );
    }
}
