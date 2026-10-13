# Arquitectura técnica — propuesta, aún no aprobada

## Orientación
Monolito modular con cliente web, API, base de datos relacional y tareas programadas. No implementar microservicios de entrada.

## Stack propuesto (pendiente de decisión ADR)
- Frontend: React + TypeScript, biblioteca accesible para Kanban, UI responsiva.
- Backend: Python + FastAPI, capa API / aplicación / dominio / persistencia.
- Base de datos: PostgreSQL; ORM SQLAlchemy, migraciones Alembic.
- Notificaciones inicialmente dentro de la aplicación; correo opcional posterior.
- Despliegue: servicio administrado o equivalente estable, HTTPS, secretos administrados, backups y monitoreo. Docker solo si aporta reproducibilidad.

## Módulos lógicos
`identidad`, `autorizacion`, `usuarios`, `tableros`, `tareas`, `dependencias`, `aprobaciones`, `notificaciones`, `auditoria`, `reportes`.

## Invariantes técnicos a diseñar
1. Toda consulta de tarea verifica visibilidad. Prohibir referencias directas que permitan ver tareas de otros usuarios.
2. Permisos de creación y asignación no equivalen a lectura general.
3. Dependencias forman DAG; evaluar predecesoras en cierre confirmado/aprobado.
4. Operaciones de cierre + historial + desbloqueo deben ser transaccionales o usar patrón robusto de eventos.
5. Idempotencia ante reintentos y protección ante actualización simultánea.
6. Diferenciar estados de negocio de nombres editables de columnas.
7. Auditoría conserva sujeto y evento incluso si se desactiva usuario.
8. Gestión de adjuntos con almacenamiento protegido, no ejecutar cargas.

## Documento vivo
Describir aquí decisiones técnicas reales a medida que se aprueben y se implementen. No afirmar que algo ya existe sin inspección del código.
