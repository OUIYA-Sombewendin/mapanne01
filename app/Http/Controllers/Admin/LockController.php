<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Lock;
use App\Support\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Verrous anti-erreur : liste, pose et retrait (permission locks.manage). */
class LockController extends Controller
{
    public function index(Request $request): View
    {
        $types = collect(Lock::TYPES)->map(fn ($t) => (new $t[0])->getMorphClass());

        return view('admin.locks', [
            'locks' => Listing::sort(Lock::with(['lockable', 'locker:id,name'])
                ->when($types[$request->query('type')] ?? null, fn ($q, $class) => $q->where('lockable_type', $class)), $request, [
                    'date' => 'created_at',
                    'type' => 'lockable_type',
                ], 'date')->paginate(50)->withQueryString(),
        ]);
    }

    /** Pose ou retire le verrou d'un élément. */
    public function toggle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Lock::TYPES))],
            'id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        [$class, $label] = Lock::TYPES[$data['type']];
        $model = $class::findOrFail($data['id']);

        if ($model->lock) {
            $model->lock->delete();
            ActivityLog::record('lock.removed', $model);

            return back()->with('success', "{$label} déverrouillé : il peut à nouveau être modifié.");
        }

        $model->lock()->create(['locked_by' => $request->user()->id, 'reason' => $data['reason'] ?? null]);
        ActivityLog::record('lock.added', $model, array_filter(['motif' => $data['reason'] ?? null]));

        return back()->with('success', "{$label} verrouillé : aucune modification ni suppression possible depuis l'admin.");
    }
}
