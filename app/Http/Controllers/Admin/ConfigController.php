<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Notifications\SimpleMessage;
use App\Support\Delivery;
use App\Support\ImageOptimizer;
use App\Support\MailConfigurator;
use App\Support\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Réglages de la plateforme : formulaire généré à partir de SettingsRegistry,
 * aucun réglage ne nécessite de modifier le code une fois en production.
 */
class ConfigController extends Controller
{
    /** Permission requise pour chaque groupe de réglages. */
    public static function permissionFor(string $group): string
    {
        return match ($group) {
            'appearance', 'home' => 'appearance.manage',
            'backups' => 'backups.manage',
            default => 'settings.manage',
        };
    }

    public function index(Request $request, ?string $group = null): View|RedirectResponse
    {
        $groups = collect(SettingsRegistry::groups())
            ->filter(fn ($g, $key) => $request->user()->can(self::permissionFor($key)))->all();
        abort_if($groups === [], 403);
        if ($group === null) {
            $group = array_key_first($groups);
        }
        abort_unless(isset(SettingsRegistry::groups()[$group]), 404);
        abort_unless(isset($groups[$group]), 403);

        return view('admin.settings', [
            'groups' => $groups,
            'current' => $group,
            'definitions' => $groups[$group]['settings'],
            'values' => Setting::values(),
            'upcoming' => SettingsRegistry::upcomingModules(),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $groups = SettingsRegistry::groups();
        abort_unless(isset($groups[$group]), 404);
        abort_unless($request->user()->can(self::permissionFor($group)), 403);

        $definitions = $groups[$group]['settings'];
        $request->validate($this->rules($definitions));

        $values = [];
        foreach ($definitions as $key => $def) {
            $raw = $request->input($key);
            if ($def['type'] === 'image') {
                if ($request->boolean($key.'_remove')) {
                    Storage::disk('public')->delete((string) setting($key));
                    $values[$key] = '';
                } elseif ($request->hasFile($key)) {
                    Storage::disk('public')->delete((string) setting($key));
                    $values[$key] = ImageOptimizer::store($request->file($key), 'branding', 512);
                }

                continue;
            }
            if ($def['type'] === 'secret') {
                // Champ laissé vide = on garde le mot de passe actuel.
                if (blank($raw)) {
                    continue;
                }
                $values[$key] = Crypt::encryptString($raw);

                continue;
            }
            $values[$key] = $this->cast($def, $raw);
        }

        Setting::store($values);
        ActivityLog::record('settings.updated', null, ['groupe' => $group, 'clés' => array_keys($values)]);

        return back()->with('success', 'Réglages enregistrés.');
    }

    /** Envoie un email de test avec la configuration actuelle. */
    public function testMail(Request $request): RedirectResponse
    {
        $email = $request->validate(['email' => ['required', 'email']])['email'];
        MailConfigurator::apply();

        try {
            Notification::route('mail', $email)->notify(new SimpleMessage(
                'Test d\'envoi '.setting('site_name'),
                ['Si vous lisez ce message, l\'envoi d\'emails fonctionne.'],
            ));
        } catch (Throwable $e) {
            return back()->withErrors(['email' => 'Échec de l\'envoi : '.$e->getMessage()]);
        }

        // En mode « journal », rien n'est parti : on le dit clairement.
        if (! Delivery::mailReady()) {
            return back()->with('error', 'Aucun email n\'est parti : le mode d\'envoi est « '.config('mail.default').' » (écrit dans storage/logs seulement). Choisissez SMTP et saisissez les accès de votre fournisseur.');
        }

        return back()->with('success', 'Email de test envoyé à '.$email.' (mode : '.config('mail.default').'). Vérifiez la boîte de réception et les spams.');
    }

    private function rules(array $definitions): array
    {
        $rules = [];
        foreach ($definitions as $key => $def) {
            $rules[$key] = match ($def['type']) {
                'bool' => ['nullable', 'boolean'],
                'int' => ['required', 'integer', 'min:0', 'max:1000000'],
                'float' => ['required', 'numeric'],
                'email' => ['nullable', 'email', 'max:150'],
                'select' => ['required', Rule::in(array_keys($def['options']))],
                'text', 'list', 'pairs' => ['nullable', 'string', 'max:10000'],
                'int_list' => ['required', 'string', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
                'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        return $rules;
    }

    private function cast(array $def, mixed $raw): mixed
    {
        $lines = fn () => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $raw))));

        return match ($def['type']) {
            'bool' => (bool) $raw,
            'int' => (int) $raw,
            'float' => (float) $raw,
            'color' => strtolower((string) $raw),
            'int_list' => array_values(array_unique(array_map('intval', explode(',', (string) $raw)))),
            'list' => $lines(),
            'pairs' => collect($lines())->mapWithKeys(function ($line) {
                [$k, $v] = array_pad(array_map('trim', explode('|', $line, 2)), 2, null);

                return [$k => $v ?: $k];
            })->filter(fn ($v, $k) => $k !== '')->all(),
            default => $raw === null ? '' : trim((string) $raw),
        };
    }
}
