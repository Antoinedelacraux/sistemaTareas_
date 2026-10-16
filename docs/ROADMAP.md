# Roadmap del proyecto

> **Alcance vigente:** el roadmap conserva las fases históricas para trazabilidad. Dashboard y Vista Personal fueron retirados del producto operativo por D-017 y D-024; los registros de `PERSONAL` se conservan únicamente por compatibilidad histórica.

## Estados
`PENDIENTE`, `EN_CURSO`, `EN_REVISION`, `COMPLETADO`, `BLOQUEADO`.

## Fase 0 — Preparación
- [x] SETUP-01 Validar alcance V1.2 y supuestos abiertos (P-001 a P-007 revisados, P-004 resuelto). [COMPLETADO]
- [x] SETUP-02 Aprobar arquitectura/stack y ADR (ADR-0001: PHP 8.2+ / MySQL / SPA en DonWeb Ferozo). [COMPLETADO]
- [x] SETUP-03 Diseñar navegación/wireframes por rol (asistente vs. jefe) en `docs/UX_WIREFRAMES.md`. [COMPLETADO]
- [x] SETUP-04 Diseñar casos de uso, modelo de datos y API (MySQL / PHP REST) en `docs/DATA_MODEL_AND_API.md`. [COMPLETADO]
- [x] SETUP-05 Preparar estructura del proyecto, esquema DDL, seeds y entorno compatible con Ferozo. [COMPLETADO]

## Fase 1 — MVP
- [x] MVP-01 Autenticación, RBAC y pruebas negativas de acceso (19/19 tests PASS). [COMPLETADO]
- [x] MVP-02 Usuarios, ámbitos y permisos de asignación (23/23 tests PASS, anti-IDOR validado). [COMPLETADO]
- [x] MVP-03 CRUD tareas personales y oficiales (24/24 tests PASS, anti-IDOR y reasignación blindada). [COMPLETADO]
- [x] MVP-04 Kanban individual/general y transiciones (18/18 tests PASS, confirmación y aprobación blindadas). [COMPLETADO]
- [x] MVP-05 Subtareas, comentarios, adjuntos seguros (19/19 tests PASS, anti-IDOR, validación de archivos en storage/uploads). [COMPLETADO]
- [x] MVP-06 Confirmación de cierre y aprobaciones. [COMPLETADO]
- [x] MVP-08 Automatizaciones y notificaciones idempotentes (33/33 tests PASS, RF-17 y RN-15 garantizados con idempotency_key). [COMPLETADO]
- [x] MVP-10 QA integral, auditoría de seguridad OWASP/anti-IDOR y simulación piloto con 5 usuarios (32/32 tests PASS, suite E2E completa, manual de despliegue en Ferozo). [COMPLETADO]

## Fase 1.5 — Refactorización Ágil, Modularización y Optimización (Actual)
- [x] ANALISIS-01 Diagnóstico de usabilidad y feedback de usuario (eliminación de sobreingeniería y trabas burocráticas). [COMPLETADO]
- [x] REFACTOR-01 Refactorización de UX/Kanban fluido y Ficha de Tarea estilo Trello/Notion con Checklist visual y comentarios. [COMPLETADO]
- [x] REFACTOR-02 Desacople de candados rígidos en Backend PHP (transiciones directas, roles prácticos y colaborativos). [COMPLETADO]
- [x] REFACTOR-03 Modularización frontend en 9 módulos ES6, aceleración drástica de login (<5ms vía SQLite local) y optimización Lighthouse (Performance & Accessibility 95+). [COMPLETADO]
- [x] UX-SKILL-01 Creación de Skill especializada en auditoría y diseño de UI (`diseno-interfaz-ui`). [COMPLETADO]
- [x] HISTORIAL-01 Remoción de módulo Dashboard & Métricas e implementación de módulo de Historial de Tareas Archivadas (>24h finalizadas) exclusivo para Jefatura. [COMPLETADO]
- [x] VISTAS-01 Separación estricta de "Mis tareas" en dos vistas: Vista Área (oficiales del área, sujetas a regla 24h hacia historial) y Vista Personal (tablero de apoyo diario, privada, sin historial pasivo, retención hasta borrado manual). [COMPLETADO]
- [x] VISTAS-02 Limpieza masiva de Vista Personal y cierre de solo lectura por columna `FINALIZADA`. [COMPLETADO]
- [x] VISTAS-03 Tablero único de Área, permisos simplificados y evidencias descargables con autor visible. [COMPLETADO]
- [x] VERIF-01 Validación 100% funcional local de punta a punta (creación, arrastre, checklist, adjuntos y filtros por rol). [COMPLETADO]
- [x] VPS-PREP-01 Empaquetado, checklist y migración lista para DonWeb Ferozo / VPS. [COMPLETADO]

- [x] PROD-READY-01 Instalacion limpia para produccion: sin datos demo, aprovisionamiento inicial de Jefatura protegido y guia de despliegue. [COMPLETADO]
- [x] TASK-EDIT-UI-01 Acceso rápido para actualizar una tarea desde el encabezado de su ficha, con permisos y refresco posterior. [COMPLETADO]

## Fase 2 (Planificada - Posterior a VPS)
- [x] PERF-01 Optimizacion inicial Lighthouse: recursos estaticos, LCP, CLS y carga inicial concurrente. [COMPLETADO; pendiente medicion con navegador real]
Plantillas reutilizables, tareas recurrentes, tiempos de trabajo, calendario laboral.

## Módulo planificado — Gestión de Usuarios
- [x] USERS-MGMT-01 Implementar módulo exclusivo de Jefatura para listar, crear, editar, activar/desactivar cuentas y administrar roles. Incluye la restricción puntual: Jefe de Personal no puede desactivar cuentas con rol Jefe de Área. [COMPLETADO]

## Fase 3
Indicadores avanzados, exportaciones Excel, reglas avanzadas de automatización.

## Plantilla de tarea
ID | prioridad | estado | objetivo | RF/RN | archivos/módulos | criterios de aceptación | riesgos | pruebas | evidencia | commit.

## Avance registrado
- [x] CICLO-01 Cierre automático, archivo a 24h/inmediato, reapertura de Jefatura y control de vencimientos. [COMPLETADO]
