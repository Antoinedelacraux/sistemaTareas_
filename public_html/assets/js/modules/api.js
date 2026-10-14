/**
 * MODULO API — CLIENTE HTTP CENTRALIZADO (REST API)
 * TAL S.A. — Área de Planeamiento
 * Maneja cabeceras de autenticación (Bearer Token), cookies HttpOnly y control de errores.
 */

export function getAuthToken() {
    return '';
}

export function setAuthToken(token) {
    // La cookie HttpOnly es la única fuente de autenticación del navegador.
    localStorage.removeItem('auth_token');
}

/**
 * Realiza una petición HTTP con autenticación automática.
 * @param {string} url - Ruta relativa o absoluta
 * @param {RequestInit} [options={}] - Opciones de fetch
 * @returns {Promise<Response>}
 */
export async function apiFetch(url, options = {}) {
    options.headers = options.headers || {};
    options.credentials = 'include';

    return fetch(url, options);
}

/**
 * Petición JSON simplificada con parseo automático y control de errores.
 * @template T
 * @param {string} url
 * @param {RequestInit} [options={}]
 * @returns {Promise<{success: boolean, data?: T, error?: {code: string, message: string}}>}
 */
export async function apiJson(url, options = {}) {
    try {
        const res = await apiFetch(url, options);
        const data = await res.json();
        return data;
    } catch (err) {
        return {
            success: false,
            error: {
                code: 'ERR_NETWORK_OR_PARSING',
                message: err.message || 'Error de conexión con el backend'
            }
        };
    }
}
