<?php

declare(strict_types=1);

namespace Saas\Core;

/**
 * Safe background task executor for the SaaS platform.
 *
 * Isolates each task so one failure never stops the entire run.
 * Tracks consecutive failures and applies automatic backoff.
 *
 * Feature #3:  Cron Auto-Recovery
 * Feature #11: Background Task Safety
 */
class BackgroundTaskRunner
{
    private const MAX_CONSECUTIVE_FAILURES = 3;

    /** @var array<string, array{attempts: int, last_error: string, last_run: int}> */
    private array $taskState = [];

    /**
     * Run a single callable safely. Never throws.
     *
     * @return array{ok: bool, result: mixed, error: string, skipped: bool, attempts: int}
     */
    public function run(callable $task, string $taskId, string $tid = ''): array
    {
        if ($this->isInBackoff($taskId)) {
            $attempts = $this->taskState[$taskId]['attempts'];
            StructuredLogger::cron('task.backoff', 'skipped', $tid, [
                'task'                => $taskId,
                'consecutive_failures'=> $attempts,
            ]);
            return [
                'ok'       => false,
                'result'   => null,
                'error'    => "Task '{$taskId}' in backoff after {$attempts} consecutive failures.",
                'skipped'  => true,
                'attempts' => $attempts,
            ];
        }

        try {
            $result = $task();
            $this->taskState[$taskId] = ['attempts' => 0, 'last_error' => '', 'last_run' => time()];
            StructuredLogger::cron('task.success', 'ok', $tid, ['task' => $taskId]);
            return ['ok' => true, 'result' => $result, 'error' => '', 'skipped' => false, 'attempts' => 0];
        } catch (\Throwable $e) {
            $this->recordFailure($taskId, $e->getMessage());
            $attempts = $this->taskState[$taskId]['attempts'];
            ErrorLogger::logThrowable($e, $tid);
            StructuredLogger::cron('task.failed', 'error', $tid, [
                'task'                => $taskId,
                'error'               => $e->getMessage(),
                'consecutive_failures'=> $attempts,
                'in_backoff'          => $this->isInBackoff($taskId) ? 'yes' : 'no',
            ]);
            return [
                'ok'       => false,
                'result'   => null,
                'error'    => $e->getMessage(),
                'skipped'  => false,
                'attempts' => $attempts,
            ];
        }
    }

    /**
     * Run multiple tasks, fully isolated from each other.
     *
     * @param  array<string, callable> $tasks
     * @return array<string, array>
     */
    public function runAll(array $tasks, string $tid = ''): array
    {
        $results = [];
        foreach ($tasks as $taskId => $task) {
            $results[$taskId] = $this->run($task, (string)$taskId, $tid);
        }
        return $results;
    }

    public function isInBackoff(string $taskId): bool
    {
        return isset($this->taskState[$taskId])
            && $this->taskState[$taskId]['attempts'] >= self::MAX_CONSECUTIVE_FAILURES;
    }

    public function resetTask(string $taskId): void
    {
        unset($this->taskState[$taskId]);
    }

    public function resetAll(): void
    {
        $this->taskState = [];
    }

    public function getTaskState(string $taskId = ''): array
    {
        if ($taskId !== '') {
            return $this->taskState[$taskId] ?? ['attempts' => 0, 'last_error' => '', 'last_run' => 0];
        }
        return $this->taskState;
    }

    private function recordFailure(string $taskId, string $error): void
    {
        if (!isset($this->taskState[$taskId])) {
            $this->taskState[$taskId] = ['attempts' => 0, 'last_error' => '', 'last_run' => 0];
        }
        $this->taskState[$taskId]['attempts']++;
        $this->taskState[$taskId]['last_error'] = $error;
        $this->taskState[$taskId]['last_run']   = time();
    }
}
