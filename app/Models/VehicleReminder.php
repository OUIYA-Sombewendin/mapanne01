<?php

namespace App\Models;

use App\Support\MaintenanceTasks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rappel d'entretien basé sur une date (vidange, assurance, visite technique…). */
class VehicleReminder extends Model
{
    /** Jours avant l'échéance où le rappel est envoyé et affiché « bientôt ». */
    public const NOTICE_DAYS = 7;

    protected $fillable = ['task', 'label', 'every_months', 'next_due_on', 'last_done_on', 'notified_for'];

    protected function casts(): array
    {
        return ['next_due_on' => 'date', 'last_done_on' => 'date', 'notified_for' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function icon(): string
    {
        return MaintenanceTasks::icon($this->task);
    }

    public function daysLeft(): int
    {
        return (int) today()->diffInDays($this->next_due_on, false);
    }

    /** late, soon ou ok */
    public function state(): string
    {
        $days = $this->daysLeft();

        return $days < 0 ? 'late' : ($days <= self::NOTICE_DAYS ? 'soon' : 'ok');
    }

    public function dueLabel(): string
    {
        $days = $this->daysLeft();

        return match (true) {
            $days < 0 => 'En retard de '.abs($days).' j',
            $days === 0 => 'Aujourd\'hui',
            $days === 1 => 'Demain',
            $days <= 60 => 'Dans '.$days.' j',
            default => 'Le '.$this->next_due_on->translatedFormat('j M Y'),
        };
    }

    public function stateClass(): string
    {
        return ['late' => 'bg-red-100 text-red-700', 'soon' => 'bg-amber-100 text-amber-800', 'ok' => 'bg-emerald-50 text-emerald-700'][$this->state()];
    }
}
