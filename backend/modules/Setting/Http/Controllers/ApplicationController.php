<?php

namespace Modules\Setting\Http\Controllers;

use App\Scopes\Asc;
use App\Models\Appsetting;
use App\Traits\PictureTrait;
use App\Traits\PictureResizeTrait;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Modules\Setting\Entities\Currency;
use Modules\Localize\Entities\Language;
use Illuminate\Support\Facades\Storage;
use Modules\Setting\Entities\Application;
use Modules\Setting\Http\Requests\ApplicationRequest;

class ApplicationController extends Controller
{

    use PictureTrait, PictureResizeTrait;

    public function __construct()
    {
        $this->middleware('permission:read_application')->only('application');
        $this->middleware('permission:update_application')->only(['edit', 'update']);
    }

    public function application()
    {
        $app = Application::first();
        $currencies = Currency::withoutGlobalScopes([Asc::class])->whereStatus(1)->get();
        $langs = Language::orderBy('langname')->get();
        return view('setting::application', [
            'currencies' => $currencies,
            'app' => $app,
            'langs' => $langs,
        ]);
    }

    public function update(ApplicationRequest $request, $id)
    {

        $app = Application::findOrFail($id);

        $old = $app->logo;
        $old_sidebar_logo = $app->sidebar_logo;
        $old_sidebar_collapsed_logo = $app->sidebar_collapsed_logo;
        $old_login_image = $app->old_login_image;
        $oldFavicon = $app->favicon;
        $app->fill($request->except(['logo', 'favicon', 'fixed_date', 'sidebar_logo']));

        if ($request->hasFile('logo')) {
            if ($old) {
                $this->deleteFile($old);
            }
            $request_file = $request->file('logo');
            $name = time() . 'logo.' . $request_file->getClientOriginalExtension();
            $path = Storage::disk('public')->putFileAs('application', $request_file, $name);
            Image::make($request_file)
                ->resize(96, 96, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })
                ->resizeCanvas(96, 96, 'center', false, '#ffffff')
                ->save(public_path('storage/' . $path));
            $app->logo = $path;
        }
        if ($request->hasFile('sidebar_logo')) {
            if ($old_sidebar_logo) {
                $this->deleteFile($old_sidebar_logo);
            }
            $request_file = $request->file('sidebar_logo');
            $name = time() . 'sidebar-logo.' . $request_file->getClientOriginalExtension();
            $path = Storage::disk('public')->putFileAs('application', $request_file, $name);
            Image::make($request_file)->resize(250, 80)->save(public_path('storage/' . $path));
            $app->sidebar_logo = $path;
        }

        if ($request->hasFile('sidebar_collapsed_logo')) {
            if ($old_sidebar_collapsed_logo) {
                $this->deleteFile($old_sidebar_collapsed_logo);
            }
            $request_file = $request->file('sidebar_collapsed_logo');
            $name = time() . 'sidebar-collapsed-logo.' . $request_file->getClientOriginalExtension();
            $path = Storage::disk('public')->putFileAs('application', $request_file, $name);
            Image::make($request_file)->resize(37, 37)->save(public_path('storage/' . $path));
            $app->sidebar_collapsed_logo = $path;
        }
        if ($request->hasFile('login_image')) {
            if ($old_login_image) {
                $this->deleteFile($old_login_image);
            }
            $request_file = $request->file('login_image');
            $name = time() . 'login-image.' . $request_file->getClientOriginalExtension();
            $path = Storage::disk('public')->putFileAs('application', $request_file, $name);
            Image::make($request_file)->save(public_path('storage/' . $path));
            $app->login_image = $path;
        }

        if ($request->hasFile('favicon')) {
            if ($oldFavicon) {
                $this->deleteFile($oldFavicon);
            }
            $request_file = $request->file('favicon');
            $name = time() . 'favicon.' . $request_file->getClientOriginalExtension();
            $path = Storage::disk('public')->putFileAs('application', $request_file, $name);
            Image::make($request_file)->resize(60, 60)->save(public_path('storage/' . $path));
            $app->favicon = $path;
        }
        $app->fixed_date = $request->fixed_date;
        $app->update();

        cache()->forget('appSetting');
        cache()->forever('appSetting', $app);

        return redirect()->back()->with('success', localize('application_updated'));
    }

    public function appSetting()
    {
        $app = Appsetting::first();
        return view('setting::app_setting', compact('app'));
    }

    public function updateAppSetting(Request $request)
    {
        $validated = $request->validate([
            'latitude' => ['nullable', 'string', 'max:191'],
            'longitude' => ['nullable', 'string', 'max:191'],
            'acceptablerange' => ['nullable', 'string', 'max:191'],
            'googleapi_authkey' => ['nullable', 'string'],
            'otp_required' => ['nullable', 'in:0,1'],
            'otp_channel' => ['nullable', 'in:log,telegram,sms_http'],
        ]);

        $validated['otp_required'] = (int) ($request->input('otp_required', 0) == 1);

        if ($validated['otp_required'] === 0) {
            // Keep channel nullable when OTP is disabled.
            $validated['otp_channel'] = null;
        } else {
            $validated['otp_channel'] = $validated['otp_channel'] ?? 'telegram';
        }

        Appsetting::updateOrCreate(
            ['id' => 1],
            $validated
        );

        cache()->forget('otp_setting');

        Toastr::success(localize('app_setting_updated_successfully'));
        return redirect()->route('app.index');
    }
}
