# Learn Module Phase 3: Quizzes / Assessment Engine — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an async, LMS-style quiz engine to Learn — question bank, timed/multi-attempt quizzes with auto+manual grading, Class Record push, and analytics — per `docs/superpowers/specs/2026-08-09-learn-module-phase3-design.md`.

**Architecture:** `Quiz` becomes a fourth polymorphic `ModuleItem` itemable type alongside Page/File/Assignment, reusing all existing module/item CRUD. A quiz locks its question structure on first submitted attempt (mirrors `ClassRecordQuarter.is_locked`). Attempts autosave per-answer and are lazily finalized past their deadline on next touch (no cron). `Assignment` and `Quiz` both implement a new `HasClassRecordLink` contract so Phase 2b's `ClassRecordPushService` works against either without duplicating link/push logic. Question bank items are fully independent copies (no FK to live quiz questions), same safety pattern as Phase 2c's rubric templates.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia 2, Tailwind, KaTeX (new npm dep, client-side math rendering) — reusing Phase 1/2/2b/2c's Learn infrastructure.

## Global Constraints

- All prior Learn-phase constraints apply (base64 uploads, `Storage::disk('s3')`, `Inertia::render(...)`, eager-load relations, migrations always write `down()`, `student_id` columns are always `unsignedInteger` with no FK — `students` is legacy MyISAM).
- MySQL identifier limit is 64 chars — several of this phase's table names are long enough that auto-generated FK/index names exceed it; use explicit short names wherever noted below.
- **Quiz structure locks on first submitted attempt** (`is_locked`). Existing questions/options become read-only once true; new questions may still be added. No task should allow editing/deleting a question or option on a locked quiz.
- **Question bank items are copy-on-save, copy-on-apply only** — never a live reference to any `learn_quiz_questions` row, in either direction. No task should add such a link.
- **Design resolution (not a scope change):** when `questions_to_draw` is set on a quiz, all of that quiz's questions must carry equal `points` — this is what keeps `Quiz::maxScore()` deterministic despite each attempt only seeing a random subset. Enforced as a validation rule wherever a question is created/updated on such a quiz.
- **Design resolution (not a scope change):** the question bank has no separate "options" vs "accepted answers" split like the live schema does — `learn_quiz_question_bank_options` rows double as accepted-answer phrases when the bank item's `question_type` is `short_answer` (`option_text` holds the phrase, `is_correct` is ignored for that type). Applying such a bank item copies its option rows into the new question's `learn_quiz_question_accepted_answers` instead of `learn_quiz_question_options`.
- Never modify `Assignment`'s existing behavior — only add to it (implementing the new shared contract, adding one new method).

---

### Task 1: Core quiz schema (7 migrations)

**Files:**
- Create: `database/migrations/2026_08_09_100009_create_learn_quizzes_table.php`
- Create: `database/migrations/2026_08_09_100010_create_learn_quiz_questions_table.php`
- Create: `database/migrations/2026_08_09_100011_create_learn_quiz_question_options_table.php`
- Create: `database/migrations/2026_08_09_100012_create_learn_quiz_question_accepted_answers_table.php`
- Create: `database/migrations/2026_08_09_100013_create_learn_quiz_attempts_table.php`
- Create: `database/migrations/2026_08_09_100014_create_learn_quiz_attempt_answers_table.php`
- Create: `database/migrations/2026_08_09_100015_create_learn_quiz_attempt_selected_options_table.php`
- Test: `tests/Feature/Learn/LearnQuizSchemaTest.php`

**Interfaces:**
- Produces tables consumed by every later task in this plan.

- [ ] **Step 1: Write the failing schema test**

```php
<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnQuizSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_quizzes_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quizzes'));
        $this->assertTrue(Schema::hasColumns('learn_quizzes', [
            'id', 'title', 'instructions', 'time_limit_minutes', 'max_attempts',
            'questions_to_draw', 'shuffle_questions', 'shuffle_options', 'due_at',
            'is_locked', 'class_record_assessment_id', 'pushed_at',
        ]));
    }

    public function test_learn_quiz_questions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_questions'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_questions', [
            'id', 'learn_quiz_id', 'question_type', 'prompt', 'points', 'position', 'difficulty',
        ]));
    }

    public function test_learn_quiz_question_options_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_question_options'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_question_options', [
            'id', 'learn_quiz_question_id', 'option_text', 'is_correct', 'position',
        ]));
    }

    public function test_learn_quiz_question_accepted_answers_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_question_accepted_answers'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_question_accepted_answers', [
            'id', 'learn_quiz_question_id', 'answer_text',
        ]));
    }

    public function test_learn_quiz_attempts_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_attempts'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_attempts', [
            'id', 'learn_quiz_id', 'student_id', 'attempt_number', 'question_order',
            'started_at', 'submitted_at', 'auto_submitted', 'score',
        ]));
    }

    public function test_learn_quiz_attempt_answers_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_attempt_answers'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_attempt_answers', [
            'id', 'learn_quiz_attempt_id', 'learn_quiz_question_id', 'answer_text',
            'is_correct', 'points_earned', 'graded_at', 'graded_by',
        ]));
    }

    public function test_learn_quiz_attempt_selected_options_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_attempt_selected_options'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_attempt_selected_options', [
            'id', 'learn_quiz_attempt_answer_id', 'learn_quiz_question_option_id',
        ]));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnQuizSchemaTest.php"`
Expected: FAIL — none of the tables exist.

- [ ] **Step 3: Write the 7 migrations**

`database/migrations/2026_08_09_100009_create_learn_quizzes_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('instructions')->nullable();
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->unsignedInteger('max_attempts')->nullable();
            $table->unsignedInteger('questions_to_draw')->nullable();
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->timestamp('due_at')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->foreignId('class_record_assessment_id')->nullable()
                  ->constrained('class_record_assessments')->nullOnDelete();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quizzes');
    }
};
```

`database/migrations/2026_08_09_100010_create_learn_quiz_questions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_quiz_id')->constrained('learn_quizzes')->cascadeOnDelete();
            $table->enum('question_type', ['multiple_choice', 'true_false', 'multiple_select', 'short_answer', 'essay']);
            $table->longText('prompt');
            $table->decimal('points', 6, 2);
            $table->unsignedInteger('position')->default(0);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_questions');
    }
};
```

`database/migrations/2026_08_09_100011_create_learn_quiz_question_options_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_quiz_question_id')->constrained('learn_quiz_questions')->cascadeOnDelete();
            $table->string('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_question_options');
    }
};
```

`database/migrations/2026_08_09_100012_create_learn_quiz_question_accepted_answers_table.php`
(explicit short FK name — `learn_quiz_question_accepted_answers_learn_quiz_question_id_foreign` would exceed 64 chars):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_question_accepted_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learn_quiz_question_id');
            $table->foreign('learn_quiz_question_id', 'lqqaa_question_fk')
                  ->references('id')->on('learn_quiz_questions')->cascadeOnDelete();
            $table->string('answer_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_question_accepted_answers');
    }
};
```

`database/migrations/2026_08_09_100013_create_learn_quiz_attempts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_quiz_id')->constrained('learn_quizzes')->cascadeOnDelete();
            $table->unsignedInteger('student_id')
                  ->comment('references students.id — app-level constraint (MyISAM cannot be FK target)');
            $table->unsignedInteger('attempt_number');
            $table->json('question_order');
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('auto_submitted')->default(false);
            $table->decimal('score', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['learn_quiz_id', 'student_id', 'attempt_number'], 'learn_quiz_attempts_quiz_student_attempt_unique');
            $table->index(['learn_quiz_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_attempts');
    }
};
```

`database/migrations/2026_08_09_100014_create_learn_quiz_attempt_answers_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_quiz_attempt_id')->constrained('learn_quiz_attempts')->cascadeOnDelete();
            $table->foreignId('learn_quiz_question_id')->constrained('learn_quiz_questions')->cascadeOnDelete();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('points_earned', 6, 2)->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['learn_quiz_attempt_id', 'learn_quiz_question_id'], 'learn_quiz_attempt_answers_attempt_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_attempt_answers');
    }
};
```

`database/migrations/2026_08_09_100015_create_learn_quiz_attempt_selected_options_table.php`
(explicit short FK/unique names — the auto-generated ones would exceed 64 chars on this table):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_attempt_selected_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learn_quiz_attempt_answer_id');
            $table->foreign('learn_quiz_attempt_answer_id', 'lqaso_answer_fk')
                  ->references('id')->on('learn_quiz_attempt_answers')->cascadeOnDelete();
            $table->unsignedBigInteger('learn_quiz_question_option_id');
            $table->foreign('learn_quiz_question_option_id', 'lqaso_option_fk')
                  ->references('id')->on('learn_quiz_question_options')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['learn_quiz_attempt_answer_id', 'learn_quiz_question_option_id'], 'lqaso_answer_option_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_attempt_selected_options');
    }
};
```

- [ ] **Step 4: Run migrations and the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_09_100009_create_learn_quizzes_table.php --path=database/migrations/2026_08_09_100010_create_learn_quiz_questions_table.php --path=database/migrations/2026_08_09_100011_create_learn_quiz_question_options_table.php --path=database/migrations/2026_08_09_100012_create_learn_quiz_question_accepted_answers_table.php --path=database/migrations/2026_08_09_100013_create_learn_quiz_attempts_table.php --path=database/migrations/2026_08_09_100014_create_learn_quiz_attempt_answers_table.php --path=database/migrations/2026_08_09_100015_create_learn_quiz_attempt_selected_options_table.php --force"`
Then: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnQuizSchemaTest.php"`
Expected: PASS (6 tests). If a migration fails partway (e.g. an identifier-length error), drop whatever table it partially created via tinker (`Schema::dropIfExists('table_name')`) before retrying — Laravel doesn't record a failed migration as run, so re-running collides with the leftover table otherwise.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_09_100009_create_learn_quizzes_table.php \
        database/migrations/2026_08_09_100010_create_learn_quiz_questions_table.php \
        database/migrations/2026_08_09_100011_create_learn_quiz_question_options_table.php \
        database/migrations/2026_08_09_100012_create_learn_quiz_question_accepted_answers_table.php \
        database/migrations/2026_08_09_100013_create_learn_quiz_attempts_table.php \
        database/migrations/2026_08_09_100014_create_learn_quiz_attempt_answers_table.php \
        database/migrations/2026_08_09_100015_create_learn_quiz_attempt_selected_options_table.php \
        tests/Feature/Learn/LearnQuizSchemaTest.php
git commit -m "feat(learn): add core quiz schema (quizzes, questions, options, attempts)"
```

---

### Task 2: Core quiz models

**Files:**
- Create: `app/Models/Learn/Quiz.php`
- Create: `app/Models/Learn/QuizQuestion.php`
- Create: `app/Models/Learn/QuizQuestionOption.php`
- Create: `app/Models/Learn/QuizQuestionAcceptedAnswer.php`
- Create: `app/Models/Learn/QuizAttempt.php`
- Create: `app/Models/Learn/QuizAttemptAnswer.php`
- Create: `app/Models/Learn/QuizAttemptSelectedOption.php`
- Test: `tests/Feature/Learn/QuizModelTest.php`

**Interfaces:**
- Consumes: tables from Task 1.
- Produces: `Quiz::questions()`, `Quiz::attempts()`, `Quiz::moduleItem()`, `Quiz::course()`,
  `Quiz::canEdit(User)`, `Quiz::maxScore()` — all consumed by later tasks.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_quiz_has_ordered_questions_with_options_and_accepted_answers(): void
    {
        $quiz = Quiz::create(['title' => 'Chapter 1 Quiz']);

        $q1 = $quiz->questions()->create([
            'question_type' => 'multiple_choice', 'prompt' => 'What is 2+2?', 'points' => 5, 'position' => 1,
        ]);
        $q1->options()->create(['option_text' => '4', 'is_correct' => true, 'position' => 0]);
        $q1->options()->create(['option_text' => '5', 'is_correct' => false, 'position' => 1]);

        $q0 = $quiz->questions()->create([
            'question_type' => 'short_answer', 'prompt' => 'Capital of the Philippines?', 'points' => 5, 'position' => 0,
        ]);
        $q0->acceptedAnswers()->create(['answer_text' => 'Manila']);

        $this->assertSame([$q0->id, $q1->id], $quiz->fresh()->questions->pluck('id')->all());
        $this->assertCount(2, $q1->fresh()->options);
        $this->assertCount(1, $q0->fresh()->acceptedAnswers);
    }

    public function test_quiz_max_score_sums_question_points_when_not_using_draw(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'A', 'points' => 10, 'position' => 0]);
        $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'B', 'points' => 15, 'position' => 1]);

        $this->assertSame(25.0, $quiz->maxScore());
    }

    public function test_quiz_max_score_uses_draw_count_times_per_question_points_when_drawing(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz', 'questions_to_draw' => 3]);
        foreach (range(1, 5) as $i) {
            $quiz->questions()->create(['question_type' => 'essay', 'prompt' => "Q{$i}", 'points' => 4, 'position' => $i]);
        }

        $this->assertSame(12.0, $quiz->maxScore());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizModelTest.php"`
Expected: FAIL — model classes don't exist.

- [ ] **Step 3: Write the models**

`app/Models/Learn/Quiz.php`:

```php
<?php

namespace App\Models\Learn;

use App\Contracts\Learn\HasClassRecordLink;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Quiz extends Model implements HasClassRecordLink
{
    protected $table = 'learn_quizzes';

    protected $fillable = [
        'title', 'instructions', 'time_limit_minutes', 'max_attempts', 'questions_to_draw',
        'shuffle_questions', 'shuffle_options', 'due_at', 'is_locked',
        'class_record_assessment_id', 'pushed_at',
    ];

    protected $casts = [
        'time_limit_minutes' => 'integer',
        'max_attempts' => 'integer',
        'questions_to_draw' => 'integer',
        'shuffle_questions' => 'boolean',
        'shuffle_options' => 'boolean',
        'due_at' => 'datetime',
        'is_locked' => 'boolean',
        'pushed_at' => 'datetime',
    ];

    public function moduleItem(): MorphOne
    {
        return $this->morphOne(ModuleItem::class, 'itemable');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'learn_quiz_id')->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'learn_quiz_id');
    }

    public function classRecordAssessment(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAssessment::class, 'class_record_assessment_id');
    }

    /**
     * When questions_to_draw is set, every question on the quiz is required to carry equal
     * points (enforced at question-creation time) — so the deterministic max is draw-count ×
     * per-question points, even though each attempt only sees a random subset.
     */
    public function maxScore(): ?float
    {
        if ($this->questions()->count() === 0) {
            return null;
        }

        if ($this->questions_to_draw !== null) {
            $perQuestion = (float) $this->questions()->value('points');

            return $perQuestion * $this->questions_to_draw;
        }

        return (float) $this->questions()->sum('points');
    }

    /** The Learn course this quiz belongs to, resolved through its module item. */
    public function course(): ?Course
    {
        return $this->moduleItem?->module?->course;
    }

    public function canEdit(User $user): bool
    {
        return $this->course()?->canEdit($user) ?? false;
    }

    /**
     * @return array<int, float> student_id => highest finalized score across their attempts.
     */
    public function gradedStudentScores(): array
    {
        return $this->attempts()
            ->whereNotNull('score')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($attempts) => (float) $attempts->max('score'))
            ->all();
    }
}
```

`app/Models/Learn/QuizQuestion.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    protected $table = 'learn_quiz_questions';

    protected $fillable = ['learn_quiz_id', 'question_type', 'prompt', 'points', 'position', 'difficulty'];

    protected $casts = [
        'points' => 'decimal:2',
        'position' => 'integer',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'learn_quiz_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class, 'learn_quiz_question_id')->orderBy('position');
    }

    public function acceptedAnswers(): HasMany
    {
        return $this->hasMany(QuizQuestionAcceptedAnswer::class, 'learn_quiz_question_id');
    }
}
```

`app/Models/Learn/QuizQuestionOption.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestionOption extends Model
{
    protected $table = 'learn_quiz_question_options';

    protected $fillable = ['learn_quiz_question_id', 'option_text', 'is_correct', 'position'];

    protected $casts = [
        'is_correct' => 'boolean',
        'position' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'learn_quiz_question_id');
    }
}
```

`app/Models/Learn/QuizQuestionAcceptedAnswer.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestionAcceptedAnswer extends Model
{
    protected $table = 'learn_quiz_question_accepted_answers';

    protected $fillable = ['learn_quiz_question_id', 'answer_text'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'learn_quiz_question_id');
    }
}
```

`app/Models/Learn/QuizAttempt.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    protected $table = 'learn_quiz_attempts';

    protected $fillable = [
        'learn_quiz_id', 'student_id', 'attempt_number', 'question_order',
        'started_at', 'submitted_at', 'auto_submitted', 'score',
    ];

    protected $casts = [
        'question_order' => 'array',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'auto_submitted' => 'boolean',
        'score' => 'decimal:2',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'learn_quiz_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class, 'learn_quiz_attempt_id');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
```

`app/Models/Learn/QuizAttemptAnswer.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttemptAnswer extends Model
{
    protected $table = 'learn_quiz_attempt_answers';

    protected $fillable = [
        'learn_quiz_attempt_id', 'learn_quiz_question_id', 'answer_text',
        'is_correct', 'points_earned', 'graded_at', 'graded_by',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'points_earned' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'learn_quiz_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'learn_quiz_question_id');
    }

    public function selectedOptions(): HasMany
    {
        return $this->hasMany(QuizAttemptSelectedOption::class, 'learn_quiz_attempt_answer_id');
    }
}
```

`app/Models/Learn/QuizAttemptSelectedOption.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptSelectedOption extends Model
{
    protected $table = 'learn_quiz_attempt_selected_options';

    protected $fillable = ['learn_quiz_attempt_answer_id', 'learn_quiz_question_option_id'];

    public function answer(): BelongsTo
    {
        return $this->belongsTo(QuizAttemptAnswer::class, 'learn_quiz_attempt_answer_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuizQuestionOption::class, 'learn_quiz_question_option_id');
    }
}
```

- [ ] **Step 4: Run the test**

