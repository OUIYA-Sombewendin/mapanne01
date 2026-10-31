<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProfileField;
use App\Models\ProviderType;
use App\Support\ProviderProfiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Formulaires des métiers : chaque type a son formulaire de fiche (champs du métier livrés avec la plateforme),
 * auquel l'administration ajoute ses propres champs sans toucher au code.
 */
class FormController extends Controller
{
    public function index(Request $request): View
    {
        $types = ProviderType::cached()->values();
        $current = $request->query('type', $types->first()?->slug);

        return view('admin.forms', [
            'types' => $types,
            'current' => $current,
            'currentType' => ProviderType::bySlug($current),
            'builtIn' => ProviderProfiles::get(ProviderType::bySlug($current)?->profileKind()),
            'fields' => ProfileField::where('provider_type', $current)->ordered()->get(),
            'shared' => ProfileField::whereNull('provider_type')->ordered()->get(),
            'fieldTypes' => ProfileField::TYPES,
            'kinds' => ProviderProfiles::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['key'] = $this->uniqueKey($data['label'], $data['provider_type']);
        $field = ProfileField::create($data + ['position' => (int) ProfileField::where('provider_type', $data['provider_type'])->max('position') + 1]);
        ActivityLog::record('form.field_added', $field, ['champ' => $field->label, 'métier' => $field->targetLabel()]);

        return back()->with('success', "Champ « {$field->label} » ajouté au formulaire {$field->targetLabel()}.");
    }

    public function update(Request $request, ProfileField $field): RedirectResponse
    {
        $data = $this->validated($request, $field);
        unset($data['provider_type']); // un champ ne change pas de métier (les réponses y sont rattachées)
        $field->update($data);
        ActivityLog::record('form.field_updated', $field, ['champ' => $field->label]);

        return back()->with('success', 'Champ mis à jour.');
    }

    /** Suppression : le champ disparaît des formulaires ; les réponses déjà saisies restent en base. */
    public function destroy(ProfileField $field): RedirectResponse
    {
        ActivityLog::record('form.field_deleted', null, ['champ' => $field->label, 'métier' => $field->targetLabel()]);
        $field->delete();

        return back()->with('success', 'Champ supprimé du formulaire.');
    }

    private function validated(Request $request, ?ProfileField $field = null): array
    {
        $data = $request->validate([
            'provider_type' => [$field ? 'nullable' : 'present', 'nullable', Rule::exists('provider_types', 'slug')],
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(ProfileField::TYPES))],
            'options' => ['nullable', 'required_if:type,select,multi', 'string', 'max:2000'],
            'help' => ['nullable', 'string', 'max:200'],
            'placeholder' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['options.required_if' => 'Indiquez les choix possibles, un par ligne.']);

        $data['options'] = in_array($data['type'], ['select', 'multi'], true)
            ? array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) $data['options'])))))
            : null;
        $data['position'] = (int) ($data['position'] ?? $field?->position ?? 0);
        foreach (['is_required', 'is_public', 'is_active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }

    private function uniqueKey(string $label, ?string $type): string
    {
        $base = Str::limit(Str::slug($label, '_'), 50, '') ?: 'champ';
        $key = $base;
        for ($i = 2; ProfileField::where('provider_type', $type)->where('key', $key)->exists(); $i++) {
            $key = $base.'_'.$i;
        }

        return $key;
    }
}
