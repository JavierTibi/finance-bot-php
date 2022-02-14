<?php

namespace App\Http\Controllers;

use App\Models\Cryptos;
use App\Models\PriceAlert;
use App\Models\Stock;
use App\Services\FinnhubService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CryptoController extends Controller
{
    public function getCrypto(Request $request)
    {
        try {
            $telegram = TelegramService::new();

            if(!isset($request->crypto))
            {
                $crypto = Cryptos::orderBy('updated_at', 'ASC')->first();
                $crypto->updated_at = Carbon::now();
                $crypto->save();
                $crypto_name = $crypto->name;
            } else {
                $crypto_name = $request->crypto;
            }

            $text = $this->cryptoAnalysis($crypto_name);

            if($text) {
                $telegram->sendMessage([
                    'chat_id' => '@ageofinvestments',
                    'text' => $text,
                    'parse_mode' => 'HTML'
                ]);

                $priceAlert = new PriceAlert();
                $priceAlert->notify(new \App\Notifications\PriceAlertNotification($text));

            }

            return response([
                'error' => false,
                'message' => 'Success',
                'data' => $crypto_name,
            ], 200);

        } catch (\Exception $exception) {
            return response([
                'error' => true,
                'message' => 'Error',
                'data' => $exception->getMessage(),
            ], 400);
        }

    }

    public function cryptoAnalysis($crypto_name)
    {
        try {
            $text = null;
            $to = Carbon::now()->timestamp;
            $from = Carbon::now()->subYear()->timestamp;

            $rsi = FinnhubService::rsi($crypto_name, $from, $to, 14);
            $candles = FinnhubService::cryptoCandle($crypto_name, $from, $to);

            $i = count($rsi);
            $price = $candles['c'][$i];

            //COMPRA
            $condition_buy_1 = ($rsi[$i] > 55);
            $condition_buy_2 = ($rsi[$i] > $rsi[$i-1]);

            if($condition_buy_1 && $condition_buy_2) {
                $text = 'COMPRA: <b>' . $crypto_name .'</b> - Precio: ' . $price . ' '. hex2bin('F09F9388') ;

                Stock::updateOrCreate(
                    [
                        'name' => $crypto_name,
                    ],
                    [
                        'last_signal' => 'buy',
                        'date_last_signal' => Carbon::now()->format('Y-m-d'),
                        'price' => $price
                    ]);
            }

            //VENTA
            $condition_sell_1 = ($rsi[$i] < 40);
            $condition_sell_2 = ($rsi[$i] < $rsi[$i-1]);

            if($condition_sell_1 && $condition_sell_2) {
                $text = 'COMPRA: <b>' . $crypto_name .'</b> - Precio: ' . $price . ' '. hex2bin('F09F9388') ;

                Stock::updateOrCreate(
                    [
                        'name' => $crypto_name,
                    ],
                    [
                        'last_signal' => 'sell',
                        'date_last_signal' => Carbon::now()->format('Y-m-d'),
                        'price' => $price
                    ]);
            }

            return $text;
        } catch (\Exception $exception) {
            return 'Lo siento, no puedo analizar eso';
        }
    }
}
