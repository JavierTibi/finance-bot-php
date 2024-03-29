<?php

namespace App\Http\Controllers;

use App\Models\LogHistorial;
use App\Models\PriceAlert;
use App\Models\Stock;
use App\Services\AnalysisService;
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
    private $stocks = ['AAPL', 'ABNB', 'ADBE', 'AMD', 'AMZN', 'AXP', 'BAC', 'BP', 'C',
'COST',
'CVX',
'DIS',
'FB',
'GM',
'GOOGL',
'HD',
'INTC',
'JPM',
'KO',
'MCO',
'MELI',
'MSFT',
'NFLX',
'NVDA',
'PLTR',
'PYPL',
'SE',
'SHOP',
'SPY',
'SQ',
'TDOC',
'TRUE',
'TSLA',
'TWLO',
'UBX',
'UNH',
'VIST',
'WBA',
'WFC',
'WMT',
'XOM',
'YPF'
];

    public function getRequestStock(Request $request) {
        try {
            $data = $request->all();
            $text = $this->analisys($data['stock'], $data['short_term']);
            return response([
                'error' => false,
                'message' => $text,
                'data' => $data['stock'],
            ], 200);
        } catch (\Exception $exception) {
            return response([
                'error' => true,
                'message' => $exception->getMessage(),
                'data' => $request->stock,
            ], 400);
        }
    }

    public function getStock(Request $request)
    {
        try {
            $telegram = TelegramService::new();

            if(!isset($request->stock))
            {
                $stock = Stock::orderBy('updated_at', 'ASC')->first();
                $stock->updated_at = Carbon::now();
            } else {
                $stock = Stock::where('name', $request->stock)->first();
            }

            if(in_array($stock, ['TQQQ', 'UPRO', 'TECL', 'USD', 'ROM'])) {
                $text = $this->analisys($stock, true);
            } else {
                $text = $this->analisys($stock);
            }

            if($text) {
                $stock->last_signal = ($stock->last_signal == 'sell') ? 'buy' : 'sell';
                $stock->date_last_signal = Carbon::now()->format('Y-m-d');
                $telegram->sendMessage([
                    'chat_id' => '@ageofinvestments',
                    'text' => $text,
                    'parse_mode' => 'MARKDOWN'
                ]);
            }

            $stock->save();

            return response([
                'error' => false,
                'message' => $text ?? 'No hay señal de cambio de tendencia. Última señal: ' . strtoupper($stock->last_signal) . ' el día ' . Carbon::parse($stock->date_last_signal)->format('Y-m-d'),
                'data' => $stock->name,
            ], 200);

        } catch (\Exception $exception) {
            return response([
                'error' => true,
                'message' => 'Error',
                'data' => $exception->getMessage(),
            ], 400);
        }

    }


    private function alert($stock, $price, $sma9, $sma18, $last_signal) {

        if($price > $sma9 && $sma9 > $sma18 && $last_signal = 'sell') {
            $text = 'ALERTA: **' . $stock .'** - CRUZO EN ALZA: ** - PRECIO: ' . $price . '** ' ;
        }

        if($price < $sma9 && $sma9 < $sma18) {
            $text = 'ALERTA: **' . $stock .'** - CRUZO EN BAJA: ** - PRECIO: ' . $price . '** ' ;
        }

        if(isset($text)) {
            $telegram = TelegramService::new();
            $telegram->sendMessage([
                'chat_id' => '@ageofinvestments',
                'text' => $text,
                'parse_mode' => 'MARKDOWN'
            ]);
        }

    }

    /**
     * The magic code
     * @param $stock
     * @param bool $short_term
     * @return string|void|null
     */
    public function analisys($stock, bool $short_term = false) {

        try {
            if(is_object($stock)) {
                $name_stock = $stock->name;
                $last_signal = $stock->last_signal;
            } else {
                $name_stock = $stock;
                $last_signal = null;
            }

            $data = AnalysisService::getData($name_stock, $short_term);

            $i = $data['count'];
            //$avg = array_sum($data['candles']['v']) / count($data['candles']['v']);
            $price = $data['candles']['c'][$i];
            $vol = $data['candles']['v'][$i];
            $indicador_1 = $data['indicador_1'][$i];
            $indicador_2 = $data['indicador_2'][$i];
            //$sma80 = $data['sma80'][$i];
            $wma30  = $data['wma30'];
            $candles = $data['candles'];

//            if(/*!isset($technicalEvents) ||*/ !isset($candles['v'][$i]) || !isset($ema100[$i]) || !isset($sma200[$i]) || !isset($sma80[$i])) {
//                return "Lo siento! No puedo analizar eso " . hex2bin('F09F989E');
//            }

            //ALERT W30
            //$text_alert_w30 = AnalysisService::alertW30($name_stock, $candles['c'][$i], $candles['c'][$i-1], $wma30[$i], $wma30[$i-1]);

            //ALERT STOCK
            return AnalysisService::alert($name_stock, $price, $indicador_1, $indicador_2, $last_signal);

        } catch (\Exception $exception) {
            return $exception->getMessage();
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
