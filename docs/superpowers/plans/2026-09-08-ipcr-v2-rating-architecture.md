# IPCR V2 Rating Architecture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Shift Strategic Function ratings to the prior fiscal year's OPCR, and give Core/Support Function items an employee self-rating column group (required before submitting for rating) alongside the existing Division-Chief-only rating, mirroring IPCR V1's proven `self_*`/`sup_*` split.

**Architecture:** `StrategicFunctionService::ratingFiscalYear()` subtracts one from the IPCR's own current year before querying `OpcrIndicator`. `ipcr_v2_core_items`/`ipcr_v2_support_items` gain parallel `self_*` columns; `EmployeeIpcrV2Controller` computes `self_row_average` server-side on every save (recomputed from the item's merged current state, so partial saves never crash and completeness is trivially checkable); `submitForRating()` blocks until every item has one. The Vue rating tables gain a second Q/E/T/A column group with its own save action, mirroring the existing Division-Chief rating UI exactly (dropdown + dedicated Save button, not blur-autosave — ratings in this codebase are never blur-saved).

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia.js 2, MySQL 8.

**Spec:** `docs/superpowers/specs/2026-09-08-ipcr-v2-rating-architecture-design.md`

## Global Constraints

- `computeFinalRating()`, `IpcrV2SummaryService::buildRows()`, the Rating Summary table, and the PDF are never modified in this plan — they read only the existing official columns (`quality_rating`/`efficiency_rating`/`timeliness_rating`/`student_feedback_rating`/`supervisor_feedback_rating`/`im_development_rating`/`row_average`), untouched by this work.
- `DivisionChiefIpcrV2Controller::rateCoreItem()`/`rateSupportItem()` are never modified — the official rating path is completely unchanged; only regression-verified.
- Self-rating fields are `nullable` at the per-save validation level. `self_row_average` is recomputed from the item's full current state (existing DB values merged with whatever this request just changed) on every `updateCoreItem()`/`updateSupportItem()` call — this is deliberately unconditional and idempotent, not conditional on "did a self-rating field change this request," since recomputing from a shape that's still incomplete just yields `null` again at no cost.
- The self-rating UI action is a **dedicated Save button per rating group**, matching the existing Division-Chief rating UI exactly (`<select>` dropdowns + a "Save" button, via `useSubmit`) — not folded into the blur-triggered `saveEmployeeFields()` used for `target`/`actual_accomplishment`/`mov_link`. Every rating group in this table already works this way; self-rating follows the same convention rather than inventing a new one.
- `self_row_average` naming and the shared `self_timeliness_rating` column (used by both the WDP-tagged and CSC-teaching Core item shapes) come directly from the spec — do not rename.
- Run artisan/tests via: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan <command>"`.

---

## Task 1: Migration — self-rating columns + model updates

**Files:**
- Create: `database/migrations/2026_09_08_110000_add_self_ratings_to_ipcr_v2_items.php`
- Modify: `app/Models/IPCRV2/IpcrV2CoreItem.php:12-18`
- Modify: `app/Models/IPCRV2/IpcrV2SupportItem.php:12-16`
- Test: `tests/Feature/IPCRV2/IpcrV2MigrationsTest.php` (extend existing file)

**Interfaces:**
- Produces: `ipcr_v2_core_items` gains `self_quality_rating`, `self_efficiency_rating`, `self_student_feedback_rating`, `self_supervisor_feedback_rating`, `self_im_development_rating`, `self_timeliness_rating`, `self_row_average` (all nullable). `ipcr_v2_support_items` gains `self_quality_rating`, `self_efficiency_rating`, `self_timeliness_rating`, `self_row_average` (all nullable).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/IPCRV2/IpcrV2MigrationsTest.php` (add `use Illuminate\Support\Facades\Schema;` is already imported):

```php
public function test_ipcr_v2_items_have_self_rating_columns(): void
{
    $this->assertTrue(Schema::hasColumns('ipcr_v2_core_items', [
        'self_quality_rating', 'self_efficiency_rating', 'self_student_feedback_rating',
        'self_supervisor_feedback_rating', 'self_im_development_rating',
        'self_timeliness_rating', 'self_row_average',
    ]));
    $this->assertTrue(Schema::hasColumns('ipcr_v2_support_items', [
        'self_quality_rating', 'self_efficiency_rating', 'self_timeliness_rating', 'self_row_average',
    ]));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_ipcr_v2_items_have_self_rating_columns"`
Expected: FAIL — columns don't exist.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('self_quality_rating')->nullable()->after('remarks');
            $table->unsignedTinyInteger('self_efficiency_rating')->nullable()->after('self_quality_rating');
            $table->unsignedTinyInteger('self_student_feedback_rating')->nullable()->after('self_efficiency_rating');
            $table->unsignedTinyInteger('self_supervisor_feedback_rating')->nullable()->after('self_student_feedback_rating');
            $table->unsignedTinyInteger('self_im_development_rating')->nullable()->after('self_supervisor_feedback_rating');
            $table->unsignedTinyInteger('self_timeliness_rating')->nullable()->after('self_im_development_rating');
            $table->decimal('self_row_average', 4, 2)->nullable()->after('self_timeliness_rating');
        });

        Schema::table('ipcr_v2_support_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('self_quality_rating')->nullable()->after('remarks');
            $table->unsignedTinyInteger('self_efficiency_rating')->nullable()->after('self_quality_rating');
            $table->unsignedTinyInteger('self_timeliness_rating')->nullable()->after('self_efficiency_rating');
            $table->decimal('self_row_average', 4, 2)->nullable()->after('self_timeliness_rating');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->dropColumn([
                'self_quality_rating', 'self_efficiency_rating', 'self_student_feedback_rating',
                'self_supervisor_feedback_rating', 'self_im_development_rating',
                'self_timeliness_rating', 'self_row_average',
            ]);
        });

        Schema::table('ipcr_v2_support_items', function (Blueprint $table) {
            $table->dropColumn(['self_quality_rating', 'self_efficiency_rating', 'self_timeliness_rating', 'self_row_average']);
        });
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_08_110000_add_self_ratings_to_ipcr_v2_items.php"`