This test references `App\Contracts\Learn\HasClassRecordLink`, which doesn't exist until Task 3.
For this task only, temporarily comment out `implements HasClassRecordLink` and the `use
App\Contracts\Learn\HasClassRecordLink;` import in `Quiz.php` (leave `gradedStudentScores()` in
place — it's harmless without the interface), run the test, confirm PASS, then proceed to Task 3
immediately afterward to restore the `implements` clause as part of that task's own step 3 edit.

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizModelTest.php"`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Learn/Quiz.php app/Models/Learn/QuizQuestion.php app/Models/Learn/QuizQuestionOption.php \
        app/Models/Learn/QuizQuestionAcceptedAnswer.php app/Models/Learn/QuizAttempt.php \
        app/Models/Learn/QuizAttemptAnswer.php app/Models/Learn/QuizAttemptSelectedOption.php \
        tests/Feature/Learn/QuizModelTest.php
git commit -m "feat(learn): add core quiz models"
```

---

### Task 3: `HasClassRecordLink` contract + `Assignment` retrofit

**Files:**
- Create: `app/Contracts/Learn/HasClassRecordLink.php`
- Modify: `app/Models/Learn/Assignment.php`
- Modify: `app/Models/Learn/Quiz.php` (restore the `implements` clause commented out in Task 2)
- Test: `tests/Feature/Learn/HasClassRecordLinkContractTest.php`

**Interfaces:**
- Produces: `HasClassRecordLink` interface (`course()`, `canEdit()`, `maxScore()`,
  `classRecordAssessment()`, `gradedStudentScores()`) — consumed by Task 16's widened
  `ClassRecordPushService`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Contracts\Learn\HasClassRecordLink;
use App\Models\Learn\Assignment;
use App\Models\Learn\Quiz;
use App\Models\Learn\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasClassRecordLinkContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_and_quiz_both_implement_the_contract(): void
    {
        $this->assertInstanceOf(HasClassRecordLink::class, new Assignment());
        $this->assertInstanceOf(HasClassRecordLink::class, new Quiz());
    }

    public function test_assignment_graded_student_scores_reads_from_graded_submissions_only(): void
    {
        $assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 10]);
        $student = User::factory()->create();

        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 111,
            'text_body' => 'x', 'submitted_at' => now(), 'score' => 8, 'graded_at' => now(), 'graded_by' => $student->id,
        ]);
        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 222,
            'text_body' => 'x', 'submitted_at' => now(),
        ]);

        $this->assertSame([111 => 8.0], $assignment->gradedStudentScores());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/HasClassRecordLinkContractTest.php"`
Expected: FAIL — interface and `Assignment::gradedStudentScores()` don't exist yet.

- [ ] **Step 3: Write the interface, retrofit `Assignment`, restore `Quiz`**

`app/Contracts/Learn/HasClassRecordLink.php`:

```php
<?php

namespace App\Contracts\Learn;

use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\Learn\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Implemented by any Learn gradable item (Assignment, Quiz) that can be linked to a
 * pre-existing Class Record assessment and have its scores pushed into it. Implementers
 * never create or date a ClassRecordAssessment themselves — see ClassRecordPushService.
 */
interface HasClassRecordLink
{
    public function course(): ?Course;

    public function canEdit(User $user): bool;

    public function maxScore(): ?float;

    public function classRecordAssessment(): BelongsTo;

    /** @return array<int, float> student_id => the score to push for that student. */
    public function gradedStudentScores(): array;
}
```

In `app/Models/Learn/Assignment.php`, add the import and `implements` clause:

```php
use App\Contracts\Learn\HasClassRecordLink;
```

```php
class Assignment extends Model implements HasClassRecordLink
```

Add this method to `Assignment` (anywhere among its other methods):

```php
    /**
     * @return array<int, float> student_id => score, for every graded submission.
     */
    public function gradedStudentScores(): array
    {
        return Submission::where('learn_assignment_id', $this->id)
            ->whereNotNull('graded_at')
            ->pluck('score', 'student_id')
            ->map(fn ($score) => (float) $score)
            ->all();
    }
```

In `app/Models/Learn/Quiz.php`, restore the import and `implements` clause commented out in
Task 2 step 4 (they should already be present from Task 2's step 3 — this step is only needed
if they were commented out to make Task 2's test pass in isolation):

```php
use App\Contracts\Learn\HasClassRecordLink;
```

```php
class Quiz extends Model implements HasClassRecordLink
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/HasClassRecordLinkContractTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 5: Run Phase 2b's existing Assignment/ClassRecordPushService tests to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ClassRecordPushServiceTest.php tests/Feature/Learn/ClassRecordPushControllerTest.php tests/Feature/Learn/AssignmentGradingClassRecordDataTest.php"`
Expected: PASS, same counts as before — `Assignment` implementing a new interface and gaining
one new method must not change any existing behavior.

- [ ] **Step 6: Commit**

```bash
git add app/Contracts/Learn/HasClassRecordLink.php app/Models/Learn/Assignment.php app/Models/Learn/Quiz.php \
        tests/Feature/Learn/HasClassRecordLinkContractTest.php
git commit -m "feat(learn): add HasClassRecordLink contract, retrofit Assignment"
```

---

### Task 4: Quiz creation (`storeQuiz`) + question factory

**Files:**
- Create: `app/Services/Learn/QuizQuestionFactory.php`
- Modify: `app/Http/Controllers/Learn/ModuleItemController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/ModuleItemQuizControllerTest.php`

**Interfaces:**
- Produces: `QuizQuestionFactory::create(Quiz $quiz, array $data, int $position): QuizQuestion` —
  reused by Task 5's `QuizQuestionController::store`.
- Produces route: `learn.items.store-quiz` (POST `/learn/modules/{module}/items/quiz`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\ModuleItem;
use App\Models\Learn\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleItemQuizControllerTest extends TestCase
{
    use RefreshDatabase;

    private $module;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);

        $this->teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);

        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $this->module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    public function test_instructor_can_add_a_quiz_with_mixed_question_types(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'Chapter 1 Quiz',
            'time_limit_minutes' => 20,
            'max_attempts' => 2,
            'shuffle_questions' => true,
            'questions' => [
                [
                    'question_type' => 'multiple_choice', 'prompt' => 'What is 2+2?', 'points' => 5,
                    'options' => [
                        ['option_text' => '4', 'is_correct' => true],
                        ['option_text' => '5', 'is_correct' => false],
                    ],
                ],
                [
                    'question_type' => 'short_answer', 'prompt' => 'Capital of the Philippines?', 'points' => 5,
                    'accepted_answers' => ['Manila', 'City of Manila'],
                ],
                [
                    'question_type' => 'essay', 'prompt' => 'Explain photosynthesis.', 'points' => 10,
                ],
            ],
        ]);

        $response->assertRedirect();

        $quiz = Quiz::where('title', 'Chapter 1 Quiz')->firstOrFail();
        $this->assertSame(20, $quiz->time_limit_minutes);
        $this->assertCount(3, $quiz->questions);
        $this->assertCount(2, $quiz->questions[0]->options);
        $this->assertCount(2, $quiz->questions[1]->acceptedAnswers);
        $this->assertSame(20.0, $quiz->maxScore());

        $item = ModuleItem::where('itemable_type', Quiz::class)->where('itemable_id', $quiz->id)->first();
        $this->assertNotNull($item);
    }

    public function test_questions_to_draw_requires_equal_points_across_questions(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'Pool Quiz', 'questions_to_draw' => 2,
            'questions' => [
                ['question_type' => 'essay', 'prompt' => 'A', 'points' => 5],
                ['question_type' => 'essay', 'prompt' => 'B', 'points' => 10],
            ],
        ]);

        $response->assertSessionHasErrors('questions_to_draw');
        $this->assertDatabaseCount('learn_quizzes', 0);
    }

    public function test_stranger_cannot_add_a_quiz(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'X',
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleItemQuizControllerTest.php"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Write `QuizQuestionFactory`**

`app/Services/Learn/QuizQuestionFactory.php`:

```php
<?php

namespace App\Services\Learn;

use App\Models\Learn\Quiz;
use App\Models\Learn\QuizQuestion;

class QuizQuestionFactory
{
    /** @param array{question_type: string, prompt: string, points: float, difficulty?: ?string, options?: array, accepted_answers?: array} $data */
    public function create(Quiz $quiz, array $data, int $position): QuizQuestion
    {
        $question = $quiz->questions()->create([
            'question_type' => $data['question_type'],
            'prompt' => $data['prompt'],
            'points' => $data['points'],
            'position' => $position,
            'difficulty' => $data['difficulty'] ?? null,
        ]);

        if (in_array($data['question_type'], ['multiple_choice', 'true_false', 'multiple_select'], true)) {
            foreach (($data['options'] ?? []) as $optPosition => $option) {
                $question->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => $option['is_correct'] ?? false,
                    'position' => $optPosition,
                ]);
            }
        } elseif ($data['question_type'] === 'short_answer') {
            foreach (($data['accepted_answers'] ?? []) as $answer) {
                $question->acceptedAnswers()->create(['answer_text' => $answer]);
            }
        }

        return $question;
    }
}
```

- [ ] **Step 4: Add `storeQuiz` to `ModuleItemController`**

Update the constructor to inject the factory:

```php
    public function __construct(
        private CourseFileService $files,
        private QuizQuestionFactory $questionFactory,
    ) {
    }
```

Add imports:

```php
use App\Models\Learn\Quiz;
use App\Services\Learn\QuizQuestionFactory;
use Illuminate\Validation\ValidationException;
```

Add this method (anywhere among the other `store*` methods):

```php
    /** POST /learn/modules/{module}/items/quiz */
    public function storeQuiz(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'max_attempts' => 'nullable|integer|min:1',
            'questions_to_draw' => 'nullable|integer|min:1',
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options' => 'nullable|boolean',
            'due_at' => 'nullable|date',
            'questions' => 'nullable|array',
            'questions.*.question_type' => 'required_with:questions|in:multiple_choice,true_false,multiple_select,short_answer,essay',
            'questions.*.prompt' => 'required_with:questions|string',
            'questions.*.points' => 'required_with:questions|numeric|min:0',
            'questions.*.difficulty' => 'nullable|in:easy,medium,hard',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*.option_text' => 'required_with:questions.*.options|string|max:255',
            'questions.*.options.*.is_correct' => 'nullable|boolean',
            'questions.*.accepted_answers' => 'nullable|array',
            'questions.*.accepted_answers.*' => 'required_with:questions.*.accepted_answers|string|max:255',
        ]);

        $questions = $validated['questions'] ?? [];

        if (! empty($validated['questions_to_draw']) && count($questions) > 1) {
            $distinctPoints = collect($questions)->pluck('points')->unique();
            if ($distinctPoints->count() > 1) {
                throw ValidationException::withMessages([
                    'questions_to_draw' => 'When drawing a random subset of questions, every question must be worth the same points.',
                ]);
            }
        }

        $quiz = Quiz::create([
            'title' => $validated['title'],
            'instructions' => $validated['instructions'] ?? null,
            'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
            'max_attempts' => $validated['max_attempts'] ?? null,
            'questions_to_draw' => $validated['questions_to_draw'] ?? null,
            'shuffle_questions' => $validated['shuffle_questions'] ?? false,
            'shuffle_options' => $validated['shuffle_options'] ?? false,
            'due_at' => $validated['due_at'] ?? null,
        ]);

        foreach ($questions as $position => $questionData) {
            $this->questionFactory->create($quiz, $questionData, $position);
        }

        $this->attachItem($module, $quiz);

        return back()->with('success', 'Quiz added.');
    }
```

- [ ] **Step 5: Add the route**

Add to `routes/web.php`, immediately after the `items.store-assignment` line:

```php
    Route::post('/modules/{module}/items/quiz', [\App\Http\Controllers\Learn\ModuleItemController::class, 'storeQuiz'])->name('items.store-quiz');
```

- [ ] **Step 6: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleItemQuizControllerTest.php"`
Expected: PASS (3 tests).

- [ ] **Step 7: Run Phase 2's existing assignment-authoring test to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleItemAssignmentControllerTest.php"`
Expected: PASS (3 tests) — the constructor change (new injected dependency) must not break existing assignment creation.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Learn/QuizQuestionFactory.php app/Http/Controllers/Learn/ModuleItemController.php \
        routes/web.php tests/Feature/Learn/ModuleItemQuizControllerTest.php
git commit -m "feat(learn): add quiz creation endpoint (storeQuiz) with mixed question types"
```

---

### Task 5: Per-question CRUD with lock enforcement

**Files:**
- Create: `app/Http/Controllers/Learn/QuizQuestionController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/QuizQuestionControllerTest.php`

**Interfaces:**
- Consumes: `QuizQuestionFactory` (Task 4).
- Produces routes: `learn.quiz-questions.store` (POST `/learn/quizzes/{quiz}/questions`),
  `learn.quiz-questions.update` (PUT `/learn/quiz-questions/{question}`),
  `learn.quiz-questions.destroy` (DELETE `/learn/quiz-questions/{question}`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);

        $this->teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);

        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->quiz = Quiz::create(['title' => 'Quiz']);
        $this->quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
    }

    public function test_instructor_can_add_edit_and_delete_a_question_before_locking(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.quiz-questions.store', $this->quiz), [
            'question_type' => 'true_false', 'prompt' => 'The sky is blue.', 'points' => 5,
            'options' => [
                ['option_text' => 'True', 'is_correct' => true],
                ['option_text' => 'False', 'is_correct' => false],
            ],
        ])->assertRedirect();

        $question = $this->quiz->fresh()->questions()->where('prompt', 'The sky is blue.')->firstOrFail();
        $this->assertCount(2, $question->options);

        $this->actingAs($this->teacher)->put(route('learn.quiz-questions.update', $question), [
            'question_type' => 'true_false', 'prompt' => 'The sky is green.', 'points' => 8,
            'options' => [
                ['option_text' => 'True', 'is_correct' => false],
                ['option_text' => 'False', 'is_correct' => true],
            ],
        ])->assertRedirect();
        $question->refresh();
        $this->assertSame('The sky is green.', $question->prompt);
        $this->assertSame('8.00', $question->points);
        $this->assertCount(2, $question->options);
        $this->assertTrue($question->options->firstWhere('option_text', 'False')->is_correct);

        $this->actingAs($this->teacher)->delete(route('learn.quiz-questions.destroy', $question))->assertRedirect();
        $this->assertDatabaseMissing('learn_quiz_questions', ['id' => $question->id]);
    }

    public function test_locked_quiz_rejects_editing_or_deleting_but_allows_adding(): void
    {
        $question = $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'A', 'points' => 5, 'position' => 0]);
        $this->quiz->update(['is_locked' => true]);

        $this->actingAs($this->teacher)->put(route('learn.quiz-questions.update', $question), [
            'question_type' => 'essay', 'prompt' => 'Changed', 'points' => 5,
        ])->assertForbidden();

        $this->actingAs($this->teacher)->delete(route('learn.quiz-questions.destroy', $question))->assertForbidden();

        $this->actingAs($this->teacher)->post(route('learn.quiz-questions.store', $this->quiz), [
            'question_type' => 'essay', 'prompt' => 'New question', 'points' => 5,
        ])->assertRedirect();

        $this->assertSame('A', $question->fresh()->prompt);
        $this->assertTrue($this->quiz->fresh()->questions()->where('prompt', 'New question')->exists());
    }

    public function test_questions_to_draw_rejects_a_mismatched_point_value(): void
    {
        $this->quiz->update(['questions_to_draw' => 2]);
        $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'A', 'points' => 5, 'position' => 0]);

        $this->actingAs($this->teacher)->post(route('learn.quiz-questions.store', $this->quiz), [
            'question_type' => 'essay', 'prompt' => 'B', 'points' => 9,
        ])->assertSessionHasErrors('points');

        $this->assertFalse($this->quiz->fresh()->questions()->where('prompt', 'B')->exists());
    }

    public function test_stranger_cannot_manage_questions(): void
    {
        $stranger = User::factory()->create();
        $question = $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'A', 'points' => 5, 'position' => 0]);

        $this->actingAs($stranger)->post(route('learn.quiz-questions.store', $this->quiz), [
            'question_type' => 'essay', 'prompt' => 'X', 'points' => 5,
        ])->assertForbidden();
        $this->actingAs($stranger)->put(route('learn.quiz-questions.update', $question), [
            'question_type' => 'essay', 'prompt' => 'X', 'points' => 5,
        ])->assertForbidden();
        $this->actingAs($stranger)->delete(route('learn.quiz-questions.destroy', $question))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizQuestionControllerTest.php"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Write `QuizQuestionController`**

`app/Http/Controllers/Learn/QuizQuestionController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizQuestion;
use App\Services\Learn\QuizQuestionFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class QuizQuestionController extends Controller
{
    public function __construct(private QuizQuestionFactory $questionFactory)
    {
    }

    private function questionValidationRules(): array
    {
        return [
            'question_type' => 'required|in:multiple_choice,true_false,multiple_select,short_answer,essay',
            'prompt' => 'required|string',
            'points' => 'required|numeric|min:0',
            'difficulty' => 'nullable|in:easy,medium,hard',
            'options' => 'nullable|array',
            'options.*.option_text' => 'required_with:options|string|max:255',
            'options.*.is_correct' => 'nullable|boolean',
            'accepted_answers' => 'nullable|array',
            'accepted_answers.*' => 'required_with:accepted_answers|string|max:255',
        ];
    }

    private function assertPointsMatchDrawConstraint(Quiz $quiz, float $points, ?int $excludingQuestionId = null): void
    {
        if ($quiz->questions_to_draw === null) {
            return;
        }

        $existingPoints = $quiz->questions()
            ->when($excludingQuestionId, fn ($q) => $q->where('id', '!=', $excludingQuestionId))
            ->value('points');

        if ($existingPoints !== null && (float) $existingPoints !== $points) {
            throw ValidationException::withMessages([
                'points' => "This quiz draws a random question subset — every question must be worth {$existingPoints} points.",
            ]);
        }
    }

    /** POST /learn/quizzes/{quiz}/questions */
    public function store(Request $request, Quiz $quiz)
    {
        $user = Auth::user();
        abort_unless($quiz->canEdit($user), 403);

        $validated = $request->validate($this->questionValidationRules());
        $this->assertPointsMatchDrawConstraint($quiz, (float) $validated['points']);

        $position = ((int) $quiz->questions()->max('position')) + 1;
        $this->questionFactory->create($quiz, $validated, $position);

        return back()->with('success', 'Question added.');
    }

    /** PUT /learn/quiz-questions/{question} */
    public function update(Request $request, QuizQuestion $question)
    {
        $user = Auth::user();
        $quiz = $question->quiz;
        abort_unless($quiz->canEdit($user), 403);
        abort_if($quiz->is_locked, 403, 'This quiz is locked — students have already submitted attempts.');

        $validated = $request->validate($this->questionValidationRules());
        $this->assertPointsMatchDrawConstraint($quiz, (float) $validated['points'], $question->id);

        $question->update([
            'question_type' => $validated['question_type'],
            'prompt' => $validated['prompt'],
            'points' => $validated['points'],
            'difficulty' => $validated['difficulty'] ?? null,
        ]);

        $question->options()->delete();
        $question->acceptedAnswers()->delete();

        if (in_array($validated['question_type'], ['multiple_choice', 'true_false', 'multiple_select'], true)) {
            foreach (($validated['options'] ?? []) as $position => $option) {
                $question->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => $option['is_correct'] ?? false,
                    'position' => $position,
                ]);
            }
        } elseif ($validated['question_type'] === 'short_answer') {
            foreach (($validated['accepted_answers'] ?? []) as $answer) {
                $question->acceptedAnswers()->create(['answer_text' => $answer]);
            }
        }

        return back()->with('success', 'Question updated.');
    }

    /** DELETE /learn/quiz-questions/{question} */
    public function destroy(QuizQuestion $question)
    {
        $user = Auth::user();
        $quiz = $question->quiz;
        abort_unless($quiz->canEdit($user), 403);
        abort_if($quiz->is_locked, 403, 'This quiz is locked — students have already submitted attempts.');

        $question->delete();

        return back()->with('success', 'Question deleted.');
    }
}
```

- [ ] **Step 4: Add the routes**

Add to `routes/web.php`, immediately after the `items.store-quiz` line added in Task 4:

```php
    Route::post('/quizzes/{quiz}/questions', [\App\Http\Controllers\Learn\QuizQuestionController::class, 'store'])->name('quiz-questions.store');
    Route::put('/quiz-questions/{question}', [\App\Http\Controllers\Learn\QuizQuestionController::class, 'update'])->name('quiz-questions.update');
    Route::delete('/quiz-questions/{question}', [\App\Http\Controllers\Learn\QuizQuestionController::class, 'destroy'])->name('quiz-questions.destroy');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizQuestionControllerTest.php"`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/QuizQuestionController.php routes/web.php \
        tests/Feature/Learn/QuizQuestionControllerTest.php
git commit -m "feat(learn): add per-question CRUD with lock enforcement"
```

---

### Task 6: Item serialization for the `quiz` type (faculty + student)

**Files:**
- Modify: `app/Http/Controllers/Learn/CourseController.php`
- Modify: `app/Http/Controllers/StudentPortal/LearnController.php`
- Test: `tests/Feature/Learn/CourseQuizSerializationTest.php`
- Test: `tests/Feature/StudentPortal/LearnQuizSerializationTest.php`

**Interfaces:**
- Extends `CourseController::serializeItem()`'s `match(true)` with a `quiz` branch (full question
  content including `is_correct`/accepted answers — instructor-only view).
- Extends `StudentPortal\LearnController::serializeItem()`'s `match(true)` with a `quiz` branch
  (metadata + the student's own attempt summary only — never question content, `is_correct`, or
  accepted answers, since those are only revealed inside a started/graded attempt in Task 11).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Learn/CourseQuizSerializationTest.php`:

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseQuizSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_serializes_quiz_item_type_with_full_question_content(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $quiz = Quiz::create(['title' => 'Quiz', 'time_limit_minutes' => 15]);
        $quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
        $question = $quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q?', 'points' => 5, 'position' => 0]);
        $question->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);

        $response = $this->actingAs($teacher)->get(route('learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->where('course.modules.0.items.0.type', 'quiz')
            ->where('course.modules.0.items.0.quiz.time_limit_minutes', 15)
            ->where('course.modules.0.items.0.quiz.max_score', 5)
            ->where('course.modules.0.items.0.quiz.questions.0.options.0.is_correct', true)
        );
    }
}
```

`tests/Feature/StudentPortal/LearnQuizSerializationTest.php`:

```php
<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearnQuizSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_serializes_quiz_item_with_attempt_summary_and_no_answer_content(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => AcademicTerm::first()->id,
            'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $quiz = Quiz::create(['title' => 'Quiz', 'max_attempts' => 2]);
        $item = $quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);
        $question = $quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q?', 'points' => 5, 'position' => 0]);
        $question->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);

        $studentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        QuizAttempt::create([
            'learn_quiz_id' => $quiz->id, 'student_id' => $studentId, 'attempt_number' => 1,
            'question_order' => [$question->id], 'started_at' => now()->subMinutes(10),
            'submitted_at' => now(), 'score' => 5,
        ]);

        session(['student_pisaysystemID' => "PS{$studentId}"]);

        $response = $this->get(route('student-portal.learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->where('course.modules.0.items.0.type', 'quiz')
            ->where('course.modules.0.items.0.quiz.attempts_used', 1)
            ->where('course.modules.0.items.0.quiz.best_score', 5)
            ->where('course.modules.0.items.0.quiz.can_start_new_attempt', true)
            ->missing('course.modules.0.items.0.quiz.questions')
        );
    }
}
```

- [ ] **Step 2: Run to verify both fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseQuizSerializationTest.php tests/Feature/StudentPortal/LearnQuizSerializationTest.php"`
Expected: FAIL — `type` resolves to `'unknown'`, no `quiz` key present.

- [ ] **Step 3: Update `CourseController`**

Add the import:

```php
use App\Models\Learn\Quiz;
```

In `show()`, add a call alongside the existing `loadAssignmentRubrics`:

```php
        $this->loadAssignmentRubrics($course);
        $this->loadQuizQuestions($course);
```

Add this private method (near `loadAssignmentRubrics`):

```php
    private function loadQuizQuestions(Course $course): void
    {
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Quiz) {
                    $item->itemable->load('questions.options', 'questions.acceptedAnswers');
                }
            }
        }
    }
```

Update `serializeItem()`'s `match(true)`:

