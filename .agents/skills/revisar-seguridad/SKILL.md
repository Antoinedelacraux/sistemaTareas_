---
name: revisar-seguridad
description: "Audita riesgos de autorización y manejo de datos antes de entregar funcionalidades sensibles."
---

# Workflow: revisar seguridad
1. Revisar rutas/endpoints y uso de identidad para identificar posibles IDOR/BOLA.
2. Probar denegación al asistente para consultar/editar/asignar tareas de otros y para modificar aprobaciones.
3. Revisar validación, sesiones, adjuntos, secretos en diffs y logs, SQL injection/XSS/CSRF según stack.
4. Verificar trazabilidad y consistencia de automatizaciones.
5. Documentar hallazgos con criticidad, reproducción segura y corrección sugerida; ejecutar pruebas pertinentes.
6. Detener promoción a producción ante vulnerabilidades críticas no resueltas.
