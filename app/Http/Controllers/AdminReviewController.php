<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Series;
use App\Services\ModerationNotifier;
use App\Support\ReviewBody;
use App\Support\SiteConfig;
use App\Support\TplCache;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminReviewController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'approved');
        $perPage = min(100, max(10, (int) $request->query('per_page', 50)));
        $page = max(1, (int) $request->query('page', 1));

        $query = Review::query()
            ->with(['user:id,name,email', 'series:id,title,slug,kp_id,year,start_year']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Oldest pending first — moderation queue; otherwise newest first.
        if ($status === 'pending') {
            $query->orderBy('id');
        } else {
            $query->orderByDesc('id');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $min = SiteConfig::int('reviews_body_min_length');
        $max = SiteConfig::int('reviews_body_max_length');
        $authorMax = SiteConfig::int('reviews_author_name_max_length');

        $data = $request->validate([
            'series_id' => ['required', 'integer', 'exists:series,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:10'],
            'body' => ['required', 'string', 'min:' . $min, 'max:' . $max],
            'author_name' => ['nullable', 'string', 'max:' . $authorMax],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        $series = Series::query()->findOrFail((int) $data['series_id']);
        $body = ReviewBody::assertValid($data['body']);
        $authorName = trim((string) ($data['author_name'] ?? ''));
        $userId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        if ($userId) {
            $exists = Review::query()
                ->where('series_id', $series->id)
                ->where('user_id', $userId)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'user_id' => 'У этого пользователя уже есть рецензия на этот сериал.',
                ]);
            }
        }

        try {
            $review = Review::query()->create([
                'series_id' => $series->id,
                'user_id' => $userId,
                'rating' => (int) $data['rating'],
                'body' => $body,
                'author_name' => $authorName !== '' ? $authorName : ($userId ? null : SiteConfig::str('reviews_label_editorial')),
                'is_editorial' => true,
                'status' => $data['status'] ?? 'approved',
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'user_id' => 'У этого пользователя уже есть рецензия на этот сериал.',
            ]);
        }

        TplCache::forgetSeries($series->id);

        $review->load(['user:id,name,email', 'series:id,title,slug,kp_id,year,start_year']);

        return response()->json(['ok' => true, 'item' => $review]);
    }

    public function update(Request $request, int $id)
    {
        $min = SiteConfig::int('reviews_body_min_length');
        $max = SiteConfig::int('reviews_body_max_length');
        $authorMax = SiteConfig::int('reviews_author_name_max_length');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:10'],
            'body' => ['required', 'string', 'min:' . $min, 'max:' . $max],
            'author_name' => ['nullable', 'string', 'max:' . $authorMax],
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        $review = Review::query()->with(['series', 'user'])->findOrFail($id);
        $previousStatus = (string) $review->status;
        $review->rating = (int) $data['rating'];
        $review->body = ReviewBody::assertValid($data['body']);
        if (array_key_exists('author_name', $data)) {
            $authorName = trim((string) ($data['author_name'] ?? ''));
            $review->author_name = $authorName !== '' ? $authorName : null;
        }
        if (isset($data['status'])) {
            $review->status = $data['status'];
        }
        $review->save();

        if ($review->series_id) {
            TplCache::forgetSeries((int) $review->series_id);
        }

        ModerationNotifier::reviewApproved($review, $previousStatus);

        $review->load(['user:id,name,email', 'series:id,title,slug,kp_id,year,start_year']);

        return response()->json(['ok' => true, 'item' => $review]);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,pending'],
        ]);

        $review = Review::query()->with(['series', 'user'])->findOrFail($id);
        $previousStatus = (string) $review->status;
        $review->status = $data['status'];
        $review->save();

        if ($review->series_id) {
            TplCache::forgetSeries((int) $review->series_id);
        }

        ModerationNotifier::reviewApproved($review, $previousStatus);

        return response()->json(['ok' => true, 'item' => $review]);
    }

    public function destroy(int $id)
    {
        $review = Review::query()->find($id);
        if ($review) {
            $seriesId = (int) $review->series_id;
            $review->delete();
            if ($seriesId > 0) {
                TplCache::forgetSeries($seriesId);
            }
        }

        return response()->json(['ok' => true]);
    }
}