```php
        $type = match (true) {
            $itemable instanceof LearnPage => 'page',
            $itemable instanceof LearnFile => 'file',
            $itemable instanceof Assignment => 'assignment',
            $itemable instanceof Quiz => 'quiz',
            default => 'unknown',
        };
```

Add a `'quiz'` key to the returned array (alongside the existing `'assignment'` key):

```php
            'quiz' => $itemable instanceof Quiz ? [
                'id' => $itemable->id,
                'instructions' => $itemable->instructions,
                'time_limit_minutes' => $itemable->time_limit_minutes,
                'max_attempts' => $itemable->max_attempts,
                'questions_to_draw' => $itemable->questions_to_draw,
                'shuffle_questions' => $itemable->shuffle_questions,
                'shuffle_options' => $itemable->shuffle_options,
                'due_at' => $itemable->due_at?->toIso8601String(),
                'is_locked' => $itemable->is_locked,
                'max_score' => $itemable->maxScore(),
                'question_count' => $itemable->questions->count(),
                'questions' => $itemable->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'question_type' => $q->question_type,
                    'prompt' => $q->prompt,
                    'points' => (float) $q->points,
                    'difficulty' => $q->difficulty,
                    'options' => $q->options->map(fn ($o) => [
                        'id' => $o->id, 'option_text' => $o->option_text, 'is_correct' => $o->is_correct,
                    ])->values(),
                    'accepted_answers' => $q->acceptedAnswers->pluck('answer_text')->values(),
                ])->values(),
            ] : null,
```

- [ ] **Step 4: Update `StudentPortal\LearnController`**

Add imports:

```php
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
```

In `show()`, add a call alongside the existing `loadAssignmentRubrics`:

```php
        $this->loadAssignmentRubrics($course);
        $this->loadQuizQuestionCounts($course);
```

Add this private method (near `loadAssignmentRubrics`):

```php
    private function loadQuizQuestionCounts(Course $course): void
    {
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Quiz) {
                    $item->itemable->load('questions');
                }
            }
        }
    }
```

Update `serializeItem()`'s `match(true)`:

```php
        $type = match (true) {
            $itemable instanceof LearnPage => 'page',
            $itemable instanceof LearnFile => 'file',
            $itemable instanceof Assignment => 'assignment',
            $itemable instanceof Quiz => 'quiz',
            default => 'unknown',
        };
```

Add a `$quizData` block (mirroring the existing `$assignmentData` block) before the `return`:

```php
        $quizData = null;
        if ($itemable instanceof Quiz) {
            $attempts = QuizAttempt::where('learn_quiz_id', $itemable->id)
                ->where('student_id', $studentId)
                ->orderByDesc('attempt_number')
                ->get();
            $bestScore = $attempts->whereNotNull('score')->max('score');
            $inProgress = $attempts->first(fn ($a) => $a->submitted_at === null);

            $quizData = [
                'id' => $itemable->id,
                'instructions' => $itemable->instructions,
                'time_limit_minutes' => $itemable->time_limit_minutes,
                'max_attempts' => $itemable->max_attempts,
                'due_at' => $itemable->due_at?->toIso8601String(),
                'max_score' => $itemable->maxScore(),
                'question_count' => $itemable->questions_to_draw ?? $itemable->questions->count(),
                'attempts_used' => $attempts->count(),
                'best_score' => $bestScore !== null ? (float) $bestScore : null,
                'can_start_new_attempt' => $itemable->max_attempts === null || $attempts->count() < $itemable->max_attempts,
                'in_progress_attempt_id' => $inProgress?->id,
            ];
        }
```

Add `'quiz' => $quizData,` to the returned array (alongside the existing `'assignment' => $assignmentData,` line).

- [ ] **Step 5: Run the tests**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseQuizSerializationTest.php tests/Feature/StudentPortal/LearnQuizSerializationTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 6: Run existing Assignment serialization tests to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseAssignmentSerializationTest.php"`
Expected: PASS (1 test).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Learn/CourseController.php app/Http/Controllers/StudentPortal/LearnController.php \
        tests/Feature/Learn/CourseQuizSerializationTest.php tests/Feature/StudentPortal/LearnQuizSerializationTest.php
git commit -m "feat(learn): serialize quiz module items for faculty and student views"
```

---

### Task 7: Attempt start (sampling, shuffling, `max_attempts` enforcement)

**Files:**
- Create: `app/Services/Learn/QuizAttemptService.php`
- Create: `app/Http/Controllers/StudentPortal/QuizAttemptController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/StudentPortal/QuizAttemptStartTest.php`

**Interfaces:**
- Produces: `QuizAttemptService::start(Quiz $quiz, int $studentId): QuizAttempt` — resumes an
  existing in-progress attempt instead of creating a duplicate; throws `ValidationException` when
  `max_attempts` is exhausted.
- Produces routes (all four registered now, `answer`/`submit`/`show` implemented in Tasks 8/9/11):
  `student-portal.learn.quiz-attempts.start` (POST), `.answer` (PUT), `.submit` (POST), `.show`
  (GET) — registering the URIs now lets `start()`'s redirect target resolve immediately; Laravel
  only resolves a route's controller method when that specific route is dispatched, so the
  not-yet-implemented methods cause no error until their own task adds them.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizAttemptStartTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $this->quiz = Quiz::create(['title' => 'Quiz', 'max_attempts' => 1, 'questions_to_draw' => 2]);
        $this->quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);
        foreach (range(1, 4) as $i) {
            $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => "Q{$i}", 'points' => 5, 'position' => $i]);
        }

        $this->studentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $this->studentId, 'pisaysystemID' => "PS{$this->studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        session(['student_pisaysystemID' => "PS{$this->studentId}"]);
    }

    public function test_starting_creates_an_attempt_with_a_sampled_question_order(): void
    {
        $response = $this->post(route('student-portal.learn.quiz-attempts.start', $this->quiz));
        $response->assertRedirect();

        $attempt = QuizAttempt::where('learn_quiz_id', $this->quiz->id)->where('student_id', $this->studentId)->firstOrFail();
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertCount(2, $attempt->question_order);
        $this->assertNull($attempt->submitted_at);

        $allQuestionIds = $this->quiz->questions->pluck('id')->all();
        foreach ($attempt->question_order as $id) {
            $this->assertContains($id, $allQuestionIds);
        }
    }

    public function test_starting_again_while_in_progress_resumes_the_same_attempt(): void
    {
        $this->post(route('student-portal.learn.quiz-attempts.start', $this->quiz));
        $first = QuizAttempt::where('learn_quiz_id', $this->quiz->id)->first();

        $this->post(route('student-portal.learn.quiz-attempts.start', $this->quiz));
        $this->assertSame(1, QuizAttempt::where('learn_quiz_id', $this->quiz->id)->count());
        $this->assertSame($first->id, QuizAttempt::where('learn_quiz_id', $this->quiz->id)->first()->id);
    }

    public function test_max_attempts_is_enforced_once_a_prior_attempt_is_submitted(): void
    {
        QuizAttempt::create([
            'learn_quiz_id' => $this->quiz->id, 'student_id' => $this->studentId, 'attempt_number' => 1,
            'question_order' => [], 'started_at' => now()->subHour(), 'submitted_at' => now(), 'score' => 10,
        ]);

        $response = $this->post(route('student-portal.learn.quiz-attempts.start', $this->quiz));
        $response->assertSessionHasErrors('quiz');
        $this->assertSame(1, QuizAttempt::where('learn_quiz_id', $this->quiz->id)->count());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/QuizAttemptStartTest.php"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Write `QuizAttemptService`**

`app/Services/Learn/QuizAttemptService.php`:

```php
<?php

namespace App\Services\Learn;

use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use Illuminate\Validation\ValidationException;

class QuizAttemptService
{
    public function start(Quiz $quiz, int $studentId): QuizAttempt
    {
        $inProgress = QuizAttempt::where('learn_quiz_id', $quiz->id)
            ->where('student_id', $studentId)
            ->whereNull('submitted_at')
            ->first();
        if ($inProgress) {
            return $inProgress;
        }

        $attemptsUsed = QuizAttempt::where('learn_quiz_id', $quiz->id)->where('student_id', $studentId)->count();

        if ($quiz->max_attempts !== null && $attemptsUsed >= $quiz->max_attempts) {
            throw ValidationException::withMessages([
                'quiz' => 'You have used all of your attempts for this quiz.',
            ]);
        }

        return QuizAttempt::create([
            'learn_quiz_id' => $quiz->id,
            'student_id' => $studentId,
            'attempt_number' => $attemptsUsed + 1,
            'question_order' => $this->buildQuestionOrder($quiz),
            'started_at' => now(),
        ]);
    }

    /**
     * Samples questions_to_draw questions at random (or uses every question when unset),
     * keeping the sampled subset in original position order — then shuffles that final order
     * if shuffle_questions is set. Sampling and shuffling are independent: a drawn-but-unshuffled
     * quiz still presents its random subset in a stable, predictable order.
     *
     * @return array<int, int>
     */
    private function buildQuestionOrder(Quiz $quiz): array
    {
        $allQuestionIds = $quiz->questions()->pluck('id')->all(); // already position-ordered

        if ($quiz->questions_to_draw !== null && $quiz->questions_to_draw < count($allQuestionIds)) {
            $sampled = $allQuestionIds;
            shuffle($sampled);
            $sampled = array_slice($sampled, 0, $quiz->questions_to_draw);
            $questionIds = array_values(array_intersect($allQuestionIds, $sampled));
        } else {
            $questionIds = $allQuestionIds;
        }

        if ($quiz->shuffle_questions) {
            shuffle($questionIds);
        }

        return $questionIds;
    }
}
```

- [ ] **Step 4: Write `QuizAttemptController`**

`app/Http/Controllers/StudentPortal/QuizAttemptController.php`:

```php
<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Learn\Quiz;
use App\Models\Student;
use App\Services\Learn\QuizAttemptService;

class QuizAttemptController extends Controller
{
    public function __construct(private QuizAttemptService $attemptService)
    {
    }

