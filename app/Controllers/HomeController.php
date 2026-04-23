<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class HomeController
{
    public function welcome(Request $request): Response
    {
        return response_view('welcome', [
            'title' => 'Laundry Management System',
        ]);
    }

    public function installApp(Request $request): Response
    {
        return response_view('install-app', [
            'title' => 'Install App',
        ]);
    }

    public function demoVideo(Request $request): Response
    {
        return response_view('demo-video', [
            'title' => 'Demo Video',
        ]);
    }

    public function pricing(Request $request): Response
    {
        return view_guest('Pricing', 'pricing');
    }

    /** Pricing page inside the authenticated app shell (trial / upsell CTAs). */
    public function tenantPlans(Request $request): Response
    {
        if (! Auth::user()) {
            return redirect(url('/login'));
        }

        return view_page('Plans & pricing', 'pricing', ['pricing_in_app' => true]);
    }
}
