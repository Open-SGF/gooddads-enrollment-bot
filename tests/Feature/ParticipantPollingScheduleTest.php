<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

it('polls participants every minute', function (): void {
    $pollingEvent = collect(resolve(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains($event->command, 'neon:poll-participants'));

    expect($pollingEvent)->toBeInstanceOf(Event::class)
        ->and($pollingEvent?->expression)->toBe('* * * * *');
});
