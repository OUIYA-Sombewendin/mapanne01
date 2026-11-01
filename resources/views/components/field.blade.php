@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null])
@php($errorKey = str_replace(['[', ']'], ['.', ''], $name))
<div>
    @if ($label)
        <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    @endif
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="4" {{ $attributes->class(['input', 'input-error' => $errors->has($errorKey)]) }}>{{ old($errorKey, $value) }}</textarea>
    @elseif ($type === 'password')
        {{-- Bouton 👁 pour vérifier ce qu'on a tapé --}}
        <div class="relative" x-data="{ show: false }">
            <input id="{{ $name }}" name="{{ $name }}" type="password" :type="show ? 'text' : 'password'" value="" {{ $attributes->class(['input pr-12', 'input-error' => $errors->has($errorKey)]) }}>
            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-ink-400 hover:text-ink-700"
                    :aria-label="show ? 'Masquer le mot de passe' : 'Afficher le mot de passe'" :title="show ? 'Masquer' : 'Afficher'">
                <svg x-show="!show" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="show" x-cloak class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 3l18 18M10.6 5.1A10.4 10.4 0 0112 5c6.5 0 10 7 10 7a17 17 0 01-3.2 4.1M6.6 6.6C3.9 8.4 2 12 2 12s3.5 7 10 7c1.8 0 3.4-.5 4.8-1.3M9.9 9.9a3 3 0 004.2 4.2"/></svg>
            </button>
        </div>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($errorKey, $value) }}" {{ $attributes->class(['input', 'input-error' => $errors->has($errorKey)]) }}>
    @endif
    @if ($hint)
        <p class="mt-1 text-xs text-ink-400">{{ $hint }}</p>
    @endif
    @error($errorKey)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
