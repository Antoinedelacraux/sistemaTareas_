<?php

declare(strict_types=1);

/**
 * SISTEMA DE TAREAS — TAL S.A. (ÁREA DE PLANEAMIENTO)
 * Ensamblador Modular de Vistas Frontend (Separación de Responsabilidades)
 */

$partialsDir = __DIR__ . '/partials';

require $partialsDir . '/head.html';
require $partialsDir . '/header.html';
require $partialsDir . '/workspace_bar.html';
require $partialsDir . '/login.html';
require $partialsDir . '/kanban.html';
require $partialsDir . '/approvals.html';
require $partialsDir . '/history.html';
require $partialsDir . '/modals.html';
require $partialsDir . '/footer.html';