- [ ] **Step 4: Update the models**

`app/Models/IPCRV2/IpcrV2CoreItem.php` — replace `$fillable`:

```php
protected $fillable = [
    'ipcr_v2_id', 'employee_function_id', 'label', 'weight_percent', 'success_indicator',
    'target', 'actual_accomplishment', 'mov_link',
    'student_feedback_rating', 'supervisor_feedback_rating',
    'im_development_rating', 'quality_rating', 'efficiency_rating',
    'timeliness_rating', 'row_average', 'remarks',
    'self_quality_rating', 'self_efficiency_rating', 'self_student_feedback_rating',
    'self_supervisor_feedback_rating', 'self_im_development_rating',
    'self_timeliness_rating', 'self_row_average',
];

protected $casts = [
    'weight_percent' => 'decimal:2',
    'row_average' => 'decimal:2',
    'self_row_average' => 'decimal:2',
];
```

`app/Models/IPCRV2/IpcrV2SupportItem.php` — replace `$fillable`:

```php
protected $fillable = [
    'ipcr_v2_id', 'employee_function_id', 'label', 'success_indicator', 'target',
    'actual_accomplishment', 'mov_link',
    'quality_rating', 'efficiency_rating', 'timeliness_rating', 'row_average', 'remarks',
    'self_quality_rating', 'self_efficiency_rating', 'self_timeliness_rating', 'self_row_average',
];

protected $casts = [
    'row_average' => 'decimal:2',
    'self_row_average' => 'decimal:2',
];
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_ipcr_v2_items_have_self_rating_columns"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_08_110000_add_self_ratings_to_ipcr_v2_items.php app/Models/IPCRV2/IpcrV2CoreItem.php app/Models/IPCRV2/IpcrV2SupportItem.php tests/Feature/IPCRV2/IpcrV2MigrationsTest.php
git commit -m "feat(ipcr-v2): add self-rating columns to core/support items"
```

---

## Task 2: `StrategicFunctionService` — prior-year OPCR lookup

**Files:**
- Modify: `app/Services/IPCRV2/StrategicFunctionService.php:16-19`
- Test: `tests/Feature/IPCRV2/StrategicFunctionServiceTest.php` (rewrite existing tests)

**Interfaces:**
- Produces: `StrategicFunctionService::ratingFiscalYear(): ?int` (replaces `currentFiscalYear()`, which had zero external callers — confirmed via repo-wide grep).

- [ ] **Step 1: Rewrite the existing tests to the new prior-year semantics**

Replace the full contents of `tests/Feature/IPCRV2/StrategicFunctionServiceTest.php`:

```php
<?php

namespace Tests\Feature\IPCRV2;

use App\Models\AgencyOutcome;
use App\Models\IPCRRatingPeriod;
use App\Models\OPCR\OpcrIndicator;
use App\Services\IPCRV2\StrategicFunctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategicFunctionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rating_fiscal_year_returns_current_period_year_minus_one(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);

        $this->assertSame(2025, (new StrategicFunctionService())->ratingFiscalYear());
    }

    public function test_rating_fiscal_year_returns_null_when_no_current_period(): void
    {
        $this->assertNull((new StrategicFunctionService())->ratingFiscalYear());
    }

    public function test_returns_the_prior_fiscal_years_indicators_grouped_by_program(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);

        $programB = AgencyOutcome::create(['outcome' => 'B. STEM Promotion Program']);
        $programA = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);

        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $programB->id, 'description' => 'Indicator B1']);
        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $programA->id, 'description' => 'Indicator A1']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $programA->id, 'description' => 'Current year, excluded']);

        $result = (new StrategicFunctionService())->currentIndicators();

        $this->assertCount(2, $result);
        $this->assertSame('Indicator A1', $result->first()->description);
    }

    public function test_returns_empty_when_the_prior_fiscal_year_has_no_indicators(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);
        $program = AgencyOutcome::create(['outcome' => 'A']);
        OpcrIndicator::create(['fiscal_year' => 2026, 'agency_outcome_id' => $program->id, 'description' => 'Current year only, should not surface']);

        $result = (new StrategicFunctionService())->currentIndicators();

        $this->assertCount(0, $result);
    }

    public function test_current_indicators_resolve_dost_strategy_and_sub_strategy_text(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);

        $pillar = \App\Models\DostPillar::create(['name' => 'Pillar 1']);
        $strategy = \App\Models\DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1: Achieve quality science education']);
        \App\Models\DostSubStrategy::create(['dost_strategy_id' => $strategy->id, 'description' => 'Institutionalized FORWARD program']);

        $program = AgencyOutcome::create(['outcome' => 'A. STEM Secondary Education']);
        $program->dostStrategies()->attach($strategy->id);

        $performanceIndicator = \App\Models\PerformanceIndicator::create(['agency_outcome_id' => $program->id, 'description' => 'PI 1']);

        OpcrIndicator::create([
            'fiscal_year' => 2025,
            'agency_outcome_id' => $program->id,
            'performance_indicator_id' => $performanceIndicator->id,
            'description' => 'Indicator 1',
        ]);

        $result = (new StrategicFunctionService())->currentIndicators();

        $source = $result->first()->performanceIndicator?->agencyOutcome ?? $result->first()->agencyOutcome;
        $this->assertSame('Strategy 1: Achieve quality science education', $source->dost_strategy_names_joined);
        $this->assertSame('Institutionalized FORWARD program', $source->dost_sub_strategy_descriptions_joined);
    }

    public function test_current_indicators_carry_nested_rowspan_metadata_without_changing_program_order(): void
    {
        IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open', 'is_current' => true]);

        $pillar = \App\Models\DostPillar::create(['name' => 'Pillar 1']);
        $strategy1 = \App\Models\DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 1']);
        $strategy2 = \App\Models\DostStrategy::create(['dost_pillar_id' => $pillar->id, 'name' => 'Strategy 2']);

        $programA = AgencyOutcome::create(['outcome' => 'A. First Program']);
        $programA->dostStrategies()->attach($strategy1->id);

        $programB = AgencyOutcome::create(['outcome' => 'B. Second Program']);
        $programB->dostStrategies()->attach($strategy1->id);

        $programC = AgencyOutcome::create(['outcome' => 'C. Third Program']);
        $programC->dostStrategies()->attach($strategy2->id);

        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $programA->id, 'description' => 'Indicator A']);
        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $programB->id, 'description' => 'Indicator B']);
        OpcrIndicator::create(['fiscal_year' => 2025, 'agency_outcome_id' => $programC->id, 'description' => 'Indicator C']);

        $result = (new StrategicFunctionService())->currentIndicators();

        $this->assertSame(['Indicator A', 'Indicator B', 'Indicator C'], $result->pluck('description')->all());

        [$rowA, $rowB, $rowC] = $result->all();

        $this->assertSame(2, $rowA->strategy_rowspan);
        $this->assertSame(2, $rowA->sub_strategy_rowspan);
        $this->assertSame(1, $rowA->program_rowspan);

        $this->assertSame(0, $rowB->strategy_rowspan);
        $this->assertSame(0, $rowB->sub_strategy_rowspan);
        $this->assertSame(1, $rowB->program_rowspan);

        $this->assertSame(1, $rowC->strategy_rowspan);
        $this->assertSame(1, $rowC->sub_strategy_rowspan);
        $this->assertSame(1, $rowC->program_rowspan);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StrategicFunctionServiceTest"`
