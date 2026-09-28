<?php

declare(strict_types=1);

namespace ExplainLint\Laravel\Testing;

use ExplainLint\Adapter\MySqlAdapter;
use ExplainLint\Adapter\PostgresAdapter;
use ExplainLint\Adapter\SqliteNoopAdapter;
use ExplainLint\Config\ConfigLoader;
use ExplainLint\Engine\ExplainRunner;
use ExplainLint\Fingerprint\SqlFingerprint;
use ExplainLint\Recorder\QueryLedger;
use ExplainLint\Recorder\QueryRecorder;
use ExplainLint\Rules\RuleEngine;
use ExplainLint\Severity;
use ExplainLint\Violation;

/**
 * Optional per-test alternative to the suite-wide PHPUnit extension: call
 * assertNoQueryRegressions() explicitly inside a test to check whatever
 * queries have run so far, and drain them from the buffer.
 *
 * Prefer ExplainLintExtension for suite-wide enforcement — reach for this
 * trait when you want a regression check scoped to one specific test
 * without touching phpunit.xml, or on PHPUnit versions predating the
 * Extension/Event API.
 */
trait ExplainLintTesting
{
    protected function assertNoQueryRegressions(?string $configPath = null): void
    {
        $config = ConfigLoader::load(ConfigLoader::resolvePath($configPath, getcwd() ?: '.'));
        $ledger = new QueryLedger();
        $runner = new ExplainRunner(adapters: [
            new MySqlAdapter(),
            new PostgresAdapter(),
            new SqliteNoopAdapter(),
        ]);
        $ruleEngine = new RuleEngine($config);

        $queries = QueryRecorder::instance()->flush();

        /** @var list<Violation> $violations */
        $violations = [];

        foreach ($queries as $query) {
            $fingerprint = SqlFingerprint::hash($query->sql);
            if (!$ledger->shouldAnalyze($fingerprint)) {
                continue;
            }

            $outcome = $runner->run($query);
            $verdict = $ruleEngine->evaluate($query, $outcome, $fingerprint);

            foreach ($verdict->violations as $violation) {
                if ($violation->severity === Severity::Error) {
                    $violations[] = $violation;
                }
            }
        }

        self::assertSame([], array_map(
            static fn (Violation $violation): string => $violation->describe() . ' — ' . $violation->normalizedSql,
            $violations
        ));
    }
}
