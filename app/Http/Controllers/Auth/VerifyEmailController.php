<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
        );

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if ($request->user() !== null) {
            return redirect()->to(route('dashboard', absolute: false).'?verified=1')->with('toast', [
                'type' => 'success',
                'message' => 'Email address verified successfully.',
            ]);
        }

        return redirect()->route('login')->with(
            'status',
            'Email address verified successfully. You can now sign in.',
        );
    }
}
