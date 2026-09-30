<?php

declare(strict_types=1);

namespace Jeytekdev\ExplainLint\Laravel\Testing;

use Jeytekdev\ExplainLint\Adapter\MySqlAdapter;
use Jeytekdev\ExplainLint\Adapter\PostgresAdapter;
use Jeytekdev\ExplainLint\Adapter\SqliteNoopAdapter;
use Jeytekdev\ExplainLint\Config\ConfigLoader;
use Jeytekdev\ExplainLint\Engine\ExplainRunner;
use Jeytekdev\ExplainLint\Fingerprint\SqlFingerprint;
use Jeytekdev\ExplainLint\Recorder\QueryLedger;
use Jeytekdev\ExplainLint\Recorder\QueryRecorder;
use Jeytekdev\ExplainLint\Rules\RuleEngine;
use Jeytekdev\ExplainLint\Severity;
use Jeytekdev\ExplainLint\Violation;

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
