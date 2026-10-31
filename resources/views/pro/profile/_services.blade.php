{{-- Services du métier uniquement (filtrés par le contrôleur). Paramètres : $id, $title, $text. --}}
<section id="{{ $id ?? 'services' }}" class="card scroll-mt-28 p-6">
    @include('pro._section-head', ['icon' => 'layers', 'title' => $title, 'text' => $text ?? 'Cochez ce que vous faites : les clients vous trouvent en cherchant ces services.'])
    <div class="space-y-5">
        @foreach ($categories as $category)
            <fieldset>
                @if ($categories->count() > 1)
                    <legend class="mb-2 text-sm font-semibold text-ink-700">{{ $category->icon }} {{ $category->name }}</legend>
                @endif
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($category->services as $service)
                        <label class="chip chip-filter px-3 py-1.5 text-sm">
                            <input type="checkbox" name="services[]" value="{{ $service->id }}" class="sr-only" @checked(in_array($service->id, old('services', $selectedServices)))>
                            {{ $service->name }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>
    @error('services.*') <p class="field-error">{{ $message }}</p> @enderror
</section>
