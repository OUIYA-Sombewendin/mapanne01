<?php

namespace App\Models;

use App\Enums\VehicleType;
use App\Support\MaintenanceTasks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Véhicule d'un client, avec son carnet d'entretien (rappels et historique). */
class Vehicle extends Model
{
    protected $fillable = ['type', 'energy', 'brand', 'model', 'year', 'plate'];

    protected function casts(): array
    {
        return ['type' => VehicleType::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(VehicleReminder::class)->orderBy('next_due_on');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VehicleLog::class)->orderByDesc('done_on')->orderByDesc('id');
    }

    public function title(): string
    {
        return trim($this->brand.' '.$this->model.($this->year ? ' ('.$this->year.')' : ''));
    }

    public function isElectric(): bool
    {
        return $this->energy === 'electrique';
    }

    public function icon(): string
    {
        return match ($this->type) {
            VehicleType::Motorbike => '🛵',
            VehicleType::Tricycle => '🛺',
            VehicleType::Truck => '🚚',
            default => '🚗',
        };
    }

    public function energyLabel(): string
    {
        return MaintenanceTasks::energies()[$this->energy] ?? $this->energy;
    }

    /** Entretiens proposés pour ce véhicule (type et énergie). */
    public function tasks(): array
    {
        return MaintenanceTasks::for($this->type->value, $this->energy);
    }

    /**
     * Un entretien enregistré repousse le rappel du même type : prochaine échéance = date de l'entretien + fréquence.
     * Un entretien plus ancien que le dernier connu ne change rien.
     */
    public function recordDone(?string $task, \DateTimeInterface $doneOn): void
    {
        if (! $task) {
            return;
        }
        $reminder = $this->reminders()->where('task', $task)->first();
        if (! $reminder || ($reminder->last_done_on && $reminder->last_done_on->gt($doneOn))) {
            return;
        }
        $reminder->update([
            'last_done_on' => $doneOn,
            'next_due_on' => \Illuminate\Support\Carbon::parse($doneOn)->addMonthsNoOverflow($reminder->every_months),
            'notified_for' => null,
        ]);
    }
}
