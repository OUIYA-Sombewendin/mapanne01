import Alpine from 'alpinejs';

/*
 * Leaflet n'est chargé que sur les pages qui affichent une carte (import dynamique) :
 * la page d'accueil et les formulaires restent légers sur une connexion lente.
 */
let leaflet;
async function loadLeaflet() {
    if (!leaflet) {
        const [mod] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')]);
        leaflet = mod.default;
    }
    return leaflet;
}

function tiles(L) {
    return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    });
}

const escapeHtml = (s) =>
    String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const pin = (L, emoji, extra = '') =>
    L.divIcon({
        className: '',
        html: `<div class="mp-pin ${extra}">${emoji}</div>`,
        iconSize: [36, 36],
        iconAnchor: [18, 36],
        popupAnchor: [0, -34],
    });

const mePin = (L) => L.divIcon({ className: '', html: '<div class="mp-pin-me"></div>', iconSize: [16, 16], iconAnchor: [8, 8] });

function locate() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('La géolocalisation n\'est pas disponible sur cet appareil.'));
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
            (err) =>
                reject(
                    new Error(
                        err.code === 1
                            ? 'Autorisez l\'accès à votre position, ou choisissez votre quartier.'
                            : 'Position introuvable. Choisissez votre quartier.',
                    ),
                ),
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 },
        );
    });
}

/* Carte des résultats de recherche. */
Alpine.data('resultsMap', (markers, center, hasCenter) => ({
    async init() {
        const L = await loadLeaflet();
        // La carte reste hors de l'état Alpine : un proxy réactif casse les calculs internes de Leaflet.
        const map = L.map(this.$refs.map).setView([center.lat, center.lng], hasCenter ? 13 : 12);
        tiles(L).addTo(map);

        const points = [];
        if (hasCenter) {
            L.marker([center.lat, center.lng], { icon: mePin(L) }).addTo(map).bindPopup(escapeHtml(center.label || 'Centre de recherche'));
            points.push([center.lat, center.lng]);
        }
        markers.forEach((m) => {
            L.marker([m.lat, m.lng], { icon: pin(L, m.icon, m.open ? '' : 'is-closed') })
                .addTo(map)
                .bindPopup(`<a href="${m.url}" style="font-weight:600">${escapeHtml(m.name)}</a><br><small>${escapeHtml(m.meta)}</small>`);
            points.push([m.lat, m.lng]);
        });

        const fit = () => points.length > 1 && map.fitBounds(points, { padding: [30, 30], maxZoom: 15 });
        fit();

        // Sur mobile la carte est dans un onglet masqué : à l'affichage, Leaflet doit
        // recalculer sa taille et le cadrage (calculé à tort sur une taille nulle).
        this.refresh = () =>
            setTimeout(() => {
                map.invalidateSize();
                fit();
            }, 60);
    },
    refresh() {},
}));

/* Petite carte non interactive (fiche prestataire). */
Alpine.data('staticMap', (lat, lng, emoji) => ({
    async init() {
        const L = await loadLeaflet();
        const map = L.map(this.$refs.map, { scrollWheelZoom: false, dragging: !L.Browser.mobile }).setView([lat, lng], 15);
        tiles(L).addTo(map);
        L.marker([lat, lng], { icon: pin(L, emoji) }).addTo(map);
    },
}));

/* Bouton « Autour de moi » : remplit lat/lng du formulaire et l'envoie. */
Alpine.data('geoSearch', () => ({
    loading: false,
    error: '',
    async locate() {
        this.loading = true;
        this.error = '';
        try {
            const { lat, lng } = await locate();
            const form = this.$el.closest('form');
            form.querySelector('[name=lat]').value = lat.toFixed(6);
            form.querySelector('[name=lng]').value = lng.toFixed(6);
            form.querySelectorAll('[name=neighborhood],[name=city]').forEach((el) => (el.value = ''));
            form.submit();
        } catch (e) {
            this.error = e.message;
            this.loading = false;
        }
    },
}));

/* Listes liées Ville → Quartier. */
Alpine.data('locationSelect', (cities, cityId, neighborhoodId) => ({
    cities,
    cityId: cityId ? String(cityId) : '',
    neighborhoodId: neighborhoodId ? String(neighborhoodId) : '',
    get neighborhoods() {
        return this.cities.find((c) => String(c.id) === this.cityId)?.neighborhoods ?? [];
    },
    cityChanged() {
        this.neighborhoodId = '';
        this.$dispatch('location-changed', this.selected());
    },
    neighborhoodChanged() {
        this.$dispatch('location-changed', this.selected());
    },
    selected() {
        const city = this.cities.find((c) => String(c.id) === this.cityId);
        const nb = city?.neighborhoods.find((n) => String(n.id) === this.neighborhoodId);
        const src = nb ?? city;
        return src?.latitude ? { lat: Number(src.latitude), lng: Number(src.longitude) } : null;
    },
}));

