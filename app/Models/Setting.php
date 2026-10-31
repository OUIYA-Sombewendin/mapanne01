<?php

namespace App\Models;

use App\Support\SettingsRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Réglages de la plateforme : défauts dans SettingsRegistry, surcharges en base,
 * le tout mis en cache. Lecture via les helpers setting('cle') et feature('cle').
 */
class Setting extends Model
{
    public const CACHE_KEY = 'mapanne.settings';

    public const CREATED_AT = null;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** Valeurs mémorisées pour la requête en cours. */
    private static ?array $memo = null;

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    /** Valeurs effectives : défauts du registre remplacés par les valeurs enregistrées. */
    public static function values(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            $stored = []; // table absente (installation, migrations en cours)
        }

        $defaults = SettingsRegistry::defaults();

        return static::$memo = array_replace($defaults, array_intersect_key($stored, $defaults));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::values()[$key] ?? $default;

        if ((SettingsRegistry::all()[$key]['type'] ?? null) === 'secret' && filled($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (Throwable) {
                return null;
            }
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    public static function store(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        static::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        static::$memo = null;
    }
}
