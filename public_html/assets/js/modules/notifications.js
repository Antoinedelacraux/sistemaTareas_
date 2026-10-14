/**
 * MODULO NOTIFICATIONS — BANDEJA DE ALERTAS Y POLLING (MVP-08)
 * TAL S.A. — Área de Planeamiento
 */

import { apiFetch } from './api.js';
import { STATE } from './state.js';
import { escapeHtml, formatRelativeTime, showToast } from './ui.js';

let onNotificationClickCallback = null;
let pollTimer = null;

export function registerNotificationCallbacks({ onNotificationClick }) {
    if (onNotificationClick) onNotificationClickCallback = onNotificationClick;
}

/**
 * Consulta el resumen de notificaciones no leídas para el badge.
 */
export async function fetchNotificationsSummary() {
    if (!STATE.currentUser) return;
    try {
        const res = await apiFetch('/api/notificaciones/resumen');
        if (!res.ok) return;
        const result = await res.json();
        if (result.success && result.data) {
            updateNotificationBadge(result.data.no_leidas || 0);
        }
    } catch (err) {
        console.warn('Error al consultar resumen de notificaciones:', err);
    }
}

export function updateNotificationBadge(count) {
    const badge = document.getElementById('notif-badge');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'inline-block';
        badge.setAttribute('aria-label', `${count} notificaciones no leídas`);
    } else {
        badge.textContent = '0';
        badge.style.display = 'none';
        badge.removeAttribute('aria-label');
    }
}

/**
 * Carga las notificaciones completas en el dropdown.
 */
export async function loadNotifications() {
    const listContainer = document.getElementById('notif-items-list');
    if (!listContainer) return;

    listContainer.innerHTML = '<div class="notif-empty-state">Cargando notificaciones...</div>';

    try {
        const res = await apiFetch('/api/notificaciones');
        const result = await res.json();

        if (result.success && result.data && Array.isArray(result.data.notificaciones)) {
            renderNotifications(result.data.notificaciones);
            updateNotificationBadge(result.data.no_leidas || 0);
        } else {
            listContainer.innerHTML = '<div class="notif-empty-state">No se pudieron cargar las alertas</div>';
        }
    } catch {
        listContainer.innerHTML = '<div class="notif-empty-state">Error de red al cargar notificaciones</div>';
    }
}

export function renderNotifications(notifs) {
    const listContainer = document.getElementById('notif-items-list');
    if (!listContainer) return;

    if (!notifs || notifs.length === 0) {
        listContainer.innerHTML = '<div class="notif-empty-state">No tienes notificaciones pendientes</div>';
        return;
    }

    listContainer.innerHTML = '';
    notifs.forEach(notif => {
        const item = document.createElement('div');
        const isUnread = parseInt(notif.leida, 10) === 0;
        item.className = `notif-item ${isUnread ? 'unread' : 'read'}`;
        item.dataset.id = notif.id;

        const icon = getNotificationIcon(notif.tipo_evento);
        const timeAgo = formatRelativeTime(notif.created_at);

        item.innerHTML = `
            <div class="notif-item-icon" aria-hidden="true">${icon}</div>
            <div class="notif-item-content">
                <div class="notif-item-title">${escapeHtml(notif.titulo || 'Notificación')}</div>
                <div class="notif-item-msg">${escapeHtml(notif.mensaje || '')}</div>
                <div class="notif-item-time">${escapeHtml(timeAgo)}</div>
            </div>
        `;

        item.addEventListener('click', async () => {
            if (isUnread) {
                await markNotificationRead(notif.id);
                item.classList.remove('unread');
                item.classList.add('read');
            }
            const tareaId = notif.entidad_tipo === 'TAREA' ? notif.entidad_id : null;
            if (tareaId && onNotificationClickCallback) {
                const notifDropdown = document.getElementById('notif-dropdown');
                if (notifDropdown) notifDropdown.style.display = 'none';
                onNotificationClickCallback(tareaId);
            }
        });

        listContainer.appendChild(item);
    });
}

export function getNotificationIcon(tipo) {
    switch (tipo) {
        case 'TAREA_ASIGNADA':
            return `<svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>`;
        case 'APROBACION_SOLICITADA':
            return `<svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>`;
        case 'TAREA_APROBADA':
            return `<svg class="icon-svg icon-svg-sm" style="color: #10b981;" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`;
        case 'TAREA_DEVUELTA':
            return `<svg class="icon-svg icon-svg-sm" style="color: #f59e0b;" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`;
        case 'TAREA_DESBLOQUEADA':
            return `<svg class="icon-svg icon-svg-sm" style="color: #3b82f6;" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg>`;
        case 'NUEVO_COMENTARIO':
            return `<svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>`;
        default:
            return `<svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>`;
    }
}

export async function markNotificationRead(notifId) {
    try {
        const res = await apiFetch(`/api/notificaciones/${notifId}/leer`, { method: 'PATCH' });
        if (res.ok) {
            fetchNotificationsSummary();
        }
    } catch (e) {
        console.warn('Error al marcar leída:', e);
    }
}

export async function markAllNotificationsRead() {
    try {
        const res = await apiFetch('/api/notificaciones/marcar-todas-leidas', { method: 'POST' });
        const result = await res.json();
        if (result.success) {
            showToast('Todas las notificaciones fueron marcadas como leídas', 'success');
            updateNotificationBadge(0);
            loadNotifications();
        } else {
            showToast(result.error?.message || 'Error al actualizar notificaciones', 'error');
        }
    } catch {
        showToast('Error de red al marcar notificaciones', 'error');
    }
}

/**
 * Inicia el temporizador de sondeo en segundo plano (cada 30s).
 */
export function startNotificationPolling() {
    if (pollTimer) clearInterval(pollTimer);
    fetchNotificationsSummary();
    pollTimer = setInterval(fetchNotificationsSummary, 30000);
}

export function stopNotificationPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}
