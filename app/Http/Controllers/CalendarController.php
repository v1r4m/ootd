<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function show(string $handle, ?int $year = null, ?int $month = null)
    {
        $profile = Profile::where('handle', $handle)->firstOrFail();

        $today = Carbon::today();
        $year ??= $today->year;
        $month ??= $today->month;
        abort_unless($month >= 1 && $month <= 12, 404);

        $first = Carbon::create($year, $month, 1);

        $outfits = $profile->outfits()
            ->whereBetween('worn_on', [$first, $first->copy()->endOfMonth()])
            ->get()
            ->keyBy(fn ($o) => $o->worn_on->toDateString());

        // 일요일 시작 달력 그리드 (앞쪽 빈 칸 포함)
        $days = [];
        for ($i = 0; $i < $first->dayOfWeek; $i++) {
            $days[] = null;
        }
        for ($d = $first->copy(); $d->month === $first->month; $d->addDay()) {
            $days[] = $d->copy();
        }

        return view('calendar.show', [
            'profile' => $profile,
            'first' => $first,
            'days' => $days,
            'outfits' => $outfits,
            'today' => $today,
            'prev' => $first->copy()->subMonth(),
            'next' => $first->copy()->addMonth(),
        ]);
    }
}