/* Placement de l'établissement sur la carte (espace prestataire). */
Alpine.data('locationPicker', (lat, lng) => ({
    lat,
    lng,
    error: '',
    loading: false,
    async init() {
        const L = await loadLeaflet();
        const start = lat && lng ? [lat, lng] : [12.3714, -1.5197];
        // Carte et épingle hors de l'état Alpine (voir resultsMap).
        const map = L.map(this.$refs.map).setView(start, lat ? 16 : 12);
        tiles(L).addTo(map);
        const marker = L.marker(start, { draggable: true, icon: pin(L, '📍') }).addTo(map);

        const set = (p, move = false) => {
            this.lat = Number(p.lat.toFixed(7));
            this.lng = Number(p.lng.toFixed(7));
            if (move) marker.setLatLng([this.lat, this.lng]);
        };
        marker.on('dragend', () => set(marker.getLatLng()));
        map.on('click', (e) => set(e.latlng, true));

        this.moveTo = (point, zoom = 15) => {
            if (!point) return;
            set(point, true);
            map.setView([point.lat, point.lng], zoom);
        };
    },
    moveTo() {},
    async useMyPosition() {
        this.loading = true;
        this.error = '';
        try {
            this.moveTo(await locate(), 17);
        } catch (e) {
            this.error = e.message;
        }
        this.loading = false;
    },
}));

// Administration : menu réductible et mode sombre, mémorisés sur l'appareil.
const remember = {
    get: (k) => { try { return localStorage.getItem(k) === '1'; } catch { return false; } },
    set: (k, v) => { try { localStorage.setItem(k, v ? '1' : '0'); } catch { /* stockage indisponible */ } },
};
Alpine.data('adminShell', () => ({
    menu: false,
    collapsed: remember.get('mp-admin-collapsed'),
    dark: remember.get('mp-admin-dark'),
    toggleCollapse() { this.collapsed = !this.collapsed; remember.set('mp-admin-collapsed', this.collapsed); },
    toggleDark() { this.dark = !this.dark; remember.set('mp-admin-dark', this.dark); },
    // Le menu latéral reprend sa position d'une page à l'autre et montre toujours l'entrée active.
    followActive(nav) {
        try { nav.scrollTop = Number(sessionStorage.getItem('mp-admin-nav') || 0); } catch { /* indisponible */ }
        const active = nav.querySelector('[data-active]');
        if (!active) return;
        const a = active.getBoundingClientRect();
        const n = nav.getBoundingClientRect();
        if (a.top < n.top + 40 || a.bottom > n.bottom - 40) {
            nav.scrollTop += a.top - n.top - n.height / 3;
        }
    },
    rememberScroll(nav) {
        try { sessionStorage.setItem('mp-admin-nav', String(nav.scrollTop)); } catch { /* indisponible */ }
    },
}));

// Formulaire de rôle : compteur, recherche, tout cocher par groupe ou en entier.
Alpine.data('roleForm', (count, total) => ({
    count,
    total,
    search: '',
    tick: 0,
    boxes(scope) { return [...(scope || this.$root).querySelectorAll('input[name="permissions[]"]')]; },
    recount() { this.count = this.boxes().filter((b) => b.checked).length; this.tick++; },
    setAll(on) { this.boxes().forEach((b) => { b.checked = on; }); this.recount(); },
    setGroup(group, on) { this.boxes(group).forEach((b) => { b.checked = on; }); this.recount(); },
    groupCount(group) { this.tick; return group ? this.boxes(group).filter((b) => b.checked).length : 0; },
    groupFull(group) { this.tick; const all = group ? this.boxes(group) : []; return all.length > 0 && all.every((b) => b.checked); },
    matches(el) { return !this.search || el.dataset.perm.includes(this.search.toLowerCase()); },
    groupVisible(el) { this.search; return !this.search || [...el.querySelectorAll('[data-perm]')].some((p) => this.matches(p)); },
}));