Expected: FAIL — `ratingFiscalYear()` doesn't exist yet, and `currentIndicators()` still reads the current year so the prior-year tests get the wrong rows.

- [ ] **Step 3: Implement**

In `app/Services/IPCRV2/StrategicFunctionService.php`, replace:

```php
public function currentFiscalYear(): ?int
{
    return IPCRRatingPeriod::current()->value('year');
}

public function currentIndicators(): Collection
{
    $year = $this->currentFiscalYear();
```

with:

```php
public function ratingFiscalYear(): ?int
{
    $currentYear = IPCRRatingPeriod::current()->value('year');

    return $currentYear ? $currentYear - 1 : null;
}

public function currentIndicators(): Collection
{
    $year = $this->ratingFiscalYear();
```

Also update the class doc-comment at the top of the file (lines 9-13) to describe the prior-year behavior:

```php
/**
 * Strategic Function is read-only, identical for every employee, and
 * inherited from the PRIOR fiscal year's OPCR — a year's own OPCR rating
 * isn't knowable until that year is essentially over, so an IPCR for
 * fiscal year N reads fiscal year N-1's OPCR indicators. No snapshot,
 * ever (spec: "true to all employees," campus-wide, not a per-employee
 * commitment).
 */
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StrategicFunctionServiceTest"`
Expected: PASS (all 6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/IPCRV2/StrategicFunctionService.php tests/Feature/IPCRV2/StrategicFunctionServiceTest.php
git commit -m "feat(ipcr-v2): source Strategic Function ratings from the prior fiscal year's OPCR"
```

---

## Task 3: `EmployeeIpcrV2Controller` — self-rating save

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php:111-143` (`updateCoreItem`, `updateSupportItem`)
- Test: `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php` (extend existing file)

**Interfaces:**
- Consumes: `IpcrV2CoreItem`/`IpcrV2SupportItem` `$fillable`/`$casts` from Task 1.
- Produces: `updateCoreItem()`/`updateSupportItem()` accept and persist `self_*` fields, recomputing `self_row_average` on every call.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php`:

```php
public function test_owner_can_save_a_self_rating_on_a_wdp_tagged_core_item(): void
{
    $employee = $this->employee();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
    $coreItem = $record->coreItems()->create(['label' => 'IT Management', 'weight_percent' => 100, 'success_indicator' => 'Systems maintained']);

    $response = $this->actingAs($employee)->put(route('employee-ipcr-v2.updateCoreItem', [$record->id, $coreItem->id]), [
        'self_quality_rating' => 4, 'self_efficiency_rating' => 5, 'self_timeliness_rating' => 3,
    ]);

    $response->assertRedirect();
    $fresh = $coreItem->fresh();
    $this->assertSame(4, $fresh->self_quality_rating);
    $this->assertSame('4.00', $fresh->self_row_average);
}

public function test_owner_can_save_a_self_rating_on_the_csc_teaching_rubric(): void
{
    $employee = $this->employee();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
    $coreItem = $record->coreItems()->create(['label' => 'Subject 1', 'weight_percent' => 100]);

    $response = $this->actingAs($employee)->put(route('employee-ipcr-v2.updateCoreItem', [$record->id, $coreItem->id]), [
        'self_student_feedback_rating' => 5, 'self_supervisor_feedback_rating' => 4,
        'self_im_development_rating' => 4, 'self_timeliness_rating' => 5,
    ]);

    $response->assertRedirect();
    $fresh = $coreItem->fresh();
    // 5*0.30 + 4*0.20 + 4*0.20 + 5*0.30 = 1.5 + 0.8 + 0.8 + 1.5 = 4.60
    $this->assertSame('4.60', $fresh->self_row_average);
}

public function test_self_row_average_stays_null_until_all_criteria_are_present(): void
{
    $employee = $this->employee();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
    $coreItem = $record->coreItems()->create(['label' => 'IT Management', 'weight_percent' => 100, 'success_indicator' => 'Systems maintained']);

    $this->actingAs($employee)->put(route('employee-ipcr-v2.updateCoreItem', [$record->id, $coreItem->id]), [
        'self_quality_rating' => 4,
    ]);

    $this->assertNull($coreItem->fresh()->self_row_average);
}

public function test_owner_can_save_a_self_rating_on_a_support_item(): void
{
    $employee = $this->employee();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = \App\Models\IPCRV2\IpcrV2Record::create(['user_id' => $employee->id, 'rating_period_id' => $period->id]);
    $supportItem = $record->supportItems()->create(['label' => 'Administrative']);

    $response = $this->actingAs($employee)->put(route('employee-ipcr-v2.updateSupportItem', [$record->id, $supportItem->id]), [
        'self_quality_rating' => 3, 'self_efficiency_rating' => 3, 'self_timeliness_rating' => 3,
    ]);

    $response->assertRedirect();
    $this->assertSame('3.00', $supportItem->fresh()->self_row_average);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EmployeeIpcrV2ControllerTest"`
Expected: FAIL — the 4 new tests fail (validation silently drops unknown fields, `self_row_average` never computed); other tests in the file still pass.

- [ ] **Step 3: Implement**

Replace `updateCoreItem()` and `updateSupportItem()` in `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php`:

```php
public function updateCoreItem(Request $request, int $id, IpcrV2CoreItem $coreItem)
{
    $record = IpcrV2Record::findOrFail($id);
    $this->workflow->assertOwner($request->user(), $record);
    $this->workflow->assertMutable($record);
    abort_if($coreItem->ipcr_v2_id !== $record->id, 404);

    if ($coreItem->success_indicator !== null) {
        $data = $request->validate([
            'target' => 'nullable|string|max:1000',
            'actual_accomplishment' => 'nullable|string|max:1000',
            'mov_link' => 'nullable|string|max:500',
            'self_quality_rating' => 'nullable|integer|min:1|max:5',
            'self_efficiency_rating' => 'nullable|integer|min:1|max:5',
            'self_timeliness_rating' => 'nullable|integer|min:1|max:5',
        ]);
    } else {
        $data = $request->validate([
            'target' => 'nullable|string|max:1000',
            'actual_accomplishment' => 'nullable|string|max:1000',
            'mov_link' => 'nullable|string|max:500',
            'self_student_feedback_rating' => 'nullable|integer|min:1|max:5',
            'self_supervisor_feedback_rating' => 'nullable|integer|min:1|max:5',
            'self_im_development_rating' => 'nullable|integer|min:1|max:5',
            'self_timeliness_rating' => 'nullable|integer|min:1|max:5',
        ]);
    }

    $coreItem->fill($data);

    if ($coreItem->success_indicator !== null) {
        $ratings = [$coreItem->self_quality_rating, $coreItem->self_efficiency_rating, $coreItem->self_timeliness_rating];
        $coreItem->self_row_average = in_array(null, $ratings, true) ? null : round(array_sum($ratings) / 3, 2);
    } else {
        $ratings = [
            $coreItem->self_student_feedback_rating, $coreItem->self_supervisor_feedback_rating,
            $coreItem->self_im_development_rating, $coreItem->self_timeliness_rating,
        ];
        $coreItem->self_row_average = in_array(null, $ratings, true)
            ? null
            : round($ratings[0] * 0.30 + $ratings[1] * 0.20 + $ratings[2] * 0.20 + $ratings[3] * 0.30, 2);
    }

    $coreItem->save();

    return back()->with('success', 'Updated.');
}

public function updateSupportItem(Request $request, int $id, IpcrV2SupportItem $supportItem)
{
    $record = IpcrV2Record::findOrFail($id);
    $this->workflow->assertOwner($request->user(), $record);
    $this->workflow->assertMutable($record);
    abort_if($supportItem->ipcr_v2_id !== $record->id, 404);

    $data = $request->validate([
        'target' => 'nullable|string|max:1000',
        'actual_accomplishment' => 'nullable|string|max:1000',
        'mov_link' => 'nullable|string|max:500',
        'self_quality_rating' => 'nullable|integer|min:1|max:5',
        'self_efficiency_rating' => 'nullable|integer|min:1|max:5',
        'self_timeliness_rating' => 'nullable|integer|min:1|max:5',
    ]);

    $supportItem->fill($data);

    $ratings = [$supportItem->self_quality_rating, $supportItem->self_efficiency_rating, $supportItem->self_timeliness_rating];
    $supportItem->self_row_average = in_array(null, $ratings, true) ? null : round(array_sum($ratings) / 3, 2);

    $supportItem->save();

    return back()->with('success', 'Updated.');
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EmployeeIpcrV2ControllerTest"`
Expected: PASS (full file, including the pre-existing `test_owner_can_set_a_mov_link_on_a_core_item`/`test_owner_can_set_a_target_on_a_support_item` — confirm those two still pass unchanged, since they exercise the same two methods without touching self-rating fields at all)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): employee self-rating on core/support items"
```

---

## Task 4: `EmployeeIpcrV2Controller::submitForRating()` — require self-rating

**Files:**
- Modify: `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php` (`submitForRating`, add `use Illuminate\Validation\ValidationException;`)
- Test: `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php` (extend existing file)

**Interfaces:**
- Consumes: `self_row_average` column from Task 1, populated via Task 3.
- Produces: `submitForRating()` throws `ValidationException` (key `self_rating`) when any Core/Support item lacks a self-rating.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php`:

```php
public function test_submit_for_rating_rejects_when_an_item_is_missing_a_self_rating(): void
{
    $employee = $this->employee();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = \App\Models\IPCRV2\IpcrV2Record::create([
        'user_id' => $employee->id, 'rating_period_id' => $period->id,
        'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
    ]);
    $record->coreItems()->create(['label' => 'Subject 1', 'weight_percent' => 100]);

    $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitRating', $record->id));

    $response->assertSessionHasErrors('self_rating');
    $this->assertSame('Targets Approved', $record->fresh()->status);
}

public function test_submit_for_rating_succeeds_once_all_items_have_a_self_rating(): void
{
    $employee = $this->employee();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    $record = \App\Models\IPCRV2\IpcrV2Record::create([
        'user_id' => $employee->id, 'rating_period_id' => $period->id,
        'status' => \App\Services\IPCRV2\IpcrV2WorkflowService::STATUS_TARGETS_APPROVED,
    ]);
    $record->coreItems()->create([
        'label' => 'IT Management', 'weight_percent' => 100, 'success_indicator' => 'x',
        'self_quality_rating' => 4, 'self_efficiency_rating' => 4, 'self_timeliness_rating' => 4, 'self_row_average' => 4.00,
    ]);

    $response = $this->actingAs($employee)->post(route('employee-ipcr-v2.submitRating', $record->id));

    $response->assertRedirect();
    $this->assertSame('Submitted for Rating', $record->fresh()->status);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EmployeeIpcrV2ControllerTest"`
Expected: FAIL — `test_submit_for_rating_rejects_when_an_item_is_missing_a_self_rating` fails (no such check exists yet, transition succeeds instead of being rejected).

- [ ] **Step 3: Implement**

Add `use Illuminate\Validation\ValidationException;` to the top of `app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php`, and replace `submitForRating()`:

```php
public function submitForRating(Request $request, int $id)
{
    $record = IpcrV2Record::findOrFail($id);
    $this->workflow->assertOwner($request->user(), $record);

    $record->loadMissing('coreItems', 'supportItems');
    $missing = $record->coreItems->whereNull('self_row_average')->count()
        + $record->supportItems->whereNull('self_row_average')->count();

    if ($missing > 0) {
        throw ValidationException::withMessages([
            'self_rating' => "{$missing} Core/Support item(s) still need a self-rating before you can submit for rating.",
        ]);
    }

    $data = $request->validate(['pin' => 'nullable|string']);
    $this->sigService->assertSigningPin($request->user(), $data['pin'] ?? null);

    $this->workflow->transition(
        $record,
        IpcrV2WorkflowService::STATUS_FOR_RATING,
        extra: ['submitted_for_rating_at' => now()],
        actor: $request->user(),
        actionType: 'submitted',
        signedViaPin: ! empty($request->user()->signature_pin),
    );

    return back()->with('success', 'Submitted for rating.');
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EmployeeIpcrV2ControllerTest"`
Expected: PASS (full file — confirm the Task 6-era PIN tests for `submitForReview`/`submitForRating` still pass, since `submitForRating`'s PIN check is unchanged, just moved after the new precondition)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/IPCRV2/EmployeeIpcrV2Controller.php tests/Feature/IPCRV2/EmployeeIpcrV2ControllerTest.php
git commit -m "feat(ipcr-v2): require a self-rating on every item before submitting for rating"
```

---

## Task 5: `IpcrV2CoreItemsTable.vue` — self-rating column group

**Files:**
- Modify: `resources/js/Components/IPCRV2/IpcrV2CoreItemsTable.vue`

**Interfaces:**
- Consumes: `self_quality_rating`/`self_efficiency_rating`/`self_timeliness_rating`/`self_row_average` (WDP-tagged shape) and `self_student_feedback_rating`/`self_supervisor_feedback_rating`/`self_im_development_rating`/`self_timeliness_rating`/`self_row_average` (teaching shape) from Task 1, saved via the existing `employee-ipcr-v2.updateCoreItem` route (Task 3).
- Produces: column count changes from 11 to 15 — Task 7 updates the `<thead>` blocks that must match.

- [ ] **Step 1: Implement**

Replace the full contents of `resources/js/Components/IPCRV2/IpcrV2CoreItemsTable.vue`:

```vue
<script setup>
import AppTextarea from "@/Components/AppTextarea.vue"
import { TD } from "@/Composables/useTableClasses.js"
import { router } from "@inertiajs/vue3"
import { computed } from "vue"
import { useSubmit } from "@/Composables/useSubmit"
import { groupConsecutiveByFunction } from "@/Composables/ipcrV2FunctionGrouping.js"

const props = defineProps({
  ipcrId: [Number, String],
  items: { type: Array, default: () => [] },
  isOwner: Boolean,
  isMutable: Boolean,
  canRate: { type: Boolean, default: false },
})

const { submit } = useSubmit()

const rows = computed(() => groupConsecutiveByFunction(props.items))

const CRITERIA = [
  { key: "student_feedback_rating", label: "Positive feedback from students (30%)" },
  { key: "supervisor_feedback_rating", label: "Positive feedback from immediate supervisor (20%)" },
  { key: "im_development_rating", label: "Instructional materials development (20%)" },
  { key: "timeliness_rating", label: "Timely submission of forms and documents (30%)" },
]

function saveEmployeeFields(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateCoreItem", [props.ipcrId, item.id]), {
    target: item.target,
    actual_accomplishment: item.actual_accomplishment,
    mov_link: item.mov_link,
  }, opts))
}

