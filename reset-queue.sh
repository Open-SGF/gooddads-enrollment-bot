#!/usr/bin/env bash

set -e

SAIL="./vendor/bin/sail"

echo "🧹 Clearing config, cache and routes..."
$SAIL artisan config:clear
$SAIL artisan cache:clear
$SAIL artisan route:clear

echo "🔄 Regenerating autoloader..."
$SAIL composer dump-autoload

echo "🗑️  Flushing and clearing queue..."
$SAIL artisan queue:flush
$SAIL artisan queue:clear
$SAIL artisan queue:restart

echo "🗄️  Truncating hash and failed jobs tables..."
$SAIL artisan tinker --execute="DB::table('neon_participant_hashes')->truncate(); DB::table('failed_jobs')->truncate();"

echo "📋 Resetting log file..."
> storage/logs/laravel.log

echo "✅ Done! Tailing log..."
tail -f storage/logs/laravel.log