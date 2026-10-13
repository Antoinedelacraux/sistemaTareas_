---
name: implementar-funcionalidad
description: "Implementa una historia pequeña, prueba, documenta e integra sin perder contexto."
---

# Workflow: implementar funcionalidad
1. Ejecutar diagnóstico inicial de cambios existentes y leer RF/RN de la historia.
2. Verificar que diseño y arquitectura necesarios estén aceptados; si no, preparar propuesta primero.
3. Limitar cambios a la historia e integrar de forma incremental con pruebas automatizadas pertinentes.
4. Pedir revisión especializada para reglas de negocio, permisos, concurrencia o accesibilidad según el cambio.
5. Ejecutar pruebas, build/lint si están configurados. Registrar resultados exactos y bloqueos.
6. Actualizar documentación y terminar usando `/cerrar-avance` para un checkpoint local de Git cuando sea verificable.
7. Nunca hacer push remoto automáticamente.
