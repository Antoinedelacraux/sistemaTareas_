/**
 * CLIENT APPLICATION (SPA) — SISTEMA DE TAREAS (PLANEAMIENTO TAL S.A.)
 * Interfaz interactiva conectada a la API REST PHP 8.2+
 */

const STATE = {
    currentUser: null,
    users: [],
    columns: [],
    tasks: [],
    currentScope: 'area', // 'area' | 'personal' | 'team'
    searchQuery: '',
    priorityFilter: '',
    activeTaskDetail: null,
    pendingCloseTaskId: null
};

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initEventListeners();
    checkSession();
});

// -----------------------------------------------------------------------------
// Utilidad HTTP Centralizada (Bearer Token + Cookies)
// -----------------------------------------------------------------------------
function apiFetch(url, options = {}) {
    options.headers = options.headers || {};
    options.credentials = 'include';
    const token = localStorage.getItem('auth_token');
    if (token) {
        options.headers['Authorization'] = `Bearer ${token}`;
    }
    return fetch(url, options);
}

// -----------------------------------------------------------------------------
// Inicialización y Tema (Modo Claro / Oscuro)
// -----------------------------------------------------------------------------
function initTheme() {
    const themeBtn = document.getElementById('theme-toggle-btn');
    const savedTheme = localStorage.getItem('theme') || 'light';

    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', nextTheme);
            localStorage.setItem('theme', nextTheme);
            updateThemeIcon(nextTheme);
        });
    }
}

function updateThemeIcon(theme) {
    const iconSpan = document.getElementById('theme-icon');
    const textSpan = document.getElementById('theme-text');
    if (iconSpan) iconSpan.textContent = theme === 'dark' ? '☀️' : '🌙';
    if (textSpan) textSpan.textContent = theme === 'dark' ? 'Claro' : 'Oscuro';
}

// -----------------------------------------------------------------------------
// Event Listeners Globales
// -----------------------------------------------------------------------------
function initEventListeners() {
    // Formulario de Login
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;
            await doLogin(email, password);
        });
    }

    // Botón Salir
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', doLogout);
    }

    // Selector Rápido de Rol
    const quickRoleSelect = document.getElementById('quick-role-select');
    if (quickRoleSelect) {
        quickRoleSelect.addEventListener('change', async (e) => {
            const email = e.target.value;
            if (email) {
                await quickLogin(email);
            }
        });
    }

    // Pestañas de Alcance
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const scope = btn.getAttribute('data-scope');
            setScope(scope);
        });
    });

    // Filtros de Búsqueda y Prioridad
    const searchInput = document.getElementById('task-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            STATE.searchQuery = e.target.value.toLowerCase().trim();
            renderKanban();
        });
    }

    const prioritySelect = document.getElementById('priority-filter-select');
    if (prioritySelect) {
        prioritySelect.addEventListener('change', (e) => {
            STATE.priorityFilter = e.target.value;
            renderKanban();
        });
    }

    // Abrir modal de crear tarea
    const openCreateBtn = document.getElementById('btn-open-create-task');
    if (openCreateBtn) {
        openCreateBtn.addEventListener('click', () => {
            openCreateTaskModal();
        });
    }

    // Formulario Crear Tarea
    const createTaskForm = document.getElementById('create-task-form');
    if (createTaskForm) {
        createTaskForm.addEventListener('submit', handleCreateTask);
    }

    // Formulario Añadir Subtarea
    const addSubtaskForm = document.getElementById('add-subtask-form');
    if (addSubtaskForm) {
        addSubtaskForm.addEventListener('submit', handleAddSubtask);
    }

    // Formulario Añadir Comentario
    const addCommentForm = document.getElementById('add-comment-form');
    if (addCommentForm) {
        addCommentForm.addEventListener('submit', handleAddComment);
    }

    // Mover columna desde modal de detalle
    const detailColumnSelect = document.getElementById('detail-column-select');
    if (detailColumnSelect) {
        detailColumnSelect.addEventListener('change', async (e) => {
            const newColId = parseInt(e.target.value, 10);
            if (STATE.activeTaskDetail && newColId) {
                await requestTaskTransition(STATE.activeTaskDetail.id, newColId);
            }
        });
    }

    // Checkbox de Confirmación de Cierre
    const confirmCheckbox = document.getElementById('confirm-close-checkbox');
    const btnSubmitConfirmClose = document.getElementById('btn-submit-confirm-close');
    if (confirmCheckbox && btnSubmitConfirmClose) {
        confirmCheckbox.addEventListener('change', (e) => {
            btnSubmitConfirmClose.disabled = !e.target.checked;
        });

        btnSubmitConfirmClose.addEventListener('click', async () => {
            if (STATE.pendingCloseTaskId) {
                closeModal('modal-confirm-close');
                await executeTaskTransition(STATE.pendingCloseTaskId, 5, true);
                STATE.pendingCloseTaskId = null;
            }
        });
    }
}

