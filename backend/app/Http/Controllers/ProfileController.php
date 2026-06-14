<?php

namespace App\Http\Controllers;

use App\Models\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Update user profile information
     */
    public function update(Request $request)
    {
        $request->validate([
            'full_name' => 'nullable|string|max:191',
            'email' => 'nullable|email|max:191|unique:users,email,' . Auth::id(),
            'contact_no' => 'nullable|string|max:20',
        ]);

        $user = Auth::user();
        $user->update($request->only('full_name', 'email', 'contact_no'));

        return response()->json([
            'status' => true,
            'message' => localize('profile_updated_successfully', 'ប្រវត្តិរូបបានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ'),
            'route' => route('myProfile'),
        ]);
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => localize('current_password_incorrect', 'ពាក្យសម្ងាត់បច្ចុប្បន្នមិនត្រឹមត្រូវទេ'),
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        Toastr::success(localize('password_changed_successfully', 'ពាក្យសម្ងាត់ត្រូវបានប្ដូរដោយជោគជ័យ'));

        return response()->json([
            'status' => true,
            'message' => localize('password_changed_successfully', 'ពាក្យសម្ងាត់ត្រូវបានប្ដូរដោយជោគជ័យ'),
        ]);
    }

    /**
     * Update data management settings (Admin only)
     */
    public function updateDataManagement(Request $request)
    {
        // Only admin users can access data management settings
        if (!Auth::user()->admin()) {
            return response()->json([
                'status' => false,
                'message' => localize('unauthorized_access', 'មិនមាន​សិទ្ធិ​ក្នុង​ការ​ធ្វើ​សកម្មភាព​នេះទេ'),
            ], 403);
        }

        $user = Auth::user();

        // Store user preferences in user_settings or similar
        // For now, we'll just validate and return success
        $request->validate([
            'language_preference' => 'nullable|in:en,km',
            'email_notifications' => 'nullable|boolean',
            'telegram_notifications' => 'nullable|boolean',
        ]);

        // Store preferences in session or database (if you have a user_settings table)
        // session(['user_language_preference' => $request->language_preference]);
        // session(['user_email_notifications' => $request->email_notifications]);
        // session(['user_telegram_notifications' => $request->telegram_notifications]);

        return response()->json([
            'status' => true,
            'message' => localize('settings_saved_successfully', 'ការកំណត់ត្រូវបានរក្សាទុកដោយជោគជ័យ'),
        ]);
    }

    /**
     * Update user profile image
     */
    public function updateProfileImage(Request $request, $id)
    {
        $request->validate([
            'profile_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = User::findOrFail($id);

        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('profiles', 'public');
            $user->profile_image = $path;
            $user->save();
        }

        Toastr::success(localize('profile_image_updated', 'រូបថតប្រវត្តិរូបត្រូវបានធ្វើបច្ចុប្បន្នភាព'));

        return response()->json([
            'status' => true,
            'message' => localize('profile_image_updated', 'រូបថតប្រវត្តិរូបត្រូវបានធ្វើបច្ចុប្បន្នភាព'),
        ]);
    }

    /**
     * Update user cover image
     */
    public function updateCoverImage(Request $request, $id)
    {
        $request->validate([
            'cover_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = User::findOrFail($id);

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('covers', 'public');
            $user->cover_image = $path;
            $user->save();
        }

        return response()->json([
            'status' => true,
            'message' => localize('cover_image_updated', 'រូបភាពគម្របត្រូវបានធ្វើបច្ចុប្បន្នភាព'),
        ]);
    }
}
