/**
 * CLIENT APPLICATION (SPA) — SISTEMA DE TAREAS (PLANEAMIENTO TAL S.A.)
 * Interfaz interactiva conectada a la API REST PHP 8.2+
 */

const STATE = {
    currentUser: null,
    users: [],
    columns: [],
    tasks: [],
    currentScope: 'area', // 'area' (mis tareas) | 'team' (tablero del equipo) | 'dashboard'
    searchQuery: '',
    priorityFilter: '',
    memberFilter: '',
    activeTaskDetail: null,
    pendingCloseTaskId: null,
    pendingReturnTaskId: null,
    pendingApprovals: []
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

    const memberSelect = document.getElementById('member-filter-select');
    if (memberSelect) {
        memberSelect.addEventListener('change', (e) => {
            STATE.memberFilter = e.target.value;
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

    // Formulario de Devolución de Tarea (RN-12)
    const returnTaskForm = document.getElementById('return-task-form');
    if (returnTaskForm) {
        returnTaskForm.addEventListener('submit', handleReturnTaskSubmit);
    }

    // Formulario de Agregar Dependencia (DAG MVP-07)
    const addDepForm = document.getElementById('add-dependency-form');
    if (addDepForm) {
        addDepForm.addEventListener('submit', handleAddDependency);
    }

    // Campana y Menú de Notificaciones (MVP-08)
    const notifBellBtn = document.getElementById('notif-bell-btn');
    const notifDropdown = document.getElementById('notif-dropdown');
    const notifMarkAllBtn = document.getElementById('notif-mark-all-btn');

    if (notifBellBtn && notifDropdown) {
        notifBellBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = notifDropdown.style.display !== 'none';
            if (isOpen) {
                notifDropdown.style.display = 'none';
                notifBellBtn.setAttribute('aria-expanded', 'false');
            } else {
                notifDropdown.style.display = 'block';
                notifBellBtn.setAttribute('aria-expanded', 'true');
                loadNotifications();
            }
        });

        document.addEventListener('click', (e) => {
            const wrapper = document.getElementById('notifications-menu-container');
            if (wrapper && !wrapper.contains(e.target)) {
                notifDropdown.style.display = 'none';
                notifBellBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (notifMarkAllBtn) {
        notifMarkAllBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            await markAllNotificationsRead();
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
    fetchNotificationsSummary();
    if (!window._notifPollTimer) {
        window._notifPollTimer = setInterval(fetchNotificationsSummary, 30000);
    }
}

function showLoginView() {
    document.getElementById('login-view').style.display = 'block';
    document.getElementById('kanban-view').style.display = 'none';
    const dashView = document.getElementById('dashboard-view');
    if (dashView) dashView.style.display = 'none';
    const appView = document.getElementById('approvals-view');
    if (appView) appView.style.display = 'none';
    document.getElementById('workspace-controls-bar').style.display = 'none';
    document.getElementById('user-profile-container').style.display = 'none';
    const notifContainer = document.getElementById('notifications-menu-container');
    if (notifContainer) notifContainer.style.display = 'none';
    document.getElementById('quick-role-select').style.display = 'none';
    document.getElementById('logout-btn').style.display = 'none';
}

function showKanbanView() {
    document.getElementById('login-view').style.display = 'none';
    document.getElementById('kanban-view').style.display = 'block';
    document.getElementById('workspace-controls-bar').style.display = 'flex';
    document.getElementById('user-profile-container').style.display = 'flex';
    const notifContainer = document.getElementById('notifications-menu-container');
    if (notifContainer) notifContainer.style.display = 'inline-flex';
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

    // Visibilidad colaborativa: todos los integrantes pueden ver el Tablero del Equipo
    const tabTeam = document.getElementById('tab-team-board');
    if (tabTeam) tabTeam.style.display = 'inline-flex';

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

function renderMemberFilterOptions() {
    const select = document.getElementById('member-filter-select');
    if (!select) return;
    const currentVal = select.value;
    select.innerHTML = '<option value="">👤 Todos los miembros</option>';
    STATE.users.forEach(u => {
        const opt = document.createElement('option');
        opt.value = u.id;
        opt.textContent = `${u.nombre} (${u.cargo || u.rol})`;
        select.appendChild(opt);
    });
    select.value = currentVal;
}

async function fetchColumns() {
    try {
        const res = await apiFetch('/api/tableros/columnas');
        const json = await res.json();
        if (json.success) {
            STATE.columns = json.data?.columnas || (Array.isArray(json.data) ? json.data : []);
        }
    } catch (e) {
        console.error('Error al cargar columnas', e);
    }
}

async function fetchTasks() {
    try {
        const scopeParam = STATE.currentScope ? `?scope=${STATE.currentScope}` : '';
        const res = await apiFetch(`/api/tableros/kanban${scopeParam}`);
        const json = await res.json();
        if (json.success && json.data) {
            const cols = json.data.columnas || (Array.isArray(json.data) ? json.data : []);
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

async function setScope(scope) {
    STATE.currentScope = scope;
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.getAttribute('data-scope') === scope) btn.classList.add('active');
        else btn.classList.remove('active');
    });

    const kanbanView = document.getElementById('kanban-view');
    const dashboardView = document.getElementById('dashboard-view');

    if (scope === 'dashboard') {
        if (kanbanView) kanbanView.style.display = 'none';
        if (dashboardView) dashboardView.style.display = 'block';
        loadDashboardMetrics();
    } else {
        if (dashboardView) dashboardView.style.display = 'none';
        if (kanbanView) kanbanView.style.display = 'block';
        await fetchTasks();
    }
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
    let filteredTasks = [...STATE.tasks];
    if (STATE.currentScope === 'area') {
        filteredTasks = filteredTasks.filter(task => parseInt(task.responsable_id, 10) === parseInt(user.id, 10));
    } else if (STATE.currentScope === 'personal') {
        filteredTasks = filteredTasks.filter(task => task.tipo_tarea === 'PERSONAL' && parseInt(task.responsable_id, 10) === parseInt(user.id, 10));
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

    // Renderizar las columnas
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
}

function createTaskCardElement(task) {
    const card = document.createElement('div');
    card.className = 'task-card';
    card.setAttribute('draggable', 'true');

    // Eventos de arrastre nativo
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
// Transiciones de Estado y Kanban Directas (Sin bloqueo)
// -----------------------------------------------------------------------------
window.onQuickMoveSelect = async function(e, taskId) {
    const newColId = parseInt(e.target.value, 10);
    await executeTaskTransition(taskId, newColId);
};

async function requestTaskTransition(taskId, newColId) {
    await executeTaskTransition(taskId, newColId);
}

async function executeTaskTransition(taskId, newColId) {
    try {
        const res = await apiFetch(`/api/tareas/${taskId}/transicionar`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nueva_columna_id: newColId
            })
        });
        const result = await res.json();
        if (result.success) {
            showToast('Tarea movida con éxito', 'success');
            await fetchTasks();
            if (STATE.activeTaskDetail && parseInt(STATE.activeTaskDetail.id, 10) === parseInt(taskId, 10)) {
                await loadTaskDetail(taskId);
            }
            fetchNotificationsSummary();
        } else {
            showToast(result.error?.message || 'Error en la transición', 'error');
            renderKanban();
        }
    } catch (err) {
        showToast('Error de conexión al mover tarea', 'error');
        renderKanban();
    }
}

// Acción de eliminar tarea activa desde el modal
window.handleDeleteActiveTask = async function() {
    if (!STATE.activeTaskDetail) return;
    const task = STATE.activeTaskDetail;
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
        } else {
            showToast(json.error?.message || 'No se pudo eliminar la tarea', 'error');
        }
    } catch (e) {
        showToast('Error de conexión al eliminar la tarea', 'error');
    }
};

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
            fetchNotificationsSummary();
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

        // Cargar historial de aprobaciones si aplica (MVP-06)
        let approvals = [];
        if (task.requiere_aprobacion) {
            try {
                const appRes = await apiFetch(`/api/tareas/${taskId}/aprobaciones`);
                const appJson = await appRes.json();
                if (appJson.success) approvals = appJson.data;
            } catch (e) {
                console.warn('Error al cargar historial de aprobaciones', e);
            }
        }
        renderApprovalBox(task, approvals);

        // Cargar dependencias (DAG MVP-07)
        try {
            const depRes = await apiFetch(`/api/tareas/${taskId}/dependencias`);
            const depJson = await depRes.json();
            if (depJson.success) {
                renderDependenciesSection(task, depJson.data);
            }
        } catch (e) {
            console.warn('Error al cargar dependencias', e);
        }

        // Cargar historial de auditoría inmutable (MVP-09 / RF-18 / RN-13)
        try {
            const auditRes = await apiFetch(`/api/tareas/${taskId}/historial`);
            const auditJson = await auditRes.json();
            if (auditJson.success) {
                renderAuditTimeline(auditJson.data);
            }
        } catch (e) {
            console.warn('Error al cargar historial de auditoría', e);
        }

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
            fetchNotificationsSummary();
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

// -----------------------------------------------------------------------------
// Flujo y Bandeja de Aprobaciones (MVP-06)
// -----------------------------------------------------------------------------
function renderApprovalBox(task, approvals) {
    const box = document.getElementById('detail-approval-box');
    if (!box) return;

    if (!task.requiere_aprobacion) {
        box.style.display = 'block';
        box.className = 'approval-detail-box';
        box.innerHTML = `
            <div style="font-size: 0.8rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                <span>ℹ️</span>
                <span>Esta tarea no requiere circuito formal de aprobación (cierre directo con confirmación de usuario, RN-11).</span>
            </div>
        `;
        return;
    }

    box.style.display = 'block';
    const user = STATE.currentUser;
    const isJefe = user && (user.rol === 'JEFE_PLANEAMIENTO' || user.rol === 'JEFE_PERSONAL');
    const isOwnerOrCreator = user && (parseInt(task.responsable_id, 10) === parseInt(user.id, 10) || parseInt(task.creador_id, 10) === parseInt(user.id, 10));

    const devueltas = approvals && Array.isArray(approvals) ? approvals.filter(a => a.estado === 'DEVUELTA') : [];
    const ultimaDevuelta = devueltas.length > 0 ? devueltas[0] : null;

    let html = `
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <div style="display: flex; align-items: center; gap: 0.4rem;">
                <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);">Circuito de Aprobación Formal (RN-10)</span>
            </div>
            <span class="badge ${task.estado === 'FINALIZADA' ? 'badge-prioridad-BAJA' : (task.estado === 'EN_REVISION' ? 'badge-prioridad-ALTA' : 'badge-tipo-area')}">
                ${task.estado === 'FINALIZADA' ? 'APROBADA Y CERRADA' : (task.estado === 'EN_REVISION' ? 'EN REVISIÓN' : 'REQUIERE APROBACIÓN')}
            </span>
        </div>
    `;

    // Regla RN-12: Si fue devuelta previamente, visibilizar observaciones de forma prominente
    if (ultimaDevuelta && task.estado !== 'FINALIZADA') {
        html += `
            <div style="background-color: #fee2e2; border: 1px solid #f87171; border-radius: var(--radius-sm); padding: 0.6rem 0.75rem; margin-bottom: 0.75rem;">
                <div style="font-size: 0.75rem; font-weight: 700; color: #b91c1c; margin-bottom: 0.25rem;">
                    ⚠️ Observaciones de Devolución (${formatDate(ultimaDevuelta.resolved_at)}):
                </div>
                <div style="font-size: 0.8rem; color: #7f1d1d; white-space: pre-wrap;">${escapeHtml(ultimaDevuelta.observaciones)}</div>
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
                    <button class="btn btn-success btn-sm" onclick="approveTask(${task.id})">✅ Aprobar y Finalizar</button>
                    <button class="btn btn-danger btn-sm" onclick="openReturnModal(${task.id}, '${task.codigo}')">↩️ Devolver con Observaciones</button>
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
            <p style="font-size: 0.8rem; color: #065f46; margin: 0;">
                ✅ Tarea validada y aprobada formalmente. Ciclo completado satisfactoriamente.
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
                    🚀 Enviar a Aprobación (Jefatura)
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

async function fetchPendingApprovalsCount() {
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

window.fetchPendingApprovals = async function() {
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
    } catch (e) {
        showToast('Error de red al consultar aprobaciones', 'error');
    }
};

function renderApprovalsList(approvals) {
    const container = document.getElementById('approvals-container');
    if (!container) return;
    container.innerHTML = '';

    if (!approvals || approvals.length === 0) {
        container.innerHTML = `
            <div style="grid-column: 1 / -1; padding: 3rem; text-align: center; background-color: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-strong);">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">✨</div>
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
                <span><strong>Solicitante:</strong> ${escapeHtml(appr.solicitante_nombre || 'Usuario')}</span>
                <span>${formatDate(appr.created_at)}</span>
            </div>
            <div class="approval-actions">
                <button class="btn btn-outline btn-sm" onclick="openTaskDetailModal(${appr.tarea_id})">🔍 Ver Detalle</button>
                <button class="btn btn-success btn-sm" onclick="approveTask(${appr.tarea_id})">✅ Aprobar</button>
                <button class="btn btn-danger btn-sm" onclick="openReturnModal(${appr.tarea_id}, '${appr.tarea_codigo}')">↩️ Devolver</button>
            </div>
        `;
        container.appendChild(card);
    });
}

window.requestApproval = async function(taskId) {
    try {
        const res = await apiFetch(`/api/tareas/${taskId}/solicitar-aprobacion`, {
            method: 'POST'
        });
        const result = await res.json();
        if (result.success) {
            showToast('¡Tarea enviada a revisión formal con éxito!', 'success');
            await fetchTasks();
            await fetchPendingApprovalsCount();
            fetchNotificationsSummary();
            if (STATE.activeTaskDetail && parseInt(STATE.activeTaskDetail.id, 10) === parseInt(taskId, 10)) {
                await loadTaskDetail(taskId);
            }
        } else {
            showToast(result.error?.message || 'Error al solicitar aprobación', 'error');
        }
    } catch (e) {
        showToast('Error de red al solicitar aprobación', 'error');
    }
};

window.approveTask = async function(taskId) {
    try {
        const res = await apiFetch(`/api/tareas/${taskId}/resolver-aprobacion`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'APROBAR' })
        });
        const result = await res.json();
        if (result.success) {
            showToast('✅ Tarea Aprobada y Finalizada formalmente', 'success');
            await fetchTasks();
            await fetchPendingApprovalsCount();
            fetchNotificationsSummary();
            if (STATE.currentScope === 'approvals') {
                await fetchPendingApprovals();
            }
            if (STATE.activeTaskDetail && parseInt(STATE.activeTaskDetail.id, 10) === parseInt(taskId, 10)) {
                await loadTaskDetail(taskId);
            }
        } else {
            showToast(result.error?.message || 'Error al aprobar tarea', 'error');
        }
    } catch (e) {
        showToast('Error de red al aprobar tarea', 'error');
    }
};

window.openReturnModal = function(taskId, taskCode) {
    STATE.pendingReturnTaskId = taskId;
    const codeEl = document.getElementById('return-task-code');
    if (codeEl) codeEl.textContent = taskCode;
    const obsEl = document.getElementById('return-observations');
    if (obsEl) obsEl.value = '';
    openModal('modal-return-task');
};

async function handleReturnTaskSubmit(e) {
    e.preventDefault();
    if (!STATE.pendingReturnTaskId) return;

    const obsEl = document.getElementById('return-observations');
    const observaciones = obsEl ? obsEl.value.trim() : '';

    if (!observaciones) {
        showToast('⚠️ Regla RN-12: Las observaciones de devolución son obligatorias.', 'error');
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
            showToast('↩️ Tarea devuelta con observaciones registradas (RN-12)', 'success');
            const taskId = STATE.pendingReturnTaskId;
            STATE.pendingReturnTaskId = null;
            await fetchTasks();
            await fetchPendingApprovalsCount();
            fetchNotificationsSummary();
            if (STATE.currentScope === 'approvals') {
                await fetchPendingApprovals();
            }
            if (STATE.activeTaskDetail && parseInt(STATE.activeTaskDetail.id, 10) === parseInt(taskId, 10)) {
                await loadTaskDetail(taskId);
            }
        } else {
            showToast(result.error?.message || 'Error al devolver tarea', 'error');
        }
    } catch (e) {
        showToast('Error de red al devolver tarea', 'error');
    }
}

// -----------------------------------------------------------------------------
// Dependencias entre Tareas - DAG (MVP-07)
// -----------------------------------------------------------------------------
function renderDependenciesSection(task, depData) {
    if (!depData) return;

    // Contador y alerta de bloqueo (RN-08)
    const countBadge = document.getElementById('detail-deps-count');
    if (countBadge) countBadge.textContent = depData.total_predecesoras || 0;

    const alertBox = document.getElementById('detail-deps-blocked-alert');
    if (alertBox) {
        alertBox.style.display = depData.bloqueada_por_dependencias ? 'block' : 'none';
    }

    // 1. Renderizar lista de predecesoras
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
                            ${isFinalizada ? '✓ FINALIZADA' : '⏳ PENDIENTE'}
                        </span>
                        <button class="btn-close" style="font-size: 0.9rem; color: #ef4444;" onclick="removeDependency(${task.id}, ${p.id})" title="Desvincular dependencia">&times;</button>
                    </div>
                `;
                predList.appendChild(item);
            });
        }
    }

    // 2. Llenar el selector de predecesoras elegibles
    const select = document.getElementById('dependency-predecessor-select');
    if (select) {
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

    // 3. Renderizar lista de sucesoras
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

async function handleAddDependency(e) {
    e.preventDefault();
    if (!STATE.activeTaskDetail) return;

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
    } catch (err) {
        showToast('Error de red al agregar dependencia', 'error');
    }
}

window.removeDependency = async function(taskId, predId) {
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
    } catch (err) {
        showToast('Error de red al eliminar dependencia', 'error');
    }
};

// -----------------------------------------------------------------------------
// Notificaciones y Automatizaciones (MVP-08)
// -----------------------------------------------------------------------------
async function fetchNotificationsSummary() {
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

function updateNotificationBadge(count) {
    const badge = document.getElementById('notif-badge');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'inline-block';
    } else {
        badge.textContent = '0';
        badge.style.display = 'none';
    }
}

async function loadNotifications() {
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
    } catch (err) {
        listContainer.innerHTML = '<div class="notif-empty-state">Error de red al cargar notificaciones</div>';
    }
}

