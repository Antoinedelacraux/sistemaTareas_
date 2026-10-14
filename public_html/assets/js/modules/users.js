/**
 * MODULO USERS — CREACIÓN Y GESTIÓN DE USUARIOS Y EQUIPO (RF-02)
 * TAL S.A. — Área de Planeamiento
 */

import { apiFetch } from './api.js';
import { STATE, isCurrentUserJefe } from './state.js';
import { showToast, openModal, closeModal } from './ui.js';
import { fetchUsers } from './kanban.js';

let onUserCreatedCallback = null;

export function registerUserCallbacks({ onUserCreated }) {
    if (onUserCreated) onUserCreatedCallback = onUserCreated;
}

/**
 * Abre el modal para registrar un nuevo integrante del equipo.
 */
export function openCreateUserModal() {
    if (!isCurrentUserJefe()) {
        showToast('Solo el personal de Jefatura puede dar de alta nuevos usuarios', 'error');
        return;
    }

    const form = document.getElementById('create-user-form');
    if (form) form.reset();

    const passInput = document.getElementById('new-user-password');
    if (passInput) passInput.value = 'Planeamiento2026!';

    openModal('modal-create-user');
}

/**
 * Procesa el formulario de creación de usuario.
 */
export async function handleCreateUser(e) {
    if (e) e.preventDefault();

    const nombre = document.getElementById('new-user-name').value.trim();
    const email = document.getElementById('new-user-email').value.trim();
    const rol = document.getElementById('new-user-role').value;
    const cargo = document.getElementById('new-user-position').value.trim();
    const password = document.getElementById('new-user-password').value;

    if (!nombre || !email) {
        showToast('El nombre y el correo institucional son obligatorios', 'error');
        return;
    }

    try {
        const res = await apiFetch('/api/usuarios', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre,
                email,
                rol,
                cargo,
                password
            })
        });

        const result = await res.json();
        if (result.success) {
            closeModal('modal-create-user');
            showToast(`¡Usuario ${result.data?.usuario?.nombre || nombre} creado con éxito!`, 'success');
            await fetchUsers();
            if (onUserCreatedCallback) onUserCreatedCallback(result.data?.usuario);
        } else {
            showToast(result.error?.message || 'Error al registrar el usuario', 'error');
        }
    } catch {
        showToast('Error de conexión al registrar usuario', 'error');
    }
}
