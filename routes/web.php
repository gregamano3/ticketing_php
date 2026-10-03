<?php

use App\Http\Controllers\Admin\LookupController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CannedResponseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KbArticleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketReplyController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/home');

// Internal helpdesk: accounts are created by administrators, not self-registered.
Auth::routes(['register' => false, 'verify' => false]);

Route::middleware('auth')->group(function () {
    Route::get('/home', DashboardController::class)->name('home');

    // Tickets
    Route::get('tickets/export', [TicketController::class, 'export'])->name('tickets.export');
    Route::resource('tickets', TicketController::class);
    Route::post('tickets/{ticket}/claim', [TicketController::class, 'claim'])->name('tickets.claim');
    Route::post('tickets/{ticket}/watch', [TicketController::class, 'toggleWatch'])->name('tickets.watch');
    Route::post('tickets/{ticket}/replies', [TicketReplyController::class, 'store'])->name('tickets.replies.store');

    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Knowledge base
    Route::prefix('kb')->name('kb.')->group(function () {
        Route::get('/', [KbArticleController::class, 'index'])->name('index');
        Route::get('category/{category}', [KbArticleController::class, 'category'])->name('category');
        Route::resource('articles', KbArticleController::class)->except('index')->parameters(['articles' => 'article']);
        Route::post('articles/{article}/vote', [KbArticleController::class, 'vote'])->name('articles.vote');
    });

    Route::resource('canned-responses', CannedResponseController::class)->except('show');

    // Reports
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/{report}', [ReportController::class, 'export'])->name('reports.export');
    });

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/poll', [NotificationController::class, 'poll'])->name('notifications.poll');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('notifications/{id}', [NotificationController::class, 'read'])->name('notifications.read');

    // Profile
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // Administration
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->except('show');

        Route::get('lookups/{type}', [LookupController::class, 'index'])->name('lookups.index');
        Route::get('lookups/{type}/create', [LookupController::class, 'create'])->name('lookups.create');
        Route::post('lookups/{type}', [LookupController::class, 'store'])->name('lookups.store');
        Route::get('lookups/{type}/{id}/edit', [LookupController::class, 'edit'])->name('lookups.edit');
        Route::put('lookups/{type}/{id}', [LookupController::class, 'update'])->name('lookups.update');
        Route::delete('lookups/{type}/{id}', [LookupController::class, 'destroy'])->name('lookups.destroy');
    });
});
