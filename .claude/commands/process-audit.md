Run a process audit on one or more BugSayMis modules — find redundancy, ambiguity, and inconsistency, then fix it.

Usage: /process-audit <module or list of files>

## Step 1 — Read everything in scope
Read all controllers, models, services, routes, and Vue pages for the named module.
Do not skip files — partial reads produce incomplete audits.

## Step 2 — Identify findings
Look for:
- **Duplicate logic**: same method or query copied across files
- **Schema/code mismatches**: validation enums that don't match DB columns, wrong field names
- **Hardcoded values**: magic numbers, hardcoded IDs or thresholds that belong in constants
- **N+1 queries**: missing `with()` eager loads
- **Dead code**: unused imports, variables, methods
- **Inconsistent patterns**: one controller uses a service, another duplicates the logic inline
- **Unsafe operations**: seeder truncates tables with live data, destructive migrations without guards

## Step 3 — Present findings, wait for approval
List each finding with:
- What the problem is
- Which file(s) and line(s)
- What the fix is

**Do not write any code until the user says "approved".**

## Step 4 — Implement fixes
Apply fixes in dependency order (models before controllers, services before controllers).
One logical change at a time — don't mix unrelated refactors in one edit.

## Step 5 — Verify
```bash
# Lint all modified PHP files
git diff --name-only | grep '\.php$' | xargs -I{} php -l {}
```
Run any affected migrations if schema changed.

## Step 6 — Commit and deploy
```bash
git checkout main && git merge junlou && git push origin main && git checkout junlou
```

## Audit checklist (quick reference)
- [ ] No private method duplicated across 2+ controllers → extract to Service
- [ ] Seeder uses `updateOrCreate`, not `truncate` + `create`
- [ ] Migration `down()` implemented
- [ ] All enum values in controller validation match actual DB column definition
- [ ] `full_load_threshold` uses `LoadComputationService::FULL_LOAD_THRESHOLD` constant
- [ ] AUH / HRA / HAC / RES designations guarded from direct assignment
- [ ] Vue form fields match controller validation keys exactly
