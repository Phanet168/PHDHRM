<?php

use Illuminate\Support\Facades\Route;
use Modules\Correspondence\Http\Controllers\CorrespondenceController;
use Modules\Correspondence\Http\Controllers\CorrespondenceHelpController;

Route::group(['prefix' => 'correspondence', 'middleware' => ['web', 'auth']], function () {
    Route::get('/help/{article?}', [CorrespondenceHelpController::class, 'index'])->middleware('permission:read_correspondence_management')->name('correspondence.help');
    Route::get('/', [CorrespondenceController::class, 'index'])->middleware('permission:read_correspondence_management')->name('correspondence.index');
    Route::get('/incoming', [CorrespondenceController::class, 'incoming'])->middleware('permission:read_correspondence_management')->name('correspondence.incoming');
    Route::get('/outgoing', [CorrespondenceController::class, 'outgoing'])->middleware('permission:read_correspondence_management')->name('correspondence.outgoing');
    Route::get('/create/{type}', [CorrespondenceController::class, 'create'])->middleware('permission:create_correspondence_management')->name('correspondence.create');
    Route::post('/store', [CorrespondenceController::class, 'store'])->middleware('permission:create_correspondence_management')->name('correspondence.store');
    Route::get('/users/search', [CorrespondenceController::class, 'searchUsers'])->name('correspondence.users.search');
    Route::get('/{letter}/print', [CorrespondenceController::class, 'print'])->name('correspondence.print');
    Route::get('/{letter}/attachments/{index}/preview', [CorrespondenceController::class, 'previewAttachment'])
        ->whereNumber('index')
        ->name('correspondence.attachments.preview');
    Route::get('/notifications/open/{notification}', [CorrespondenceController::class, 'openNotification'])
        ->name('correspondence.notifications.open');
    Route::get('/{letter}', [CorrespondenceController::class, 'show'])->name('correspondence.show');
    Route::post('/{letter}/progress', [CorrespondenceController::class, 'progress'])->name('correspondence.progress');
    Route::post('/{letter}/distribute', [CorrespondenceController::class, 'distribute'])->name('correspondence.distribute');
    Route::post('/notifications/mark-read', [CorrespondenceController::class, 'markNotificationsRead'])->name('correspondence.notifications.read');
    Route::post('/{letter}/feedback-parent', [CorrespondenceController::class, 'feedbackParent'])->name('correspondence.feedback_parent');
    Route::post('/distribution/{distribution}/acknowledge', [CorrespondenceController::class, 'acknowledge'])->name('correspondence.acknowledge');
    Route::post('/distribution/{distribution}/feedback', [CorrespondenceController::class, 'feedback'])->name('correspondence.feedback');
});
