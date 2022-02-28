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
            /*dd($text);
            if($text) {
                $telegram->sendMessage([
                    'chat_id' => '@ageofinvestments',
                    'text' => $text,
                    'parse_mode' => 'MARKDOWN'
                ]);
            }*/

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

//            $response = YahooFinanceService::call_curl('ws/insights/v1/finance/insights?symbol=' . $stock);
//
//            if(isset($response->finance->result->instrumentInfo)) {
//                $technicalEvents = $response->finance->result->instrumentInfo->technicalEvents;
//            }

            //$rsi = FinnhubService::rsi($stock, $from, $to);
            $sma9 = FinnhubService::technicalIndicator($stock, $from, $to, 9);
            $sma18 = FinnhubService::technicalIndicator($stock, $from, $to, 25);
            $sma80 = FinnhubService::technicalIndicator($stock, $from, $to, 70);
           // $sma200 = FinnhubService::technicalIndicator($stock, $from, $to, 200);
            $candles = FinnhubService::stockCandles($stock, $from, $to);
            $i = (int) count($candles['v'] ) - 1;

            if(/*!isset($technicalEvents) ||*/ !isset($candles['v'][$i]) || !isset($sma9[$i]) || !isset($sma18[$i]) || !isset($sma80[$i])) {
                return "Lo siento! No puedo analizar eso " . hex2bin('F09F989E');
            }

            $avg = array_sum($candles['v']) / count($candles['v']);
            $price = $candles['c'][$i];
            $vol = $candles['v'][$i];
            $sma9 = $sma9[$i];
            $sma18 = $sma18[$i];
            $sma80 = $sma80[$i];
           // $sma200 = $sma200[$i];

            //$stock_obj = Stock::where('name', $stock)->first();

            //COMPRA
            $condition_buy_2 = ($vol > $avg );
            $condition_buy_3  = ($price > $sma9[$i] && $sma9[$i] > $sma18[$i]  && $sma18[$i] > $sma80[$i]);
          //  $condition_buy_4 = $rsi[$i] >= 80 && $rsi[$i-1] < 80;
          //  $condition_buy_5 = isset($sma200[$i-50]) && $sma200[$i] > $sma200[$i-50];
           // $condition_buy_6 = $technicalEvents->midTerm != "down" || $technicalEvents->longTerm != "down";
           // $condition_buy_7 = ($stock_obj->last_signal == "sell");

            if($condition_buy_2 && $condition_buy_3) {
                $text = 'COMPRA: **' . $stock .'** - Precio: **' . $price . '** '. hex2bin('F09F9388') ;

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
            $condition_sell_3 = ($sma18[$i] < $sma80[$i]);
            //$condition_sell_4 = $rsi[$i] < 80 && $rsi[$i-1] >= 80;
            //$condition_sell_5 = $technicalEvents->midTerm != "up" || $technicalEvents->longTerm != "up";
            //$condition_sell_6 = ($stock_obj->last_signal == "buy");


            if($condition_sell_2 && $condition_sell_3) {
                $text = 'VENTA **' . $stock .'** - Precio: **' . $price .'** ' . hex2bin('F09F98B0') ;
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
            dd($exception->getMessage());
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

/*        $recommendation = FinnhubService::recommendationTrends($stock);

      //  dd($recommendation);*/
        $rsi = FinnhubService::rsi($stock, $from, $to);
      //  $obv = FinnhubService::obv($stock, $from, $to, 200);

        $sma9 = FinnhubService::technicalIndicator($stock, $from, $to, 9);
        $sma18 = FinnhubService::technicalIndicator($stock, $from, $to, 18);
        $sma80 = FinnhubService::technicalIndicator($stock, $from, $to, 80);
        $sma200 = FinnhubService::technicalIndicator($stock, $from, $to, 200);
        $candles = FinnhubService::stockCandles($stock, $from, $to);

        $avg = array_sum($candles['v']) / count($candles['v']);

        $result= [];
        for ($i = 0; $i < count($candles['c']); $i++){
            $price = $candles['c'][$i];
            $vol = $candles['v'][$i];

            $date = Carbon::createFromTimestamp($candles['t'][$i])->format('d/m/Y');
/*            if($date == "07/09/2021") {
                dd($vol,$avg );
            }*/
            //COMPRA
            $condition_buy_7 = ($signal == 'sell');
            $condition_buy_2 = ($vol > $avg );
            $condition_buy_3  = ($price > $sma9[$i] && $sma9[$i] > $sma18[$i]  && $sma18[$i] > $sma80[$i]);
        //    $condition_buy_3  = ($price > $sma9[$i] && $sma9[$i] > $sma18[$i]);
        //    $condition_buy_3  = ($sma18[$i] > $sma80[$i] &&  $sma80[$i] > $sma200[$i]);

            //$condition_buy_3  =  $price > $sma9[$i];
            //$condition_buy_4 = isset($rsi[$i - 1]) && $rsi[$i] < $rsi[$i - 1] && $rsi[$i] >= 80 && $rsi[$i] < 82;

           // $condition_buy_4 =  $rsi[$i] >= 60; //&& $rsi[$i-1] < 65;

           // $condition_buy_4 = (isset($obv[$i - 7]) && isset($candles['c'][$i - 7])) && ($obv[$i-1] > $obv[$i-7] && $price < $candles['c'][$i-7]);
            //$condition_buy_4 = $obv[$i] > 0;

            //$condition_buy_5 = isset($sma80[$i - 21]) && $sma80[$i] > $sma80[$i - 21];


            if($condition_buy_2  && ($condition_buy_3 /*&& $condition_buy_4*/) /*&& $condition_buy_5*/ &&  $condition_buy_7) {

                $signal = 'buy';
                $r['date'] = Carbon::createFromTimestamp($candles['t'][$i])->format('d/m/Y');
                $r['signal'] = $signal;
                $r['price'] = $candles['c'][$i];
                $precio_compra = $candles['c'][$i];

                $result[] = $r;

            }

            $condition_sell_1 = ($signal == 'buy');

            $condition_sell_2 = ($vol > $avg);
            //$condition_sell_3 =  $price < $sma9[$i] ;
             $condition_sell_3 = ( $sma18[$i] < $sma80[$i] );
           // $condition_sell_4 =  $rsi[$i] < 40; //&& $rsi[$i-1] >= 45;

            //$condition_sell_4 = (isset($obv[$i - 14]) && isset($candles['c'][$i - 14])) && ($obv[$i-1] < $obv[$i-14] && $price > $candles['c'][$i-14]);
           // $condition_sell_4 = $obv[$i] < 0;

            $condition_sell_5 =  ($i == (count($candles['v'])-1) and $signal == 'buy');
           // $condition_sell_6 = isset($precio_compra) && $price < $precio_compra;

            //VENTA
            if($condition_sell_1 && $condition_sell_2 && ($condition_sell_3 /*&& $condition_sell_4*/) || $condition_sell_5  /*|| ($condition_sell_1 && $condition_sell_2  && $condition_sell_4)*/){
                $signal = 'sell';
                $r['date'] = Carbon::createFromTimestamp($candles['t'][$i])->format('d/m/Y');
                $r['signal'] = $signal;
                $r['price'] = $candles['c'][$i];
                $result[] = $r;
            }

        }

        $earn = $win_rate = $lose_rate = 0;

        if(isset($result[0])) {
            foreach ($result as $r) {
                if($r['signal'] == 'buy') {
                    $earn -= $r['price'];
                    $price_buy = $r['price'];
                }
                if($r['signal'] == 'sell') {
                    $earn += $r['price'];
                    $last_price_sell = $r['price'];
                    if($r['price'] > $price_buy) {
                        $win_rate +=1;
                    } else {
                        $lose_rate -=1;
                    }
                }
            }

            $hold = $candles['c'][$i-1] - $result[0]['price'];
            $hold_perc = ($hold * 100) / $candles['c'][$i-1] . '%';

            $earn_perc = ($earn * 100) / $last_price_sell . '%';

            $super_hold = $candles['c'][$i-1] - $candles['c'][0];
            $super_hold_perc = ($super_hold * 100) / $candles['c'][$i-1] . '%';
            $array = [
                "algo_ganancia" => $earn,
                "algo_ganancia_porc" => round($earn_perc,2) .'%',
                "win_rate" => $win_rate .' / '. count($result) / 2,
                "win_rate_porc" => $win_rate / (count($result)/2),
                "hold_ganancia" => $super_hold,
                "hold_ganancia_porc" => round($super_hold_perc) . '%',
//                "hold_anual_ganancia" => $super_hold,
//                "hold_anual_ganancia_porc" => round($super_hold_perc) . '%',
                "historical" => $result
            ];
        } else {
            $array = [
                "hold_ganancia" => $candles['c'][$i-1] - $candles['c'][0],
                "hold_ganancia_porc" => round( ($candles['c'][$i-1] - $candles['c'][0]) * 100 / $candles['c'][$i-1], 2) . '%',
//                "hold_anual_ganancia" => $super_hold,
//                "hold_anual_ganancia_porc" => round($super_hold_perc) . '%',
                "historical" => $result
            ];
        }

        dd($array);

    }

}
