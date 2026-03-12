<?php
/**
 * Ymmo - Real Estate Platform
 * Database Configuration and Connection
 */

class Database
{
    private $host = 'localhost';
    private $db_name = 'ymmo_db';
    private $db_user = 'root';
    private $db_pass = '';
    private $charset = 'utf8mb4';
    private $pdo;

    /**
     * Connect to database using PDO
     */
    public function connect()
    {
        $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
        
        try {
            $this->pdo = new PDO($dsn, $this->db_user, $this->db_pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->pdo;
        } catch (PDOException $e) {
            // Check if this is an API request
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur de base de données: ' . $e->getMessage()
                ]);
                exit;
            }
            die("Erreur de connexion: " . $e->getMessage());
        }
    }

    /**
     * Get PDO instance
     */
    public function getPDO()
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }
}

// Create database instance
$db = new Database();
$pdo = $db->connect();
?>
