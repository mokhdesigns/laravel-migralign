<?php

namespace MigrAlign\ValueObjects;

class SyncReport
{
    /** @var list<SchemaChange> */
    public array $applied = [];

    /** @var list<SchemaChange> */
    public array $skipped = [];

    /** @var list<SchemaChange> */
    public array $pending = [];

    /** @var list<string> */
    public array $errors = [];

    public function addApplied(SchemaChange $change): void
    {
        $this->applied[] = $change;
    }

    public function addSkipped(SchemaChange $change): void
    {
        $this->skipped[] = $change;
    }

    public function addPending(SchemaChange $change): void
    {
        $this->pending[] = $change;
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }
}
