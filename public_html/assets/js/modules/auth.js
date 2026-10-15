/**
 * MODULO AUTH — GESTION DE SESION, LOGIN ULTRA-RAPIDO Y PERMISOS RBAC
 * TAL S.A. — Área de Planeamiento
 */

import { apiFetch, getAuthToken, setAuthToken } from './api.js';
import { STATE, resetState, isCurrentUserJefe } from './state.js';
import { showToast } from './ui.js';

let onLoginCallback = null;

export function registerLoginCallback(fn) {
    onLoginCallback = fn;
}

/**
 * Comprueba de manera instantánea si existe sesión activa.
 */
export async function checkSession() {
    const urlParams = new URLSearchParams(window.location.search);
    const hash = window.location.hash;
    if (urlParams.has('logout')) {
        try { await apiFetch('/api/auth/logout', { method: 'POST' }); } catch {}
        setAuthToken(null); resetState(); sessionStorage.removeItem('tal_session_active');
        window.history.replaceState({}, document.title, window.location.pathname);
        showLoginView();
        return;
    }
    if (urlParams.has('login') || hash === '#login') window.history.replaceState({}, document.title, window.location.pathname);

    try {
        const res = await apiFetch('/api/auth/me');
        if (res.ok) {
            const data = await res.json();
            if (data.success && data.data) {
                STATE.currentUser = data.data.user || data.data;
                await onLoginSuccess();
                return;
            }
        }
    } catch (err) {
        console.warn('Sin sesión previa válida', err);
    }

    setAuthToken(null);
    sessionStorage.removeItem('tal_session_active');
    showLoginView();
}

/**
 * Inicia sesión con credenciales y feedback visual en el botón.
 */
export async function doLogin(email, password) {
    const submitBtn = document.getElementById('login-submit-btn');
    const originalText = submitBtn ? submitBtn.innerHTML : '';

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg class="icon-svg icon-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
            <span>Verificando...</span>
        `;
    }

    try {
        const res = await apiFetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email.trim(), password })
        });
        const result = await res.json();

        if (result.success && result.data) {
            STATE.currentUser = result.data.user || result.data.usuario;
            sessionStorage.setItem('tal_session_active', 'true');
            setAuthToken(null);
            showToast(`¡Bienvenido, ${STATE.currentUser.nombre}!`, 'success');
            await onLoginSuccess();
        } else {
            showToast(result.error?.message || 'Credenciales de acceso incorrectas', 'error');
        }
    } catch (err) {
        showToast('Error al conectar con el servidor', 'error');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
}

/**
 * Cierre de sesión seguro con limpieza de almacenamiento y estado.
 */
export async function doLogout() {
    try {
        await apiFetch('/api/auth/logout', { method: 'POST' });
    } catch {
        // Ignorar fallo de red en logout
    }

    sessionStorage.removeItem('tal_session_active');
    setAuthToken(null);
    resetState();
    showToast('Sesión cerrada correctamente', 'info');
    showLoginView();
}

/**
 * Rutina tras inicio de sesión exitoso.
 */
export async function onLoginSuccess() {
    renderUserBar();
    showKanbanView();

    if (onLoginCallback) {
        await onLoginCallback();
    }
}

/**
 * Muestra la vista de autenticación y oculta las vistas privadas.
 */
export function showLoginView() {
    const loginView = document.getElementById('login-view');
    if (loginView) loginView.style.display = 'flex';

    const kanbanView = document.getElementById('kanban-view');
    if (kanbanView) kanbanView.style.display = 'none';

    const historyView = document.getElementById('history-view');
    if (historyView) historyView.style.display = 'none';

    const appView = document.getElementById('approvals-view');
    if (appView) appView.style.display = 'none';

    const usersManagementView = document.getElementById('users-management-view');
    if (usersManagementView) usersManagementView.style.display = 'none';

    const controlsBar = document.getElementById('workspace-controls-bar');
    if (controlsBar) controlsBar.style.display = 'none';

    const userProfile = document.getElementById('user-profile-container');
    if (userProfile) userProfile.style.display = 'none';

    const notifContainer = document.getElementById('notifications-menu-container');
    if (notifContainer) notifContainer.style.display = 'none';

    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) logoutBtn.style.display = 'none';
}

/**
 * Muestra el espacio de trabajo kanban y los controles correspondientes.
 */
export function showKanbanView() {
    const loginView = document.getElementById('login-view');
    if (loginView) loginView.style.display = 'none';

    const kanbanView = document.getElementById('kanban-view');
    if (kanbanView) kanbanView.style.display = 'block';

    const controlsBar = document.getElementById('workspace-controls-bar');
    if (controlsBar) controlsBar.style.display = 'flex';

    const userProfile = document.getElementById('user-profile-container');
    if (userProfile) userProfile.style.display = 'flex';

    const notifContainer = document.getElementById('notifications-menu-container');
    if (notifContainer) notifContainer.style.display = 'inline-flex';

    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) logoutBtn.style.display = 'inline-flex';
}

/**
 * Renderiza la barra superior de usuario y permisos acordes a su rol.
 */
export function renderUserBar() {
    const user = STATE.currentUser;
    if (!user) return;

    const avatarEl = document.getElementById('user-avatar');
    const nameEl = document.getElementById('user-name');
    const roleEl = document.getElementById('user-role');

    if (avatarEl) avatarEl.textContent = user.nombre.charAt(0).toUpperCase();
    if (nameEl) nameEl.textContent = user.nombre;

    const isJefe = isCurrentUserJefe();

    let roleText = 'Asistente';
    if (user.rol === 'JEFE_PLANEAMIENTO') roleText = 'Jefe Planeamiento';
    else if (user.rol === 'JEFE_PERSONAL') roleText = 'Jefe Personal';
    if (roleEl) roleEl.textContent = roleText;

    // Control de visibilidad según roles (RN-01, RN-03, RN-05)
    const tabApprovals = document.getElementById('tab-approvals-view');
    const tabHistory = document.getElementById('tab-history-view');
    const tabUsersManagement = document.getElementById('tab-users-management');
    const memberFilter = document.getElementById('member-filter-select');
    const btnCreateColumn = document.getElementById('btn-open-create-column');

    if (isJefe) {
        if (tabApprovals) tabApprovals.style.display = 'inline-flex';
        if (tabHistory) tabHistory.style.display = 'inline-flex';
        if (tabUsersManagement) tabUsersManagement.style.display = 'inline-flex';
        if (memberFilter) memberFilter.style.display = 'inline-block';
        if (btnCreateColumn) btnCreateColumn.style.display = STATE.currentScope === 'area' ? 'inline-flex' : 'none';
    } else {
        if (tabApprovals) tabApprovals.style.display = 'none';
        if (tabHistory) tabHistory.style.display = 'none';
        if (tabUsersManagement) tabUsersManagement.style.display = 'none';
        if (memberFilter) memberFilter.style.display = 'none';
        if (btnCreateColumn) btnCreateColumn.style.display = 'none';

        if (STATE.currentScope === 'approvals' || STATE.currentScope === 'history' || STATE.currentScope === 'users' || STATE.currentScope === 'personal') {
            STATE.currentScope = 'area';
        }
    }

    const btnCreateTask = document.getElementById('btn-open-create-task');
    if (btnCreateTask) btnCreateTask.style.display = 'inline-flex';
}