    /** POST /student-portal/learn/quizzes/{quiz}/attempts */
    public function start(Quiz $quiz)
    {
        $student = $this->currentStudent();
        $course = $quiz->course();
        abort_if(! $course, 404);
        abort_unless($course->isVisibleToStudent($student->id), 403);

        $attempt = $this->attemptService->start($quiz, $student->id);

        return redirect()->route('student-portal.learn.quiz-attempts.show', $attempt);
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
```

- [ ] **Step 5: Add the routes**

Add inside the `student.portal` middleware group in `routes/web.php`, immediately after the
existing `learn.submissions.file` line:

```php
        Route::post('/learn/quizzes/{quiz}/attempts', [\App\Http\Controllers\StudentPortal\QuizAttemptController::class, 'start'])->name('learn.quiz-attempts.start');
        Route::put('/learn/quiz-attempts/{attempt}/answers/{question}', [\App\Http\Controllers\StudentPortal\QuizAttemptController::class, 'answer'])->name('learn.quiz-attempts.answer');
        Route::post('/learn/quiz-attempts/{attempt}/submit', [\App\Http\Controllers\StudentPortal\QuizAttemptController::class, 'submit'])->name('learn.quiz-attempts.submit');
        Route::get('/learn/quiz-attempts/{attempt}', [\App\Http\Controllers\StudentPortal\QuizAttemptController::class, 'show'])->name('learn.quiz-attempts.show');
```

Note: the route names above omit the group's `student-portal.` prefix in this snippet for
readability — Laravel applies it automatically from the enclosing `Route::prefix('student-portal')->name('student-portal.')` group, so the full names are `student-portal.learn.quiz-attempts.start` etc., matching what the test and controller reference.

- [ ] **Step 6: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/QuizAttemptStartTest.php"`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Services/Learn/QuizAttemptService.php app/Http/Controllers/StudentPortal/QuizAttemptController.php \
        routes/web.php tests/Feature/StudentPortal/QuizAttemptStartTest.php
git commit -m "feat(learn): add quiz attempt start (sampling, shuffling, max_attempts)"
```

---

### Task 8: Finalize core — auto-grading, submit, lazy expiry

**Files:**
- Modify: `app/Services/Learn/QuizAttemptService.php`
- Modify: `app/Http/Controllers/StudentPortal/QuizAttemptController.php`
- Test: `tests/Feature/Learn/QuizAttemptFinalizeTest.php`

**Interfaces:**
- Produces: `QuizAttemptService::submit(QuizAttempt): QuizAttempt`,
  `QuizAttemptService::finalizeIfExpired(QuizAttempt): QuizAttempt` — both funnel through a
  shared private `finalize()` so a manual submit and a lazy-expiry auto-submit grade identically;
  consumed by Task 9 (answer autosave, rejects writes once submitted) and Task 10 (attempt show,
  calls `finalizeIfExpired` on every load).
- Implements route: `student-portal.learn.quiz-attempts.submit` (registered in Task 7).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Services\Learn\QuizAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAttemptFinalizeTest extends TestCase
{
    use RefreshDatabase;

    private QuizAttemptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QuizAttemptService::class);
    }

    private function makeAttempt(Quiz $quiz, array $questionIds, ?\Illuminate\Support\Carbon $startedAt = null): QuizAttempt
    {
        return QuizAttempt::create([
            'learn_quiz_id' => $quiz->id, 'student_id' => mt_rand(1, 999999999), 'attempt_number' => 1,
            'question_order' => $questionIds, 'started_at' => $startedAt ?? now(),
        ]);
    }

    public function test_single_select_grades_correct_and_incorrect(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);
        $correct = $q->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $wrong = $q->options()->create(['option_text' => 'B', 'is_correct' => false, 'position' => 1]);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        $answer = QuizAttemptAnswer::create(['learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $q->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $correct->id]);

        $this->service->submit($attempt);

        $this->assertTrue($answer->fresh()->is_correct);
        $this->assertSame('10.00', $answer->fresh()->points_earned);
        $this->assertSame('10.00', $attempt->fresh()->score);
    }

    public function test_multi_select_awards_proportional_partial_credit_with_negative_floor(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'multiple_select', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);
        $c1 = $q->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $c2 = $q->options()->create(['option_text' => 'B', 'is_correct' => true, 'position' => 1]);
        $wrong = $q->options()->create(['option_text' => 'C', 'is_correct' => false, 'position' => 2]);

        // Selects both correct + the one wrong option: (2 correct - 1 incorrect) / 2 total correct = 0.5 -> 5.00
        $attempt = $this->makeAttempt($quiz, [$q->id]);
        $answer = QuizAttemptAnswer::create(['learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $q->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $c1->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $c2->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $wrong->id]);

        $this->service->submit($attempt);

        $this->assertFalse($answer->fresh()->is_correct);
        $this->assertSame('5.00', $answer->fresh()->points_earned);
    }

    public function test_multi_select_floors_at_zero_when_wrong_selections_outweigh_correct(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'multiple_select', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);
        $c1 = $q->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $w1 = $q->options()->create(['option_text' => 'B', 'is_correct' => false, 'position' => 1]);
        $w2 = $q->options()->create(['option_text' => 'C', 'is_correct' => false, 'position' => 2]);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        $answer = QuizAttemptAnswer::create(['learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $q->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $w1->id]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $w2->id]);

        $this->service->submit($attempt);

        $this->assertSame('0.00', $answer->fresh()->points_earned);
    }

    public function test_short_answer_matches_case_insensitively_and_trimmed(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'short_answer', 'prompt' => 'Q', 'points' => 5, 'position' => 0]);
        $q->acceptedAnswers()->create(['answer_text' => 'Manila']);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        QuizAttemptAnswer::create([
            'learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $q->id, 'answer_text' => '  manila  ',
        ]);

        $this->service->submit($attempt);

        $answer = $attempt->answers()->first();
        $this->assertTrue($answer->is_correct);
        $this->assertSame('5.00', $answer->points_earned);
    }

    public function test_essay_leaves_attempt_score_null_until_manually_graded(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        QuizAttemptAnswer::create([
            'learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $q->id, 'answer_text' => 'My essay.',
        ]);

        $this->service->submit($attempt);

        $this->assertNotNull($attempt->fresh()->submitted_at);
        $this->assertNull($attempt->fresh()->score);
        $answer = $attempt->answers()->first();
        $this->assertNull($answer->is_correct);
        $this->assertNull($answer->points_earned);
    }

    public function test_submit_locks_the_quiz_on_first_submission_only(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);

        $this->assertFalse($quiz->is_locked);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        $this->service->submit($attempt);

        $this->assertTrue($quiz->fresh()->is_locked);
    }

    public function test_lazy_finalize_backfills_submitted_at_to_the_deadline_and_marks_auto_submitted(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz', 'time_limit_minutes' => 10]);
        $q = $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);

        $startedAt = now()->subMinutes(30);
        $attempt = $this->makeAttempt($quiz, [$q->id], $startedAt);

        $result = $this->service->finalizeIfExpired($attempt);

        $this->assertTrue($result->auto_submitted);
        $this->assertEqualsWithDelta($startedAt->copy()->addMinutes(10)->timestamp, $result->submitted_at->timestamp, 1);
    }

    public function test_finalize_if_expired_is_a_no_op_before_the_deadline(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz', 'time_limit_minutes' => 60]);
        $q = $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);

        $attempt = $this->makeAttempt($quiz, [$q->id], now());

        $result = $this->service->finalizeIfExpired($attempt);

        $this->assertNull($result->submitted_at);
    }

    public function test_submit_is_idempotent_once_already_submitted(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $q = $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);

        $attempt = $this->makeAttempt($quiz, [$q->id]);
        $this->service->submit($attempt);
        $firstSubmittedAt = $attempt->fresh()->submitted_at;

        $this->service->submit($attempt->fresh());

        $this->assertEquals($firstSubmittedAt, $attempt->fresh()->submitted_at);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizAttemptFinalizeTest.php"`
Expected: FAIL — `submit()`/`finalizeIfExpired()` don't exist on the service yet.

- [ ] **Step 3: Add grading/finalize methods to `QuizAttemptService`**

Add these imports to `app/Services/Learn/QuizAttemptService.php`:

```php
use App\Models\Learn\QuizAttemptAnswer;
use App\Models\Learn\QuizQuestion;
```

Add these methods to the class (alongside `start()`/`buildQuestionOrder()`):

```php
    public function submit(QuizAttempt $attempt): QuizAttempt
    {
        return $this->finalize($attempt, autoSubmitted: false);
    }

    public function finalizeIfExpired(QuizAttempt $attempt): QuizAttempt
    {
        if ($attempt->isSubmitted()) {
            return $attempt;
        }

        $quiz = $attempt->quiz;
        if ($quiz->time_limit_minutes === null) {
            return $attempt;
        }

        $deadline = $attempt->started_at->copy()->addMinutes($quiz->time_limit_minutes);
        if (now()->lessThan($deadline)) {
            return $attempt;
        }

        return $this->finalize($attempt, autoSubmitted: true);
    }

    private function finalize(QuizAttempt $attempt, bool $autoSubmitted): QuizAttempt
    {
        if ($attempt->isSubmitted()) {
            return $attempt;
        }

        $quiz = $attempt->quiz;
        $questions = $quiz->questions()->whereIn('id', $attempt->question_order)->get()->keyBy('id');

        $hasPendingEssay = false;
        $total = 0.0;

        foreach ($attempt->question_order as $questionId) {
            $question = $questions->get($questionId);
            if (! $question) {
                continue;
            }

            $answer = $attempt->answers()->firstOrCreate(['learn_quiz_question_id' => $questionId]);
            $this->gradeAnswer($question, $answer);

            if ($answer->fresh()->points_earned === null) {
                $hasPendingEssay = true;
            } else {
                $total += (float) $answer->fresh()->points_earned;
            }
        }

        $attempt->update([
            'submitted_at' => $autoSubmitted
                ? $attempt->started_at->copy()->addMinutes($quiz->time_limit_minutes)
                : now(),
            'auto_submitted' => $autoSubmitted,
            'score' => $hasPendingEssay ? null : $total,
        ]);

        if (! $quiz->is_locked) {
            $quiz->update(['is_locked' => true]);
        }

        return $attempt->fresh();
    }

    private function gradeAnswer(QuizQuestion $question, QuizAttemptAnswer $answer): void
    {
        match ($question->question_type) {
            'multiple_choice', 'true_false' => $this->gradeSingleSelect($question, $answer),
            'multiple_select' => $this->gradeMultiSelect($question, $answer),
            'short_answer' => $this->gradeShortAnswer($question, $answer),
            'essay' => null, // stays ungraded until an instructor scores it manually
        };
    }

    private function gradeSingleSelect(QuizQuestion $question, QuizAttemptAnswer $answer): void
    {
        $selectedId = $answer->selectedOptions()->value('learn_quiz_question_option_id');
        $correctId = $question->options()->where('is_correct', true)->value('id');

        $isCorrect = $selectedId !== null && $selectedId === $correctId;
        $answer->update([
            'is_correct' => $isCorrect,
            'points_earned' => $isCorrect ? (float) $question->points : 0.0,
        ]);
    }

    private function gradeMultiSelect(QuizQuestion $question, QuizAttemptAnswer $answer): void
    {
        $selectedIds = $answer->selectedOptions()->pluck('learn_quiz_question_option_id')->all();
        $correctIds = $question->options()->where('is_correct', true)->pluck('id')->all();
        $incorrectIds = $question->options()->where('is_correct', false)->pluck('id')->all();

        $correctSelected = count(array_intersect($selectedIds, $correctIds));
        $incorrectSelected = count(array_intersect($selectedIds, $incorrectIds));
        $totalCorrect = count($correctIds);

        $fraction = $totalCorrect > 0 ? max(0, $correctSelected - $incorrectSelected) / $totalCorrect : 0;
        $pointsEarned = round((float) $question->points * $fraction, 2);

        $answer->update([
            'is_correct' => $pointsEarned === round((float) $question->points, 2),
            'points_earned' => $pointsEarned,
        ]);
    }

    private function gradeShortAnswer(QuizQuestion $question, QuizAttemptAnswer $answer): void
    {
        $submitted = trim(mb_strtolower((string) $answer->answer_text));
        $accepted = $question->acceptedAnswers()->pluck('answer_text')
            ->map(fn ($a) => trim(mb_strtolower($a)))
            ->all();

        $isCorrect = $submitted !== '' && in_array($submitted, $accepted, true);
        $answer->update([
            'is_correct' => $isCorrect,
            'points_earned' => $isCorrect ? (float) $question->points : 0.0,
        ]);
    }
```

- [ ] **Step 4: Add `submit` to `QuizAttemptController`**

Add this method to `app/Http/Controllers/StudentPortal/QuizAttemptController.php`:

```php
    /** POST /student-portal/learn/quiz-attempts/{attempt}/submit */
    public function submit(\App\Models\Learn\QuizAttempt $attempt)
    {
        $student = $this->currentStudent();
        abort_unless($attempt->student_id === $student->id, 403);

        $this->attemptService->submit($attempt);

        return redirect()->route('student-portal.learn.quiz-attempts.show', $attempt);
    }
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizAttemptFinalizeTest.php"`
Expected: PASS (9 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Learn/QuizAttemptService.php app/Http/Controllers/StudentPortal/QuizAttemptController.php \
        tests/Feature/Learn/QuizAttemptFinalizeTest.php
git commit -m "feat(learn): add quiz auto-grading, submit, and lazy expiry finalization"
```

---

### Task 9: Answer autosave

**Files:**
- Modify: `app/Services/Learn/QuizAttemptService.php`
- Modify: `app/Http/Controllers/StudentPortal/QuizAttemptController.php`
- Test: `tests/Feature/StudentPortal/QuizAttemptAnswerTest.php`

**Interfaces:**
- Consumes: `QuizAttemptService::finalizeIfExpired()` (Task 8) — every answer write first checks
  expiry, so a stale in-progress attempt gets lazily finalized and rejects the write instead of
  silently accepting an answer past the deadline.
- Produces: `QuizAttemptService::saveAnswer(QuizAttempt, QuizQuestion, array): QuizAttemptAnswer`.
- Implements route: `student-portal.learn.quiz-attempts.answer` (registered in Task 7).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizAttemptAnswerTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
    private $mcQuestion;
    private $optionA;
    private $optionB;
    private int $studentId;
    private QuizAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => AcademicTerm::first()->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $this->quiz = Quiz::create(['title' => 'Quiz', 'time_limit_minutes' => 10]);
        $this->quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);
        $this->mcQuestion = $this->quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q', 'points' => 5, 'position' => 0]);
        $this->optionA = $this->mcQuestion->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $this->optionB = $this->mcQuestion->options()->create(['option_text' => 'B', 'is_correct' => false, 'position' => 1]);

        $this->studentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $this->studentId, 'pisaysystemID' => "PS{$this->studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        session(['student_pisaysystemID' => "PS{$this->studentId}"]);

        $this->attempt = QuizAttempt::create([
            'learn_quiz_id' => $this->quiz->id, 'student_id' => $this->studentId, 'attempt_number' => 1,
            'question_order' => [$this->mcQuestion->id], 'started_at' => now(),
        ]);
    }

    public function test_saving_an_answer_persists_the_selected_option(): void
    {
        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionA->id],
        ])->assertRedirect();

        $answer = $this->attempt->answers()->first();
        $this->assertSame([$this->optionA->id], $answer->selectedOptions->pluck('learn_quiz_question_option_id')->all());
    }

    public function test_resaving_replaces_the_previous_selection(): void
    {
        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionA->id],
        ]);
        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionB->id],
        ]);

        $answer = $this->attempt->answers()->first();
        $this->assertSame([$this->optionB->id], $answer->fresh()->selectedOptions->pluck('learn_quiz_question_option_id')->all());
    }

    public function test_answering_a_question_not_in_this_attempt_is_rejected(): void
    {
        $otherQuestion = $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Not drawn', 'points' => 5, 'position' => 1]);

        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $otherQuestion]), [
            'answer_text' => 'x',
        ])->assertNotFound();
    }

    public function test_answering_after_submission_is_rejected(): void
    {
        $this->attempt->update(['submitted_at' => now(), 'score' => 5]);

        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionA->id],
        ])->assertForbidden();
    }

    public function test_answering_past_the_time_limit_lazily_finalizes_and_rejects(): void
    {
        $this->attempt->update(['started_at' => now()->subMinutes(20)]);

        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionA->id],
        ])->assertForbidden();

        $this->assertNotNull($this->attempt->fresh()->submitted_at);
        $this->assertTrue($this->attempt->fresh()->auto_submitted);
    }

    public function test_a_different_student_cannot_answer_this_attempt(): void
    {
        $otherStudentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $otherStudentId, 'pisaysystemID' => "PS{$otherStudentId}", 'firstname' => 'Other', 'lastname' => 'Student',
        ]);
        session(['student_pisaysystemID' => "PS{$otherStudentId}"]);

        $this->put(route('student-portal.learn.quiz-attempts.answer', [$this->attempt, $this->mcQuestion]), [
            'selected_option_ids' => [$this->optionA->id],
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/QuizAttemptAnswerTest.php"`
Expected: FAIL — `answer()` isn't implemented yet (route exists from Task 7 but has no method body).

- [ ] **Step 3: Add `saveAnswer` to `QuizAttemptService`**

Add this method to `app/Services/Learn/QuizAttemptService.php` (alongside the others):

```php
    public function saveAnswer(QuizAttempt $attempt, QuizQuestion $question, array $data): QuizAttemptAnswer
    {
        $answer = QuizAttemptAnswer::updateOrCreate(
            ['learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $question->id],
            ['answer_text' => $data['answer_text'] ?? null]
        );

        if (in_array($question->question_type, ['multiple_choice', 'true_false', 'multiple_select'], true)) {
            $answer->selectedOptions()->delete();
            foreach (($data['selected_option_ids'] ?? []) as $optionId) {
                $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $optionId]);
            }
        }

        return $answer->fresh();
    }
```

- [ ] **Step 4: Add `answer` to `QuizAttemptController`**

Add the imports:

```php
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizQuestion;
use Illuminate\Http\Request;
```

Add this method:

```php
    /** PUT /student-portal/learn/quiz-attempts/{attempt}/answers/{question} */
    public function answer(Request $request, QuizAttempt $attempt, QuizQuestion $question)
    {
        $student = $this->currentStudent();
        abort_unless($attempt->student_id === $student->id, 403);
        abort_unless(in_array($question->id, $attempt->question_order, true), 404);

        $attempt = $this->attemptService->finalizeIfExpired($attempt);
        abort_if($attempt->isSubmitted(), 403, 'This attempt has already been submitted.');

        $validated = $request->validate([
            'answer_text' => 'nullable|string',
            'selected_option_ids' => 'nullable|array',
            'selected_option_ids.*' => 'integer|exists:learn_quiz_question_options,id',
        ]);

        $this->attemptService->saveAnswer($attempt, $question, $validated);

        return back()->with('success', 'Answer saved.');
    }
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/QuizAttemptAnswerTest.php"`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Learn/QuizAttemptService.php app/Http/Controllers/StudentPortal/QuizAttemptController.php \
        tests/Feature/StudentPortal/QuizAttemptAnswerTest.php
git commit -m "feat(learn): add quiz answer autosave"
```

---

### Task 10: Attempt show (student-facing, deterministic option shuffle)

**Files:**
- Modify: `app/Services/Learn/QuizAttemptService.php`
- Modify: `app/Http/Controllers/StudentPortal/QuizAttemptController.php`
- Test: `tests/Feature/StudentPortal/QuizAttemptShowTest.php`

**Interfaces:**
- Produces: `QuizAttemptService::shuffledOptionsFor(QuizAttempt, QuizQuestion): Collection` — sorts
  by `crc32("{attemptId}-{questionId}-{optionId}")` when `shuffle_options` is set, so the same
  attempt+question always reproduces the same order on reload; reused by Task 11's grading view.
- Implements route: `student-portal.learn.quiz-attempts.show` (registered in Task 7).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizAttemptShowTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
    private $question;
    private int $studentId;
    private QuizAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => AcademicTerm::first()->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $this->quiz = Quiz::create(['title' => 'Quiz', 'shuffle_options' => true]);
        $this->quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);
        $this->question = $this->quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q', 'points' => 5, 'position' => 0]);
        $this->question->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $this->question->options()->create(['option_text' => 'B', 'is_correct' => false, 'position' => 1]);
        $this->question->options()->create(['option_text' => 'C', 'is_correct' => false, 'position' => 2]);
        $this->question->options()->create(['option_text' => 'D', 'is_correct' => false, 'position' => 3]);

        $this->studentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $this->studentId, 'pisaysystemID' => "PS{$this->studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        session(['student_pisaysystemID' => "PS{$this->studentId}"]);

        $this->attempt = QuizAttempt::create([
            'learn_quiz_id' => $this->quiz->id, 'student_id' => $this->studentId, 'attempt_number' => 1,
            'question_order' => [$this->question->id], 'started_at' => now(),
        ]);
    }

    public function test_option_order_is_a_reproducible_shuffle_across_two_loads(): void
    {
        $first = $this->get(route('student-portal.learn.quiz-attempts.show', $this->attempt));
        $second = $this->get(route('student-portal.learn.quiz-attempts.show', $this->attempt));

        $extractOrder = fn ($response) => collect($response->original->getData()['page']['props']['attempt']['questions'])
            ->first()['options'];

        $orderA = collect($extractOrder($first))->pluck('id')->all();
        $orderB = collect($extractOrder($second))->pluck('id')->all();

        $this->assertSame($orderA, $orderB);
        $this->assertEqualsCanonicalizing($this->question->options->pluck('id')->all(), $orderA);
    }

    public function test_answer_correctness_is_hidden_while_in_progress_and_shown_after_submission(): void
    {
        $option = $this->question->options()->where('is_correct', true)->first();
        QuizAttemptAnswer::create(['learn_quiz_attempt_id' => $this->attempt->id, 'learn_quiz_question_id' => $this->question->id])
            ->selectedOptions()->create(['learn_quiz_question_option_id' => $option->id]);

        $inProgress = $this->get(route('student-portal.learn.quiz-attempts.show', $this->attempt));
        $inProgress->assertInertia(fn ($page) => $page->where('attempt.questions.0.your_answer.is_correct', null));

        $this->attempt->update(['submitted_at' => now(), 'score' => 5]);
        $this->attempt->answers()->first()->update(['is_correct' => true, 'points_earned' => 5]);

        $submitted = $this->get(route('student-portal.learn.quiz-attempts.show', $this->attempt));
        $submitted->assertInertia(fn ($page) => $page->where('attempt.questions.0.your_answer.is_correct', true));
    }

    public function test_a_different_student_cannot_view_this_attempt(): void
    {
        $otherStudentId = mt_rand(1, 999999999);
        DB::table('students')->insert([
            'id' => $otherStudentId, 'pisaysystemID' => "PS{$otherStudentId}", 'firstname' => 'Other', 'lastname' => 'Student',
        ]);
        session(['student_pisaysystemID' => "PS{$otherStudentId}"]);

        $this->get(route('student-portal.learn.quiz-attempts.show', $this->attempt))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/QuizAttemptShowTest.php"`
Expected: FAIL — `show()` isn't implemented yet.

- [ ] **Step 3: Add `shuffledOptionsFor` to `QuizAttemptService`**

Add this method (alongside the others):

```php
    /** Reproducible per-attempt shuffle — same attempt+question always yields the same order. */
    public function shuffledOptionsFor(QuizAttempt $attempt, QuizQuestion $question): \Illuminate\Support\Collection
    {
        $options = $question->options;

        if (! $attempt->quiz->shuffle_options) {
            return $options;
        }

        return $options->sortBy(fn ($option) => crc32("{$attempt->id}-{$question->id}-{$option->id}"))->values();
    }
```

- [ ] **Step 4: Add `show` to `QuizAttemptController`**

Add the imports:

```php
use Inertia\Inertia;
use Inertia\Response;
```

Add this method:

```php
    /** GET /student-portal/learn/quiz-attempts/{attempt} */
    public function show(QuizAttempt $attempt): Response
    {
        $student = $this->currentStudent();
        abort_unless($attempt->student_id === $student->id, 403);

        $attempt = $this->attemptService->finalizeIfExpired($attempt);
        $quiz = $attempt->quiz;

        $questions = $quiz->questions()->whereIn('id', $attempt->question_order)->get()->keyBy('id');
        $answers = $attempt->answers()->with('selectedOptions')->get()->keyBy('learn_quiz_question_id');

        $orderedQuestions = collect($attempt->question_order)
            ->map(function ($id) use ($questions, $answers, $attempt) {
                $question = $questions->get($id);
                if (! $question) {
                    return null;
                }
                $answer = $answers->get($id);
                $hasOptions = in_array($question->question_type, ['multiple_choice', 'true_false', 'multiple_select'], true);

                return [
                    'id' => $question->id,
                    'question_type' => $question->question_type,
                    'prompt' => $question->prompt,
                    'points' => (float) $question->points,
                    'options' => $hasOptions
                        ? $this->attemptService->shuffledOptionsFor($attempt, $question)
                            ->map(fn ($o) => ['id' => $o->id, 'option_text' => $o->option_text])->values()
                        : null,
                    'your_answer' => $answer ? [
                        'answer_text' => $answer->answer_text,
                        'selected_option_ids' => $answer->selectedOptions->pluck('learn_quiz_question_option_id')->values(),
                        'is_correct' => $attempt->isSubmitted() ? $answer->is_correct : null,
                        'points_earned' => $attempt->isSubmitted() && $answer->points_earned !== null
                            ? (float) $answer->points_earned : null,
                    ] : null,
                ];
            })
            ->filter()
            ->values();

        return Inertia::render('StudentPortal/Learn/QuizAttempt', [
            'attempt' => [
                'id' => $attempt->id,
                'quiz_title' => $quiz->title,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'started_at' => $attempt->started_at->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'auto_submitted' => $attempt->auto_submitted,
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'max_score' => $quiz->maxScore(),
                'is_submitted' => $attempt->isSubmitted(),
                'questions' => $orderedQuestions,
            ],
        ]);
    }
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/QuizAttemptShowTest.php"`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Learn/QuizAttemptService.php app/Http/Controllers/StudentPortal/QuizAttemptController.php \
        tests/Feature/StudentPortal/QuizAttemptShowTest.php
git commit -m "feat(learn): add student-facing quiz attempt view with reproducible option shuffle"
```

---

### Task 11: Instructor grading — roster, essay grading, reopen

**Files:**
- Create: `app/Http/Controllers/Learn/QuizGradingController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/QuizGradingControllerTest.php`

**Interfaces:**
- Produces routes: `learn.quizzes.attempts` (GET `/learn/quizzes/{quiz}/attempts`),
  `learn.quiz-attempt-answers.grade` (PUT `/learn/quiz-attempt-answers/{answer}/grade`),
  `learn.quiz-attempts.reopen` (POST `/learn/quiz-attempts/{attempt}/reopen`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizGradingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Quiz $quiz;
    private $essayQuestion;
    private User $teacher;
    private QuizAttempt $attempt;
    private QuizAttemptAnswer $essayAnswer;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);

        $this->teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);

        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->quiz = Quiz::create(['title' => 'Quiz']);
        $this->quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $mcQuestion = $this->quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q1', 'points' => 5, 'position' => 0]);
        $correct = $mcQuestion->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);
        $this->essayQuestion = $this->quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q2', 'points' => 10, 'position' => 1]);

        $studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student']);

        $this->attempt = QuizAttempt::create([
            'learn_quiz_id' => $this->quiz->id, 'student_id' => $studentId, 'attempt_number' => 1,
            'question_order' => [$mcQuestion->id, $this->essayQuestion->id], 'started_at' => now()->subMinutes(5), 'submitted_at' => now(),
        ]);
        $mcAnswer = QuizAttemptAnswer::create([
            'learn_quiz_attempt_id' => $this->attempt->id, 'learn_quiz_question_id' => $mcQuestion->id,
            'is_correct' => true, 'points_earned' => 5,
        ]);
        $mcAnswer->selectedOptions()->create(['learn_quiz_question_option_id' => $correct->id]);
        $this->essayAnswer = QuizAttemptAnswer::create([
            'learn_quiz_attempt_id' => $this->attempt->id, 'learn_quiz_question_id' => $this->essayQuestion->id,
            'answer_text' => 'My essay response.',
        ]);
    }

    public function test_index_lists_attempts_with_pending_essay_count(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('learn.quizzes.attempts', $this->quiz));

        $response->assertInertia(fn ($page) => $page
            ->where('attempts.0.pending_essays', 1)
            ->where('attempts.0.score', null)
        );
    }

    public function test_grading_the_only_pending_essay_computes_the_attempt_score(): void
    {
        $this->actingAs($this->teacher)->put(route('learn.quiz-attempt-answers.grade', $this->essayAnswer), [
            'points_earned' => 8,
        ])->assertRedirect();

        $this->assertSame('8.00', $this->essayAnswer->fresh()->points_earned);
        $this->assertNotNull($this->essayAnswer->fresh()->graded_at);
        $this->assertSame('13.00', $this->attempt->fresh()->score);
    }

    public function test_grading_rejects_a_score_above_the_question_max(): void
    {
        $this->actingAs($this->teacher)->put(route('learn.quiz-attempt-answers.grade', $this->essayAnswer), [
            'points_earned' => 999,
        ])->assertSessionHasErrors('points_earned');
    }

    public function test_reopen_clears_grading_but_preserves_answer_content(): void
    {
        $this->actingAs($this->teacher)->put(route('learn.quiz-attempt-answers.grade', $this->essayAnswer), ['points_earned' => 8]);

        $this->actingAs($this->teacher)->post(route('learn.quiz-attempts.reopen', $this->attempt))->assertRedirect();

        $this->assertNull($this->attempt->fresh()->submitted_at);
        $this->assertNull($this->attempt->fresh()->score);
        $this->assertNull($this->essayAnswer->fresh()->points_earned);
        $this->assertSame('My essay response.', $this->essayAnswer->fresh()->answer_text);
    }

    public function test_stranger_cannot_view_grade_or_reopen(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('learn.quizzes.attempts', $this->quiz))->assertForbidden();
        $this->actingAs($stranger)->put(route('learn.quiz-attempt-answers.grade', $this->essayAnswer), ['points_earned' => 5])->assertForbidden();
        $this->actingAs($stranger)->post(route('learn.quiz-attempts.reopen', $this->attempt))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizGradingControllerTest.php"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Write `QuizGradingController`**

`app/Http/Controllers/Learn/QuizGradingController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class QuizGradingController extends Controller
{
    /** GET /learn/quizzes/{quiz}/attempts */
    public function index(Quiz $quiz): Response
    {
        $user = Auth::user();
        abort_unless($quiz->canEdit($user), 403);

        $attempts = QuizAttempt::where('learn_quiz_id', $quiz->id)
            ->with('answers.question')
            ->orderBy('student_id')
            ->orderBy('attempt_number')
            ->get();

        $students = DB::table('students')
            ->whereIn('id', $attempts->pluck('student_id')->unique())
            ->get(['id', 'firstname', 'lastname'])
            ->keyBy('id');

        return Inertia::render('Learn/QuizGrading', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'max_score' => $quiz->maxScore(),
            ],
            'attempts' => $attempts->map(function (QuizAttempt $attempt) use ($students) {
                $student = $students->get($attempt->student_id);
                $pendingEssays = $attempt->answers
                    ->filter(fn ($a) => $a->question->question_type === 'essay' && $a->points_earned === null)
                    ->count();

                return [
                    'id' => $attempt->id,
                    'student_name' => $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$attempt->student_id}",
                    'attempt_number' => $attempt->attempt_number,
                    'is_submitted' => $attempt->isSubmitted(),
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'pending_essays' => $pendingEssays,
                ];
            })->values(),
        ]);
    }

    /** PUT /learn/quiz-attempt-answers/{answer}/grade */
    public function gradeEssay(Request $request, QuizAttemptAnswer $answer)
    {
        $user = Auth::user();
        $quiz = $answer->attempt->quiz;
        abort_unless($quiz->canEdit($user), 403);
        abort_unless($answer->question->question_type === 'essay', 422, 'Only essay answers are manually graded.');
        abort_unless($answer->attempt->isSubmitted(), 422, 'This attempt has not been submitted yet.');

        $validated = $request->validate([
            'points_earned' => 'required|numeric|min:0|max:' . $answer->question->points,
        ]);

        $answer->update([
            'points_earned' => $validated['points_earned'],
            'is_correct' => (float) $validated['points_earned'] === (float) $answer->question->points,
            'graded_at' => now(),
            'graded_by' => $user->id,
        ]);

        $this->recomputeAttemptScoreIfComplete($answer->attempt);

        return back()->with('success', 'Essay graded.');
    }

