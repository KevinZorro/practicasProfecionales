<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cerrar sesión. No es parte del acceso de desarrollo: seguirá igual cuando
 * la entrada sea por Google.
 */
final class SalirController extends Controller
{
    public function __invoke(Request $peticion): RedirectResponse
    {
        Auth::logout();
        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        return redirect('/');
    }
}
