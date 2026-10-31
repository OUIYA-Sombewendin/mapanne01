<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Report;
use App\Notifications\SimpleMessage;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request, Provider $provider): RedirectResponse
    {
        abort_unless($provider->isVisible(), 404);

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Report::reasons()))],
            'details' => ['nullable', 'string', 'max:1000'],
            'review_id' => ['nullable', Rule::exists('reviews', 'id')->where('provider_id', $provider->id)],
        ]);

        $report = new Report($data + ['provider_id' => $provider->id]);
        $report->reporter_id = $request->user()->id;
        $report->save();

        Notifier::sendTo(setting('admin_notification_email'), new SimpleMessage(
            'Nouveau signalement : '.$provider->business_name,
            ['Motif : '.$report->reasonLabel(), $report->details ?: 'Sans détail.'],
            'Traiter les signalements', route('admin.reports.index'),
        ), 'notify_admin_new_report');

        return back()->with('success', 'Signalement envoyé. L\'équipe MaPanne va vérifier.');
    }
}
