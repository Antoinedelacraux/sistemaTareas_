/**
 * ORQUESTADOR PRINCIPAL (SPA) — SISTEMA DE TAREAS (PLANEAMIENTO TAL S.A.)
 * Arquitectura modular con separación estricta de responsabilidades.
 */

import { STATE, isCurrentUserJefe } from './modules/state.js';
import { apiFetch } from './modules/api.js';
import { initTheme, openModal, closeModal, showToast } from './modules/ui.js';
import { checkSession, doLogin, doLogout, registerLoginCallback } from './modules/auth.js';
import { fetchUsers, fetchColumns, fetchTasks, refreshBoard, renderKanban, executeTaskTransition, registerKanbanCallbacks } from './modules/kanban.js?v=1.6.4';
import { openCreateTaskModal, handleCreateTask, openTaskDetailModal, refreshTaskDetail, loadTaskDetail, handleAddSubtask, toggleSubtask, deleteSubtask, handleAddComment, handleDeleteActiveTask, handleFinalizeImmediately, handleAddDependency, removeDependency, toggleTaskEditMode, handleSaveTaskEdit, registerTaskModalCallbacks } from './modules/task-modal.js?v=1.6.4';
import { fetchPendingApprovals, fetchPendingApprovalsCount, approveTask, openReturnModal, handleReturnTaskSubmit, requestApproval, registerApprovalCallbacks } from './modules/approvals.js';
import { loadHistoryTasks, filterHistoryTasks, toggleHistorySelection, toggleAllHistorySelection, deleteHistoryTask, deleteSelectedHistoryTasks } from './modules/history.js?v=1.7.0';
import { fetchNotificationsSummary, loadNotifications, markAllNotificationsRead, startNotificationPolling, stopNotificationPolling, registerNotificationCallbacks } from './modules/notifications.js';
import { initUserManagementEvents, loadManagedUsers, openManagedUserEditor, saveManagedUser, changeManagedUserStatus } from './modules/users-management.js';
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
window.openModal = openModal;
window.closeModal = closeModal;
window.openTaskDetailModal = openTaskDetailModal;
window.refreshTaskDetail = refreshTaskDetail;
// La acción es global porque el botón del tablero vive en un partial HTML.
// No depende del rol: Jefatura y Asistencia usan la misma función de refresco.
window.refreshBoard = refreshBoard;
window.toggleSubtask = toggleSubtask;
window.deleteSubtask = deleteSubtask;
window.handleDeleteActiveTask = handleDeleteActiveTask;
window.handleFinalizeImmediately = handleFinalizeImmediately;
window.approveTask = approveTask;
window.openReturnModal = openReturnModal;
window.requestApproval = requestApproval;
window.fetchPendingApprovals = fetchPendingApprovals;
window.loadHistoryTasks = loadHistoryTasks;
window.filterHistoryTasks = filterHistoryTasks;
window.toggleHistorySelection = toggleHistorySelection;
window.toggleAllHistorySelection = toggleAllHistorySelection;
window.deleteHistoryTask = deleteHistoryTask;
window.deleteSelectedHistoryTasks = deleteSelectedHistoryTasks;
window.removeDependency = removeDependency;

// Nuevas funcionalidades: Edición de tareas, Usuarios y Columnas estilo Trello
window.toggleTaskEditMode = toggleTaskEditMode;
window.handleSaveTaskEdit = handleSaveTaskEdit;
window.openManagedUserEditor = openManagedUserEditor;
window.saveManagedUser = saveManagedUser;
window.changeManagedUserStatus = changeManagedUserStatus;
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
    // Estas consultas son independientes; ejecutarlas juntas reduce el tiempo
    // hasta el primer tablero completo, especialmente en hosting compartido.
    await Promise.all([
        fetchUsers(),
        fetchColumns(),
        fetchTasks(),
        fetchPendingApprovalsCount()
    ]);
}

/**
 * Cambio de ámbito y pestañas (Vista Área / Aprobaciones / Historial / Gestión de Usuarios).
 */
export async function setScope(scope) {
    if (scope === 'users' && !isCurrentUserJefe()) {
        showToast('La gestión de usuarios es exclusiva de Jefatura.', 'error');
        return;
    }
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
    const usersManagementView = document.getElementById('users-management-view');
    const memberFilter = document.getElementById('member-filter-select');
    const workspaceActions = document.querySelector('.workspace-actions');

    if (workspaceActions) workspaceActions.style.display = scope === 'users' ? 'none' : 'flex';

    if (memberFilter) {
        if (!isCurrentUserJefe()) {
            memberFilter.style.display = 'none';
        } else if (scope === 'area') {
            memberFilter.style.display = 'inline-block';
        } else {
            memberFilter.style.display = 'none';
        }
    }

    const btnCreateTask = document.getElementById('btn-open-create-task');
    if (btnCreateTask) {
        btnCreateTask.style.display = scope === 'users' ? 'none' : 'inline-flex';
    }

    const btnRefreshBoard = document.getElementById('btn-refresh-board');
    if (btnRefreshBoard) {
        btnRefreshBoard.style.display = scope === 'area' ? 'inline-flex' : 'none';
    }

    const btnCreateColumn = document.getElementById('btn-open-create-column');
    if (btnCreateColumn) {
        if (isCurrentUserJefe() && scope === 'area') {
            btnCreateColumn.style.display = 'inline-flex';
        } else {
            btnCreateColumn.style.display = 'none';
        }
    }

    if (scope === 'area') {
        await fetchColumns();
    }

    if (scope === 'history') {
        if (kanbanView) kanbanView.style.display = 'none';
        if (approvalsView) approvalsView.style.display = 'none';
        if (usersManagementView) usersManagementView.style.display = 'none';
        if (historyView) historyView.style.display = 'block';
        await loadHistoryTasks();
    } else if (scope === 'approvals') {
        if (kanbanView) kanbanView.style.display = 'none';
        if (historyView) historyView.style.display = 'none';
        if (usersManagementView) usersManagementView.style.display = 'none';
        if (approvalsView) approvalsView.style.display = 'block';
        await fetchPendingApprovals();
    } else if (scope === 'users') {
        if (kanbanView) kanbanView.style.display = 'none';
        if (historyView) historyView.style.display = 'none';
        if (approvalsView) approvalsView.style.display = 'none';
        if (usersManagementView) usersManagementView.style.display = 'block';
        await loadManagedUsers();
    } else {
        if (historyView) historyView.style.display = 'none';
        if (approvalsView) approvalsView.style.display = 'none';
        if (usersManagementView) usersManagementView.style.display = 'none';
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

    initUserManagementEvents();

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
