{{-- Un champ ajouté par l'admin : résumé, modification en ligne, suppression. --}}
<div class="rounded-xl border border-ink-100" x-data="{ edit: false, type: '{{ $field->type }}' }">
    <div class="flex flex-wrap items-center gap-2 px-4 py-3">
        <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ $field->label }} @if ($field->is_required) <span class="text-red-600">*</span> @endif</p>
            <p class="text-xs text-ink-500">
                {{ \App\Models\ProfileField::TYPES[$field->type] ?? $field->type }}
                @if ($field->options) · {{ implode(', ', $field->options) }} @endif
                · <code>{{ $field->key }}</code>
            </p>
        </div>
        @unless ($field->is_active) <span class="chip bg-ink-100 text-ink-500">inactif</span> @endunless
        <span @class(['chip', 'bg-emerald-100 text-emerald-800' => $field->is_public, 'bg-ink-100 text-ink-500' => ! $field->is_public])>{{ $field->is_public ? 'public' : 'interne' }}</span>
        <button type="button" @click="edit = !edit" class="btn btn-ghost btn-sm">Modifier</button>
        <form method="POST" action="{{ route('admin.forms.destroy', $field) }}" onsubmit="return confirm('Retirer ce champ du formulaire ? Les réponses déjà saisies sont conservées.')">@csrf @method('DELETE')
            <button class="btn btn-ghost btn-sm text-red-600">Supprimer</button>
        </form>
    </div>
    <form x-cloak x-show="edit" method="POST" action="{{ route('admin.forms.update', $field) }}" class="grid gap-3 border-t border-ink-100 p-4 sm:grid-cols-2">
        @csrf @method('PUT')
        <div><label class="field-label">Libellé</label><input name="label" value="{{ $field->label }}" class="input" required></div>
        <div>
            <label class="field-label">Type de réponse</label>
            <select name="type" x-model="type" class="input">
                @foreach (\App\Models\ProfileField::TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
        </div>
        <div class="sm:col-span-2" x-show="type === 'select' || type === 'multi'">
            <label class="field-label">Choix (un par ligne)</label>
            <textarea name="options" rows="3" class="input">{{ implode("\n", $field->options ?? []) }}</textarea>
        </div>
        <div><label class="field-label">Exemple affiché</label><input name="placeholder" value="{{ $field->placeholder }}" class="input"></div>
        <div><label class="field-label">Aide</label><input name="help" value="{{ $field->help }}" class="input"></div>
        <div><label class="field-label">Ordre</label><input type="number" name="position" value="{{ $field->position }}" class="input" min="0"></div>
        <div class="flex flex-wrap items-end gap-4 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="is_required" value="1" class="checkbox" @checked($field->is_required)> Obligatoire</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_public" value="1" class="checkbox" @checked($field->is_public)> Public</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" class="checkbox" @checked($field->is_active)> Actif</label>
        </div>
        <div class="sm:col-span-2"><button class="btn btn-primary btn-sm">Enregistrer</button></div>
    </form>
</div>
