/**
 * MODULO APPROVALS — CIRCUITO FORMAL DE APROBACION DE JEFATURA (MVP-06)
 * TAL S.A. — Área de Planeamiento
 */

import { apiFetch } from './api.js';
import { STATE, isCurrentUserJefe } from './state.js';
import { escapeHtml, formatDate, showToast, openModal, closeModal } from './ui.js';
import { fetchTasks } from './kanban.js?v=1.6.1';

let onApprovalActionCallback = null;

export function registerApprovalCallbacks({ onAction }) {
    if (onAction) onApprovalActionCallback = onAction;
}

/**
 * Renderiza la caja de estado y acciones de aprobación en el modal de detalle.
 */
export function renderApprovalBox(task, approvals) {
    const box = document.getElementById('detail-approval-box');
    if (!box) return;

    if (!task.requiere_aprobacion) {
        box.style.display = 'block';
        box.className = 'approval-detail-box';
        box.innerHTML = `
            <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <span>Esta tarea no requiere circuito formal de aprobación (cierre directo con confirmación de finalización).</span>
            </div>
        `;
        return;
    }

    box.style.display = 'block';
    const user = STATE.currentUser;
    const isJefe = isCurrentUserJefe();
    const isOwnerOrCreator = user && (parseInt(task.responsable_id, 10) === parseInt(user.id, 10) || parseInt(task.creador_id, 10) === parseInt(user.id, 10));

    const devueltas = approvals && Array.isArray(approvals) ? approvals.filter(a => a.estado === 'DEVUELTA') : [];
    const ultimaDevuelta = devueltas.length > 0 ? devueltas[0] : null;

    let html = `
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <div style="display: flex; align-items: center; gap: 0.4rem;">
                <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);">Circuito de Aprobación Formal</span>
            </div>
            <span class="badge ${task.estado === 'FINALIZADA' ? 'badge-prioridad-BAJA' : (task.estado === 'EN_REVISION' ? 'badge-prioridad-ALTA' : 'badge-tipo-area')}">
                ${task.estado === 'FINALIZADA' ? 'APROBADA Y CERRADA' : (task.estado === 'EN_REVISION' ? 'EN REVISIÓN' : 'REQUIERE APROBACIÓN')}
            </span>
        </div>
    `;

    // Regla RN-12: Si fue devuelta previamente, visibilizar observaciones de forma prominente
    if (ultimaDevuelta && task.estado !== 'FINALIZADA') {
        html += `
            <div class="approval-returned-notice" style="border-radius: var(--radius-sm); padding: 0.6rem 0.75rem; margin-bottom: 0.75rem;">
                <div class="approval-returned-title" style="font-size: 0.75rem; font-weight: 700; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.35rem;">
                    <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <span>Observaciones de Devolución (${formatDate(ultimaDevuelta.resolved_at)}):</span>
                </div>
                <div class="approval-returned-body" style="font-size: 0.8rem; white-space: pre-wrap;">${escapeHtml(ultimaDevuelta.observaciones)}</div>
            </div>
        `;
    }

    if (task.estado === 'EN_REVISION') {
        box.className = 'approval-detail-box pending';
        html += `
            <p style="font-size: 0.8rem; color: var(--text-main); margin-bottom: 0.75rem;">
                Esta tarea ha concluido su ejecución y está pendiente del dictamen técnico de Jefatura.
            </p>
        `;

        if (isJefe) {
            html += `
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <button class="btn btn-success btn-sm" onclick="approveTask(${task.id})">
                        <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <span>Aprobar y Finalizar</span>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="openReturnModal(${task.id}, '${task.codigo}')">
                        <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                        <span>Devolver con Observaciones</span>
                    </button>
                </div>
            `;
        } else {
            html += `
                <div style="font-size: 0.775rem; color: var(--text-muted); font-style: italic;">
                    Esperando resolución formal de Jefatura.
                </div>
            `;
        }
    } else if (task.estado === 'FINALIZADA') {
        box.className = 'approval-detail-box approved';
        html += `
            <p class="approval-approved-message" style="font-size: 0.8rem; margin: 0; display: flex; align-items: center; gap: 0.35rem;">
                <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <span>Tarea validada y aprobada formalmente. Ciclo completado satisfactoriamente.</span>
            </p>
        `;
    } else {
        box.className = 'approval-detail-box';
        if (isOwnerOrCreator || isJefe) {
            html += `
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.65rem;">
                    Al concluir el desarrollo y checklist, envía la tarea a Jefatura para su evaluación formal.
                </p>
                <button class="btn btn-primary btn-sm" onclick="requestApproval(${task.id})">
                    <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    <span>Enviar a Aprobación (Jefatura)</span>
                </button>
            `;
        } else {
            html += `
                <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                    Esta tarea pasará a revisión formal cuando el responsable solicite su aprobación.
                </p>
            `;
        }
    }

    box.innerHTML = html;
}

/**
 * Consulta el contador de aprobaciones pendientes para jefatura.
 */
export async function fetchPendingApprovalsCount() {
    if (!isCurrentUserJefe()) return;
    try {
        const res = await apiFetch('/api/aprobaciones/pendientes');
        if (!res.ok) return;
        const json = await res.json();
        if (json.success && Array.isArray(json.data)) {
            STATE.pendingApprovals = json.data;
            const badge = document.getElementById('approvals-badge');
            if (badge) {
                const count = json.data.length;
                badge.textContent = count;
                badge.style.display = count > 0 ? 'inline-block' : 'none';
            }
        }
    } catch (e) {
        console.warn('Error al consultar contador de aprobaciones pendientes', e);
    }
}

/**
 * Carga y renderiza la bandeja de aprobaciones pendientes.
 */
