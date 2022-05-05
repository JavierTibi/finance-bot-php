<?php

namespace App\Services;

use Finnhub;
use GuzzleHttp;
use phpDocumentor\Reflection\Types\Self_;


class FinnhubService
{
    const TIMEPERIOD = 80;
    const TIMEPERIOD_RSI = 14;

    /**
     * @return Finnhub\Api\DefaultApi
     */
    private function init()
    {
        $config = Finnhub\Configuration::getDefaultConfiguration()->setApiKey('token', env('TOKEN_FINNHUB'));
        return new Finnhub\Api\DefaultApi(
            new GuzzleHttp\Client(),
            $config
        );
    }

    /**
     * @param $stock
     * @param $from
     * @param $to
     * @param $time_period
     * @param $indicator
     * @return array
     * @throws Finnhub\ApiException
     */
    static public function technicalIndicator($stock, $from, $to, $time_period = null, $indicator = "sma"){
        $client = (new FinnhubService)->init();
        $indicatorFields = new \stdClass();
        $indicatorFields->timeperiod = $time_period ?? self::TIMEPERIOD;
        $technicalSMA = $client->technicalIndicator($stock, "D", $from, $to, $indicator, $indicatorFields);
        return isset(json_decode($technicalSMA[0])->sma) ? json_decode($technicalSMA[0])->sma : [];
    }

    static public function obv($stock, $from, $to, $time_period = null){
        $client = (new FinnhubService)->init();
        $indicatorFields = new \stdClass();
        $indicatorFields->timeperiod = $time_period ?? self::TIMEPERIOD;
        $result = $client->technicalIndicator($stock, "D", $from, $to, "obv");
        return isset(json_decode($result[0])->obv) ? json_decode($result[0])->obv : [];
    }

    /**
     * @param $stock
     * @param $from
     * @param $to
     * @return array
     * @throws Finnhub\ApiException
     */
    static public function rsi($stock, $from, $to){
        $client = (new FinnhubService)->init();
        $indicatorFields = new \stdClass();
        $indicatorFields->timeperiod = self::TIMEPERIOD_RSI;
        $technical = $client->technicalIndicator($stock, "D", $from, $to, "rsi", $indicatorFields);
        return isset(json_decode($technical[0])->rsi) ? json_decode($technical[0])->rsi : [];
    }

    /**
     * @param $stock
     * @param $from
     * @param $to
     * @return Finnhub\Model\StockCandles
     * @throws Finnhub\ApiException
     */
    static public function stockCandles($stock, $from, $to){
        $client = (new FinnhubService)->init();
        return $client->stockCandles($stock, "D", $from, $to);
    }

    /**
     * @param $stock
     * @return Finnhub\Model\RecommendationTrend[]
     * @throws Finnhub\ApiException
     */
    static public function recommendationTrends($stock)
    {
        $client = (new FinnhubService)->init();
        return $client->recommendationTrends($stock);
    }

    /**
     * @param $crypto
     * @param $from
     * @param $to
     * @return Finnhub\Model\CryptoCandles
     * @throws Finnhub\ApiException
     */
    static public function cryptoCandle($crypto, $from, $to){
        $client = (new FinnhubService)->init();
        return $client->cryptoCandles($crypto, "D", $from, $to);

    }
}
