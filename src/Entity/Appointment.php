<?php

namespace App\Entity;

/**
 * Entité du domaine : un rendez-vous de visite.
 * Modélise une ligne de la table `appointments`.
 */
final class Appointment
{
    public const STATUS_LABELS = [
        'pending'   => 'En attente',
        'confirmed' => 'Confirmé',
        'cancelled' => 'Annulé',
        'done'      => 'Terminé',
    ];

    public function __construct(
        public ?int $id = null,
        public ?int $propertyId = null,
        public int $clientId = 0,
        public int $agentId = 0,
        public string $scheduledAt = '',
        public int $durationMinutes = 60,
        public string $status = 'pending',
        public ?string $note = null,
        public ?string $createdAt = null,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id:              isset($row['id']) ? (int) $row['id'] : null,
            propertyId:      isset($row['property_id']) ? (int) $row['property_id'] : null,
            clientId:        (int) ($row['client_id'] ?? 0),
            agentId:         (int) ($row['agent_id'] ?? 0),
            scheduledAt:     (string) ($row['scheduled_at'] ?? ''),
            durationMinutes: (int) ($row['duration_minutes'] ?? 60),
            status:          (string) ($row['status'] ?? 'pending'),
            note:            $row['note'] ?? null,
            createdAt:       $row['created_at'] ?? null,
        );
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
