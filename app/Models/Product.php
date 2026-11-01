<?php

namespace App\Models;

use App\Enums\VehicleType;
use App\Models\Concerns\Lockable;
use App\Support\PartCatalog;
use App\Support\Phone;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use Lockable;

    public const CONDITIONS = ['new' => 'Neuf', 'used' => 'Occasion'];

    /** Seuil sous lequel on affiche « Stock limité ». */
    public const LOW_STOCK = 3;

    /** La modération (is_hidden, approved_at), la mise en avant et les compteurs ne sont pas assignables. */
    protected $fillable = [
        'part_category_id', 'name', 'description', 'brand', 'reference', 'barcode', 'unit', 'compatibility',
        'vehicle_types', 'condition', 'warranty', 'price', 'compare_at_price', 'stock', 'on_order', 'is_active',
        'cost_price', 'stock_alert', 'shelf',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_types' => 'array',
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'cost_price' => 'integer',
            'stock_alert' => 'integer',
            'stock' => 'integer',
            'on_order' => 'boolean',
            'is_active' => 'boolean',
            'is_hidden' => 'boolean',
            'is_featured' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->slug ??= static::uniqueSlug($product->name);
            // Pré-modération (option admin) : le produit attend une validation avant d'être public.
            if (! $product->approved_at && ! feature('products_premoderation')) {
                $product->approved_at = now();
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        // Suffixe aléatoire : plusieurs boutiques vendent souvent la même pièce.
        do {
            $slug = Str::limit(Str::slug($name), 80, '').'-'.Str::lower(Str::random(5));
        } while (static::where('slug', $slug)->exists());

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Stock sous le seuil d'alerte choisi par le vendeur (ou à zéro). */
    public function isLowStock(): bool
    {
        return ! $this->on_order && $this->stock <= max(0, (int) $this->stock_alert);
    }

    /** Marge unitaire (null si le prix d'achat n'est pas renseigné). */
    public function unitMargin(): ?int
    {
        return $this->cost_price !== null ? $this->price - $this->cost_price : null;
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'part_category_id');
    }

    /** Compatibilité structurée : marque, modèle, années. */
    public function fitments(): HasMany
    {
        return $this->hasMany(ProductFitment::class)->orderBy('make')->orderBy('model');
    }

    /** Remplace la compatibilité par la liste saisie (lignes sans marque ignorées). */
    public function syncFitments(array $rows): void
    {
        $this->fitments()->delete();
        foreach ($rows as $row) {
            if (blank($row['make'] ?? null)) {
                continue;
            }
            $this->fitments()->create([
                'make' => PartCatalog::make($row['make']),
                'model' => filled($row['model'] ?? null) ? trim($row['model']) : null,
                'year_from' => $row['year_from'] ?? null,
                'year_to' => $row['year_to'] ?? null,
            ]);
        }
        $this->unsetRelation('fitments');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProductPhoto::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Produit visible au public : mis en vente, non masqué par la modération,
     * et vendu par une boutique elle-même visible (validée, compte actif).
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('products.is_active', true)
            ->where('products.is_hidden', false)
            ->whereNotNull('products.approved_at')
            ->whereHas('provider', fn (Builder $q) => $q->visible()->whereIn('type', ProviderType::sellingSlugs()));
    }

    public function isVisible(): bool
    {
        return $this->is_active && ! $this->is_hidden && $this->approved_at !== null
            && $this->provider->isVisible() && $this->provider->canSellParts();
    }

    public function isPendingApproval(): bool
    {
        return $this->approved_at === null;
    }

    // Présentation -------------------------------------------------------------

    public function priceLabel(): string
    {
        return static::formatPrice($this->price);
    }

    /** « / paire », « / litre »… (rien pour la pièce à l'unité). */
    public function unitSuffix(): string
    {
        return $this->unit && $this->unit !== 'piece' ? ' / '.PartCatalog::unitLabel($this->unit) : '';
    }

    public static function formatPrice(?int $amount): string
    {
        return number_format((int) $amount, 0, ',', ' ').' FCFA';
    }

    /** Pourcentage de réduction si un prix barré supérieur est renseigné. */
    public function discountPercent(): ?int
    {
        return $this->compare_at_price > $this->price
            ? (int) round(100 - $this->price / $this->compare_at_price * 100)
            : null;
    }

    /** @return array{label:string, class:string, available:bool} */
    public function availability(): array
    {
        // Quantité exacte affichée seulement si l'admin l'a choisi.
        $qty = feature('show_stock_quantity') && $this->stock > 0 ? " ({$this->stock})" : '';

        if ($this->stock > self::LOW_STOCK) {
            return ['label' => 'En stock'.$qty, 'class' => 'bg-emerald-50 text-emerald-700', 'available' => true];
        }
        if ($this->stock > 0) {
            return ['label' => 'Stock limité'.$qty, 'class' => 'bg-amber-50 text-amber-700', 'available' => true];
        }
        if ($this->on_order) {
            return ['label' => 'Sur commande', 'class' => 'bg-sky-50 text-sky-700', 'available' => true];
        }

        return ['label' => 'Rupture de stock', 'class' => 'bg-ink-100 text-ink-500', 'available' => false];
    }

    public function vehicleTypeLabels(): array
    {
        return collect($this->vehicle_types ?? [])
            ->map(fn ($v) => VehicleType::tryFrom($v)?->label())
            ->filter()
            ->values()
            ->all();
    }

    public function conditionLabel(): string
    {
        return self::CONDITIONS[$this->condition] ?? $this->condition;
    }

    public function coverUrl(): ?string
    {
        return $this->photos->first()?->url();
    }

    /** Lien WhatsApp avec un message qui identifie la pièce et son prix. */
    public function whatsappUrl(): ?string
    {
        $shop = $this->provider;

        return Phone::whatsappUrl(
            $shop->whatsapp ?: $shop->phone,
            "Bonjour {$shop->business_name}, je suis intéressé(e) par « {$this->name} » à {$this->priceLabel()} vu sur MaPanne. Est-il disponible ?\n".route('products.show', $this)
        );
    }
}
