<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\City;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Référentiels gérés par l'administration : services et zones géographiques.
 */
class SettingsController extends Controller
{
    public function services(): View
    {
        return view('admin.services', [
            'categories' => ServiceCategory::with(['services' => fn ($q) => $q->withCount('providers')])->orderBy('position')->get(),
        ]);
    }

    public function storeService(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'name' => ['required', 'string', 'max:80'],
        ]);

        $slug = Str::slug($data['name']);
        if (Service::where('slug', $slug)->exists()) {
            return back()->withErrors(['name' => 'Ce service existe déjà.']);
        }

        $service = Service::create($data + ['slug' => $slug]);
        ActivityLog::record('service.created', $service);

        return back()->with('success', 'Service ajouté.');
    }

    public function toggleService(Service $service): RedirectResponse
    {
        $service->update(['is_active' => ! $service->is_active]);

        return back()->with('success', $service->is_active ? 'Service activé.' : 'Service désactivé (masqué des formulaires).');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('service_categories', 'name')],
            'icon' => ['nullable', 'string', 'max:16'],
        ]);

        ServiceCategory::create($data + [
            'slug' => Str::slug($data['name']),
            'position' => (int) ServiceCategory::max('position') + 1,
        ]);

        return back()->with('success', 'Catégorie ajoutée.');
    }

    public function locations(): View
    {
        return view('admin.locations', [
            'cities' => City::withCount('neighborhoods')->with('neighborhoods')->orderBy('name')->get(),
        ]);
    }

    public function storeCity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('cities', 'name')],
            'latitude' => ['required', 'numeric', 'between:9,15.5'],
            'longitude' => ['required', 'numeric', 'between:-6,2.5'],
        ]);

        City::create($data + ['slug' => Str::slug($data['name'])]);

        return back()->with('success', 'Ville ajoutée.');
    }

    public function storeNeighborhood(Request $request, City $city): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('neighborhoods', 'name')->where('city_id', $city->id)],
            'sector' => ['nullable', 'string', 'max:40'],
            'latitude' => ['required', 'numeric', 'between:9,15.5'],
            'longitude' => ['required', 'numeric', 'between:-6,2.5'],
        ]);

        $city->neighborhoods()->create($data);

        return back()->with('success', 'Quartier ajouté.');
    }

    public function toggleCity(City $city): RedirectResponse
    {
        $city->update(['is_active' => ! $city->is_active]);

        return back()->with('success', $city->is_active ? 'Ville activée.' : 'Ville désactivée.');
    }
}
