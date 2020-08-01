<?php

namespace App\Notifiers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class TelegramNotifier
{
    /** @var string */
    protected $baseUrl;

    /** @var string */
    protected $token;

    /** @var string */
    protected $chatId;

    public function __construct()
    {
        $this->baseUrl = env('TELEGRAM_BASE_URL');
        $this->token   = env('TELEGRAM_TOKEN');
        $this->chatId  = env('TELEGRAM_CHAT_ID');
    }

    public function sendMessage(string $message): Response
    {
        return Http::post("$this->baseUrl/bot$this->token/sendMessage", [
            'chat_id'    => $this->chatId,
            'text'       => $message,
            'parse_mode' => 'HTML',
        ]);
    }
}
