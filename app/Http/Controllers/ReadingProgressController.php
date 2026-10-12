<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ReadingProgress;
use App\Services\Reader\ReadingSessionManager;
use App\Services\Reader\ReadingTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReadingProgressController extends Controller
{
    /**
     * Save the current reading position (HU-015).
     */
    public function store(Request $request, Book $book, ReadingSessionManager $sessions, ReadingTracker $tracker): JsonResponse
    {
        Gate::authorize('read', $book);

        $total = $book->unitCount();

        $validated = $request->validate([
            'position' => ['required', 'integer', 'min:1', 'max:'.$total],
        ]);

        $previousPosition = ReadingProgress::where(['user_id' => $request->user()->id, 'book_id' => $book->id])->value('position');
        $session = $sessions->find($request->user(), $book, $request->header(ReaderController::SESSION_HEADER));

        if ($session) {
            $tracker->record($session, $book, $previousPosition !== $validated['position'] || $session->last_tracked_at === null);
        }

        $percentage = round($validated['position'] / $total * 100, 2);

        $progress = ReadingProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'book_id' => $book->id],
            [
                'position' => $validated['position'],
                'total' => $total,
                'percentage' => $percentage,
                'finished_at' => $validated['position'] === $total ? now() : null,
            ],
        );

        return response()->json(['position' => $progress->position, 'percentage' => $progress->percentage]);
    }
}
