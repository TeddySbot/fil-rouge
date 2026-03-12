<?php
/**
 * Connection file - Initializes the PDO connection
 */
require_once __DIR__ . '/database.php';

$database = new Database();
$pdo = $database->connect();
