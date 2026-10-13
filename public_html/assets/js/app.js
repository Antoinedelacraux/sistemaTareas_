/**
 * CLIENT BOOTSTRAP — SISTEMA DE TAREAS (PLANEAMIENTO)
 */

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    checkApiHealth();
});

/**
 * Inicializa y conmuta el modo oscuro/claro persistente.
 */
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
    if (iconSpan) {
        iconSpan.textContent = theme === 'dark' ? '☀️' : '🌙';
    }
}

/**
 * Comprueba la salud del backend REST PHP.
 */
async function checkApiHealth() {
    const statusBadge = document.getElementById('api-status-badge');
    const statusText = document.getElementById('api-status-text');

    try {
        const response = await fetch('/api/health');
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        
        if (result.success && result.data && result.data.status === 'ok') {
            if (statusBadge) {
                statusBadge.className = 'badge badge-success';
                statusBadge.textContent = 'En Línea';
            }
            if (statusText) {
                statusText.textContent = `Backend PHP ${result.data.php_version} activo en entorno ${result.data.environment}.`;
            }
        }
    } catch (err) {
        if (statusBadge) {
            statusBadge.className = 'badge';
            statusBadge.style.backgroundColor = 'var(--status-blocked-bg)';
            statusBadge.style.color = 'var(--status-blocked)';
            statusBadge.textContent = 'Sin Servidor Local';
        }
        if (statusText) {
            statusText.textContent = 'El backend PHP responderá cuando se inicie el servidor web Apache en DonWeb Ferozo o localmente con php -S.';
        }
    }
}
