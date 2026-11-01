{{-- Bouton d'activation des notifications push sur cet appareil (masqué si le push est désactivé ou non pris en charge). --}}
@props(['compact' => false])
@if (\App\Support\Channels::pushReady())
    <div x-data="pushToggle" x-show="supported" x-cloak {{ $attributes }}>
        <button type="button" @click="toggle" :disabled="busy" @class(['btn', 'btn-sm' => $compact])
                :class="enabled ? 'btn-ghost' : 'btn-primary'">
            <x-icon name="bell" />
            <span x-text="enabled ? 'Notifications activées' : 'Activer les notifications'">Activer les notifications</span>
        </button>
        <p x-show="error" x-text="error" class="field-error"></p>
    </div>
@endif
