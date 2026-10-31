<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const UPDATED_AT = null;

    /** Motifs proposés au vendeur selon le sens du mouvement. */
    public const REASONS = [
        'in' => ['purchase' => 'Achat / réapprovisionnement', 'return' => 'Retour client'],
        'out' => ['sale' => 'Vente au comptoir', 'loss' => 'Perte, casse, vol', 'own_use' => 'Utilisation à l\'atelier'],
        'adjust' => ['inventory' => 'Inventaire (correction)'],
    ];

    /** Motifs créés automatiquement par la plateforme. */
    public const SYSTEM_REASONS = [
        'initial' => 'Stock initial',
        'invoice' => 'Facture',
        'cancel' => 'Annulation de facture',
        'import' => 'Import du catalogue',
        'quick' => 'Correction rapide',
    ];

    protected $fillable = ['product_id', 'type', 'quantity', 'stock_after', 'unit_cost', 'reason', 'note', 'invoice_id', 'user_id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'stock_after' => 'integer', 'unit_cost' => 'integer', 'created_at' => 'datetime'];
    }

    public static function reasonLabel(string $reason): string
    {
        $all = array_merge(...array_values(self::REASONS)) + self::SYSTEM_REASONS;

        return $all[$reason] ?? $reason;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