function rate(item) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.rateCoreItem", [props.ipcrId, item.id]), {
    student_feedback_rating: item.student_feedback_rating,
    supervisor_feedback_rating: item.supervisor_feedback_rating,
    im_development_rating: item.im_development_rating,
    timeliness_rating: item.timeliness_rating,
    remarks: item.remarks,
  }, opts))
}

function rateWdpRow(item) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.rateCoreItem", [props.ipcrId, item.id]), {
    quality_rating: item.quality_rating,
    efficiency_rating: item.efficiency_rating,
    timeliness_rating: item.timeliness_rating,
    remarks: item.remarks,
  }, opts))
}

function rateSelfWdpRow(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateCoreItem", [props.ipcrId, item.id]), {
    self_quality_rating: item.self_quality_rating,
    self_efficiency_rating: item.self_efficiency_rating,
    self_timeliness_rating: item.self_timeliness_rating,
  }, opts))
}

function rateSelfCsc(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateCoreItem", [props.ipcrId, item.id]), {
    self_student_feedback_rating: item.self_student_feedback_rating,
    self_supervisor_feedback_rating: item.self_supervisor_feedback_rating,
    self_im_development_rating: item.self_im_development_rating,
    self_timeliness_rating: item.self_timeliness_rating,
  }, opts))
}