// -----------------------------------------------------------------------------
// Sesión y Autenticación
// -----------------------------------------------------------------------------
async function checkSession() {
    try {
        const res = await apiFetch('/api/auth/me');
        if (res.ok) {
            const data = await res.json();
            if (data.success && data.data) {
                STATE.currentUser = data.data;
                await onLoginSuccess();
                return;
            }
        }
    } catch (err) {
        console.warn('Sin sesión previa', err);
    }
    showLoginView();
}

async function doLogin(email, password) {
    try {
        const res = await fetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const result = await res.json();
        if (result.success && result.data) {
            STATE.currentUser = result.data.user || result.data.usuario;
            if (result.data.token) {
                localStorage.setItem('auth_token', result.data.token);
            }
            showToast('¡Bienvenido, ' + STATE.currentUser.nombre + '!', 'success');
            await onLoginSuccess();
        } else {
            showToast(result.error?.message || 'Credenciales inválidas', 'error');
        }
    } catch (err) {
        showToast('Error de conexión con el backend', 'error');
    }
}

window.quickLogin = async function(email) {
    await doLogin(email, 'Planeamiento2026!');
};

async function doLogout() {
    try {
        await apiFetch('/api/auth/logout', { method: 'POST' });
    } catch (e) {}
    localStorage.removeItem('auth_token');
    STATE.currentUser = null;
    showToast('Sesión cerrada correctamente', 'success');
    showLoginView();
}

async function onLoginSuccess() {
    renderUserBar();
    await loadInitialData();
    showKanbanView();
}

function showLoginView() {
    document.getElementById('login-view').style.display = 'block';
    document.getElementById('kanban-view').style.display = 'none';
    document.getElementById('workspace-controls-bar').style.display = 'none';
    document.getElementById('user-profile-container').style.display = 'none';
    document.getElementById('quick-role-select').style.display = 'none';
    document.getElementById('logout-btn').style.display = 'none';
}

function showKanbanView() {
    document.getElementById('login-view').style.display = 'none';
    document.getElementById('kanban-view').style.display = 'block';
    document.getElementById('workspace-controls-bar').style.display = 'flex';
    document.getElementById('user-profile-container').style.display = 'flex';
    document.getElementById('quick-role-select').style.display = 'inline-block';
    document.getElementById('logout-btn').style.display = 'inline-flex';
}

function renderUserBar() {
    const user = STATE.currentUser;
    if (!user) return;

    document.getElementById('user-avatar').textContent = user.nombre.charAt(0).toUpperCase();
    document.getElementById('user-name').textContent = user.nombre;

    let roleText = 'Asistente';
    if (user.rol === 'JEFE_PLANEAMIENTO') roleText = 'Jefe Planeamiento';
    else if (user.rol === 'JEFE_PERSONAL') roleText = 'Jefe Personal';
    document.getElementById('user-role').textContent = roleText;

    // Ajustar visibilidad de pestaña de equipo según rol
    const tabTeam = document.getElementById('tab-team-board');
    if (user.rol === 'ASISTENTE') {
        tabTeam.style.display = 'none';
        if (STATE.currentScope === 'team') STATE.currentScope = 'area';
    } else {
        tabTeam.style.display = 'inline-flex';
    }

    // Sincronizar selector rápido
    const quickSelect = document.getElementById('quick-role-select');
    if (quickSelect) quickSelect.value = user.email;
}

// -----------------------------------------------------------------------------
// Carga de Datos
// -----------------------------------------------------------------------------
async function loadInitialData() {
    await Promise.all([fetchUsers(), fetchColumns()]);
    await fetchTasks();
}

async function fetchUsers() {
    try {
        const res = await apiFetch('/api/usuarios');
        const json = await res.json();
        if (json.success) STATE.users = json.data;
    } catch (e) {
        console.error('Error al cargar usuarios', e);
    }
}

async function fetchColumns() {
    try {
        const res = await apiFetch('/api/tableros/columnas');
        const json = await res.json();
        if (json.success) STATE.columns = json.data;
    } catch (e) {
        console.error('Error al cargar columnas', e);
    }
}

