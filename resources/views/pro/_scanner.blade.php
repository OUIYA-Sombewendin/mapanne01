{{-- Fenêtre de scan (caméra du téléphone). À inclure dans un élément x-data="barcodeScanner". --}}
<div x-show="open" x-cloak class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 bg-black/90 p-4" @keydown.escape.window="stop()">
    <video x-ref="video" playsinline muted class="max-h-[60vh] w-full max-w-md rounded-2xl bg-black"></video>
    <p class="text-center text-sm text-white" x-text="error || 'Visez le code-barres de la pièce'"></p>
    <button type="button" @click="stop()" class="btn bg-white text-ink-900">Fermer</button>
</div>
