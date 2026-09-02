<?php

use App\Http\Controllers\CheckController;
use App\Http\Controllers\MisspellingController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('check', [CheckController::class, 'index'])->name('check');
Route::post('check', [CheckController::class, 'store'])->name('check.store');
Route::get('check/{document}', [CheckController::class, 'show'])->name('check.show');

Route::patch('misspellings/{misspelling}', [MisspellingController::class, 'update'])->name('misspellings.update');

require __DIR__.'/settings.php';
