#!/bin/bash

# Export container environment variables to a file so cron can use them
printenv | grep -v "no_proxy" > /etc/environment

# Start cron
service cron start

# Start php-fpm
php-fpm
