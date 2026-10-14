/**
 * ORQUESTADOR PRINCIPAL (SPA) — SISTEMA DE TAREAS (PLANEAMIENTO TAL S.A.)
 * Arquitectura modular con separación estricta de responsabilidades.
 */

import { STATE, isCurrentUserJefe } from './modules/state.js';
import { apiFetch } from './modules/api.js';
import { initTheme, openModal, closeModal, showToast } from './modules/ui.js';
import { checkSession, doLogin, quickLogin, doLogout, registerLoginCallback } from './modules/auth.js';
import { fetchUsers, fetchColumns, fetchTasks, renderKanban, executeTaskTransition, registerKanbanCallbacks } from './modules/kanban.js';
import { openCreateTaskModal, handleCreateTask, openTaskDetailModal, loadTaskDetail, handleAddSubtask, toggleSubtask, deleteSubtask, handleAddComment, handleDeleteActiveTask, handleAddDependency, removeDependency, toggleTaskEditMode, handleSaveTaskEdit, registerTaskModalCallbacks } from './modules/task-modal.js';
import { fetchPendingApprovals, fetchPendingApprovalsCount, approveTask, openReturnModal, handleReturnTaskSubmit, requestApproval, registerApprovalCallbacks } from './modules/approvals.js';
import { loadHistoryTasks, filterHistoryTasks } from './modules/history.js';
import { fetchNotificationsSummary, loadNotifications, markAllNotificationsRead, startNotificationPolling, stopNotificationPolling, registerNotificationCallbacks } from './modules/notifications.js';
import { openCreateUserModal, handleCreateUser } from './modules/users.js';
import { openCreateColumnModal, openEditColumnModal, selectColorHex, handleSaveColumn, handleDeleteColumn } from './modules/columns.js';

// -----------------------------------------------------------------------------
// Registro de Intercomunicación entre Módulos
// -----------------------------------------------------------------------------
registerLoginCallback(async () => {
    await loadInitialData();
    startNotificationPolling();
});

registerKanbanCallbacks({
    onTaskClick: (taskId) => openTaskDetailModal(taskId),
    onTransition: async (taskId) => {
        if (STATE.activeTaskDetail && parseInt(STATE.activeTaskDetail.id, 10) === parseInt(taskId, 10)) {
            await loadTaskDetail(taskId);
        }
        fetchNotificationsSummary();
    }
});

registerTaskModalCallbacks({
    onTaskUpdated: () => fetchNotificationsSummary()
});

registerApprovalCallbacks({
    onAction: async (taskId) => {
        if (STATE.activeTaskDetail && parseInt(STATE.activeTaskDetail.id, 10) === parseInt(taskId, 10)) {
            await loadTaskDetail(taskId);
        }
        fetchNotificationsSummary();
    }
});

registerNotificationCallbacks({
    onNotificationClick: (taskId) => openTaskDetailModal(taskId)
});

// -----------------------------------------------------------------------------
// Exposición Global en Window para Compatibilidad con Atributos HTML
// -----------------------------------------------------------------------------
window.quickLogin = quickLogin;
window.openModal = openModal;
window.closeModal = closeModal;
window.openTaskDetailModal = openTaskDetailModal;
window.toggleSubtask = toggleSubtask;
window.deleteSubtask = deleteSubtask;
window.handleDeleteActiveTask = handleDeleteActiveTask;
window.approveTask = approveTask;
window.openReturnModal = openReturnModal;
window.requestApproval = requestApproval;
window.fetchPendingApprovals = fetchPendingApprovals;
window.loadHistoryTasks = loadHistoryTasks;
window.filterHistoryTasks = filterHistoryTasks;
window.removeDependency = removeDependency;

// Nuevas funcionalidades: Edición de tareas, Usuarios y Columnas estilo Trello
window.toggleTaskEditMode = toggleTaskEditMode;
window.handleSaveTaskEdit = handleSaveTaskEdit;
window.openCreateUserModal = openCreateUserModal;
window.handleCreateUser = handleCreateUser;
window.openCreateColumnModal = openCreateColumnModal;
window.openEditColumnModal = openEditColumnModal;
window.selectColumnColor = selectColorHex;
window.handleSaveColumn = handleSaveColumn;
window.handleDeleteColumn = handleDeleteColumn;
window.setScope = setScope;
window.doLogout = doLogout;

