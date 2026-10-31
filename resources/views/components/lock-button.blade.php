{{-- Verrou anti-erreur d'un élément : bouton pour qui a la permission, simple cadenas pour les autres. --}}
@props(['model', 'compact' => false])
@php
    $key = \App\Models\Lock::keyFor($model);
    $locked = $model->isLocked();
@endphp
@if ($key)
    @can('locks.manage')
        <form method="POST" action="{{ route('admin.locks.toggle') }}" class="inline"
              @if (! $locked) onsubmit="const r = prompt('Verrouiller cet élément ? Motif (facultatif) :', ''); if (r === null) return false; this.reason.value = r;" @else onsubmit="return confirm('Déverrouiller cet élément ? Il pourra de nouveau être modifié ou supprimé.')" @endif>
            @csrf
            <input type="hidden" name="type" value="{{ $key }}">
            <input type="hidden" name="id" value="{{ $model->getKey() }}">
            <input type="hidden" name="reason" value="">
            <button @class([
                'inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold ring-1 transition',
                'bg-amber-100 text-amber-900 ring-amber-300 hover:bg-amber-200' => $locked,
                'bg-white text-ink-500 ring-ink-200 hover:text-ink-800' => ! $locked,
            ]) title="{{ $locked ? 'Verrouillé'.($model->lock->reason ? ' : '.$model->lock->reason : '').' — cliquer pour déverrouiller' : 'Verrouiller pour éviter toute modification par erreur' }}">
                {{ $locked ? '🔒' : '🔓' }}@unless ($compact) {{ $locked ? 'Verrouillé' : 'Verrouiller' }}@endunless
            </button>
        </form>
    @else
        @if ($locked)
            <span class="chip bg-amber-100 text-amber-900" title="{{ $model->lock->reason }}">🔒 Verrouillé</span>
        @endif
    @endcan
@endif
