<?php

namespace MigrAlign\Tests\Feature;

use MigrAlign\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SyncMigrationsCommandTest extends TestCase
{
    #[Test]
    public function command_is_registered(): void
    {
        $this->assertTrue(
            collect($this->app['Illuminate\Contracts\Console\Kernel']->all())
                ->has('migralign:sync')
        );
    }

    #[Test]
    public function it_rejects_non_mysql_connections(): void
    {
        $this->artisan('migralign:sync', ['--dry-run' => true, '--connection' => 'testing'])
            ->expectsOutputToContain('supports MySQL')
            ->assertExitCode(1);
    }
}
