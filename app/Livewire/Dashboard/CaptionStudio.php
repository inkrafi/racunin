<?php

namespace App\Livewire\Dashboard;

use App\Actions\AI\GenerateCaptionsAction;
use App\Livewire\Concerns\LimitsAiUsage;
use App\Models\Link;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * AI Caption Studio modal: view, generate and copy X/Threads captions for a link.
 * Opened from the links table via the "open-caption-studio" event.
 */
class CaptionStudio extends Component
{
    use AuthorizesRequests, LimitsAiUsage;

    protected const EMPTY_CAPTIONS = ['twitter' => '', 'threads' => ''];

    public bool $show = false;
    public ?int $linkId = null;
    public array $captions = self::EMPTY_CAPTIONS;
    public ?string $error = null;

    #[On('open-caption-studio')]
    public function open(int $linkId): void
    {
        $link = $this->findAuthorizedLink($linkId);

        $this->linkId = $link->id;
        $this->captions = $link->ai_captions ?? self::EMPTY_CAPTIONS;
        $this->error = null;
        $this->show = true;
    }

    public function close(): void
    {
        $this->reset(['show', 'linkId', 'captions', 'error']);
    }

    public function generate(GenerateCaptionsAction $action): void
    {
        if (! $this->linkId) {
            return;
        }

        if ($quotaError = $this->consumeAiQuota()) {
            $this->error = $quotaError;
            return;
        }

        $this->error = null;
        $link = $this->findAuthorizedLink($this->linkId);

        try {
            $this->captions = $action->execute(
                $link->title,
                $link->description,
                $link->price ? (float) $link->price : null,
                $link->discount_tag,
                $link->short_url,
                $link->platform_label
            );

            // Persist immediately so the user never loses generated copy
            $link->update(['ai_captions' => $this->captions]);

            $this->dispatch('links-changed');
            $this->dispatch('toast', message: 'Caption ditulis dan disimpan.');
        } catch (\Exception $e) {
            $this->error = $this->aiFailureMessage($e, 'Menulis caption');
        }
    }

    /**
     * Re-resolve and re-authorize on every request: the link id is client-side state.
     */
    protected function findAuthorizedLink(int $linkId): Link
    {
        $link = Link::findOrFail($linkId);
        $this->authorize('update', $link);

        return $link;
    }

    public function render()
    {
        return view('livewire.dashboard.caption-studio', [
            'link' => $this->show && $this->linkId ? $this->findAuthorizedLink($this->linkId) : null,
        ]);
    }
}
