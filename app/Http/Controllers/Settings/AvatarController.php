<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    /**
     * Profile pictures live on the private disk and are served through this
     * app (to signed-in users only) rather than a public storage symlink.
     */
    private const DISK = 'local';

    /**
     * Replace the signed-in user's profile picture.
     */
    public function update(AvatarUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        /** @var UploadedFile $file */
        $file = $request->file('avatar');
        $previous = $user->avatar_path;

        $user->forceFill(['avatar_path' => $file->store("avatars/{$user->id}", self::DISK)])->save();
        if ($previous !== null) {
            Storage::disk(self::DISK)->delete($previous);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile picture updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Remove the signed-in user's profile picture.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->avatar_path !== null) {
            Storage::disk(self::DISK)->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile picture removed.')]);

        return to_route('profile.edit');
    }

    /**
     * Stream a user's profile picture.
     */
    public function show(User $user): StreamedResponse
    {
        abort_if($user->avatar_path === null || ! Storage::disk(self::DISK)->exists($user->avatar_path), 404);

        return Storage::disk(self::DISK)->response($user->avatar_path, null, [
            'Cache-Control' => 'private, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
