<?php

namespace App\Jobs;

use App\Actions\Analytics\RecordClickAction;
use App\DTOs\ClickEventData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TrackClickJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $linkId,
        public int $userId,
        public ?string $ipAddress,
        public ?string $referrer,
        public ?string $userAgent,
        public string $clickedAtString,
    ) {}

    public static function fromEvent(int $linkId, int $userId, ClickEventData $event): self
    {
        return new self(
            linkId: $linkId,
            userId: $userId,
            ipAddress: $event->ipAddress,
            referrer: $event->referrer,
            userAgent: $event->userAgent,
            clickedAtString: $event->clickedAt->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Execute the job.
     */
    public function handle(RecordClickAction $recordClickAction): void
    {
        $event = new ClickEventData(
            ipAddress: $this->ipAddress,
            referrer: $this->referrer,
            userAgent: $this->userAgent,
            clickedAt: new \DateTimeImmutable($this->clickedAtString),
        );

        $recordClickAction->execute($this->linkId, $this->userId, $event);
    }
}
