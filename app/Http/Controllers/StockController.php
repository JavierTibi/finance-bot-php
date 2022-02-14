<?php

namespace App\Http\Controllers;

use App\Models\PriceAlert;
use App\Models\Stock;
use App\Services\FinnhubService;
use App\Services\TelegramService;
use App\Services\YahooFinanceService;
use Carbon\Carbon;
use Discord\Discord;
use Illuminate\Http\Request;
use Finnhub;
use GuzzleHttp;
use Discord\Parts\Channel\Message;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory;
use React\EventLoop\Loop;
use React\Http\Browser;

class StockController extends Controller
{
    public function getStock(Request $request)
    {
        try {
            $telegram = TelegramService::new();

            $stock = Stock::orderBy('updated_at', 'ASC')->first();

            if(!isset($request->stocks))
            {
                $stock->updated_at = Carbon::now();
                $stock->save();
                $stock_name = $stock->name;
            } else {
                $stock_name = $request->stocks;
            }

            $text = $this->analisys($stock_name);
            if($text) {
                $telegram->sendMessage([
                    'chat_id' => '@ageofinvestments',
                    'text' => $text,
                    'parse_mode' => 'HTML'
                ]);
            }

            return response([
                'error' => false,
                'message' => 'Success',
                'data' => $stock_name,
            ], 200);

        } catch (\Exception $exception) {
            return response([
                'error' => true,
                'message' => 'Error',
                'data' => $exception->getMessage(),
            ], 400);
        }

    }

    /**
     * The magic code
     *
     * @param $stock
     * @return string
     */
    public function analisys($stock) {

        try {

            $text = null;
            $to = Carbon::now()->timestamp;
            $from = Carbon::now()->subYear()->timestamp;

            $response = YahooFinanceService::call_curl('ws/insights/v1/finance/insights?symbol=' . $stock);

            if(isset($response->finance->result->instrumentInfo)) {
                $technicalEvents = $response->finance->result->instrumentInfo->technicalEvents;
            }

            $rsi = FinnhubService::rsi($stock, $from, $to);
            $sma9 = FinnhubService::technicalIndicator($stock, $from, $to, 9);
            $sma18 = FinnhubService::technicalIndicator($stock, $from, $to, 18);
            $sma80 = FinnhubService::technicalIndicator($stock, $from, $to, 80);
            $sma200 = FinnhubService::technicalIndicator($stock, $from, $to, 200);
            $candles = FinnhubService::stockCandles($stock, $from, $to);
            $i = count($sma200) - 1;

            if(!isset($technicalEvents) || !isset($candles['v'][$i]) || !isset($sma9[$i]) || !isset($sma18[$i]) || !isset($sma80[$i]) || !isset($sma200[$i])) {
                return "Lo siento! No puedo analizar eso " . hex2bin('F09F989E');
            }

            $avg = array_sum($candles['v']) / count($candles['v']);
            $price = $candles['c'][$i];
            $vol = $candles['v'][$i];
            $sma9 = $sma9[$i];
            $sma18 = $sma18[$i];
            $sma80 = $sma80[$i];
            $sma200 = $sma200[$i];


            //COMPRA
            $condition_buy_2 = ($vol > $avg);
            $condition_buy_3  = ($price > $sma9 && $sma9 > $sma18  && $sma18 > $sma80);
            $condition_buy_4 = $rsi[$i] < $rsi[$i-1] && $rsi[$i] >= 80 && $rsi[$i] < 82;
            $condition_buy_5 = isset($sma200[$i-50]) && $sma200[$i] > $sma200[$i-50];
            $condition_buy_6 = $technicalEvents->midTerm != "down" || $technicalEvents->longTerm != "down";

            if($condition_buy_2 && ($condition_buy_3 || $condition_buy_4) && $condition_buy_5 && $condition_buy_6) {
                $text = 'COMPRA: <b>' . $stock .'</b> - Precio: ' . $price . ' '. hex2bin('F09F9388') ;

                Stock::updateOrCreate(
                    [
                        'name' => $stock,
                    ],
                    [
                        'last_signal' => 'buy',
                        'date_last_signal' => Carbon::now()->format('Y-m-d')
                    ]);
            }

            //VENTA
            $condition_sell_2 = ($vol > $avg);
            $condition_sell_3 = ($price < $sma9 && $sma9 < $sma18 && $sma18 < $sma80);
            $condition_sell_4 = $rsi[$i] < $rsi[$i-1] && $rsi[$i] >= 80 && $rsi[253] < 82;
            $condition_sell_5 = $technicalEvents->midTerm != "up" || $technicalEvents->longTerm != "up";


            if($condition_sell_2 && ($condition_sell_3 || $condition_sell_4) && $condition_sell_5) {
                $text = 'VENTA <b>' . $stock .'</b> - Precio: ' . $price .' ' . hex2bin('F09F98B0') ;
                Stock::updateOrCreate(
                    [
                        'name' => $stock,
                    ],
                    [
                        'last_signal' => 'sell',
                        'date_last_signal' => Carbon::now()->format('Y-m-d')
                    ]);
            }

            return $text;
        } catch (\Exception $exception) {
            return 'Lo siento, no puedo analizar eso';
        }

    }

