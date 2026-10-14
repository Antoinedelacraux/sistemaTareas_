---
name: modularizar-archivos-grandes
description: "Separa archivos monolíticos extensos (HTML, CSS, JS, PHP) en módulos atómicos de responsabilidad única para escalar la interfaz."
---

# Workflow: Modularización y Separación de Responsabilidades en Archivos Extensos

Usa esta habilidad para descomponer archivos que superen las 300-400 líneas de código o que mezclen múltiples responsabilidades visuales (ej. Login mezclado con Dashboard, estilos globales con componentes específicos, modales incrustados en vistas principales).

---

## 1. Criterios de Detección de Monolitos Visuales
- **HTML > 400 líneas:** Archivo que contiene estructura de autenticación, vistas operativas (Kanban, tablas), y múltiples diálogos modales.
- **CSS > 500 líneas:** Archivo único que acumula tokens de diseño, resets, layouts, componentes específicos y animaciones.
- **JS > 400 líneas:** Controladores o módulos que gestionan eventos de múltiples vistas no relacionadas entre sí.
- **Síntoma Crítico de Coexistencia:** Vistas de autenticación (Login) solapándose visualmente con vistas privadas (Tableros, Headers) por colisión de estilos o renderizado simultáneo.

---

## 2. Estrategia de Separación por Tipo de Archivo

### A. Descomposición de HTML / Vistas
1. **Crear directorio de partials (`public_html/partials/`):**
   - `head.html`: Metadatos, fuentes, stylesheets base.
   - `header.html`: Barra superior corporativa y controles de usuario.
   - `workspace_bar.html`: Pestañas de navegación, filtros y botones de acción.
   - `login.html`: **Vista de autenticación 100% aislada**. No debe depender de elementos del layout interno.
   - `kanban.html`: Estructura del tablero y columnas operativas.
   - `approvals.html`: Bandeja de revisión y dictamen.
   - `history.html`: Repositorio y tablas históricas.
   - `modals.html`: Contenedor exclusivo de todos los diálogos modales y formularios emergentes.
   - `footer.html`: Pie de página, indicadores de estado y scripts base.
2. **Ensamblado Limpio:**
   - En entornos PHP: Usar inclusión nativa directa (`require __DIR__ . '/partials/login.html';`).
   - En entornos estáticos: Mantener el archivo maestro sincronizado sin duplicidad de lógica.

### B. Descomposición de CSS por Dominios
1. **Crear directorio modular (`public_html/assets/css/`):**
   - `variables.css`: Tokens de diseño, paleta de colores HSL/Hex, tipografía, radios, sombras, variables CSS light y dark theme.
   - `base.css`: Reset, estilos globales de body, tipografía, botones base, badges, inputs e iconos base.
   - `header.css`: Header corporativo, dropdown de notificaciones, toggle de tema, barra de workspace (`#workspace-controls-bar`).
   - `login.css`: **Estilos exclusivos y 100% aislados del Login**. Prohibido usar `!important` en propiedades de display que impidan ocultar la vista al autenticar.
   - `kanban.css`: Tablero kanban, columnas con scrollbar personalizado, tarjetas de tarea (`.task-card`, `.task-card-closed`), drag & drop.
   - `views.css`: Vistas especializadas (Bandeja de Aprobaciones, Repositorio de Historial).
   - `modals.css`: Modales accesibles, backdrops, diálogos, checklist interactivo, árbol de dependencias, auditoría y comentarios.
2. **Punto de Entrada Unificado:**
   - `app.css` actúa como orquestador importando cada módulo mediante `@import` en orden de cascada predecible:
     ```css
     @import './variables.css';
     @import './base.css';
     @import './header.css';
     @import './login.css';
     @import './kanban.css';
     @import './views.css';
     @import './modals.css';
     ```

### C. Aislamiento Estricto de la Vista de Login
- **Regla de Oro:** La vista de Login y la vista de Aplicación Privada son estados mutuamente excluyentes.
- Cuando `#login-view` está activo:
  - Ocultar `.app-header` o reducirlo al modo branding neutro (sin avatar, sin notificaciones, sin botón de salir).
  - Ocultar `#workspace-controls-bar`.
  - Ocultar `#kanban-view`, `#approvals-view`, `#history-view`.
- Al transicionar a sesión iniciada (`onLoginSuccess`):
  - `#login-view` debe recibir `display: none` explícito e incondicional.
  - El contenedor principal muestra el tablero kanban o la vista solicitada.

---

## 3. Protocolo de Verificación tras la Modularización
1. **Integridad del Layout:** Comprobar que ningún estilo se perdió o desalineó al dividir los archivos.
2. **Cero `!important` de bloqueo:** Verificar que ninguna regla de visualización fuerce la visibilidad de elementos que deben ocultarse.
3. **Validación de Pruebas:** Ejecutar la suite de pruebas unitarias/funcionales del sistema (ej. `/api/test-runner`) para garantizar 100% PASS.
4. **Limpieza y Documentación:** Registrar la nueva estructura en `docs/ARCHITECTURE.md` y actualizar `docs/PROGRESS.md` y `docs/SESSION_HANDOFF.md`.
