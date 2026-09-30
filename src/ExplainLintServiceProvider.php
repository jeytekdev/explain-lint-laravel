<?php

declare(strict_types=1);

namespace Jeytekdev\ExplainLint\Laravel;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;

final class ExplainLintServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!$this->shouldCapture()) {
            return;
        }

        $listener = new QueryListener();
        $this->app['events']->listen(QueryExecuted::class, [$listener, 'handle']);
    }

    /**
     * Only captures while the app is actually under test (or explicitly
     * forced via EXPLAIN_LINT_FORCE) — this provider auto-discovers, so it
     * boots on every request/command in a normal app, and DB::listen()
     * capture has a real (small but non-zero) per-query cost not worth
     * paying outside a test run.
     */
    private function shouldCapture(): bool
    {
        return $this->app->runningUnitTests() || filter_var(env('EXPLAIN_LINT_FORCE', false), FILTER_VALIDATE_BOOLEAN);
    }
}