// Filtres instantanés : chaque choix relance la liste ; la saisie de texte attend une courte pause.
// Après rechargement, le champ en cours de saisie retrouve le focus (curseur à la fin).
Alpine.data('autoFilter', (extra = {}) => ({
    ...extra,
    init() {
        let name = null;
        try { name = sessionStorage.getItem('mp-filter-focus'); sessionStorage.removeItem('mp-filter-focus'); } catch { /* indisponible */ }
        const field = name && this.$el.querySelector(`[name="${CSS.escape(name)}"]`);
        if (field && typeof field.setSelectionRange === 'function') {
            field.focus();
            field.setSelectionRange(field.value.length, field.value.length);
        }
        this.$el.addEventListener('change', (e) => { if (!this.isText(e.target)) this.submit(); });
        let timer;
        this.$el.addEventListener('input', (e) => {
            if (!this.isText(e.target)) return;
            clearTimeout(timer);
            timer = setTimeout(() => this.submit(e.target), 600);
        });
    },
    isText(el) { return el.matches('input[type=search], input[type=text], input:not([type])'); },
    submit(focused = null) {
        if (focused?.name) { try { sessionStorage.setItem('mp-filter-focus', focused.name); } catch { /* indisponible */ } }
        // Adresse propre : sans champs vides ni doublons (le dernier gagne), retour à la page 1.
        const params = new Map();
        for (const [name, value] of new FormData(this.$el)) {
            if (name.endsWith('[]')) { if (value !== '') params.set(`${name}#${params.size}`, [name, value]); continue; }
            params.delete(name);
            if (value !== '') params.set(name, [name, value]);
        }
        const query = new URLSearchParams([...params.values()]).toString();
        const action = this.$el.getAttribute('action') || window.location.pathname;
        window.location.assign(query ? `${action}?${query}` : action);
    },
}));

/* Dépannage en direct : position GPS du conducteur (ou ville et quartier à défaut). */
Alpine.data('assistForm', (old) => ({
    vehicle: old.vehicle || 'moto',
    energy: old.energy || 'thermique',
    problem: old.problem || '',
    lat: old.lat || '',
    lng: old.lng || '',
    locating: false,
    error: '',
    init() {
        if (!this.lat) this.locate();
    },
    async locate() {
        this.locating = true;
        this.error = '';
        try {
            const { lat, lng } = await locate();
            this.lat = lat.toFixed(6);
            this.lng = lng.toFixed(6);
        } catch (e) {
            this.error = e.message;
        }
        this.locating = false;
    },
}));

/* Suivi d'une demande de dépannage : l'état est rafraîchi toutes les 10 secondes. */
Alpine.data('assistTracker', (url, state) => ({
    state,
    init() {
        if (!this.state.active) return;
        const timer = setInterval(async () => {
            try {
                const next = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();
                if (next.status !== this.state.status) {
                    clearInterval(timer);
                    window.location.reload();
                }
            } catch (e) {
                /* réseau coupé : on réessaie au tour suivant */
            }
        }, 10000);
    },
}));

/* Espace pro : recharge la page des dépannages quand une nouvelle demande arrive. */
Alpine.data('assistWatcher', (url, count) => ({
    init() {
        setInterval(async () => {
            try {
                const { open } = await (await fetch(url, { headers: { Accept: 'application/json' } })).json();
                if (open !== count) window.location.reload();
            } catch (e) {
                /* réseau coupé */
            }
        }, 20000);
    },
}));

/* Notifications push : abonnement de cet appareil (clé VAPID publique dans <meta name="vapid-key">). */
const urlBase64ToUint8Array = (base64) => {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
};

Alpine.data('pushToggle', () => ({
    supported: 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window,
    enabled: false,
    busy: false,
    error: '',
    async init() {
        if (!this.supported) return;
        const reg = await navigator.serviceWorker.getRegistration('/sw.js');
        this.enabled = Boolean(reg && (await reg.pushManager.getSubscription()) && Notification.permission === 'granted');
    },
    csrf() {
        return document.querySelector('meta[name=csrf-token]').content;
    },
    async toggle() {
        this.busy = true;
        this.error = '';
        try {
            const reg = await navigator.serviceWorker.register('/sw.js');
            await navigator.serviceWorker.ready;
            let sub = await reg.pushManager.getSubscription();
            if (this.enabled && sub) {
                await fetch('/notifications/push', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' },
                    body: JSON.stringify({ endpoint: sub.endpoint }),
                });
                await sub.unsubscribe();
                this.enabled = false;
            } else {
                if ((await Notification.requestPermission()) !== 'granted') {
                    throw new Error('Autorisez les notifications dans votre navigateur.');
                }
                const key = document.querySelector('meta[name=vapid-key]').content;
                sub = sub || (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(key) }));
                const res = await fetch('/notifications/push', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf(), Accept: 'application/json' },
                    body: JSON.stringify({ ...sub.toJSON(), contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0] }),
                });
                if (!res.ok) throw new Error('Activation impossible pour le moment.');
                this.enabled = true;
            }
        } catch (e) {
            this.error = e.message;
        }
        this.busy = false;
    },
}));

window.Alpine = Alpine;
Alpine.start();