function renderNotifications(notifs) {
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
            // Navegar a la tarea asociada si aplica
            const tareaId = notif.entidad_tipo === 'TAREA' ? notif.entidad_id : null;
            if (tareaId) {
                const notifDropdown = document.getElementById('notif-dropdown');
                if (notifDropdown) notifDropdown.style.display = 'none';
                await openTaskDetailModal(tareaId);
            }
        });

        listContainer.appendChild(item);
    });
}

function getNotificationIcon(tipo) {
    switch (tipo) {
        case 'TAREA_ASIGNADA': return '📋';
        case 'APROBACION_SOLICITADA': return '⏳';
        case 'TAREA_APROBADA': return '✅';
        case 'TAREA_DEVUELTA': return '⚠️';
        case 'TAREA_DESBLOQUEADA': return '🔓';
        case 'NUEVO_COMENTARIO': return '💬';
        default: return '🔔';
    }
}

function formatRelativeTime(dateStr) {
    if (!dateStr) return '';
    try {
        const date = new Date(dateStr.replace(' ', 'T') + 'Z');
        const now = new Date();
        const diffSecs = Math.max(0, Math.floor((now - date) / 1000));

        if (diffSecs < 60) return 'Hace un momento';
        const diffMins = Math.floor(diffSecs / 60);
        if (diffMins < 60) return `Hace ${diffMins} min`;
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `Hace ${diffHours} h`;
        const diffDays = Math.floor(diffHours / 24);
        return `Hace ${diffDays} d`;
    } catch (e) {
        return dateStr;
    }
}

