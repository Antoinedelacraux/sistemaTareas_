# Traspaso entre sesiones

## Actualización 2026-10-16 — Limpieza técnica y documental

- Se está alineando el repositorio con el alcance vigente: tablero único de Área, Historial de Jefatura e instalación limpia.
- Se retiraron las instrucciones de acceso con cuentas demo de `LEEME_PRIMERO.md`.
- RF-11 Dashboard quedó registrado como requisito retirado por D-017.
- D-018 quedó marcado como superado por D-024.
- Los registros históricos `tipo_tarea = PERSONAL` se conservan en la base, pero no se crean ni se exponen por los flujos operativos actuales.
- Los módulos vacíos/no referenciados del Dashboard y las pruebas que dependían de ellos ya fueron retirados del árbol operativo.
- `.tmp-cloudflare/` y respaldos locales están excluidos por `.gitignore`; no deben subirse al repositorio ni al paquete de producción.

## Estado verificable

- Rama: `main`, sincronizada con `origin/main` antes de este avance.
- Último commit previo: `60ce24f feat(deploy): prepare clean production installation`.
- Plataforma local: PHP 8.4 + SQLite.
- Entrada vigente: `public_html/index.php` ensamblando partials.
- Producción: preparada técnicamente, todavía no desplegada en Ferozo.
- Limpieza CLEANUP-01: verificada con lint PHP `49/49`, suite integral `10/10`, QA piloto `43/43` y Test Runner HTTP `10/10`.

## Pendientes después de esta limpieza

1. Ejecutar lint y pruebas actualizadas.
2. Confirmar que no queden referencias operativas a credenciales demo, Dashboard ni Vista Personal.
3. Validar visualmente el flujo vigente en escritorio y móvil.
4. Repetir Lighthouse con navegador real.
5. Desplegar mediante `docs/DEPLOYMENT_AND_PILOT_GUIDE.md` y realizar piloto controlado.

## Archivos de referencia

- `docs/PROJECT_CONTEXT.md`
- `docs/REQUIREMENTS.md`
- `docs/DECISIONS.md`
- `docs/ROADMAP.md`
- `docs/PROGRESS.md`
- `docs/CHANGELOG.md`
