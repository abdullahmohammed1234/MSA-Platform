<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DiscussionBookmark;
use App\Models\DiscussionCategory;
use App\Models\DiscussionPost;
use App\Models\DiscussionReaction;
use App\Models\DiscussionReport;
use App\Models\DiscussionThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscussionController extends Controller
{
    private const REACTION_TYPES = ['THUMBS_UP', 'HEART', 'CLAP', 'INSIGHTFUL', 'LAUGH', 'SAD'];

    private const REPORT_REASONS = ['SPAM', 'HARASSMENT', 'INAPPROPRIATE_CONTENT', 'MISINFORMATION', 'OTHER'];

    public function getCategories(): JsonResponse
    {
        $categories = DiscussionCategory::all()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
        ]);

        return response()->json([
            'success' => true,
            'data' => $categories,
            'categories' => $categories,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $categoryId = $request->input('categoryId', $request->input('category_id'));

        $query = DiscussionThread::with(['category', 'author']);

        if ($categoryId && $categoryId !== 'all') {
            $query->where('category_id', $categoryId);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $userId = $request->user()?->id;

        $threads = $query->latest()->get()->map(function ($thread) use ($userId) {
            $reactionCounts = DiscussionReaction::where('thread_id', $thread->id)
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type');

            return [
                'id' => $thread->id,
                'title' => $thread->title,
                'content' => $thread->content,
                'created_at' => $thread->created_at->toIso8601String(),
                'category' => [
                    'id' => $thread->category->id,
                    'name' => $thread->category->name,
                    'slug' => $thread->category->slug,
                ],
                'author' => $this->formatAuthor($thread->author),
                'posts_count' => $thread->posts()->count(),
                'reactions' => $reactionCounts,
                'bookmarked' => $userId
                    ? DiscussionBookmark::where('user_id', $userId)->where('thread_id', $thread->id)->exists()
                    : false,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $threads,
        ]);
    }

    public function getPosts(string $threadId): JsonResponse
    {
        $posts = DiscussionPost::with('author')
            ->where('thread_id', $threadId)
            ->oldest()
            ->get()
            ->map(function ($post) {
                $reactionCounts = DiscussionReaction::where('post_id', $post->id)
                    ->selectRaw('type, count(*) as total')
                    ->groupBy('type')
                    ->pluck('total', 'type');

                return [
                    'id' => $post->id,
                    'content' => $post->content,
                    'created_at' => $post->created_at->toIso8601String(),
                    'author' => $this->formatAuthor($post->author),
                    'reactions' => $reactionCounts,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $posts,
        ]);
    }

    public function storeThread(Request $request): JsonResponse
    {
        $categoryId = $request->input('categoryId', $request->input('category_id'));

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        if (!$categoryId) {
            return response()->json(['success' => false, 'message' => 'Category is required.'], 422);
        }

        $thread = DiscussionThread::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category_id' => $categoryId,
            'user_id' => $request->user()->id,
        ]);

        $thread->load(['category', 'author']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $thread->id,
                'title' => $thread->title,
                'content' => $thread->content,
                'created_at' => $thread->created_at->toIso8601String(),
                'category' => [
                    'id' => $thread->category->id,
                    'name' => $thread->category->name,
                    'slug' => $thread->category->slug,
                ],
                'author' => $this->formatAuthor($thread->author),
                'posts_count' => 0,
            ],
        ], 201);
    }

    public function storePost(Request $request, string $threadId): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $post = DiscussionPost::create([
            'content' => $validated['content'],
            'thread_id' => $threadId,
            'user_id' => $request->user()->id,
        ]);

        $post->load('author');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $post->id,
                'content' => $post->content,
                'created_at' => $post->created_at->toIso8601String(),
                'author' => $this->formatAuthor($post->author),
                'reactions' => [],
            ],
            'post' => [
                'id' => $post->id,
                'content' => $post->content,
                'created_at' => $post->created_at->toIso8601String(),
                'author' => $this->formatAuthor($post->author),
            ],
        ], 201);
    }

    public function toggleReaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(self::REACTION_TYPES)],
            'thread_id' => 'nullable|integer|exists:discussion_threads,id',
            'threadId' => 'nullable|integer|exists:discussion_threads,id',
            'post_id' => 'nullable|integer|exists:discussion_posts,id',
            'postId' => 'nullable|integer|exists:discussion_posts,id',
        ]);

        $threadId = $validated['thread_id'] ?? $validated['threadId'] ?? null;
        $postId = $validated['post_id'] ?? $validated['postId'] ?? null;

        if (!$threadId && !$postId) {
            return response()->json(['success' => false, 'message' => 'thread_id or post_id is required.'], 422);
        }

        $query = DiscussionReaction::where('user_id', $request->user()->id)
            ->where('type', $validated['type']);

        if ($threadId) {
            $query->where('thread_id', $threadId)->whereNull('post_id');
        } else {
            $query->where('post_id', $postId)->whereNull('thread_id');
        }

        $existing = $query->first();

        if ($existing) {
            $existing->delete();
            $active = false;
        } else {
            DiscussionReaction::create([
                'user_id' => $request->user()->id,
                'thread_id' => $threadId,
                'post_id' => $postId,
                'type' => $validated['type'],
            ]);
            $active = true;
        }

        return response()->json([
            'success' => true,
            'active' => $active,
            'type' => $validated['type'],
        ]);
    }

    public function toggleBookmark(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'thread_id' => 'required_without:threadId|integer|exists:discussion_threads,id',
            'threadId' => 'required_without:thread_id|integer|exists:discussion_threads,id',
        ]);

        $threadId = $validated['thread_id'] ?? $validated['threadId'];

        $bookmark = DiscussionBookmark::where('user_id', $request->user()->id)
            ->where('thread_id', $threadId)
            ->first();

        if ($bookmark) {
            $bookmark->delete();
            $bookmarked = false;
        } else {
            DiscussionBookmark::create([
                'user_id' => $request->user()->id,
                'thread_id' => $threadId,
            ]);
            $bookmarked = true;
        }

        return response()->json([
            'success' => true,
            'bookmarked' => $bookmarked,
        ]);
    }

    public function storeReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', Rule::in(self::REPORT_REASONS)],
            'thread_id' => 'nullable|integer|exists:discussion_threads,id',
            'threadId' => 'nullable|integer|exists:discussion_threads,id',
            'post_id' => 'nullable|integer|exists:discussion_posts,id',
            'postId' => 'nullable|integer|exists:discussion_posts,id',
        ]);

        $threadId = $validated['thread_id'] ?? $validated['threadId'] ?? null;
        $postId = $validated['post_id'] ?? $validated['postId'] ?? null;

        if (!$threadId && !$postId) {
            return response()->json(['success' => false, 'message' => 'thread_id or post_id is required.'], 422);
        }

        DiscussionReport::create([
            'user_id' => $request->user()->id,
            'thread_id' => $threadId,
            'post_id' => $postId,
            'reason' => $validated['reason'],
            'status' => 'open',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report submitted. Our moderators will review it shortly.',
        ], 201);
    }

    private function formatAuthor($author): array
    {
        return [
            'id' => $author->id,
            'name' => $author->name,
            'avatar_url' => $author->avatar,
            'roles' => $author->roles()->pluck('slug')->all(),
        ];
    }
}
