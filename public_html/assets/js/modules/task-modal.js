/**
 * MODULO TASK-MODAL — DETALLE DE TAREA, CHECKLIST, COMENTARIOS Y AUDITORIA
 * TAL S.A. — Área de Planeamiento
 */

import { apiFetch } from './api.js';
import { STATE, isCurrentUserJefe } from './state.js';
import { escapeHtml, formatDate, showToast, openModal, closeModal } from './ui.js';
import { fetchTasks } from './kanban.js?v=1.6.1';
import { renderApprovalBox } from './approvals.js';

let onTaskUpdatedCallback = null;
const assigneePickerInstances = new Map();

function getAssigneeLabel(user) {
    return `${user.nombre || 'Usuario'} (${user.rol || 'SIN ROL'})`;
}

function closeAssigneePicker(instance) {
    instance.options.hidden = true;
    instance.picker.classList.remove('is-open');
    instance.input.setAttribute('aria-expanded', 'false');
}

function renderAssigneePickerOptions(instance) {
    const query = instance.input.value.trim().toLowerCase();
    const matches = instance.users.filter(user => getAssigneeLabel(user).toLowerCase().includes(query));

    instance.options.innerHTML = '';
    if (matches.length === 0) {
        instance.options.innerHTML = '<div class="assignee-picker-empty">No se encontraron usuarios.</div>';
        return;
    }

    matches.forEach(user => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'assignee-picker-option';
        option.setAttribute('role', 'option');
        option.dataset.value = user.id;
        option.textContent = getAssigneeLabel(user);
        option.setAttribute('aria-selected', String(String(instance.select.value) === String(user.id)));
        option.addEventListener('click', () => {
            instance.select.value = String(user.id);
            instance.input.value = getAssigneeLabel(user);
            instance.selectedLabel = instance.input.value;
            closeAssigneePicker(instance);
        });
        instance.options.appendChild(option);
    });
}

function initializeAssigneePicker(selectId) {
    if (assigneePickerInstances.has(selectId)) return assigneePickerInstances.get(selectId);

    const select = document.getElementById(selectId);
    const picker = select?.closest('[data-assignee-picker]');
    const input = picker?.querySelector('.assignee-picker-input');
    const options = picker?.querySelector('.assignee-picker-options');
    if (!select || !picker || !input || !options) return null;

    const instance = { select, picker, input, options, users: [], selectedLabel: '' };
    const openPicker = () => {
        if (input.disabled) return;
        renderAssigneePickerOptions(instance);
        options.hidden = false;
        picker.classList.add('is-open');
        input.setAttribute('aria-expanded', 'true');
    };

    input.addEventListener('focus', openPicker);
    input.addEventListener('click', openPicker);
    input.addEventListener('input', () => {
        if (input.value !== instance.selectedLabel) select.value = '';
        openPicker();
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeAssigneePicker(instance);
            input.value = instance.selectedLabel;
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            openPicker();
            options.querySelector('.assignee-picker-option')?.focus();
        }
    });
    document.addEventListener('click', event => {
        if (!picker.contains(event.target)) closeAssigneePicker(instance);
    });

    assigneePickerInstances.set(selectId, instance);
    return instance;
}

function setAssigneePickerOptions(selectId, users, selectedId, disabled = false) {
    const instance = initializeAssigneePicker(selectId);
    if (!instance) return;

    instance.users = users;
    instance.select.innerHTML = users.map(user => `<option value="${escapeHtml(user.id)}">${escapeHtml(getAssigneeLabel(user))}</option>`).join('');
    instance.select.value = selectedId ? String(selectedId) : '';
    const selectedUser = users.find(user => String(user.id) === String(selectedId));
    instance.selectedLabel = selectedUser ? getAssigneeLabel(selectedUser) : '';
    instance.input.value = instance.selectedLabel;
    instance.input.disabled = disabled;
    instance.select.disabled = disabled;
    instance.input.setAttribute('aria-disabled', String(disabled));
    closeAssigneePicker(instance);
}

