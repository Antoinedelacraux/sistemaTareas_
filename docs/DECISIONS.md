# Registro de decisiones y supuestos

## Decisiones CONFIRMADAS con el usuario
- D-001 Equipo inicial 5: tres asistentes y dos jefes.
- D-002 Creación de tareas por jefes y asistentes sujeta a permisos.
- D-003 Asistentes solo asignan a sí mismos; jefes asignan a otros.
- D-004 Asistentes solo ven sus propias tareas, jefes supervisan tareas oficiales.
- D-005 Aprobación solo cuando esté configurada.
- D-006 Confirmación adicional para cierre sin aprobación.
- D-007 Automatización de movimiento de estados y secuencias.
- D-008 Procesos secuenciales y paralelos.
- D-009 Tareas personales y del área separadas.
- D-010 Acceso web seguro desde cualquier ubicación.

## Propuestas pendientes de validación
- P-001 Privacidad de tareas personales también ante jefes, exclusión de KPIs oficiales.
- P-002 Motor de dependencias AND para habilitar sucesores; OR futuro si lo requiere el proceso.
- P-003 Estado inicial de 5 columnas Kanban.
- P-004 Stack sugerido React/TypeScript + FastAPI/Python + PostgreSQL.
- P-005 Identidad corporativa, MFA obligatoria, SSO y hosting todavía por definir con TI.
- P-006 Gestión de adjuntos: tipos admitidos, límites, almacenamiento y retención.
- P-007 Modo de reasignar/convertir tarea personal a oficial y su autorización.

## ADR (Architecture Decision Records)
Al aprobar decisiones técnicas, registrar una entrada nueva con fecha real:
`ADR-000N | Estado: propuesta/aceptada/sustituida | Contexto | Alternativas | Decisión | Consecuencias | Aprobador`.
No marcar propuestas técnicas como aceptadas de manera autónoma.
