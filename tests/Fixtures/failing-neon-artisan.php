<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// Keep the HTTP fake in the child process launched by the real scheduler.
define('ARTISAN_BINARY', __FILE__);

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

Http::preventStrayRequests();
Http::fake(fn () => throw new ConnectionException('Neon polling regression failure'));

exit($kernel->handle(new ArgvInput, new ConsoleOutput));
