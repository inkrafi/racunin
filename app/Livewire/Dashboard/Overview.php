<?php

namespace App\Livewire\Dashboard;

use App\Actions\AI\AutoCategorizeAction;
use App\Actions\AI\BeautifyProductAction;
use App\Actions\AI\GenerateCaptionsAction;
use App\Actions\Links\ExtractProductMetadataAction;
use App\DTOs\LinkData;
use App\Enums\LinkStatus;
use App\Livewire\Concerns\LimitsAiUsage;
use App\Models\Link;
use App\Rules\AllowedAffiliateUrl;
use App\Services\AnalyticsService;
use App\Services\LinkService;
use App\Support\AffiliateUrlPolicy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Link — Racunin')]
class Overview extends Component
{
    use WithPagination, AuthorizesRequests, LimitsAiUsage;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = 'all';

    // Modal state
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $editingLinkId = null;

    // Delete confirmation modal state
    public bool $showDeleteModal = false;
    public ?int $deletingLinkId = null;

    // Quick Auto-Extract state
    public bool $isExtracting = false;
    public ?string $extractError = null;

    // Form inputs (General)
    public string $title = '';
    public string $original_url = '';
    public string $slug = '';
    public string $sub_id = '';
    public string $product_ref = '';
    public string $status = 'active';
    public string $description = '';

    // Form inputs (Branding & OG Metadata)
    public string $og_title = '';
    public string $og_description = '';
    public string $og_image_url = '';

    // Form inputs (Catalog & Showcase)
    public ?float $price = null;
    public string $discount_tag = '';
    public string $category = '';
    public bool $is_in_catalog = true;
    public bool $is_featured = false;

    // AI Smart Tools state
    public bool $aiLoading = false;
    public string $aiLoadingMessage = '';
    public ?string $aiError = null;
    public array $generatedCaptions = [
        'twitter' => '',
        'threads' => '',
    ];

    protected function rules(): array
    {
        $uniqueRule = $this->isEditing && $this->editingLinkId
            ? 'unique:links,slug,' . $this->editingLinkId
            : 'unique:links,slug';

        return [
            'title' => ['required', 'string', 'max:255'],
            'original_url' => ['required', 'string', 'url', 'max:2000', new AllowedAffiliateUrl()],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:100', $uniqueRule],
            'sub_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'product_ref' => ['nullable', 'string', 'regex:/^\d{1,40}$/'],
            'status' => ['required', 'string', 'in:active,inactive,archived'],
            'description' => ['nullable', 'string', 'max:1000'],
            
            // Phase 2 rules
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:1000'],
            'og_image_url' => ['nullable', 'string', 'url', 'max:1000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_tag' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_in_catalog' => ['boolean'],
            'is_featured' => ['boolean'],
        ];
    }

    #[On('links-imported')]
    public function onLinksImported(): void
    {
        $this->resetPage();
    }

    #[On('links-changed')]
    public function onLinksChanged(): void
    {
        // Re-render the table (e.g. caption badge after the Caption Studio saved captions)
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->reset([
            'title', 'original_url', 'slug', 'sub_id', 'product_ref', 'description', 'editingLinkId',
            'og_title', 'og_description', 'og_image_url',
            'price', 'discount_tag', 'category', 'is_featured',
            'aiError', 'generatedCaptions', 'aiLoading'
        ]);
        $this->status = 'active';
        $this->is_in_catalog = true;
        $this->is_featured = false;
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();
        $link = Link::findOrFail($id);
        $this->authorize('update', $link);

        $this->editingLinkId = $link->id;
        $this->title = $link->title;
        $this->original_url = $link->original_url;
        $this->slug = $link->slug;
        $this->sub_id = $link->sub_id ?? '';
        $this->product_ref = $link->product_ref ?? '';
        $this->status = $link->status->value;
        $this->description = $link->description ?? '';
        
        // Phase 2 fields
        $this->og_title = $link->og_title ?? '';
        $this->og_description = $link->og_description ?? '';
        $this->og_image_url = $link->og_image_url ?? '';
        $this->price = $link->price !== null ? (float) $link->price : null;
        $this->discount_tag = $link->discount_tag ?? '';
        $this->category = $link->category ?? '';
        $this->is_in_catalog = (bool) $link->is_in_catalog;
        $this->is_featured = (bool) $link->is_featured;

        // Reset AI state
        $this->aiError = null;
        $this->generatedCaptions = $link->ai_captions ?? [
            'twitter' => '',
            'threads' => '',
        ];

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->isEditing = false;
        $this->resetValidation();
        $this->reset([
            'title', 'original_url', 'slug', 'sub_id', 'product_ref', 'description', 'editingLinkId',
            'og_title', 'og_description', 'og_image_url',
            'price', 'discount_tag', 'category', 'is_featured',
            'aiError', 'generatedCaptions', 'aiLoading',
            'isExtracting', 'extractError'
        ]);
    }

    /**
     * Single Mode: Auto-extract metadata from URL input
     */
    public function autoExtractSingleUrl(ExtractProductMetadataAction $extractor): void
    {
        $url = trim($this->original_url);
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->addError('original_url', 'Ini bukan link. Salin link produk lengkap, diawali https://');
            return;
        }

        if (!app(AffiliateUrlPolicy::class)->isAllowed($url)) {
            $this->addError('original_url', 'Link ini bukan dari toko yang didukung. Racunin menerima link ' . implode(', ', app(AffiliateUrlPolicy::class)->platformLabels()) . '.');
            return;
        }

        $this->isExtracting = true;
        $this->extractError = null;

        try {
            $data = $extractor->execute($url);

            if (!empty($data['title'])) {
                $this->title = $data['title'];
            }
            if (!empty($data['og_title']) && empty($this->og_title)) {
                $this->og_title = $data['og_title'];
            }
            if (!empty($data['og_description'])) {
                $this->og_description = $data['og_description'];
                if (empty($this->description)) {
                    $this->description = $data['og_description'];
                }
            }
            if (!empty($data['og_image_url'])) {
                $this->og_image_url = $data['og_image_url'];
            }
            if (!empty($data['category'])) {
                $this->category = $data['category'];
            }
            if (!empty($data['discount_tag'])) {
                $this->discount_tag = $data['discount_tag'];
            }
            if (!empty($data['suggested_slug']) && empty($this->slug)) {
                $this->slug = $data['suggested_slug'];
            }
            if (!empty($data['price'])) {
                $this->price = $data['price'];
            }
            $this->product_ref = $data['product_ref'] ?? '';
            if (!empty($data['sub_id']) && empty($this->sub_id)) {
                $this->sub_id = $data['sub_id'];
            }

            $this->dispatch('toast', message: 'Detail produk terisi dari ' . (app(AffiliateUrlPolicy::class)->platformFor($url)['label'] ?? 'link') . '.');
        } catch (\Exception $e) {
            $this->extractError = 'Detail produk tidak bisa diambil. Isi judul secara manual, atau coba lagi nanti.';
        } finally {
            $this->isExtracting = false;
        }
    }