async function markNotificationRead(notifId) {
    try {
        const res = await apiFetch(`/api/notificaciones/${notifId}/leer`, {
            method: 'PATCH'
        });
        if (res.ok) {
            fetchNotificationsSummary();
        }
    } catch (e) {
        console.warn('Error al marcar leída:', e);
    }
}

async function markAllNotificationsRead() {
    try {
        const res = await apiFetch('/api/notificaciones/marcar-todas-leidas', {
            method: 'POST'
        });
        const result = await res.json();
        if (result.success) {
            showToast('Todas las notificaciones fueron marcadas como leídas', 'success');
            updateNotificationBadge(0);
            loadNotifications();
        } else {
            showToast(result.error?.message || 'Error al actualizar notificaciones', 'error');
        }
    } catch (e) {
        showToast('Error de red al marcar notificaciones', 'error');
    }
}

// -----------------------------------------------------------------------------
// Bitácora de Auditoría Inmutable (MVP-09 / RF-18 / RN-13)
// -----------------------------------------------------------------------------
function renderAuditTimeline(events) {
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

// -----------------------------------------------------------------------------
// Dashboard Operativo y Métricas (MVP-09 / RF-19)
// -----------------------------------------------------------------------------
async function loadDashboardMetrics() {
    try {
        const res = await apiFetch('/api/dashboard/metricas');
        const json = await res.json();
        if (json.success && json.data) {
            renderDashboard(json.data);
        } else {
            showToast(json.error?.message || 'Error al cargar métricas del dashboard', 'error');
        }
    } catch (e) {
        showToast('Error de red al consultar métricas', 'error');
    }
}

function renderDashboard(data) {
    const total = data.total || 0;
    const completadas = data.completadas || 0;
    const tasa = data.tasa_cumplimiento_pct || 0;
    const activas = data.activas || 0;
    const alertas = data.alertas || { vencidas: 0, por_vencer_7_dias: 0 };
    const estados = data.estados || {};
    const prioridades = data.prioridades_activas || {};

    // KPIs principales
    const elTotal = document.getElementById('kpi-total-tasks');
    if (elTotal) elTotal.textContent = total;

    const elScopeText = document.getElementById('kpi-scope-text');
    if (elScopeText) {
        elScopeText.textContent = (data.alcance === 'global' || data.alcance === 'area')
            ? 'Tareas oficiales del área'
            : 'Mis tareas asignadas';
    }

    const elRate = document.getElementById('kpi-completion-rate');
    if (elRate) elRate.textContent = `${tasa}%`;

    const elComp = document.getElementById('kpi-completed-tasks');
    if (elComp) elComp.textContent = completadas;

    const elActive = document.getElementById('kpi-active-tasks');
    if (elActive) elActive.textContent = activas;

    const elReview = document.getElementById('kpi-review-tasks');
    if (elReview) elReview.textContent = estados['EN_REVISION'] || 0;

    const elOverdue = document.getElementById('kpi-overdue-tasks');
    if (elOverdue) elOverdue.textContent = alertas.vencidas || 0;

    const elDueSoon = document.getElementById('kpi-due-soon-tasks');
    if (elDueSoon) elDueSoon.textContent = alertas.por_vencer_7_dias || 0;

    // Barras de distribución por estados Kanban
    const statusContainer = document.getElementById('dashboard-status-bars');
    if (statusContainer) {
        statusContainer.innerHTML = '';
        const statusMeta = [
            { key: 'BACKLOG', label: 'Backlog / Planificación', color: '#6b7280' },
            { key: 'EN_PROCESO', label: 'En Proceso Activo', color: '#2563eb' },
            { key: 'BLOQUEADA', label: 'Bloqueada por Dependencia', color: '#dc2626' },
            { key: 'EN_REVISION', label: 'En Revisión (Jefatura)', color: '#d97706' },
            { key: 'FINALIZADA', label: 'Finalizada / Completada', color: '#16a34a' }
        ];

        statusMeta.forEach(meta => {
            const count = estados[meta.key] || 0;
            const pct = total > 0 ? Math.round((count / total) * 100) : 0;
            const item = document.createElement('div');
            item.className = 'dist-bar-item';
            item.innerHTML = `
                <div class="dist-bar-header">
                    <span>${meta.label}</span>
                    <strong>${count} <span style="font-size: 0.725rem; color: var(--text-muted); font-weight: normal;">(${pct}%)</span></strong>
                </div>
                <div class="dist-bar-track">
                    <div class="dist-bar-fill" style="width: ${pct}%; background-color: ${meta.color};"></div>
                </div>
            `;
            statusContainer.appendChild(item);
        });
    }

    // Barras de distribución por prioridad
    const prioContainer = document.getElementById('dashboard-priority-bars');
    if (prioContainer) {
        prioContainer.innerHTML = '';
        const prioMeta = [
            { key: 'URGENTE', label: '🔥 Urgente', color: '#dc2626' },
            { key: 'ALTA', label: '⚡ Alta', color: '#ea580c' },
            { key: 'MEDIA', label: '📌 Media', color: '#2563eb' },
            { key: 'BAJA', label: '☕ Baja', color: '#64748b' }
        ];

        const totalPrios = Object.values(prioridades).reduce((a, b) => a + b, 0);

        prioMeta.forEach(meta => {
            const count = prioridades[meta.key] || 0;
            const pct = totalPrios > 0 ? Math.round((count / totalPrios) * 100) : 0;
            const item = document.createElement('div');
            item.className = 'dist-bar-item';
            item.innerHTML = `
                <div class="dist-bar-header">
                    <span>${meta.label}</span>
                    <strong>${count} <span style="font-size: 0.725rem; color: var(--text-muted); font-weight: normal;">(${pct}%)</span></strong>
                </div>
                <div class="dist-bar-track">
                    <div class="dist-bar-fill" style="width: ${pct}%; background-color: ${meta.color};"></div>
                </div>
            `;
            prioContainer.appendChild(item);
        });
    }

    // Balance de Carga del Equipo (Solo visible para Jefatura)
    const teamSection = document.getElementById('dashboard-team-section');
    const teamTbody = document.getElementById('dashboard-team-tbody');
    if (teamSection && teamTbody) {
        if (Array.isArray(data.balance_equipo) && data.balance_equipo.length > 0) {
            teamSection.style.display = 'block';
            teamTbody.innerHTML = '';

            data.balance_equipo.forEach(member => {
                const tr = document.createElement('tr');
                const cargaBadgeClass = member.activas >= 4 ? 'badge-prioridad-URGENTE' : (member.activas >= 2 ? 'badge-prioridad-ALTA' : 'badge-tipo-area');
                const roleBadge = member.usuario_rol === 'JEFE_PLANEAMIENTO' ? 'JEFE PLAN.' : (member.usuario_rol === 'JEFE_PERSONAL' ? 'JEFE PERS.' : 'ASISTENTE');

                tr.innerHTML = `
                    <td><strong>${escapeHtml(member.usuario_nombre)}</strong><br><span style="font-size: 0.725rem; color: var(--text-muted);">${escapeHtml(member.usuario_email)}</span></td>
                    <td><span class="badge ${member.usuario_rol === 'ASISTENTE' ? 'badge-tipo-area' : 'badge-prioridad-ALTA'}">${roleBadge}</span></td>
                    <td>${member.total_asignadas}</td>
                    <td><strong style="color: var(--primary-600);">${member.activas}</strong></td>
                    <td><span style="color: var(--status-done); font-weight: 600;">${member.finalizadas}</span></td>
                    <td>${member.atrasadas > 0 ? `<strong style="color: #dc2626;">${member.atrasadas}</strong>` : '0'}</td>
                    <td><span class="badge ${cargaBadgeClass}">${member.activas} activas</span></td>
                `;
                teamTbody.appendChild(tr);
            });
        } else {
            teamSection.style.display = 'none';
        }
    }
}




