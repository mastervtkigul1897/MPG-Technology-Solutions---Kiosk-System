<?php

declare(strict_types=1);

namespace App\Controllers\SuperAdmin;

use App\Core\ActivityLogger;
use App\Core\App;
use App\Core\AppSettings;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class SettingsController
{
    public function edit(Request $request): Response
    {
        $s = AppSettings::all();

        return view_page('Settings', 'super-admin.settings.index', [
            'settings' => $s,
            'can_run_storage_migrations' => $this->canRunStorageMigrations($request),
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

    public function runStorageMigrations(Request $request): Response
    {
        if (! $this->canRunStorageMigrations($request)) {
            session_flash('errors', ['Storage migration runner is disabled on this environment.']);

            return redirect(url('/super-admin/settings'));
        }
        $confirmation = strtoupper(trim((string) $request->input('migration_confirmation', '')));
        if ($confirmation !== 'RUN MIGRATIONS') {
            session_flash('errors', ['Type RUN MIGRATIONS to confirm executing all storage migrations.']);

            return redirect(url('/super-admin/settings'));
        }
        $actor = Auth::user();
        $actorId = (int) ($actor['id'] ?? 0);

        $projectRoot = dirname(__DIR__, 3);
        $scriptPath = $projectRoot.'/scripts/run_storage_migrations.php';
        if (! is_file($scriptPath)) {
            session_flash('errors', ['Migration runner script was not found.']);

            return redirect(url('/super-admin/settings'));
        }

        $phpBinary = defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $command = escapeshellarg($phpBinary).' '.escapeshellarg($scriptPath).' --confirm=RUN_MIGRATIONS 2>&1';
        $outputLines = [];
        $exitCode = 1;
        @exec($command, $outputLines, $exitCode);
        $outputText = trim(implode("\n", $outputLines));
        $hasFailLine = stripos($outputText, 'FAIL') !== false;

        if ($exitCode !== 0 || $hasFailLine) {
            $message = $outputText !== '' ? $outputText : 'Storage migrations failed to execute.';
            ActivityLogger::log(
                null,
                $actorId,
                (string) ($actor['role'] ?? 'super_admin'),
                'settings',
                'run_storage_migrations_failed',
                $request,
                'Storage migrations execution failed.',
                ['exit_code' => $exitCode, 'output_excerpt' => mb_substr($message, 0, 500)]
            );
            session_flash('errors', [mb_substr($message, 0, 2000)]);

            return redirect(url('/super-admin/settings'));
        }

        $summaryLine = '';
        if ($outputLines !== []) {
            $summaryLine = trim((string) end($outputLines));
        }
        if ($summaryLine === '') {
            $summaryLine = 'Storage migrations executed successfully.';
        }
        ActivityLogger::log(
            null,
            $actorId,
            (string) ($actor['role'] ?? 'super_admin'),
            'settings',
            'run_storage_migrations',
            $request,
            'Storage migrations executed from settings.',
            ['exit_code' => $exitCode, 'summary' => mb_substr($summaryLine, 0, 300)]
        );
        session_flash('status', $summaryLine);

        return redirect(url('/super-admin/settings'));
    }

    private function canRunStorageMigrations(Request $request): bool
    {
        return (bool) (App::config('debug') ?? false);
    }
}
