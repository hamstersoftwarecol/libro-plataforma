<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\ReadingDailyStat;
use App\Models\ReadingProgress;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReadingDemoSeeder extends Seeder
{
    /**
     * Ninety days of demo reading activity for the analytics panel.
     */
    public function run(): void
    {
        $books = Book::published()->get();
        $readers = User::query()->whereHas('subscriptions')->where('role', 'reader')->get();

        foreach ($readers as $reader) {
            if (random_int(1, 100) <= 20) {
                continue;
            }

            foreach ($books->random(random_int(1, 3)) as $book) {
                $total = $book->unitCount();
                $position = 1;
                $day = now()->subDays(random_int(5, 89));

                while ($day->lte(now()) && $position < $total) {
                    if (random_int(1, 100) <= ($day->isWeekend() ? 55 : 35)) {
                        $pages = random_int(1, 3);
                        $position = min($total, $position + $pages);

                        ReadingDailyStat::updateOrCreate(
                            ['user_id' => $reader->id, 'book_id' => $book->id, 'date' => $day->toDateString()],
                            ['pages_viewed' => $pages, 'seconds_read' => $pages * random_int(60, 240)],
                        );
                    }

                    if (random_int(1, 100) <= 4) {
                        break;
                    }

                    $day = $day->addDay();
                }

                ReadingProgress::updateOrCreate(['user_id' => $reader->id, 'book_id' => $book->id], [
                    'position' => $position,
                    'total' => $total,
                    'percentage' => round($position / $total * 100, 2),
                    'finished_at' => $position >= $total ? now() : null,
                ]);
            }
        }
    }
}
