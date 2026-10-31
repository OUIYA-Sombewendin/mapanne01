{{-- Filtre « oui / non / indifférent » compact. Paramètres : $name, $label. --}}
<select name="{{ $name }}" class="input-sm" aria-label="{{ $label }}">
    <option value="">{{ $label }} : tous</option>
    <option value="1" @selected(request($name) === '1')>{{ $label }} : oui</option>
    <option value="0" @selected(request($name) === '0')>{{ $label }} : non</option>
</select>
