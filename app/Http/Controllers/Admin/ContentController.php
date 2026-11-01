<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\ProviderType;
use App\Support\Listing;
use App\Support\ProviderProfiles;
use App\Support\TradeModules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Types de prestataires et pages de contenu, gérés sans toucher au code. */
class ContentController extends Controller
{
    // Types de prestataires ------------------------------------------------------

    public function types(): View
    {
        return view('admin.types', [
            'types' => ProviderType::query()->ordered()->withCount('providers')->get(),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $data = $this->validatedType($request);
        $data['slug'] = Str::slug($data['name']);
        abort_if(ProviderType::where('slug', $data['slug'])->exists(), 422, 'Ce type existe déjà.');

        $type = ProviderType::create($data + [
            'position' => (int) ProviderType::max('position') + 1,
            'modules' => TradeModules::defaults($data['profile_kind']),
        ]);
        ActivityLog::record('provider_type.created', $type);

        return back()->with('success', 'Type « '.$type->name.' » ajouté.');
    }

    public function updateType(Request $request, ProviderType $type): RedirectResponse
    {
        $type->update($this->validatedType($request));
        ActivityLog::record('provider_type.updated', $type);

        return back()->with('success', 'Type mis à jour.');
    }

    private function validatedType(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'short_name' => ['nullable', 'string', 'max:40'],
            'icon' => ['required', 'string', 'max:16'],
            'description' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $kind = $request->validate(['profile_kind' => ['nullable', Rule::in(array_keys(ProviderProfiles::kinds()))]])['profile_kind'] ?? null;

        return array_filter($data, fn ($v) => $v !== null) + ['profile_kind' => $kind];
    }

    // Pages de contenu ---------------------------------------------------------------

    public function pages(Request $request): View
    {
        return view('admin.pages.index', ['pages' => Listing::sort(Page::with('lock')
            ->when(Listing::flag($request, 'published') !== null, fn ($q) => $q->where('is_published', Listing::flag($request, 'published'))), $request, [
                'title' => 'title',
                'slug' => 'slug',
                'position' => 'position',
                'updated' => 'updated_at',
            ], 'position', 'asc')->get()]);
    }

    public function createPage(): View
    {
        return view('admin.pages.form', ['page' => new Page(['is_published' => true, 'in_footer' => true])]);
    }

    public function storePage(Request $request): RedirectResponse
    {
        $page = Page::create($this->validatedPage($request));
        ActivityLog::record('page.created', $page);

        return redirect()->route('admin.pages.index')->with('success', 'Page créée.');
    }

    public function editPage(Page $page): View
    {
        return view('admin.pages.form', ['page' => $page]);
    }

    public function updatePage(Request $request, Page $page): RedirectResponse
    {
        $page->update($this->validatedPage($request, $page));
        ActivityLog::record('page.updated', $page);

        return redirect()->route('admin.pages.index')->with('success', 'Page enregistrée.');
    }

    public function destroyPage(Page $page): RedirectResponse
    {
        abort_if(in_array($page->slug, ['conditions-utilisation', 'confidentialite'], true), 422, 'Cette page est obligatoire (liée à l\'inscription).');
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'Page supprimée.');
    }

    private function validatedPage(Request $request, ?Page $page = null): array
    {
        $request->merge(['slug' => Str::slug($request->input('slug') ?: $request->input('title'))]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'body' => ['nullable', 'string', 'max:50000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $data['in_footer'] = $request->boolean('in_footer');
        $data['position'] ??= 0;

        // Les adresses des pages liées à l'inscription ne changent pas.
        if ($page && in_array($page->slug, ['conditions-utilisation', 'confidentialite'], true)) {
            $data['slug'] = $page->slug;
            $data['is_published'] = true;
        }

        return $data;
    }
}
