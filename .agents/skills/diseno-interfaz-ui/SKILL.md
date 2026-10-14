---
name: diseno-interfaz-ui
description: "Auditoría y diseño de interfaz de usuario: márgenes, centrado de iconos, contraste WCAG, rendimiento Lighthouse y micro-animaciones dinámicas."
---

# Workflow: Diseño y Revisión de Elementos de Interfaz (UI/UX)

Usa esta habilidad para diseñar, revisar o auditar componentes visuales, garantizando alta estética corporativa, alineación matemática de elementos, accesibilidad WCAG AA/AAA y puntaje Lighthouse >= 95.

---

## 1. Auditoría de Márgenes y Sistema de Espaciado (Grid de 8px / 4px)
- **Escala de Espaciado Estricta:**
  - `4px (0.25rem)`: Micro-separaciones entre iconos y texto compacto.
  - `8px (0.5rem)`: Espaciado interno en badges, chips y botones compactos.
  - `12px (0.75rem)`: Separación entre inputs, etiquetas y listas secundarias.
  - `16px (1rem)`: Padding base de tarjetas, modales compactos y botones estándar.
  - `24px (1.5rem)`: Padding de contenedores, columnas kanban y modales principales.
  - `32px (2rem)`: Separación entre secciones estructurales.
- **Regla de Caja:**
  - Evitar márgenes colapsables arbitrarios (`margin: 13px`, `padding: 7px`).
  - Utilizar `gap` en contenedores Flexbox y Grid en lugar de `margin-right` / `margin-bottom` individuales.

---

## 2. Centrado y Alineación Geométrica de Iconos
- **Iconos dentro de Inputs:**
  - Contenedor con `position: relative; display: flex; align-items: center;`.
  - Icono posicionado con:
    ```css
    .input-field-icon {
      position: absolute;
      left: 1rem;
      width: 1.125rem;
      height: 1.125rem;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
    }
    .form-input {
      padding-left: 2.75rem; /* Asegura holgura visual de 16px entre icono y texto */
    }
    ```
- **Iconos en Botones y Badges:**
  - Siempre `display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;`.
  - Los iconos SVG deben declarar `flex-shrink: 0; width: 1rem; height: 1rem;`.
  - Atributos obligatorios en SVGs decorativos: `aria-hidden="true"` y `focusable="false"`.

---

## 3. Accesibilidad y Contraste WCAG (100% Lighthouse A11y)
- **Ratio de Contraste Mínimo:**
  - Texto estándar: mínimo **4.5:1** contra el fondo.
  - Badges y estados: jamás usar texto claro (#f59e0b, #0284c7) sobre fondos pálidos sin verificar el ratio.
    - Asistente / Informativo: Texto `#0369a1` sobre fondo `#e0f2fe` (ratio > 5.5:1).
    - Jefatura / Primario: Texto `#4338ca` sobre fondo `#eef2ff` (ratio > 6.8:1).
    - Advertencia / Proceso: Texto `#92400e` sobre fondo `#fef3c7` (ratio > 5.0:1).
    - Peligro / Urgente: Texto `#b91c1c` sobre fondo `#fee2e2` (ratio > 5.8:1).
    - Éxito / Finalizado: Texto `#047857` sobre fondo `#d1fae5` (ratio > 6.0:1).
- **Etiquetas y Nombres Accesibles:**
  - Todo `<input>` y `<select>` debe tener un `<label for="id">` explícito o un `aria-label` descriptivo.
  - Todo `<button>` con solo icono debe tener `aria-label="Acción descriptiva"`.
  - Todos los modales deben tener un botón de cerrar con `aria-label="Cerrar ventana modal"`.

---

## 4. Rendimiento Extremo en Lighthouse (Score >= 95)
- **Eliminación de Render-Blocking:**
  - Prohibido `@import url(...)` dentro de archivos CSS.
  - Cargar fuentes vía `<link rel="preconnect">` y `<link rel="stylesheet">` con `font-display: swap` en `<head>`.
  - Declarar pila tipográfica de respaldo nativa (`system-ui, -apple-system, sans-serif`) para FCP instantáneo.
- **Pintado Inicial Inmediato (Zero-Delay FCP):**
  - Nunca ocultar la vista inicial de login con `display: none` esperando respuestas asíncronas de red.
  - Renderizar el login directamente en HTML/CSS. Si existe sesión previa en `localStorage`, la transición se ejecuta en milisegundos.
- **Imágenes Optimizadas:**
  - Usar SVGs inline o formatos modernos (WebP/AVIF) con dimensiones fijas (`width` y `height`) para prevenir Cumulative Layout Shift (CLS = 0).

---

## 5. Dinamismo y Micro-interacciones sin Penalizar CPU
- **Aceleración por Hardware (GPU):**
  - Animar únicamente propiedades compuestas por GPU: `transform` y `opacity`.
  - Prohibido animar `width`, `height`, `top`, `left`, `margin` o `box-shadow` pesados en bucles continuos.
- **Transiciones Ergonométricas:**
  - Duración óptima: `150ms` para feedback táctil / clicks; `250ms` para apertura de modales y hover.
  - Timing function: `cubic-bezier(0.16, 1, 0.3, 1)` (suavizado natural estilo Apple/Notion).
- **Estados Activos y Focus-Visible:**
  - Aplicar `outline: 2px solid var(--primary-500); outline-offset: 2px;` solo en `:focus-visible` para accesibilidad sin alterar el clic con mouse.