// -----------------------------------------------------------------------------
// Ciclo de Vida y Event Listeners
// -----------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initEventListeners();
    checkSession();
});

/**
 * Carga concurrente optimizada de datos iniciales.
 */
async function loadInitialData() {
    await Promise.all([fetchUsers(), fetchColumns()]);
    await fetchTasks();
    await fetchPendingApprovalsCount();
}

/**
 * Cambio de ámbito y pestañas (Mis Tareas / Tablero del Equipo / Aprobaciones / Historial).
 */
export async function setScope(scope) {
    STATE.currentScope = scope;
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.getAttribute('data-scope') === scope) {
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
        } else {
            btn.classList.remove('active');
            btn.setAttribute('aria-selected', 'false');
        }
    });

    const kanbanView = document.getElementById('kanban-view');
    const historyView = document.getElementById('history-view');
    const approvalsView = document.getElementById('approvals-view');
    const memberFilter = document.getElementById('member-filter-select');

    // Filtro de miembros no aplica en tablero de apoyo personal
    if (memberFilter) {
        if (scope === 'personal' || !isCurrentUserJefe()) {
            memberFilter.style.display = 'none';
        } else if (isCurrentUserJefe() && (scope === 'area' || scope === 'team')) {
            memberFilter.style.display = 'inline-block';
        }
    }

    const btnCreateTask = document.getElementById('btn-open-create-task');
    if (btnCreateTask) {
        if (!isCurrentUserJefe() && scope === 'area') {
            btnCreateTask.style.display = 'none';
        } else {
            btnCreateTask.style.display = 'inline-flex';
        }
    }

    const btnCreateColumn = document.getElementById('btn-open-create-column');
    if (btnCreateColumn) {
        if (isCurrentUserJefe() && (scope === 'area' || scope === 'personal' || scope === 'team')) {
            btnCreateColumn.style.display = 'inline-flex';
        } else {
            btnCreateColumn.style.display = 'none';
        }
    }

    if (scope === 'history') {
        if (kanbanView) kanbanView.style.display = 'none';
        if (approvalsView) approvalsView.style.display = 'none';
        if (historyView) historyView.style.display = 'block';
        await loadHistoryTasks();
    } else if (scope === 'approvals') {
        if (kanbanView) kanbanView.style.display = 'none';
        if (historyView) historyView.style.display = 'none';
        if (approvalsView) approvalsView.style.display = 'block';
        await fetchPendingApprovals();
    } else {
        if (historyView) historyView.style.display = 'none';
        if (approvalsView) approvalsView.style.display = 'none';
        if (kanbanView) kanbanView.style.display = 'block';
        await fetchTasks();
    }
}

/**
 * Inicialización de escuchadores de eventos del DOM.
 */
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
        logoutBtn.addEventListener('click', async () => {
            stopNotificationPolling();
            await doLogout();
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

    // Modal Crear Tarea
    const openCreateBtn = document.getElementById('btn-open-create-task');
    if (openCreateBtn) {
        openCreateBtn.addEventListener('click', openCreateTaskModal);
    }

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
                await executeTaskTransition(STATE.activeTaskDetail.id, newColId);
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

    // Formulario de Edición de Tarea
    const editTaskForm = document.getElementById('edit-task-form');
    if (editTaskForm) {
        editTaskForm.addEventListener('submit', handleSaveTaskEdit);
    }

    // Formulario de Creación de Usuario
    const createUserForm = document.getElementById('create-user-form');
    if (createUserForm) {
        createUserForm.addEventListener('submit', handleCreateUser);
    }

    // Formulario de Creación/Edición de Columnas (Trello)
    const columnEditorForm = document.getElementById('column-editor-form');
    if (columnEditorForm) {
        columnEditorForm.addEventListener('submit', handleSaveColumn);
    }

    // Campana y Dropdown de Notificaciones (MVP-08)
    const notifBellBtn = document.getElementById('notif-bell-btn');
    const notifDropdown = document.getElementById('notif-dropdown');
    const notifMarkAllBtn = document.getElementById('notif-mark-all-btn');

    if (notifBellBtn && notifDropdown) {
        notifBellBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = notifDropdown.style.display === 'block';
            if (isVisible) {
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

    // Cierre accesible de modales con tecla Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(m => {
                m.classList.remove('active');
            });
            if (notifDropdown) notifDropdown.style.display = 'none';
        }
    });
}
