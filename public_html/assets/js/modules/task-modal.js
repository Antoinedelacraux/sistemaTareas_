/**
 * MODULO TASK-MODAL — DETALLE DE TAREA, CHECKLIST, COMENTARIOS Y AUDITORIA
 * TAL S.A. — Área de Planeamiento
 */

import { apiFetch } from './api.js';
import { STATE, isCurrentUserJefe } from './state.js';
import { escapeHtml, formatDate, showToast, openModal, closeModal } from './ui.js';
import { fetchTasks } from './kanban.js?v=1.5.9';
import { renderApprovalBox } from './approvals.js';

let onTaskUpdatedCallback = null;

export function registerTaskModalCallbacks({ onTaskUpdated }) {
    if (onTaskUpdated) onTaskUpdatedCallback = onTaskUpdated;
}

/**
 * Abre el modal para crear una nueva tarea.
/**
 * Actualiza los campos y badges del modal de creación según el tipo seleccionado (AREA vs PERSONAL).
 */
export function updateCreateTaskTypeUI() {
    const user = STATE.currentUser;
    if (!user) return;

    const badge = document.getElementById('create-task-badge');
    const helpSpan = document.getElementById('assignee-help');
    const assigneeSelect = document.getElementById('task-assignee');
    const groupApproval = document.getElementById('group-requires-approval');
    const requiresApproval = document.getElementById('task-requires-approval');

    assigneeSelect.innerHTML = '';

    if (badge) {
        badge.className = 'badge badge-tipo-area';
        badge.textContent = 'TAREA DEL ÁREA';
    }
    if (groupApproval) groupApproval.style.display = 'block';
    if (user.rol === 'ASISTENTE') {
        const opt = document.createElement('option');
        opt.value = user.id;
        opt.textContent = `${user.nombre} (Autoasignado)`;
        opt.selected = true;
        assigneeSelect.appendChild(opt);
        assigneeSelect.disabled = true;
        if (helpSpan) helpSpan.textContent = 'Como asistente, las tareas creadas se asignan a tu responsabilidad.';
    } else {
        assigneeSelect.disabled = false;
        if (helpSpan) helpSpan.textContent = 'Puedes asignar tareas a asistentes activos del área.';
        STATE.users.filter(u => u.rol === 'ASISTENTE' || parseInt(u.id, 10) === parseInt(user.id, 10)).forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = `${u.nombre} (${u.rol}) - ${u.cargo || ''}`;
            if (parseInt(u.id, 10) === parseInt(user.id, 10)) opt.selected = true;
            assigneeSelect.appendChild(opt);
        });
    }
}

/**
 * Abre el modal para crear una nueva tarea.
 */
export function openCreateTaskModal() {
    const user = STATE.currentUser;
    if (!user) return;

    document.getElementById('task-title').value = '';
    document.getElementById('task-description').value = '';
    document.getElementById('task-priority').value = 'MEDIA';
    document.getElementById('task-due-date').value = '';
    document.getElementById('task-requires-approval').checked = false;

    updateCreateTaskTypeUI();
    openModal('modal-create-task');
}

/**
 * Procesa el formulario de creación de tarea.
 */
export async function handleCreateTask(e) {
    e.preventDefault();
    const user = STATE.currentUser;
    const title = document.getElementById('task-title').value.trim();
    const desc = document.getElementById('task-description').value.trim();
    const priority = document.getElementById('task-priority').value;
    const dueDate = document.getElementById('task-due-date').value || null;
    const requiresApproval = document.getElementById('task-requires-approval').checked;

    let assigneeId = parseInt(document.getElementById('task-assignee').value, 10);
    if (user.rol === 'ASISTENTE') assigneeId = parseInt(user.id, 10);

    try {
        const res = await apiFetch('/api/tareas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                titulo: title,
                descripcion: desc,
                tipo_tarea: 'AREA',
                prioridad: priority,
                responsable_id: assigneeId,
                fecha_limite: dueDate,
                requiere_aprobacion: requiresApproval ? 1 : 0
            })
        });
        const result = await res.json();
        if (result.success) {
            closeModal('modal-create-task');
            showToast(`¡Tarea ${result.data.codigo} creada exitosamente!`, 'success');
            
            await fetchTasks();
            if (onTaskUpdatedCallback) onTaskUpdatedCallback();
        } else {
            showToast(result.error?.message || 'Error al crear tarea', 'error');
        }
    } catch {
        showToast('Error de conexión al crear tarea', 'error');
    }
}

