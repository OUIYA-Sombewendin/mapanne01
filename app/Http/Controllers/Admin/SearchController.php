<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Recherche globale de la barre supérieure : prestataires, utilisateurs, produits. */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $digits = preg_replace('/\D/', '', $q);
        $user = $request->user();
        $ready = mb_strlen($q) >= 2;

        return view('admin.search', [
            'q' => $q,
            'providers' => $ready && $user->can('providers.view') ? Provider::with('city:id,name')
                ->where(fn ($w) => $w->where('business_name', 'ilike', $like)->when(strlen($digits) >= 4, fn ($w) => $w->orWhere('phone', 'like', "%$digits%")))
                ->take(10)->get() : collect(),
            'users' => $ready && $user->can('users.view') ? User::where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like)
                ->when(strlen($digits) >= 4, fn ($w) => $w->orWhere('phone', 'like', "%$digits%")))
                ->take(10)->get() : collect(),
            'products' => $ready && $user->can('marketplace.manage') ? Product::with('provider:id,business_name')
                ->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('reference', 'ilike', $like))->take(10)->get() : collect(),
        ]);
    }
}
