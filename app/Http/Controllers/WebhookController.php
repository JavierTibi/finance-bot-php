<?php

namespace App\Http\Controllers;

use App\Models\Cryptos;
use App\Models\Stock;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WebhookController extends Controller
{

    /**
     * @var StockController
     */
    private $stockController;

    /**
     * @var CryptoController
     */
    private $cryptoController;

    /**
     * @param StockController $stockController
     */
    public function __construct(StockController $stockController, CryptoController $cryptoController)
    {
        $this->stockController = $stockController;
        $this->cryptoController = $cryptoController;
    }

    /**
     * @return void
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    public function webhook(){
        try {
            $telegram = TelegramService::new();

            $response = $telegram->getWebhookUpdate();

            $crypto_array = ['BTC', 'ETH', 'ADA', 'SOL', 'MATIC', 'FTT', 'CAKE', 'DOGE', 'SHIB', 'AVAX', 'DOT', 'ALGO', 'LTC', 'ATOM', 'UNI', 'LINK', 'LUNA', 'BNB', 'XRP', 'TRX', 'NEAR', 'BCH', 'XLM', 'FTM', 'MANA'];

            if(in_array(strtoupper($response['message']['text']), $crypto_array)){
                $crypto_txt = 'BINANCE:'.$response['message']['text'].'USDT';
                $text = $this->cryptoController->cryptoAnalysis(strtoupper($crypto_txt));
                $result = Cryptos::where('name', $crypto_txt)->first();
            } else {
                $text = $this->stockController->analisys(strtoupper($response['message']['text']));
                $result = Stock::where('name', $response['message']['text'])->first();
            }

            if(!$text) {
                $signal = ($result->last_signal == 'buy') ? 'COMPRA' : 'VENTA';
                $text =  'La última señal fue de **' . $signal ?? '*sin valor*' .'**  el día  ' . Carbon::parse($result->date_last_signal)->format('Y-m-d') ?? '*sin valor*';
            }

            $telegram->sendMessage([
                'chat_id' => $response['message']['chat']['id'],
                'text' => $text,
                'parse_mode' => 'MARKDOWN'
            ]);

        } catch (\Exception $exception) {
            $telegram->sendMessage([
                'chat_id' => $response['message']['chat']['id'],
                'text' => 'Lo siento! No puedo analizar eso.',
                'parse_mode' => 'MARKDOWN'
            ]);
        }


    }
}