/**
 * Abre y carga los datos de la ficha de detalle de tarea.
 */
export async function openTaskDetailModal(taskId) {
    await loadTaskDetail(taskId);
    openModal('modal-task-detail');
}

export async function loadTaskDetail(taskId) {
    try {
        const [taskRes, subRes, comRes] = await Promise.all([
            apiFetch(`/api/tareas/${taskId}`),
            apiFetch(`/api/tareas/${taskId}/subtareas`),
            apiFetch(`/api/tareas/${taskId}/comentarios`)
        ]);

        const taskJson = await taskRes.json();
        const subJson = await subRes.json();
        const comJson = await comRes.json();

        if (!taskJson.success) {
            showToast('No tienes permisos para ver esta tarea', 'error');
            return;
        }

        const task = taskJson.data?.tarea || taskJson.data;
        if (!task || !task.id) {
            showToast('No se pudieron obtener los datos de la tarea', 'error');
            return;
        }
        STATE.activeTaskDetail = task;

        document.getElementById('detail-code').textContent = task.codigo || `TAR-${task.id}`;
        document.getElementById('detail-title').textContent = task.titulo || '';
        document.getElementById('detail-desc').textContent = task.descripcion || 'Sin descripción adicional.';
        document.getElementById('detail-assignee').textContent = task.responsable_nombre || 'Sin asignar';
        document.getElementById('detail-creator').textContent = task.creador_nombre || 'Sistema';
        document.getElementById('detail-due-date').textContent = task.fecha_limite || 'Sin fecha límite';

        const typeBadge = document.getElementById('detail-type-badge');
        typeBadge.className = 'badge badge-tipo-area';
        typeBadge.textContent = 'ÁREA';

        const prioBadge = document.getElementById('detail-priority-badge');
        prioBadge.className = `badge badge-prioridad-${task.prioridad}`;
        prioBadge.textContent = task.prioridad;

        const user = STATE.currentUser;
        const isJefe = isCurrentUserJefe();
        const isFinalizada = (task.columna_tipo_estado === 'FINALIZADA');
        const isClosedForAssistant = isFinalizada;

        const colSelect = document.getElementById('detail-column-select');
        colSelect.innerHTML = STATE.columns.map(c => `
            <option value="${c.id}" ${parseInt(c.id, 10) === parseInt(task.columna_id, 10) ? 'selected' : ''}>
                ${escapeHtml(c.nombre)}
            </option>
        `).join('');
        colSelect.disabled = isClosedForAssistant;

        const subtasks = subJson.success ? (subJson.data?.subtareas || (Array.isArray(subJson.data) ? subJson.data : [])) : [];
        renderSubtasksList(subtasks, isClosedForAssistant);

        const comments = comJson.success ? (comJson.data?.comentarios || (Array.isArray(comJson.data) ? comJson.data : [])) : [];
        renderCommentsList(comments);

        // Control de formulario de comentarios y checklist para tareas finalizadas de asistentes
        const addSubtaskForm = document.getElementById('add-subtask-form');
        if (addSubtaskForm) {
            addSubtaskForm.style.display = isClosedForAssistant ? 'none' : 'flex';
        }

        const addCommentForm = document.getElementById('add-comment-form');
        if (isClosedForAssistant) {
            if (addCommentForm) addCommentForm.style.display = 'none';
        } else {
            if (addCommentForm) addCommentForm.style.display = 'block';
        }

        const isOwner = user && parseInt(task.responsable_id, 10) === parseInt(user.id, 10);
        const canModify = !isClosedForAssistant && (isJefe || isOwner);

        const btnEdit = document.getElementById('btn-edit-task');
        const btnDelete = document.getElementById('btn-delete-task');
        if (btnEdit) btnEdit.style.display = canModify ? 'inline-flex' : 'none';
        if (btnDelete) btnDelete.style.display = canModify ? 'inline-flex' : 'none';

        // Desactivar modo edición si estuviera activo
        toggleTaskEditMode(false);

        // Historial de aprobaciones si aplica (MVP-06)
        let approvals = [];
        if (task.requiere_aprobacion) {
            try {
                const appRes = await apiFetch(`/api/tareas/${taskId}/aprobaciones`);
                const appJson = await appRes.json();
                if (appJson.success) approvals = appJson.data?.aprobaciones || (Array.isArray(appJson.data) ? appJson.data : []);
            } catch (e) {
                console.warn('Error al cargar historial de aprobaciones', e);
            }
        }
        renderApprovalBox(task, approvals);

        const depsBox = document.getElementById('detail-deps-box');
        if (depsBox) depsBox.style.display = 'block';
        try {
            const depRes = await apiFetch(`/api/tareas/${taskId}/dependencias`);
            const depJson = await depRes.json();
            if (depJson.success) {
                renderDependenciesSection(task, depJson.data);
            }
        } catch (e) {
            console.warn('Error al cargar dependencias', e);
        }

    } catch {
        showToast('Error al cargar detalle de la tarea', 'error');
    }
}