    /** POST /learn/quiz-attempts/{attempt}/reopen */
    public function reopen(QuizAttempt $attempt)
    {
        $user = Auth::user();
        abort_unless($attempt->quiz->canEdit($user), 403);

        $attempt->answers()->update(['is_correct' => null, 'points_earned' => null, 'graded_at' => null, 'graded_by' => null]);
        $attempt->update(['submitted_at' => null, 'auto_submitted' => false, 'score' => null]);

        return back()->with('success', 'Attempt reopened for resubmission.');
    }

    private function recomputeAttemptScoreIfComplete(QuizAttempt $attempt): void
    {
        $answers = $attempt->answers()->get();
        if ($answers->contains(fn ($a) => $a->points_earned === null)) {
            return;
        }

        $attempt->update(['score' => $answers->sum('points_earned')]);
    }
}
```

- [ ] **Step 4: Add the routes**

Add to `routes/web.php`, immediately after the `quiz-questions.destroy` line added in Task 5:

```php
    Route::get('/quizzes/{quiz}/attempts', [\App\Http\Controllers\Learn\QuizGradingController::class, 'index'])->name('quizzes.attempts');
    Route::put('/quiz-attempt-answers/{answer}/grade', [\App\Http\Controllers\Learn\QuizGradingController::class, 'gradeEssay'])->name('quiz-attempt-answers.grade');
    Route::post('/quiz-attempts/{attempt}/reopen', [\App\Http\Controllers\Learn\QuizGradingController::class, 'reopen'])->name('quiz-attempts.reopen');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizGradingControllerTest.php"`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/QuizGradingController.php routes/web.php \
        tests/Feature/Learn/QuizGradingControllerTest.php
git commit -m "feat(learn): add quiz grading roster, essay grading, and attempt reopen"
```

---

### Task 12: Question bank schema + models

**Files:**
- Create: `database/migrations/2026_08_09_100016_create_learn_quiz_question_bank_items_table.php`
- Create: `database/migrations/2026_08_09_100017_create_learn_quiz_question_bank_options_table.php`
- Create: `app/Models/Learn/QuizQuestionBankItem.php`
- Create: `app/Models/Learn/QuizQuestionBankOption.php`
- Test: `tests/Feature/Learn/QuizQuestionBankSchemaTest.php`

**Interfaces:**
- Produces: `QuizQuestionBankItem::options()` — note the option rows double as accepted-answer
  phrases when `question_type` is `short_answer` (see the Global Constraints note at the top of
  this plan); consumed by Task 13/14.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\QuizQuestionBankItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuizQuestionBankSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_have_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_quiz_question_bank_items'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_question_bank_items', [
            'id', 'user_id', 'name', 'question_type', 'prompt', 'points', 'difficulty',
        ]));

        $this->assertTrue(Schema::hasTable('learn_quiz_question_bank_options'));
        $this->assertTrue(Schema::hasColumns('learn_quiz_question_bank_options', [
            'id', 'learn_quiz_question_bank_item_id', 'option_text', 'is_correct', 'position',
        ]));
    }

    public function test_bank_item_has_ordered_options(): void
    {
        $user = User::factory()->create();
        $item = QuizQuestionBankItem::create([
            'user_id' => $user->id, 'name' => 'Photosynthesis MC', 'question_type' => 'multiple_choice',
            'prompt' => 'What do plants produce?', 'points' => 5,
        ]);
        $item->options()->create(['option_text' => 'Oxygen', 'is_correct' => true, 'position' => 1]);
        $item->options()->create(['option_text' => 'Nitrogen', 'is_correct' => false, 'position' => 0]);

        $this->assertSame(['Nitrogen', 'Oxygen'], $item->fresh()->options->pluck('option_text')->all());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizQuestionBankSchemaTest.php"`
Expected: FAIL — tables/models don't exist.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_08_09_100016_create_learn_quiz_question_bank_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->enum('question_type', ['multiple_choice', 'true_false', 'multiple_select', 'short_answer', 'essay']);
            $table->longText('prompt');
            $table->decimal('points', 6, 2);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_question_bank_items');
    }
};
```

`database/migrations/2026_08_09_100017_create_learn_quiz_question_bank_options_table.php`
(explicit short FK name — the auto-generated one would exceed 64 chars):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_question_bank_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learn_quiz_question_bank_item_id');
            $table->foreign('learn_quiz_question_bank_item_id', 'lqqbo_item_fk')
                  ->references('id')->on('learn_quiz_question_bank_items')->cascadeOnDelete();
            $table->string('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_question_bank_options');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/Learn/QuizQuestionBankItem.php`:

```php
<?php

namespace App\Models\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestionBankItem extends Model
{
    protected $table = 'learn_quiz_question_bank_items';

    protected $fillable = ['user_id', 'name', 'question_type', 'prompt', 'points', 'difficulty'];

    protected $casts = ['points' => 'decimal:2'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionBankOption::class, 'learn_quiz_question_bank_item_id')->orderBy('position');
    }
}
```

`app/Models/Learn/QuizQuestionBankOption.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestionBankOption extends Model
{
    protected $table = 'learn_quiz_question_bank_options';

    protected $fillable = ['learn_quiz_question_bank_item_id', 'option_text', 'is_correct', 'position'];

    protected $casts = ['is_correct' => 'boolean', 'position' => 'integer'];

    public function bankItem(): BelongsTo
    {
        return $this->belongsTo(QuizQuestionBankItem::class, 'learn_quiz_question_bank_item_id');
    }
}
```

- [ ] **Step 5: Run migrations and the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_09_100016_create_learn_quiz_question_bank_items_table.php --path=database/migrations/2026_08_09_100017_create_learn_quiz_question_bank_options_table.php --force"`
Then: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizQuestionBankSchemaTest.php"`
Expected: PASS (2 tests). If a migration fails partway, drop the partially-created table via
tinker before retrying (same caveat as Task 1).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_09_100016_create_learn_quiz_question_bank_items_table.php \
        database/migrations/2026_08_09_100017_create_learn_quiz_question_bank_options_table.php \
        app/Models/Learn/QuizQuestionBankItem.php app/Models/Learn/QuizQuestionBankOption.php \
        tests/Feature/Learn/QuizQuestionBankSchemaTest.php
git commit -m "feat(learn): add quiz question bank schema and models"
```

---

### Task 13: Question bank rename/delete controller

**Files:**
- Create: `app/Http/Controllers/Learn/QuizQuestionBankController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/QuizQuestionBankControllerTest.php`

**Interfaces:**
- Produces routes: `learn.quiz-question-bank.update` (PUT `/learn/quiz-question-bank/{item}`),
  `learn.quiz-question-bank.destroy` (DELETE `/learn/quiz-question-bank/{item}`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\QuizQuestionBankItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizQuestionBankControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rename_and_delete_a_bank_item(): void
    {
        $user = User::factory()->create();
        $item = QuizQuestionBankItem::create([
            'user_id' => $user->id, 'name' => 'Original', 'question_type' => 'essay', 'prompt' => 'P', 'points' => 5,
        ]);

        $this->actingAs($user)
            ->put(route('learn.quiz-question-bank.update', $item), ['name' => 'Renamed'])
            ->assertRedirect();
        $this->assertSame('Renamed', $item->fresh()->name);

        $this->actingAs($user)
            ->delete(route('learn.quiz-question-bank.destroy', $item))
            ->assertRedirect();
        $this->assertDatabaseMissing('learn_quiz_question_bank_items', ['id' => $item->id]);
    }

    public function test_non_owner_cannot_rename_or_delete(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $item = QuizQuestionBankItem::create([
            'user_id' => $owner->id, 'name' => 'Original', 'question_type' => 'essay', 'prompt' => 'P', 'points' => 5,
        ]);

        $this->actingAs($stranger)
            ->put(route('learn.quiz-question-bank.update', $item), ['name' => 'Hacked'])
            ->assertForbidden();
        $this->actingAs($stranger)
            ->delete(route('learn.quiz-question-bank.destroy', $item))
            ->assertForbidden();

        $this->assertDatabaseHas('learn_quiz_question_bank_items', ['id' => $item->id, 'name' => 'Original']);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizQuestionBankControllerTest.php"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Write `QuizQuestionBankController`**

`app/Http/Controllers/Learn/QuizQuestionBankController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\QuizQuestionBankItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizQuestionBankController extends Controller
{
    /** PUT /learn/quiz-question-bank/{item} */
    public function update(Request $request, QuizQuestionBankItem $item)
    {
        abort_unless($item->user_id === Auth::id(), 403);

        $validated = $request->validate(['name' => 'required|string|max:255']);
        $item->update($validated);

        return back()->with('success', 'Bank question renamed.');
    }

    /** DELETE /learn/quiz-question-bank/{item} */
    public function destroy(QuizQuestionBankItem $item)
    {
        abort_unless($item->user_id === Auth::id(), 403);

        $item->delete();

        return back()->with('success', 'Bank question deleted.');
    }
}
```

- [ ] **Step 4: Add the routes**

Add to `routes/web.php`, immediately after the `quiz-attempts.reopen` line added in Task 11:

```php
    Route::put('/quiz-question-bank/{item}', [\App\Http\Controllers\Learn\QuizQuestionBankController::class, 'update'])->name('quiz-question-bank.update');
    Route::delete('/quiz-question-bank/{item}', [\App\Http\Controllers\Learn\QuizQuestionBankController::class, 'destroy'])->name('quiz-question-bank.destroy');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizQuestionBankControllerTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/QuizQuestionBankController.php routes/web.php \
        tests/Feature/Learn/QuizQuestionBankControllerTest.php
git commit -m "feat(learn): add quiz question bank rename/delete endpoints"
```

---

### Task 14: Save-to-bank wiring + course payload

**Files:**
- Modify: `app/Services/Learn/QuizQuestionFactory.php`
- Modify: `app/Http/Controllers/Learn/ModuleItemController.php`
- Modify: `app/Http/Controllers/Learn/QuizQuestionController.php`
- Modify: `app/Http/Controllers/Learn/CourseController.php`
- Test: `tests/Feature/Learn/QuizQuestionBankSaveTest.php`
- Test: `tests/Feature/Learn/CourseQuizQuestionBankPayloadTest.php`

**Interfaces:**
- Extends `QuizQuestionFactory::create()` with a 4th optional `?User $user` parameter — when
  passed and `$data['save_to_bank']` is truthy, creates an independent
  `QuizQuestionBankItem` copy (no FK to the live question, same safety pattern as Phase 2c).
  `short_answer` questions store their accepted-answer phrases as bank options with
  `is_correct = true` (see this plan's Global Constraints note).
- Extends `CourseController::show()`'s Inertia payload with a `quiz_question_bank` prop (the
  instructor's own bank items with options).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Learn/QuizQuestionBankSaveTest.php`:

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizQuestionBankItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizQuestionBankSaveTest extends TestCase
{
    use RefreshDatabase;

    private $module;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);

        $this->teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);

        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $this->module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    public function test_storing_a_quiz_saves_a_flagged_question_to_the_bank(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'Quiz',
            'questions' => [
                [
                    'question_type' => 'multiple_choice', 'prompt' => 'Q?', 'points' => 5,
                    'options' => [['option_text' => 'A', 'is_correct' => true], ['option_text' => 'B', 'is_correct' => false]],
                    'save_to_bank' => true, 'bank_name' => 'Reusable MC',
                ],
            ],
        ])->assertRedirect();

        $bankItem = QuizQuestionBankItem::where('name', 'Reusable MC')->firstOrFail();
        $this->assertSame($this->teacher->id, $bankItem->user_id);
        $this->assertCount(2, $bankItem->options);
    }

    public function test_adding_a_question_to_an_existing_quiz_can_also_save_to_bank(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $quiz->moduleItem()->create(['learn_module_id' => $this->module->id, 'position' => 0]);

        $this->actingAs($this->teacher)->post(route('learn.quiz-questions.store', $quiz), [
            'question_type' => 'short_answer', 'prompt' => 'Capital?', 'points' => 5,
            'accepted_answers' => ['Manila'],
            'save_to_bank' => true, 'bank_name' => 'Capital Question',
        ])->assertRedirect();

        $bankItem = QuizQuestionBankItem::where('name', 'Capital Question')->firstOrFail();
        $this->assertSame('short_answer', $bankItem->question_type);
        $this->assertSame('Manila', $bankItem->options->first()->option_text);
        $this->assertTrue($bankItem->options->first()->is_correct);
    }

    public function test_omitting_save_to_bank_creates_no_bank_item(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'Quiz',
            'questions' => [['question_type' => 'essay', 'prompt' => 'Q?', 'points' => 5]],
        ]);

        $this->assertDatabaseCount('learn_quiz_question_bank_items', 0);
    }

    public function test_editing_the_live_question_afterward_never_touches_the_bank_copy(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-quiz', $this->module), [
            'title' => 'Quiz',
            'questions' => [['question_type' => 'essay', 'prompt' => 'Original', 'points' => 5, 'save_to_bank' => true, 'bank_name' => 'B']],
        ]);

        $quiz = Quiz::where('title', 'Quiz')->firstOrFail();
        $question = $quiz->questions->first();
        $bankItem = QuizQuestionBankItem::where('name', 'B')->firstOrFail();

        $question->update(['prompt' => 'Changed']);
        $this->assertSame('Original', $bankItem->fresh()->prompt);

        $bankItem->update(['name' => 'Renamed']);
        $this->assertSame('Original', $question->fresh()->prompt);
    }
}
```

`tests/Feature/Learn/CourseQuizQuestionBankPayloadTest.php`:

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\QuizQuestionBankItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseQuizQuestionBankPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_includes_only_the_instructors_own_bank_items(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);

        $item = QuizQuestionBankItem::create([
            'user_id' => $teacher->id, 'name' => 'Mine', 'question_type' => 'essay', 'prompt' => 'P', 'points' => 5,
        ]);
        $other = User::factory()->create();
        QuizQuestionBankItem::create([
            'user_id' => $other->id, 'name' => 'Not mine', 'question_type' => 'essay', 'prompt' => 'P', 'points' => 5,
        ]);

        $response = $this->actingAs($teacher)->get(route('learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->has('quiz_question_bank', 1)
            ->where('quiz_question_bank.0.name', 'Mine')
        );
    }
}
```

- [ ] **Step 2: Run to verify both fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizQuestionBankSaveTest.php tests/Feature/Learn/CourseQuizQuestionBankPayloadTest.php"`
Expected: FAIL — `save_to_bank`/`bank_name` are silently ignored, no `quiz_question_bank` prop.

- [ ] **Step 3: Update `QuizQuestionFactory`**

Add imports:

```php
use App\Models\Learn\QuizQuestionBankItem;
use App\Models\User;
```

Change the `create()` signature and add the bank-saving call + a new private method:

```php
    public function create(Quiz $quiz, array $data, int $position, ?User $user = null): QuizQuestion
    {
        $question = $quiz->questions()->create([
            'question_type' => $data['question_type'],
            'prompt' => $data['prompt'],
            'points' => $data['points'],
            'position' => $position,
            'difficulty' => $data['difficulty'] ?? null,
        ]);

        if (in_array($data['question_type'], ['multiple_choice', 'true_false', 'multiple_select'], true)) {
            foreach (($data['options'] ?? []) as $optPosition => $option) {
                $question->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => $option['is_correct'] ?? false,
                    'position' => $optPosition,
                ]);
            }
        } elseif ($data['question_type'] === 'short_answer') {
            foreach (($data['accepted_answers'] ?? []) as $answer) {
                $question->acceptedAnswers()->create(['answer_text' => $answer]);
            }
        }

        if ($user && ($data['save_to_bank'] ?? false)) {
            $this->saveToBank($user, $data);
        }

        return $question;
    }

    private function saveToBank(User $user, array $data): void
    {
        $bankItem = QuizQuestionBankItem::create([
            'user_id' => $user->id,
            'name' => $data['bank_name'],
            'question_type' => $data['question_type'],
            'prompt' => $data['prompt'],
            'points' => $data['points'],
            'difficulty' => $data['difficulty'] ?? null,
        ]);

        if (in_array($data['question_type'], ['multiple_choice', 'true_false', 'multiple_select'], true)) {
            foreach (($data['options'] ?? []) as $optPosition => $option) {
                $bankItem->options()->create([
                    'option_text' => $option['option_text'],
                    'is_correct' => $option['is_correct'] ?? false,
                    'position' => $optPosition,
                ]);
            }
        } elseif ($data['question_type'] === 'short_answer') {
            foreach (($data['accepted_answers'] ?? []) as $optPosition => $answer) {
                $bankItem->options()->create([
                    'option_text' => $answer, 'is_correct' => true, 'position' => $optPosition,
                ]);
            }
        }
    }
```

- [ ] **Step 4: Wire `save_to_bank`/`bank_name` into `ModuleItemController::storeQuiz`**

Add two validation rules to the `questions.*` block in `storeQuiz`:

```php
            'questions.*.save_to_bank' => 'nullable|boolean',
            'questions.*.bank_name' => 'required_if:questions.*.save_to_bank,true|nullable|string|max:255',
```

Change the question-creation loop to pass `$user`:

```php
        foreach ($questions as $position => $questionData) {
            $this->questionFactory->create($quiz, $questionData, $position, $user);
        }
```

- [ ] **Step 5: Wire `save_to_bank`/`bank_name` into `QuizQuestionController::store`**

Add two rules to `questionValidationRules()`:

```php
            'save_to_bank' => 'nullable|boolean',
            'bank_name' => 'required_if:save_to_bank,true|nullable|string|max:255',
```

Change the `store()` method's factory call to pass `$user`:

```php
        $this->questionFactory->create($quiz, $validated, $position, $user);
```

- [ ] **Step 6: Update `CourseController`**

Add the import:

```php
use App\Models\Learn\QuizQuestionBankItem;
```

In `show()`, add a second prop to the existing `Inertia::render('Learn/Show', [...])` call
(alongside `rubric_templates`):

```php
            'quiz_question_bank' => QuizQuestionBankItem::where('user_id', $user->id)
                ->with('options')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'question_type' => $item->question_type,
                    'prompt' => $item->prompt,
                    'points' => (float) $item->points,
                    'difficulty' => $item->difficulty,
                    'options' => $item->options->map(fn ($o) => [
                        'option_text' => $o->option_text, 'is_correct' => $o->is_correct,
                    ])->values(),
                ])->values(),
```

- [ ] **Step 7: Run the tests**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizQuestionBankSaveTest.php tests/Feature/Learn/CourseQuizQuestionBankPayloadTest.php"`
Expected: PASS (5 tests).

- [ ] **Step 8: Run Task 4/5's existing quiz-authoring tests to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleItemQuizControllerTest.php tests/Feature/Learn/QuizQuestionControllerTest.php"`
Expected: PASS, same counts as before — the new optional fields and the factory's new 4th
parameter must not break existing quiz/question creation.

- [ ] **Step 9: Commit**

```bash
git add app/Services/Learn/QuizQuestionFactory.php app/Http/Controllers/Learn/ModuleItemController.php \
        app/Http/Controllers/Learn/QuizQuestionController.php app/Http/Controllers/Learn/CourseController.php \
        tests/Feature/Learn/QuizQuestionBankSaveTest.php tests/Feature/Learn/CourseQuizQuestionBankPayloadTest.php
git commit -m "feat(learn): wire save-to-bank into quiz question creation, surface bank on course page"
```

---

### Task 15: Widen Class Record push to Quiz + WAT invariant

**Files:**
- Modify: `app/Services/Learn/ClassRecordPushService.php`
- Modify: `app/Http/Controllers/Learn/QuizGradingController.php`
- Create: `app/Http/Controllers/Learn/QuizClassRecordPushController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/QuizClassRecordPushTest.php`

**Interfaces:**
- Widens `ClassRecordPushService::candidateClassRecords()`/`link()`/`push()` from
  `Assignment`-only to `HasClassRecordLink` — `Assignment`'s own call sites
  (`ClassRecordPushController`) are unaffected since `Assignment` already implements the
  interface (Task 3).
