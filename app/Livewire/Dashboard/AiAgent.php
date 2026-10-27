<?php

namespace App\Livewire\Dashboard;

use App\Actions\AI\GenerateAdCopyAction;
use App\Actions\AI\GenerateLandingPageAction;
use App\Actions\AI\GenerateVideoScriptAction;
use App\Livewire\Concerns\LimitsAiUsage;
use App\Models\Link;
use App\Models\LinkClick;
use App\Support\LandingPageContent;
use App\Support\ReferencePageFetcher;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * AI Agent: pick a product link, then let AI write a pre-sell landing page,
 * paid-ad copy (Meta / TikTok) and short-video scripts for it.
 * Everything is saved on the link so the user never loses generated work.
 */
#[Layout('components.layouts.app')]
#[Title('AI Agent — Racunin')]
class AiAgent extends Component
{
    use AuthorizesRequests, LimitsAiUsage;

    public const TABS = [
        'landing' => 'Landing page',
        'ads' => 'Konten iklan',
        'script' => 'Script video',
    ];

    #[Url(as: 'link')]
    public ?int $linkId = null;

    #[Url]
    public string $tab = 'landing';

    public string $audience = '';
    public string $angle = '';
    public int $duration = 30;
    public ?string $error = null;

    /** Landing page editor; list fields are edited as one item per line. */
    public array $lp = [];

    /** Set before generating a landing page: reference links (one per line) and the offer. */
    public string $references = '';
    public string $ctaText = '';
    public string $ctaUrl = '';
    public string $originalPrice = '';
    public string $videoUrl = '';
    public string $videoShape = 'auto';

    protected const LINE_LISTS = ['pain_points', 'suitable_for'];

    /** How long text read from reference links is reused before the pages are read again. */
    protected const REFERENCE_TTL_DAYS = 7;

    public function mount(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'landing';
        }

        // A link id from the URL must belong to the user; otherwise start empty
        if ($this->linkId && ! $this->ownedLink()) {
            $this->linkId = null;
            unset($this->link);
        }

