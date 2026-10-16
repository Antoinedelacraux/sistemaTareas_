# Sistema de Gestión y Seguimiento de Tareas (TAL S.A.) — Guía rápida

Aplicación web interna del área de Planeamiento, construida con PHP 8.2+, MySQL/MariaDB y frontend Vanilla JS/CSS. El paquete de producción no incluye cuentas, tareas ni contraseñas demo.

## Desarrollo local

Abre [http://127.0.0.1:8000](http://127.0.0.1:8000). La base SQLite local puede contener datos de desarrollo históricos; no uses esas cuentas como procedimiento de producción.

Para una instalación nueva, sigue [`docs/DEPLOYMENT_AND_PILOT_GUIDE.md`](docs/DEPLOYMENT_AND_PILOT_GUIDE.md). El primer usuario se crea una sola vez desde `/instalar.php`, con `INSTALLER_ENABLED=true`, un `INSTALL_TOKEN` de al menos 32 caracteres y una contraseña de al menos 12 caracteres. Después de crear la cuenta, desactiva inmediatamente el instalador.

## Alcance operativo vigente

- Tablero único de Área con columnas configurables y movimiento directo de tareas.
- Creación, asignación, edición, checklist, comentarios y evidencias.
- Aprobaciones, notificaciones, vencimientos y cierre con archivo después de 24 horas.
- Historial de tareas archivadas y auditoría para Jefatura.
- Gestión administrativa de usuarios para Jefatura.
- Las referencias históricas a Dashboard y Vista Personal ya no representan módulos operativos activos.

## Verificaciones

Lint PHP de la aplicación:

```powershell
$phpFiles = @(Get-ChildItem app,public_html,tests -Filter *.php -Recurse -File) + @(Get-Item server.php)
foreach ($file in $phpFiles) { php -l $file.FullName }
```

Las pruebas aisladas disponibles se encuentran en `tests/`. La suite integral debe interpretarse junto con el alcance vigente y no debe incluir módulos retirados.

## Documentación principal

- `docs/PROJECT_CONTEXT.md`: contexto y alcance.
- `docs/REQUIREMENTS.md`: requisitos y reglas vigentes.
- `docs/DECISIONS.md`: decisiones confirmadas y decisiones superadas.
- `docs/ROADMAP.md`: fases y tareas.
- `docs/PROGRESS.md`: bitácora verificable.
- `docs/SESSION_HANDOFF.md`: estado para la siguiente sesión.
- `docs/DEPLOYMENT_AND_PILOT_GUIDE.md`: despliegue seguro en DonWeb Ferozo.
