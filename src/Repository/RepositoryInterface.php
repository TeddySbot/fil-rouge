<?php

namespace App\Repository;

/**
 * Contrat commun à tous les repositories (abstraction — SOLID : D & I).
 *
 * Les pages dépendent de cette abstraction et non d'une implémentation
 * concrète : on pourrait remplacer la source de données sans toucher
 * au code appelant.
 */
interface RepositoryInterface
{
    /** Récupère une entité par son identifiant, ou null si absente. */
    public function find(int $id): ?object;
}
