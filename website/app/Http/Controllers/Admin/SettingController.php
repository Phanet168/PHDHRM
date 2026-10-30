<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\MediaUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    private const KEYS = [
        'org_name_km', 'org_name_en', 'logo', 'favicon', 'phone', 'email', 'address',
        'office_hours', 'facebook_url', 'telegram_url', 'youtube_url',
        'seo_title', 'seo_description', 'hrm_url', 'drugs_url', 'loading_text_km',
    ];

    /** A full address, or one on this server such as /login (the HRM and drugs systems sit beside the website). */
    private const SYSTEM_URL = ['nullable', 'string', 'max:255', 'regex:#^(https?://\S+|/\S*)$#'];

    public function edit(): View
    {
        $this->authorize('settings.manage');

        $settings = Setting::allSettings();

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('settings.manage');

        $data = $request->validate([
            'org_name_km' => ['required', 'string', 'max:255'],
            'org_name_en' => ['nullable', 'string', 'max:255'],
            'loading_text_km' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'favicon' => ['nullable', 'image', 'max:512'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'office_hours' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'telegram_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'hrm_url' => self::SYSTEM_URL,
            'drugs_url' => self::SYSTEM_URL,
        ], [
            'hrm_url.regex' => 'សូមបញ្ចូលអាសយដ្ឋានពេញ (https://...) ឬផ្លូវលើ server នេះ (ឧ. /login)',
            'drugs_url.regex' => 'សូមបញ្ចូលអាសយដ្ឋានពេញ (https://...) ឬផ្លូវលើ server នេះ (ឧ. /drugs/login)',
        ]);

        foreach (self::KEYS as $key) {
            if (in_array($key, ['logo', 'favicon'], true)) {
                continue;
            }
            Setting::set($key, $data[$key] ?? null);
        }

        if ($request->hasFile('logo')) {
            Setting::set('logo', MediaUploader::store($request->file('logo'), 'settings'));
        }
        if ($request->hasFile('favicon')) {
            Setting::set('favicon', MediaUploader::store($request->file('favicon'), 'settings'));
        }

        AuditLog::record('updated', 'Setting', null, 'បានធ្វើបច្ចុប្បន្នភាពការកំណត់ប្រព័ន្ធ');

        return redirect()->route('admin.settings.edit')->with('status', 'បានរក្សាទុកការកំណត់ដោយជោគជ័យ។');
    }
}
