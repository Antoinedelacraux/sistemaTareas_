---
name: verificar-calidad
description: "Ejecuta checklist QA, permisos, regresión y consistencia antes de cerrar un avance."
---

# Workflow: verificar calidad
1. Identificar historias, cambios y pruebas del repositorio.
2. Verificar lógica, lint, tipos, tests unitarios/de integración disponibles y pruebas de autorización.
3. Comprobar tareas personales/oficiales, asignación a sí mismo, confirmación de cierre, revisión/aprobación y dependencias paralelas si aplica.
4. Examinar `git diff --check`, documentación y riesgos de seguridad.
5. Reportar PASS/FAIL/NO EJECUTADA, comando y causa. No crear evidencia ficticia.
6. Si hay problemas, devolver una lista de correcciones priorizadas y detener cierre.
