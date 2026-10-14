/**
 * MODULO HISTORY — HISTORIAL DE TAREAS ARCHIVADAS (>24 HORAS FINALIZADAS)
 * TAL S.A. — Área de Planeamiento
 * Acceso exclusivo para Jefe de Área y Jefe de Personal.
 */

import { apiFetch } from './api.js';
import { STATE, isCurrentUserJefe } from './state.js';
import { escapeHtml, formatDate, showToast } from './ui.js';
import { openTaskDetailModal } from './task-modal.js';

let archivedTasksCache = [];

/**
 * Carga las tareas archivadas desde el backend.
 */
export async function loadHistoryTasks() {
    if (!isCurrentUserJefe()) {
        showToast('Acceso exclusivo para Jefatura de Área y Jefatura de Personal', 'error');
        return;
    }

    const container = document.getElementById('history-table-tbody');
    const countBadge = document.getElementById('history-total-count');
    if (container) {
        container.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <span class="status-pulse-dot" style="background-color: var(--primary-500);"></span>
                        <span>Cargando expedientes del historial archivado...</span>
                    </div>
                </td>
            </tr>
        `;
    }

    try {
        const res = await apiFetch('/api/historial');
        const json = await res.json();

        if (json.success && json.data) {
            archivedTasksCache = json.data.tareas || [];
            if (countBadge) countBadge.textContent = `${archivedTasksCache.length} expedientes`;
            renderHistoryTable(archivedTasksCache);
        } else {
            showToast(json.error?.message || 'Error al obtener el historial de tareas', 'error');
            if (container) {
                container.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 2rem; color: #ef4444;">
                            ${escapeHtml(json.error?.message || 'No se pudo cargar el historial.')}
                        </td>
                    </tr>
                `;
            }
        }
    } catch {
        showToast('Error de conexión al cargar el historial', 'error');
    }
}

/**
 * Filtra las tareas archivadas en memoria por búsqueda o selects.
 */
export function filterHistoryTasks() {
    const search = (document.getElementById('history-search-input')?.value || '').toLowerCase().trim();
    const prio = document.getElementById('history-priority-select')?.value || '';
    const member = document.getElementById('history-member-select')?.value || '';

    let filtered = archivedTasksCache;

    if (search) {
        filtered = filtered.filter(t =>
            (t.codigo && t.codigo.toLowerCase().includes(search)) ||
            (t.titulo && t.titulo.toLowerCase().includes(search)) ||
            (t.responsable_nombre && t.responsable_nombre.toLowerCase().includes(search))
        );
    }

    if (prio) {
        filtered = filtered.filter(t => t.prioridad === prio);
    }

    if (member) {
        filtered = filtered.filter(t => parseInt(t.responsable_id, 10) === parseInt(member, 10));
    }

    renderHistoryTable(filtered);
}

/**
 * Renderiza las filas en la tabla de historial.
 */
export function renderHistoryTable(tasks) {
    const tbody = document.getElementById('history-table-tbody');
    if (!tbody) return;

    if (!tasks || tasks.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;" aria-hidden="true">📂</div>
                    <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.35rem;">
                        No hay tareas archivadas en el historial
                    </div>
                    <p style="font-size: 0.8rem; max-width: 480px; margin: 0 auto; line-height: 1.4;">
                        Las tareas finalizadas permanecen un máximo de <strong>24 horas</strong> en la columna de finalizadas del tablero Kanban.
                        Transcurrido ese periodo, se trasladan automáticamente a este módulo reservado para Jefatura.
                    </p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = tasks.map(t => {
        const prioBadge = `<span class="badge badge-prioridad-${t.prioridad}">${t.prioridad}</span>`;
        const tipoBadge = t.tipo_tarea === 'PERSONAL'
            ? `<span class="badge badge-tipo-personal">PERSONAL</span>`
            : `<span class="badge badge-tipo-area">ÁREA</span>`;

        const timeDiffText = t.horas_desde_finalizada !== null
            ? (t.horas_desde_finalizada >= 48
                ? `Hace ${Math.floor(t.horas_desde_finalizada / 24)} días`
                : `Hace ${Math.floor(t.horas_desde_finalizada)} horas`)
            : 'Archivada';

        return `
            <tr class="history-table-row" onclick="openTaskDetailModal(${t.id})" style="cursor: pointer;">
                <td>
                    <span class="task-code" style="font-size: 0.775rem;">${escapeHtml(t.codigo)}</span>
                </td>
                <td>
                    <div style="font-weight: 600; font-size: 0.875rem; color: var(--text-main); line-height: 1.3;">
                        ${escapeHtml(t.titulo)}
                    </div>
                    ${t.descripcion ? `<div style="font-size: 0.75rem; color: var(--text-muted); max-width: 420px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.15rem;">${escapeHtml(t.descripcion)}</div>` : ''}
                </td>
                <td>
                    <div style="font-size: 0.825rem; font-weight: 600;">${escapeHtml(t.responsable_nombre || 'Sin asignar')}</div>
                    <div style="font-size: 0.7rem; color: var(--text-muted);">Creado por: ${escapeHtml(t.creador_nombre || 'Sistema')}</div>
                </td>
                <td>${prioBadge}</td>
                <td>${tipoBadge}</td>
                <td>
                    <div style="font-size: 0.8rem; font-weight: 600; color: #059669;">
                        ${formatDate(t.fecha_finalizada)}
                    </div>
                    <div style="font-size: 0.7rem; color: var(--text-muted);">
                        ${timeDiffText}
                    </div>
                </td>
                <td style="text-align: right;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="event.stopPropagation(); openTaskDetailModal(${t.id})" aria-label="Ver expediente completo de la tarea">
                        <svg class="icon-svg icon-svg-xs" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <span>Expediente</span>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}
