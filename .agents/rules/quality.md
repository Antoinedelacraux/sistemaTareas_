---
trigger: always_on
description: "Arquitectura modular, calidad de implementación y pruebas."
---
# Estándar técnico
- No sobreingeniería ni arquitectura distribuida para cinco usuarios. Elegir tecnologías solo tras registrar la decisión.
- Diseñar por dominios: identidad/permisos, tableros, tareas, procesos/dependencias, aprobaciones, notificaciones, auditoría y reportes.
- Antes del código registrar comportamiento esperado y criterios de aceptación. Mantener validaciones de API y bases de datos.
- Dependencias como grafo acíclico dirigido; soportar tareas paralelas y desbloqueo solo tras cierre efectivo de TODAS las predecesoras.
- Confirmación adicional para tareas sin aprobación; aprobación obligatoria solo si está configurada.
- Prevenir doble ejecución de eventos y movimientos inválidos con operaciones atómicas y pruebas de concurrencia.
- Ejecutar pruebas apropiadas para cada cambio. Los fallos se reportan, no se ocultan.
