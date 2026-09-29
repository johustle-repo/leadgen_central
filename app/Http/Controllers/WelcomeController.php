<?php

namespace App\Http\Controllers;

use App\AccountStatus;
use App\Services\WelcomeOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    /**
     * Signed-in, active users see their live lead overview; guests see the sample preview.
     */
    public function __invoke(Request $request, WelcomeOverview $overview): Response
    {
        $user = $request->user();
        $canSeeLeads = $user !== null && $user->status === AccountStatus::Active && $user->hasVerifiedEmail();

        return Inertia::render('welcome', [
            'overview' => $canSeeLeads ? $overview->for($user) : null,
        ]);
    }
}
