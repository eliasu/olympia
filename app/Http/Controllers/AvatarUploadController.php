<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Statamic\Facades\User;

class AvatarUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|max:1024',
        ]);

        $user = User::current();

        // Store to the Statamic 'assets' disk (public/assets), which is the
        // container the avatar blueprint field points to. Statamic expects the
        // relative path (e.g. "profile-pictures/photo.jpg"), not a full URL.
        $path = $request->file('avatar')->store('profile-pictures', 'assets');

        // Save the relative path — this matches how Statamic's Assets fieldtype
        // writes values to the user YAML (e.g. avatar: profile-pictures/photo.jpg)
        $user->data()->put('avatar', $path);
        $user->save();

        // Build the public URL for the live JS preview
        $url = Storage::disk('assets')->url($path);

        return response()->json(['success' => true, 'url' => $url]);
    }
}
