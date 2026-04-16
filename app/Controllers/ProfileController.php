<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class ProfileController
{
    public function edit(Request $request): Response
    {
        $user = Auth::user();

        return view_page('Profile', 'profile.edit', [
            'user' => $user,
        ]);
    }

    public function update(Request $request): Response
    {
        session_flash('errors', ['Profile update is disabled. Use Receipt Config menu for receipt details or change password below.']);

        return redirect(url('/profile'));
    }

    public function destroy(Request $request): Response
    {
        session_flash('errors', ['Account deletion is disabled. You can only change your password.']);

        return redirect(url('/profile'));
    }

    public function updatePassword(Request $request): Response
    {
        $user = Auth::user();
        if (! $user) {
            return redirect(url('/login'));
        }

        $current = (string) $request->input('current_password');
        $new = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');

        if (! password_verify($current, (string) $user['password'])) {
            session_flash('errors', ['Current password is incorrect.']);

            return redirect(url('/profile'));
        }
        if (strlen($new) < 8) {
            session_flash('errors', ['New password must be at least 8 characters.']);

            return redirect(url('/profile'));
        }
        if ($new !== $confirm) {
            session_flash('errors', ['Password confirmation does not match.']);

            return redirect(url('/profile'));
        }

        $hash = password_hash($new, PASSWORD_BCRYPT);
        $pdo = App::db();
        $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?')->execute([$hash, $user['id']]);

        session_flash('status', 'Password updated.');

        return redirect(url('/profile'));
    }
}
