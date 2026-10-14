/**
 * MODULO KANBAN — TABLERO DE TAREAS, DRAG & DROP Y TRANSICIONES
 * TAL S.A. — Área de Planeamiento
 */

import { apiFetch } from './api.js';
import { STATE, isCurrentUserJefe } from './state.js';
import { escapeHtml, showToast } from './ui.js';

let onTaskClickCallback = null;
let onTransitionCallback = null;

export function registerKanbanCallbacks({ onTaskClick, onTransition }) {
    if (onTaskClick) onTaskClickCallback = onTaskClick;
    if (onTransition) onTransitionCallback = onTransition;
}

/**
 * Carga usuarios y llena el filtro de miembros.
 */
export async function fetchUsers() {
    try {
        const res = await apiFetch('/api/usuarios?scope=team');
        const json = await res.json();
        if (json.success) {
            STATE.users = json.data?.usuarios || (Array.isArray(json.data) ? json.data : []);
            renderMemberFilterOptions();
        }
    } catch (e) {
        console.error('Error al cargar usuarios', e);
    }
}

export function renderMemberFilterOptions() {
    const selects = [
        document.getElementById('member-filter-select'),
        document.getElementById('history-member-select')
    ];
    selects.forEach(select => {
        if (!select) return;
        const currentVal = select.value;
        select.innerHTML = '<option value="">Todos los miembros</option>';
        STATE.users.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = `${u.nombre} (${u.cargo || u.rol})`;
            select.appendChild(opt);
        });
        select.value = currentVal;
    });
}

/**
 * Carga las columnas del tablero Kanban.
 */
export async function fetchColumns() {
    try {
        const scope = STATE.currentScope === 'personal' ? 'personal' : 'area';
        const res = await apiFetch(`/api/tableros/columnas?scope=${scope}`);
        const json = await res.json();
        if (json.success) {
            STATE.columns = json.data?.columnas || (Array.isArray(json.data) ? json.data : []);
        }
    } catch (e) {
        console.error('Error al cargar columnas', e);
    }
}

/**
 * Carga las tareas del alcance actual y refresca el tablero.
 */
export async function fetchTasks() {
    try {
        const scopeParam = STATE.currentScope ? `?scope=${STATE.currentScope}` : '';
        const res = await apiFetch(`/api/tableros/kanban${scopeParam}`);
        const json = await res.json();
        if (json.success && json.data) {
            const cols = json.data.columnas || (Array.isArray(json.data) ? json.data : []);
            if (!STATE.columns || STATE.columns.length === 0) {
                STATE.columns = cols;
            }
            const allTasks = [];
            cols.forEach(col => {
                if (Array.isArray(col.tareas)) {
                    allTasks.push(...col.tareas);
                }
            });
            STATE.tasks = allTasks;
            renderKanban();
        }
    } catch (e) {
        console.error('Error al cargar tareas', e);
    }
}

/**
 * Renderiza el tablero Kanban y sus columnas.
 */
