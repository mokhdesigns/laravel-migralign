<?php

namespace MigrAlign\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use MigrAlign\Scanning\MigrationScanner;
use MigrAlign\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MigrationScannerTest extends TestCase
{
    #[Test]
    public function it_builds_schema_intent_from_migration_files(): void
    {
        $scanner = new MigrationScanner(new Filesystem);

        $intent = $scanner->scan(__DIR__.'/../fixtures/migrations', null, ['migrations'], $this->app['db']->connection('testing'));

        $this->assertNotNull($intent->getTable('users'));
        $this->assertNotNull($intent->getTable('users')->getColumn('email'));
        $this->assertNotNull($intent->getTable('users')->getColumn('bio'));
        $this->assertSame(500, $intent->getTable('users')->getColumn('bio')->length);
    }

    #[Test]
    public function it_filters_by_migration_name(): void
    {
        $scanner = new MigrationScanner(new Filesystem);

        $intent = $scanner->scan(__DIR__.'/../fixtures/migrations', 'create_users', ['migrations'], $this->app['db']->connection('testing'));

        $this->assertNotNull($intent->getTable('users'));
    }

    #[Test]
    public function it_treats_id_column_as_not_nullable(): void
    {
        $scanner = new MigrationScanner(new Filesystem);

        $intent = $scanner->scan(__DIR__.'/../fixtures/migrations', 'create_patients', ['migrations'], $this->app['db']->connection('testing'));

        $id = $intent->getTable('patients')?->getColumn('id');

        $this->assertNotNull($id);
        $this->assertFalse($id->nullable);
        $this->assertTrue($id->autoIncrement);
        $this->assertTrue($id->unsigned);
    }
}