- Produces routes: `learn.quizzes.link` (PUT `/learn/quizzes/{quiz}/link`), `learn.quizzes.push`
  (POST `/learn/quizzes/{quiz}/push`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizClassRecordPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_and_push_work_for_a_quiz_and_never_touch_wat_scheduling_fields(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $quiz = Quiz::create(['title' => 'Quiz']);
        $quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
        $quiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 20, 'position' => 0]);

        $option = GradingOption::create(['name' => 'Standard', 'is_active' => true]);
        $classRecord = ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $option->id, 'school_year_id' => $sy->id,
            'school_year' => $sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $classRecord->id, 'grading_option_id' => $option->id,
            'quarter' => 1, 'is_locked' => false,
        ]);
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'name' => 'Written Work', 'code' => 'WW',
            'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
        $plottedAt = now()->subDays(3);
        $activityDate = now()->subDays(2)->toDateString();
        $assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Quiz 1', 'max_score' => 20, 'sort_order' => 1,
            'plotted_at' => $plottedAt, 'activity_date' => $activityDate,
        ]);

        $studentId = mt_rand(1, 999999999);
        \Illuminate\Support\Facades\DB::table('students')->insert([
            'id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'student_id' => $studentId,
            'lastname' => 'Student', 'firstname' => 'Test', 'sort_order' => 1,
        ]);

        QuizAttempt::create([
            'learn_quiz_id' => $quiz->id, 'student_id' => $studentId, 'attempt_number' => 1,
            'question_order' => [], 'started_at' => now()->subMinutes(5), 'submitted_at' => now(), 'score' => 18,
        ]);

        $this->actingAs($teacher)->put(route('learn.quizzes.link', $quiz), [
            'class_record_assessment_id' => $assessment->id,
        ])->assertRedirect();
        $this->assertSame($assessment->id, $quiz->fresh()->class_record_assessment_id);

        $response = $this->actingAs($teacher)->post(route('learn.quizzes.push', $quiz));
        $response->assertRedirect();

        $this->assertDatabaseHas('class_record_scores', [
            'class_record_student_id' => ClassRecordStudent::where('student_id', $studentId)->first()->id,
            'class_record_assessment_id' => $assessment->id,
            'score' => 18,
        ]);
        $this->assertNotNull($quiz->fresh()->pushed_at);

        // WAT invariant: linking/pushing never touches the assessment's own scheduling fields.
        $assessment->refresh();
        $this->assertSame($plottedAt->toDateTimeString(), $assessment->plotted_at->toDateTimeString());
        $this->assertSame($activityDate, $assessment->activity_date->toDateString());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizClassRecordPushTest.php"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Widen `ClassRecordPushService`**

Replace the file's imports and every `Assignment` type-hint with `HasClassRecordLink`:

```php
<?php

namespace App\Services\Learn;

use App\Contracts\Learn\HasClassRecordLink;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links a Learn gradable item (Assignment or Quiz) to a pre-existing Class Record assessment
 * and pushes graded scores into it. Never creates, dates, or reschedules a
 * ClassRecordAssessment — the instructor does that themselves through Class Record's own
 * existing WAT-enforced flow. This service only ever selects an already-plotted assessment
 * and writes ClassRecordScore rows.
 */
class ClassRecordPushService
{
    /** @return Collection<int, ClassRecord> */
    public function candidateClassRecords(HasClassRecordLink $item): Collection
    {
        $course = $item->course();
        if (! $course) {
            return collect();
        }

        return ClassRecord::active()
            ->where('subject_id', $course->subject_id)
            ->where('section_id', $course->section_id)
            ->where('school_year_id', $course->school_year_id)
            ->with([
                'quarters' => fn ($q) => $q->orderBy('quarter'),
                'quarters.assessments' => fn ($q) => $q->where('is_graded', true)->with('gradingCategory'),
            ])
            ->get();
    }

    public function link(HasClassRecordLink $item, int $assessmentId, User $user): void
    {
        abort_unless($item->canEdit($user), 403);

        $assessment = ClassRecordAssessment::with(['gradingCategory', 'quarter.classRecord'])->findOrFail($assessmentId);

        abort_unless(
            $assessment->gradingCategory->canEditOn($assessment->quarter->classRecord, $user),
            403
        );

        $maxScore = $item->maxScore();
        if ($maxScore === null || (float) $assessment->max_score !== $maxScore) {
            throw ValidationException::withMessages([
                'class_record_assessment_id' => "The assessment's max score ({$assessment->max_score}) must exactly match this item's max score ({$maxScore}) before linking.",
            ]);
        }

        $item->update(['class_record_assessment_id' => $assessment->id]);
    }

    /** @return array{pushed: int, skipped: array<int, string>} */
    public function push(HasClassRecordLink $item, User $user): array
    {
        abort_if(! $item->class_record_assessment_id, 422, 'Link a Class Record assessment first.');

        $assessment = $item->classRecordAssessment()->with(['gradingCategory', 'quarter.classRecord'])->firstOrFail();

        abort_unless($item->canEdit($user), 403);
        abort_unless($assessment->gradingCategory->canEditOn($assessment->quarter->classRecord, $user), 403);

        $scores = $item->gradedStudentScores();

        $pushed = 0;
        $skipped = [];

        foreach ($scores as $studentId => $score) {
            $classRecordStudent = ClassRecordStudent::where('class_record_quarter_id', $assessment->class_record_quarter_id)
                ->where('student_id', $studentId)
                ->first();

            if (! $classRecordStudent) {
                $student = DB::table('students')->where('id', $studentId)->first(['lastname', 'firstname']);
                $skipped[] = $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$studentId}";
                continue;
            }

            ClassRecordScore::updateOrCreate(
                ['class_record_student_id' => $classRecordStudent->id, 'class_record_assessment_id' => $assessment->id],
                ['score' => $score]
            );
            $pushed++;
        }

        $item->update(['pushed_at' => now()]);

        return ['pushed' => $pushed, 'skipped' => $skipped];
    }
}
```

- [ ] **Step 4: Write `QuizClassRecordPushController`**

`app/Http/Controllers/Learn/QuizClassRecordPushController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Quiz;
use App\Services\Learn\ClassRecordPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizClassRecordPushController extends Controller
{
    public function __construct(private ClassRecordPushService $pushService)
    {
    }

    /** PUT /learn/quizzes/{quiz}/link */
    public function link(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
            'class_record_assessment_id' => 'required|integer|exists:class_record_assessments,id',
        ]);

        $this->pushService->link($quiz, $validated['class_record_assessment_id'], Auth::user());

        return back()->with('success', 'Linked to Class Record assessment.');
    }

    /** POST /learn/quizzes/{quiz}/push */
    public function push(Quiz $quiz)
    {
        $result = $this->pushService->push($quiz, Auth::user());

        $message = "Pushed {$result['pushed']} score(s) to Class Record.";
        if (! empty($result['skipped'])) {
            $message .= ' Skipped (not on quarter roster): ' . implode(', ', $result['skipped']) . '.';
        }

        return back()->with('success', $message);
    }
}
```

- [ ] **Step 5: Add Class Record data to `QuizGradingController::index`**

Add the constructor:

```php
    public function __construct(private ClassRecordPushService $pushService)
    {
    }
```

Add the import:

```php
use App\Services\Learn\ClassRecordPushService;
```

In `index()`, eager load the link before building the response:

```php
        $quiz->load(['classRecordAssessment.gradingCategory', 'classRecordAssessment.quarter.classRecord']);
        $classRecordOptions = $this->pushService->candidateClassRecords($quiz);
```

Extend the `'quiz'` key in the returned Inertia payload:

```php
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'max_score' => $quiz->maxScore(),
                'class_record_link' => $quiz->classRecordAssessment ? [
                    'assessment_id' => $quiz->classRecordAssessment->id,
                    'assessment_title' => $quiz->classRecordAssessment->title,
                    'class_record_name' => $quiz->classRecordAssessment->quarter->classRecord->display_name,
                    'quarter' => $quiz->classRecordAssessment->quarter->quarter,
                    'category_name' => $quiz->classRecordAssessment->gradingCategory->name,
                    'max_score' => (float) $quiz->classRecordAssessment->max_score,
                    'pushed_at' => $quiz->pushed_at?->toIso8601String(),
                ] : null,
                'class_record_options' => $classRecordOptions->map(fn ($cr) => [
                    'id' => $cr->id,
                    'display_name' => $cr->display_name,
                    'quarters' => $cr->quarters->map(fn ($q) => [
                        'id' => $q->id,
                        'quarter' => $q->quarter,
                        'assessments' => $q->assessments->map(fn ($a) => [
                            'id' => $a->id, 'title' => $a->title, 'max_score' => (float) $a->max_score,
                            'category_name' => $a->gradingCategory->name,
                        ])->values(),
                    ])->values(),
                ])->values(),
            ],
```

- [ ] **Step 6: Add the routes**

Add to `routes/web.php`, immediately after the `quiz-question-bank.destroy` line added in Task 13:

```php
    Route::put('/quizzes/{quiz}/link', [\App\Http\Controllers\Learn\QuizClassRecordPushController::class, 'link'])->name('quizzes.link');
    Route::post('/quizzes/{quiz}/push', [\App\Http\Controllers\Learn\QuizClassRecordPushController::class, 'push'])->name('quizzes.push');
```

- [ ] **Step 7: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizClassRecordPushTest.php"`
Expected: PASS (1 test).

- [ ] **Step 8: Run Phase 2b's existing Assignment push/link tests to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ClassRecordPushServiceTest.php tests/Feature/Learn/ClassRecordPushControllerTest.php tests/Feature/Learn/AssignmentGradingClassRecordDataTest.php tests/Feature/Learn/QuizGradingControllerTest.php"`
Expected: PASS, same counts as before — widening the service to `HasClassRecordLink` and adding
a constructor dependency to `QuizGradingController` must not change `Assignment`'s existing
push/link behavior or break Task 11's grading tests.

- [ ] **Step 9: Commit**

```bash
git add app/Services/Learn/ClassRecordPushService.php app/Http/Controllers/Learn/QuizGradingController.php \
        app/Http/Controllers/Learn/QuizClassRecordPushController.php routes/web.php \
        tests/Feature/Learn/QuizClassRecordPushTest.php
git commit -m "feat(learn): widen Class Record push to quizzes via HasClassRecordLink"
```

---

### Task 16: KaTeX dependency + shared math-rendering component

**Files:**
- Modify: `package.json` (via `npm install`)
- Create: `resources/js/Components/MathContent.vue`

**Interfaces:**
- Produces: `<MathContent :html="sanitizeHtml(text)" />` — a drop-in replacement for a raw
  `<div v-html="sanitizeHtml(text)" />` wherever a quiz prompt is displayed. Consumed by Tasks
  18–21's Vue pages (authoring preview, student attempt view, grading view, item analysis).
  Always sanitize with the existing `sanitizeHtml()`/DOMPurify helper (Phase 1) *before* passing
  to `MathContent` — this component renders math inside already-trusted HTML, it does not sanitize.

This task has no backend test — it's a frontend-only dependency addition verified by a successful
build, consistent with how DOMPurify was added in Phase 1.

- [ ] **Step 1: Install KaTeX**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm install katex`
Expected: `package.json`/`package-lock.json` gain a `katex` dependency, install succeeds with no
errors.

- [ ] **Step 2: Write `MathContent.vue`**

`resources/js/Components/MathContent.vue`:

```vue
<script setup>
import { ref, watch, onMounted } from 'vue'
import renderMathInElement from 'katex/contrib/auto-render'
import 'katex/dist/katex.min.css'

const props = defineProps({ html: { type: String, default: '' } })
const el = ref(null)

function render() {
  if (! el.value) return
  renderMathInElement(el.value, {
    delimiters: [
      { left: '$$', right: '$$', display: true },
      { left: '$', right: '$', display: false },
    ],
    throwOnError: false,
  })
}

onMounted(render)
watch(() => props.html, () => render())
</script>

<template>
  <div ref="el" v-html="html" />
</template>
```

- [ ] **Step 3: Build and verify no errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds, no errors referencing `MathContent.vue` or the `katex` import.

- [ ] **Step 4: Commit**

```bash
git add package.json package-lock.json resources/js/Components/MathContent.vue
git commit -m "feat(learn): add KaTeX dependency and shared MathContent component"
```

---

### Task 17: Item analysis + course trend analytics

**Files:**
- Create: `app/Services/Learn/QuizAnalyticsService.php`
- Create: `app/Http/Controllers/Learn/QuizAnalyticsController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/QuizAnalyticsServiceTest.php`

**Interfaces:**
- Produces: `QuizAnalyticsService::itemAnalysis(Quiz): array{questions, distribution}`,
  `QuizAnalyticsService::courseTrend(Course): array{quizzes, by_difficulty}` — both computed
  live, no stored aggregates.
- Produces routes: `learn.quizzes.analytics` (GET `/learn/quizzes/{quiz}/analytics`),
  `learn.course-trend` (GET `/learn/{course}/quiz-trend`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Models\User;
use App\Services\Learn\QuizAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuizAnalyticsService $service;
    private Course $course;
    private $module;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QuizAnalyticsService::class);

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $this->module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    public function test_item_analysis_computes_per_question_percentage_and_score_distribution(): void
    {
        $quiz = Quiz::create(['title' => 'Quiz']);
        $quiz->moduleItem()->create(['learn_module_id' => $this->module->id, 'position' => 0]);
        $question = $quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q', 'points' => 10, 'position' => 0, 'difficulty' => 'medium']);

        foreach ([10, 5, 0] as $score) {
            $attempt = QuizAttempt::create([
                'learn_quiz_id' => $quiz->id, 'student_id' => mt_rand(1, 999999999), 'attempt_number' => 1,
                'question_order' => [$question->id], 'started_at' => now(), 'submitted_at' => now(), 'score' => $score,
            ]);
            QuizAttemptAnswer::create([
                'learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $question->id,
                'points_earned' => $score, 'is_correct' => $score === 10,
            ]);
        }

        $analysis = $this->service->itemAnalysis($quiz);

        $this->assertSame(50.0, $analysis['questions'][0]['avg_score_percentage']);
        $this->assertSame('medium', $analysis['questions'][0]['difficulty']);
        $this->assertSame(0.0, $analysis['distribution']['min']);
        $this->assertSame(10.0, $analysis['distribution']['max']);
        $this->assertSame(5.0, $analysis['distribution']['avg']);
        $this->assertSame(5.0, $analysis['distribution']['median']);
    }

    public function test_course_trend_orders_quizzes_by_due_date_and_computes_average_percentage(): void
    {
        $laterQuiz = Quiz::create(['title' => 'Later Quiz', 'due_at' => now()->addWeek()]);
        $laterQuiz->moduleItem()->create(['learn_module_id' => $this->module->id, 'position' => 0]);
        $laterQuestion = $laterQuiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);
        $laterAttempt = QuizAttempt::create([
            'learn_quiz_id' => $laterQuiz->id, 'student_id' => mt_rand(1, 999999999), 'attempt_number' => 1,
            'question_order' => [$laterQuestion->id], 'started_at' => now(), 'submitted_at' => now(), 'score' => 10,
        ]);

        $earlierQuiz = Quiz::create(['title' => 'Earlier Quiz', 'due_at' => now()->subWeek()]);
        $earlierQuiz->moduleItem()->create(['learn_module_id' => $this->module->id, 'position' => 1]);
        $earlierQuestion = $earlierQuiz->questions()->create(['question_type' => 'essay', 'prompt' => 'Q', 'points' => 10, 'position' => 0]);
        $earlierAttempt = QuizAttempt::create([
            'learn_quiz_id' => $earlierQuiz->id, 'student_id' => mt_rand(1, 999999999), 'attempt_number' => 1,
            'question_order' => [$earlierQuestion->id], 'started_at' => now(), 'submitted_at' => now(), 'score' => 5,
        ]);

        $this->course->load('modules.items.itemable');
        $trend = $this->service->courseTrend($this->course);

        $this->assertSame('Earlier Quiz', $trend['quizzes'][0]['title']);
        $this->assertSame(50.0, $trend['quizzes'][0]['avg_score_percentage']);
        $this->assertSame('Later Quiz', $trend['quizzes'][1]['title']);
        $this->assertSame(100.0, $trend['quizzes'][1]['avg_score_percentage']);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizAnalyticsServiceTest.php"`
Expected: FAIL — `QuizAnalyticsService` doesn't exist.

- [ ] **Step 3: Write `QuizAnalyticsService`**

`app/Services/Learn/QuizAnalyticsService.php`:

```php
<?php

namespace App\Services\Learn;

use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Models\Learn\QuizQuestion;
use Illuminate\Support\Collection;

class QuizAnalyticsService
{
    /** @return array{questions: array, distribution: array} */
    public function itemAnalysis(Quiz $quiz): array
    {
        $submittedAttemptIds = $quiz->attempts()->whereNotNull('submitted_at')->pluck('id');

        $questionStats = $quiz->questions()->get()->map(function (QuizQuestion $question) use ($submittedAttemptIds) {
            $answers = QuizAttemptAnswer::whereIn('learn_quiz_attempt_id', $submittedAttemptIds)
                ->where('learn_quiz_question_id', $question->id)
                ->whereNotNull('points_earned')
                ->get();

            $avgPercentage = $answers->isNotEmpty() && (float) $question->points > 0
                ? round($answers->avg(fn ($a) => ((float) $a->points_earned / (float) $question->points) * 100), 1)
                : null;

            return [
                'id' => $question->id,
                'prompt' => $question->prompt,
                'difficulty' => $question->difficulty,
                'avg_score_percentage' => $avgPercentage,
                'graded_attempts' => $answers->count(),
            ];
        })->values()->all();

        $scores = QuizAttempt::whereIn('id', $submittedAttemptIds)
            ->whereNotNull('score')
            ->pluck('score')
            ->map(fn ($s) => (float) $s);

        return [
            'questions' => $questionStats,
            'distribution' => [
                'min' => $scores->isNotEmpty() ? $scores->min() : null,
                'max' => $scores->isNotEmpty() ? $scores->max() : null,
                'avg' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
                'median' => $scores->isNotEmpty() ? $this->median($scores) : null,
            ],
        ];
    }

    /** @return array{quizzes: array, by_difficulty: array} */
    public function courseTrend(Course $course): array
    {
        $quizzes = collect();
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Quiz) {
                    $quizzes->push($item->itemable);
                }
            }
        }
        $quizzes = $quizzes->sortBy(fn (Quiz $q) => $q->due_at ?? $q->created_at)->values();

        $quizTrend = $quizzes->map(function (Quiz $quiz) {
            $submittedScoredIds = $quiz->attempts()->whereNotNull('submitted_at')->whereNotNull('score')->pluck('id');
            $scores = QuizAttempt::whereIn('id', $submittedScoredIds)->pluck('score')->map(fn ($s) => (float) $s);
            $maxScore = $quiz->maxScore();

            $avgPercentage = $scores->isNotEmpty() && $maxScore
                ? round(($scores->avg() / $maxScore) * 100, 1)
                : null;

            return [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'due_at' => $quiz->due_at?->toIso8601String(),
                'avg_score_percentage' => $avgPercentage,
            ];
        })->values()->all();

        $byDifficulty = collect(['easy', 'medium', 'hard'])->mapWithKeys(function (string $difficulty) use ($quizzes) {
            $questionIds = QuizQuestion::whereIn('learn_quiz_id', $quizzes->pluck('id'))
                ->where('difficulty', $difficulty)
                ->pluck('id');

            $answers = QuizAttemptAnswer::whereIn('learn_quiz_question_id', $questionIds)
                ->whereNotNull('points_earned')
                ->with('question')
                ->get();

            $percentages = $answers->map(function ($a) {
                $points = (float) $a->question->points;

                return $points > 0 ? ((float) $a->points_earned / $points) * 100 : null;
            })->filter(fn ($p) => $p !== null);

            return [$difficulty => $percentages->isNotEmpty() ? round($percentages->avg(), 1) : null];
        })->all();

        return ['quizzes' => $quizTrend, 'by_difficulty' => $byDifficulty];
    }

    private function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return round(($sorted[$middle - 1] + $sorted[$middle]) / 2, 2);
        }

        return round($sorted[$middle], 2);
    }
}
```

- [ ] **Step 4: Write `QuizAnalyticsController`**

`app/Http/Controllers/Learn/QuizAnalyticsController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Services\Learn\QuizAnalyticsService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class QuizAnalyticsController extends Controller
{
    public function __construct(private QuizAnalyticsService $analyticsService)
    {
    }

    /** GET /learn/quizzes/{quiz}/analytics */
    public function show(Quiz $quiz): Response
    {
        $user = Auth::user();
        abort_unless($quiz->canEdit($user), 403);

        return Inertia::render('Learn/QuizAnalytics', [
            'quiz' => ['id' => $quiz->id, 'title' => $quiz->title],
            'analysis' => $this->analyticsService->itemAnalysis($quiz),
        ]);
    }

    /** GET /learn/{course}/quiz-trend */
    public function courseTrend(Course $course): Response
    {
        $user = Auth::user();
        abort_unless($course->canView($user), 403);

        $course->load(['subject', 'modules.items.itemable']);

        return Inertia::render('Learn/QuizCourseTrend', [
            'course' => ['id' => $course->id, 'subject_name' => $course->subject->name],
            'trend' => $this->analyticsService->courseTrend($course),
        ]);
    }
}
```

- [ ] **Step 5: Add the routes**

Add to `routes/web.php`, immediately after the `quizzes.push` line added in Task 15:

```php
    Route::get('/quizzes/{quiz}/analytics', [\App\Http\Controllers\Learn\QuizAnalyticsController::class, 'show'])->name('quizzes.analytics');
    Route::get('/{course}/quiz-trend', [\App\Http\Controllers\Learn\QuizAnalyticsController::class, 'courseTrend'])->name('course-trend');
```

- [ ] **Step 6: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizAnalyticsServiceTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Services/Learn/QuizAnalyticsService.php app/Http/Controllers/Learn/QuizAnalyticsController.php \
        routes/web.php tests/Feature/Learn/QuizAnalyticsServiceTest.php
