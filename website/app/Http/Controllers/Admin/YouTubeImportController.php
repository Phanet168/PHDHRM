<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\HealthContent;
use App\Services\YouTubeImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class YouTubeImportController extends Controller
{
    public function __construct(private YouTubeImportService $importService)
    {
    }

    public function create(): View
    {
        $this->authorize('import', HealthContent::class);

        return view('admin.health-content.youtube-import');
    }

    /**
     * Channel preview (Tab 2, step 1): fetches the channel's latest videos
     * and annotates already-imported ones — does not create anything.
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('import', HealthContent::class);

        $request->validate([
            'channel_url' => ['required', 'string', 'max:255'],
            'limit' => ['required', 'integer', 'in:10,20,30,50'],
        ]);

        try {
            $videos = $this->importService->previewChannelVideos($request->input('channel_url'), (int) $request->input('limit'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['videos' => $videos]);
    }

    /**
     * Shared by both the paste-a-URL tab (normal browser form post) and the
     * channel-selection tab's sequential JS import loop (fetch with
     * Accept: application/json) — one endpoint, two response shapes.
     */
    public function import(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('import', HealthContent::class);

        $request->validate(['url_or_id' => ['required', 'string', 'max:2048']]);

        try {
            $result = $this->importService->importByUrlOrId($request->input('url_or_id'));
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['url_or_id' => $e->getMessage()])->withInput();
        }

        $content = $result['content'];
        $editUrl = route('admin.health-content.edit', $content);

        if ($result['duplicate']) {
            $message = 'វីដេអូនេះមានរួចហើយ។';

            if ($request->wantsJson()) {
                return response()->json(['duplicate' => true, 'message' => $message, 'edit_url' => $editUrl], 200);
            }

            return redirect($editUrl)->with('status', $message);
        }

        AuditLog::record('created', 'HealthContent', $content->id, "នាំចូលវីដេអូពី YouTube៖ {$content->title_km}");

        if ($request->wantsJson()) {
            return response()->json(['duplicate' => false, 'message' => 'បាននាំចូលដោយជោគជ័យ។', 'edit_url' => $editUrl], 201);
        }

        return redirect($editUrl)->with('status', 'បាននាំចូលវីដេអូដោយជោគជ័យ។ សូមពិនិត្យ និងបំពេញព័ត៌មានមុននឹងផ្សាយ។');
    }

    public function refreshMetadata(HealthContent $healthContent): RedirectResponse
    {
        $this->authorize('update', $healthContent);

        try {
            $this->importService->refreshMetadata($healthContent);
        } catch (RuntimeException $e) {
            return back()->withErrors(['metadata' => $e->getMessage()]);
        }

        return back()->with('status', 'បានធ្វើបច្ចុប្បន្នភាព Metadata ដោយជោគជ័យ។');
    }

    public function saveThumbnail(HealthContent $healthContent): RedirectResponse
    {
        $this->authorize('update', $healthContent);

        try {
            $this->importService->saveThumbnailToLibrary($healthContent);
        } catch (RuntimeException $e) {
            return back()->withErrors(['thumbnail' => $e->getMessage()]);
        }

        return back()->with('status', 'បានរក្សារូបតំណាងក្នុង Media Library ដោយជោគជ័យ។');
    }
}
