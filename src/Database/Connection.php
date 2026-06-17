<?php

namespace App\Database;

use PDO;
use PDOException;

/**
 * Connexion unique à la base de données (pattern Singleton).
 *
 * Responsabilité unique (SOLID-S) : fournir UN objet PDO partagé.
 * Évite d'ouvrir plusieurs connexions par requête HTTP (DRY) et
 * centralise la configuration en un seul endroit (KISS).
 */
final class Connection
{
    private const HOST    = 'localhost';
    private const DB_NAME = 'ymmo_db';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    private const CHARSET = 'utf8mb4';

    private static ?PDO $pdo = null;

    /** Empêche l'instanciation : on passe par getInstance(). */
    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::HOST,
                self::DB_NAME,
                self::CHARSET
            );

            try {
                self::$pdo = new PDO($dsn, self::DB_USER, self::DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Erreur de base de données: ' . $e->getMessage(),
                    ]);
                    exit;
                }
                die('Erreur de connexion: ' . $e->getMessage());
            }
        }

        return self::$pdo;
    }
}
