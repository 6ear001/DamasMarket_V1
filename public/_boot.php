<?php
// Gemeinsamer Einstieg. Liegt der Ordner "app" woanders (z. B. außerhalb von public_html),
// hier den Pfad anpassen, z. B.: define('APP_DIR', '/home/benutzer/damas-app');
define('PUBLIC_DIR', __DIR__);
if (!defined('APP_DIR')) define('APP_DIR', dirname(__DIR__) . '/app');
require APP_DIR . '/bootstrap.php';
