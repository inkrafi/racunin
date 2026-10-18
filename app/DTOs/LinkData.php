<?php

namespace App\DTOs;

use App\Enums\LinkStatus;

readonly class LinkData
{
    public function __construct(
        public int $userId,
        public string $title,
        public string $originalUrl,
        public ?string $slug = null,
        public LinkStatus $status = LinkStatus::ACTIVE,
        public ?string $description = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImageUrl = null,
        public ?float $price = null,
        public ?string $discountTag = null,
        public ?string $category = null,
        public bool $isInCatalog = true,
        public bool $isFeatured = false,
        public int $sortOrder = 0,
        public ?array $aiCaptions = null,
        public ?string $subId = null,
        public ?string $productRef = null,
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            title: trim($data['title']),
            originalUrl: trim($data['original_url']),
            slug: isset($data['slug']) && trim($data['slug']) !== '' ? trim($data['slug']) : null,
            status: isset($data['status'])
                ? ($data['status'] instanceof LinkStatus ? $data['status'] : LinkStatus::from($data['status']))
                : LinkStatus::ACTIVE,
            description: isset($data['description']) && trim($data['description']) !== '' ? trim($data['description']) : null,
            ogTitle: isset($data['og_title']) && trim($data['og_title']) !== '' ? trim($data['og_title']) : null,
            ogDescription: isset($data['og_description']) && trim($data['og_description']) !== '' ? trim($data['og_description']) : null,
            ogImageUrl: isset($data['og_image_url']) && trim($data['og_image_url']) !== '' ? trim($data['og_image_url']) : null,
            price: isset($data['price']) && is_numeric($data['price']) ? (float) $data['price'] : null,
            discountTag: isset($data['discount_tag']) && trim($data['discount_tag']) !== '' ? trim($data['discount_tag']) : null,
            category: isset($data['category']) && trim($data['category']) !== '' ? trim($data['category']) : null,
            isInCatalog: isset($data['is_in_catalog']) ? (bool) $data['is_in_catalog'] : true,
            isFeatured: isset($data['is_featured']) ? (bool) $data['is_featured'] : false,
            sortOrder: isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            aiCaptions: $data['ai_captions'] ?? null,
            subId: isset($data['sub_id']) && trim((string) $data['sub_id']) !== '' ? trim((string) $data['sub_id']) : null,
            productRef: isset($data['product_ref']) && trim((string) $data['product_ref']) !== '' ? trim((string) $data['product_ref']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'title' => $this->title,
            'original_url' => $this->originalUrl,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'description' => $this->description,
            'og_title' => $this->ogTitle,
            'og_description' => $this->ogDescription,
            'og_image_url' => $this->ogImageUrl,
            'price' => $this->price,
            'discount_tag' => $this->discountTag,
            'category' => $this->category,
            'is_in_catalog' => $this->isInCatalog,
            'is_featured' => $this->isFeatured,
            'sort_order' => $this->sortOrder,
            'ai_captions' => $this->aiCaptions,
            'sub_id' => $this->subId,
        ] + ($this->productRef !== null ? ['product_ref' => $this->productRef] : []);
    }
}
