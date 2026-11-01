<?php

namespace App\Http\Controllers\Account;

use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Models\AssistRequest;
use App\Models\Provider;
use App\Models\Vehicle;
use App\Models\VehicleLog;
use App\Models\VehicleReminder;
use App\Support\MaintenanceTasks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Carnet d'entretien du client : rappels par date et historique, en quelques gestes.
 */
class LogbookController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $vehicles = $request->user()->vehicles()->with(['reminders', 'logs' => fn ($q) => $q->limit(3)])->withSum('logs', 'cost')->get();

        if ($vehicles->count() === 1) {
            return redirect()->route('logbook.show', $vehicles->first());
        }

        return view('account.logbook.index', [
            'vehicles' => $vehicles,
            'vehicleTypes' => VehicleType::options(),
            'energies' => MaintenanceTasks::energies(),
        ]);
    }

    public function show(Request $request, Vehicle $vehicle): View
    {
        $this->authorizeVehicle($request, $vehicle);
        $vehicle->load(['reminders', 'logs.provider:id,slug,business_name']);

        return view('account.logbook.show', [
            'vehicle' => $vehicle,
            'vehicles' => $request->user()->vehicles()->get(['id', 'type', 'brand', 'model', 'year']),
            'tasks' => $vehicle->tasks(),
            'reminders' => $vehicle->reminders->keyBy('task'),
            'providers' => $this->knownProviders($request),
            'spent' => [
                'year' => (int) $vehicle->logs->filter(fn ($l) => $l->done_on->year === now()->year)->sum('cost'),
                'total' => (int) $vehicle->logs->sum('cost'),
            ],
        ]);
    }

    /** Rappels cochés : création, mise à jour de la fréquence et de la dernière date, suppression des décochés. */
    public function reminders(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorizeVehicle($request, $vehicle);
        $tasks = $vehicle->tasks();
        $data = $request->validate([
            'tasks' => ['array'],
            'tasks.*.on' => ['nullable', 'boolean'],
            'tasks.*.every' => ['nullable', 'integer', 'between:1,36'],
            'tasks.*.last' => ['nullable', 'date', 'before_or_equal:today', 'after:2000-01-01'],
        ], ['tasks.*.last.before_or_equal' => 'La date du dernier entretien ne peut pas être dans le futur.']);

        $existing = $vehicle->reminders()->get()->keyBy('task');
        $kept = [];
        foreach ($tasks as $key => $task) {
            $input = $data['tasks'][$key] ?? [];
            if (empty($input['on'])) {
                continue;
            }
            $kept[] = $key;
            $every = (int) ($input['every'] ?? $task['every']);
            $reminder = $existing->get($key);
            $last = ! empty($input['last']) ? Carbon::parse($input['last']) : $reminder?->last_done_on;
            // Sans date connue : premier rappel au bout d'une période.
            $next = $last ? $last->copy()->addMonthsNoOverflow($every) : ($reminder?->next_due_on ?? today()->addMonthsNoOverflow($every));

            $vehicle->reminders()->updateOrCreate(['task' => $key], [
                'label' => $task['label'],
                'every_months' => $every,
                'last_done_on' => $last,
                'next_due_on' => $next,
                'notified_for' => $reminder && $reminder->next_due_on?->equalTo($next) ? $reminder->notified_for : null,
            ]);
        }
        $vehicle->reminders()->whereNotIn('task', $kept)->delete();

        return back()->with('success', count($kept) ? count($kept).' rappel(s) enregistré(s). Vous serez prévenu '.VehicleReminder::NOTICE_DAYS.' jours avant.' : 'Rappels désactivés.');
    }

    public function storeLog(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorizeVehicle($request, $vehicle);
        $request->merge(['cost' => $request->filled('cost') ? preg_replace('/\D/', '', (string) $request->input('cost')) : null]);
        $data = $request->validate([
            'task' => ['nullable', Rule::in(array_keys($vehicle->tasks()))],
            'label' => ['required_without:task', 'nullable', 'string', 'max:120'],
            'done_on' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'cost' => ['nullable', 'integer', 'max:50000000'],
            'provider_id' => ['nullable', Rule::in($this->knownProviders($request)->pluck('id')->all())],
            'provider_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'label.required_without' => 'Choisissez un entretien ou décrivez ce qui a été fait.',
            'done_on.before_or_equal' => 'La date ne peut pas être dans le futur.',
        ]);
        $data['label'] = filled($data['label'] ?? null) ? $data['label'] : MaintenanceTasks::label($data['task']);

        $log = $vehicle->logs()->create($data);
        $vehicle->recordDone($log->task, $log->done_on);

        return back()->with('success', 'Entretien ajouté au carnet.');
    }

    public function destroyLog(Request $request, Vehicle $vehicle, VehicleLog $log): RedirectResponse
    {
        $this->authorizeVehicle($request, $vehicle);
        abort_unless($log->vehicle_id === $vehicle->id, 404);
        $log->delete();

        return back()->with('success', 'Entretien supprimé.');
    }

    private function authorizeVehicle(Request $request, Vehicle $vehicle): void
    {
        abort_unless($vehicle->user_id === $request->user()->id, 404);
    }

    /** Prestataires que le client connaît déjà : favoris et dépanneurs venus l'aider. */
    private function knownProviders(Request $request): Collection
    {
        $user = $request->user();
        $ids = AssistRequest::where('user_id', $user->id)->whereNotNull('provider_id')->pluck('provider_id')
            ->merge($user->favorites()->pluck('providers.id'))->unique();

        return Provider::whereIn('id', $ids)->orderBy('business_name')->get(['id', 'business_name', 'type']);
    }
}
