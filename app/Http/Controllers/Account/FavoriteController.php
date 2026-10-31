<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request, Provider $provider): RedirectResponse
    {
        abort_unless($provider->isVisible(), 404);

        $result = $request->user()->favorites()->toggle([$provider->id => ['created_at' => now()]]);

        return back()->with('success', $result['attached'] ? 'Ajouté à vos favoris.' : 'Retiré de vos favoris.');
    }
}
