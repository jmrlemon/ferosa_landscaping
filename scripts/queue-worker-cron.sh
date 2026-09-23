#!/bin/sh

set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
cd "$SCRIPT_DIR/.."

exec /usr/bin/flock -n storage/framework/queue-cron.lock \
    /usr/bin/php artisan queue:work \
    --queue=sms,default \
    --stop-when-empty \
    --tries=3 \
    --timeout=120 \
    --no-interaction
