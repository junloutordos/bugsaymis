<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Borrowing;
use App\Models\LibraryCollection;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetLibraryStatsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult;
use Tests\TestCase;

class GetLibraryStatsToolTest extends TestCase
{
    use AssertsJsonSafeToolResult;
    use RefreshDatabase;

    public function test_returns_current_borrowed_overdue_and_distinct_active_borrower_counts(): void
    {
        $book = LibraryCollection::create(['title' => 'Test Book']);

        Borrowing::create(['collection_id' => $book->id, 'borrower_type' => 'App\\Models\\User', 'borrower_id' => 1, 'borrow_date' => now()->subDays(5), 'due_date' => now()->addDays(3), 'status' => 'Borrowed']);
        Borrowing::create(['collection_id' => $book->id, 'borrower_type' => 'App\\Models\\User', 'borrower_id' => 2, 'borrow_date' => now()->subDays(10), 'due_date' => now()->subDays(2), 'status' => 'Borrowed']);
        Borrowing::create(['collection_id' => $book->id, 'borrower_type' => 'App\\Models\\User', 'borrower_id' => 1, 'borrow_date' => now()->subDays(20), 'due_date' => now()->subDays(10), 'status' => 'Returned']);

        $user = User::factory()->create();

        $result = (new GetLibraryStatsTool())->execute($user, []);

        $this->assertEquals(2, $result['currently_borrowed']);
        $this->assertEquals(1, $result['overdue']);
        $this->assertEquals(2, $result['active_borrowers']);
    }

    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $book = LibraryCollection::create(['title' => 'Test Book']);
        Borrowing::create(['collection_id' => $book->id, 'borrower_type' => 'App\\Models\\User', 'borrower_id' => 1, 'borrow_date' => now()->subDays(5), 'due_date' => now()->addDays(3), 'status' => 'Borrowed']);

        $user = User::factory()->create();

        $result = (new GetLibraryStatsTool())->execute($user, []);

        $this->assertNoNonScalarLeaves($result);
    }
}