/**
 * Renderiza el checklist de subtareas con barra de progreso.
 */
export function renderSubtasksList(subtasks, isClosed = false) {
    const list = document.getElementById('detail-subtasks-list');
    if (!list) return;
    list.innerHTML = '';

    const total = subtasks.length;
    const completed = subtasks.filter(s => s.completada).length;
    const pct = total > 0 ? Math.round((completed / total) * 100) : 0;

    document.getElementById('detail-subtasks-count').textContent = `${completed}/${total}`;
    document.getElementById('detail-subtasks-progress').style.width = `${pct}%`;

    if (subtasks.length === 0) {
        list.innerHTML = `<div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 0.5rem;">No hay subtareas añadidas al checklist.</div>`;
        return;
    }

    subtasks.forEach(sub => {
        const item = document.createElement('div');
        item.className = `subtask-item ${sub.completada ? 'completed' : ''}`;
        item.innerHTML = `
            <div class="subtask-left">
                <input type="checkbox" ${sub.completada ? 'checked' : ''} ${isClosed ? 'disabled' : ''} aria-label="${escapeHtml(sub.titulo)}" onchange="toggleSubtask(${sub.id}, this.checked)">
                <span>${escapeHtml(sub.titulo)}</span>
            </div>
            ${!isClosed ? `
            <button class="btn-close" style="font-size: 1rem; color: #ef4444;" aria-label="Eliminar subtarea" onclick="deleteSubtask(${sub.id})" title="Eliminar subtarea">&times;</button>
            ` : ''}
        `;
        list.appendChild(item);
    });
}

