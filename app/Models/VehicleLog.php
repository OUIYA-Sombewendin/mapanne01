<?php

namespace App\Models;

use App\Support\MaintenanceTasks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entretien ou réparation noté dans le carnet. */
class VehicleLog extends Model
{
    protected $fillable = ['done_on', 'task', 'label', 'cost', 'provider_id', 'provider_name', 'notes'];

    protected function casts(): array
    {
        return ['done_on' => 'date', 'cost' => 'integer'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function icon(): string
    {
        return MaintenanceTasks::icon($this->task);
    }

    public function providerLabel(): ?string
    {
        return $this->provider?->business_name ?? $this->provider_name;
    }
}
