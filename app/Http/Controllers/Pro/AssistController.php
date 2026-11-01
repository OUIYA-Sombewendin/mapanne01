<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\AssistRequest;
use App\Support\AssistDispatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dépannage en direct, côté prestataire : demandes reçues, acceptation (le premier gagne),
 * puis « en route » et « terminé ». Le numéro du conducteur n'est visible qu'après acceptation.
 */
class AssistController extends Controller
{
    public function index(Request $request): View
    {
        $provider = $request->user()->provider;

        return view('pro.assist.index', [
            'open' => $this->openFor($request)->get(),
            'mine' => AssistRequest::where('provider_id', $provider->id)->whereIn('status', ['accepted', 'on_way'])->latest('accepted_at')->get(),
            'history' => AssistRequest::where('provider_id', $provider->id)->whereIn('status', ['done', 'cancelled'])->latest('updated_at')->limit(15)->get(),
            'doneCount' => AssistRequest::where('provider_id', $provider->id)->where('status', 'done')->count(),
        ]);
    }

    /** Nombre de demandes en attente : la page se recharge quand il change. */
    public function count(Request $request): JsonResponse
    {
        return response()->json(['open' => $this->openFor($request)->count()]);
    }

    public function accept(Request $request, AssistRequest $assist): RedirectResponse
    {
        $provider = $request->user()->provider;
        abort_unless(AssistDispatch::canSee($assist, $provider), 403);

        if (! AssistDispatch::accept($assist, $provider)) {
            return back()->with('error', 'Trop tard : cette demande a déjà été prise ou fermée.');
        }

        return back()->with('success', 'Demande acceptée. Appelez le conducteur pour confirmer.');
    }

    public function update(Request $request, AssistRequest $assist): RedirectResponse
    {
        abort_unless($assist->provider_id === $request->user()->provider->id && in_array($assist->status, ['accepted', 'on_way'], true), 403);
        $step = $request->validate(['step' => ['required', 'in:on_way,done,release']])['step'];

        if ($step === 'release') {
            AssistDispatch::release($assist, $request->user()->provider);

            return back()->with('success', 'Demande rendue : les autres prestataires peuvent la prendre.');
        }

        AssistDispatch::advance($assist, $step);

        return back()->with('success', $step === 'done' ? 'Intervention terminée. Merci !' : 'Le conducteur sait que vous êtes en route.');
    }

    /** Demandes ouvertes pour lesquelles ce prestataire a été prévenu (et qu'il n'a pas rendues). */
    private function openFor(Request $request)
    {
        $provider = $request->user()->provider;

        return AssistRequest::where('status', 'open')
            ->whereHas('notifiedProviders', fn ($q) => $q->whereKey($provider->id)->whereNull('assist_dispatches.declined_at'))
            ->with(['notifiedProviders' => fn ($q) => $q->whereKey($provider->id)])
            ->latest();
    }
}
