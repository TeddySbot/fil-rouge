<?php

namespace App\Entity;

/**
 * Entité du domaine : une agence immobilière.
 * Modélise une ligne de la table `agencies`.
 */
final class Agency
{
    public function __construct(
        public ?int $id = null,
        public string $name = '',
        public string $city = '',
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $website = null,
        public ?string $logo = null,
        public ?string $description = null,
        public bool $isActive = true,
        public ?string $createdAt = null,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:          isset($row['id']) ? (int) $row['id'] : null,
            name:        (string) ($row['name'] ?? ''),
            city:        (string) ($row['city'] ?? ''),
            address:     $row['address'] ?? null,
            phone:       $row['phone'] ?? null,
            email:       $row['email'] ?? null,
            website:     $row['website'] ?? null,
            logo:        $row['logo'] ?? null,
            description: $row['description'] ?? null,
            isActive:    (bool) ($row['is_active'] ?? true),
            createdAt:   $row['created_at'] ?? null,
        );
    }
}