export function renderKanban() {
    const container = document.getElementById('kanban-container');
    if (!container) return;
    container.innerHTML = '';

    const user = STATE.currentUser;
    if (!user) return;

    // Control del banner explicativo para la Vista Personal
    const banner = document.getElementById('personal-board-banner');
    if (banner) {
        banner.style.display = (STATE.currentScope === 'personal') ? 'flex' : 'none';
    }

    // Filtrar tareas según alcance activo (separación estricta de AREA vs PERSONAL)
    let filteredTasks = [...STATE.tasks];
    if (STATE.currentScope === 'area') {
        filteredTasks = filteredTasks.filter(task =>
            task.tipo_tarea === 'AREA' &&
            (user.rol !== 'ASISTENTE' ||
                parseInt(task.responsable_id, 10) === parseInt(user.id, 10) ||
                parseInt(task.creador_id, 10) === parseInt(user.id, 10))
        );
    } else if (STATE.currentScope === 'personal') {
        filteredTasks = filteredTasks.filter(task =>
            task.tipo_tarea === 'PERSONAL' &&
            (parseInt(task.responsable_id, 10) === parseInt(user.id, 10) ||
                parseInt(task.creador_id, 10) === parseInt(user.id, 10))
        );
    } else if (STATE.currentScope === 'team') {
        filteredTasks = filteredTasks.filter(task => task.tipo_tarea === 'AREA');
    }

    if (STATE.memberFilter) {
        filteredTasks = filteredTasks.filter(t => parseInt(t.responsable_id, 10) === parseInt(STATE.memberFilter, 10));
    }

    if (STATE.searchQuery) {
        filteredTasks = filteredTasks.filter(t =>
            t.codigo.toLowerCase().includes(STATE.searchQuery) ||
            t.titulo.toLowerCase().includes(STATE.searchQuery) ||
            (t.descripcion && t.descripcion.toLowerCase().includes(STATE.searchQuery))
        );
    }

    if (STATE.priorityFilter) {
        filteredTasks = filteredTasks.filter(t => t.prioridad === STATE.priorityFilter);
    }

    const isJefe = isCurrentUserJefe();

    // Renderizar las columnas
    STATE.columns.forEach(col => {
        const colTasks = filteredTasks.filter(t => parseInt(t.columna_id, 10) === parseInt(col.id, 10));

        const colDiv = document.createElement('div');
        colDiv.className = 'kanban-column';
        colDiv.style.borderTop = `4px solid ${col.color_hex || '#3b82f6'}`;
        colDiv.innerHTML = `
            <div class="column-header">
                <div class="column-title-group">
                    <span class="column-indicator" style="background-color: ${col.color_hex};" aria-hidden="true"></span>
                    <span class="column-title">${escapeHtml(col.nombre)}</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.35rem;">
                    <span class="column-badge" aria-label="${colTasks.length} tareas">${colTasks.length}</span>
                    ${isJefe ? `
                    <button type="button" class="btn-col-action" title="Personalizar columna" onclick="openEditColumnModal(${col.id})">
                        <svg class="icon-svg icon-svg-xs" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>
                    </button>
                    ` : ''}
                </div>
            </div>
            <div class="column-cards-container" id="col-cards-${col.id}">
            </div>
        `;

        const cardsContainer = colDiv.querySelector('.column-cards-container');

        // Soporte Drag & Drop nativo en columna
        cardsContainer.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            cardsContainer.classList.add('drag-over');
        });

        cardsContainer.addEventListener('dragleave', (e) => {
            if (!cardsContainer.contains(e.relatedTarget)) {
                cardsContainer.classList.remove('drag-over');
            }
        });

        cardsContainer.addEventListener('drop', async (e) => {
            e.preventDefault();
            cardsContainer.classList.remove('drag-over');
            const taskId = e.dataTransfer.getData('text/plain') || window._draggedTaskId;
            if (taskId) {
                await executeTaskTransition(parseInt(taskId, 10), parseInt(col.id, 10));
            }
        });

        if (colTasks.length === 0) {
            cardsContainer.innerHTML = `
                <div style="padding: 2.5rem 1rem; text-align: center; color: var(--text-muted); font-size: 0.8rem;">
                    Sin tareas en esta etapa
                </div>
            `;
        } else {
            colTasks.forEach(task => {
                cardsContainer.appendChild(createTaskCardElement(task));
            });
        }

        container.appendChild(colDiv);
    });

    // Botón estilo Trello para añadir nuevas columnas (Exclusivo para Jefatura)
    if (isJefe) {
        const addColDiv = document.createElement('div');
        addColDiv.className = 'kanban-column add-column-placeholder';
        addColDiv.innerHTML = `
            <button type="button" class="btn-add-column-card" onclick="openCreateColumnModal()">
                <svg class="icon-svg" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span>+ Añadir otra columna</span>
            </button>
        `;
        container.appendChild(addColDiv);
    }
}

/**
 * Crea el elemento visual interactivo de cada tarjeta de tarea.
 */
