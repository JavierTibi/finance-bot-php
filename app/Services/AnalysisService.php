<?php

namespace App\Services;

use Carbon\Carbon;
use Finnhub\ApiException;

class AnalysisService
{

    /**
     * @param $stock
     * @return array
     * @throws ApiException
     */
    public static function getData($stock): array
    {
        $to = Carbon::now()->timestamp;
        $from = Carbon::now()->subYear()->timestamp;
        $candles = FinnhubService::stockCandles($stock, $from, $to);

        //$rsi = FinnhubService::rsi($stock, $from, $to);
        //$response = YahooFinanceService::call_curl('ws/insights/v1/finance/insights?symbol=' . $stock);
        // if(isset($response->finance->result->instrumentInfo)) {
        //      $technicalEvents = $response->finance->result->instrumentInfo->technicalEvents;
        // }

        return [
            'wma30' =>  FinnhubService::technicalIndicator($stock, $from, $to, 30, "wma"),
            'sma9' => FinnhubService::technicalIndicator($stock, $from, $to, 9),
            'sma18' => FinnhubService::technicalIndicator($stock, $from, $to, 18),
            'sma80' => FinnhubService::technicalIndicator($stock, $from, $to, 80),
            'candles' => $candles,
            'count' => count($candles['v'] ) - 1
        ];

    }

    /**
     * @param $stock
     * @param $price_today
     * @param $price_yesterday
     * @param $w30_today
     * @param $w30_yesterday
     * @return string|void
     */
    public static function alertW30($stock, $price_today, $price_yesterday, $w30_today, $w30_yesterday) {

        if($w30_yesterday <= $price_yesterday && $w30_today > $price_today) {
            return 'ALERTA: **' . $stock .'** - CRUZO W30 EN ALZA: ** - PRECIO: ' . $price_today . '** ' ;
        }

        if($w30_yesterday >= $price_yesterday && $w30_today < $price_today) {
            return 'ALERTA: **' . $stock .'** - CRUZO W30 EN BAJA: ** - PRECIO: ' . $price_today . '** ' ;
        }
    }

    /**
     * @param $stock
     * @param $price
     * @param $sma9
     * @param $sma18
     * @param $last_signal
     * @return string|void
     */
    public static function alert($stock, $price, $sma9, $sma18, $last_signal) {
        if($price > $sma9 && $sma9 > $sma18 && $last_signal == 'sell') {
            return 'ALERTA: **' . $stock .'** - CRUZO EN ALZA: ** - PRECIO: ' . $price . '** ' ;
        }

        if($price < $sma9 && $sma9 < $sma18 && $last_signal == 'buy') {
            return 'ALERTA: **' . $stock .'** - CRUZO EN BAJA: ** - PRECIO: ' . $price . '** ' ;
        }
    }
}
