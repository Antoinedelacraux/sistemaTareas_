/**
 * MODULO COLUMNS — CREACIÓN Y PERSONALIZACIÓN DE COLUMNAS ESTILO TRELLO
 * TAL S.A. — Área de Planeamiento
 * Permite crear más columnas, nombrarlas y asignarles paletas cromáticas diferenciadas.
 */

import { apiFetch } from './api.js';
import { STATE } from './state.js';
import { showToast, openModal, closeModal } from './ui.js';
import { fetchColumns, fetchTasks } from './kanban.js';

let editingColumnId = null;

export const TRELLO_PALETTE = [
    { name: 'Azul Océano', hex: '#2563eb' },
    { name: 'Púrpura Real', hex: '#7c3aed' },
    { name: 'Verde Esmeralda', hex: '#059669' },
    { name: 'Ámbar Cálido', hex: '#d97706' },
    { name: 'Rosa Coral', hex: '#e11d48' },
    { name: 'Cian Glaciar', hex: '#0891b2' },
    { name: 'Índigo Profundo', hex: '#4f46e5' },
    { name: 'Gris Pizarra', hex: '#475569' }
];

/**
 * Abre el modal para crear una nueva columna.
 */
export function openCreateColumnModal() {
    editingColumnId = null;
    document.getElementById('column-modal-title').textContent = STATE.currentScope === 'personal' ? 'Nueva Columna Personal' : 'Nueva Columna del Área';
    document.getElementById('column-name-input').value = '';
    document.getElementById('column-type-select').value = 'EN_PROCESO';
    document.getElementById('btn-delete-column').style.display = 'none';

    selectColorHex('#2563eb');
    renderColorSwatches('#2563eb');
    openModal('modal-column-editor');
}

/**
 * Abre el modal para editar una columna existente.
 */
export function openEditColumnModal(colId) {
    const col = STATE.columns.find(c => parseInt(c.id, 10) === parseInt(colId, 10));
    if (!col) return;

    editingColumnId = col.id;
    document.getElementById('column-modal-title').textContent = `Editar Columna: ${col.nombre}`;
    document.getElementById('column-name-input').value = col.nombre;
    document.getElementById('column-type-select').value = col.tipo_estado || 'EN_PROCESO';
    document.getElementById('btn-delete-column').style.display = 'inline-flex';

    selectColorHex(col.color_hex || '#2563eb');
    renderColorSwatches(col.color_hex || '#2563eb');
    openModal('modal-column-editor');
}

/**
 * Renderiza los botones de selección rápida de color estilo Trello.
 */
export function renderColorSwatches(selectedHex) {
    const container = document.getElementById('column-color-swatches');
    if (!container) return;

    container.innerHTML = TRELLO_PALETTE.map(c => `
        <button type="button" 
            class="color-swatch-btn ${c.hex.toLowerCase() === selectedHex.toLowerCase() ? 'active' : ''}" 
            style="background-color: ${c.hex};" 
            title="${c.name}" 
            aria-label="${c.name}"
            onclick="selectColumnColor('${c.hex}')">
        </button>
    `).join('');
}

export function selectColorHex(hex) {
    const hidden = document.getElementById('column-color-hex-input');
    if (hidden) hidden.value = hex;
    renderColorSwatches(hex);
}

/**
 * Procesa el guardado (creación o actualización) de la columna.
 */
export async function handleSaveColumn(e) {
    if (e) e.preventDefault();

    const nombre = document.getElementById('column-name-input').value.trim();
    const tipoEstado = document.getElementById('column-type-select').value;
    const colorHex = document.getElementById('column-color-hex-input').value || '#2563eb';

    if (!nombre) {
        showToast('El nombre de la columna es obligatorio', 'error');
        return;
    }

    try {
        const isEditing = editingColumnId !== null;
        const url = isEditing ? `/api/tableros/columnas/${editingColumnId}` : '/api/tableros/columnas';
        const method = isEditing ? 'PUT' : 'POST';

        const res = await apiFetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre,
                tipo_estado: tipoEstado,
                color_hex: colorHex,
                scope: STATE.currentScope === 'personal' ? 'personal' : 'area'
            })
        });

        const result = await res.json();
        if (result.success) {
            closeModal('modal-column-editor');
            showToast(isEditing ? 'Columna actualizada' : '¡Columna creada con éxito!', 'success');
            await fetchColumns();
            await fetchTasks();
        } else {
            showToast(result.error?.message || 'Error al guardar columna', 'error');
        }
    } catch {
        showToast('Error de conexión al procesar la columna', 'error');
    }
}

/**
 * Elimina una columna que no tenga tareas asociadas.
 */
export async function handleDeleteColumn() {
    if (!editingColumnId) return;

    if (!confirm('¿Estás seguro de que deseas eliminar esta columna? Solo se puede eliminar si no contiene tareas.')) {
        return;
    }

    try {
        const res = await apiFetch(`/api/tableros/columnas/${editingColumnId}`, {
            method: 'DELETE'
        });
        const result = await res.json();
        if (result.success) {
            closeModal('modal-column-editor');
            showToast('Columna eliminada', 'success');
            await fetchColumns();
            await fetchTasks();
        } else {
            showToast(result.error?.message || 'No se puede eliminar la columna', 'error');
        }
    } catch {
        showToast('Error de conexión al eliminar columna', 'error');
    }
}