    /**
     * AI Tool 1: Beautify Title & Description
     */
    public function aiBeautifyTitle(BeautifyProductAction $action): void
    {
        if ($quotaError = $this->consumeAiQuota()) {
            $this->aiError = $quotaError;
            return;
        }

        if (empty(trim($this->title))) {
            $this->addError('title', 'Masukkan judul produk terlebih dahulu sebelum menggunakan AI Beautify.');
            return;
        }

        $this->aiLoading = true;
        $this->aiLoadingMessage = 'AI sedang mempercantik judul & deskripsi produk...';
        $this->aiError = null;

        try {
            $result = $action->execute($this->title, $this->description, $this->original_url);
            $this->title = $result['title'];
            if (!empty($result['description'])) {
                $this->description = $result['description'];
            }
            if (empty($this->og_title)) {
                $this->og_title = $result['og_title'];
            }
            if (empty($this->og_description)) {
                $this->og_description = $result['og_description'];
            }

            $this->dispatch('toast', message: 'Judul dan deskripsi sudah dirapikan.');
        } catch (\Exception $e) {
            $this->aiError = $this->aiFailureMessage($e, 'Merapikan judul');
        } finally {
            $this->aiLoading = false;
        }
    }

    /**
     * AI Tool 2: Auto Categorize & Tag
     */
    public function aiAutoCategorize(AutoCategorizeAction $action): void
    {
        if ($quotaError = $this->consumeAiQuota()) {
            $this->aiError = $quotaError;
            return;
        }

        $query = !empty($this->title) ? $this->title : $this->original_url;
        if (empty(trim($query))) {
            $this->addError('original_url', 'Masukkan URL atau Judul produk untuk mendeteksi kategori.');
            return;
        }

        $this->aiLoading = true;
        $this->aiLoadingMessage = 'AI sedang mendeteksi kategori, diskon, dan slug...';
        $this->aiError = null;

        try {
            $result = $action->execute($this->title ?: 'Produk pilihan', $this->description, $this->original_url);
            $this->category = $result['category'];
            $this->discount_tag = $result['discount_tag'];
            if (empty($this->slug)) {
                $this->slug = $result['suggested_slug'];
            }
            if ($result['is_featured']) {
                $this->is_featured = true;
            }

            $this->dispatch('toast', message: 'Kategori dan label promo sudah diisi.');
        } catch (\Exception $e) {
            $this->aiError = $this->aiFailureMessage($e, 'Mengisi kategori');
        } finally {
            $this->aiLoading = false;
        }
    }

