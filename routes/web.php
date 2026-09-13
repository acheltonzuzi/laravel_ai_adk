<?php

use App\Http\Controllers\CarController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::controller(CarController::class)->group(function () {
        Route::get('/cars', 'index')->name('cars.index');
        Route::post('/cars', 'store')->name('cars.store');
        Route::put('/cars/{car}', 'update')->name('cars.update');
        Route::delete('/cars/{car}', 'destroy')->name('cars.destroy');
    });

    Route::controller(RentalController::class)->group(function () {
        Route::get('/rentals', 'index')->name('rentals.index');
        Route::post('/rentals', 'store')->name('rentals.store');
        Route::put('/rentals/{rental}', 'update')->name('rentals.update');
        Route::delete('/rentals/{rental}', 'destroy')->name('rentals.destroy');
    });

    Route::controller(UserController::class)->group(function () {
        Route::get('/users', 'index')->name('users.index');
        Route::post('/users', 'store')->name('users.store');
        Route::put('/users/{user}', 'update')->name('users.update');
        Route::delete('/users/{user}', 'destroy')->name('users.destroy');
    });

    Route::controller(ChatController::class)->group(function () {
        Route::get('/chat', 'index')->name('chat.index');
        Route::post('/chat', 'sendMessage')->name('chat.send');
        Route::post('/chat/approvals', 'resolveApproval')->name('chat.approvals');
        Route::get('/chat/conversations', 'getConversation')->name('chat.conversations');
        Route::get('/chat/conversations/{conversation}', 'getConversationMessages')->name('chat.conversation.messages');
    });
});

require __DIR__.'/settings.php';