export function createTaskCardElement(task) {
    const isJefe = isCurrentUserJefe();
    const isFinalizada = (task.columna_tipo_estado === 'FINALIZADA');
    const isClosedForAssistant = (!isJefe && isFinalizada);

    const card = document.createElement('div');
    card.className = `task-card ${isClosedForAssistant ? 'task-card-closed' : ''}`;
    card.setAttribute('draggable', isClosedForAssistant ? 'false' : 'true');
    card.setAttribute('tabindex', '0');
    card.setAttribute('role', 'button');
    card.setAttribute('aria-label', `Tarea ${task.codigo}: ${task.titulo} ${isClosedForAssistant ? '(Cerrada)' : ''}`);

    // Eventos de arrastre nativo solo si no está cerrada para el usuario
    if (!isClosedForAssistant) {
        card.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', task.id);
            e.dataTransfer.effectAllowed = 'move';
            card.classList.add('dragging');
            window._draggedTaskId = task.id;
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            window._draggedTaskId = null;
            document.querySelectorAll('.column-cards-container').forEach(c => c.classList.remove('drag-over'));
        });
    }

    const isPersonal = task.tipo_tarea === 'PERSONAL';
    const tipoBadge = isPersonal
        ? `<span class="badge badge-tipo-personal"><svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> PERSONAL</span>`
        : `<span class="badge badge-tipo-area">ÁREA</span>`;

    const prioBadge = `<span class="badge badge-prioridad-${task.prioridad}">${task.prioridad}</span>`;
    const closedBadge = isClosedForAssistant
        ? `<span class="badge" style="background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;" title="Tarea cerrada para asistentes. Solo interactúan Jefes.">🔒 CERRADA</span>`
        : '';

    let subtasksHtml = '';
    const totalSub = parseInt(task.total_subtareas || 0, 10);
    const compSub = parseInt(task.subtareas_completadas || 0, 10);
    if (totalSub > 0) {
        const pct = Math.round((compSub / totalSub) * 100);
        subtasksHtml = `
            <div class="task-subtasks-bar">
                <div class="progress-header">
                    <span>Checklist</span>
                    <span>${compSub}/${totalSub} (${pct}%)</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width: ${pct}%;"></div>
                </div>
            </div>
        `;
    }

    const respName = task.responsable_nombre || 'Sin asignar';
    const initial = respName.charAt(0).toUpperCase();

    const commentsCount = parseInt(task.total_comentarios || 0, 10);
    const dueDateBadge = task.fecha_limite ? `
        <span class="task-due-chip" title="Fecha límite: ${task.fecha_limite}">
            <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <span>${escapeHtml(task.fecha_limite)}</span>
        </span>
    ` : '';

    card.innerHTML = `
        <div class="task-card-header">
            <span class="task-code">${task.codigo}</span>
            <div class="task-badges">
                ${tipoBadge}
                ${prioBadge}
                ${closedBadge}
            </div>
        </div>
        <h4 class="task-title">${escapeHtml(task.titulo)}</h4>
        <p class="task-desc">${escapeHtml(task.descripcion || 'Sin descripción adicional.')}</p>
        ${subtasksHtml}
        <div class="task-card-footer">
            <div class="task-assignee">
                <div class="assignee-avatar" aria-hidden="true">${initial}</div>
                <span class="assignee-name">${escapeHtml(respName)}</span>
            </div>
            <div class="task-meta-icons">
                ${dueDateBadge}
                <span class="task-comments-chip" title="${commentsCount} Comentarios">
                    <svg class="icon-svg icon-svg-sm" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    <span>${commentsCount}</span>
                </span>
            </div>
        </div>
    `;

    card.addEventListener('click', () => {
        if (onTaskClickCallback) onTaskClickCallback(task.id);
    });

    card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            if (onTaskClickCallback) onTaskClickCallback(task.id);
        }
    });

    return card;
}

/**
 * Ejecuta la transición de una tarea a otra columna.
 */
export async function executeTaskTransition(taskId, newColId) {
    try {
        const res = await apiFetch(`/api/tareas/${taskId}/transicion`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nueva_columna_id: newColId })
        });
        const result = await res.json();
        if (result.success) {
            const msg = result.data?.mensaje || result.mensaje || 'Tarea movida con éxito';
            showToast(msg, 'success');
            await fetchTasks();
            if (onTransitionCallback) onTransitionCallback(taskId);
        } else {
            const errMsg = result.error?.message || result.error || 'Error en la transición';
            showToast(errMsg, 'error');
            renderKanban();
        }
    } catch {
        showToast('Error de conexión al mover tarea', 'error');
        renderKanban();
    }
}
