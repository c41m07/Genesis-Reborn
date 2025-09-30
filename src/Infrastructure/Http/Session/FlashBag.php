<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Session;

class FlashBag
{
    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function add(string $type, string $message): void
    {
        $this->session->flash('messages', ['type' => $type, 'message' => $message]);
    }

    /** @return array<int, array{type: string, message: string}> */
    public function consume(): array
    {
        $flashes = $this->session->pull('_flashes', []);
        if (!is_array($flashes)) {
            return [];
        }

        $messages = [];
        foreach ($flashes as $entries) {
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $entry) {
                if (is_array($entry) && isset($entry['type'], $entry['message'])) {
                    $messages[] = [
                        'type' => (string)$entry['type'],
                        'message' => (string)$entry['message'],
                    ];
                }
            }
        }

        return $messages;
    }
}
