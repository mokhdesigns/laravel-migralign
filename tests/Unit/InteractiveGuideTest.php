<?php

namespace MigrAlign\Tests\Unit;

use MigrAlign\Console\InteractiveGuide;
use MigrAlign\Schema\MySqlSchemaIntrospector;
use MigrAlign\Tests\TestCase;
use MigrAlign\ValueObjects\ChangeOperation;
use MigrAlign\ValueObjects\ColumnDefinition;
use MigrAlign\ValueObjects\RiskLevel;
use MigrAlign\ValueObjects\SchemaChange;
use PHPUnit\Framework\Attributes\Test;

class InteractiveGuideTest extends TestCase
{
    #[Test]
    public function it_auto_applies_safe_changes_when_enabled(): void
    {
        $guide = new InteractiveGuide(new MySqlSchemaIntrospector($this->app['db']->connection()));

        $change = new SchemaChange(
            operation: ChangeOperation::AddColumn,
            table: 'users',
            risk: RiskLevel::Safe,
            column: 'nickname',
            expected: new ColumnDefinition('nickname', 'varchar', 100, nullable: true),
        );

        $this->assertTrue($guide->shouldAutoApply($change, true));
        $this->assertFalse($guide->requiresConfirmation($change));
    }

    #[Test]
    public function it_requires_confirmation_for_risky_changes(): void
    {
        $guide = new InteractiveGuide(new MySqlSchemaIntrospector($this->app['db']->connection()));

        $change = new SchemaChange(
            operation: ChangeOperation::DropColumn,
            table: 'users',
            risk: RiskLevel::Destructive,
            column: 'legacy',
        );

        $this->assertTrue($guide->requiresConfirmation($change));
        $this->assertFalse($guide->shouldAutoApply($change, true));
    }
}
