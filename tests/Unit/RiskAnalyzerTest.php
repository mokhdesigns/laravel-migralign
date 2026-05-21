<?php

namespace MigrAlign\Tests\Unit;

use MigrAlign\Risk\RiskAnalyzer;
use MigrAlign\Tests\TestCase;
use MigrAlign\ValueObjects\ColumnDefinition;
use MigrAlign\ValueObjects\RiskLevel;
use PHPUnit\Framework\Attributes\Test;

class RiskAnalyzerTest extends TestCase
{
    private RiskAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new RiskAnalyzer;
    }

    #[Test]
    public function it_marks_nullable_column_add_as_safe(): void
    {
        $change = $this->analyzer->buildAddColumnChange('users', new ColumnDefinition(
            name: 'nickname',
            type: 'string',
            length: 100,
            nullable: true,
        ));

        $this->assertSame(RiskLevel::Safe, $change->risk);
    }

    #[Test]
    public function it_marks_not_null_column_add_as_risky(): void
    {
        $change = $this->analyzer->buildAddColumnChange('users', new ColumnDefinition(
            name: 'code',
            type: 'string',
            length: 10,
            nullable: false,
        ));

        $this->assertSame(RiskLevel::Risky, $change->risk);
        $this->assertNotNull($change->remediationSql);
    }

    #[Test]
    public function it_detects_varchar_narrowing_as_risky(): void
    {
        $expected = new ColumnDefinition('bio', 'string', 50, nullable: true);
        $actual = new ColumnDefinition('bio', 'varchar', 255, nullable: true);

        $change = $this->analyzer->buildModifyColumnChange('users', $expected, $actual);

        $this->assertSame(RiskLevel::Risky, $change->risk);
        $this->assertStringContainsString('reduced', strtolower($change->reason));
        $this->assertNotNull($change->precheckSql);
    }

    #[Test]
    public function it_detects_nullable_to_not_null_as_risky(): void
    {
        $expected = new ColumnDefinition('email', 'varchar', 255, nullable: false);
        $actual = new ColumnDefinition('email', 'varchar', 255, nullable: true);

        $change = $this->analyzer->buildModifyColumnChange('users', $expected, $actual);

        $this->assertSame(RiskLevel::Risky, $change->risk);
        $this->assertStringContainsString('NOT NULL', $change->reason);
    }

    #[Test]
    public function it_marks_drop_column_as_destructive(): void
    {
        $change = $this->analyzer->buildDropColumnChange('users', new ColumnDefinition('legacy', 'varchar', 50));

        $this->assertSame(RiskLevel::Destructive, $change->risk);
    }
}
