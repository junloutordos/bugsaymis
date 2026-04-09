Create and run a new database migration for the BugSayMis project.

Usage: /migrate <description of what to add/change>

Steps to follow:
1. Determine the correct migration filename: `YYYY_MM_DD_HHMMSS_description.php`
   - Use today's date and a sequential time (e.g., 000001, 000002)
   - Description should be snake_case (e.g., `add_salary_grade_to_users_table`)
2. Write the migration file at `database/migrations/<filename>.php`
   - Use `Schema::table()` for modifying existing tables
   - Use `Schema::create()` for new tables
   - Always implement `down()` to reverse the migration
   - Use `->after('column_name')` when adding columns to existing tables
3. Run it via Docker:
   ```bash
   cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/<filename>.php"
   ```
4. Report the result.

Ask the user to confirm before running if the migration is destructive (drop column, drop table, truncate).
