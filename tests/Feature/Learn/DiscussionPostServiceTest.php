<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\Discussion;
use App\Models\User;
use App\Services\Learn\DiscussionPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscussionPostServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tree_nests_replies_at_least_three_levels_deep_regardless_of_fetch_order(): void
    {
        $service = app(DiscussionPostService::class);
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);

        $studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Ana', 'lastname' => 'Cruz']);
        $teacher = User::factory()->create(['name' => 'Mr. Santos']);

        $top = $discussion->posts()->create(['author_type' => 'student', 'author_id' => $studentId, 'body' => 'Top level']);
        $reply1 = $discussion->posts()->create(['parent_post_id' => $top->id, 'author_type' => 'faculty', 'author_id' => $teacher->id, 'body' => 'Reply 1']);
        $reply2 = $discussion->posts()->create(['parent_post_id' => $reply1->id, 'author_type' => 'student', 'author_id' => $studentId, 'body' => 'Reply 2']);

        $tree = $service->tree($discussion);

        $this->assertCount(1, $tree);
        $this->assertSame('Top level', $tree[0]['body']);
        $this->assertSame('Ana Cruz', $tree[0]['author_name']);
        $this->assertCount(1, $tree[0]['replies']);
        $this->assertSame('Reply 1', $tree[0]['replies'][0]['body']);
        $this->assertSame('Mr. Santos', $tree[0]['replies'][0]['author_name']);
        $this->assertCount(1, $tree[0]['replies'][0]['replies']);
        $this->assertSame('Reply 2', $tree[0]['replies'][0]['replies'][0]['body']);
    }

    public function test_deleted_post_hides_body_but_keeps_children_in_the_tree(): void
    {
        $service = app(DiscussionPostService::class);
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);

        $top = $discussion->posts()->create(['author_type' => 'student', 'author_id' => 111, 'body' => 'Original']);
        $discussion->posts()->create(['parent_post_id' => $top->id, 'author_type' => 'student', 'author_id' => 222, 'body' => 'A reply']);
        $top->update(['is_deleted' => true, 'deleted_by_type' => 'student', 'deleted_by_id' => 111]);

        $tree = $service->tree($discussion);

        $this->assertTrue($tree[0]['is_deleted']);
        $this->assertNull($tree[0]['body']);
        $this->assertCount(1, $tree[0]['replies']);
        $this->assertSame('A reply', $tree[0]['replies'][0]['body']);
    }
}
