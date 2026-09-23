<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Throwable;

class MailSettingController extends Controller
{
    /**
     * Send a test email using the currently configured mail settings.
     */
    public function test(Request $request): RedirectResponse
    {
        $user = $request->user();

        try {
            Mail::to($user?->email)->queue(new TestMail);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Test email queued for :email.', ['email' => $user?->email]),
            ]);
        } catch (Throwable $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Could not queue test email: :error', ['error' => $e->getMessage()]),
            ]);
        }

        return back();
    }
}