export async function toggleSubtask(subtaskId, isCompleted) {
    try {
        const res = await apiFetch(`/api/subtareas/${subtaskId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ completada: isCompleted })
        });
        const result = await res.json();
        if (result.success) {
            await loadTaskDetail(STATE.activeTaskDetail.id);
            await fetchTasks();
        } else {
            showToast(result.error?.message || 'Error al actualizar subtarea', 'error');
        }
    } catch {
        showToast('Error de red', 'error');
    }
}

export async function deleteSubtask(subtaskId) {
    try {
        const res = await apiFetch(`/api/subtareas/${subtaskId}`, { method: 'DELETE' });
        const result = await res.json();
        if (result.success) {
            await loadTaskDetail(STATE.activeTaskDetail.id);
            await fetchTasks();
        } else {
            showToast(result.error?.message || 'Error al eliminar subtarea', 'error');
        }
    } catch {
        showToast('Error de red', 'error');
    }
}

export async function handleAddSubtask(e) {
    e.preventDefault();
    if (!STATE.activeTaskDetail) return;
    const input = document.getElementById('subtask-title-input');
    const title = input.value.trim();
    if (!title) return;

    try {
        const res = await apiFetch(`/api/tareas/${STATE.activeTaskDetail.id}/subtareas`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ titulo: title })
        });
        const result = await res.json();
        if (result.success) {
            input.value = '';
            await loadTaskDetail(STATE.activeTaskDetail.id);
            await fetchTasks();
        } else {
            showToast(result.error?.message || 'Error al agregar subtarea', 'error');
        }
    } catch {
        showToast('Error de red', 'error');
    }
}

/**
 * Renderiza comentarios y archivos adjuntos seguros.
 */
export function renderCommentsList(comments) {
    const list = document.getElementById('detail-comments-list');
    if (!list) return;
    list.innerHTML = '';

    document.getElementById('detail-comments-count').textContent = comments.length;

    if (comments.length === 0) {
        list.innerHTML = `<div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 1rem;">No hay comentarios registrados todavía.</div>`;
        return;
    }

    comments.forEach(com => {
        const card = document.createElement('div');
        card.className = 'comment-card';

        let attachmentHtml = '';
        if (com.adjunto) {
            const kb = Math.max(1, Math.round((com.adjunto.size_bytes || 0) / 1024));
            attachmentHtml = `
                <div class="comment-attachment">
                    <div class="attachment-info">
                        <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                        <span>${escapeHtml(com.adjunto.nombre_original)} (${kb} KB)</span>
                    </div>
                    <a href="${escapeHtml(com.adjunto.descarga_url)}" class="btn btn-outline btn-sm" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                        Descargar
                    </a>
                </div>
            `;
        }

        card.innerHTML = `
            <div class="comment-header">
                <span class="comment-author">${escapeHtml(com.usuario_primer_nombre || (com.usuario_nombre || 'Usuario').trim().split(/\s+/)[0])}</span>
                <span class="comment-time">${formatDate(com.created_at)}</span>
            </div>
            <div class="comment-body">${escapeHtml(com.comentario)}</div>
            ${attachmentHtml}
        `;
        list.appendChild(card);
    });

    list.scrollTop = list.scrollHeight;
}

export async function handleAddComment(e) {
    e.preventDefault();
    if (!STATE.activeTaskDetail) return;

    const textarea = document.getElementById('comment-text-input');
    const fileInput = document.getElementById('comment-file-input');
    const comment = textarea.value.trim();

    const attachedFile = fileInput.files[0];
    if (!comment && !attachedFile) {
        showToast('Escribe un mensaje o adjunta un archivo.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('comentario', comment);
    if (attachedFile) {
        formData.append('archivo', attachedFile);
    }

    try {
        const res = await apiFetch(`/api/tareas/${STATE.activeTaskDetail.id}/comentarios`, {
            method: 'POST',
            body: formData
        });
        const result = await res.json();
        if (result.success) {
            textarea.value = '';
            fileInput.value = '';
            showToast('Comentario publicado', 'success');
            await loadTaskDetail(STATE.activeTaskDetail.id);
            await fetchTasks();
            if (onTaskUpdatedCallback) onTaskUpdatedCallback();
        } else {
            showToast(result.error?.message || 'Error al publicar comentario', 'error');
        }
    } catch {
        showToast('Error de red', 'error');
    }
}

/**
 * Elimina la tarea activa.
 */
export async function handleDeleteActiveTask() {
    if (!STATE.activeTaskDetail) return;
    const task = STATE.activeTaskDetail;
    const user = STATE.currentUser;
    const isJefe = isCurrentUserJefe();
    if (!isJefe) {
        showToast('No tienes autorización para eliminar esta tarea oficial del área', 'warning');
        return;
    }

    if (!confirm(`¿Estás seguro de eliminar la tarea "${task.titulo}" (${task.codigo})?`)) {
        return;
    }
    try {
        const res = await apiFetch(`/api/tareas/${task.id}`, { method: 'DELETE' });
        const json = await res.json();
        if (json.success) {
            showToast('Tarea eliminada correctamente', 'success');
            closeModal('modal-task-detail');
            await fetchTasks();
            if (onTaskUpdatedCallback) onTaskUpdatedCallback();
        } else {
            showToast(json.error?.message || 'No se pudo eliminar la tarea', 'error');
        }
    } catch {
        showToast('Error de conexión al eliminar la tarea', 'error');
    }
}

/**
 * Dependencias entre tareas (DAG).
 */
export function renderDependenciesSection(task, depData) {
    if (!depData) return;

    const isJefe = isCurrentUserJefe();

    const countBadge = document.getElementById('detail-deps-count');
    if (countBadge) countBadge.textContent = depData.total_predecesoras || 0;

    const alertBox = document.getElementById('detail-deps-blocked-alert');
    if (alertBox) {
        alertBox.style.display = depData.bloqueada_por_dependencias ? 'block' : 'none';
    }

    const predList = document.getElementById('detail-predecessors-list');
    if (predList) {
        predList.innerHTML = '';
        const predecesoras = depData.predecesoras || [];

        if (predecesoras.length === 0) {
            predList.innerHTML = `<div style="font-size: 0.775rem; color: var(--text-muted); font-style: italic; padding: 0.25rem 0;">No tiene dependencias previas requeridas.</div>`;
        } else {
            predecesoras.forEach(p => {
                const isFinalizada = !!p.finalizada;
                const item = document.createElement('div');
                item.className = `dependency-item ${isFinalizada ? 'completed' : 'pending'}`;
                item.innerHTML = `
                    <div class="dependency-info">
                        <span class="dependency-code">${escapeHtml(p.codigo)}</span>
                        <span class="dependency-title">${escapeHtml(p.titulo)}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.4rem;">
                        <span class="badge ${isFinalizada ? 'badge-prioridad-BAJA' : 'badge-prioridad-ALTA'}" style="font-size: 0.7rem; padding: 0.15rem 0.4rem;">
                            ${isFinalizada ? 'FINALIZADA' : 'PENDIENTE'}
                        </span>
                        ${isJefe ? `
                        <button class="btn-close" style="font-size: 0.9rem; color: #ef4444;" aria-label="Desvincular dependencia" onclick="removeDependency(${task.id}, ${p.id})" title="Desvincular dependencia">&times;</button>
                        ` : ''}
                    </div>
                `;
                predList.appendChild(item);
            });
        }
    }

    // El formulario de vinculación solo está disponible para Jefatura
    const addDepForm = document.getElementById('add-dependency-form');
    if (addDepForm) {
        addDepForm.style.display = isJefe ? 'flex' : 'none';
    }

    const select = document.getElementById('dependency-predecessor-select');
    if (select && isJefe) {
        select.innerHTML = '<option value="" disabled selected>Vincular tarea predecesora...</option>';
        const predIds = new Set((depData.predecesoras || []).map(p => parseInt(p.id, 10)));
        const currentTaskId = parseInt(task.id, 10);

        const candidates = STATE.tasks.filter(t => {
            const tid = parseInt(t.id, 10);
            return tid !== currentTaskId && !predIds.has(tid);
        });

        if (candidates.length === 0) {
            const opt = document.createElement('option');
            opt.disabled = true;
            opt.textContent = 'No hay otras tareas disponibles para vincular';
            select.appendChild(opt);
        } else {
            candidates.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `${c.codigo} — ${c.titulo}`;
                select.appendChild(opt);
            });
        }
    }

    const succContainer = document.getElementById('detail-successors-container');
    const succList = document.getElementById('detail-successors-list');
    const sucesoras = depData.sucesoras || [];

    if (succContainer && succList) {
        if (sucesoras.length > 0) {
            succContainer.style.display = 'block';
            succList.innerHTML = '';
            sucesoras.forEach(s => {
                const isFinalizada = !!s.finalizada;
                const item = document.createElement('div');
                item.className = `dependency-item ${isFinalizada ? 'completed' : 'pending'}`;
                item.innerHTML = `
                    <div class="dependency-info">
                        <span class="dependency-code">${escapeHtml(s.codigo)}</span>
                        <span class="dependency-title">${escapeHtml(s.titulo)}</span>
                    </div>
                    <span class="badge ${isFinalizada ? 'badge-prioridad-BAJA' : 'badge-tipo-area'}" style="font-size: 0.7rem; padding: 0.15rem 0.4rem;">
                        ${escapeHtml(s.columna_nombre || s.tipo_estado)}
                    </span>
                `;
                succList.appendChild(item);
            });
        } else {
            succContainer.style.display = 'none';
        }
    }
}

export async function handleAddDependency(e) {
    e.preventDefault();
    if (!STATE.activeTaskDetail) return;

    if (!isCurrentUserJefe()) {
        showToast('Solo la Jefatura está autorizada para vincular dependencias', 'warning');
        return;
    }

    const select = document.getElementById('dependency-predecessor-select');
    const predId = parseInt(select.value, 10);
    if (!predId) return;

    try {
        const res = await apiFetch(`/api/tareas/${STATE.activeTaskDetail.id}/dependencias`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ predecesora_id: predId })
        });
        const result = await res.json();
        if (result.success) {
            showToast('Predecesora vinculada exitosamente (DAG)', 'success');
            await fetchTasks();
            await loadTaskDetail(STATE.activeTaskDetail.id);
        } else {
            showToast(result.error?.message || 'Error al vincular dependencia', 'error');
        }
    } catch {
        showToast('Error de red al agregar dependencia', 'error');
    }
}

