<?php

namespace App\Entity;

/**
 * Entité du domaine : un utilisateur (client, attente, agent, admin).
 * Modélise une ligne de la table `users`.
 */
final class User
{
    public const ROLE_CLIENT  = 'client';
    public const ROLE_PENDING = 'attente';
    public const ROLE_AGENT   = 'agent';
    public const ROLE_ADMIN   = 'admin';

    public function __construct(
        public ?int $id = null,
        public string $name = '',
        public string $email = '',
        public string $role = self::ROLE_CLIENT,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $profileImage = null,
        public bool $isActive = true,
        public ?string $createdAt = null,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:           isset($row['id']) ? (int) $row['id'] : null,
            name:         (string) ($row['name'] ?? ''),
            email:        (string) ($row['email'] ?? ''),
            role:         (string) ($row['role'] ?? self::ROLE_CLIENT),
            phone:        $row['phone'] ?? null,
            address:      $row['address'] ?? null,
            city:         $row['city'] ?? null,
            profileImage: $row['profile_image'] ?? null,
            isActive:     (bool) ($row['is_active'] ?? true),
            createdAt:    $row['created_at'] ?? null,
        );
    }

    public function isAgent(): bool
    {
        return $this->role === self::ROLE_AGENT;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
