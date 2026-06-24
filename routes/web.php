<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\OutfitController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check() && Auth::user()->profile) {
        return redirect()->route('calendar', ['handle' => Auth::user()->profile->handle]);
    }

    return view('welcome');
});

// 인증 (비로그인 전용)
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// 본인 옷장 수정 (로그인 필요)
Route::middleware('auth')->group(function () {
    Route::get('/settings', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/settings', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/days/{date}', [OutfitController::class, 'edit'])
        ->name('outfits.edit')->where('date', '\d{4}-\d{2}-\d{2}');
    Route::post('/days/{date}', [OutfitController::class, 'update'])
        ->name('outfits.update')->where('date', '\d{4}-\d{2}-\d{2}');
    Route::delete('/days/{date}', [OutfitController::class, 'destroy'])
        ->name('outfits.destroy')->where('date', '\d{4}-\d{2}-\d{2}');
});

// 공개 달력 (누구나 구경 가능, 수정은 본인만)
Route::get('/@{handle}/{year?}/{month?}', [CalendarController::class, 'show'])
    ->name('calendar')
    ->where(['handle' => '[A-Za-z0-9_-]+', 'year' => '\d{4}', 'month' => '\d{1,2}']);
