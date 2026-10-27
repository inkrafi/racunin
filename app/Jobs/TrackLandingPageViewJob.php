<?php

namespace App\Jobs;

use App\Actions\Analytics\RecordLandingPageViewAction;
use App\DTOs\ClickEventData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TrackLandingPageViewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $linkId,
        public ?string $ipAddress,
        public ?string $userAgent,
        public string $viewedAtString,
    ) {}

    public static function fromEvent(int $linkId, ClickEventData $event): self
    {
        return new self(
            linkId: $linkId,
            ipAddress: $event->ipAddress,
            userAgent: $event->userAgent,
            viewedAtString: $event->clickedAt->format('Y-m-d H:i:s'),
        );
    }

    public function handle(RecordLandingPageViewAction $action): void
    {
        $action->execute($this->linkId, new ClickEventData(
            ipAddress: $this->ipAddress,
            referrer: null,
            userAgent: $this->userAgent,
            clickedAt: new \DateTimeImmutable($this->viewedAtString),
        ));
    }
}
