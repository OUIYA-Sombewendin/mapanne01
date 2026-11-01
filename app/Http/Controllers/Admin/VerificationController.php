<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProviderDocument;
use App\Notifications\SimpleMessage;
use App\Support\Listing;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Vérification des justificatifs envoyés par les prestataires (badges de confiance). */
class VerificationController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');

        $query = ProviderDocument::query()
            ->with(['provider:id,slug,business_name,type,manager_name,phone,user_id,identity_verified_at,business_verified_at,workshop_verified_at', 'reviewer:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('kind'), fn ($q, $k) => $q->where('kind', $k))
            ->when($request->query('type'), fn ($q, $t) => $q->whereHas('provider', fn ($p) => $p->where('type', $t)))
            ->when(trim((string) $request->query('q')) !== '', fn ($q) => $q->whereHas('provider', fn ($p) => $p->where('business_name', 'ilike', Listing::like(trim((string) $request->query('q'))))));

        return view('admin.verifications', [
            'documents' => Listing::sort($query, $request, ['date' => 'created_at', 'kind' => 'kind'], 'date', $status === 'pending' ? 'asc' : 'desc')
                ->paginate(20)->withQueryString(),
            'status' => $status,
            'counts' => ProviderDocument::selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'kinds' => ProviderDocument::KINDS,
            'types' => \App\Models\ProviderType::cached()->values(),
        ]);
    }

    /** Image du document (disque privé). */
    public function file(ProviderDocument $document)
    {
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response($document->path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function review(Request $request, ProviderDocument $document): RedirectResponse
    {
        abort_unless($document->status === 'pending', 422, 'Ce document a déjà été traité.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:255'],
        ], ['reason.required_if' => 'Indiquez le motif du refus.']);

        $approved = $data['decision'] === 'approve';
        $document->review($request->user(), $approved, $data['reason'] ?? null);
        ActivityLog::record($approved ? 'document.approved' : 'document.rejected', $document->provider, ['document' => $document->kindLabel(), 'motif' => $data['reason'] ?? null]);

        Notifier::send($document->provider->user, new SimpleMessage(
            $approved ? 'Badge obtenu : '.ProviderDocument::KINDS[$document->kind][1] : 'Document à renvoyer',
            $approved
                ? ['Votre document « '.$document->kindLabel().' » a été vérifié.', 'Le badge « '.ProviderDocument::KINDS[$document->kind][1].' » apparaît maintenant sur votre fiche.']
                : ['Votre document « '.$document->kindLabel().' » n\'a pas pu être validé.', 'Motif : '.$data['reason']],
            'Mes badges',
            route('pro.verification'),
        ));

        return back()->with('success', $approved ? 'Document validé : badge attribué.' : 'Document refusé : le prestataire est prévenu.');
    }
}
