/**
 * MODULO STATE — ESTADO GLOBAL REACTIVO DE LA SPA
 * TAL S.A. — Área de Planeamiento
 */

export const STATE = {
    currentUser: null,
    users: [],
    columns: [],
    tasks: [],
    currentScope: 'area', // 'area' (mis tareas) | 'team' (tablero del equipo) | 'approvals' | 'history'
    searchQuery: '',
    priorityFilter: '',
    memberFilter: '',
    activeTaskDetail: null,
    pendingCloseTaskId: null,
    pendingReturnTaskId: null,
    pendingApprovals: []
};

/**
 * Determina si el usuario autenticado tiene facultades de jefatura (RN-04, RN-05).
 */
export function isCurrentUserJefe() {
    const user = STATE.currentUser;
    if (!user) return false;
    return !!(user.es_jefe == 1 || user.es_jefe === true || user.rol === 'JEFE_PLANEAMIENTO' || user.rol === 'JEFE_PERSONAL');
}

/**
 * Reinicia el estado global al cerrar sesión.
 */
export function resetState() {
    STATE.currentUser = null;
    STATE.users = [];
    STATE.columns = [];
    STATE.tasks = [];
    STATE.currentScope = 'area';
    STATE.searchQuery = '';
    STATE.priorityFilter = '';
    STATE.memberFilter = '';
    STATE.activeTaskDetail = null;
    STATE.pendingCloseTaskId = null;
    STATE.pendingReturnTaskId = null;
    STATE.pendingApprovals = [];
}
