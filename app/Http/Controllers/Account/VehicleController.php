<?php

namespace App\Http\Controllers\Account;

use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Support\MaintenanceTasks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $vehicle = $request->user()->vehicles()->create($this->validated($request));

        if (feature('logbook_enabled')) {
            return redirect()->route('logbook.show', $vehicle)->with('success', 'Véhicule ajouté. Cochez les rappels qui vous intéressent.');
        }

        return back()->with('success', 'Véhicule ajouté.');
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->user_id === $request->user()->id, 403);
        $vehicle->update($this->validated($request));
        // Rappels qui ne concernent plus ce véhicule (ex. vidange après passage à l'électrique).
        $vehicle->reminders()->whereNotIn('task', array_keys($vehicle->tasks()))->delete();

        return back()->with('success', 'Véhicule modifié.');
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
            'energy' => ['required', Rule::in(array_keys(MaintenanceTasks::energies()))],
            'brand' => ['required', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'between:1960,'.(now()->year + 1)],
            'plate' => ['nullable', 'string', 'max:20'],
        ]);
    }
}