        $this->loadLandingForm();
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, self::TABS)) {
            $this->tab = $tab;
            $this->error = null;
        }
    }

    public function updatedLinkId(): void
    {
        unset($this->link);
        $this->error = null;
        $this->resetValidation();
        $this->loadLandingForm();
    }

    public function generateLandingPage(GenerateLandingPageAction $action): void
    {
        $this->validate($this->offerRules(''), $this->offerMessages());

        $this->runGenerator('Menulis landing page', function (Link $link, ?string $references) use ($action) {
            $link->update(['ai_landing_page' => $action->execute(
                $link,
                $this->brief('audience'),
                $this->brief('angle'),
                $references,
                [
                    'reference_urls' => $this->referenceUrls(),
                    'cta_text' => trim($this->ctaText),
                    'cta_url' => trim($this->ctaUrl),
                    'original_price' => trim($this->originalPrice),
                    'video_url' => trim($this->videoUrl),
                    'video_shape' => $this->videoShape,
                ],
            )]);
            $this->loadLandingForm();

            return 'Landing page selesai ditulis dan disimpan.';
        });
    }

    /**
     * @return list<string>
     */
    protected function referenceUrls(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $this->references))));
    }

    /**
     * Text of the reference links for the AI, read once and kept on the link: the
     * landing page, ad copy and scripts reuse it while the links stay the same.
     *
     * @return array{0: string|null, 1: int} The text, and how many links couldn't be read
     */
    protected function readReferences(Link $link): array
    {
        $urls = $this->referenceUrls();
        $saved = $link->ai_reference ?? [];

        if (! $urls) {
            if ($saved) {
                $link->update(['ai_reference' => null]);
            }

            return [null, 0];
        }

        // Reuse only a complete, recent read; a link that failed may work on the next try
        if (($saved['urls'] ?? null) === $urls && ($saved['unread'] ?? 1) === 0
            && Carbon::parse($saved['fetched_at'] ?? 0)->gt(now()->subDays(self::REFERENCE_TTL_DAYS))) {
            return [$saved['text'], 0];
        }

        $texts = [];
        foreach (app(ReferencePageFetcher::class)->fetchMany($urls) as $i => $text) {
            if ($text) {
                $texts[] = "[{$urls[$i]}]\n{$text}";
            }
        }

        $text = $texts ? implode("\n\n", $texts) : null;
        $unread = count($urls) - count($texts);
        $link->update(['ai_reference' => ['urls' => $urls, 'text' => $text, 'unread' => $unread, 'fetched_at' => now()->toIso8601String()]]);

        return [$text, $unread];
    }

    /**
     * Rules for the offer settings, shared by the pre-generate brief and the editor.
     *
     * @return array<string, mixed>
     */
    protected function offerRules(string $prefix): array
    {
        $field = fn (string $name) => $prefix === '' ? $name : "{$prefix}.{$name}";
        $snake = $prefix === '' ? ['ctaText', 'ctaUrl', 'originalPrice', 'videoUrl'] : ['cta_text', 'cta_url', 'original_price', 'video_url'];

        return [
            $field($snake[0]) => 'nullable|string|max:40',
            $field($snake[1]) => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                if (! LandingPageContent::isAllowedCtaUrl($value)) {
                    $fail('Link checkout harus link toko/affiliate yang didukung atau link WhatsApp (wa.me).');
                }
            }],
            $field($snake[2]) => ['nullable', function ($attribute, $value, $fail) {
                $price = (int) preg_replace('/[^0-9]/', '', (string) $value);
                $link = $this->selectedLink();
                if ($link && (! $link->price || $price <= (float) $link->price)) {
                    $fail($link->price ? 'Harga normal harus lebih tinggi dari harga jual (' . $link->formatted_price . ').' : 'Isi dulu harga jual di link produk supaya diskon bisa dihitung.');
                }
            }],
            $field($snake[3]) => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                if (! LandingPageContent::video($value)) {
                    $fail('Video harus link YouTube, TikTok, Google Drive, atau file .mp4 (https).');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function offerMessages(): array
    {
        return ['ctaText.max' => 'Teks tombol maksimal 40 karakter.', 'lp.cta_text.max' => 'Teks tombol maksimal 40 karakter.'];
    }

    public function generateAdCopy(GenerateAdCopyAction $action): void
    {
        $this->runGenerator('Menulis konten iklan', function (Link $link, ?string $references) use ($action) {
            $link->update(['ai_ad_copy' => $action->execute($link, $this->brief('audience'), $this->brief('angle'), $references)]);

            return 'Konten iklan selesai ditulis dan disimpan.';
        });
    }

    public function generateVideoScripts(GenerateVideoScriptAction $action): void
    {
        $this->runGenerator('Menulis script video', function (Link $link, ?string $references) use ($action) {
            $link->update(['ai_video_scripts' => $action->execute($link, $this->brief('audience'), $this->brief('angle'), $this->duration, $references)]);

            return 'Script video selesai ditulis dan disimpan.';
        });
    }

    public function addLandingItem(string $field): void
    {
        $limits = ['benefits' => 6, 'steps' => 5, 'faq' => 6, 'images' => LandingPageContent::MAX_IMAGES];
        if (! isset($limits[$field]) || count($this->lp[$field] ?? []) >= $limits[$field]) {
            return;
        }

        $this->lp[$field][] = match ($field) {
            'faq' => ['q' => '', 'a' => ''],
            'images' => '',
            default => ['title' => '', 'desc' => ''],
        };
    }

    public function removeLandingItem(string $field, int $index): void
    {
        if (in_array($field, ['benefits', 'steps', 'faq', 'images'], true) && isset($this->lp[$field][$index])) {
            unset($this->lp[$field][$index]);
            $this->lp[$field] = array_values($this->lp[$field]);
        }
    }

    public function saveLandingPage(): void
    {
        $link = $this->selectedLink();
        if (! $link || empty($link->ai_landing_page)) {
            return;
        }

        $this->validate([
            'lp.theme' => 'required|in:' . implode(',', array_keys(LandingPageContent::THEMES)),
            'lp.headline' => 'required|string|max:140',
            'lp.cta_text' => 'nullable|string|max:40',
            'lp.images.*' => 'nullable|url:http,https|max:2048',
            ...$this->offerRules('lp'),
            'lp.promo_ends_at' => ['nullable', 'date', function ($attribute, $value, $fail) {
                if (! Carbon::parse($value, LandingPageContent::INPUT_TIMEZONE)->isFuture()) {
                    $fail('Waktu promo berakhir harus di masa depan.');
                }
            }],
        ], [
            'lp.headline.required' => 'Headline wajib diisi.',
            'lp.images.*.url' => 'Link foto harus diawali http:// atau https://.',
            ...$this->offerMessages(),
        ]);

        $data = $this->lp;
        foreach (self::LINE_LISTS as $field) {
            $data[$field] = preg_split('/\R/', (string) ($data[$field] ?? ''));
        }
        $data['comparison'] = [
            'without' => preg_split('/\R/', (string) ($data['comparison_without'] ?? '')),
            'with' => preg_split('/\R/', (string) ($data['comparison_with'] ?? '')),
        ];

        $link->update(['ai_landing_page' => LandingPageContent::normalize($data, $link)]);
        $this->loadLandingForm();

        $this->dispatch('toast', message: 'Perubahan landing page disimpan.');
    }

    /**
     * Fill the editor from the saved page of the selected link.
     */
    protected function loadLandingForm(): void
    {
        $link = $this->ownedLink();

        // References are shared by all three generators; pages saved before that kept them on the landing page
        $this->references = implode("\n", $link?->ai_reference['urls'] ?? $link?->ai_landing_page['reference_urls'] ?? []);

        if (! $link || empty($link->ai_landing_page)) {
            $this->lp = [];
            $this->reset(['ctaText', 'ctaUrl', 'originalPrice', 'videoUrl', 'videoShape']);
            return;
        }

        $page = LandingPageContent::normalize($link->ai_landing_page, $link);

        // The pre-generate brief starts from the saved page, so "Tulis ulang" keeps the offer
        $this->ctaText = $page['cta_text'];
        $this->ctaUrl = $page['cta_url'];
        $this->originalPrice = (string) ($page['original_price'] ?? '');
        $this->videoUrl = $page['video_url'];
        $this->videoShape = $page['video_shape'];
        $page['original_price'] = (string) ($page['original_price'] ?? '');

        foreach (self::LINE_LISTS as $field) {
            $page[$field] = implode("\n", $page[$field]);
        }
        $page['comparison_without'] = implode("\n", $page['comparison']['without']);
        $page['comparison_with'] = implode("\n", $page['comparison']['with']);
        unset($page['comparison']);

        $page['promo_ends_at'] = $page['promo_ends_at']
            ? Carbon::parse($page['promo_ends_at'])->setTimezone(LandingPageContent::INPUT_TIMEZONE)->format('Y-m-d\TH:i')
            : '';

        $this->lp = $page;
    }

    public function togglePublish(): void
    {
        $link = $this->selectedLink();

        if (! $link || empty($link->ai_landing_page)) {
            return;
        }

        $link->update(['lp_published_at' => $link->lp_published_at ? null : now()]);

        $this->dispatch('toast', message: $link->lp_published_at ? 'Landing page sudah online.' : 'Landing page disembunyikan.');
    }

    /**
     * Quota, authorization and error handling shared by the three generators.
     */
    protected function runGenerator(string $task, \Closure $generate): void
    {
        $this->validate([
            'audience' => 'nullable|string|max:150',
            'angle' => 'nullable|string|max:150',
            'duration' => 'integer|in:' . implode(',', GenerateVideoScriptAction::DURATIONS),
            'references' => [function ($attribute, $value, $fail) {
                $urls = $this->referenceUrls();
                if (count($urls) > LandingPageContent::MAX_REFERENCES) {
                    $fail('Maksimal ' . LandingPageContent::MAX_REFERENCES . ' link referensi.');
                }
                foreach ($urls as $url) {
                    if (! preg_match('#^https?://#i', $url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                        $fail("Link referensi tidak valid: {$url}");
                    }
                }
            }],
        ]);

        $link = $this->selectedLink();
        if (! $link) {
            $this->error = 'Pilih produk dulu.';
            return;
        }

        if ($quotaError = $this->consumeAiQuota()) {
            $this->error = $quotaError;
            return;
        }

        $this->error = null;

        try {
            [$references, $unread] = $this->readReferences($link);

            $this->dispatch('toast', message: $generate($link, $references)
                . ($unread ? " {$unread} link referensi tidak bisa dibaca (biasanya karena halamannya butuh login atau memblokir bot), jadi AI memakai data link saja." : ''));
        } catch (\Exception $e) {
            // A failed generation shouldn't cost the user part of their quota
            $this->refundAiQuota();
            $this->error = $this->aiFailureMessage($e, $task);
        }
    }

    protected function brief(string $field): ?string
    {
        return trim($this->{$field}) ?: null;
    }

    /**
     * Re-resolve and re-authorize on every request: the link id is client-side state.
     */
    protected function selectedLink(): ?Link
    {
        if (! $this->linkId) {
            return null;
        }

        abort_unless($link = $this->link, 404);
        $this->authorize('update', $link);

        return $link;
    }

    /**
     * The selected link, loaded once per request (render, the editor and the actions all use it).
     */
    #[Computed]
    public function link(): ?Link
    {
        return $this->linkId ? Link::find($this->linkId) : null;
    }

    /**
     * The selected link when it belongs to the user, for display and the editor.
     */
    protected function ownedLink(): ?Link
    {
        $link = $this->link;

        return $link && $link->user_id === Auth::id() ? $link : null;
    }

    /**
     * Landing page funnel for the last 30 days: visits, buy-button clicks and the rate between them.
     *
     * @return array{views: int, unique_views: int, clicks: int, rate: float|null}
     */
    protected function landingStats(Link $link): array
    {
        $since = now()->subDays(29)->startOfDay();

        $views = DB::table('landing_page_views')
            ->where('link_id', $link->id)
            ->where('date', '>=', $since->toDateString())
            ->selectRaw('COALESCE(SUM(views), 0) AS views, COALESCE(SUM(unique_views), 0) AS unique_views')
            ->first();

        // Buy buttons carry utm_source=landing_page (see Link::landingPageCtaUrl)
        $clicks = LinkClick::where('link_id', $link->id)
            ->where('utm_source', 'landing_page')
            ->where('device_type', '!=', 'bot')
            ->where('clicked_at', '>=', $since)
            ->count();

        return [
            'views' => (int) $views->views,
            'unique_views' => (int) $views->unique_views,
            'clicks' => $clicks,
            'rate' => $views->views > 0 ? min(100, round($clicks / $views->views * 100, 1)) : null,
        ];
    }

    public function render()
    {
        // Display only the user's own link; actions still authorize via selectedLink()
        $link = $this->ownedLink();

        return view('livewire.dashboard.ai-agent', [
            'link' => $link,
            'landingStats' => $link && $link->lp_published_at && $this->tab === 'landing' ? $this->landingStats($link) : null,
            'links' => Link::forUser(Auth::id())->latest()->limit(300)->get(['id', 'title', 'slug']),
        ]);
    }
}
