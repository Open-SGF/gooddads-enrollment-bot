#!/usr/bin/env bash

set -e

SAIL="./vendor/bin/sail"

echo "🧹 Clearing config and routes..."
$SAIL artisan config:clear
$SAIL artisan route:clear

echo "🔄 Regenerating autoloader..."
$SAIL composer dump-autoload

echo "🗑️  Flushing and clearing queue..."
$SAIL artisan queue:flush
$SAIL artisan queue:clear
$SAIL artisan cache:clear
$SAIL artisan cache:clear --locks
$SAIL artisan queue:restart

echo "🗄️  Truncating failed jobs table..."
$SAIL artisan tinker --execute="DB::table('failed_jobs')->truncate();"

echo "📋 Resetting log file..."
> storage/logs/laravel.log

echo "✅ Done! Tailing log..."
tail -f storage/logs/laravel.log