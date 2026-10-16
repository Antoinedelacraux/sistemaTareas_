/**
 * MODULO HISTORY - HISTORIAL DE TAREAS ARCHIVADAS (>24 HORAS FINALIZADAS)
 * TAL S.A. - Area de Planeamiento
 * Acceso exclusivo para Jefatura.
 */

import { apiFetch } from './api.js';
import { isCurrentUserJefe } from './state.js';
import { escapeHtml, formatDate, showToast } from './ui.js';
import { openTaskDetailModal } from './task-modal.js';

let archivedTasksCache = [];
let visibleHistoryTasks = [];
let selectedHistoryTaskIds = new Set();
let historyDeletionInProgress = false;

/** Carga las tareas archivadas desde el backend. */
export async function loadHistoryTasks() {
    if (!isCurrentUserJefe()) {
        showToast('Acceso exclusivo para Jefatura de Area y Jefatura de Personal', 'error');
        return;
    }

    const container = document.getElementById('history-table-tbody');
    const countBadge = document.getElementById('history-total-count');
    if (container) {
        container.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
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
            selectedHistoryTaskIds.clear();
            if (countBadge) countBadge.textContent = `${archivedTasksCache.length} expedientes`;
            renderHistoryTable(archivedTasksCache);
        } else {
            showToast(json.error?.message || 'Error al obtener el historial de tareas', 'error');
            if (container) {
                container.innerHTML = `
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 2rem; color: var(--status-danger-text);">
                            ${escapeHtml(json.error?.message || 'No se pudo cargar el historial.')}
                        </td>
                    </tr>
                `;
            }
        }
    } catch {
        showToast('Error de conexion al cargar el historial', 'error');
    }
}

/** Filtra las tareas archivadas en memoria por busqueda o selects. */
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

    if (prio) filtered = filtered.filter(t => t.prioridad === prio);
    if (member) filtered = filtered.filter(t => parseInt(t.responsable_id, 10) === parseInt(member, 10));

    renderHistoryTable(filtered);
}

