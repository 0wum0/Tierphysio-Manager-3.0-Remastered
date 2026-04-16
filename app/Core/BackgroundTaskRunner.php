<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Safe background task executor.
 *
 * Guarantees that:
 *  - One task or tenant crash does NOT stop the whole execution loop.
 *  - Each task runs in full isolation via try-catch.
 *  - Repeated failures are tracked and the task is placed in a
 *    temporary backoff state to prevent endless error storms.
 *  - All failures are logged via ErrorLogger and StructuredLogger.
 *
 * Feature #3:  Cron Auto-Recovery System
 * Feature #11: Background Task Safety
 *
 * Usage:
 *   $runner = new BackgroundTaskRunner();
 *   $result = $runner->run(fn() => $myService->doWork(), 'my_job', $tid);
 *   if (!$result['ok']) { ... handle error ... }
 *
 *   // Or run many tasks, all isolated:
 *   $results = $runner->runAll([
 *       'job_a' => fn() => $serviceA->run(),
 *       'job_b' => fn() => $serviceB->run(),
 *   ], $tid);
 */
class BackgroundTaskRunner
{
    /**
     * A task is put in backoff after this many consecutive failures.
     * While in backoff the task is skipped (not re-executed).
     * Call resetTask() after a manual fix to restore execution.
     */
    private const MAX_CONSECUTIVE_FAILURES = 3;

    /**
     * Per-task state tracking.
     *
     * @var array<string, array{attempts: int, last_error: string, last_run: int}>
     */
    private array $taskState = [];

    /* ──────────────────────────────────────────────────────────
       Public API
    ────────────────────────────────────────────────────────── */

    /**
     * Run a single callable safely.
     *
     * @param callable $task    The work to execute.
     * @param string   $taskId  Stable identifier used for failure tracking and logging.
     * @param string   $tid     Tenant ID for log context (optional).
     *
     * @return array{
     *   ok: bool,
     *   result: mixed,
     *   error: string,
     *   skipped: bool,
     *   attempts: int
     * }
     */
    public function run(callable $task, string $taskId, string $tid = ''): array
    {
        // ── Backoff check ─────────────────────────────────────
        if ($this->isInBackoff($taskId)) {
            $attempts = $this->taskState[$taskId]['attempts'];
            StructuredLogger::cron(
                'task.backoff',
                'skipped',
                $tid,
                ['task' => $taskId, 'consecutive_failures' => $attempts]
            );
            return [
                'ok'       => false,
                'result'   => null,
                'error'    => "Task '{$taskId}' is in backoff after {$attempts} consecutive failures. Call resetTask() after fixing the root cause.",
                'skipped'  => true,
                'attempts' => $attempts,
            ];
        }

        // ── Execute ───────────────────────────────────────────
        try {
            $result = $task();

            // Success → reset failure counter
            $this->taskState[$taskId] = [
                'attempts'   => 0,
                'last_error' => '',
                'last_run'   => time(),
            ];

            StructuredLogger::cron('task.success', 'ok', $tid, ['task' => $taskId]);

            return [
                'ok'       => true,
                'result'   => $result,
                'error'    => '',
                'skipped'  => false,
                'attempts' => 0,
            ];

        } catch (\Throwable $e) {
            $this->recordFailure($taskId, $e->getMessage());
            $attempts = $this->taskState[$taskId]['attempts'];

            ErrorLogger::logThrowable($e, $tid);
            StructuredLogger::cron(
                'task.failed',
                'error',
                $tid,
                [
                    'task'                => $taskId,
                    'error'               => $e->getMessage(),
                    'consecutive_failures'=> $attempts,
                    'in_backoff'          => $this->isInBackoff($taskId) ? 'yes' : 'no',
                ]
            );

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
     * Run multiple tasks. Each task runs in full isolation.
     * A failure in one task does not affect the others.
     *
     * @param array<string, callable> $tasks  taskId → callable map
     * @param string                  $tid    Tenant ID for log context
     *
     * @return array<string, array>  taskId → result map
     */
    public function runAll(array $tasks, string $tid = ''): array
    {
        $results = [];
        foreach ($tasks as $taskId => $task) {
            $results[$taskId] = $this->run($task, (string)$taskId, $tid);
        }
        return $results;
    }

    /* ──────────────────────────────────────────────────────────
       State inspection & control
    ────────────────────────────────────────────────────────── */

    /**
     * Get the current state of a single task (or all tasks).
     *
     * @return array{attempts: int, last_error: string, last_run: int}|array
     */
    public function getTaskState(string $taskId = ''): array
    {
        if ($taskId !== '') {
            return $this->taskState[$taskId] ?? [
                'attempts'   => 0,
                'last_error' => '',
                'last_run'   => 0,
            ];
        }
        return $this->taskState;
    }

    /**
     * Reset a task's failure counter (e.g., after a manual fix).
     * This removes the task from backoff immediately.
     */
    public function resetTask(string $taskId): void
    {
        unset($this->taskState[$taskId]);
    }

    /**
     * Reset ALL task state (useful at the start of a fresh dispatcher run).
     */
    public function resetAll(): void
    {
        $this->taskState = [];
    }

    /**
     * Returns true if the task has hit the consecutive failure limit.
     */
    public function isInBackoff(string $taskId): bool
    {
        return isset($this->taskState[$taskId])
            && $this->taskState[$taskId]['attempts'] >= self::MAX_CONSECUTIVE_FAILURES;
    }

    /* ──────────────────────────────────────────────────────────
       Internal helpers
    ────────────────────────────────────────────────────────── */

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
