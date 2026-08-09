<?php

namespace App\Services\Learn;

use App\Models\Learn\Discussion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DiscussionPostService
{
    /** @return array Nested tree — each node: {id, author_type, author_id, author_name, body, is_deleted, created_at, updated_at, replies}. */
    public function tree(Discussion $discussion): array
    {
        $posts = $discussion->posts()->get();
        $authorNames = $this->resolveAuthorNames($posts);

        $childrenByParent = [];
        foreach ($posts as $post) {
            $childrenByParent[$post->parent_post_id ?? 0][] = $post;
        }

        return $this->buildBranch(0, $childrenByParent, $authorNames);
    }

    private function buildBranch(int $parentId, array $childrenByParent, array $authorNames): array
    {
        $branch = [];

        foreach ($childrenByParent[$parentId] ?? [] as $post) {
            $branch[] = [
                'id' => $post->id,
                'author_type' => $post->author_type,
                'author_id' => $post->author_id,
                'author_name' => $authorNames["{$post->author_type}:{$post->author_id}"] ?? 'Unknown',
                'body' => $post->is_deleted ? null : $post->body,
                'is_deleted' => $post->is_deleted,
                'created_at' => $post->created_at->toIso8601String(),
                'updated_at' => $post->updated_at->toIso8601String(),
                'replies' => $this->buildBranch($post->id, $childrenByParent, $authorNames),
            ];
        }

        return $branch;
    }

    /** @return array<string, string> "{author_type}:{author_id}" => display name */
    private function resolveAuthorNames(Collection $posts): array
    {
        $studentIds = $posts->where('author_type', 'student')->pluck('author_id')->unique();
        $userIds = $posts->where('author_type', 'faculty')->pluck('author_id')->unique();

        $names = [];

        if ($studentIds->isNotEmpty()) {
            foreach (DB::table('students')->whereIn('id', $studentIds)->get(['id', 'firstname', 'lastname']) as $s) {
                $names["student:{$s->id}"] = trim("{$s->firstname} {$s->lastname}");
            }
        }

        if ($userIds->isNotEmpty()) {
            foreach (User::whereIn('id', $userIds)->get(['id', 'name']) as $u) {
                $names["faculty:{$u->id}"] = $u->name;
            }
        }

        return $names;
    }
}
