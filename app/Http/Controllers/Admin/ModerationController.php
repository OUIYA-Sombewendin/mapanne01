<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModerationController extends Controller
{
    public function reviews(Request $request): View
    {
        return view('admin.reviews', [
            'reviews' => Review::with(['user:id,name,phone,email', 'provider:id,business_name,slug'])
                ->when($request->query('filter') === 'pending', fn ($q) => $q->where('is_pending', true))
                ->when($request->query('filter') === 'hidden', fn ($q) => $q->where('is_hidden', true))
                ->when($request->query('filter') === 'low', fn ($q) => $q->where('rating', '<=', 2))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'pendingCount' => Review::where('is_pending', true)->count(),
        ]);
    }

    /** Pré-modération : publie un avis en attente. */
    public function approveReview(Review $review): RedirectResponse
    {
        $review->is_pending = false;
        $review->save();
        ActivityLog::record('review.approved', $review);

        return back()->with('success', 'Avis publié.');
    }

    public function toggleReview(Review $review): RedirectResponse
    {
        $review->is_hidden = ! $review->is_hidden;
        $review->save(); // déclenche le recalcul de la note du prestataire

        ActivityLog::record($review->is_hidden ? 'review.hidden' : 'review.restored', $review);

        return back()->with('success', $review->is_hidden ? 'Avis masqué.' : 'Avis republié.');
    }

    public function reports(Request $request): View
    {
        $status = ReportStatus::tryFrom((string) $request->query('status', 'open'));

        return view('admin.reports', [
            'reports' => Report::with(['reporter:id,name,phone', 'provider:id,business_name,slug', 'review:id,comment,rating', 'handler:id,name'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'status' => $status,
            'statuses' => ReportStatus::cases(),
        ]);
    }

    public function handleReport(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([ReportStatus::Resolved->value, ReportStatus::Dismissed->value])],
        ]);

        $report->forceFill([
            'status' => $data['status'],
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();
        ActivityLog::record('report.'.$data['status'], $report);

        return back()->with('success', 'Signalement mis à jour.');
    }
}
