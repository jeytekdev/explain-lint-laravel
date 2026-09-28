<?php

declare(strict_types=1);

namespace ExplainLint\Laravel;

use ExplainLint\Recorder\CapturedQuery;
use ExplainLint\Recorder\QueryRecorder;
use Illuminate\Database\Events\QueryExecuted;

final class QueryListener
{
    public function handle(QueryExecuted $event): void
    {
        $pdo = $event->connection->getPdo();
        $recorder = QueryRecorder::instance();

        $recorder->record(new CapturedQuery(
            $event->sql,
            $this->normalizeBindings($event->bindings),
            $pdo,
            $event->connectionName ?: $event->connection->getName(),
            $recorder->currentPhase(),
        ));
    }

    /**
     * @param array<int|string, mixed> $bindings
     * @return array<int|string, array{value: mixed, type: int}>
     */
    private function normalizeBindings(array $bindings): array
    {
        $normalized = [];
        foreach ($bindings as $key => $value) {
            $normalized[$key] = [
                'value' => $value,
                'type' => is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR,
            ];
        }

        return $normalized;
    }
}
