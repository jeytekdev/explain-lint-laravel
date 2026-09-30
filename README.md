# jeytekdev/explain-lint-laravel

Laravel bridge for [jeytekdev/explain-lint](https://github.com/jeytekdev/explain-lint/blob/master/packages/core/README.md) — re-runs `EXPLAIN` against every query your test suite executes, and fails the build on full table scans, lost indexes, filesort and temporary tables.

## Install (2 minutes)

```bash
composer require --dev jeytekdev/explain-lint-laravel
```

The service provider auto-discovers via `extra.laravel.providers` — nothing to register manually. It hooks `DB::listen()` and only captures queries while `$app->runningUnitTests()` is true (or `EXPLAIN_LINT_FORCE=true`), so there's no overhead outside your test suite.

Wire up the PHPUnit extension:

```bash
vendor/bin/explain-lint explain-lint:install
```

Run this from your **project root** (where `composer.json`/`vendor/` live) — every path it touches (`phpunit.xml`, the new `explain-lint.php`) is resolved relative to the current working directory, not to `vendor/bin/`.

Or add it manually to `phpunit.xml`:

```xml
<extensions>
    <bootstrap class="Jeytekdev\ExplainLint\PHPUnit\ExplainLintExtension">
        <parameter name="config" value="explain-lint.php"/>
    </bootstrap>
</extensions>
```

Set the connection driver to match your database in the generated `explain-lint.php`:

```php
'connections' => [
    'default' => [
        'driver' => 'mysql', // or 'pgsql'
    ],
],
```

Then run your suite as usual — no other code changes needed:

```bash
vendor/bin/phpunit
# or, with Pest:
vendor/bin/pest
```

## Reading the report

A violation is printed as a block, grouped by test, with the info needed to act on it — including the fingerprint you'd copy into an allowlist entry:

```
explain-lint found 1 issue(s) in 1 test(s):

Tests\Feature\OrdersTest::test_pending_orders_are_listed
  [error] Full table scan on orders
      table:       orders
      rows:        48213
      query:       select * from orders where status = ?
      hint:        Add an index covering the query's WHERE/JOIN/ORDER BY columns, or check
                   why an existing index isn't used (leading wildcard LIKE, a function/cast
                   on the column, implicit type mismatch).
      fingerprint: 4f6a1c3e9d2b7a805e4f1c9b6d3a2e7f8c0b1a5d
```

- In `mode => 'warn'` this is informational only — the build stays green.
- In `mode => 'strict'`, any `[error]`-severity violation fails the run.

## Allowlisting a known-OK query

Two ways, both in `explain-lint.php`, both require a non-empty reason:

```php
// Every violation on this table, regardless of query:
'allowlist' => [
    'audit_log' => 'Intentional full scan for the nightly export — JIRA-123',
],

// One specific query, by the fingerprint shown in the report above:
'allowlist_fingerprints' => [
    '4f6a1c3e9d2b7a805e4f1c9b6d3a2e7f8c0b1a5d' => 'Known slow report query — JIRA-456',
],
```

## Optional: per-test assertions

If you'd rather assert explicitly inside a specific test instead of relying on suite-wide enforcement (or you're on a PHPUnit version predating the Extension/Event API), use the testing trait:

```php
use Jeytekdev\ExplainLint\Laravel\Testing\ExplainLintTesting;

final class CheckoutTest extends TestCase
{
    use ExplainLintTesting;

    public function test_checkout_query_has_no_regressions(): void
    {
        $this->get('/checkout');

        $this->assertNoQueryRegressions();
    }
}
```

## Running under Codeception

This package only handles capture (`DB::listen()`) — the report step is
core's PHPUnit `<extensions>` mechanism, registered via `phpunit.xml`.

**If your suite runs via `vendor/bin/codecept run` instead of
`vendor/bin/phpunit`/`pest`, that mechanism never fires** — Codeception 5
doesn't bootstrap PHPUnit's native extension system. `DB::listen()` will
still capture every query, but nothing will ever be analyzed or printed:
no error, no warning, just a report that never appears.

Install [`jeytekdev/explain-lint-codeception`](https://github.com/jeytekdev/explain-lint/blob/master/packages/codeception/README.md) too,
and register it in `codeception.yml` instead of `phpunit.xml`. Use
`explain-lint:install --config-only` (not the plain form) to generate
`explain-lint.php` without also wiring `phpunit.xml`, since Codeception never
reads that file.

## CI

Relying solely on the in-process `exit(1)` from the PHPUnit run is a single point of failure if anything in your pipeline swallows PHPUnit's exit code — run the check as a separate second step:

```yaml
- run: vendor/bin/phpunit
- run: vendor/bin/explain-lint explain-lint:check
```

`explain-lint:check` needs a JUnit report to read, so enable it in config:

```php
'report' => [
    'junit' => 'build/explain-lint.xml',
],
```

## Troubleshooting

**A table with very few rows still gets flagged as `error`.** Table-size-based suppression (tiny/small tables never trigger scan rules) relies on `information_schema.TABLES.TABLE_ROWS` on MySQL, which is a cached estimate — on a freshly migrated test database it's often `NULL` until `ANALYZE TABLE` runs, and an unknown size is treated as "could be large" on purpose (so real regressions aren't silently hidden by a stale stat). If this shows up a lot on your test DB, either run `ANALYZE TABLE <name>` as part of your test setup, or allowlist the specific table/query.

**`Could not locate the Composer autoloader`** when running `vendor/bin/explain-lint`. If the package was installed via a `path` repository, `vendor/jeytekdev/explain-lint` is a symlink — this is resolved correctly as of the current release, but if you're on an older checkout, update `jeytekdev/explain-lint`.

**Connection resolution.** `DB::listen()` fires with the query's `connectionName`, matched against `connections.<name>` in your config, falling back to `connections.default` if there's no exact match — you don't need one entry per Laravel connection unless you actually want different thresholds per connection.

## License

MIT