async function fetchTasks() {
    try {
        const res = await apiFetch('/api/tableros/kanban');
        const json = await res.json();
        if (json.success) {
            const allTasks = [];
            json.data.forEach(col => {
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

function setScope(scope) {
    STATE.currentScope = scope;
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.getAttribute('data-scope') === scope) btn.classList.add('active');
        else btn.classList.remove('active');
    });
    renderKanban();
}

// -----------------------------------------------------------------------------
// Renderizado del Tablero Kanban
// -----------------------------------------------------------------------------
function renderKanban() {
    const container = document.getElementById('kanban-container');
    if (!container) return;
    container.innerHTML = '';

    const user = STATE.currentUser;
    if (!user) return;

    // Filtrar tareas según alcance activo
    let filteredTasks = STATE.tasks.filter(task => {
        if (STATE.currentScope === 'personal') {
            return task.tipo_tarea === 'PERSONAL' && parseInt(task.responsable_id, 10) === parseInt(user.id, 10);
        } else if (STATE.currentScope === 'area') {
            return task.tipo_tarea === 'AREA' && parseInt(task.responsable_id, 10) === parseInt(user.id, 10);
        } else if (STATE.currentScope === 'team') {
            return task.tipo_tarea === 'AREA';
        }
        return true;
    });

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

    // Renderizar las 5 columnas
    STATE.columns.forEach(col => {
        const colTasks = filteredTasks.filter(t => parseInt(t.columna_id, 10) === parseInt(col.id, 10));

        const colDiv = document.createElement('div');
        colDiv.className = 'kanban-column';
        colDiv.innerHTML = `
            <div class="column-header">
                <div class="column-title-group">
                    <span class="column-indicator" style="background-color: ${col.color_hex};"></span>
                    <span class="column-title">${escapeHtml(col.nombre)}</span>
                </div>
                <span class="column-badge">${colTasks.length}</span>
            </div>
            <div class="column-cards-container" id="col-cards-${col.id}">
            </div>
        `;

        const cardsContainer = colDiv.querySelector('.column-cards-container');
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
}

function createTaskCardElement(task) {
    const card = document.createElement('div');
    card.className = 'task-card';

    const isPersonal = task.tipo_tarea === 'PERSONAL';
    const tipoBadge = isPersonal
        ? `<span class="badge badge-tipo-personal">🔒 PERSONAL</span>`
        : `<span class="badge badge-tipo-area">📋 ÁREA</span>`;

    const prioBadge = `<span class="badge badge-prioridad-${task.prioridad}">${task.prioridad}</span>`;

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

    let moveOptions = STATE.columns.map(c => `
        <option value="${c.id}" ${parseInt(c.id, 10) === parseInt(task.columna_id, 10) ? 'selected' : ''}>
            ${escapeHtml(c.nombre)}
        </option>
    `).join('');

    card.innerHTML = `
        <div class="task-card-header">
            <span class="task-code">${task.codigo}</span>
            <div class="task-badges">
                ${tipoBadge}
                ${prioBadge}
            </div>
        </div>
        <h4 class="task-title">${escapeHtml(task.titulo)}</h4>
        <p class="task-desc">${escapeHtml(task.descripcion || 'Sin descripción adicional.')}</p>
        ${subtasksHtml}
        <div class="task-card-footer">
            <div class="task-assignee">
                <div class="assignee-avatar">${initial}</div>
                <span class="assignee-name">${escapeHtml(respName)}</span>
            </div>
            <div class="task-meta-icons">
                ${task.requiere_aprobacion ? `<span title="Requiere Aprobación Formal de Cierre">⚖️</span>` : ''}
                <span title="${task.total_comentarios || 0} Comentarios">💬 ${task.total_comentarios || 0}</span>
            </div>
        </div>
        <div class="task-quick-move" onclick="event.stopPropagation()">
            <span style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600;">Mover:</span>
            <select class="quick-move-select" onchange="onQuickMoveSelect(event, ${task.id})">
                ${moveOptions}
            </select>
        </div>
    `;

    card.addEventListener('click', () => {
        openTaskDetailModal(task.id);
    });

    return card;
}

// -----------------------------------------------------------------------------
// Transiciones de Estado y Kanban
// -----------------------------------------------------------------------------
window.onQuickMoveSelect = async function(e, taskId) {
    const newColId = parseInt(e.target.value, 10);
    await requestTaskTransition(taskId, newColId);
};

async function requestTaskTransition(taskId, newColId) {
    const targetCol = STATE.columns.find(c => parseInt(c.id, 10) === newColId);
    if (!targetCol) return;

    if (targetCol.tipo_estado === 'FINALIZADA') {
        const task = STATE.tasks.find(t => parseInt(t.id, 10) === parseInt(taskId, 10));
        if (task && task.requiere_aprobacion && STATE.currentUser.rol === 'ASISTENTE') {
            showToast('Esta tarea requiere aprobación de jefatura. Debe pasar a "En revisión".', 'error');
            renderKanban();
            return;
        }

        STATE.pendingCloseTaskId = taskId;
        document.getElementById('confirm-close-task-code').textContent = task ? task.codigo : `ID #${taskId}`;
        document.getElementById('confirm-close-checkbox').checked = false;
        document.getElementById('btn-submit-confirm-close').disabled = true;
        openModal('modal-confirm-close');
        return;
    }

    await executeTaskTransition(taskId, newColId, false);
}

async function executeTaskTransition(taskId, newColId, confirmation) {
    try {
        const res = await apiFetch(`/api/tareas/${taskId}/transicionar`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nueva_columna_id: newColId,
                confirmacion_cierre: !!confirmation
            })
        });
        const result = await res.json();
        if (result.success) {
            showToast('Tarea movida con éxito', 'success');
            await fetchTasks();
            if (STATE.activeTaskDetail && parseInt(STATE.activeTaskDetail.id, 10) === parseInt(taskId, 10)) {
                await loadTaskDetail(taskId);
            }
        } else {
            showToast(result.error?.message || 'Error en la transición', 'error');
            renderKanban();
        }
    } catch (err) {
        showToast('Error de conexión al mover tarea', 'error');
        renderKanban();
    }
}

// -----------------------------------------------------------------------------
// Modal de Creación de Tarea (MVP-03)
// -----------------------------------------------------------------------------
function openCreateTaskModal() {
    const user = STATE.currentUser;
    if (!user) return;

    document.getElementById('task-title').value = '';
    document.getElementById('task-description').value = '';
    document.getElementById('task-type').value = 'AREA';
    document.getElementById('task-priority').value = 'MEDIA';
    document.getElementById('task-due-date').value = '';
    document.getElementById('task-requires-approval').checked = false;

    const assigneeSelect = document.getElementById('task-assignee');
    const helpSpan = document.getElementById('assignee-help');
    assigneeSelect.innerHTML = '';

    if (user.rol === 'ASISTENTE') {
        const opt = document.createElement('option');
        opt.value = user.id;
        opt.textContent = `${user.nombre} (Autoasignado - Mi persona)`;
        opt.selected = true;
        assigneeSelect.appendChild(opt);
        assigneeSelect.disabled = true;
        helpSpan.textContent = '🔒 Regla RN-03: Los asistentes no pueden asignar tareas a compañeros.';
    } else {
        assigneeSelect.disabled = false;
        helpSpan.textContent = '👑 Regla RN-04: Como jefatura autorizada, puedes asignar a cualquier miembro del equipo.';

        STATE.users.forEach(u => {
            const opt = document.createElement('option');
            opt.value = u.id;
            opt.textContent = `${u.nombre} (${u.rol}) - ${u.cargo}`;
            if (parseInt(u.id, 10) === parseInt(user.id, 10)) opt.selected = true;
            assigneeSelect.appendChild(opt);
        });
    }

    openModal('modal-create-task');
}

async function handleCreateTask(e) {
    e.preventDefault();
    const user = STATE.currentUser;
    const title = document.getElementById('task-title').value.trim();
    const desc = document.getElementById('task-description').value.trim();
    const type = document.getElementById('task-type').value;
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
                tipo_tarea: type,
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
        } else {
            showToast(result.error?.message || 'Error al crear tarea', 'error');
        }
    } catch (err) {
        showToast('Error de conexión al crear tarea', 'error');
    }
}

