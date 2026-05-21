<?php

namespace MigrAlign\Scanning;

use Closure;
use Illuminate\Database\Schema\Builder;

class RecordingSchemaBuilder extends Builder
{
    /** @var list<RecordingBlueprint> */
    public array $blueprints = [];

    public function __construct($connection)
    {
        parent::__construct($connection);
    }

    public function create($table, Closure $callback)
    {
        $blueprint = $this->createBlueprint($table);
        $blueprint->create();

        if ($callback instanceof Closure) {
            $callback($blueprint);
        }

        $this->blueprints[] = $blueprint;
    }

    public function table($table, Closure $callback)
    {
        $blueprint = $this->createBlueprint($table);

        if ($callback instanceof Closure) {
            $callback($blueprint);
        }

        $this->blueprints[] = $blueprint;
    }

    public function drop($table)
    {
        $blueprint = $this->createBlueprint($table);
        $blueprint->drop();
        $this->blueprints[] = $blueprint;
    }

    public function dropIfExists($table)
    {
        $this->drop($table);
    }

    public function rename($from, $to)
    {
        $blueprint = $this->createBlueprint($from);
        $blueprint->rename($to);
        $this->blueprints[] = $blueprint;
    }

    protected function createBlueprint($table, ?Closure $callback = null)
    {
        return RecordingBlueprint::fromBuilder($this->connection, $table, $callback);
    }
}
