<?php

namespace Tests\Feature\ClassRecord;

use App\Console\Commands\ClassRecord\ResequenceStudents;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResequenceStudentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_resequences_an_out_of_order_roster_alphabetically(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
        $teacher = User::factory()->create();
        $record = ClassRecord::create([
            'grading_option_id' => $option->id, 'school_year_id' => $sy->id, 'school_year' => $sy->name,
            'subject_name' => 'Subject 1', 'year_level_section' => 'G-8 Test', 'teacher_id' => $teacher->id,
            'status' => 'draft',
        ]);
        $quarter = ClassRecordQuarter::create(['class_record_id' => $record->id, 'quarter' => 1, 'is_locked' => false]);

        // Deliberately out-of-order: Zeta first, Alpha last.
        $zeta = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'ZETA', 'given_name' => 'ZOE',
            'sequence_number' => 1, 'is_active' => true,
        ]);
        $mid = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'MIDDLE', 'given_name' => 'MAE',
            'sequence_number' => 2, 'is_active' => true,
        ]);
        $alpha = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'ALPHA', 'given_name' => 'ANA',
            'sequence_number' => 3, 'is_active' => true,
        ]);
        // Inactive/removed student — must be left alone, not counted in the new sequence.
        $inactive = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'BETA', 'given_name' => 'BEN',
            'sequence_number' => 4, 'is_active' => false,
        ]);

        $this->artisan(ResequenceStudents::class)->assertSuccessful();

        $this->assertSame(1, $alpha->fresh()->sequence_number);
        $this->assertSame(2, $mid->fresh()->sequence_number);
        $this->assertSame(3, $zeta->fresh()->sequence_number);
        $this->assertSame(4, $inactive->fresh()->sequence_number); // untouched
    }
}
