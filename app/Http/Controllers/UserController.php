<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        Log::info('[Profile] request received', [
            'user_id'    => $user->id,
            'has_avatar' => $request->hasFile('avatar'),
            'files'      => array_keys($request->allFiles()),
            'input_keys' => array_keys($request->all()),
        ]);

        $validated = $request->validate([
            'name'     => 'nullable|string|max:255',
            'username' => 'nullable|string|max:50|unique:users,username,' . $user->id,
            'bio'      => 'nullable|string|max:300',
            'weight'   => 'nullable|numeric|min:1|max:500',
            'height'   => 'nullable|numeric|min:1|max:300',
            // 'file' instead of 'image' — React Native sometimes sends
            // a content-type that fools PHP's MIME detection
            'avatar'   => 'nullable|file|max:5120',
        ]);

        $data = [];

        if ($request->has('name'))     $data['name']     = $request->name;
        if ($request->has('username')) $data['username'] = $request->username;
        if ($request->has('bio'))      $data['bio']      = $request->bio;
        if ($request->has('weight') && $request->weight !== null) $data['weight'] = (int) $request->weight;
        if ($request->has('height') && $request->height !== null) $data['height'] = (int) $request->height;

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            Log::info('[Profile] avatar file received', [
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getClientMimeType(),
                'size'          => $file->getSize(),
                'is_valid'      => $file->isValid(),
            ]);

            if ($file->isValid()) {
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $path = $file->store('avatars', 'public');
                $data['avatar'] = $path;
                Log::info('[Profile] avatar stored at: ' . $path);
            } else {
                Log::warning('[Profile] avatar file invalid, error: ' . $file->getError());
            }
        }

        if (! empty($data)) {
            // Use fill + save to bypass any fillable caching issues
            $user->fill($data)->save();
        }

        $fresh = $user->fresh();
        Log::info('[Profile] saved, avatar=' . $fresh->avatar);

        return response()->json(['user' => $fresh]);
    }
}
