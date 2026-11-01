/* Service worker MaPanne : affiche les notifications push et ouvre la bonne page au clic. */
self.addEventListener('push', (event) => {
    if (!event.data) return;
    const data = event.data.json();
    event.waitUntil(
        self.registration.showNotification(data.title || 'MaPanne', {
            body: data.body,
            icon: data.icon,
            tag: data.tag,
            data: data.data || {},
            requireInteraction: true,
            vibrate: [200, 100, 200],
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const w of windows) {
                if (w.url === url && 'focus' in w) return w.focus();
            }
            return self.clients.openWindow(url);
        }),
    );
});
