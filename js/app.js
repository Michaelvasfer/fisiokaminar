const APP_CACHE_RESET_KEY = 'kaminar_cache_reset_v20260413';
const APP_SW_VERSION = '20260413';

async function resetLegacyAppCachesIfNeeded() {
    if (!('serviceWorker' in navigator) || !('caches' in window)) {
        return;
    }

    if (localStorage.getItem(APP_CACHE_RESET_KEY) === 'done') {
        return;
    }

    let changed = false;

    try {
        const registrations = await navigator.serviceWorker.getRegistrations();
        for (const registration of registrations) {
            const scope = String(registration.scope || '');
            if (scope.includes(window.location.origin)) {
                const unregistered = await registration.unregister();
                changed = changed || !!unregistered;
            }
        }
    } catch (error) {
        console.warn('No se pudo limpiar el service worker anterior', error);
    }

    try {
        const cacheKeys = await caches.keys();
        for (const key of cacheKeys) {
            if (key.startsWith('kaminarfisio-')) {
                const deleted = await caches.delete(key);
                changed = changed || !!deleted;
            }
        }
    } catch (error) {
        console.warn('No se pudo limpiar la cache anterior', error);
    }

    localStorage.setItem(APP_CACHE_RESET_KEY, 'done');

    if (changed && !sessionStorage.getItem(APP_CACHE_RESET_KEY + '_reloaded')) {
        sessionStorage.setItem(APP_CACHE_RESET_KEY + '_reloaded', '1');
        window.location.reload();
    }
}

document.addEventListener('DOMContentLoaded', async function() {
    await resetLegacyAppCachesIfNeeded();
    const progressFills = document.querySelectorAll('.progress-fill');

    progressFills.forEach(fill => {
        const targetWidth = fill.style.width;
        fill.style.width = '0%';

        setTimeout(() => {
            fill.style.width = targetWidth;
        }, 300);
    });

    const clickables = document.querySelectorAll('.list-item, .quick-access-btn, .nav-item');

    clickables.forEach(item => {
        item.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 100);
        });
    });

    console.log('KaminarFisio initialized successfully.');

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('./sw.js?v=' + APP_SW_VERSION)
                .then(reg => console.log('SW Registered!', reg))
                .catch(err => console.error('SW Registration failed:', err));
        });
    }

    window.addEventListener('online', () => {
        document.body.classList.remove('is-offline');
        if (window.showToast) showToast('Conexion restaurada', 'success');
    });

    window.addEventListener('offline', () => {
        document.body.classList.add('is-offline');
        if (window.showToast) showToast('Trabajando en modo offline', 'warning');
    });
});

function sendReminder(name, phone, date, time, therapist) {
    const phoneStr = phone ? String(phone).trim() : '';

    if (!phoneStr || phoneStr === 'null') {
        if (window.showToast) showToast('Sin telefono registrado', 'error');
        return;
    }

    let cleanPhone = phoneStr.replace(/\D/g, '');
    if (cleanPhone.length === 9) {
        cleanPhone = '51' + cleanPhone;
    }

    const drName = (therapist && therapist !== 'null') ? therapist : 'el equipo de KaminarFisio';

    const message = `Hola ${name}, te recordamos tu cita en *KaminarFisio*:\n\n- Fecha: ${date}\n- Hora: ${time}\n- Fisioterapeuta: ${drName}\n\nTe esperamos.`;
    const url = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;

    window.open(url, '_blank');
}

async function markAppointment(id, status) {
    const label = status === 'completed' ? 'completada' : 'cancelada';
    if (!confirm('Marcar esta cita como ' + label + '?')) return;

    try {
        const res = await (window.SyncManager ? SyncManager.fetch('api/appointments.php', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id, status })
        }) : fetch('api/appointments.php', {
            method: 'PUT',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id, status })
        }));

        const json = await res.json();
        if (json.success) {
            const msg = json.offline ? 'Guardado offline' : 'Cita ' + label;
            if (window.showToast) showToast(msg, json.offline ? 'warning' : 'success');

            if (!json.offline && typeof window.handleAppointmentStatusChanged === 'function') {
                window.handleAppointmentStatusChanged(id, status);
            } else if (!json.offline) {
                setTimeout(() => window.location.reload(), 1000);
            }
        } else if (window.showToast) {
            showToast(json.error, 'error');
        }
    } catch (e) {
        if (window.showToast) showToast('Error de conexion', 'error');
    }
}

async function deleteAppointment(id) {
    if (!confirm('Eliminar esta cita permanentemente?')) return;

    try {
        const res = await fetch('api/appointments.php', {
            method: 'DELETE',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ id })
        });
        const json = await res.json();

        if (json.success) {
            if (window.showToast) showToast('Cita eliminada', 'success');

            if (typeof window.handleAppointmentDeleted === 'function') {
                window.handleAppointmentDeleted(id);
            } else {
                const el = document.getElementById('apt-' + id) || document.getElementById('appointment-' + id);
                if (el) el.remove();
                else setTimeout(() => window.location.reload(), 800);
            }
        } else if (window.showToast) {
            showToast(json.error, 'error');
        }
    } catch (e) {
        if (window.showToast) showToast('Error de conexion', 'error');
    }
}
