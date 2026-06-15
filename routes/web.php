<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\OutfitController;
use App\Http\Controllers\ProfileController;
use App\Models\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $profile = Profile::firstOrFail();

    return redirect()->route('calendar', ['handle' => $profile->handle]);
});

Route::get('/settings', [ProfileController::class, 'edit'])->name('profile.edit');
Route::post('/settings', [ProfileController::class, 'update'])->name('profile.update');

Route::get('/days/{date}', [OutfitController::class, 'edit'])
    ->name('outfits.edit')->where('date', '\d{4}-\d{2}-\d{2}');
Route::post('/days/{date}', [OutfitController::class, 'update'])
    ->name('outfits.update')->where('date', '\d{4}-\d{2}-\d{2}');
Route::delete('/days/{date}', [OutfitController::class, 'destroy'])
    ->name('outfits.destroy')->where('date', '\d{4}-\d{2}-\d{2}');

Route::get('/@{handle}/{year?}/{month?}', [CalendarController::class, 'show'])
    ->name('calendar')
    ->where(['handle' => '[A-Za-z0-9_-]+', 'year' => '\d{4}', 'month' => '\d{1,2}']);
