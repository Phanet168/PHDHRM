<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HealthMessageRequest;
use App\Models\AuditLog;
use App\Models\HealthMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HealthMessageController extends Controller
{
    private const CACHE_KEY = 'health-messages.active';

    public function index(): View
    {
        $this->authorize('viewAny', HealthMessage::class);

        $messages = HealthMessage::orderBy('sort_order')->orderBy('id')->paginate(20);

        return view('admin.health-messages.index', compact('messages'));
    }

    public function create(): View
    {
        $this->authorize('create', HealthMessage::class);

        return view('admin.health-messages.create');
    }

    public function store(HealthMessageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $message = HealthMessage::create($data);
        Cache::forget(self::CACHE_KEY);

        AuditLog::record('created', 'HealthMessage', $message->id, "បង្កើតសារអប់រំសុខភាព៖ {$message->message_km}");

        return redirect()->route('admin.health-messages.index')->with('status', 'បានបង្កើតដោយជោគជ័យ។');
    }

    public function edit(HealthMessage $healthMessage): View
    {
        $this->authorize('update', $healthMessage);

        return view('admin.health-messages.edit', ['message' => $healthMessage]);
    }

    public function update(HealthMessageRequest $request, HealthMessage $healthMessage): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $healthMessage->update($data);
        Cache::forget(self::CACHE_KEY);

        AuditLog::record('updated', 'HealthMessage', $healthMessage->id, "កែប្រែសារអប់រំសុខភាព៖ {$healthMessage->message_km}");

        return redirect()->route('admin.health-messages.index')->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(HealthMessage $healthMessage): RedirectResponse
    {
        $this->authorize('delete', $healthMessage);

        $healthMessage->delete();
        Cache::forget(self::CACHE_KEY);

        AuditLog::record('deleted', 'HealthMessage', $healthMessage->id, "លុបសារអប់រំសុខភាព៖ {$healthMessage->message_km}");

        return redirect()->route('admin.health-messages.index')->with('status', 'បានលុបដោយជោគជ័យ។');
    }
}