export async function fetchPendingApprovals() {
    try {
        const res = await apiFetch('/api/aprobaciones/pendientes');
        const json = await res.json();
        if (json.success) {
            STATE.pendingApprovals = json.data;
            renderApprovalsList(json.data);
            const badge = document.getElementById('approvals-badge');
            if (badge) {
                badge.textContent = json.data.length;
                badge.style.display = json.data.length > 0 ? 'inline-block' : 'none';
            }
        } else {
            showToast(json.error?.message || 'Error al cargar aprobaciones', 'error');
        }
    } catch {
        showToast('Error de red al consultar aprobaciones', 'error');
    }
}

export function renderApprovalsList(approvals) {
    const container = document.getElementById('approvals-container');
    if (!container) return;
    container.innerHTML = '';

    if (!approvals || approvals.length === 0) {
        container.innerHTML = `
            <div style="grid-column: 1 / -1; padding: 3rem; text-align: center; background-color: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-strong);">
                <div style="margin-bottom: 0.75rem;"><svg class="icon-svg" style="width: 32px; height: 32px; color: var(--primary-500);" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
                <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main);">Sin aprobaciones pendientes</h3>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Todas las tareas enviadas a revisión han sido dictaminadas satisfactoriamente.</p>
            </div>
        `;
        return;
    }

    approvals.forEach(appr => {
        const card = document.createElement('div');
        card.className = 'approval-card';
        card.innerHTML = `
            <div class="approval-card-header">
                <span class="task-code">${escapeHtml(appr.tarea_codigo)}</span>
                <span class="badge badge-prioridad-ALTA">PENDIENTE REVISIÓN</span>
            </div>
            <h4 class="approval-card-title">${escapeHtml(appr.tarea_titulo)}</h4>
            <div class="approval-meta-row">
                <span><strong>Responsable:</strong> ${escapeHtml(appr.responsable_nombre || appr.solicitante_nombre || 'Usuario')}</span>
                <span>Solicitud: ${formatDate(appr.fecha_solicitud || appr.created_at)}</span>
            </div>
            <div class="approval-actions">
                <button class="btn btn-outline btn-sm" onclick="openTaskDetailModal(${appr.tarea_id})">
                    <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <span>Ver Detalle</span>
                </button>
                <button class="btn btn-success btn-sm" onclick="approveTask(${appr.tarea_id})">
                    <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span>Aprobar</span>
                </button>
                <button class="btn btn-danger btn-sm" onclick="openReturnModal(${appr.tarea_id}, '${appr.tarea_codigo}')">
                    <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg>
                    <span>Devolver</span>
                </button>
            </div>
        `;
        container.appendChild(card);
    });
}

/**
 * Solicita aprobación para una tarea.
 */
export async function requestApproval(taskId) {
    try {
        const res = await apiFetch(`/api/tareas/${taskId}/solicitar-aprobacion`, { method: 'POST' });
        const result = await res.json();
        if (result.success) {
            showToast('¡Tarea enviada a revisión formal con éxito!', 'success');
            await fetchTasks();
            await fetchPendingApprovalsCount();
            if (onApprovalActionCallback) onApprovalActionCallback(taskId);
        } else {
            showToast(result.error?.message || 'Error al solicitar aprobación', 'error');
        }
    } catch {
        showToast('Error de red al solicitar aprobación', 'error');
    }
}

/**
 * Dictamina la aprobación formal de una tarea.
 */
export async function approveTask(taskId) {
    try {
        const res = await apiFetch(`/api/tareas/${taskId}/resolver-aprobacion`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'APROBAR' })
        });
        const result = await res.json();
        if (result.success) {
            showToast('Tarea Aprobada y Finalizada formalmente', 'success');
            await fetchTasks();
            await fetchPendingApprovalsCount();
            if (STATE.currentScope === 'approvals') {
                await fetchPendingApprovals();
            }
            if (onApprovalActionCallback) onApprovalActionCallback(taskId);
        } else {
            showToast(result.error?.message || 'Error al aprobar tarea', 'error');
        }
    } catch {
        showToast('Error de red al aprobar tarea', 'error');
    }
}

export function openReturnModal(taskId, taskCode) {
    STATE.pendingReturnTaskId = taskId;
    const codeEl = document.getElementById('return-task-code');
    if (codeEl) codeEl.textContent = taskCode;
    const obsEl = document.getElementById('return-observations');
    if (obsEl) obsEl.value = '';
    openModal('modal-return-task');
}

export async function handleReturnTaskSubmit(e) {
    e.preventDefault();
    if (!STATE.pendingReturnTaskId) return;

    const obsEl = document.getElementById('return-observations');
    const observaciones = obsEl ? obsEl.value.trim() : '';

    if (!observaciones) {
        showToast('Debe ingresar las observaciones de devolución para continuar.', 'error');
        return;
    }

    try {
        const res = await apiFetch(`/api/tareas/${STATE.pendingReturnTaskId}/resolver-aprobacion`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'DEVOLVER',
                observaciones: observaciones
            })
        });
        const result = await res.json();
        if (result.success) {
            closeModal('modal-return-task');
            showToast('Tarea devuelta para corrección con observaciones registradas', 'info');
            const taskId = STATE.pendingReturnTaskId;
            STATE.pendingReturnTaskId = null;
            await fetchTasks();
            await fetchPendingApprovalsCount();
            if (STATE.currentScope === 'approvals') {
                await fetchPendingApprovals();
            }
            if (onApprovalActionCallback) onApprovalActionCallback(taskId);
        } else {
            showToast(result.error?.message || 'Error al devolver tarea', 'error');
        }
    } catch {
        showToast('Error de red al devolver tarea', 'error');
    }
}
