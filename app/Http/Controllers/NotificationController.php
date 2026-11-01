<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Notifications affichées dans la cloche (validation de la fiche, demandes de modification…). */
class NotificationController extends Controller
{
    /** Ouvre une notification : elle est marquée comme lue, puis on suit son lien. */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = (string) ($notification->data['url'] ?? '');

        // Seulement un lien de MaPanne (jamais une adresse extérieure).
        return str_starts_with($url, url('/')) ? redirect($url) : redirect($request->user()->homeRoute());
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
