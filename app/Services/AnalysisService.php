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
    public static function getData($stock, $short_term): array
    {
        $to = Carbon::now()->timestamp;
        $from = Carbon::now()->subYear()->timestamp;
        $candles = FinnhubService::stockCandles($stock, $from, $to);

        //$rsi = FinnhubService::rsi($stock, $from, $to);
        //$response = YahooFinanceService::call_curl('ws/insights/v1/finance/insights?symbol=' . $stock);
        // if(isset($response->finance->result->instrumentInfo)) {
        //      $technicalEvents = $response->finance->result->instrumentInfo->technicalEvents;
        // }

        if($short_term) {
            $indicator_1 = FinnhubService::technicalIndicator($stock, $from, $to, 9);
            $indicator_2 = FinnhubService::technicalIndicator($stock, $from, $to, 18);
        } else {
            $indicator_1 = FinnhubService::technicalIndicator($stock, $from, $to, 100, 'ema');
            $indicator_2 = FinnhubService::technicalIndicator($stock, $from, $to, 200);
        }
        return [
            'wma30' =>  FinnhubService::technicalIndicator($stock, $from, $to, 30, "wma"),
            'indicador_1' => $indicator_1,
            'indicador_2' => $indicator_2,
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
     * @param $signal_1
     * @param $signal_2
     * @param $last_signal
     * @return string|void
     */
    public static function alert($stock, $price, $signal_1, $signal_2, $last_signal = null) {
        if($last_signal) {
            if($price > $signal_1 && $signal_1 > $signal_2 && $last_signal == 'sell') {
                return 'ALERTA: **' . $stock .'** - CRUZO EN ALZA: ** - PRECIO: ' . $price . '** ' ;
            }

            if($price < $signal_1 && $signal_1 < $signal_2 && $last_signal == 'buy') {
                return 'ALERTA: **' . $stock .'** - CRUZO EN BAJA: ** - PRECIO: ' . $price . '** ' ;
            }
        }

        if($price > $signal_1 && $signal_1 > $signal_2) {
            return '**' . $stock .'** - ESTA EN ALZA: ** - PRECIO: ' . $price . '** ' ;
        }
        if($price < $signal_1 && $signal_1 < $signal_2) {
            return '**' . $stock .'** - ESTA EN BAJA: ** - PRECIO: ' . $price . '** ' ;
        }
    }

}
