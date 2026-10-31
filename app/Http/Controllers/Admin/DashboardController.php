<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProviderStatus;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PartRequest;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderType;
use App\Models\Report;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $byStatus = Provider::selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');
        $byType = Provider::where('status', ProviderStatus::Approved)->selectRaw('type, COUNT(*) AS n')->groupBy('type')->pluck('n', 'type');

        // Délai moyen entre l'inscription et la validation (heures), sur 30 jours.
        $avgHours = Provider::whereNotNull('approved_at')->where('approved_at', '>=', now()->subDays(30))
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (approved_at - created_at)) / 3600) AS h')->value('h');

        return view('admin.dashboard', [
            'stats' => [
                'clients' => User::where('role', UserRole::Client)->count(),
                'providers_approved' => (int) ($byStatus[ProviderStatus::Approved->value] ?? 0),
                'providers_pending' => (int) ($byStatus[ProviderStatus::Pending->value] ?? 0),
                'new_users_7d' => User::where('role', UserRole::Client)->where('created_at', '>=', now()->subDays(7))->count(),
                'products' => Product::visible()->count(),
                'contacts' => (int) Product::sum('contacts_count'),
                'avg_validation_hours' => $avgHours !== null ? (int) round($avgHours) : null,
                'open_requests' => PartRequest::open()->count(),
                'subscriptions' => Subscription::current()->count(),
                'subscriptions_revenue' => (int) Subscription::whereIn('status', ['active', 'expired'])->where('starts_at', '>=', now()->startOfMonth())->sum('amount'),
                'reviews' => Review::where('is_hidden', false)->where('is_pending', false)->count(),
            ],
            'previousLogin' => session('previous_login_at') ? Carbon::parse(session('previous_login_at')) : null,
            'types' => ProviderType::cached()->where('is_active', true)->values(),
            'typeCounts' => $byType,
            // File « À traiter » (également affichée dans le menu latéral)
            'todo' => [
                ['Inscriptions à valider', (int) ($byStatus[ProviderStatus::Pending->value] ?? 0), route('admin.providers.index')],
                ['Changements de fiche à vérifier', Provider::where('needs_review', true)->count(), route('admin.providers.index', ['status' => 'review'])],
                ['Signalements ouverts', Report::where('status', ReportStatus::Open)->count(), route('admin.reports.index')],
                ['Avis en attente', Review::where('is_pending', true)->count(), route('admin.reviews.index', ['filter' => 'pending'])],
                ['Produits en attente', Product::whereNull('approved_at')->count(), route('admin.marketplace.index', ['filter' => 'pending'])],
            ],
            'byType' => ProviderType::cached()->values()->mapWithKeys(fn ($t) => [$t->icon().' '.$t->label() => (int) ($byType[$t->slug] ?? 0)]),
            'pending' => Provider::where('status', ProviderStatus::Pending)->with('city:id,name')->oldest()->take(5)->get(),
            'logs' => ActivityLog::with('user:id,name')->latest('created_at')->take(12)->get(),
        ]);
    }
}
