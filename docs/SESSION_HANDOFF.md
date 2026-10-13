# Traspaso entre sesiones (leer primero)

## Estado actual verificable
- Repositorio Git inicializado con commits `652765c` (configuración) y `d0df541` (arquitectura Ferozo).
- Plataforma de despliegue consolidada: DonWeb Web Hosting tradicional (Ferozo) con stack PHP 8.2+ / MySQL / SPA (ADR-0001).
- Diseño UI/UX y wireframes completados en `docs/UX_WIREFRAMES.md` (SETUP-03): vistas por rol, flujos modales de confirmación/devolución y tokens de diseño.
- Fase 0 en progreso ordenado: SETUP-01, SETUP-02 y SETUP-03 completados.

## Decisiones cerradas
- Ver D-001 a D-010 (Alcance V1.2 y reglas de negocio) y **D-011 / ADR-0001** (Stack para DonWeb Ferozo) en `docs/DECISIONS.md`.
- Especificación UX aprobada en `docs/UX_WIREFRAMES.md`.

## Bloqueos y pendientes
- Sin bloqueos técnicos. Siguiente hito de Fase 0: **SETUP-04** (Diseñar modelo de datos relacional MySQL y especificación de endpoints API REST).

## Próximo paso recomendado
1. Registrar commit del checkpoint documental de SETUP-03.
2. Iniciar **SETUP-04**: Diseñar el modelo de datos relacional MySQL (tablas, relaciones, llaves foráneas, índices, restricciones ACID para DAG y transiciones) y los contratos de la API REST (endpoints, request/response, validaciones y códigos de estado HTTP).

## Datos para la próxima sesión
- Último commit verificado: `d0df541` (más el commit de SETUP-03).
- Pruebas: Consistencia de diseño UX contra especificación V1.2 (RF-01 a RF-19, RN-01 a RN-15).
- Siguiente tarea: SETUP-04 (Diseñar casos de uso, modelo de datos y API).

Actualizar este documento al FINAL de cada avance, sin prometer trabajo futuro automático.
