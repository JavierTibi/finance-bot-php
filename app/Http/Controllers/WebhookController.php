<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{

    /**
     * @var StockController
     */
    private $stockController;

    /**
     * @param StockController $stockController
     */
    public function __construct(StockController $stockController)
    {
        $this->stockController = $stockController;
    }

    /**
     * @return void
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    public function webhook(){
        $telegram = TelegramService::new();

        $response = $telegram->getWebhookUpdate();

        $text = $this->stockController->analisys($response['message']['text']);
        if(!$text) {
            $text = '<b><i>Sin movimientos importantes</i></b>';
        }
        $telegram->sendMessage([
            'chat_id' => $response['message']['chat']['id'],
            'text' => $text,
            'parse_mode' => 'HTML'
        ]);

    }
}
