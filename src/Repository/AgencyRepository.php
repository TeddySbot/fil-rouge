<?php

namespace App\Repository;

use App\Entity\Agency;
use PDO;

/**
 * Couche d'accès aux données des agences (tables `agencies` / `agency_agents`).
 * PDO injecté (Dependency Inversion), requêtes préparées uniquement.
 */
final class AgencyRepository implements RepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?Agency
    {
        $stmt = $this->pdo->prepare('SELECT * FROM agencies WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Agency::fromArray($row) : null;
    }

    /**
     * Agence à laquelle appartient un agent (ou null).
     * Remplace l'ancienne requête à interpolation directe d'agent/index.php.
     */
    public function findByAgent(int $agentId): ?Agency
    {
        $sql = 'SELECT a.*
                FROM agency_agents aa
                JOIN agencies a ON a.id = aa.agency_id
                WHERE aa.agent_id = ?
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$agentId]);
        $row = $stmt->fetch();

        return $row ? Agency::fromArray($row) : null;
    }
}