git commit -m "feat(learn): add quiz item analysis and course trend analytics"
```

---

### Task 18: Show.vue — quiz authoring UI

**Files:**
- Modify: `resources/js/Pages/Learn/Show.vue`

**Interfaces:**
- Consumes `quiz_question_bank` prop (Task 14), `learn.items.store-quiz` (Task 4).
- No backend test — frontend-only, verified by build (same pattern as Task 16).

- [ ] **Step 1: Add the `quiz_question_bank` prop, `AcademicCapIcon`, and `MathContent` import**

Change the props declaration:

```js
const props = defineProps({ course: Object, rubric_templates: Array, quiz_question_bank: Array })
```

Add `AcademicCapIcon` to the existing `@heroicons/vue/24/outline` import line, and add:

```js
import MathContent from '@/Components/MathContent.vue'
```

- [ ] **Step 2: Add quiz-authoring state and functions**

Add these after the existing `deleteAnnouncement` function (or anywhere among the other
module-item functions):

```js
const quizForms = ref({})
function quizForm(moduleId) {
  if (! quizForms.value[moduleId]) {
    quizForms.value[moduleId] = useForm({
      title: '', instructions: '', time_limit_minutes: '', max_attempts: '',
      questions_to_draw: '', shuffle_questions: false, shuffle_options: false, due_at: '',
      questions: [],
    })
  }
  return quizForms.value[moduleId]
}
function addQuizQuestion(moduleId) {
  quizForm(moduleId).questions.push({
    question_type: 'multiple_choice', prompt: '', points: 5, difficulty: '',
    options: [{ option_text: '', is_correct: true }, { option_text: '', is_correct: false }],
    accepted_answers: [],
    save_to_bank: false, bank_name: '',
  })
}
function removeQuizQuestion(moduleId, index) {
  quizForm(moduleId).questions.splice(index, 1)
}
function addQuizQuestionOption(moduleId, qIndex) {
  quizForm(moduleId).questions[qIndex].options.push({ option_text: '', is_correct: false })
}
function removeQuizQuestionOption(moduleId, qIndex, oIndex) {
  quizForm(moduleId).questions[qIndex].options.splice(oIndex, 1)
}
function addAcceptedAnswer(moduleId, qIndex) {
  quizForm(moduleId).questions[qIndex].accepted_answers.push('')
}
function removeAcceptedAnswer(moduleId, qIndex, aIndex) {
  quizForm(moduleId).questions[qIndex].accepted_answers.splice(aIndex, 1)
}
function applyQuizBankItem(moduleId, qIndex, bankItemId) {
  const item = props.quiz_question_bank.find(b => b.id === Number(bankItemId))
  if (! item) return
  const q = quizForm(moduleId).questions[qIndex]
  q.question_type = item.question_type
  q.prompt = item.prompt
  q.points = item.points
  q.difficulty = item.difficulty || ''
  if (['multiple_choice', 'true_false', 'multiple_select'].includes(item.question_type)) {
    q.options = item.options.map(o => ({ option_text: o.option_text, is_correct: o.is_correct }))
    q.accepted_answers = []
  } else if (item.question_type === 'short_answer') {
    q.accepted_answers = item.options.map(o => o.option_text)
    q.options = []
  } else {
    q.options = []
    q.accepted_answers = []
  }
}
function addQuiz(moduleId) {
  quizForm(moduleId).post(route('learn.items.store-quiz', moduleId), {
    preserveScroll: true,
    onSuccess: () => { quizForms.value[moduleId] = null },
  })
}

// Adding/deleting questions on an ALREADY-CREATED quiz — separate form state from quizForm
// above (which only ever builds a brand-new quiz's initial question set in one POST).
const newQuestionForms = ref({})
function newQuestionForm(quizId) {
  if (! newQuestionForms.value[quizId]) {
    newQuestionForms.value[quizId] = useForm({
      question_type: 'multiple_choice', prompt: '', points: 5, difficulty: '',
      options: [{ option_text: '', is_correct: true }, { option_text: '', is_correct: false }],
      accepted_answers: [],
      save_to_bank: false, bank_name: '',
    })
  }
  return newQuestionForms.value[quizId]
}
function addNewQuestionOption(quizId) {
  newQuestionForm(quizId).options.push({ option_text: '', is_correct: false })
}
function removeNewQuestionOption(quizId, index) {
  newQuestionForm(quizId).options.splice(index, 1)
}
function addNewAcceptedAnswer(quizId) {
  newQuestionForm(quizId).accepted_answers.push('')
}
function removeNewAcceptedAnswer(quizId, index) {
  newQuestionForm(quizId).accepted_answers.splice(index, 1)
}
function submitNewQuestion(quizId) {
  newQuestionForm(quizId).post(route('learn.quiz-questions.store', quizId), {
    preserveScroll: true,
    onSuccess: () => { newQuestionForms.value[quizId] = null },
  })
}
function deleteQuizQuestion(questionId) {
  router.delete(route('learn.quiz-questions.destroy', questionId), { preserveScroll: true })
}

