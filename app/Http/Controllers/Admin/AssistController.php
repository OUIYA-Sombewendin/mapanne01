<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AssistRequest;
use App\Models\City;
use App\Support\AssistDispatch;
use App\Support\AssistProblems;
use App\Support\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Suivi des demandes de dépannage en direct. */
class AssistController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        $query = AssistRequest::query()
            ->with(['provider:id,slug,business_name,type', 'city:id,name'])
            ->when($request->query('status'), fn ($query, $s) => $s === 'active' ? $query->active() : $query->where('status', $s))
            ->when($request->query('problem'), fn ($query, $p) => $query->where('problem', $p))
            ->when($request->query('city'), fn ($query, $c) => $query->where('city_id', $c))
            ->when($request->query('vehicle'), fn ($query, $v) => $query->where('vehicle_type', $v))
            ->when($request->query('energy'), fn ($query, $e) => $query->where('energy', $e))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('contact_name', 'ilike', Listing::like($q))
                ->orWhere('contact_phone', 'like', '%'.preg_replace('/\D/', '', $q).'%')));

        $since = now()->subDays(30);

        return view('admin.assist.index', [
            'requests' => Listing::sort($query, $request, [
                'created' => 'created_at',
                'status' => 'status',
                'problem' => 'problem',
                'notified' => 'notified_count',
            ], 'created', 'desc')->paginate(30)->withQueryString(),
            'problems' => AssistProblems::all(),
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'stats' => [
                'total' => AssistRequest::where('created_at', '>=', $since)->count(),
                'active' => AssistRequest::active()->count(),
                'done' => AssistRequest::where('created_at', '>=', $since)->where('status', 'done')->count(),
                'expired' => AssistRequest::where('created_at', '>=', $since)->where('status', 'expired')->count(),
                'minutes' => AssistRequest::where('created_at', '>=', $since)->whereNotNull('accepted_at')
                    ->selectRaw('AVG(EXTRACT(EPOCH FROM (accepted_at - created_at)) / 60) AS m')->value('m'),
            ],
        ]);
    }

    /** Fermer une demande abusive ou restée bloquée. */
    public function close(Request $request, AssistRequest $assist): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:200']])['reason'];
        abort_unless($assist->isActive(), 422, 'Cette demande est déjà fermée.');

        AssistDispatch::close($assist, 'cancelled', 'Fermée par l\'administration : '.$reason);
        ActivityLog::record('assist.closed', $assist, ['motif' => $reason]);

        return back()->with('success', 'Demande fermée.');
    }
}
