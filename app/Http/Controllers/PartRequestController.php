<?php

namespace App\Http\Controllers;

use App\Enums\VehicleType;
use App\Models\City;
use App\Models\PartCategory;
use App\Models\PartRequest;
use App\Rules\BurkinaPhone;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Côté client : « Je ne trouve pas ma pièce » → la demande est diffusée aux boutiques
 * de la ville, qui répondent par une offre. Option activable dans l'admin.
 */
class PartRequestController extends Controller
{
    public function index(Request $request): View
    {
        return view('part-requests.index', [
            'requests' => $request->user()->partRequests()->withCount('offers')->with('city:id,name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('part-requests.create', [
            'categories' => PartCategory::orderBy('position')->get(),
            'cities' => City::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'vehicleTypes' => VehicleType::options(),
            'vehicles' => $request->user()->vehicles,
            'prefill' => ['title' => (string) $request->query('q')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['contact_phone' => $request->filled('contact_phone')
            ? (Phone::normalize($request->input('contact_phone')) ?? $request->input('contact_phone'))
            : null]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'details' => ['nullable', 'string', 'max:1000'],
            'part_category_id' => ['nullable', 'exists:part_categories,id'],
            'city_id' => ['required', 'exists:cities,id'],
            'vehicle' => ['nullable', 'string', 'max:120'],
            'vehicle_type' => ['nullable', Rule::enum(VehicleType::class)],
            // Les boutiques doivent pouvoir rappeler le client.
            'contact_phone' => [$user->phone ? 'nullable' : 'required', new BurkinaPhone],
            'photo' => ['nullable', 'image', 'max:'.(int) setting('max_image_kb')],
        ], ['contact_phone.required' => 'Indiquez un numéro pour que les boutiques puissent vous rappeler.']);

        $key = 'part-request:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, max(1, (int) setting('part_requests_per_day')))) {
            return back()->withInput()->withErrors(['title' => 'Vous avez publié beaucoup de demandes aujourd\'hui. Réessayez demain.']);
        }
        RateLimiter::hit($key, 86400);

        $partRequest = $user->partRequests()->make(collect($data)->except('photo')->all());
        $partRequest->expires_at = now()->addDays(max(1, (int) setting('part_request_days')));
        if ($request->hasFile('photo')) {
            $partRequest->photo_path = $request->file('photo')->store('requests', 'public');
        }
        $partRequest->save();

        return redirect()->route('part-requests.show', $partRequest)
            ->with('success', 'Demande publiée ! Les boutiques de votre ville peuvent maintenant vous faire une offre.');
    }

    public function show(Request $request, PartRequest $partRequest): View
    {
        abort_unless($partRequest->user_id === $request->user()->id || $request->user()->isAdmin(), 404);

        return view('part-requests.show', [
            'partRequest' => $partRequest->load(['category', 'city', 'offers.provider.neighborhood', 'offers.product']),
        ]);
    }

    public function close(Request $request, PartRequest $partRequest): RedirectResponse
    {
        abort_unless($partRequest->user_id === $request->user()->id, 404);
        $partRequest->forceFill(['status' => 'closed'])->save();

        return back()->with('success', 'Demande clôturée : elle n\'est plus visible des boutiques.');
    }
}
