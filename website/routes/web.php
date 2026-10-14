<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\HealthContentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaderRecommendationController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\VisitorStatisticsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
Route::get('/facilities/{facility}', [FacilityController::class, 'show'])->name('facilities.show');

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{news}', [NewsController::class, 'show'])->name('news.show');
Route::get('/visitor-statistics', VisitorStatisticsController::class)
    ->middleware('throttle:60,1')->name('visitor-statistics');

Route::get('/legal-documents', [LegalDocumentController::class, 'index'])->name('legal-documents.index');
Route::get('/legal-documents/{legalDocument}/download', [LegalDocumentController::class, 'download'])->name('legal-documents.download');

Route::get('/health-content', [HealthContentController::class, 'index'])->name('health-content.index');
Route::get('/health-content/{healthContent}', [HealthContentController::class, 'show'])->name('health-content.show');

Route::get('/about', [AboutController::class, 'index'])->name('about');

Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::get('/pages/{page}', [PageController::class, 'show'])->name('pages.show');

Route::get('/leader-recommendations/{leaderRecommendation}', [LeaderRecommendationController::class, 'show'])->name('leader-recommendations.show');

Route::get('/category/{category}', [CategoryController::class, 'show'])->name('category.show');
Route::get('/content/{content}', [ContentController::class, 'show'])->name('content.show');
