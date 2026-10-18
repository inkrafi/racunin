<?php

namespace App\Livewire\Catalog;

use App\Services\CatalogService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class PublicCatalog extends Component
{
    public string $username = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = 'all';

    /** Products shown so far; grows with "load more" instead of loading the whole catalog. */
    public int $perPage = self::PAGE_SIZE;

    protected const PAGE_SIZE = 24;

    public function mount(string $username): void
    {
        $this->username = ltrim($username, '@');
    }

    public function selectCategory(string $category): void
    {
        $this->category = $category;
        $this->perPage = self::PAGE_SIZE;
    }

    public function updatedSearch(): void
    {
        $this->perPage = self::PAGE_SIZE;
    }

    public function loadMore(): void
    {
        $this->perPage += self::PAGE_SIZE;
    }

    public function render(CatalogService $catalogService)
    {
        $catalog = $catalogService->getPublicCatalog($this->username);

        if (! $catalog) {
            abort(404, 'Katalog affiliate tidak ditemukan atau sedang dinonaktifkan.');
        }

        // Fetch one extra row to know whether a "load more" button is needed
        $products = $catalogService->getCatalogLinks($catalog->user_id, $this->search, $this->category, request()->ip(), $this->perPage + 1);
        $hasMore = $products->count() > $this->perPage;
        $products = $products->take($this->perPage);
        $categories = $catalogService->getCategories($catalog->user_id);

        return view('livewire.catalog.public-catalog', [
            'catalog' => $catalog,
            'products' => $products,
            'hasMore' => $hasMore,
            'totalProducts' => $catalogService->countCatalogLinks($catalog->user_id),
            'categories' => $categories,
            'theme' => $catalog->theme_config,
        ])->title($catalog->display_name . ' — Katalog Racunin');
    }
}
