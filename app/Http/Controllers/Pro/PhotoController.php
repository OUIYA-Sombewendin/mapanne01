<?php

namespace App\Http\Controllers\Pro;

use App\Http\Controllers\Controller;
use App\Models\ProviderPhoto;
use App\Support\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $provider = $request->user()->provider;
        $max = (int) setting('max_photos');

        $request->validate([
            'photos' => ['required', 'array', 'max:'.$max],
            'photos.*' => ['image', 'max:'.(int) setting('max_image_kb')],
        ]);

        if ($provider->photos()->count() + count($request->file('photos')) > $max) {
            return back()->withErrors(['photos' => "Maximum $max photos."]);
        }

        foreach ($request->file('photos') as $file) {
            $provider->photos()->create(['path' => ImageOptimizer::store($file, 'photos')]);
        }

        return back()->with('success', 'Photos ajoutées.');
    }

    public function destroy(Request $request, ProviderPhoto $photo): RedirectResponse
    {
        abort_unless($photo->provider_id === $request->user()->provider->id, 403);

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return back()->with('success', 'Photo supprimée.');
    }
}
