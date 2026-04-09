PHP syntax-check all modified files in the current git working tree.

Steps:
1. Run `git diff --name-only` to get the list of changed files
2. Filter for `.php` files only
3. Run `php -l <file>` on each one
4. Report any syntax errors found, along with the file path and line number
5. If all files pass, confirm "All modified PHP files passed syntax check."

If no PHP files are modified, say so and exit cleanly.
