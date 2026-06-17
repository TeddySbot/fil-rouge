<?php

/**
 * Point d'entrée de la couche d'accès aux données.
 *
 * Désormais branché sur la couche POO (dossier src/) :
 *   - charge l'autoloader PSR-4 (namespace App\)
 *   - expose $pdo via le Singleton App\Database\Connection
 *
 * $pdo reste disponible pour TOUTES les pages existantes (compatibilité).
 * Les nouvelles pages peuvent en plus instancier des Repositories :
 *   $repo = new App\Repository\PropertyRepository($pdo);
 */

require_once __DIR__ . '/../src/autoload.php';

use App\Database\Connection;

$pdo = Connection::getInstance();

/*
 * Compatibilité héritée : du code ancien pouvait référencer `new Database()`.
 * On conserve une classe légère qui délègue au Singleton afin de ne rien casser.
 */
if (!class_exists('Database')) {
    class Database
    {
        public function connect(): PDO
        {
            return Connection::getInstance();
        }

        public function getPDO(): PDO
        {
            return Connection::getInstance();
        }
    }
}

$db = new Database();
