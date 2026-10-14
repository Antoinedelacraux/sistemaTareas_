# Sistema de Gestión y Seguimiento de Tareas (TAL S.A.) — Guía Rápida

Bienvenido al sistema de gestión de tareas ágil, ligero y 100% funcional, diseñado específicamente para el equipo de Planeamiento y compatible de forma nativa con **PHP 8.2+ / MySQL / Vanilla JS** para despliegue directo en **DonWeb Ferozo**.

---

## 🚀 ¿Cómo probar el sistema inmediatamente en tu máquina?

El servidor de desarrollo local ya se encuentra activo en segundo plano:

1. **Abre tu navegador:**
   👉 Ve a: [http://127.0.0.1:8000](http://127.0.0.1:8000)

2. **Inicia sesión con cualquiera de los usuarios de prueba:**
   - **Como Asistente (Marco Chumbes):**
     - Correo: `mchumbes@planeamiento.tal`
     - Contraseña: `Planeamiento2026!`
   - **Como Jefa de Planeamiento (Elena Vega):**
     - Correo: `jefe.planeamiento@planeamiento.tal`
     - Contraseña: `Planeamiento2026!`

---

## ✨ Características del Nuevo Enfoque Ágil (V1.3)

1. **Kanban Fluido con Drag & Drop Nativo:**
   - Arrastra cualquier tarjeta a la columna deseada y se mueve de inmediato, sin modales ni confirmaciones que frenen el flujo.
2. **Vistas Separadas y Prácticas:**
   - **"Mis Tareas":** Tu tablero personal enfocado en lo que tienes asignado hoy.
   - **"Tablero del Equipo":** Tablero colaborativo para ver en qué está trabajando cada miembro y evitar duplicar esfuerzos.
   - **"Dashboard & Métricas":** Resumen ejecutivo con porcentaje de avance, tareas atrasadas y balance por integrante.
3. **Filtros Ágiles en Vivo:**
   - Buscador instantáneo por código o título.
   - Selector interactivo para filtrar tareas por integrante del equipo (`Todos los miembros`, `Marco`, `Elena`, etc.).
   - Filtro por prioridad (`Urgente`, `Alta`, `Media`, `Baja`).
4. **Modal de Detalle Dinámico:**
   - **Checklist de subtareas:** Agrega subtareas y márcalas con cálculo automático de porcentaje de progreso.
   - **Comentarios y notas:** Historial cronológico con opción de adjuntar evidencias y archivos.
   - **Reasignación rápida:** Cambia el responsable o columna desde el modal.
   - **Eliminar tarea:** Botón directo para descartar tareas que ya no aplican.

---

## 🧪 Pruebas Automatizadas (100% Verificado)

Para verificar que todos los módulos y la seguridad backend funcionan al 100%:

```bash
php tests/run_all.php
```

Resultado: **10 suites de prueba ejecutadas, 10 suites aprobadas (100% PASS, 250+ tests)**.

---

## 📂 Estructura y Documentación

- `docs/DECISIONS.md`: Registro de decisiones técnicas y de producto (incluyendo D-012 sobre simplificación ágil).
- `docs/PROJECT_CONTEXT.md`: Contexto arquitectónico y visión del sistema V1.3.
- `docs/REQUIREMENTS.md`: 12 requisitos funcionales prácticos y reglas de negocio.
- `docs/ROADMAP.md`: Estado de fases y tareas completadas.
- `docs/PROGRESS.md`: Bitácora histórica del trabajo verificado.
- `docs/SESSION_HANDOFF.md`: Traspaso técnico para futuras sesiones.
- `docs/DEPLOYMENT_AND_PILOT_GUIDE.md`: Guía paso a paso para subir el sistema a DonWeb Ferozo.
