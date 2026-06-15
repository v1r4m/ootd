<?php

namespace App\Http\Controllers;

use App\Models\Outfit;
use App\Models\Profile;
use App\Services\AvatarGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OutfitController extends Controller
{
    public function edit(string $date)
    {
        $day = $this->parseDate($date);
        $profile = Profile::firstOrFail();
        $outfit = Outfit::where('worn_on', $day)->first();

        return view('outfits.edit', [
            'profile' => $profile,
            'day' => $day,
            'outfit' => $outfit,
        ]);
    }

    public function update(Request $request, string $date, AvatarGenerator $generator)
    {
        $day = $this->parseDate($date);
        $profile = Profile::firstOrFail();

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $outfit = Outfit::updateOrCreate(
            ['worn_on' => $day],
            ['profile_id' => $profile->id, 'description' => $validated['description']],
        );

        try {
            $generator->generate($outfit, $profile);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', '아바타 생성에 실패했어요: '.$e->getMessage());
        }

        return redirect()
            ->route('calendar', ['handle' => $profile->handle, 'year' => $day->year, 'month' => $day->month])
            ->with('status', $day->format('n월 j일').' 옷이 기록됐어요!');
    }

    public function destroy(string $date)
    {
        $day = $this->parseDate($date);
        $profile = Profile::firstOrFail();
        $outfit = Outfit::where('worn_on', $day)->firstOrFail();

        if ($outfit->avatar_path) {
            Storage::disk('public')->delete($outfit->avatar_path);
        }
        $outfit->delete();

        return redirect()
            ->route('calendar', ['handle' => $profile->handle, 'year' => $day->year, 'month' => $day->month])
            ->with('status', $day->format('n월 j일').' 기록을 지웠어요.');
    }

    private function parseDate(string $date): Carbon
    {
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (Throwable) {
            abort(404);
        }

        abort_if($day->format('Y-m-d') !== $date, 404);

        return $day;
    }
}