export async function removeDependency(taskId, predId) {
    if (!isCurrentUserJefe()) {
        showToast('Solo la Jefatura está autorizada para desvincular dependencias', 'warning');
        return;
    }

    if (!confirm('¿Deseas desvincular esta tarea predecesora?')) return;

    try {
        const res = await apiFetch(`/api/tareas/${taskId}/dependencias/${predId}`, {
            method: 'DELETE'
        });
        const result = await res.json();
        if (result.success) {
            showToast('Dependencia desvinculada', 'success');
            await fetchTasks();
            if (STATE.activeTaskDetail && parseInt(STATE.activeTaskDetail.id, 10) === parseInt(taskId, 10)) {
                await loadTaskDetail(taskId);
            }
        } else {
            showToast(result.error?.message || 'Error al desvincular dependencia', 'error');
        }
    } catch {
        showToast('Error de red al eliminar dependencia', 'error');
    }
}

/**
 * Renderiza la línea de tiempo de auditoría inmutable.
 */
export function renderAuditTimeline(events) {
    const container = document.getElementById('detail-audit-timeline');
    const countBadge = document.getElementById('detail-audit-count');
    if (!container) return;

    container.innerHTML = '';
    const list = Array.isArray(events) ? events : [];
    if (countBadge) countBadge.textContent = list.length;

    if (list.length === 0) {
        container.innerHTML = '<div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 0.75rem;">No hay registros de auditoría aún.</div>';
        return;
    }

    list.forEach(evt => {
        const item = document.createElement('div');
        item.className = 'audit-item';

        const actionBadges = {
            'CREAR': '<span class="badge badge-tipo-area" style="font-size: 0.7rem;">CREACIÓN</span>',
            'TRANSICIONAR': '<span class="badge badge-prioridad-BAJA" style="font-size: 0.7rem;">TRANSICIÓN</span>',
            'APROBAR': '<span class="badge" style="background-color: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.7rem;">APROBADO</span>',
            'DEVOLVER': '<span class="badge badge-prioridad-URGENTE" style="font-size: 0.7rem;">DEVUELTO</span>',
            'SOLICITAR_APROBACION': '<span class="badge badge-prioridad-MEDIA" style="font-size: 0.7rem;">REVISIÓN</span>',
            'REASIGNAR': '<span class="badge badge-prioridad-ALTA" style="font-size: 0.7rem;">REASIGNACIÓN</span>',
            'ACTUALIZAR': '<span class="badge" style="background-color: var(--bg-surface-subtle); color: var(--text-muted); font-size: 0.7rem;">EDICIÓN</span>'
        };

        const badgeHtml = actionBadges[evt.accion] || `<span class="badge" style="font-size: 0.7rem;">${escapeHtml(evt.accion)}</span>`;

        let detailsHtml = '';
        const det = evt.detalles;
        if (det) {
            if (evt.accion === 'TRANSICIONAR') {
                const origen = det.columna_origen_nombre || `Columna #${det.columna_origen_id || '?'}`;
                const destino = det.columna_destino_nombre || `Columna #${det.columna_destino_id || '?'}`;
                detailsHtml = `Cambió de <strong>${escapeHtml(origen)}</strong> ➔ <strong>${escapeHtml(destino)}</strong>`;
                if (det.confirmacion_cierre) detailsHtml += ' (Cierre formal ratificado)';
            } else if (evt.accion === 'DEVOLVER') {
                detailsHtml = `<strong>Observaciones de Jefatura:</strong> "${escapeHtml(det.observaciones || '')}"`;
            } else if (evt.accion === 'APROBAR') {
                detailsHtml = `Dictamen favorable de cierre formal por Jefatura`;
            } else if (evt.accion === 'REASIGNAR') {
                detailsHtml = `Responsable reasignado (ID anterior: ${det.responsable_anterior_id || '-'} ➔ Nuevo: ${det.responsable_nuevo_id || '-'})`;
            } else if (evt.accion === 'CREAR') {
                detailsHtml = `Tarea registrada en el sistema`;
            } else {
                detailsHtml = typeof det === 'string' ? escapeHtml(det) : JSON.stringify(det);
            }
        }

        item.innerHTML = `
            <div class="audit-item-header">
                ${badgeHtml}
                <span class="audit-item-actor">${escapeHtml(evt.usuario_nombre || 'Sistema')}</span>
                <span class="audit-item-time">${escapeHtml(evt.created_at || '')}</span>
            </div>
            ${detailsHtml ? `<div class="audit-item-details">${detailsHtml}</div>` : ''}
        `;
        container.appendChild(item);
    });
}

