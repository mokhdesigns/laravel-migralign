<?php

namespace MigrAlign\Console;

use Illuminate\Console\OutputStyle;
use MigrAlign\Schema\MySqlSchemaIntrospector;
use MigrAlign\ValueObjects\RiskLevel;
use MigrAlign\ValueObjects\SchemaChange;
use Symfony\Component\Console\Question\ChoiceQuestion;

class InteractiveGuide
{
    public function __construct(
        protected MySqlSchemaIntrospector $introspector,
    ) {}

    public function confirmChange(OutputStyle $output, SchemaChange $change, bool $force): bool
    {
        if ($force) {
            $output->writeln('<comment>--force:</comment> applying '.$change->description());

            return true;
        }

        $output->writeln('');
        $output->writeln('<fg=yellow>Risky change detected</>');
        $output->writeln('  '.$change->description());
        $output->writeln('  Risk: '.$change->risk->value);
        $output->writeln('  Reason: '.$change->reason);

        if ($change->precheckSql) {
            $violations = $this->introspector->countViolations($change->precheckSql);
            $output->writeln("  Pre-check violations: {$violations}");

            if ($violations > 0) {
                $output->writeln('  <fg=red>Existing data may block this change.</>');
            }
        }

        if ($change->remediationSql) {
            $output->writeln('  <fg=cyan>Suggested remediation:</>');
            foreach (explode("\n", $change->remediationSql) as $line) {
                $output->writeln('    '.$line);
            }
        }

        $helper = $output->getHelper('question');
        $question = new ChoiceQuestion(
            'How do you want to proceed?',
            [
                'apply' => 'Apply this change now',
                'skip' => 'Skip this change',
                'abort' => 'Abort entire sync',
            ],
            'skip'
        );

        $answer = $helper->ask($output, $question);

        if ($answer === 'abort') {
            throw new \RuntimeException('Sync aborted by user.');
        }

        return $answer === 'apply';
    }

    public function shouldAutoApply(SchemaChange $change, bool $autoApplySafe): bool
    {
        if (! $autoApplySafe) {
            return false;
        }

        return $change->risk === RiskLevel::Safe;
    }

    public function requiresConfirmation(SchemaChange $change): bool
    {
        return in_array($change->risk, [RiskLevel::Risky, RiskLevel::Destructive], true);
    }
}
