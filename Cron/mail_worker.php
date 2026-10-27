<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../Modelo/Database.php';
require_once __DIR__.'/../Modelo/MailQueue.php';
echo json_encode(MailQueue::run(Database::getInstance()->pdo())),"\n";
