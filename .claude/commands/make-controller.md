Scaffold a new Laravel controller for BugSayMis following project conventions.

Usage: /make-controller <Name> [namespace]

Example: /make-controller RewardTypeController Rewards

Steps:
1. Create the controller in `app/Http/Controllers/<namespace>/<Name>.php`
2. Follow the project controller pattern:
   - Extend `Controller`
   - Import `Inertia\Inertia`
   - `index()` method returns `Inertia::render('Namespace/Index', [...])`
   - `store()` validates, creates, redirects with `->with('success', '...')`
   - `update()` validates, updates, redirects with `->with('success', '...')`
   - `destroy()` soft-deletes by setting `status = 'inactive'`, returns `back()->with('success', '...')`
3. Add routes to `routes/web.php` using `middleware(['auth', 'permission:...'])`
4. Create the Vue page scaffold at `resources/js/Pages/<Namespace>/Index.vue` with `<script setup>`, AdminLayout, and basic table structure

Ask the user what permission strings to use before creating routes.
