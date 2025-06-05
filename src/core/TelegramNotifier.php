<?php

namespace App\core;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TelegramNotifier
{
    private $botToken;
    private $chatId;
    private $client;

    public function __construct(string $botToken, string $chatId)
    {
        $this->botToken = $botToken;
        $this->chatId = $chatId;
        $this->client = new Client(['timeout' => 5]);
    }

    public function sendNotification(string $message): bool
    {
        try {
            $response = $this->client->post(
                "https://api.telegram.org/bot{$this->botToken}/sendMessage",
                [
                    'json' => [
                        'chat_id' => $this->chatId,
                        'text' => $message,
                        'parse_mode' => 'HTML'
                    ]
                ]
            );
            
            return $response->getStatusCode() === 200;
        } catch (GuzzleException $e) {
            error_log("Failed to send Telegram notification: " . $e->getMessage());
            return false;
        }
    }
}