const renameBankItemDrafts = ref({})
function startRenameBankItem(item) {
  renameBankItemDrafts.value[item.id] = item.name
}
function saveBankItemRename(item) {
  router.put(route('learn.quiz-question-bank.update', item.id), {
    name: renameBankItemDrafts.value[item.id],
  }, {
    preserveScroll: true,
    onSuccess: () => { delete renameBankItemDrafts.value[item.id] },
  })
}
function deleteBankItem(item) {
  router.delete(route('learn.quiz-question-bank.destroy', item.id), { preserveScroll: true })
}
```

- [ ] **Step 3: Add quiz item display to the item list**

In the item-list loop, immediately after the existing
`<a v-if="item.type === 'file'" ...>Download file</a>` line, add:

```html
                <div v-if="item.type === 'quiz'" class="mt-1 space-y-1">
                  <p class="text-xs text-slate-500">
                    {{ item.quiz.question_count }} question{{ item.quiz.question_count === 1 ? '' : 's' }}
                    <span v-if="item.quiz.time_limit_minutes"> — {{ item.quiz.time_limit_minutes }} min</span>
                    <span v-if="item.quiz.due_at"> — due {{ new Date(item.quiz.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                    <span v-if="item.quiz.max_score !== null"> — {{ item.quiz.max_score }} pts</span>
                  </p>
                  <div class="flex gap-2">
                    <Link :href="route('learn.quizzes.attempts', item.quiz.id)" class="text-xs text-indigo-600 underline">View attempts</Link>
                    <Link :href="route('learn.quizzes.analytics', item.quiz.id)" class="text-xs text-indigo-600 underline">Analytics</Link>
                  </div>

                  <div v-if="course.can_edit" class="border-t border-slate-100 pt-2 space-y-2">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Questions</p>
                    <p v-if="item.quiz.is_locked" class="text-xs text-amber-600">Locked — students have submitted attempts. Existing questions cannot be changed, but new ones can still be added.</p>

                    <div v-for="q in item.quiz.questions" :key="q.id" class="flex items-start gap-2 border border-slate-100 rounded-lg p-2">
                      <div class="min-w-0 flex-1">
                        <MathContent :html="sanitizeHtml(q.prompt)" class="prose prose-sm max-w-none" />
                        <p class="text-xs text-slate-400">{{ q.question_type }} — {{ q.points }} pts</p>
                      </div>
                      <button v-if="!item.quiz.is_locked" @click="deleteQuizQuestion(q.id)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
                    </div>

                    <div class="border border-slate-100 rounded-lg p-3 space-y-2">
                      <p class="text-xs text-slate-500">Add another question</p>
                      <div class="flex gap-2">
                        <select v-model="newQuestionForm(item.quiz.id).question_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                          <option value="multiple_choice">Multiple choice</option>
                          <option value="true_false">True / False</option>
                          <option value="multiple_select">Multiple select</option>
                          <option value="short_answer">Short answer</option>
                          <option value="essay">Essay</option>
                        </select>
                        <input v-model="newQuestionForm(item.quiz.id).points" type="number" min="0" placeholder="Points" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-24" />
                      </div>
                      <textarea v-model="newQuestionForm(item.quiz.id).prompt" placeholder="Question prompt (supports $LaTeX$)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />

                      <div v-if="['multiple_choice', 'true_false', 'multiple_select'].includes(newQuestionForm(item.quiz.id).question_type)" class="space-y-1">
                        <div v-for="(o, oIndex) in newQuestionForm(item.quiz.id).options" :key="oIndex" class="flex gap-2 items-center">
                          <input v-model="o.option_text" placeholder="Option text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                          <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="o.is_correct" /> Correct</label>
                          <button @click="removeNewQuestionOption(item.quiz.id, oIndex)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
                        </div>
                        <button @click="addNewQuestionOption(item.quiz.id)" class="text-xs text-indigo-600 underline">+ Add option</button>
                      </div>
                      <div v-else-if="newQuestionForm(item.quiz.id).question_type === 'short_answer'" class="space-y-1">
                        <div v-for="(a, aIndex) in newQuestionForm(item.quiz.id).accepted_answers" :key="aIndex" class="flex gap-2 items-center">
                          <input v-model="newQuestionForm(item.quiz.id).accepted_answers[aIndex]" placeholder="Accepted answer" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                          <button @click="removeNewAcceptedAnswer(item.quiz.id, aIndex)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
                        </div>
                        <button @click="addNewAcceptedAnswer(item.quiz.id)" class="text-xs text-indigo-600 underline">+ Add accepted answer</button>
                      </div>

                      <button @click="submitNewQuestion(item.quiz.id)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium">Add question</button>
                    </div>
                  </div>
                </div>
```

Also update the icon line — change:

```html
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
```

to:

```html
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <AcademicCapIcon v-else-if="item.type === 'quiz'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
```

In the course header (near the existing "Publish course"/"Unpublish" button), add a link to the
course's quiz trend view — change:

```html
        <button
          v-if="course.can_edit"
          @click="toggleCourseStatus"
          class="rounded-lg px-4 py-2 text-sm font-medium"
          :class="course.status === 'published' ? 'bg-slate-100 text-slate-700' : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
        >
          {{ course.status === 'published' ? 'Unpublish' : 'Publish course' }}
        </button>
```

to:

```html
        <div v-if="course.can_edit" class="flex items-center gap-3">
          <Link :href="route('learn.course-trend', course.id)" class="text-xs text-indigo-600 underline">Quiz trend</Link>
          <button
            @click="toggleCourseStatus"
            class="rounded-lg px-4 py-2 text-sm font-medium"
            :class="course.status === 'published' ? 'bg-slate-100 text-slate-700' : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
          >
            {{ course.status === 'published' ? 'Unpublish' : 'Publish course' }}
          </button>
        </div>
```

- [ ] **Step 4: Add the quiz authoring form**

Immediately after the existing "New assignment" `</div>` block closes (right before the module's
outer `</div>` that follows it), add:

```html
              <div v-if="course.can_edit" class="border-t border-slate-100 pt-3 space-y-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">New quiz</p>
                <input v-model="quizForm(module.id).title" placeholder="Quiz title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                <textarea v-model="quizForm(module.id).instructions" placeholder="Instructions (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
                <div class="grid grid-cols-2 gap-2">
                  <input v-model="quizForm(module.id).time_limit_minutes" type="number" min="1" placeholder="Time limit (minutes, optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                  <input v-model="quizForm(module.id).max_attempts" type="number" min="1" placeholder="Max attempts (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                  <input v-model="quizForm(module.id).questions_to_draw" type="number" min="1" placeholder="Draw N random questions (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                  <input v-model="quizForm(module.id).due_at" type="datetime-local" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>
                <div class="flex gap-4">
                  <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="quizForm(module.id).shuffle_questions" /> Shuffle questions</label>
                  <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="quizForm(module.id).shuffle_options" /> Shuffle options</label>
                </div>

                <div v-for="(q, qIndex) in quizForm(module.id).questions" :key="qIndex" class="border border-slate-100 rounded-lg p-3 space-y-2">
                  <div class="flex gap-2">
                    <select v-model="q.question_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                      <option value="multiple_choice">Multiple choice</option>
                      <option value="true_false">True / False</option>
                      <option value="multiple_select">Multiple select</option>
                      <option value="short_answer">Short answer</option>
                      <option value="essay">Essay</option>
                    </select>
                    <input v-model="q.points" type="number" min="0" placeholder="Points" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-24" />
                    <select v-model="q.difficulty" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                      <option value="">Difficulty (optional)</option>
                      <option value="easy">Easy</option>
                      <option value="medium">Medium</option>
                      <option value="hard">Hard</option>
                    </select>
                    <button @click="removeQuizQuestion(module.id, qIndex)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                  </div>
                  <textarea v-model="q.prompt" placeholder="Question prompt (supports $LaTeX$)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
                  <MathContent v-if="q.prompt" :html="sanitizeHtml(q.prompt)" class="prose prose-sm max-w-none border-l-2 border-slate-200 pl-2" />

                  <div v-if="quiz_question_bank.length" class="flex gap-2 items-center">
                    <select @change="e => applyQuizBankItem(module.id, qIndex, e.target.value)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
                      <option value="" disabled selected>Start from a saved question</option>
                      <option v-for="b in quiz_question_bank" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                  </div>

                  <div v-if="['multiple_choice', 'true_false', 'multiple_select'].includes(q.question_type)" class="space-y-1">
                    <div v-for="(o, oIndex) in q.options" :key="oIndex" class="flex gap-2 items-center">
                      <input v-model="o.option_text" placeholder="Option text" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                      <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="o.is_correct" /> Correct</label>
                      <button @click="removeQuizQuestionOption(module.id, qIndex, oIndex)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
                    </div>
                    <button @click="addQuizQuestionOption(module.id, qIndex)" class="text-xs text-indigo-600 underline">+ Add option</button>
                  </div>

                  <div v-else-if="q.question_type === 'short_answer'" class="space-y-1">
                    <div v-for="(a, aIndex) in q.accepted_answers" :key="aIndex" class="flex gap-2 items-center">
                      <input v-model="q.accepted_answers[aIndex]" placeholder="Accepted answer" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                      <button @click="removeAcceptedAnswer(module.id, qIndex, aIndex)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
                    </div>
                    <button @click="addAcceptedAnswer(module.id, qIndex)" class="text-xs text-indigo-600 underline">+ Add accepted answer</button>
                  </div>

                  <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="q.save_to_bank" :id="`save-qbank-${module.id}-${qIndex}`" />
                    <label :for="`save-qbank-${module.id}-${qIndex}`" class="text-xs text-slate-600">Save this question to my bank</label>
                  </div>
                  <input v-if="q.save_to_bank" v-model="q.bank_name" placeholder="Bank name" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                </div>
                <button @click="addQuizQuestion(module.id)" class="text-xs text-indigo-600 underline">+ Add question</button>

                <div v-if="quiz_question_bank.length" class="border-t border-slate-100 pt-2 space-y-1">
                  <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">My question bank</p>
                  <div v-for="b in quiz_question_bank" :key="b.id" class="flex items-center gap-2">
                    <input v-if="renameBankItemDrafts[b.id] !== undefined" v-model="renameBankItemDrafts[b.id]" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs flex-1" />
                    <span v-else class="text-xs text-slate-600 flex-1">{{ b.name }}</span>
                    <button v-if="renameBankItemDrafts[b.id] !== undefined" @click="saveBankItemRename(b)" class="text-xs text-indigo-600 underline">Save</button>
                    <button v-else @click="startRenameBankItem(b)" class="text-xs text-slate-500 underline">Rename</button>
                    <button @click="deleteBankItem(b)" class="text-xs text-red-500 underline">Delete</button>
                  </div>
                </div>

                <button @click="addQuiz(module.id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Add quiz</button>
              </div>
```

- [ ] **Step 5: Build and verify no errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `Learn/Show.vue`.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Learn/Show.vue
git commit -m "feat(learn): add quiz authoring UI to Show.vue"
```

---

### Task 19: Grading roster detail + QuizGrading.vue

**Files:**
- Modify: `app/Http/Controllers/Learn/QuizGradingController.php`
- Create: `resources/js/Pages/Learn/QuizGrading.vue`
- Test: `tests/Feature/Learn/QuizGradingRosterAnswersTest.php`

**Interfaces:**
- Extends `QuizGradingController::index()`'s `attempts` entries with a per-answer `answers` array
  (prompt, options with `is_correct`, the student's selection/text, grading state) — the roster
  page (`AssignmentGradingController::index` precedent) returns everything needed for
  expand-to-grade in one payload, no extra round-trip per attempt.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\QuizAttemptAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuizGradingRosterAnswersTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_includes_full_answer_detail_per_attempt(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $quiz = Quiz::create(['title' => 'Quiz']);
        $quiz->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
        $question = $quiz->questions()->create(['question_type' => 'multiple_choice', 'prompt' => 'Q?', 'points' => 5, 'position' => 0]);
        $correct = $question->options()->create(['option_text' => 'A', 'is_correct' => true, 'position' => 0]);

        $studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student']);
        $attempt = QuizAttempt::create([
            'learn_quiz_id' => $quiz->id, 'student_id' => $studentId, 'attempt_number' => 1,
            'question_order' => [$question->id], 'started_at' => now(), 'submitted_at' => now(), 'score' => 5,
        ]);
        $answer = QuizAttemptAnswer::create([
            'learn_quiz_attempt_id' => $attempt->id, 'learn_quiz_question_id' => $question->id,
            'is_correct' => true, 'points_earned' => 5,
        ]);
        $answer->selectedOptions()->create(['learn_quiz_question_option_id' => $correct->id]);

        $response = $this->actingAs($teacher)->get(route('learn.quizzes.attempts', $quiz));

        $response->assertInertia(fn ($page) => $page
            ->where('attempts.0.answers.0.prompt', 'Q?')
            ->where('attempts.0.answers.0.options.0.is_correct', true)
            ->where('attempts.0.answers.0.selected_option_ids.0', $correct->id)
        );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizGradingRosterAnswersTest.php"`
Expected: FAIL — no `answers` key in the payload.

- [ ] **Step 3: Extend `QuizGradingController::index`**

Change the eager load in `index()`:

```php
        $attempts = QuizAttempt::where('learn_quiz_id', $quiz->id)
            ->with('answers.question.options', 'answers.selectedOptions')
            ->orderBy('student_id')
            ->orderBy('attempt_number')
            ->get();
```

Change the `attempts` mapping in the returned payload:

```php
            'attempts' => $attempts->map(function (QuizAttempt $attempt) use ($students) {
                $student = $students->get($attempt->student_id);

                $answers = $attempt->answers->map(fn ($answer) => [
                    'id' => $answer->id,
                    'question_type' => $answer->question->question_type,
                    'prompt' => $answer->question->prompt,
                    'points_possible' => (float) $answer->question->points,
                    'answer_text' => $answer->answer_text,
                    'selected_option_ids' => $answer->selectedOptions->pluck('learn_quiz_question_option_id')->values(),
                    'options' => in_array($answer->question->question_type, ['multiple_choice', 'true_false', 'multiple_select'], true)
                        ? $answer->question->options->map(fn ($o) => [
                            'id' => $o->id, 'option_text' => $o->option_text, 'is_correct' => $o->is_correct,
                        ])->values()
                        : null,
                    'is_correct' => $answer->is_correct,
                    'points_earned' => $answer->points_earned !== null ? (float) $answer->points_earned : null,
                ])->values();

                $pendingEssays = $answers->filter(fn ($a) => $a['question_type'] === 'essay' && $a['points_earned'] === null)->count();

                return [
                    'id' => $attempt->id,
                    'student_name' => $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$attempt->student_id}",
                    'attempt_number' => $attempt->attempt_number,
                    'is_submitted' => $attempt->isSubmitted(),
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'pending_essays' => $pendingEssays,
                    'answers' => $answers,
                ];
            })->values(),
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizGradingRosterAnswersTest.php"`
Expected: PASS (1 test).

- [ ] **Step 5: Run Task 11's existing grading test to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizGradingControllerTest.php"`
Expected: PASS (5 tests) — the richer payload must not break the existing `pending_essays`/`score` assertions.

- [ ] **Step 6: Write `QuizGrading.vue`**

`resources/js/Pages/Learn/QuizGrading.vue`:

```vue
<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MathContent from '@/Components/MathContent.vue'
import DOMPurify from 'dompurify'

const props = defineProps({ quiz: Object, attempts: Array })

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

const expandedAttemptId = ref(null)
const gradeForms = ref({})

function toggleExpand(attempt) {
  expandedAttemptId.value = expandedAttemptId.value === attempt.id ? null : attempt.id

  if (! gradeForms.value[attempt.id]) {
    const forms = {}
    for (const answer of attempt.answers) {
      if (answer.question_type === 'essay') {
        forms[answer.id] = useForm({ points_earned: answer.points_earned ?? '' })
      }
    }
    gradeForms.value[attempt.id] = forms
  }
}

function gradeEssay(attemptId, answerId) {
  gradeForms.value[attemptId][answerId].put(route('learn.quiz-attempt-answers.grade', answerId), { preserveScroll: true })
}

function reopen(attemptId) {
  router.post(route('learn.quiz-attempts.reopen', attemptId), {}, { preserveScroll: true })
}

const selectedClassRecordId = ref('')
const selectedQuarterId = ref('')
const selectedAssessmentId = ref('')

const availableQuarters = computed(() => {
  const cr = (props.quiz.class_record_options || []).find(c => c.id === Number(selectedClassRecordId.value))
  return cr ? cr.quarters : []
})
const availableAssessments = computed(() => {
  const q = availableQuarters.value.find(q => q.id === Number(selectedQuarterId.value))
  return q ? q.assessments : []
})

function linkAssessment() {
  if (! selectedAssessmentId.value) return
  router.put(route('learn.quizzes.link', props.quiz.id), {
    class_record_assessment_id: selectedAssessmentId.value,
  }, { preserveScroll: true })
}

function pushToClassRecord() {
  router.post(route('learn.quizzes.push', props.quiz.id), {}, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Quiz Grading — ${quiz.title}`" />
  <AdminLayout :title="quiz.title">
    <div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ quiz.title }}</h1>
        <p class="text-sm text-slate-500">{{ quiz.max_score }} pts total</p>
      </div>

      <div class="border border-slate-200 rounded-lg p-4 space-y-2">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Class Record</p>

        <div v-if="quiz.class_record_link">
          <p class="text-sm text-slate-700">
            Linked to <strong>{{ quiz.class_record_link.class_record_name }}</strong> —
            Q{{ quiz.class_record_link.quarter }} — {{ quiz.class_record_link.category_name }} —
            "{{ quiz.class_record_link.assessment_title }}"
          </p>
          <p class="text-xs text-slate-500 mt-1">
            {{ quiz.class_record_link.pushed_at ? `Last pushed ${new Date(quiz.class_record_link.pushed_at).toLocaleString('en-PH')}` : 'Not pushed yet' }}
          </p>
          <button @click="pushToClassRecord" class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Push graded scores
          </button>
        </div>

        <div v-else class="space-y-2">
          <p class="text-xs text-slate-500">Not linked yet. Pick the Class Record assessment to push scores into.</p>
          <div class="flex gap-2">
            <select v-model="selectedClassRecordId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
              <option value="" disabled>Class Record</option>
              <option v-for="cr in quiz.class_record_options" :key="cr.id" :value="cr.id">{{ cr.display_name }}</option>
            </select>
            <select v-model="selectedQuarterId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
              <option value="" disabled>Quarter</option>
              <option v-for="q in availableQuarters" :key="q.id" :value="q.id">Q{{ q.quarter }}</option>
            </select>
            <select v-model="selectedAssessmentId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
              <option value="" disabled>Assessment</option>
              <option v-for="a in availableAssessments" :key="a.id" :value="a.id">{{ a.category_name }} — {{ a.title }} ({{ a.max_score }} pts)</option>
            </select>
          </div>
          <button @click="linkAssessment" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">Link</button>
        </div>
      </div>

      <div v-for="attempt in attempts" :key="attempt.id" class="border border-slate-200 rounded-lg">
        <button class="w-full flex items-center justify-between px-4 py-3 text-left cursor-pointer hover:bg-slate-50" @click="toggleExpand(attempt)">
          <span class="text-sm font-medium text-slate-800">{{ attempt.student_name }} — Attempt {{ attempt.attempt_number }}</span>
          <div class="flex items-center gap-2">
            <span v-if="attempt.score !== null" class="text-xs text-slate-500">{{ attempt.score }} / {{ quiz.max_score }}</span>
            <span v-else-if="attempt.pending_essays > 0" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-amber-50 text-amber-700">{{ attempt.pending_essays }} pending</span>
            <span v-else-if="!attempt.is_submitted" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-100 text-slate-600">In progress</span>
          </div>
        </button>

        <div v-if="expandedAttemptId === attempt.id" class="border-t border-slate-100 p-4 space-y-3">
          <div v-for="answer in attempt.answers" :key="answer.id" class="border border-slate-100 rounded-lg p-3 space-y-1">
            <MathContent :html="sanitizeHtml(answer.prompt)" class="prose prose-sm max-w-none" />
            <p class="text-xs text-slate-400">{{ answer.points_possible }} pts</p>

            <div v-if="answer.options">
              <p v-for="opt in answer.options" :key="opt.id" class="text-sm"
                 :class="{ 'font-semibold text-emerald-700': opt.is_correct, 'underline': answer.selected_option_ids.includes(opt.id) }">
                {{ opt.option_text }} <span v-if="answer.selected_option_ids.includes(opt.id)">(selected)</span>
              </p>
            </div>
            <p v-else class="text-sm text-slate-700 whitespace-pre-line">{{ answer.answer_text }}</p>

            <div v-if="answer.question_type === 'essay' && gradeForms[attempt.id]" class="flex items-center gap-2 mt-2">
              <input v-model="gradeForms[attempt.id][answer.id].points_earned" type="number" min="0" :max="answer.points_possible" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-24" />
              <button @click="gradeEssay(attempt.id, answer.id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Save grade</button>
              <span v-if="answer.points_earned !== null" class="text-xs text-emerald-600">Graded: {{ answer.points_earned }}</span>
            </div>
            <p v-else-if="answer.is_correct !== null" class="text-xs" :class="answer.is_correct ? 'text-emerald-600' : 'text-red-600'">
              {{ answer.is_correct ? 'Correct' : 'Incorrect' }} — {{ answer.points_earned }} pts
            </p>
          </div>

          <button @click="reopen(attempt.id)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">Reopen for resubmission</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 7: Build and verify no errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `Learn/QuizGrading.vue`.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Learn/QuizGradingController.php resources/js/Pages/Learn/QuizGrading.vue \
        tests/Feature/Learn/QuizGradingRosterAnswersTest.php
git commit -m "feat(learn): add QuizGrading.vue with full answer detail and essay grading"
```

---

### Task 20: Student quiz-taking experience

**Files:**
- Modify: `resources/js/Pages/StudentPortal/Learn/Show.vue`
- Create: `resources/js/Pages/StudentPortal/Learn/QuizAttempt.vue`

**Interfaces:**
- Consumes `item.quiz` (Task 6), `student-portal.learn.quiz-attempts.start/answer/submit/show`
  (Tasks 7–10). No backend test — frontend-only, verified by build.

- [ ] **Step 1: Add quiz rendering to `StudentPortal/Learn/Show.vue`**

Add `router` to the existing Inertia import and `AcademicCapIcon` to the heroicons import:

```js
import { Head, router, useForm } from '@inertiajs/vue3'
```

```js
import { DocumentIcon, PaperClipIcon, AcademicCapIcon } from '@heroicons/vue/24/outline'
```

Add these functions (alongside `submitAssignment`):

```js
function startAttempt(quizId) {
  router.post(route('student-portal.learn.quiz-attempts.start', quizId))
}
function continueAttempt(attemptId) {
  router.visit(route('student-portal.learn.quiz-attempts.show', attemptId))
}
```

Update the item-icon line — change:

```html
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
```

to:

```html
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <AcademicCapIcon v-else-if="item.type === 'quiz'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
```

Immediately after the closing `</div>` of the existing `v-if="item.type === 'assignment'"` block,
add:

```html
                <div v-if="item.type === 'quiz'" class="mt-1 space-y-2">
                  <div v-if="item.quiz.instructions" class="prose prose-sm max-w-none" v-html="sanitizeHtml(item.quiz.instructions)" />
                  <p class="text-xs text-slate-500">
                    {{ item.quiz.question_count }} question{{ item.quiz.question_count === 1 ? '' : 's' }}
                    <span v-if="item.quiz.time_limit_minutes"> — {{ item.quiz.time_limit_minutes }} min</span>
                    <span v-if="item.quiz.due_at"> — due {{ new Date(item.quiz.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                    <span v-if="item.quiz.max_score !== null"> — {{ item.quiz.max_score }} pts</span>
                  </p>
                  <p class="text-xs text-slate-500">
                    Attempts used: {{ item.quiz.attempts_used }}<span v-if="item.quiz.max_attempts"> / {{ item.quiz.max_attempts }}</span>
                    <span v-if="item.quiz.best_score !== null"> — Best score: {{ item.quiz.best_score }} / {{ item.quiz.max_score }}</span>
                  </p>
                  <button v-if="item.quiz.in_progress_attempt_id" @click="continueAttempt(item.quiz.in_progress_attempt_id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Continue attempt
                  </button>
                  <button v-else-if="item.quiz.can_start_new_attempt" @click="startAttempt(item.quiz.id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    Start quiz
                  </button>
                  <p v-else class="text-xs text-slate-400">No attempts remaining.</p>
                </div>
```

- [ ] **Step 2: Write `QuizAttempt.vue`**

`resources/js/Pages/StudentPortal/Learn/QuizAttempt.vue`:

```vue
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import MathContent from '@/Components/MathContent.vue'
import DOMPurify from 'dompurify'

const props = defineProps({ attempt: Object })

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

const answers = ref({})
for (const q of props.attempt.questions) {
  answers.value[q.id] = {
    answer_text: q.your_answer?.answer_text || '',
    selected_option_ids: q.your_answer?.selected_option_ids ? [...q.your_answer.selected_option_ids] : [],
  }
}

function saveTextAnswer(question) {
  router.put(route('student-portal.learn.quiz-attempts.answer', [props.attempt.id, question.id]), {
    answer_text: answers.value[question.id].answer_text,
  }, { preserveScroll: true, preserveState: true })
}

function toggleOption(question, optionId) {
  const current = answers.value[question.id].selected_option_ids
  if (question.question_type === 'multiple_select') {
    const idx = current.indexOf(optionId)
    if (idx === -1) current.push(optionId)
    else current.splice(idx, 1)
  } else {
    answers.value[question.id].selected_option_ids = [optionId]
  }
  router.put(route('student-portal.learn.quiz-attempts.answer', [props.attempt.id, question.id]), {
    selected_option_ids: answers.value[question.id].selected_option_ids,
  }, { preserveScroll: true, preserveState: true })
}

function submitQuiz() {
  router.post(route('student-portal.learn.quiz-attempts.submit', props.attempt.id))
}

// Client-side countdown drives the auto-submit call; the server stays authoritative via
// lazy expiry finalization on next touch even if this timer never fires (dropped tab, etc).
const remainingSeconds = ref(null)
let timer = null

function computeRemaining() {
  if (! props.attempt.time_limit_minutes || props.attempt.is_submitted) return null
  const deadline = new Date(props.attempt.started_at).getTime() + props.attempt.time_limit_minutes * 60000
  return Math.max(0, Math.floor((deadline - Date.now()) / 1000))
}

onMounted(() => {
  remainingSeconds.value = computeRemaining()
  if (remainingSeconds.value === null) return
  timer = setInterval(() => {
    remainingSeconds.value = computeRemaining()
    if (remainingSeconds.value === 0) {
      clearInterval(timer)
      submitQuiz()
    }
  }, 1000)
})
onUnmounted(() => { if (timer) clearInterval(timer) })

const formattedRemaining = computed(() => {
  if (remainingSeconds.value === null) return null
  const m = Math.floor(remainingSeconds.value / 60)
  const s = remainingSeconds.value % 60
  return `${m}:${s.toString().padStart(2, '0')}`
})
</script>

<template>
  <Head :title="attempt.quiz_title" />
  <StudentPortalLayout>
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold text-slate-800">{{ attempt.quiz_title }}</h1>
          <p class="text-sm text-slate-500">{{ attempt.max_score }} pts total</p>
        </div>
        <div v-if="formattedRemaining && !attempt.is_submitted" class="text-sm font-medium text-amber-700">
          Time remaining: {{ formattedRemaining }}
        </div>
      </div>

      <div v-if="attempt.is_submitted" class="border border-emerald-200 bg-emerald-50 rounded-lg p-4">
        <p class="text-sm font-medium text-emerald-800">
          {{ attempt.score !== null ? `Score: ${attempt.score} / ${attempt.max_score}` : 'Submitted — awaiting grading on essay questions.' }}
        </p>
        <p v-if="attempt.auto_submitted" class="text-xs text-amber-700 mt-1">Automatically submitted when time ran out.</p>
      </div>

      <div v-for="(question, index) in attempt.questions" :key="question.id" class="border border-slate-200 rounded-lg p-4 space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Question {{ index + 1 }}</p>
          <p class="text-xs text-slate-400">{{ question.points }} pts</p>
        </div>
        <MathContent :html="sanitizeHtml(question.prompt)" class="prose prose-sm max-w-none" />

        <div v-if="question.options" class="space-y-1">
          <label v-for="option in question.options" :key="option.id" class="flex items-center gap-2 text-sm text-slate-700">
            <input
              :type="question.question_type === 'multiple_select' ? 'checkbox' : 'radio'"
              :name="`question-${question.id}`"
              :disabled="attempt.is_submitted"
              :checked="answers[question.id].selected_option_ids.includes(option.id)"
              @change="toggleOption(question, option.id)"
            />
            {{ option.option_text }}
          </label>
        </div>

        <textarea
          v-else
          v-model="answers[question.id].answer_text"
          :disabled="attempt.is_submitted"
          @blur="saveTextAnswer(question)"
          :placeholder="question.question_type === 'essay' ? 'Write your answer' : 'Short answer'"
          class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full"
          rows="3"
        />

        <p v-if="attempt.is_submitted && question.your_answer && question.your_answer.is_correct !== null" class="text-xs"
           :class="question.your_answer.is_correct ? 'text-emerald-600' : 'text-red-600'">
          {{ question.your_answer.is_correct ? 'Correct' : 'Incorrect' }} — {{ question.your_answer.points_earned }} pts
        </p>
      </div>

      <button v-if="!attempt.is_submitted" @click="submitQuiz" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
        Submit quiz
      </button>
    </div>
  </StudentPortalLayout>
</template>
```

- [ ] **Step 3: Build and verify no errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing either file.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/StudentPortal/Learn/Show.vue resources/js/Pages/StudentPortal/Learn/QuizAttempt.vue
git commit -m "feat(learn): add student quiz-taking experience (start/answer/submit/review)"
```

---

### Task 21: Analytics UI — QuizAnalytics.vue + QuizCourseTrend.vue

**Files:**
- Create: `resources/js/Pages/Learn/QuizAnalytics.vue`
- Create: `resources/js/Pages/Learn/QuizCourseTrend.vue`

**Interfaces:**
- Consumes `QuizAnalyticsController::show()`/`courseTrend()` payloads (Task 17). No backend
  test — frontend-only, verified by build.

- [ ] **Step 1: Write `QuizAnalytics.vue`**

`resources/js/Pages/Learn/QuizAnalytics.vue`:

```vue
<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import MathContent from '@/Components/MathContent.vue'
import DOMPurify from 'dompurify'

defineProps({ quiz: Object, analysis: Object })

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

function difficultyClass(difficulty) {
  return {
    easy: 'bg-emerald-50 text-emerald-700',
    medium: 'bg-amber-50 text-amber-700',
    hard: 'bg-red-50 text-red-700',
  }[difficulty] || 'bg-slate-100 text-slate-600'
}
</script>

<template>
  <Head :title="`Item Analysis — ${quiz.title}`" />
  <AdminLayout :title="quiz.title">
    <div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
      <h1 class="text-lg font-semibold text-slate-800">Item Analysis — {{ quiz.title }}</h1>

      <div class="border border-slate-200 rounded-lg p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Score distribution</p>
        <div class="grid grid-cols-4 gap-4 text-center">
          <div><p class="text-xs text-slate-500">Min</p><p class="text-lg font-semibold text-slate-800">{{ analysis.distribution.min ?? '—' }}</p></div>
          <div><p class="text-xs text-slate-500">Max</p><p class="text-lg font-semibold text-slate-800">{{ analysis.distribution.max ?? '—' }}</p></div>
          <div><p class="text-xs text-slate-500">Average</p><p class="text-lg font-semibold text-slate-800">{{ analysis.distribution.avg ?? '—' }}</p></div>
          <div><p class="text-xs text-slate-500">Median</p><p class="text-lg font-semibold text-slate-800">{{ analysis.distribution.median ?? '—' }}</p></div>
        </div>
      </div>

      <div v-for="question in analysis.questions" :key="question.id" class="border border-slate-200 rounded-lg p-4 space-y-1">
        <div class="flex items-center justify-between">
          <span v-if="question.difficulty" :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', difficultyClass(question.difficulty)]">{{ question.difficulty }}</span>
          <span class="text-sm font-medium text-slate-700">{{ question.avg_score_percentage !== null ? `${question.avg_score_percentage}%` : 'No data yet' }}</span>
        </div>
        <MathContent :html="sanitizeHtml(question.prompt)" class="prose prose-sm max-w-none" />
        <p class="text-xs text-slate-400">{{ question.graded_attempts }} graded attempt{{ question.graded_attempts === 1 ? '' : 's' }}</p>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Write `QuizCourseTrend.vue`**

`resources/js/Pages/Learn/QuizCourseTrend.vue`:

```vue
<script setup>
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ course: Object, trend: Object })

function difficultyClass(difficulty) {
  return {
    easy: 'bg-emerald-50 text-emerald-700',
    medium: 'bg-amber-50 text-amber-700',
    hard: 'bg-red-50 text-red-700',
  }[difficulty] || 'bg-slate-100 text-slate-600'
}
</script>

<template>
  <Head :title="`Quiz Trend — ${course.subject_name}`" />
  <AdminLayout :title="course.subject_name">
    <div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
      <h1 class="text-lg font-semibold text-slate-800">Quiz Trend — {{ course.subject_name }}</h1>

      <div class="border border-slate-200 rounded-lg p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Average score by quiz (chronological)</p>
        <div v-for="quiz in trend.quizzes" :key="quiz.id" class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
          <div>
            <p class="text-sm text-slate-700">{{ quiz.title }}</p>
            <p v-if="quiz.due_at" class="text-xs text-slate-400">{{ new Date(quiz.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
          </div>
          <span class="text-sm font-medium text-slate-800">{{ quiz.avg_score_percentage !== null ? `${quiz.avg_score_percentage}%` : 'No data yet' }}</span>
        </div>
        <p v-if="trend.quizzes.length === 0" class="text-xs text-slate-400">No quizzes yet.</p>
      </div>

      <div class="border border-slate-200 rounded-lg p-4">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Average score by difficulty</p>
        <div class="flex gap-4">
          <div v-for="difficulty in ['easy', 'medium', 'hard']" :key="difficulty" class="flex-1 text-center">
            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', difficultyClass(difficulty)]">{{ difficulty }}</span>
            <p class="text-lg font-semibold text-slate-800 mt-1">{{ trend.by_difficulty[difficulty] !== null ? `${trend.by_difficulty[difficulty]}%` : '—' }}</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 3: Build and verify no errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing either file.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Learn/QuizAnalytics.vue resources/js/Pages/Learn/QuizCourseTrend.vue
git commit -m "feat(learn): add quiz item analysis and course trend UI"
```

---

### Task 22: Full regression + manual verification

**Files:** none created — verification only.

- [ ] **Step 1: Run the full Learn suite together**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Learn tests/Feature/StudentPortal --filter=Learn"`
Expected: every Learn test from Phases 1/2/2b/2c/3 passes together — no regressions in either
direction. If the `--filter=Learn` combination misses any StudentPortal Learn/Quiz test files by
name, instead run `tests/Feature/Learn` and `tests/Feature/StudentPortal` as two explicit paths
(mirroring how Phase 2c's Task 7 verified this).

- [ ] **Step 2: Run the full project regression suite**

Run in the background (15–20+ minutes; do not run anything else that touches the database while
it's running): `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=1G vendor/bin/phpunit --no-coverage 2>/dev/null"`
Expected: no new failures beyond whatever pre-existing baseline is documented as of this plan's
execution — cross-check failing test names against the most recent known-clean baseline; none
should be in `tests/Feature/Learn` or `tests/Feature/StudentPortal`.

- [ ] **Step 3: Manual browser verification — golden path**

As a faculty member with a current-SY teaching `LoadAssignment`:

1. Create a quiz with a mix of question types (multiple choice, true/false, multiple select,
   short answer, essay), set a time limit and max attempts, save one question to the bank.
2. Confirm the bank item appears in "My templates"-style bank list and can be applied to a new
   question in a second quiz.
3. As a student enrolled in that section, start the quiz, answer every question, confirm answers
   autosave (reload mid-attempt and see them still there), submit.
4. Confirm auto-graded items show correct/incorrect immediately; essay stays "awaiting grading."
5. As the instructor, grade the essay item; confirm the attempt's total score appears once
   grading completes.
6. Start a second attempt (if `max_attempts` allows), get a different question order if
   `shuffle_questions` was set; confirm the gradebook-facing score is the higher of the two
   attempts.
7. Set a short time limit on a test quiz, start an attempt, let it expire without submitting,
   then reload — confirm it auto-finalized with `auto_submitted = true` and only the previously
   autosaved answers graded.
8. Link the quiz to a pre-existing Class Record assessment with a matching max score, push
   scores, confirm they land in Class Record and the linked assessment's `plotted_at`/
   `activity_date` are untouched.
9. Open the quiz's Item Analysis view and the course's Quiz Trend view; confirm the numbers match
   what was manually computed from the test attempts above.
10. Confirm a `$LaTeX$` expression in a question prompt renders as math, not raw text.

- [ ] **Step 4: Report results**

Note any issues found during manual verification; fix and re-verify before considering Phase 3
complete. Do not commit for this task — it is verification only.

---

## Phase 3 Complete — Program Status

Once all 22 tasks pass, the Learn Module roadmap's Phases 1, 2, 2b, 2c, and 3 are all complete:
course shell, assignments, Class Record push, a reusable rubric bank, and now a full quiz engine
with a question bank, timed/multi-attempt grading, Class Record integration, and analytics.
Phase 4 (Discussions/forums) remains unscoped — it would need its own
`superpowers:brainstorming` cycle before implementation, same as this phase did.





















