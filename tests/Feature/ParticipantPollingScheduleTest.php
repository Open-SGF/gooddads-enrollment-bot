<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('polls participants every minute', function (): void {
    $pollingEvent = collect(resolve(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains($event->command, 'neon:poll-participants'));

    expect($pollingEvent)->toBeInstanceOf(Event::class)
        ->and($pollingEvent?->expression)->toBe('* * * * *')
        ->and($pollingEvent?->withoutOverlapping)->toBeTrue();
});

it('skips overlapping polls until the active poll releases its lock', function (): void {
    $pollingEvent = collect(resolve(Schedule::class)->events())
        ->firstOrFail(fn (Event $event): bool => str_contains($event->command, 'neon:poll-participants'));
    $nextRun = clone $pollingEvent;

    try {
        expect($pollingEvent->shouldSkipDueToOverlapping())->toBeFalse()
            ->and($nextRun->shouldSkipDueToOverlapping())->toBeTrue();

        $pollingEvent->mutex->forget($pollingEvent);

        expect($nextRun->shouldSkipDueToOverlapping())->toBeFalse();
    } finally {
        $pollingEvent->mutex->forget($pollingEvent);
    }
});
