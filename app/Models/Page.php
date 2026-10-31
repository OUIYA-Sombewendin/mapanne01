<?php

namespace App\Models;

use App\Models\Concerns\Lockable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/** Page de contenu éditable depuis l'administration (Markdown). */
class Page extends Model
{
    use Lockable;

    protected $fillable = ['slug', 'title', 'body', 'is_published', 'in_footer', 'position'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'in_footer' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function footer(): Collection
    {
        try {
            return static::where('is_published', true)->where('in_footer', true)->orderBy('position')->get(['slug', 'title']);
        } catch (Throwable) {
            return new Collection;
        }
    }

    /** Markdown → HTML, le HTML brut saisi est échappé (pas d'injection de scripts). */
    public function html(): string
    {
        return Str::markdown((string) $this->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}
