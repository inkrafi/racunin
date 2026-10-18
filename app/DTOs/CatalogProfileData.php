<?php

namespace App\DTOs;

readonly class CatalogProfileData
{
    public function __construct(
        public int $userId,
        public string $username,
        public string $displayName,
        public ?string $bio = null,
        public ?string $avatarUrl = null,
        public string $theme = 'orange',
        public array $socialLinks = [],
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data, int $userId): self
    {
        return new self(
            userId: $userId,
            username: strtolower(trim($data['username'] ?? '')),
            displayName: trim($data['display_name'] ?? ''),
            bio: isset($data['bio']) ? trim($data['bio']) : null,
            avatarUrl: isset($data['avatar_url']) && trim($data['avatar_url']) !== '' ? trim($data['avatar_url']) : null,
            theme: in_array($data['theme'] ?? '', ['orange', 'indigo', 'emerald', 'slate'], true) ? $data['theme'] : 'orange',
            socialLinks: is_array($data['social_links'] ?? null) ? array_filter($data['social_links']) : [],
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'username' => $this->username,
            'display_name' => $this->displayName,
            'bio' => $this->bio,
            'avatar_url' => $this->avatarUrl,
            'theme' => $this->theme,
            'social_links' => $this->socialLinks,
            'is_active' => $this->isActive,
        ];
    }
}
