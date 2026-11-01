<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\PartCategory;
use App\Models\Product;
use App\Support\PriceWatch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Observatoire des prix : ce que coûtent les pièces chez les boutiques inscrites (min, médian, max). */
class PriceWatchController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'city', 'vehicle', 'condition', 'category']);

        return view('admin.price-watch', [
            'summary' => PriceWatch::summary($filters),
            'categories' => PriceWatch::byCategory($filters),
            'parts' => PriceWatch::byPart($filters),
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'partCategories' => PartCategory::tree(),
            'vehicles' => VehicleType::options(),
            'conditions' => Product::CONDITIONS,
        ]);
    }
}
