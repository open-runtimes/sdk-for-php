<?php

declare(strict_types=1);

namespace OpenRuntimes\Orchestrator\Model;

use OpenRuntimes\Orchestrator\Enum\CallbackEvent;

final readonly class Callback implements ArraySerializable
{
    /**
     * @param  list<CallbackEvent|string>  $events
     */
    public function __construct(
        public string $url,
        public array $events = [],
        public ?string $key = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'url' => $this->url,
            'events' => \array_map(static fn (CallbackEvent|string $event): string => $event instanceof CallbackEvent ? $event->value : $event, $this->events),
        ];

        if ($this->key !== null && $this->key !== '') {
            $data['key'] = $this->key;
        }

        return $data;
    }
}
