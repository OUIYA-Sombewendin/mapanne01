<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Sauvegardes complètes : base PostgreSQL (pg_dump, format compressé) + photos et fichiers envoyés,
 * réunis dans une archive .zip rangée dans storage/app/private/backups (jamais accessible publiquement).
 */
class Backup
{
    public const DIR = 'backups';

    /** @return list<array{name:string, size:int, date:int}> du plus récent au plus ancien */
    public static function list(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIR))
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->map(fn ($f) => ['name' => basename($f), 'size' => $disk->size($f), 'date' => $disk->lastModified($f)])
            ->sortByDesc('date')->values()->all();
    }

    public static function path(string $name): string
    {
        // Seuls les noms générés par create() sont acceptés (pas de chemin arbitraire).
        if (! preg_match('/^mapanne-[0-9]{8}-[0-9]{6}(-[a-z]+)?\.zip$/', $name)) {
            throw new RuntimeException('Nom de sauvegarde invalide.');
        }

        return Storage::disk('local')->path(self::DIR.'/'.$name);
    }

    public static function create(?bool $includeFiles = null, string $label = ''): string
    {
        $includeFiles ??= feature('backup_include_files');
        Storage::disk('local')->makeDirectory(self::DIR);

        $name = 'mapanne-'.now()->format('Ymd-His').($label ? '-'.$label : '').'.zip';
        $dump = tempnam(sys_get_temp_dir(), 'mpdump');

        try {
            self::run('pg_dump', ['--format=custom', '--no-owner', '--file='.$dump, self::db('database')]);

            $zip = new ZipArchive;
            if ($zip->open(self::path($name), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Impossible de créer l\'archive.');
            }
            $zip->addFile($dump, 'database.dump');

            $files = 0;
            if ($includeFiles) {
                $root = Storage::disk('public')->path('');
                foreach (File::allFiles($root) as $file) {
                    $zip->addFile($file->getRealPath(), 'files/'.str_replace('\\', '/', $file->getRelativePathname()));
                    $files++;
                }
            }
            $zip->addFromString('manifest.json', json_encode([
                'application' => setting('site_name'),
                'created_at' => now()->toIso8601String(),
                'database' => self::db('database'),
                'files' => $files,
                'encrypted' => self::password() !== null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            // Chiffrement AES-256 de chaque fichier si un mot de passe est défini (Réglages › Sauvegardes).
            if ($password = self::password()) {
                $zip->setPassword($password);
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    if ($zip->getNameIndex($i) !== 'manifest.json') {
                        $zip->setEncryptionIndex($i, ZipArchive::EM_AES_256);
                    }
                }
            }
            $zip->close();
        } finally {
            @unlink($dump);
        }

        self::prune();

        return $name;
    }

    /**
     * Restaure une sauvegarde : la base est remplacée (dans une transaction : en cas d'erreur, rien ne change),
     * puis les fichiers. Une sauvegarde de sécurité de l'état actuel est faite avant.
     */
    public static function restore(string $name): string
    {
        $path = self::path($name);
        $safety = self::create(true, 'avantrestauration');

        $tmp = storage_path('app/private/restore-'.Str::random(8));
        File::ensureDirectoryExists($tmp);
        try {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true || $zip->locateName('database.dump') === false) {
                throw new RuntimeException('Archive invalide : database.dump introuvable.');
            }
            if ($password = self::password()) {
                $zip->setPassword($password);
            }
            if (! $zip->extractTo($tmp)) {
                throw new RuntimeException('Impossible d\'ouvrir l\'archive : mot de passe de chiffrement incorrect ou manquant.');
            }
            $zip->close();

            self::run('pg_restore', ['--clean', '--if-exists', '--no-owner', '--single-transaction', '--dbname='.self::db('database'), $tmp.'/database.dump']);

            if (File::isDirectory($tmp.'/files')) {
                File::copyDirectory($tmp.'/files', Storage::disk('public')->path(''));
            }
        } finally {
            File::deleteDirectory($tmp);
        }

        Setting::flush();
        cache()->flush();

        return $safety;
    }

    /** Mot de passe de chiffrement (Réglages › Sauvegardes), ou null si non défini. */
    public static function password(): ?string
    {
        $password = (string) setting('backup_password');

        return $password !== '' && defined('ZipArchive::EM_AES_256') ? $password : null;
    }

    /** Supprime les plus anciennes au-delà du nombre à conserver (les sauvegardes de sécurité comptent). */
    public static function prune(): void
    {
        $keep = max(1, (int) setting('backup_keep'));
        foreach (array_slice(self::list(), $keep) as $old) {
            Storage::disk('local')->delete(self::DIR.'/'.$old['name']);
        }
    }

    /** Emplacement de l'outil PostgreSQL (réglage admin, dossiers usuels, sinon le PATH). */
    public static function binary(string $tool): string
    {
        $exe = PHP_OS_FAMILY === 'Windows' ? $tool.'.exe' : $tool;
        $dirs = array_filter([trim((string) setting('backup_pg_dump_path'))]);
        if (PHP_OS_FAMILY === 'Windows') {
            $dirs = array_merge($dirs, array_reverse(glob('C:/Program Files/PostgreSQL/*/bin') ?: []));
        } else {
            $dirs = array_merge($dirs, ['/usr/bin', '/usr/local/bin', '/usr/lib/postgresql/18/bin', '/usr/lib/postgresql/17/bin', '/usr/lib/postgresql/16/bin']);
        }
        foreach ($dirs as $dir) {
            $candidate = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$exe;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $tool;
    }

    private static function run(string $tool, array $args): void
    {
        $result = Process::timeout(900)
            ->env(['PGPASSWORD' => (string) self::db('password')])
            ->run(array_merge([self::binary($tool), '--host='.self::db('host'), '--port='.self::db('port'), '--username='.self::db('username')], $args));

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: "{$tool} a échoué. Vérifiez le chemin des outils PostgreSQL dans Réglages › Sauvegardes.");
        }
    }

    private static function db(string $key): mixed
    {
        return config('database.connections.'.config('database.default').'.'.$key);
    }
}
