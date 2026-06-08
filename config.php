<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hitster_musicos');

$scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
define('BASE_URL', str_replace(' ', '%20', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/Practicas/Web Musicos'));
