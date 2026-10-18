<?php

namespace App\Livewire\Dashboard;

use App\Actions\Links\ExtractProductMetadataAction;
use App\DTOs\LinkData;
use App\Enums\LinkStatus;
use App\Services\LinkService;
use App\Support\AffiliateUrlPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Bulk URL import modal: paste many product links, preview extracted metadata, save all.
 * Opened via the "open-bulk-import" event; emits "links-imported" after saving.
 */
class BulkImport extends Component
{
    protected const MAX_URLS = 25;

    public bool $show = false;
    public string $urlsInput = '';
    public array $items = [];
    public ?string $error = null;

    #[On('open-bulk-import')]
    public function open(): void
    {
        $this->reset(['urlsInput', 'items', 'error']);
        $this->show = true;
    }

    public function close(): void
    {
        $this->reset(['show', 'urlsInput', 'items', 'error']);
    }

    /**
     * Parse the pasted URLs and extract metadata for each (fetched concurrently).
     */
    public function process(ExtractProductMetadataAction $extractor, AffiliateUrlPolicy $urlPolicy): void
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($this->urlsInput));
        $validUrls = [];
        $rejectedCount = 0;

        foreach ($lines as $line) {
            $candidate = trim($line);
            if (empty($candidate)) {
                continue;
            }

            if ($urlPolicy->isAllowed($candidate)) {
                $validUrls[] = $candidate;
            } else {
                $rejectedCount++;
            }
        }

        if (empty($validUrls)) {
            $this->error = 'Tidak ada link produk yang dikenali. Tempel satu link per baris dari ' . implode(', ', app(AffiliateUrlPolicy::class)->platformLabels()) . '.';
            return;
        }

        $validUrls = array_slice(array_values(array_unique($validUrls)), 0, self::MAX_URLS);

        $this->error = null;
        $this->items = [];

        try {
            foreach ($extractor->executeMany($validUrls) as $meta) {
                $this->items[] = [
                    'title' => $meta['title'],
                    'original_url' => $meta['original_url'],
                    'slug' => $meta['suggested_slug'],
                    'og_image_url' => $meta['og_image_url'],
                    'category' => $meta['category'] ?? 'Produk Pilihan',
                    'price' => $meta['price'],
                    'discount_tag' => $meta['discount_tag'],
                    'description' => $meta['description'],
                    'product_ref' => $meta['product_ref'] ?? null,
                    'sub_id' => $meta['sub_id'] ?? null,
                ];
            }

            if ($rejectedCount > 0) {
                $this->error = "{$rejectedCount} baris dilewati karena bukan link dari toko yang didukung.";
            }
        } catch (\Exception $e) {
            $this->error = 'Detail produk tidak bisa diambil. Periksa link-nya lalu coba lagi.';
        }
    }

    public function removeItem(int $index): void
    {
        if (isset($this->items[$index])) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
        }
    }

    public function save(LinkService $linkService, AffiliateUrlPolicy $urlPolicy): void
    {
        if (empty($this->items)) {
            $this->error = 'Daftar produk masih kosong. Proses URL terlebih dahulu.';
            return;
        }

        $userId = Auth::id();
        $savedCount = 0;
        $skippedCount = 0;

        foreach (array_slice($this->items, 0, self::MAX_URLS) as $item) {
            // Items live in a public Livewire property and can be tampered with client-side, so re-validate
            $validator = Validator::make(is_array($item) ? $item : [], [
                'title' => ['required', 'string', 'max:255'],
                'original_url' => ['required', 'string', 'max:2000'],
                'slug' => ['nullable', 'string', 'alpha_dash', 'max:100'],
                'og_image_url' => ['nullable', 'string', 'url', 'max:1000'],
                'price' => ['nullable', 'numeric', 'min:0'],
                'discount_tag' => ['nullable', 'string', 'max:50'],
                'category' => ['nullable', 'string', 'max:100'],
                'description' => ['nullable', 'string', 'max:1000'],
                'product_ref' => ['nullable', 'string', 'regex:/^\d{1,40}$/'],
                'sub_id' => ['nullable', 'string', 'max:100'],
            ]);

            if ($validator->fails() || ! $urlPolicy->isAllowed($item['original_url'])) {
                $skippedCount++;
                continue;
            }

            $dto = LinkData::fromArray([
                'title' => $item['title'],
                'original_url' => $item['original_url'],
                'slug' => $item['slug'] ?? null,
                'status' => LinkStatus::ACTIVE,
                'description' => $item['description'] ?? null,
                'og_title' => $item['title'],
                'og_description' => $item['description'] ?? null,
                'og_image_url' => $item['og_image_url'] ?? null,
                'price' => $item['price'] ?? null,
                'discount_tag' => $item['discount_tag'] ?? null,
                'category' => $item['category'] ?? 'Produk Pilihan',
                'is_in_catalog' => true,
                'is_featured' => false,
                'ai_captions' => null,
                'product_ref' => $item['product_ref'] ?? null,
                'sub_id' => $item['sub_id'] ?? null,
            ], $userId);

            try {
                $linkService->createLink($dto);
            } catch (\InvalidArgumentException $e) {
                // Suggested slug already taken: fall back to an auto-generated slug
                $linkService->createLink(LinkData::fromArray(array_merge($dto->toArray(), ['slug' => null]), $userId));
            }
            $savedCount++;
        }

        $this->close();
        $this->dispatch('links-imported');

        $message = "{$savedCount} link disimpan.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} dilewati karena datanya tidak valid.";
        }
        $this->dispatch('toast', message: $message);
    }

    public function render()
    {
        return view('livewire.dashboard.bulk-import');
    }
}
