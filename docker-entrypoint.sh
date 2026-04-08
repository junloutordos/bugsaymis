#!/bin/bash

# Export container environment variables so cron and supervisor can use them
printenv | grep -v "no_proxy" > /etc/environment

# Start all services via supervisord (php-fpm + cron + queue worker)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/bugsaymis.conf