/**
 * Alterna entre modo lectura y modo edición en el modal de detalle de tarea.
 */
export function toggleTaskEditMode(isEditing) {
    const viewContainer = document.getElementById('detail-view-container');
    const editContainer = document.getElementById('detail-edit-container');
    const editBtn = document.getElementById('btn-edit-task');
    if (!viewContainer || !editContainer) return;

    if (isEditing && STATE.activeTaskDetail) {
        const task = STATE.activeTaskDetail;
        const user = STATE.currentUser;
        const isJefe = isCurrentUserJefe();
        const isOwner = user && parseInt(task.responsable_id, 10) === parseInt(user.id, 10);
        if (!isJefe && !isOwner) {
            showToast('No tienes autorización para editar esta tarea', 'warning');
            return;
        }

        document.getElementById('edit-task-title-input').value = task.titulo || '';
        document.getElementById('edit-task-desc-input').value = task.descripcion || '';
        document.getElementById('edit-task-priority-select').value = task.prioridad || 'MEDIA';
        document.getElementById('edit-task-due-date-input').value = task.fecha_limite || '';

        const assigneeSelect = document.getElementById('edit-task-assignee-select');
        assigneeSelect.innerHTML = '';
        if (user.rol === 'ASISTENTE') {
            const opt = document.createElement('option');
            opt.value = user.id;
            opt.textContent = `${user.nombre} (Autoasignado)`;
            opt.selected = true;
            assigneeSelect.appendChild(opt);
            assigneeSelect.disabled = true;
        } else {
            assigneeSelect.disabled = false;
            STATE.users.filter(u => u.rol === 'ASISTENTE' || parseInt(u.id, 10) === parseInt(user.id, 10)).forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = `${u.nombre} (${u.rol})`;
                if (parseInt(u.id, 10) === parseInt(task.responsable_id, 10)) {
                    opt.selected = true;
                }
                assigneeSelect.appendChild(opt);
            });
        }

        viewContainer.style.display = 'none';
        editContainer.style.display = 'block';
        if (editBtn) editBtn.style.display = 'none';
    } else {
        viewContainer.style.display = 'block';
        editContainer.style.display = 'none';
        if (editBtn) {
            const task = STATE.activeTaskDetail;
            const user = STATE.currentUser;
            const isJefe = isCurrentUserJefe();
            const isOwner = task && user && parseInt(task.responsable_id, 10) === parseInt(user.id, 10);
            const canModify = isJefe || isOwner;
            editBtn.style.display = canModify ? 'inline-flex' : 'none';
        }
    }
}

