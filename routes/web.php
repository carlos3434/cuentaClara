<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Organizer\EventController as OrganizerEventController;
use App\Http\Controllers\Organizer\ExpenseController;
use App\Http\Controllers\Organizer\ReviewController;
use App\Http\Controllers\Public\EventController as PublicEventController;
use App\Http\Controllers\Public\ExpenseController as PublicExpenseController;
use App\Http\Controllers\Public\ReceiptController as PublicReceiptController;
use Illuminate\Support\Facades\Route;

// Landing → organizer dashboard (guests get bounced to /login by the auth middleware).
Route::redirect('/', '/events');

/*
 * Guest-only auth screens.
 */
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
});

/*
 * Organizer area (requires authentication).
 */
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::name('organizer.')->group(function () {
        Route::get('/events', [OrganizerEventController::class, 'index'])->name('events.index');
        Route::get('/events/more', [OrganizerEventController::class, 'more'])->name('events.more');
        Route::get('/events/create', [OrganizerEventController::class, 'create'])->name('events.create');
        Route::post('/events', [OrganizerEventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/created', [OrganizerEventController::class, 'created'])->name('events.created');
        Route::post('/events/{event}/close', [OrganizerEventController::class, 'close'])->name('events.close');
        Route::post('/events/{event}/reopen', [OrganizerEventController::class, 'reopen'])->name('events.reopen');

        // Review hub + actions
        Route::get('/events/{event}/review', [ReviewController::class, 'show'])->name('events.review');
        Route::get('/events/{event}/participants/more', [ReviewController::class, 'participantsMore'])->name('events.participants.more');
        Route::get('/events/{event}/receipts/{receipt}/image', [ReviewController::class, 'image'])->name('receipts.image');
        Route::post('/events/{event}/receipts/{receipt}/approve', [ReviewController::class, 'approve'])->name('receipts.approve');
        Route::post('/events/{event}/receipts/{receipt}/reject', [ReviewController::class, 'reject'])->name('receipts.reject');
        Route::post('/events/{event}/participants/{participant}/cash', [ReviewController::class, 'cash'])->name('participants.cash');

        // Organizer's own expense receipts (store-only)
        Route::post('/events/{event}/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('/events/{event}/expenses/{expense}/image', [ExpenseController::class, 'image'])->name('expenses.image');
        Route::delete('/events/{event}/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });
});

/*
 * Admin area (platform administration). Requires an authenticated admin.
 */
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::post('/users/{user}/toggle', [AdminUserController::class, 'toggle'])->name('users.toggle');
});

/*
 * Public, link-based access (no login).
 */
Route::get('/e/{event}', [PublicEventController::class, 'show'])->name('public.events.show');
Route::post('/e/{event}/receipts', [PublicReceiptController::class, 'store'])
    ->middleware('throttle:uploads')
    ->name('public.receipts.store');
Route::get('/e/{event}/expenses/{expense}/image', [PublicExpenseController::class, 'image'])->name('public.expenses.image');
