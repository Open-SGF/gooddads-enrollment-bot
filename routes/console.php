<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('neon:poll-participants --quiet')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(config('logging.scheduler_output'));