export function registerTaskModalCallbacks({ onTaskUpdated }) {
    if (onTaskUpdated) onTaskUpdatedCallback = onTaskUpdated;
}

/**
 * Abre el modal para crear una nueva tarea.
/**
 * Actualiza los campos y badges del modal de creación para el tablero único de Área.
 */
export function updateCreateTaskTypeUI() {
    const user = STATE.currentUser;
    if (!user) return;

    const badge = document.getElementById('create-task-badge');
    const helpSpan = document.getElementById('assignee-help');
    const groupApproval = document.getElementById('group-requires-approval');
    const requiresApproval = document.getElementById('task-requires-approval');

    if (badge) {
        badge.className = 'badge badge-tipo-area';
        badge.textContent = 'TAREA DEL ÁREA';
    }
    if (groupApproval) groupApproval.style.display = 'block';
    if (user.rol === 'ASISTENTE') {
        setAssigneePickerOptions('task-assignee', [user], user.id, true);
        if (helpSpan) helpSpan.textContent = 'Como asistente, las tareas creadas se asignan a tu responsabilidad.';
    } else {
        if (helpSpan) helpSpan.textContent = 'Puedes asignar tareas a asistentes activos del área.';
        const eligibleUsers = STATE.users.filter(u => u.rol === 'ASISTENTE' || parseInt(u.id, 10) === parseInt(user.id, 10));
        setAssigneePickerOptions('task-assignee', eligibleUsers, user.id);
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
            const createdTask = result.data?.tarea || result.data;
            const createdCode = createdTask?.codigo;
            showToast(
                createdCode
                    ? `¡Tarea ${createdCode} creada exitosamente!`
                    : '¡Tarea creada exitosamente!',
                'success'
            );
            
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
        document.getElementById('detail-created-at').textContent = formatDate(task.created_at) || 'Sin fecha de registro';
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
        const isReadOnlyForAssistant = !isJefe && !['PENDIENTE', 'EN_PROCESO'].includes(task.columna_tipo_estado);

        const colSelect = document.getElementById('detail-column-select');
        const selectableColumns = isFinalizada && isJefe
            ? STATE.columns.filter(c => parseInt(c.id, 10) === parseInt(task.columna_id, 10) || c.tipo_estado === 'EN_PROCESO')
            : STATE.columns;
        colSelect.innerHTML = selectableColumns.map(c => `
            <option value="${c.id}" ${parseInt(c.id, 10) === parseInt(task.columna_id, 10) ? 'selected' : ''}>
                ${escapeHtml(c.nombre)}
            </option>
        `).join('');
        colSelect.disabled = isReadOnlyForAssistant;

        const subtasks = subJson.success ? (subJson.data?.subtareas || (Array.isArray(subJson.data) ? subJson.data : [])) : [];
        renderSubtasksList(subtasks, isReadOnlyForAssistant);

        const comments = comJson.success ? (comJson.data?.comentarios || (Array.isArray(comJson.data) ? comJson.data : [])) : [];
        renderCommentsList(comments);

        // Control de formulario de comentarios y checklist para tareas finalizadas de asistentes
        const addSubtaskForm = document.getElementById('add-subtask-form');
        if (addSubtaskForm) {
            addSubtaskForm.style.display = isReadOnlyForAssistant ? 'none' : 'flex';
        }

        const addCommentForm = document.getElementById('add-comment-form');
        if (isReadOnlyForAssistant) {
            if (addCommentForm) addCommentForm.style.display = 'none';
        } else {
            if (addCommentForm) addCommentForm.style.display = 'block';
        }

        const isOwner = user && parseInt(task.responsable_id, 10) === parseInt(user.id, 10);
        const canModify = isJefe || (!isReadOnlyForAssistant && isOwner);

        const btnEdit = document.getElementById('btn-edit-task');
        const btnDelete = document.getElementById('btn-delete-task');
        const btnFinalizeImmediately = document.getElementById('btn-finalize-immediately');
        if (btnEdit) btnEdit.style.display = canModify ? 'inline-flex' : 'none';
        if (btnDelete) btnDelete.style.display = isJefe ? 'inline-flex' : 'none';
        if (btnFinalizeImmediately) btnFinalizeImmediately.style.display = isJefe && !task.historial_inmediato ? 'inline-flex' : 'none';

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
            <button class="btn-close" style="font-size: 1rem; color: var(--status-danger-text);" aria-label="Eliminar subtarea" onclick="deleteSubtask(${sub.id})" title="Eliminar subtarea">&times;</button>
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

/** Finaliza una tarea por decisión de Jefatura y la publica en el historial sin espera. */
export async function handleFinalizeImmediately() {
    const task = STATE.activeTaskDetail;
    if (!task || !isCurrentUserJefe()) {
        showToast('Solo Jefatura puede finalizar y archivar una tarea de inmediato', 'warning');
        return;
    }

    if (!confirm(`¿Finalizar y enviar inmediatamente al historial la tarea "${task.titulo}"?`)) {
        return;
    }

    try {
        const res = await apiFetch(`/api/tareas/${task.id}/finalizar-inmediatamente`, { method: 'POST' });
        const result = await res.json();
        if (!result.success) {
            showToast(result.error?.message || 'No se pudo finalizar la tarea', 'error');
            return;
        }

        showToast(result.data?.mensaje || 'Tarea finalizada y archivada', 'success');
        closeModal('modal-task-detail');
        await fetchTasks();
        if (onTaskUpdatedCallback) onTaskUpdatedCallback();
    } catch {
        showToast('Error de conexión al finalizar la tarea', 'error');
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
                        <button class="btn-close" style="font-size: 0.9rem; color: var(--status-danger-text);" aria-label="Desvincular dependencia" onclick="removeDependency(${task.id}, ${p.id})" title="Desvincular dependencia">&times;</button>
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
            'APROBAR': '<span class="badge badge-status-success" style="font-size: 0.7rem;">APROBADO</span>',
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
        const canAssistantEdit = task && ['PENDIENTE', 'EN_PROCESO'].includes(task.columna_tipo_estado);
        if (!isJefe && (!isOwner || !canAssistantEdit)) {
            showToast('No tienes autorización para editar esta tarea', 'warning');
            return;
        }

        document.getElementById('edit-task-title-input').value = task.titulo || '';
        document.getElementById('edit-task-desc-input').value = task.descripcion || '';
        document.getElementById('edit-task-priority-select').value = task.prioridad || 'MEDIA';
        document.getElementById('edit-task-due-date-input').value = task.fecha_limite || '';
        document.getElementById('edit-task-due-date-input').disabled = !isJefe;
        document.getElementById('edit-task-due-date-input').title = isJefe
            ? ''
            : 'La fecha límite solo puede ser modificada por Jefatura';

        if (user.rol === 'ASISTENTE') {
            setAssigneePickerOptions('edit-task-assignee-select', [user], user.id, true);
        } else {
            const eligibleUsers = STATE.users.filter(u => u.rol === 'ASISTENTE' || parseInt(u.id, 10) === parseInt(user.id, 10));
            setAssigneePickerOptions('edit-task-assignee-select', eligibleUsers, task.responsable_id);
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
            const canModify = isJefe || (isOwner && task && ['PENDIENTE', 'EN_PROCESO'].includes(task.columna_tipo_estado));
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
    const canAssistantEdit = task && ['PENDIENTE', 'EN_PROCESO'].includes(task.columna_tipo_estado);
    if (!isJefe && (!isOwner || !canAssistantEdit)) {
        showToast('No tienes autorización para editar esta tarea', 'warning');
        return;
    }

    const taskId = STATE.activeTaskDetail.id;
    const title = document.getElementById('edit-task-title-input').value.trim();
    const desc = document.getElementById('edit-task-desc-input').value.trim();
    const priority = document.getElementById('edit-task-priority-select').value;
    const dueDate = isJefe
        ? (document.getElementById('edit-task-due-date-input').value || null)
        : (task.fecha_limite || null);
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
