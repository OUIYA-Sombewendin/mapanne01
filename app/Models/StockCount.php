<?php

namespace App\Models;

use App\Support\Inventory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Inventaire physique : le vendeur compte ses rayons, saisit les quantités réelles,
 * puis valide ; chaque écart devient un mouvement « Inventaire (correction) ».
 */
class StockCount extends Model
{
    public const STATUSES = [
        'open' => ['En cours', 'bg-amber-100 text-amber-800'],
        'applied' => ['Validé', 'bg-emerald-100 text-emerald-800'],
        'cancelled' => ['Annulé', 'bg-ink-100 text-ink-500'],
    ];

    protected $fillable = ['scope', 'user_id'];

    protected function casts(): array
    {
        return ['applied_at' => 'datetime', 'adjusted_count' => 'integer', 'gap_value' => 'integer'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusClass(): string
    {
        return self::STATUSES[$this->status][1] ?? '';
    }

    /**
     * Applique les quantités comptées. Les lignes non comptées ne changent rien.
     * Renvoie le nombre de pièces corrigées.
     */
    public function apply(): int
    {
        return DB::transaction(function () {
            $adjusted = 0;
            $gapValue = 0;
            foreach ($this->lines()->whereNotNull('counted')->with('product')->get() as $line) {
                $product = $line->product;
                if ($line->counted === $product->stock) {
                    continue;
                }
                $gapValue += ($line->counted - $product->stock) * (int) $product->cost_price;
                Inventory::move($product, 'adjust', $line->counted, 'inventory', null, 'Inventaire n° '.$this->id);
                $adjusted++;
            }
            $this->forceFill(['status' => 'applied', 'applied_at' => now(), 'adjusted_count' => $adjusted, 'gap_value' => $gapValue])->save();

            return $adjusted;
        });
    }
}
