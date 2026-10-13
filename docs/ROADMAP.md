# Roadmap del proyecto

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
- [x] MVP-05 Subtareas, comentarios, adjuntos seguros (22/22 tests PASS, anti-IDOR en descargas y uploads seguros). [COMPLETADO]
- [ ] MVP-06 Confirmación de cierre y aprobaciones. [PENDIENTE]
- [ ] MVP-07 Dependencias secuenciales/paralelas y DAG. [PENDIENTE]
- [ ] MVP-08 Automatizaciones y notificaciones idempotentes. [PENDIENTE]
- [ ] MVP-09 Historial, auditoría, dashboard básico. [PENDIENTE]
- [ ] MVP-10 QA integral, seguridad y piloto con 5 usuarios. [PENDIENTE]

## Fase 2
Plantillas, tareas recurrentes, tiempos de trabajo, calendario laboral.

## Fase 3
Indicadores avanzados, exportaciones Excel, reglas avanzadas de automatización.

## Plantilla de tarea
ID | prioridad | estado | objetivo | RF/RN | archivos/módulos | criterios de aceptación | riesgos | pruebas | evidencia | commit.