    /**
     * AI Tool 3: Generate Social Media Captions (TikTok, IG, WhatsApp)
     */
    public function aiGenerateCaptions(GenerateCaptionsAction $action): void
    {
        if ($quotaError = $this->consumeAiQuota()) {
            $this->aiError = $quotaError;
            return;
        }

        if (empty(trim($this->title))) {
            $this->addError('title', 'Judul produk diperlukan untuk membuat caption.');
            return;
        }

        $this->aiLoading = true;
        $this->aiLoadingMessage = 'AI sedang meracik caption viral untuk TikTok, Instagram & WhatsApp...';
        $this->aiError = null;

        try {
            $shortUrl = $this->slug ? url('/' . $this->slug) : url('/short-link');
            $this->generatedCaptions = $action->execute(
                $this->title,
                $this->description,
                $this->price,
                $this->discount_tag,
                $shortUrl,
                app(AffiliateUrlPolicy::class)->platformFor($this->original_url)['label'] ?? null
            );

            $this->dispatch('toast', message: 'Caption sudah ditulis. Simpan link untuk menyimpannya.');
        } catch (\Exception $e) {
            $this->aiError = $this->aiFailureMessage($e, 'Menulis caption');
        } finally {
            $this->aiLoading = false;
        }
    }

    public function saveLink(LinkService $linkService): void
    {
        $this->validate();

        $user = Auth::user();

        $linkData = LinkData::fromArray([
            'title' => $this->title,
            'original_url' => $this->original_url,
            'slug' => $this->slug ?: null,
            'sub_id' => $this->sub_id ?: null,
            'product_ref' => $this->product_ref ?: null,
            'status' => $this->status,
            'description' => $this->description ?: null,
            'og_title' => $this->og_title ?: null,
            'og_description' => $this->og_description ?: null,
            'og_image_url' => $this->og_image_url ?: null,
            'price' => $this->price,
            'discount_tag' => $this->discount_tag ?: null,
            'category' => $this->category ?: null,
            'is_in_catalog' => $this->is_in_catalog,
            'is_featured' => $this->is_featured,
            'ai_captions' => $this->generatedCaptions,
        ], $user->id);

        try {
            if ($this->isEditing && $this->editingLinkId) {
                $link = Link::findOrFail($this->editingLinkId);
                $this->authorize('update', $link);

                $linkService->updateLink($link, $linkData);
                $this->dispatch('toast', message: 'Perubahan disimpan.');
            } else {
                $linkService->createLink($linkData);
                $this->dispatch('toast', message: 'Link dibuat. Salin dan bagikan dari daftar.');
            }

            $this->closeModal();
        } catch (\InvalidArgumentException $e) {
            $this->addError('slug', $e->getMessage());
        }
    }

    public function toggleStatus(int $id, LinkService $linkService): void
    {
        $link = Link::findOrFail($id);
        $this->authorize('update', $link);

        $updated = $linkService->toggleStatus($link);
        $statusText = $updated->status === LinkStatus::ACTIVE ? 'diaktifkan' : 'dinonaktifkan';
        $this->dispatch('toast', message: "Link /{$updated->slug} {$statusText}.");
    }

    public function confirmDelete(int $id): void
    {
        $link = Link::findOrFail($id);
        $this->authorize('delete', $link);

        $this->deletingLinkId = $link->id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deletingLinkId = null;
    }

    public function deleteLink(LinkService $linkService): void
    {
        if (! $this->deletingLinkId) {
            return;
        }

        $link = Link::findOrFail($this->deletingLinkId);
        $this->authorize('delete', $link);

        $linkService->deleteLink($link);
        $this->showDeleteModal = false;
        $this->deletingLinkId = null;

        $this->dispatch('toast', message: 'Link dihapus.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
    }

    public function render(LinkService $linkService, AnalyticsService $analyticsService)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        $stats = $linkService->getStats($user->id, $isAdmin);

        $links = $isAdmin
            ? $linkService->getAllLinksForAdmin($this->search, $this->statusFilter, 10)
            : $linkService->getAffiliateLinks($user->id, $this->search, $this->statusFilter, 10);

        $recentClicks = $linkService->getRecentClicks($links->pluck('id')->all());

        // The single busiest link on this page gets the highlighter treatment
        $topLinkId = collect($recentClicks)
            ->filter(fn ($series) => $series['total'] > 0)
            ->sortByDesc('total')
            ->keys()
            ->first();

        return view('livewire.dashboard.overview', [
            'stats' => $stats,
            'links' => $links,
            'user' => $user,
            'isAdmin' => $isAdmin,
            'recentClicks' => $recentClicks,
            'topLinkId' => $topLinkId,
            'weekClicks' => $analyticsService->getClickTotal($user->id, 7, $isAdmin),
            'deletingLink' => $this->deletingLinkId ? Link::find($this->deletingLinkId) : null,
        ]);
    }
}
