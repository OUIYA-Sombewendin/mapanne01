<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Historique de toutes les actions d'administration et des événements importants. */
class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.history', [
            'logs' => $this->query($request)->with('user:id,name')->latest('id')->paginate(50)->withQueryString(),
            'admins' => User::whereIn('id', ActivityLog::query()->select('user_id')->whereNotNull('user_id')->distinct())->orderBy('name')->get(['id', 'name']),
            'domains' => [
                'provider' => 'Prestataires', 'user' => 'Utilisateurs', 'review' => 'Avis', 'report' => 'Signalements',
                'product' => 'Marketplace', 'subscription' => 'Abonnements', 'plan' => 'Offres', 'role' => 'Rôles',
                'settings' => 'Réglages', 'backup' => 'Sauvegardes', 'lock' => 'Verrous', 'data' => 'Import / export', 'auth' => 'Connexions',
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        ActivityLog::record('data.exported', null, ['jeu' => 'historique']);

        return Csv::download('historique-'.now()->format('Ymd').'.csv', ['Date', 'Par', 'Action', 'Objet', 'Détails'],
            $this->query($request)->with('user:id,name')->orderBy('id')->lazyById(1000)
                ->map(fn (ActivityLog $l) => [$l->created_at->format('d/m/Y H:i'), $l->user?->name ?? 'Système', $l->label(), $l->subjectLabel(), $l->details ? json_encode($l->details, JSON_UNESCAPED_UNICODE) : '']));
    }

    private function query(Request $request): Builder
    {
        return ActivityLog::query()
            ->when($request->query('user'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('domain'), fn ($q, $d) => $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $d).'.%'))
            ->when($request->query('from'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->where('created_at', '<', Carbon::parse($d)->addDay()))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('action', 'ilike', "%$s%")->orWhereRaw('details::text ILIKE ?', ["%$s%"])));
    }
}
