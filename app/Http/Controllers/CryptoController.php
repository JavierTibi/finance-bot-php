<?php

namespace App\Http\Controllers;

use App\Models\Cryptos;
use App\Models\LogHistorial;
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
                    'parse_mode' => 'MARKDOWN'
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
            $crypto = explode(':', $crypto_name);
            $crypto_txt = explode('USDT', $crypto[1]);

            $rsi = FinnhubService::rsi($crypto_name, $from, $to, 14);
            $candles = FinnhubService::cryptoCandle($crypto_name, $from, $to);

            $i = count($rsi) - 1;
            $price = $candles['c'][$i];

            $crypto = Cryptos::where('name', $crypto_name)->first();

            //COMPRA
            $condition_buy_1 = ($rsi[$i] > 60 && $rsi[$i-1] < 60);
            $condition_buy_2 = ($rsi[$i] > $rsi[$i-1]);
            $condition_buy_3 = ($crypto->last_signal == 'sell');


            if($condition_buy_1 && $condition_buy_2 && $condition_buy_3) {
                $text = 'COMPRA: **' . $crypto_txt[0] .'** - Precio: **' . $price . '** '. hex2bin('F09F9388') ;

                Cryptos::updateOrCreate(
                    [
                        'name' => $crypto_name,
                    ],
                    [
                        'last_signal' => 'buy',
                        'date_last_signal' => Carbon::now()->format('Y-m-d'),
                        'price' => $price
                    ]);

                LogHistorial::create([ 'name' => $crypto_name, 'price' => $price, 'signal' => 'buy' ]);

            }

            //VENTA
            $condition_sell_1 = ($rsi[$i] < 40 && $rsi[$i-1] > 40);
            $condition_sell_2 = ($rsi[$i] < $rsi[$i-1]);
            $condition_sell_3 =  ($crypto->last_signal == 'buy');

            if($condition_sell_1 && $condition_sell_2 && $condition_sell_3) {
                $text = 'VENTA: **' . $crypto_txt[0] .'** - Precio: **' . $price . '** '. hex2bin('F09F98B0') ;

                Cryptos::updateOrCreate(
                    [
                        'name' => $crypto_name,
                    ],
                    [
                        'last_signal' => 'sell',
                        'date_last_signal' => Carbon::now()->format('Y-m-d'),
                        'price' => $price
                    ]);

                LogHistorial::create([ 'name' => $crypto_name, 'price' => $price, 'signal' => 'sell' ]);

            }

            return $text;
        } catch (\Exception $exception) {
            //return 'No pude encontrar la crypto: ' . $crypto_name. '. Intentemos con otra!';
        }
    }
}
