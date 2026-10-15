# Sincronización operativa en tiempo real

## Alcance aprobado

Implementa RF-19 y RN-16 para las sesiones de usuarios autorizados. Cada mutación compartida publica un evento después de persistir la operación: creación, edición, reasignación, transición, cierre, solicitud o dictamen de aprobación, checklist, comentario, evidencia, dependencia, columna y cuenta administrativa.

## Diseño compatible con DonWeb Ferozo

El Web Hosting tradicional no garantiza Redis, WebSockets ni procesos residentes. El sistema usa una tabla de eventos y otra de destinatarios, ambas en la misma base transaccional del sistema. El cliente mantiene una única petición autenticada a `GET /api/sincronizacion/eventos?after={id}&wait=20`; el servidor responde de inmediato si hay cambios o al vencer el periodo. La siguiente petición se inicia automáticamente.

El transporte entrega únicamente metadatos: identificador, tipo, entidad, tarea relacionada y fecha. El cliente vuelve a solicitar el recurso autorizado al que ya tiene acceso; no se envían títulos, comentarios, adjuntos ni datos de otros usuarios dentro del evento.

## Contrato

Respuesta exitosa:

```json
{
  "eventos": [
    {"id": 41, "tipo": "TASK_UPDATED", "entidad_tipo": "TAREA", "entidad_id": 18, "tarea_id": 18, "created_at": "2026-10-15 12:00:00"}
  ],
  "cursor": 41,
  "espera_agotada": false
}
```

El cursor es exclusivo por destinatario; no revela eventos de otros usuarios. Los tipos de evento se limitan a un catálogo fijo del backend. Una sesión inválida recibe la misma respuesta de autenticación que cualquier API protegida.

## Criterios de aceptación

1. Dos sesiones autorizadas reflejan un cambio compartido sin recargar la página, como máximo tras una espera de 20 segundos.
2. Un asistente no puede consultar ni inferir eventos emitidos para otra tarea o cuenta.
3. Comentarios y evidencias actualizan el detalle abierto y la campana se refresca cuando corresponde.
4. Si la red se corta, el cliente reintenta con espera incremental y retoma desde el último cursor.
5. La configuración se despliega en DonWeb solo con PHP 8.2+, MySQL/MariaDB y HTTPS; no depende de servicios externos.

## Fases implementables

1. Persistencia y contrato autenticado.
2. Publicación desde todas las mutaciones compartidas.
3. Cliente SPA, reconexión y actualización selectiva.
4. Seguridad, pruebas y guía de despliegue/operación Ferozo.
