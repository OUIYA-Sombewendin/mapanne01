{{-- Formulaire véhicule (ajout ou modification). $vehicle peut être null. --}}
@php($v = $vehicle ?? null)
<form method="POST" action="{{ $v ? route('vehicles.update', $v) : route('vehicles.store') }}" class="grid grid-cols-2 gap-3">
    @csrf
    @if ($v) @method('PUT') @endif
    <div>
        <label class="field-label">Type</label>
        <select name="type" class="input" required>
            @foreach (\App\Enums\VehicleType::options() as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $v?->type->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="field-label">Énergie</label>
        <select name="energy" class="input" required>
            @foreach (\App\Support\MaintenanceTasks::energies() as $value => $label)
                <option value="{{ $value }}" @selected(old('energy', $v?->energy ?? 'thermique') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <x-field name="brand" label="Marque" placeholder="Toyota, Yamaha…" :value="$v?->brand" required />
    <x-field name="model" label="Modèle" :value="$v?->model" />
    <x-field name="year" type="number" label="Année" :value="$v?->year" />
    <x-field name="plate" label="Immatriculation" :value="$v?->plate" />
    <button class="btn btn-primary col-span-2">{{ $v ? 'Enregistrer' : 'Ajouter le véhicule' }}</button>
</form>
