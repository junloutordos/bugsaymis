#!/bin/bash

# Start cron
service cron start

# Start php-fpm
php-fpm
