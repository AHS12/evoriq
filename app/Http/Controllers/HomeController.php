<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Show the public landing page, or send authenticated users to the app.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user() !== null) {
            return to_route('dashboard');
        }

        return Inertia::render('welcome');
    }
}
