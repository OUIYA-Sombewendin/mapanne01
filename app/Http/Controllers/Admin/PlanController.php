<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Plan;
use App\Models\ProviderType;
use App\Support\PlanFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Offres d'abonnement : prix, durée, fonctionnalités incluses, types de prestataires concernés. */
class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::ordered()->withCount(['subscriptions as active_count' => fn ($q) => $q->current()])->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Plan(['features' => [], 'limits' => [], 'period_days' => 30, 'is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $base = Str::slug($data['name']) ?: 'offre';
        $slug = $base;
        for ($i = 2; Plan::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }
        $plan = Plan::create($data + ['slug' => $slug]);
        ActivityLog::record('plan.created', $plan);

        return redirect()->route('admin.plans.index')->with('success', "Offre « {$plan->name} » créée.");
    }

    public function edit(Plan $plan): View
    {
        return $this->form($plan);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validated($request);
        abort_if($plan->is_default && ! $data['is_active'], 422, 'L\'offre par défaut doit rester active.');
        $plan->update($data);
        ActivityLog::record('plan.updated', $plan, ['champs' => array_keys($plan->getChanges())]);

        return redirect()->route('admin.plans.index')->with('success', 'Offre mise à jour. Les prestataires abonnés en profitent immédiatement.');
    }

    /** L'offre par défaut s'applique à tous les prestataires sans abonnement en cours. */
    public function makeDefault(Plan $plan): RedirectResponse
    {
        abort_unless($plan->is_active, 422);
        Plan::where('is_default', true)->update(['is_default' => false]);
        $plan->forceFill(['is_default' => true])->save();
        ActivityLog::record('plan.default', $plan);

        return back()->with('success', "« {$plan->name} » est maintenant l'offre par défaut.");
    }

    private function form(Plan $plan): View
    {
        return view('admin.plans.form', [
            'plan' => $plan,
            'features' => PlanFeatures::features(),
            'limits' => PlanFeatures::limits(),
            'types' => ProviderType::cached()->values(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'period_days' => ['required', 'integer', 'min:0', 'max:3660'],
            'features' => ['array'],
            'features.*' => [Rule::in(array_keys(PlanFeatures::features()))],
            'limits' => ['array'],
            'limits.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'provider_types' => ['array'],
            'provider_types.*' => [Rule::exists('provider_types', 'slug')],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => (int) $data['price'],
            'period_days' => (int) $data['period_days'],
            'features' => array_values(array_unique($data['features'] ?? [])),
            'limits' => collect(PlanFeatures::limits())->mapWithKeys(fn ($def, $key) => [$key => (int) ($data['limits'][$key] ?? 0)])->all(),
            'provider_types' => ! empty($data['provider_types']) ? array_values($data['provider_types']) : null,
            'position' => (int) ($data['position'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'is_highlighted' => $request->boolean('is_highlighted'),
        ];
    }
}
