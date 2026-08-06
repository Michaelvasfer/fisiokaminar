/**
 * Sync Manager para KaminarFisio
 * Maneja el almacenamiento de acciones offline y su sincronizacion posterior.
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function withCsrfHeader(options = {}) {
    const token = getCsrfToken();
    const headers = new Headers(options.headers || {});

    if (token) {
        headers.set('X-CSRF-Token', token);
    }

    return {
        ...options,
        headers
    };
}

const originalFetch = window.fetch.bind(window);
window.fetch = function(url, options) {
    return originalFetch(url, withCsrfHeader(options || {}));
};

const SyncManager = {
    queueKey: 'kaminar_sync_queue',

    addToQueue: function(url, options) {
        const queue = JSON.parse(localStorage.getItem(this.queueKey) || '[]');
        queue.push({
            id: Date.now(),
            url,
            options,
            timestamp: new Date().toISOString()
        });
        localStorage.setItem(this.queueKey, JSON.stringify(queue));
        console.log('Accion guardada en cola offline');
    },

    processQueue: async function() {
        const queue = JSON.parse(localStorage.getItem(this.queueKey) || '[]');
        if (queue.length === 0) return;

        console.log(`Sincronizando ${queue.length} acciones pendientes...`);
        const remaining = [];

        for (const action of queue) {
            try {
                const response = await originalFetch(action.url, withCsrfHeader(action.options || {}));
                if (response.ok) {
                    console.log(`Accion ${action.id} sincronizada con exito`);
                } else {
                    remaining.push(action);
                }
            } catch (error) {
                remaining.push(action);
            }
        }

        localStorage.setItem(this.queueKey, JSON.stringify(remaining));
        if (remaining.length === 0) {
            if (window.showToast) showToast('Sincronizacion completada', 'success');
        }
    },

    fetch: async function(url, options) {
        const requestOptions = withCsrfHeader(options || {});

        if (!navigator.onLine) {
            this.addToQueue(url, requestOptions);
            return {
                ok: true,
                json: async () => ({ success: true, offline: true, message: 'Guardado para sincronizar' })
            };
        }

        try {
            return await originalFetch(url, requestOptions);
        } catch (error) {
            this.addToQueue(url, requestOptions);
            return {
                ok: true,
                json: async () => ({ success: true, offline: true, message: 'Falla de red, guardado offline' })
            };
        }
    }
};

window.addEventListener('online', () => SyncManager.processQueue());
