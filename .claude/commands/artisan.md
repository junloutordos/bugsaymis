Run a Laravel Artisan command inside the Docker container.

Usage: /artisan <command>

Example: /artisan migrate:status
Example: /artisan make:model HR/MyModel -m
Example: /artisan tinker

Always run Artisan through the `php` Docker service:
```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan $ARGUMENTS"
```

If no arguments are given, run `php artisan list` to show available commands.

After running a migration, confirm the result and report the migration name and status.
