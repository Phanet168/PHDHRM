<?php

use Illuminate\Support\Facades\Route;
use Modules\Correspondence\Http\Controllers\CorrespondenceController;
use Modules\Correspondence\Http\Controllers\CorrespondenceHelpController;

Route::group(['prefix' => 'correspondence', 'middleware' => ['web', 'auth']], function () {
    Route::get('/help/{article?}', [CorrespondenceHelpController::class, 'index'])->name('correspondence.help');
    Route::get('/', [CorrespondenceController::class, 'index'])->name('correspondence.index');
    Route::get('/incoming', [CorrespondenceController::class, 'incoming'])->name('correspondence.incoming');
    Route::get('/outgoing', [CorrespondenceController::class, 'outgoing'])->name('correspondence.outgoing');
    Route::get('/create/{type}', [CorrespondenceController::class, 'create'])->name('correspondence.create');
    Route::get('/settings', [CorrespondenceController::class, 'settings'])->name('correspondence.settings');
    Route::post('/settings/assign', [CorrespondenceController::class, 'assignSettingsUser'])
        ->name('correspondence.settings.assign');
    Route::post('/settings/assignment/{assignment}/delete', [CorrespondenceController::class, 'removeSettingsUser'])
        ->whereNumber('assignment')
        ->name('correspondence.settings.assignment.delete');
    Route::post('/store', [CorrespondenceController::class, 'store'])->name('correspondence.store');
    Route::get('/users/search', [CorrespondenceController::class, 'searchUsers'])->name('correspondence.users.search');
    Route::get('/{letter}/print', [CorrespondenceController::class, 'print'])->name('correspondence.print');
    Route::get('/{letter}/attachments/{index}/preview', [CorrespondenceController::class, 'previewAttachment'])
        ->whereNumber('index')
        ->name('correspondence.attachments.preview');
    Route::get('/{letter}/attachments/{index}/file', [CorrespondenceController::class, 'attachmentFile'])
        ->whereNumber('index')
        ->name('correspondence.attachments.file');
    Route::get('/notifications/open/{notification}', [CorrespondenceController::class, 'openNotification'])
        ->name('correspondence.notifications.open');
    Route::post('/notifications/clear', [CorrespondenceController::class, 'clearNotifications'])
        ->name('correspondence.notifications.clear');
    Route::get('/{letter}', [CorrespondenceController::class, 'show'])->name('correspondence.show');
    Route::post('/{letter}/progress', [CorrespondenceController::class, 'progress'])->name('correspondence.progress');
    Route::post('/{letter}/distribute', [CorrespondenceController::class, 'distribute'])->name('correspondence.distribute');
    Route::post('/notifications/mark-read', [CorrespondenceController::class, 'markNotificationsRead'])->name('correspondence.notifications.read');
    Route::post('/{letter}/feedback-parent', [CorrespondenceController::class, 'feedbackParent'])->name('correspondence.feedback_parent');
    Route::post('/distribution/{distribution}/acknowledge', [CorrespondenceController::class, 'acknowledge'])->name('correspondence.acknowledge');
    Route::post('/distribution/{distribution}/feedback', [CorrespondenceController::class, 'feedback'])->name('correspondence.feedback');
});

Route::get('/correspondence/attachments/signed/{letter}/{index}/file', [CorrespondenceController::class, 'attachmentFileSigned'])
    ->middleware(['web', 'signed'])
    ->whereNumber('index')
    ->name('correspondence.attachments.file.signed');
