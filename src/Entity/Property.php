<?php

namespace App\Entity;

/**
 * Entité du domaine : un bien immobilier.
 *
 * Modélise une ligne de la table `properties` sous forme d'objet (POO).
 * Contient quelques règles métier simples (libellés, formatage, prix au m²)
 * pour éviter de dupliquer cette logique dans les pages (DRY).
 */
final class Property
{
    public const TYPE_LABELS = [
        'house'      => 'Maison',
        'apartment'  => 'Appartement',
        'land'       => 'Terrain',
        'commercial' => 'Local commercial',
    ];

    public const STATUS_LABELS = [
        'available' => 'Disponible',
        'sold'      => 'Vendu',
        'rented'    => 'Loué',
    ];

    public function __construct(
        public ?int $id = null,
        public string $title = '',
        public ?string $description = null,
        public float $price = 0.0,
        public int $surface = 0,
        public string $city = '',
        public ?string $address = null,
        public ?string $postalCode = null,
        public string $propertyType = 'house',
        public ?int $rooms = null,
        public ?int $bathrooms = null,
        public ?int $agentId = null,
        public ?int $agencyId = null,
        public string $status = 'available',
        public bool $isFeatured = false,
        public int $viewsCount = 0,
        public ?string $createdAt = null,
        public ?string $mainImage = null,
        public ?string $agentName = null,
    ) {}

    /** Construit une entité depuis une ligne SQL associative. */
    public static function fromArray(array $row): self
    {
        return new self(
            id:           isset($row['id']) ? (int) $row['id'] : null,
            title:        (string) ($row['title'] ?? ''),
            description:  $row['description'] ?? null,
            price:        (float) ($row['price'] ?? 0),
            surface:      (int) ($row['surface'] ?? 0),
            city:         (string) ($row['city'] ?? ''),
            address:      $row['address'] ?? null,
            postalCode:   $row['postal_code'] ?? null,
            propertyType: (string) ($row['property_type'] ?? 'house'),
            rooms:        isset($row['rooms']) ? (int) $row['rooms'] : null,
            bathrooms:    isset($row['bathrooms']) ? (int) $row['bathrooms'] : null,
            agentId:      isset($row['agent_id']) ? (int) $row['agent_id'] : null,
            agencyId:     isset($row['agency_id']) ? (int) $row['agency_id'] : null,
            status:       (string) ($row['status'] ?? 'available'),
            isFeatured:   (bool) ($row['is_featured'] ?? false),
            viewsCount:   (int) ($row['views_count'] ?? 0),
            createdAt:    $row['created_at'] ?? null,
            mainImage:    $row['main_image'] ?? null,
            agentName:    $row['agent_name'] ?? null,
        );
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->propertyType] ?? $this->propertyType;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Prix formaté « 250 000 € ». */
    public function formattedPrice(): string
    {
        return number_format($this->price, 0, ',', ' ') . ' €';
    }

    /** Prix au m² (0 si surface inconnue). */
    public function pricePerSquareMeter(): float
    {
        return $this->surface > 0 ? round($this->price / $this->surface, 2) : 0.0;
    }
}
