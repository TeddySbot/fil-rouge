<?php

namespace App\Repository;

use App\Entity\Property;
use PDO;

/**
 * Couche d'accès aux données des biens (table `properties`).
 *
 * - Reçoit le PDO par injection de dépendance (SOLID : Dependency Inversion).
 * - Centralise TOUTES les requêtes SQL liées aux biens (DRY) : plus aucune
 *   page n'écrit de SQL « properties » à la main.
 * - Utilise exclusivement des requêtes préparées (sécurité).
 */
final class PropertyRepository implements RepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    /** SELECT enrichi (image principale + nom de l'agent). */
    private function baseSelect(): string
    {
        return "SELECT p.*,
                       u.name AS agent_name,
                       (SELECT image_path FROM property_images
                        WHERE property_id = p.id AND is_main = 1 LIMIT 1) AS main_image
                FROM properties p
                LEFT JOIN users u ON u.id = p.agent_id";
    }

    public function find(int $id): ?Property
    {
        $stmt = $this->pdo->prepare($this->baseSelect() . " WHERE p.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? Property::fromArray($row) : null;
    }

    /**
     * Recherche filtrée. Toutes les valeurs passent par des paramètres liés.
     *
     * @param array{type?:string,status?:string,city?:string,q?:string} $filters
     * @return Property[]
     */
    public function search(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'p.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $where[]  = 'p.property_type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['city'])) {
            $where[]  = 'p.city LIKE ?';
            $params[] = '%' . $filters['city'] . '%';
        }
        if (!empty($filters['q'])) {
            $where[]  = '(p.title LIKE ? OR p.city LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }

        $sql = $this->baseSelect()
             . ' WHERE ' . implode(' AND ', $where)
             . ' ORDER BY p.is_featured DESC, p.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(
            static fn (array $row): Property => Property::fromArray($row),
            $stmt->fetchAll()
        );
    }

    /** Compte les biens d'une agence, éventuellement filtré par statut. */
    public function countByAgency(int $agencyId, ?string $status = null): int
    {
        $sql    = 'SELECT COUNT(*) FROM properties WHERE agency_id = ?';
        $params = [$agencyId];

        if ($status !== null) {
            $sql     .= ' AND status = ?';
            $params[] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Top des biens les plus consultés d'une agence.
     * @return Property[]
     */
    public function topViewedByAgency(int $agencyId, int $limit = 5): array
    {
        $sql = $this->baseSelect()
             . ' WHERE p.agency_id = ? ORDER BY p.views_count DESC LIMIT ?';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $agencyId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            static fn (array $row): Property => Property::fromArray($row),
            $stmt->fetchAll()
        );
    }
}
