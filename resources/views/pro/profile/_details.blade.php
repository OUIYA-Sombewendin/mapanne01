{{-- Une section de la fiche métier. Paramètres : $id, $icon, $title, $text, $fields (champs à afficher, null = tous). --}}
<section id="{{ $id }}" class="card scroll-mt-28 p-6">
    @include('pro._section-head', ['icon' => $icon, 'title' => $title, 'text' => $text ?? ''])
    <x-type-details-form :provider="$provider" :title="false" :only="$fields ?? null" />
</section>