/** Renderiza las filas en la tabla de historial. */
export function renderHistoryTable(tasks) {
    const tbody = document.getElementById('history-table-tbody');
    if (!tbody) return;

    visibleHistoryTasks = Array.isArray(tasks) ? tasks : [];

    if (visibleHistoryTasks.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                    <div style="margin-bottom: 0.5rem;"><svg class="icon-svg" style="width: 2rem; height: 2rem; color: var(--text-muted);" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg></div>
                    <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.35rem;">No hay tareas archivadas en el historial</div>
                    <p style="font-size: 0.8rem; max-width: 480px; margin: 0 auto; line-height: 1.4;">Las tareas concluidas se conservan en el tablero activo y pasan automaticamente a este repositorio historico para consulta, trazabilidad y auditoria de Jefatura.</p>
                </td>
            </tr>
        `;
        updateHistorySelectionControls();
        return;
    }

    tbody.innerHTML = visibleHistoryTasks.map(t => {
        const taskId = Number(t.id);
        const prioBadge = `<span class="badge badge-prioridad-${escapeHtml(t.prioridad)}">${escapeHtml(t.prioridad)}</span>`;
        const tipoBadge = '<span class="badge badge-tipo-area">AREA</span>';
        const timeDiffText = t.horas_desde_finalizada !== null
            ? (t.horas_desde_finalizada >= 48
                ? `Hace ${Math.floor(t.horas_desde_finalizada / 24)} dias`
                : `Hace ${Math.floor(t.horas_desde_finalizada)} horas`)
            : 'Archivada';

        return `
            <tr class="history-table-row" onclick="openTaskDetailModal(${taskId})" style="cursor: pointer;">
                <td class="history-selection-cell">
                    <input type="checkbox" class="history-select-checkbox" data-history-task-select="${taskId}"
                        ${selectedHistoryTaskIds.has(taskId) ? 'checked' : ''}
                        aria-label="Seleccionar tarea ${escapeHtml(t.codigo)} del historial"
                        onclick="event.stopPropagation()" onchange="toggleHistorySelection(${taskId}, this.checked)">
                </td>
                <td><span class="task-code" style="font-size: 0.775rem;">${escapeHtml(t.codigo)}</span></td>
                <td>
                    <div style="font-weight: 600; font-size: 0.875rem; color: var(--text-main); line-height: 1.3;">${escapeHtml(t.titulo)}</div>
                    ${t.descripcion ? `<div style="font-size: 0.75rem; color: var(--text-muted); max-width: 420px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.15rem;">${escapeHtml(t.descripcion)}</div>` : ''}
                </td>
                <td>
                    <div style="font-size: 0.825rem; font-weight: 600;">${escapeHtml(t.responsable_nombre || 'Sin asignar')}</div>
                    <div style="font-size: 0.7rem; color: var(--text-muted);">Creado por: ${escapeHtml(t.creador_nombre || 'Sistema')}</div>
                </td>
                <td>${prioBadge}</td>
                <td>${tipoBadge}</td>
                <td>${t.cumplida_en_plazo === true
                    ? '<span class="badge badge-status-success">En plazo</span>'
                    : t.cumplida_en_plazo === false
                        ? '<span class="badge badge-status-danger">Fuera de plazo</span>'
                        : '<span style="font-size: 0.75rem; color: var(--text-muted);">Sin fecha limite</span>'}</td>
                <td>
                    <div style="font-size: 0.8rem; font-weight: 600; color: var(--status-success-text);">${formatDate(t.fecha_finalizada)}</div>
                    <div style="font-size: 0.7rem; color: var(--text-muted);">${timeDiffText}</div>
                </td>
                <td class="history-actions-cell">
                    <div class="history-row-actions">
                        <button type="button" class="btn btn-outline btn-icon history-action-btn"
                            onclick="event.stopPropagation(); openTaskDetailModal(${taskId})"
                            aria-label="Ver tarea cerrada" title="Ver tarea cerrada">
                            <svg class="icon-svg icon-svg-xs" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                        <button type="button" class="btn btn-outline btn-icon history-action-btn history-action-delete"
                            onclick="event.stopPropagation(); deleteHistoryTask(${taskId})"
                            aria-label="Eliminar tarea del historial" title="Eliminar tarea del historial">
                            <svg class="icon-svg icon-svg-xs" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    updateHistorySelectionControls();
}

export function toggleHistorySelection(taskId, checked) {
    const id = Number(taskId);
    if (!Number.isInteger(id)) return;
    if (checked) selectedHistoryTaskIds.add(id);
    else selectedHistoryTaskIds.delete(id);
    updateHistorySelectionControls();
}

export function toggleAllHistorySelection(checked) {
    visibleHistoryTasks.forEach(task => {
        const id = Number(task.id);
        if (checked) selectedHistoryTaskIds.add(id);
        else selectedHistoryTaskIds.delete(id);
    });
    updateHistorySelectionControls();
}

function updateHistorySelectionControls() {
    const selectedVisibleCount = visibleHistoryTasks.filter(task => selectedHistoryTaskIds.has(Number(task.id))).length;
    const selectAll = document.getElementById('history-select-all');
    const deleteSelected = document.getElementById('history-delete-selected-btn');
    const status = document.getElementById('history-selection-status');

    if (selectAll) {
        selectAll.checked = visibleHistoryTasks.length > 0 && selectedVisibleCount === visibleHistoryTasks.length;
        selectAll.indeterminate = selectedVisibleCount > 0 && selectedVisibleCount < visibleHistoryTasks.length;
    }
    if (deleteSelected) deleteSelected.disabled = selectedHistoryTaskIds.size === 0 || historyDeletionInProgress;
    if (status) status.textContent = `${selectedHistoryTaskIds.size} seleccionada(s)`;

    document.querySelectorAll('[data-history-task-select]').forEach(input => {
        input.checked = selectedHistoryTaskIds.has(Number(input.dataset.historyTaskSelect));
    });
}

export async function deleteHistoryTask(taskId) {
    const id = Number(taskId);
    const task = archivedTasksCache.find(item => Number(item.id) === id);
    if (!task || historyDeletionInProgress) return;

    if (!window.confirm(`¿Eliminar permanentemente la tarea ${task.codigo} del historial? Esta accion no se puede deshacer.`)) return;
    await deleteHistoryTaskIds([id]);
}

export async function deleteSelectedHistoryTasks() {
    const ids = Array.from(selectedHistoryTaskIds).filter(id => archivedTasksCache.some(task => Number(task.id) === id));
    if (ids.length === 0 || historyDeletionInProgress) return;

    if (!window.confirm(`¿Eliminar permanentemente ${ids.length} tarea(s) seleccionada(s) del historial? Esta accion no se puede deshacer.`)) return;
    await deleteHistoryTaskIds(ids);
}

async function deleteHistoryTaskIds(taskIds) {
    historyDeletionInProgress = true;
    updateHistorySelectionControls();

    let deletedCount = 0;
    const failures = [];

    try {
        for (const id of taskIds) {
            try {
                const res = await apiFetch(`/api/historial/${id}`, { method: 'DELETE' });
                const json = await res.json();
                if (!json.success) {
                    failures.push(json.error?.message || `No se pudo eliminar la tarea ${id}.`);
                    continue;
                }

                archivedTasksCache = archivedTasksCache.filter(task => Number(task.id) !== id);
                selectedHistoryTaskIds.delete(id);
                deletedCount += 1;
            } catch {
                failures.push(`No se pudo eliminar la tarea ${id} por un error de conexion.`);
            }
        }

        const countBadge = document.getElementById('history-total-count');
        if (countBadge) countBadge.textContent = `${archivedTasksCache.length} expedientes`;
        filterHistoryTasks();

        if (deletedCount > 0) {
            showToast(`${deletedCount} tarea(s) eliminada(s) del historial.`, failures.length ? 'info' : 'success');
        }
        if (failures.length > 0) showToast(failures[0], 'error');
    } finally {
        historyDeletionInProgress = false;
        updateHistorySelectionControls();
    }
}
