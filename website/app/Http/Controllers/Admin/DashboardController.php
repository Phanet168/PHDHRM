<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\HealthContent;
use App\Models\LegalDocument;
use App\Models\News;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'total_facilities' => Facility::count(),
            'referral_hospitals' => Facility::where('facility_type', 'referral_hospital')->count(),
            'health_centers' => Facility::where('facility_type', 'health_center')->count(),
            'health_posts' => Facility::where('facility_type', 'health_post')->count(),
            'published_news' => News::where('status', 'published')->count(),
            'legal_documents' => LegalDocument::count(),
            'pending_approval' => News::where('status', 'pending_review')->count()
                + HealthContent::where('status', 'pending_review')->count(),
        ];

        $recentContent = News::with(['author', 'category'])->latest('created_at')->take(6)->get();
        $pendingReview = News::with(['author', 'facility'])->where('status', 'pending_review')->latest('updated_at')->take(6)->get();
        $recentActivity = AuditLog::with('user')->latest('created_at')->take(10)->get();

        return view('admin.dashboard', compact('stats', 'recentContent', 'pendingReview', 'recentActivity'));
    }
}
