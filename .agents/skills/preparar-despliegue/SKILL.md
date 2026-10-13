---
name: preparar-despliegue
description: "Revisa requisitos de despliegue remoto, seguridad, backup y rollback sin publicar automáticamente."
---

# Workflow: preparar despliegue
1. Verificar aprobación explícita del usuario antes de publicar servicios o pagar recursos.
2. Revisar configuración de ambiente, HTTPS, dominios, sesión, límites, base de datos sin exposición pública, secretos y backups.
3. Comprobar scripts de migración con plan de backup/rollback y pruebas en staging.
4. Verificar monitoreo, logs sin datos sensibles y procedimiento de recuperación.
5. Preparar checklist, manual operativo y riesgos de costos; sin despliegue ni push hasta permiso.
