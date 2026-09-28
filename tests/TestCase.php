<?php

declare(strict_types=1);

namespace ExplainLint\Laravel\Tests;

use ExplainLint\Laravel\ExplainLintServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ExplainLintServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'explain_lint_mysql');
        $app['config']->set('database.connections.explain_lint_mysql', [
            'driver' => 'mysql',
            'host' => getenv('EXPLAIN_LINT_TEST_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('EXPLAIN_LINT_TEST_MYSQL_PORT') ?: '3306',
            'database' => getenv('EXPLAIN_LINT_TEST_MYSQL_DATABASE') ?: 'explain_lint_test',
            'username' => getenv('EXPLAIN_LINT_TEST_MYSQL_USER') ?: 'root',
            'password' => getenv('EXPLAIN_LINT_TEST_MYSQL_PASSWORD') ?: 'root',
            'charset' => 'utf8mb4',
        ]);

        $app['config']->set('app.env', 'testing');
    }

    /**
     * The MySQL-backed scenario is skipped (not failed) when no test
     * database is reachable, so `composer test` stays green for
     * contributors without Docker running locally.
     */
    protected function skipIfNoMysql(): void
    {
        try {
            $this->app['db']->connection()->getPdo();
        } catch (\Throwable $e) {
            self::markTestSkipped('No reachable MySQL test database: ' . $e->getMessage());
        }
    }
}
