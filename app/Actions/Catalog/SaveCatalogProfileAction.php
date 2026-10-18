<?php

namespace App\Actions\Catalog;

use App\DTOs\CatalogProfileData;
use App\Models\Catalog;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use Illuminate\Support\Str;

class SaveCatalogProfileAction
{
    protected const RESERVED_USERNAMES = [
        'login', 'register', 'logout', 'dashboard', 'admin', 'api', 'up', 'build',
        'storage', 'catalog', 'bio', 'settings', 'analytics', 'links', 'user', 'profile', 'home'
    ];

    public function __construct(
        protected CatalogRepositoryInterface $catalogRepository
    ) {}

    public function execute(CatalogProfileData $data): Catalog
    {
        $cleanUsername = Str::slug($data->username);

        if (empty($cleanUsername)) {
            throw new \InvalidArgumentException("Username katalog tidak boleh kosong.");
        }

        if (in_array(strtolower($cleanUsername), self::RESERVED_USERNAMES, true)) {
            throw new \InvalidArgumentException("Username '{$cleanUsername}' tidak dapat digunakan karena merupakan kata kunci sistem.");
        }

        $existing = $this->catalogRepository->findByUserId($data->userId);
        $excludeId = $existing?->id;

        if ($this->catalogRepository->usernameExists($cleanUsername, $excludeId)) {
            throw new \InvalidArgumentException("Username '@{$cleanUsername}' sudah dipakai oleh affiliate lain.");
        }

        $payload = $data->toArray();
        $payload['username'] = $cleanUsername;

        return $this->catalogRepository->createOrUpdateForUser($data->userId, $payload);
    }
}