// -----------------------------------------------------------------------------
// Detalle de Tarea, Subtareas y Comentarios (MVP-05)
// -----------------------------------------------------------------------------
async function openTaskDetailModal(taskId) {
    await loadTaskDetail(taskId);
    openModal('modal-task-detail');
}

async function loadTaskDetail(taskId) {
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

        const task = taskJson.data;
        STATE.activeTaskDetail = task;

        document.getElementById('detail-code').textContent = task.codigo;
        document.getElementById('detail-title').textContent = task.titulo;
        document.getElementById('detail-desc').textContent = task.descripcion || 'Sin descripción adicional.';
        document.getElementById('detail-assignee').textContent = task.responsable_nombre || 'Sin asignar';
        document.getElementById('detail-creator').textContent = task.creador_nombre || 'Sistema';
        document.getElementById('detail-due-date').textContent = task.fecha_limite || 'Sin fecha límite';

        const typeBadge = document.getElementById('detail-type-badge');
        typeBadge.className = task.tipo_tarea === 'PERSONAL' ? 'badge badge-tipo-personal' : 'badge badge-tipo-area';
        typeBadge.textContent = task.tipo_tarea;

        const prioBadge = document.getElementById('detail-priority-badge');
        prioBadge.className = `badge badge-prioridad-${task.prioridad}`;
        prioBadge.textContent = task.prioridad;

        const colSelect = document.getElementById('detail-column-select');
        colSelect.innerHTML = STATE.columns.map(c => `
            <option value="${c.id}" ${parseInt(c.id, 10) === parseInt(task.columna_id, 10) ? 'selected' : ''}>
                ${escapeHtml(c.nombre)}
            </option>
        `).join('');

        const subtasks = subJson.success ? subJson.data : [];
        renderSubtasksList(subtasks);

        const comments = comJson.success ? comJson.data : [];
        renderCommentsList(comments);

    } catch (err) {
        showToast('Error al cargar detalle de la tarea', 'error');
    }
}

