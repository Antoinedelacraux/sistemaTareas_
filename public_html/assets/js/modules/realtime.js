/**
 * Sincronización operativa por espera larga compatible con DonWeb Ferozo.
 * Un único ciclo consulta eventos autorizados y se reconecta de forma segura.
 */

import { apiFetch } from './api.js';
import { STATE } from './state.js';

let running = false;
let cursor = 0;
let abortController = null;
let eventsCallback = null;
let retryDelay = 1000;

export function registerRealtimeCallback(callback) {
    eventsCallback = callback;
}

export async function startRealtimeSync() {
    if (running || !STATE.currentUser) return;
    running = true;
    retryDelay = 1000;
    setStatus('connecting');

    try {
        const res = await apiFetch('/api/sincronizacion/cursor', { cache: 'no-store' });
        const result = await res.json();
        if (!res.ok || !result.success) throw new Error('No se pudo iniciar la sincronización');
        cursor = Number(result.data?.cursor || 0);
        setStatus('live');
        void listen();
    } catch (error) {
        console.warn('Sincronización en vivo no disponible:', error);
        setStatus('retrying');
        scheduleRetry();
    }
}

export function stopRealtimeSync() {
    running = false;
    if (abortController) abortController.abort();
    abortController = null;
    setStatus('offline');
}

async function listen() {
    if (!running || !STATE.currentUser) return;
    abortController = new AbortController();
    try {
        const res = await apiFetch(`/api/sincronizacion/eventos?after=${encodeURIComponent(cursor)}&wait=20`, {
            cache: 'no-store',
            signal: abortController.signal,
        });

        if (res.status === 401 || res.status === 403) {
            running = false;
            setStatus('offline');
            return;
        }

        const result = await res.json();
        if (!res.ok || !result.success) throw new Error(result.error?.message || 'Respuesta de sincronización inválida');

        const nextCursor = Number(result.data?.cursor || cursor);
        cursor = Math.max(cursor, nextCursor);
        retryDelay = 1000;
        setStatus('live');

        const events = Array.isArray(result.data?.eventos) ? result.data.eventos : [];
        if (events.length > 0 && eventsCallback) {
            await eventsCallback(events);
        }
    } catch (error) {
        if (!running || error?.name === 'AbortError') return;
        console.warn('Conexión de sincronización interrumpida:', error);
        setStatus('retrying');
        scheduleRetry();
        return;
    } finally {
        abortController = null;
    }

    if (running) void listen();
}

function scheduleRetry() {
    if (!running) return;
    const delay = retryDelay;
    retryDelay = Math.min(retryDelay * 2, 15000);
    window.setTimeout(() => {
        if (running) void listen();
    }, delay);
}

function setStatus(status) {
    const element = document.getElementById('realtime-status');
    if (!element) return;
    const labels = {
        connecting: 'Conectando en vivo',
        live: 'Actualización en vivo',
        retrying: 'Reconectando',
        offline: 'Sin conexión en vivo',
    };
    element.dataset.status = status;
    element.textContent = labels[status] || labels.offline;
    element.setAttribute('aria-label', element.textContent);
}