function rowAverage(item) {
  const parts = [item.student_feedback_rating, item.supervisor_feedback_rating, item.im_development_rating, item.timeliness_rating]
  if (parts.some(v => v === null || v === undefined)) return "—"
  return (parts[0] * 0.3 + parts[1] * 0.2 + parts[2] * 0.2 + parts[3] * 0.3).toFixed(2)
}

function selfRowAverage(item) {
  const parts = [item.self_student_feedback_rating, item.self_supervisor_feedback_rating, item.self_im_development_rating, item.self_timeliness_rating]
  if (parts.some(v => v === null || v === undefined)) return "—"
  return (parts[0] * 0.3 + parts[1] * 0.2 + parts[2] * 0.2 + parts[3] * 0.3).toFixed(2)
}
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td colspan="15" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Core Function (50%)
      </td>
    </tr>
    <template v-for="row in rows" :key="row.item.id">
      <!-- WDP-tagged row: one independently-ratable row per tagged plan, mirroring Support Functions -->
      <tr v-if="row.item.success_indicator">
        <td v-if="row.isFirst" :rowspan="row.groupSize" :class="TD" class="border border-slate-200 align-top font-medium">
          {{ row.item.label }}<br />
          <small class="text-slate-400">Weight: {{ row.item.weight_percent ?? "—" }}%</small>
        </td>
        <td v-if="row.isFirst" :rowspan="row.groupSize" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
        <td v-if="row.isFirst" :rowspan="row.groupSize" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
        <td :class="TD" class="border border-slate-200 align-top">{{ row.item.success_indicator }}</td>
        <td :class="TD" class="border border-slate-200 align-top">
          <AppTextarea v-if="isOwner && isMutable" v-model="row.item.target" @blur="saveEmployeeFields(row.item)" />
          <span v-else>{{ row.item.target ?? "—" }}</span>
        </td>
        <td :class="TD" class="border border-slate-200 align-top">
          <AppTextarea v-if="isOwner && isMutable" v-model="row.item.actual_accomplishment" @blur="saveEmployeeFields(row.item)" />
          <span v-else>{{ row.item.actual_accomplishment ?? "—" }}</span>
          <input v-if="isOwner && isMutable" v-model="row.item.mov_link" placeholder="MOV link" class="border rounded px-2 py-1 text-xs w-full mt-1" @blur="saveEmployeeFields(row.item)" />
          <small v-else-if="row.item.mov_link" class="block text-slate-400 mt-1">MOV: {{ row.item.mov_link }}</small>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="isOwner && isMutable" v-model.number="row.item.self_quality_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <span v-else>{{ row.item.self_quality_rating ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="isOwner && isMutable" v-model.number="row.item.self_efficiency_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <span v-else>{{ row.item.self_efficiency_rating ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="isOwner && isMutable" v-model.number="row.item.self_timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <span v-else>{{ row.item.self_timeliness_rating ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
          {{ row.item.self_row_average ?? "—" }}
          <button v-if="isOwner && isMutable" type="button" class="block mt-1 text-xs text-indigo-600" @click="rateSelfWdpRow(row.item)">Save Self-Rating</button>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="canRate" v-model.number="row.item.quality_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <span v-else>{{ row.item.quality_rating ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="canRate" v-model.number="row.item.efficiency_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <span v-else>{{ row.item.efficiency_rating ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm">
          <select v-if="canRate" v-model.number="row.item.timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
          <span v-else>{{ row.item.timeliness_rating ?? "—" }}</span>
        </td>
        <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
          {{ row.item.row_average ?? "—" }}
          <button v-if="canRate" type="button" class="block mt-1 text-xs text-indigo-600" @click="rateWdpRow(row.item)">Save</button>
        </td>
        <td :class="TD" class="border border-slate-200 align-top">
          <input v-if="canRate" v-model="row.item.remarks" class="border rounded px-2 py-1 text-xs w-full" />
          <span v-else>{{ row.item.remarks ?? "—" }}</span>
        </td>
      </tr>

      <!-- Untagged (teaching-load) row: fixed CSC 4-criteria rubric -->
      <template v-else>
        <tr v-for="(criterion, idx) in CRITERIA" :key="row.item.id + '-' + criterion.key">
          <td v-if="idx === 0" rowspan="5" :class="TD" class="border border-slate-200 align-top font-medium">
            {{ row.item.label }}<br />
            <small class="text-slate-400">Weight: {{ row.item.weight_percent ?? "—" }}%</small>
          </td>
          <td v-if="idx === 0" rowspan="5" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
          <td v-if="idx === 0" rowspan="5" class="border border-slate-200 px-4 py-3 text-sm text-slate-400 align-top">—</td>
          <td :class="TD" class="border border-slate-200">{{ criterion.label }}</td>
          <td v-if="idx === 0" rowspan="4" :class="TD" class="border border-slate-200 align-top">
            <AppTextarea v-if="isOwner && isMutable" v-model="row.item.target" @blur="saveEmployeeFields(row.item)" />
            <span v-else>{{ row.item.target ?? "—" }}</span>
          </td>
          <td v-if="idx === 0" rowspan="4" :class="TD" class="border border-slate-200 align-top">
            <AppTextarea v-if="isOwner && isMutable" v-model="row.item.actual_accomplishment" @blur="saveEmployeeFields(row.item)" />
            <span v-else>{{ row.item.actual_accomplishment ?? "—" }}</span>
            <input v-if="isOwner && isMutable" v-model="row.item.mov_link" placeholder="MOV link" class="border rounded px-2 py-1 text-xs w-full mt-1" @blur="saveEmployeeFields(row.item)" />
            <small v-else-if="row.item.mov_link" class="block text-slate-400 mt-1">MOV: {{ row.item.mov_link }}</small>
          </td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm">
            <select v-if="isOwner && isMutable" v-model.number="row.item['self_' + criterion.key]" class="border rounded text-xs px-1">
              <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
            </select>
            <span v-else>{{ row.item['self_' + criterion.key] ?? "—" }}</span>
          </td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm">
            <select v-if="canRate" v-model.number="row.item[criterion.key]" class="border rounded text-xs px-1">
              <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
            </select>
            <span v-else>{{ row.item[criterion.key] ?? "—" }}</span>
          </td>
          <td v-if="idx === 0" rowspan="5" :class="TD" class="border border-slate-200 align-top">
            <input v-if="canRate" v-model="row.item.remarks" class="border rounded px-2 py-1 text-xs w-full" />
            <span v-else>{{ row.item.remarks ?? "—" }}</span>
          </td>
        </tr>
        <tr>
          <td :class="TD" class="border border-slate-200 font-semibold" colspan="3">Row Average</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
            {{ row.item.self_row_average ?? selfRowAverage(row.item) }}
            <button v-if="isOwner && isMutable" type="button" class="ml-2 text-xs text-indigo-600" @click="rateSelfCsc(row.item)">Save Self-Rating</button>
          </td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm text-slate-300">—</td>
          <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
            {{ row.item.row_average ?? rowAverage(row.item) }}
            <button v-if="canRate" type="button" class="ml-2 text-xs text-indigo-600" @click="rate(row.item)">Save Ratings</button>
          </td>
        </tr>
      </template>
    </template>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" colspan="15">No Core Function rows yet — generate targets from Employee Functions.</td>
    </tr>
  </tbody>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/IPCRV2/IpcrV2CoreItemsTable.vue
git commit -m "feat(ipcr-v2): add employee self-rating column group to Core Function table"
```

(Build verification happens in Task 7, once the matching `<thead>` changes land — a mismatched column count between this file and the headers would only surface visually, not as a build error, so the two must be verified together.)

---

## Task 6: `IpcrV2SupportItemsTable.vue` — self-rating column group

**Files:**
- Modify: `resources/js/Components/IPCRV2/IpcrV2SupportItemsTable.vue`

**Interfaces:**
- Consumes: `self_quality_rating`/`self_efficiency_rating`/`self_timeliness_rating`/`self_row_average` from Task 1, saved via `employee-ipcr-v2.updateSupportItem` (Task 3).

- [ ] **Step 1: Implement**

Replace the full contents of `resources/js/Components/IPCRV2/IpcrV2SupportItemsTable.vue`:

```vue
<script setup>
import AppTextarea from "@/Components/AppTextarea.vue"
import { TD } from "@/Composables/useTableClasses.js"
import { router } from "@inertiajs/vue3"
import { computed } from "vue"
import { useSubmit } from "@/Composables/useSubmit"
import { groupConsecutiveByFunction } from "@/Composables/ipcrV2FunctionGrouping.js"

const props = defineProps({
  ipcrId: [Number, String],
  items: { type: Array, default: () => [] },
  isOwner: Boolean,
  isMutable: Boolean,
  canRate: { type: Boolean, default: false },
})

const { submit } = useSubmit()

const rows = computed(() => groupConsecutiveByFunction(props.items))

function saveEmployeeFields(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateSupportItem", [props.ipcrId, item.id]), {
    target: item.target,
    actual_accomplishment: item.actual_accomplishment,
    mov_link: item.mov_link,
  }, opts))
}

function rate(item) {
  submit((opts) => router.put(route("division-chief-ipcr-v2.rateSupportItem", [props.ipcrId, item.id]), {
    quality_rating: item.quality_rating,
    efficiency_rating: item.efficiency_rating,
    timeliness_rating: item.timeliness_rating,
    remarks: item.remarks,
  }, opts))
}

function rateSelf(item) {
  submit((opts) => router.put(route("employee-ipcr-v2.updateSupportItem", [props.ipcrId, item.id]), {
    self_quality_rating: item.self_quality_rating,
    self_efficiency_rating: item.self_efficiency_rating,
    self_timeliness_rating: item.self_timeliness_rating,
  }, opts))
}
</script>

<template>
  <tbody>
    <tr class="bg-slate-200">
      <td colspan="15" class="px-4 py-2 font-bold text-slate-800 border border-slate-300 uppercase">
        Support Function (20%)
      </td>
    </tr>
    <tr v-for="row in rows" :key="row.item.id">
      <td v-if="row.isFirst" :rowspan="row.groupSize" :class="TD" class="border border-slate-200 align-top font-medium">{{ row.item.label }}</td>
      <td v-if="row.isFirst" :rowspan="row.groupSize" class="border border-slate-200 px-4 py-3 text-sm text-slate-300 align-top">—</td>
      <td v-if="row.isFirst" :rowspan="row.groupSize" class="border border-slate-200 px-4 py-3 text-sm text-slate-300 align-top">—</td>
      <td :class="TD" class="border border-slate-200 align-top text-slate-500">{{ row.item.success_indicator ?? "—" }}</td>
      <td :class="TD" class="border border-slate-200 align-top">
        <AppTextarea v-if="isOwner && isMutable" v-model="row.item.target" @blur="saveEmployeeFields(row.item)" />
        <span v-else>{{ row.item.target ?? "—" }}</span>
      </td>
      <td :class="TD" class="border border-slate-200 align-top">
        <AppTextarea v-if="isOwner && isMutable" v-model="row.item.actual_accomplishment" @blur="saveEmployeeFields(row.item)" />
        <span v-else>{{ row.item.actual_accomplishment ?? "—" }}</span>
        <input v-if="isOwner && isMutable" v-model="row.item.mov_link" placeholder="MOV link" class="border rounded px-2 py-1 text-xs w-full mt-1" @blur="saveEmployeeFields(row.item)" />
        <small v-else-if="row.item.mov_link" class="block text-slate-400 mt-1">MOV: {{ row.item.mov_link }}</small>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="isOwner && isMutable" v-model.number="row.item.self_quality_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.self_quality_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="isOwner && isMutable" v-model.number="row.item.self_efficiency_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.self_efficiency_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="isOwner && isMutable" v-model.number="row.item.self_timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.self_timeliness_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
        {{ row.item.self_row_average ?? "—" }}
        <button v-if="isOwner && isMutable" type="button" class="block mt-1 text-xs text-indigo-600" @click="rateSelf(row.item)">Save Self-Rating</button>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="row.item.quality_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.quality_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="row.item.efficiency_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.efficiency_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm">
        <select v-if="canRate" v-model.number="row.item.timeliness_rating" class="border rounded text-xs px-1"><option v-for="n in 5" :key="n" :value="n">{{ n }}</option></select>
        <span v-else>{{ row.item.timeliness_rating ?? "—" }}</span>
      </td>
      <td class="border border-slate-200 px-4 py-3 text-center text-sm font-semibold">
        {{ row.item.row_average ?? "—" }}
        <button v-if="canRate" type="button" class="block mt-1 text-xs text-indigo-600" @click="rate(row.item)">Save</button>
      </td>
      <td :class="TD" class="border border-slate-200 align-top">
        <input v-if="canRate" v-model="row.item.remarks" class="border rounded px-2 py-1 text-xs w-full" />
        <span v-else>{{ row.item.remarks ?? "—" }}</span>
      </td>
    </tr>
    <tr v-if="!items.length">
      <td :class="TD" class="border border-slate-200" colspan="15">No Support Function rows yet.</td>
    </tr>
  </tbody>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/IPCRV2/IpcrV2SupportItemsTable.vue
git commit -m "feat(ipcr-v2): add employee self-rating column group to Support Function table"
```

---

## Task 7: Show-page `<thead>` updates (Employee/DivisionChief/PMT)

**Files:**
- Modify: `resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue`
- Modify: `resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue`
- Modify: `resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue`

**Interfaces:**
- Consumes: the 15-column table bodies from Tasks 5-6.

- [ ] **Step 1: Update the shared header block**

All three files currently carry this identical `<thead>` (confirmed via direct comparison of all three files):

```vue
<thead class="bg-slate-50/80">
  <tr>
    <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Function</th>
    <th colspan="2" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Output/Outcomes</th>
    <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Success Indicator</th>
    <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Target</th>
    <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Actual Accomplishment</th>
    <th colspan="4" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating</th>
    <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Remarks</th>
  </tr>
  <tr>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Sub Strategy</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Program</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Q</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">E</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">T</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">A</th>
  </tr>
</thead>
```

In **each** of the three files, replace it with (adds a "Self-Rating" `colspan="4"` group before "Rating", and a matching Q/E/T/A row-2 group):

```vue
<thead class="bg-slate-50/80">
  <tr>
    <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Function</th>
    <th colspan="2" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Output/Outcomes</th>
    <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Success Indicator</th>
    <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Target</th>
    <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Actual Accomplishment</th>
    <th colspan="4" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Self-Rating</th>
    <th colspan="4" class="border border-slate-200 px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase">Rating</th>
    <th rowspan="2" class="border border-slate-200 px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase align-bottom">Remarks</th>
  </tr>
  <tr>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Sub Strategy</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Program</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Q</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">E</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">T</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">A</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">Q</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">E</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">T</th>
    <th class="border border-slate-200 px-4 py-2 text-center text-[11px] font-semibold text-slate-400 uppercase">A</th>
  </tr>
</thead>
```

- [ ] **Step 2: Build and manually verify**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`

In the browser: open an Employee's IPCR V2 with both a WDP-tagged Core item and an untagged (teaching-load) Core item, plus a Support item — confirm the "Self-Rating" column group renders as editable dropdowns for the owner while mutable, that "Save Self-Rating" persists and shows the computed average, and that the table header aligns with the body (no ragged columns) on all three of Employee/DivisionChief/PMT Show pages. Then confirm `submitForRating()` is blocked with the new error message until every item has a self-rating.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/IPCRV2/EmployeeIpcrV2Show.vue resources/js/Pages/IPCRV2/DivisionChiefIpcrV2Show.vue resources/js/Pages/IPCRV2/PMTIpcrV2Show.vue
git commit -m "feat(ipcr-v2): add Self-Rating header group to match the new item table columns"
```

---

## Task 8: `IpcrV2GenerationServiceTest` — regression guard

**Files:**
- Modify: `tests/Feature/IPCRV2/IpcrV2GenerationServiceTest.php` (extend existing file)

**Interfaces:**
- Consumes: `self_row_average` column from Task 1.

- [ ] **Step 1: Write the test**

Add to `tests/Feature/IPCRV2/IpcrV2GenerationServiceTest.php`:

```php
public function test_generated_items_start_with_no_self_rating(): void
{
    $user = User::factory()->create();
    $period = IPCRRatingPeriod::create(['label' => 'x', 'year' => 2026, 'semester' => 1, 'status' => 'open']);
    EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'core', 'source_type' => 'manual', 'label' => 'Subject 1', 'weight_percent' => 100]);
    EmployeeFunction::create(['user_id' => $user->id, 'function_type' => 'support', 'source_type' => 'manual', 'label' => 'Discipline Committee']);

    $record = (new IpcrV2GenerationService())->generateTargets($user, $period);

    $this->assertNull($record->coreItems->first()->self_row_average);
    $this->assertNull($record->supportItems->first()->self_row_average);
}
```

- [ ] **Step 2: Run test to verify it passes immediately**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_generated_items_start_with_no_self_rating"`
Expected: PASS immediately — this is a regression guard confirming Task 1's migration didn't change generation behavior, not new functionality, so it should already be green.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/IPCRV2/IpcrV2GenerationServiceTest.php
git commit -m "test(ipcr-v2): guard that generated items start with no self-rating"
```

---

## Final verification

- [ ] Run the full IPCR V2 suite: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/IPCRV2"` — expect all green, including the untouched `DivisionChiefIpcrV2ControllerTest` rating tests (regression guard on the official rating path) and `IpcrV2SummaryServiceTest`/`IpcrV2RatingServiceTest` (regression guard that self-ratings never enter the official computation).
- [ ] `npm run build` clean, no console errors.
- [ ] Manually confirm in the browser: an employee cannot submit for rating until every Core/Support item has a self-rating; the Division Chief's own rating flow is completely unaffected; the Rating Summary and PDF show unchanged numbers (still sourced from the official columns only).
