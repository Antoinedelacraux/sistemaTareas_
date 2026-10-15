/** Gestión administrativa aislada de cuentas, roles y accesos. */
import { apiFetch } from './api.js';
import { STATE, isCurrentUserJefe } from './state.js';
import { closeModal, openModal, showToast, escapeHtml } from './ui.js';
import { doLogout } from './auth.js';

let managedUsers = [];
let editingUserId = null;

const roleLabel = (role) => ({
    ASISTENTE: 'Asistente',
    JEFE_PERSONAL: 'Jefe de Personal',
    JEFE_PLANEAMIENTO: 'Jefe de Área'
}[role] || role);

export async function loadManagedUsers() {
    if (!isCurrentUserJefe()) return;
    const search = document.getElementById('users-management-search')?.value.trim() || '';
    const role = document.getElementById('users-management-role')?.value || '';
    const status = document.getElementById('users-management-status')?.value || '';
    const params = new URLSearchParams();
    if (search) params.set('buscar', search);
    if (role) params.set('rol', role);
    if (status === 'todos') params.set('incluir_inactivos', '1');
    else if (status !== '') params.set('activo', status);
    try {
        const res = await apiFetch(`/api/gestion-usuarios${params.size ? `?${params}` : ''}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.error?.message || 'No se pudo cargar la gestión de usuarios.');
        managedUsers = json.data?.usuarios || [];
        renderManagedUsers();
    } catch (error) {
        showToast(error.message || 'Error al cargar usuarios', 'error');
    }
}

function renderManagedUsers() {
    const tbody = document.getElementById('users-management-tbody');
    if (!tbody) return;
    if (!managedUsers.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem; color: var(--text-muted);">No se encontraron cuentas con los filtros seleccionados.</td></tr>';
        return;
    }
    tbody.innerHTML = managedUsers.map(user => {
        const self = Number(user.id) === Number(STATE.currentUser?.id);
        const cannotDeactivateAreaHead = STATE.currentUser?.rol === 'JEFE_PERSONAL' && user.rol === 'JEFE_PLANEAMIENTO';
        const status = user.activo ? '<span class="badge badge-prioridad-BAJA">Activo</span>' : '<span class="badge badge-prioridad-URGENTE">Inactivo</span>';
        const statusButton = cannotDeactivateAreaHead && user.activo
            ? '<button class="btn btn-outline btn-sm" type="button" disabled title="El Jefe de Personal no puede desactivar una cuenta con rol Jefe de Área">Desactivación protegida</button>'
            : `<button class="btn ${user.activo ? 'btn-danger' : 'btn-success'} btn-sm" type="button" onclick="changeManagedUserStatus(${user.id}, ${user.activo ? 'false' : 'true'})">${user.activo ? (self ? 'Desactivar mi cuenta' : 'Desactivar') : 'Activar'}</button>`;
        return `<tr>
            <td>${escapeHtml(user.codigo)}</td>
            <td><strong>${escapeHtml(user.nombre)}</strong><br><span style="font-size:.75rem;color:var(--text-muted)">${escapeHtml(user.email)} · ${escapeHtml(user.cargo)}</span></td>
            <td>${escapeHtml(roleLabel(user.rol))}</td>
            <td>${status}</td>
            <td>${escapeHtml(user.created_at || '—')}</td>
            <td style="text-align:right"><div class="users-management-actions">
                <button class="btn btn-outline btn-sm" type="button" onclick="openManagedUserEditor(${user.id})">Editar</button>
                ${statusButton}
            </div></td>
        </tr>`;
    }).join('');
}

export function openManagedUserEditor(id = null) {
    if (!isCurrentUserJefe()) return;
    editingUserId = id;
    const user = id ? managedUsers.find(item => Number(item.id) === Number(id)) : null;
    const isEdit = Boolean(user);
    document.getElementById('user-editor-title').textContent = isEdit ? 'Editar cuenta' : 'Nueva cuenta';
    document.getElementById('user-editor-submit').textContent = isEdit ? 'Guardar cambios' : 'Crear cuenta';
    document.getElementById('managed-user-name').value = user?.nombre || '';
    document.getElementById('managed-user-email').value = user?.email || '';
    document.getElementById('managed-user-role').value = user?.rol || 'ASISTENTE';
    document.getElementById('managed-user-position').value = user?.cargo || '';
    const password = document.getElementById('managed-user-password');
    password.value = '';
    password.required = !isEdit;
    document.getElementById('managed-user-password-optional').textContent = isEdit ? '(opcional)' : 'inicial *';
    document.getElementById('managed-user-password-help').textContent = isEdit
        ? 'Déjala vacía para conservarla. Si indicas una nueva, la cuenta deberá iniciar sesión nuevamente.'
        : 'Asigna una contraseña y comunícala directamente al usuario.';
    openModal('modal-user-editor');
}

export async function saveManagedUser(event) {
    event?.preventDefault();
    const name = document.getElementById('managed-user-name').value.trim();
    const email = document.getElementById('managed-user-email').value.trim();
    const rol = document.getElementById('managed-user-role').value;
    const cargo = document.getElementById('managed-user-position').value.trim();
    const password = document.getElementById('managed-user-password').value;
    if (!name || !email || !cargo || (!editingUserId && password.length < 8)) {
        showToast('Completa los campos obligatorios y una contraseña de al menos 8 caracteres.', 'warning');
        return;
    }
    const payload = { nombre: name, email, rol, cargo };
    const editedUserId = editingUserId;
    const editingOwnAccount = Number(editedUserId) === Number(STATE.currentUser?.id);
    try {
        const url = editedUserId ? `/api/gestion-usuarios/${editedUserId}` : '/api/gestion-usuarios';
        const method = editedUserId ? 'PUT' : 'POST';
        if (!editedUserId) payload.password = password;
        const res = await apiFetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const json = await res.json();
        if (!json.success) throw new Error(json.error?.message || 'No se pudieron guardar los cambios.');
        if (editedUserId && password) {
            const passwordRes = await apiFetch(`/api/gestion-usuarios/${editedUserId}/cambiar-password`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ password }) });
            const passwordJson = await passwordRes.json();
            if (!passwordJson.success) throw new Error(passwordJson.error?.message || 'La cuenta se actualizó, pero no se pudo cambiar la contraseña.');
        }
        closeModal('modal-user-editor');
        showToast(editedUserId ? 'Cuenta actualizada correctamente.' : 'Cuenta creada correctamente.', 'success');
        if (editingOwnAccount && (password || json.data?.requiere_reinicio)) {
            await doLogout();
            return;
        }
        await loadManagedUsers();
    } catch (error) {
        showToast(error.message || 'Error al guardar la cuenta', 'error');
    }
}

export async function changeManagedUserStatus(id, active) {
    const target = managedUsers.find(item => Number(item.id) === Number(id));
    if (!target) return;
    const action = active ? 'activar' : 'desactivar';
    if (!confirm(`¿Deseas ${action} la cuenta de ${target.nombre}?`)) return;
    try {
        const res = await apiFetch(`/api/gestion-usuarios/${id}/estado`, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ activo: active }) });
        const json = await res.json();
        if (!json.success) throw new Error(json.error?.message || 'No se pudo actualizar el estado de la cuenta.');
        showToast(json.data?.mensaje || 'Estado de cuenta actualizado.', 'success');
        if (!active && Number(id) === Number(STATE.currentUser?.id)) {
            await doLogout();
            return;
        }
        await loadManagedUsers();
    } catch (error) {
        showToast(error.message || 'Error al actualizar la cuenta', 'error');
    }
}

export function initUserManagementEvents() {
    document.getElementById('btn-create-managed-user')?.addEventListener('click', () => openManagedUserEditor());
    document.getElementById('user-editor-form')?.addEventListener('submit', saveManagedUser);
    ['users-management-search', 'users-management-role', 'users-management-status'].forEach(id => {
        const input = document.getElementById(id);
        const event = id === 'users-management-search' ? 'input' : 'change';
        input?.addEventListener(event, loadManagedUsers);
    });
}