/**
 * Guarda los cambios realizados en el formulario de edición de tarea.
 */
export async function handleSaveTaskEdit(e) {
    if (e) e.preventDefault();
    if (!STATE.activeTaskDetail) return;

    const task = STATE.activeTaskDetail;
    const user = STATE.currentUser;
    const isJefe = isCurrentUserJefe();
    const isOwner = user && parseInt(task.responsable_id, 10) === parseInt(user.id, 10);
    if (!isJefe && !isOwner) {
        showToast('No tienes autorización para editar esta tarea', 'warning');
        return;
    }

    const taskId = STATE.activeTaskDetail.id;
    const title = document.getElementById('edit-task-title-input').value.trim();
    const desc = document.getElementById('edit-task-desc-input').value.trim();
    const priority = document.getElementById('edit-task-priority-select').value;
    const dueDate = document.getElementById('edit-task-due-date-input').value || null;
    const assigneeId = parseInt(document.getElementById('edit-task-assignee-select').value, 10);

    if (!title) {
        showToast('El título de la tarea no puede estar vacío', 'error');
        return;
    }

    try {
        const res = await apiFetch(`/api/tareas/${taskId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                titulo: title,
                descripcion: desc,
                prioridad: priority,
                tipo_tarea: 'AREA',
                fecha_limite: dueDate,
                responsable_id: assigneeId
            })
        });

        const result = await res.json();
        if (result.success) {
            showToast('¡Tarea actualizada exitosamente!', 'success');
            await loadTaskDetail(taskId);
            await fetchTasks();
            if (onTaskUpdatedCallback) onTaskUpdatedCallback();
        } else {
            showToast(result.error?.message || 'Error al actualizar la tarea', 'error');
        }
    } catch {
        showToast('Error de conexión al guardar cambios', 'error');
    }
}
