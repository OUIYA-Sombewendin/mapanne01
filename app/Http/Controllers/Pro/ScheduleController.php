<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function edit(Request $request): View
    {
        $provider = $request->user()->provider;

        return view('pro.schedule', [
            'provider' => $provider,
            'days' => Schedule::DAYS,
            'schedules' => $provider->schedules->keyBy('day'),
        ]);
    }

    /**
     * Formulaire : days[1][open]=1, days[1][opens_at]=08:00, days[1][closes_at]=18:00, days[1][allday]=1
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'days' => ['array'],
            'days.*.opens_at' => ['nullable', 'date_format:H:i'],
            'days.*.closes_at' => ['nullable', 'date_format:H:i'],
        ]);

        $provider = $request->user()->provider;
        $rows = [];

        foreach (array_keys(Schedule::DAYS) as $day) {
            $input = $request->input("days.$day", []);
            if (empty($input['open'])) {
                continue;
            }

            if (! empty($input['allday'])) {
                $rows[] = ['day' => $day, 'opens_at' => '00:00', 'closes_at' => '00:00'];

                continue;
            }

            if (empty($input['opens_at']) || empty($input['closes_at']) || $input['opens_at'] === $input['closes_at']) {
                return back()->withInput()->withErrors(["days.$day" => Schedule::DAYS[$day].' : indiquez une heure d\'ouverture et de fermeture différentes (ou cochez 24h/24).']);
            }

            $rows[] = ['day' => $day, 'opens_at' => $input['opens_at'], 'closes_at' => $input['closes_at']];
        }

        DB::transaction(function () use ($provider, $rows) {
            $provider->schedules()->delete();
            $provider->schedules()->createMany($rows);
        });

        return back()->with('success', 'Horaires enregistrés.');
    }
}
