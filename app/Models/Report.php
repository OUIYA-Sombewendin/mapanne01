<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    /** Motifs de signalement, modifiables dans Réglages › Avis et signalements. */
    public static function reasons(): array
    {
        return (array) setting('report_reasons', []);
    }

    protected $fillable = ['provider_id', 'review_id', 'reason', 'details'];

    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function reasonLabel(): string
    {
        return static::reasons()[$this->reason] ?? $this->reason;
    }
}
