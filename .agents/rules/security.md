---
trigger: always_on
description: "Restricciones de autorización y seguridad del sistema de Planeamiento."
---
# Seguridad obligatoria
- Autorizar cada operación en backend y filtrar consultas por alcance; ocultar botones nunca es control de seguridad.
- Asistentes: solo tareas propias y del área en que sean participantes autorizados. No pueden asignar a compañeros ni cambiar responsable a otros. Jefes autorizados asignan a otros y supervisan tareas oficiales.
- Tareas personales privadas por defecto (propuesta a validar); tareas del área visibles a jefes según ámbito.
- Validar entradas, evitar IDOR/BOLA, tratar adjuntos como no confiables, limitar tamaño y tipo y evitar ejecución de archivos.
- Contraseñas seguras, HTTPS, sesiones protegidas, protección contra fuerza bruta y MFA para roles privilegiados cuando se implemente.
- No almacenar secretos en código, Git, ejemplos ni documentación. Emplear variables de entorno y `env.example` sin credenciales.
- Crear pruebas negativas de autorización y preservar auditoría de cambios relevantes.
