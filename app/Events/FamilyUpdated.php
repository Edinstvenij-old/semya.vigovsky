<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Something changed in the family" signal for open apps. It carries only the
 * kind of change and a few ids — never content — and the app reloads what it shows.
 */
class FamilyUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $familyId, public string $type, public array $data = []) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("family.{$this->familyId}")];
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    public function broadcastWith(): array
    {
        return ['type' => $this->type] + $this->data;
    }
}
