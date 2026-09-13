<?php

declare(strict_types=1);

use Monolog\Formatter\JsonFormatter;
use Symfony\Component\Process\Process;

it('forwards the original polling exception as JSON through the quiet scheduler', function (): void {
    // A regular file stands in for the container's /proc/1/fd/2 log destination.
    $logPath = tempnam(sys_get_temp_dir(), 'scheduler-log-');

    try {
        $process = new Process([
            PHP_BINARY,
            base_path('tests/Fixtures/failing-neon-artisan.php'),
            'schedule:run',
            '--quiet',
        ], base_path(), [
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'LOG_CHANNEL' => 'stderr',
            'LOG_LEVEL' => 'info',
            'LOG_STDERR_FORMATTER' => JsonFormatter::class,
            'SCHEDULER_LOG_OUTPUT' => $logPath,
            'NEON_BASE_URL' => 'https://neon.invalid',
            'NEON_API_KEY' => 'scheduler-regression-secret',
            'SENTRY_DSN' => '',
            'SENTRY_LARAVEL_DSN' => '',
        ]);

        $process->mustRun();

        $output = file_get_contents($logPath);
        $records = array_map(
            fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
            explode("\n", mb_trim($output)),
        );

        expect($process->getOutput())->toBe('')
            ->and($output.$process->getErrorOutput())->not->toContain('scheduler-regression-secret')
            ->and(array_column($records, 'message'))->toContain('Neon polling regression failure')
            ->and($process->getErrorOutput())->toContain('failed with exit code [1]');
    } finally {
        unlink($logPath);
    }
});
