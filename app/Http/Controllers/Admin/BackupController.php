<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Backup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function index(Request $request): View
    {
        $backups = collect(Backup::list());
        $key = $request->query('sort') === 'size' ? 'size' : 'date';
        $backups = $request->query('dir') === 'asc' ? $backups->sortBy($key) : $backups->sortByDesc($key);

        return view('admin.backups', [
            'backups' => $backups->values()->all(),
            'tool' => Backup::binary('pg_dump'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $name = Backup::create($request->boolean('files', feature('backup_include_files')));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => 'La sauvegarde a échoué : '.$e->getMessage()]);
        }
        ActivityLog::record('backup.created', null, ['fichier' => $name]);

        return back()->with('success', "Sauvegarde {$name} créée.");
    }

    public function download(string $file): BinaryFileResponse
    {
        $path = rescue(fn () => Backup::path($file), null, false);
        abort_unless($path && is_file($path), 404);
        ActivityLog::record('backup.downloaded', null, ['fichier' => $file]);

        return response()->download($path);
    }

    public function destroy(string $file): RedirectResponse
    {
        $path = rescue(fn () => Backup::path($file), null, false);
        abort_unless($path && is_file($path), 404);
        Storage::disk('local')->delete(Backup::DIR.'/'.$file);
        ActivityLog::record('backup.deleted', null, ['fichier' => $file]);

        return back()->with('success', 'Sauvegarde supprimée.');
    }

    /** Restauration : réservée au super-admin, confirmée en tapant RESTAURER. */
    public function restore(Request $request, string $file): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Seul un super-administrateur peut restaurer une sauvegarde.');
        $request->validate(['confirm' => ['required', 'in:RESTAURER']], ['confirm.in' => 'Tapez RESTAURER en majuscules pour confirmer.']);
        $path = rescue(fn () => Backup::path($file), null, false);
        abort_unless($path && is_file($path), 404);

        try {
            $safety = Backup::restore($file);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => 'La restauration a échoué, la base n\'a pas été modifiée : '.$e->getMessage()]);
        }
        ActivityLog::record('backup.restored', null, ['fichier' => $file, 'sauvegarde_de_sécurité' => $safety]);

        return back()->with('success', "Sauvegarde {$file} restaurée. L'état précédent a été conservé dans {$safety}.");
    }
}
