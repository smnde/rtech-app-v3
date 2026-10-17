// Registrasi service worker + prompt update.
// Hanya di produksi: service worker saat dev bikin cache basi dan menutupi HMR.

export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    if (import.meta.env.DEV) return;

    window.addEventListener('load', async () => {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });

            // SW baru siap → tawarkan muat ulang supaya versi terbaru terpakai.
            registration.addEventListener('updatefound', () => {
                const incoming = registration.installing;
                if (!incoming) return;
                incoming.addEventListener('statechange', () => {
                    if (incoming.state === 'installed' && navigator.serviceWorker.controller) {
                        window.dispatchEvent(new CustomEvent('sw:update-ready'));
                    }
                });
            });
        } catch (e) {
            console.warn('[pwa] service worker gagal didaftarkan', e);
        }
    });
}

// Terapkan versi baru: suruh SW menunggu selesai lalu muat ulang sekali.
export function applyServiceWorkerUpdate() {
    navigator.serviceWorker?.getRegistration().then((registration) => {
        registration?.waiting?.postMessage('SKIP_WAITING');
        window.location.reload();
    });
}
