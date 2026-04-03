<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;

final class HomeController
{
    public function welcome(Request $request): Response
    {
        return view_guest('Welcome', 'welcome', [
            'canLogin' => true,
            'canRegister' => false,
        ]);
    }
}
