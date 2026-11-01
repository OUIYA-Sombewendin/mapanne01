<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChargePoint;
use App\Models\City;
use App\Rules\BurkinaPhone;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Points de recharge et d'échange de batterie affichés sur la page électrique. */
class ChargePointController extends Controller
{
    public function index(Request $request): View
    {
        $edit = $request->query('modifier') ? ChargePoint::find($request->query('modifier')) : null;

        return view('admin.charge-points', [
            'points' => ChargePoint::with('city:id,name')->orderByDesc('is_active')->orderBy('name')->get(),
            'edit' => $edit,
            'cities' => City::where('is_active', true)->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']),
            'kinds' => ChargePoint::KINDS,
            'vehicles' => VehicleType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $point = ChargePoint::create($this->validated($request));
        ActivityLog::record('charge_point.created', $point, ['nom' => $point->name]);

        return redirect()->route('admin.charge-points.index')->with('success', 'Point ajouté.');
    }

    public function update(Request $request, ChargePoint $point): RedirectResponse
    {
        $point->update($this->validated($request));
        ActivityLog::record('charge_point.updated', $point, ['nom' => $point->name]);

        return redirect()->route('admin.charge-points.index')->with('success', 'Point modifié.');
    }

    public function destroy(ChargePoint $point): RedirectResponse
    {
        ActivityLog::record('charge_point.deleted', $point, ['nom' => $point->name]);
        $point->delete();

        return back()->with('success', 'Point supprimé.');
    }

    private function validated(Request $request): array
    {
        $request->merge(['phone' => $request->filled('phone') ? (Phone::normalize($request->input('phone')) ?? $request->input('phone')) : null]);

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(array_keys(ChargePoint::KINDS))],
            'city_id' => ['nullable', 'exists:cities,id'],
            'address' => ['nullable', 'string', 'max:200'],
            'latitude' => ['required', 'numeric', 'between:9,15.5'],
            'longitude' => ['required', 'numeric', 'between:-6,2.6'],
            'phone' => ['nullable', new BurkinaPhone],
            'vehicles' => ['nullable', 'array'],
            'vehicles.*' => [Rule::in(array_keys(VehicleType::options()))],
            'hours' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:300'],
            'is_active' => ['boolean'],
        ], [
            'latitude.required' => 'Placez le point sur la carte.',
            'longitude.required' => 'Placez le point sur la carte.',
        ]) + ['is_active' => false, 'vehicles' => []];
    }
}