function renderSubtasksList(subtasks) {
    const list = document.getElementById('detail-subtasks-list');
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
                <input type="checkbox" ${sub.completada ? 'checked' : ''} onchange="toggleSubtask(${sub.id}, this.checked)">
                <span>${escapeHtml(sub.titulo)}</span>
            </div>
            <button class="btn-close" style="font-size: 1rem; color: #ef4444;" onclick="deleteSubtask(${sub.id})" title="Eliminar subtarea">&times;</button>
        `;
        list.appendChild(item);
    });
}

window.toggleSubtask = async function(subtaskId, isCompleted) {
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
    } catch (e) {
        showToast('Error de red', 'error');
    }
};

window.deleteSubtask = async function(subtaskId) {
    try {
        const res = await apiFetch(`/api/subtareas/${subtaskId}`, { method: 'DELETE' });
        const result = await res.json();
        if (result.success) {
            await loadTaskDetail(STATE.activeTaskDetail.id);
            await fetchTasks();
        } else {
            showToast(result.error?.message || 'Error al eliminar subtarea', 'error');
        }
    } catch (e) {
        showToast('Error de red', 'error');
    }
};

async function handleAddSubtask(e) {
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
    } catch (e) {
        showToast('Error de red', 'error');
    }
}

function renderCommentsList(comments) {
    const list = document.getElementById('detail-comments-list');
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
        if (com.es_evidencia && com.archivo_nombre_original) {
            const kb = Math.round((com.archivo_size_bytes || 0) / 1024);
            attachmentHtml = `
                <div class="comment-attachment">
                    <div class="attachment-info">
                        <span>📎</span>
                        <span>${escapeHtml(com.archivo_nombre_original)} (${kb} KB)</span>
                    </div>
                    <a href="/api/comentarios/${com.id}/archivo" target="_blank" class="btn btn-outline btn-sm" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">
                        Descargar
                    </a>
                </div>
            `;
        }

        card.innerHTML = `
            <div class="comment-header">
                <span class="comment-author">${escapeHtml(com.autor_nombre || 'Usuario')}</span>
                <span class="comment-time">${formatDate(com.created_at)}</span>
            </div>
            <div class="comment-body">${escapeHtml(com.comentario)}</div>
            ${attachmentHtml}
        `;
        list.appendChild(card);
    });

    list.scrollTop = list.scrollHeight;
}

async function handleAddComment(e) {
    e.preventDefault();
    if (!STATE.activeTaskDetail) return;

    const textarea = document.getElementById('comment-text-input');
    const fileInput = document.getElementById('comment-file-input');
    const comment = textarea.value.trim();

    if (!comment) return;

    const formData = new FormData();
    formData.append('comentario', comment);
    if (fileInput.files.length > 0) {
        formData.append('archivo', fileInput.files[0]);
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
        } else {
            showToast(result.error?.message || 'Error al publicar comentario', 'error');
        }
    } catch (e) {
        showToast('Error de red', 'error');
    }
}

// -----------------------------------------------------------------------------
// Utilidades de Interfaz (Modales y Toasts)
// -----------------------------------------------------------------------------
window.openModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
};

window.closeModal = function(id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
};

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : 'ℹ️');
    toast.innerHTML = `<span>${icon}</span> <span>${escapeHtml(message)}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 250ms';
        setTimeout(() => toast.remove(), 250);
    }, 3500);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatDate(isoStr) {
    if (!isoStr) return '';
    try {
        const d = new Date(isoStr);
        return d.toLocaleDateString('es-PE', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    } catch (e) {
        return isoStr;
    }
}
