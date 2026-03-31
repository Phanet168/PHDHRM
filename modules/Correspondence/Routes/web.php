<?php

use Illuminate\Support\Facades\Route;
use Modules\Correspondence\Http\Controllers\CorrespondenceController;

Route::group(['prefix' => 'correspondence', 'middleware' => ['web', 'auth']], function () {
    Route::get('/', [CorrespondenceController::class, 'index'])->name('correspondence.index');
    Route::get('/incoming', [CorrespondenceController::class, 'incoming'])->name('correspondence.incoming');
    Route::get('/outgoing', [CorrespondenceController::class, 'outgoing'])->name('correspondence.outgoing');
    Route::get('/create/{type}', [CorrespondenceController::class, 'create'])->name('correspondence.create');
    Route::post('/store', [CorrespondenceController::class, 'store'])->name('correspondence.store');
    Route::get('/users/search', [CorrespondenceController::class, 'searchUsers'])->name('correspondence.users.search');
    Route::get('/{letter}', [CorrespondenceController::class, 'show'])->name('correspondence.show');
    Route::post('/{letter}/progress', [CorrespondenceController::class, 'progress'])->name('correspondence.progress');
    Route::post('/{letter}/distribute', [CorrespondenceController::class, 'distribute'])->name('correspondence.distribute');
    Route::post('/{letter}/feedback-parent', [CorrespondenceController::class, 'feedbackParent'])->name('correspondence.feedback_parent');
    Route::post('/distribution/{distribution}/acknowledge', [CorrespondenceController::class, 'acknowledge'])->name('correspondence.acknowledge');
    Route::post('/distribution/{distribution}/feedback', [CorrespondenceController::class, 'feedback'])->name('correspondence.feedback');
});
