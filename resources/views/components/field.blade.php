@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null])
@php($errorKey = str_replace(['[', ']'], ['.', ''], $name))
<div>
    @if ($label)
        <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    @endif
    @if ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="4" {{ $attributes->class(['input', 'input-error' => $errors->has($errorKey)]) }}>{{ old($errorKey, $value) }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($errorKey, $value) }}" {{ $attributes->class(['input', 'input-error' => $errors->has($errorKey)]) }}>
    @endif
    @if ($hint)
        <p class="mt-1 text-xs text-ink-400">{{ $hint }}</p>
    @endif
    @error($errorKey)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
