<?php

namespace App\Livewire\Dashboard;

use App\Models\Link;
use App\Models\LinkClick;
use App\Support\AppLink;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Phone test bench: open a short link from each social app and see what
 * Racunin detected, comparing the plain redirect with the open-in-app page.
 */
#[Layout('components.layouts.app')]
#[Title('Tes link di HP — Racunin')]
class LinkTester extends Component
{
    #[Url(as: 'link')]
    public ?int $linkId = null;

    public function mount(): void
    {
        $owned = $this->linkId && Link::forUser(Auth::id())->whereKey($this->linkId)->exists();

        if (! $owned) {
            $this->linkId = Link::forUser(Auth::id())->active()->latest()->value('id');
        }
    }

    public function selectLink(?int $linkId): void
    {
        $this->linkId = $linkId && Link::forUser(Auth::id())->whereKey($linkId)->exists() ? $linkId : null;
    }

    public function render(AppLink $appLink)
    {
        $links = Link::forUser(Auth::id())->active()->latest()->get(['id', 'title', 'slug', 'original_url']);
        $link = $links->firstWhere('id', $this->linkId);

        $clicks = $link
            ? LinkClick::where('link_id', $link->id)
                ->where('user_id', Auth::id())
                ->latest('clicked_at')
                ->limit(10)
                ->get(['id', 'clicked_at', 'browser', 'platform', 'referrer_channel', 'device_type'])
            : collect();

        return view('livewire.dashboard.link-tester', [
            'links' => $links,
            'link' => $link,
            'clicks' => $clicks,
            'supportsApp' => $link && $appLink->intentUrl($link->original_url) !== null,
            'openInAppDefault' => (bool) config('affiliate.open_in_app'),
            'isAdmin' => Auth::user()->isAdmin(),
        ]);
    }
}
