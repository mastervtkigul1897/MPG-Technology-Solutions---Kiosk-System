<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\AppSettings;
use App\Core\Request;
use App\Core\Response;

final class SettingsController
{
    public function edit(Request $request): Response
    {
        $s = AppSettings::all();

        return view_page('Settings', 'super-admin.settings.index', [
            'settings' => $s,
        ]);
    }

    public function update(Request $request): Response
    {
        $appName = trim((string) $request->input('app_name'));
        if (strlen($appName) > 255) {
            session_flash('errors', ['Application name must be 255 characters or less.']);

            return redirect(url('/super-admin/settings'));
        }

        AppSettings::save([
            'app_name' => $appName,
            'maintenance_mode' => $request->boolean('maintenance_mode'),
            'maintenance_message' => (string) $request->input('maintenance_message'),
            'subscription_warning_days' => (int) $request->input('subscription_warning_days'),
        ]);

        session_flash('status', 'Settings saved.');

        return redirect(url('/super-admin/settings'));
    }
}