    //TODO CLEAN CODE
    public function historicalAnalysis(Request $request){

        $to = Carbon::now()->timestamp;
        $from = Carbon::now()->subYear()->timestamp;

        $stock = $request->s;
        $signal = 'sell';
        $array = [];

//        $response = YahooFinanceService::call_curl('ws/insights/v1/finance/insights?symbol=' . $stock);
//
//        dd($response);

        $rsi = FinnhubService::rsi($stock, $from, $to, 14);

   //     $recommendation = FinnhubService::recommendationTrends($stock);
   //     dd($recommendation);
        $sma = FinnhubService::technicalIndicator($stock, $from, $to, 80);

        $sma9 = FinnhubService::technicalIndicator($stock, $from, $to, 9);
        $sma18 = FinnhubService::technicalIndicator($stock, $from, $to, 18);

        $candles = FinnhubService::stockCandles($stock, $from, $to);

        $avg = (array_sum($candles['v']) / count($candles['v']));

        for ($i = 0; $i < count($candles['c']); $i++){

            //COMPRA
            $condition_buy_1 = ($signal == 'sell');
            $condition_buy_2 = ($candles['v'][$i] > $avg );
            //$condition_buy_3 = ($candles['c'][$i] > $sma[$i]);
            //$condition_buy_3 = ($candles['c'][$i] > $sma9[$i] && $sma9[$i] > $sma18[$i]);
            $condition_buy_3  = ($candles['c'][$i] > $sma9[$i] && $sma9[$i] > $sma18[$i]  && $sma18[$i] > $sma[$i]);
            $condition_buy_4 = isset($rsi[$i - 1]) && $rsi[$i] < $rsi[$i - 1] && $rsi[$i] >= 80 && $rsi[$i] < 82;
            $condition_buy_5 = isset($sma[$i - 50]) && $sma[$i] > $sma[$i - 50];


            if($condition_buy_1 && $condition_buy_2 && ($condition_buy_3 || $condition_buy_4) && $condition_buy_5) {

//            if($candles['c'][$i] > $sma9[$i] && $sma9[$i] > $sma18[$i]  &&  $candles['v'][$i] > $avg  &&  $signal == 'sell') {
//            if($candles['c'][$i] > $sma9[$i] && $sma9[$i] > $sma18[$i]  && $sma18[$i] > $sma[$i] && $candles['v'][$i] > $avg  &&  $signal == 'sell') {
//            if($candles['c'][$i] > $sma[$i]   &&  $candles['v'][$i] > $avg  &&  $signal == 'sell') {
                $signal = 'buy';
                $r['date'] = Carbon::createFromTimestamp($candles['t'][$i])->format('d/m/Y');
                $r['signal'] = $signal;
                $r['price'] = $candles['c'][$i];
                $result[] = $r;

            }

            $condition_sell_1 = ($signal == 'buy');
            $condition_sell_2 = ($candles['v'][$i] > $avg);
            //$condition_sell_3 = ($candles['c'][$i] < $sma[$i]) ;
            //$condition_sell_3 = ($candles['c'][$i] < $sma9[$i] && $sma9[$i] < $sma18[$i]);
            $condition_sell_3 = ($candles['c'][$i] < $sma9[$i] && $sma9[$i] < $sma18[$i] && $sma18[$i] < $sma[$i]);
            $condition_sell_4 = isset($rsi[$i - 1]) && $rsi[$i] < $rsi[$i - 1] && $rsi[$i] >= 80 && $rsi[$i] < 82;

            //VENTA
            if(($condition_sell_1 && $condition_sell_2 && $condition_sell_3) || ($condition_sell_1 && $condition_sell_2 && $condition_sell_4)){
 //           if($candles['c'][$i] < $sma9[$i] && $sma9[$i] < $sma18[$i]  && $candles['v'][$i] > $avg && $signal == 'buy') {
//            if($candles['c'][$i] < $sma9[$i] && $sma9[$i] < $sma18[$i] && $sma18[$i] < $sma[$i] && $candles['v'][$i] > $avg && $signal == 'buy') {
//            if($candles['c'][$i] < $sma[$i]  && $candles['v'][$i] > $avg && $signal == 'buy') {
                $signal = 'sell';
                $r['date'] = Carbon::createFromTimestamp($candles['t'][$i])->format('d/m/Y');
                $r['signal'] = $signal;
                $r['price'] = $candles['c'][$i];
                $result[] = $r;
            }

        }

        $earn = 0;

        if(isset($result)) {
            foreach ($result as $r) {
                if($r['signal'] == 'buy') {
                    $earn -= $r['price'];

                }
                if($r['signal'] == 'sell') {
                    $earn += $r['price'];
                    $last_price_sell = $r['price'];
                }
            }

            $hold = $candles['c'][251] - $result[0]['price'];
            $hold_perc = ($hold * 100) / $candles['c'][251] . '%';

            $earn_perc = ($earn * 100) / $last_price_sell . '%';

            $super_hold = $candles['c'][251] - $candles['c'][0];
            $super_hold_perc = ($super_hold * 100) / $candles['c'][251] . '%';
            $array = [
                "algo_ganancia" => $earn,
                "algo_ganancia_porc" => round($earn_perc,2) .'%',
                "hold_ganancia" => $hold,
                "hold_ganancia_porc" => round($hold_perc) . '%',
                "hold_anual_ganancia" => $super_hold,
                "hold_anual_ganancia_porc" => round($super_hold_perc) . '%',
                "historical" => $result
            ];
        }

        dd($array);

    }

    public function jokeDiscord() {



    }


}
