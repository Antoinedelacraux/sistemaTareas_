# Diseño funcional — Módulo de Gestión de Usuarios

**Estado:** implementado.
**Alcance:** administración exclusiva de cuentas, roles y acceso; no modifica la lógica de tareas, tablero, aprobaciones ni historial.

## Objetivo

Sustituir el botón aislado de alta de usuarios por un módulo dedicado, disponible solo para Jefatura, que permita consultar, crear, actualizar, activar o desactivar cuentas y asignar permisos mediante roles.

## Decisiones confirmadas

- El Jefe de Área es la autoridad de mayor jerarquía en la gestión de cuentas.
- Jefe de Área y Jefe de Personal conservan los mismos permisos operativos en los módulos de tareas.
- Los permisos se asignan por rol; no habrá permisos individuales en esta versión.
- Un usuario puede desactivar su propia cuenta. La sesión deberá finalizar inmediatamente.
- Las contraseñas se cambian manualmente por una jefatura; no se envían enlaces ni correos de restablecimiento.
- Ambos roles de Jefatura pueden crear, editar, cambiar rol, restablecer contraseña, activar y desactivar cuentas. La única excepción es que el Jefe de Personal no puede desactivar una cuenta cuyo rol sea Jefe de Área.

## Experiencia de usuario

La barra principal de Jefatura tendrá una pestaña **Gestión de Usuarios** en lugar del botón actual `Usuario`.

La vista tendrá:

- Tabla con código, nombre, correo, cargo, rol, estado y fecha de creación.
- Búsqueda por nombre, correo o código.
- Filtros por rol y estado.
- Acción para crear, editar, activar, desactivar y cambiar contraseña.
- Ficha lateral o modal de edición con historial administrativo de la cuenta.

Los asistentes no verán la pestaña ni podrán acceder a sus rutas administrativas.

## Permisos por rol

| Acción | Asistente | Jefe de Personal | Jefe de Área |
| --- | --- | --- | --- |
| Ver y gestionar tareas del Área | Según permisos operativos vigentes | Sí | Sí |
| Acceder a Gestión de Usuarios | No | Sí | Sí |
| Crear cuentas de asistentes | No | Sí | Sí |
| Editar o desactivar asistentes | No | Sí | Sí |
| Cambiar contraseña de asistentes | No | Sí | Sí |
| Crear o modificar cuentas de jefatura | No | Sí | Sí |
| Cambiar roles de jefatura | No | Sí | Sí |
| Desactivar una cuenta con rol Jefe de Área | No | No | Sí |
| Gestionar su propia cuenta | Consulta | Consulta y auto-desactivación | Consulta y auto-desactivación |

## Reglas de negocio propuestas

- Una cuenta inactiva no puede iniciar sesión ni recibir asignaciones nuevas.
- La desactivación no elimina datos: tareas, comentarios, evidencias y auditoría se conservan.
- La auto-desactivación cierra la sesión de inmediato y requiere confirmación explícita.
- Al cambiar una contraseña, se almacena únicamente su hash. La nueva contraseña se muestra o comunica solo en el momento de asignarla, y se invalidan las sesiones de la cuenta afectada.
- Al cambiar el rol de una cuenta se invalidan sus sesiones para que el nuevo permiso se aplique al volver a iniciar sesión.
- El formulario no permite asignar un rol o permiso inexistente.
- Las modificaciones de cuenta registran auditoría: ejecutor, cuenta afectada, acción, fecha y valores relevantes sin exponer contraseñas.

## Separación técnica

El módulo administrativo será independiente del directorio de personas usado por tareas:

```text
Consulta de responsables para tareas: GET /api/usuarios
Gestión administrativa:              /api/gestion-usuarios/*
```

Propuesta de endpoints:

```text
GET    /api/gestion-usuarios
POST   /api/gestion-usuarios
GET    /api/gestion-usuarios/{id}
PUT    /api/gestion-usuarios/{id}
PATCH  /api/gestion-usuarios/{id}/estado
POST   /api/gestion-usuarios/{id}/cambiar-password
GET    /api/gestion-usuarios/roles
```

La autorización se concentrará en una política propia de gestión de usuarios. El módulo de tareas continuará consumiendo solo usuarios asignables y activos, sin conocer formularios, auditoría ni reglas administrativas.

## Criterios de aceptación

1. Un asistente no visualiza ni accede por URL o API al módulo.
2. Jefatura puede listar, crear, editar, activar, desactivar y cambiar contraseña de cuentas autorizadas.
3. Los cambios de rol invalidan la sesión afectada y se aplican al volver a iniciar sesión.
4. Una cuenta desactivada no puede iniciar sesión ni recibir tareas.
5. La auto-desactivación cierra la sesión y conserva todos los registros históricos.
6. Ninguna contraseña aparece en listados, auditoría, respuestas API ni registros.
7. Las consultas para asignar responsables continúan funcionando sin depender de la pantalla administrativa.
8. El Jefe de Personal recibe un 403 al intentar desactivar una cuenta con rol Jefe de Área; el Jefe de Área sí puede hacerlo.
