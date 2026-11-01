<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ProviderDocument;
use App\Support\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Badges de confiance : le prestataire envoie ses justificatifs, l'équipe les vérifie. */
class VerificationController extends Controller
{
    public function index(Request $request): View
    {
        $provider = $request->user()->provider;

        return view('pro.verification', [
            'provider' => $provider,
            'kinds' => ProviderDocument::KINDS,
            'documents' => $provider->documents()->get()->groupBy('kind')->map->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(ProviderDocument::KINDS))],
            'number' => ['nullable', 'string', 'max:60'],
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], ['file.required' => 'Ajoutez la photo du document.']);

        // Un seul document en attente par type : le nouveau remplace l'ancien.
        $provider->documents()->where('kind', $data['kind'])->where('status', 'pending')->get()->each(function (ProviderDocument $doc) {
            Storage::disk('local')->delete($doc->path);
            $doc->delete();
        });

        $document = $provider->documents()->create([
            'kind' => $data['kind'],
            'number' => $data['number'] ?? null,
            // Photo ré-encodée (sans métadonnées) et gardée sur le disque privé.
            'path' => ImageOptimizer::store($request->file('file'), 'documents', 2000, 'local'),
        ]);
        ActivityLog::record('document.sent', $provider, ['document' => $document->kindLabel()]);

        return back()->with('success', 'Document envoyé : l\'équipe le vérifie et vous prévient.');
    }

    public function destroy(Request $request, ProviderDocument $document): RedirectResponse
    {
        abort_unless($document->provider_id === $request->user()->provider->id && $document->status === 'pending', 404);
        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document retiré.');
    }
}
