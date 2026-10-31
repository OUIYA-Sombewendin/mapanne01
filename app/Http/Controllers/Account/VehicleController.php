<?php

namespace App\Http\Controllers\Account;

use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->user()->vehicles()->create($this->validated($request));

        return back()->with('success', 'Véhicule ajouté.');
    }

    public function destroy(Request $request, Vehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->user_id === $request->user()->id, 403);
        $vehicle->delete();

        return back()->with('success', 'Véhicule supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::enum(VehicleType::class)],
            'brand' => ['required', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'between:1960,'.(now()->year + 1)],
            'plate' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
