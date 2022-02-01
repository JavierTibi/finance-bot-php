<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class YahooFinanceService
{
    static public function call($url) {
        $response = Http::withToken(env('oh4YtZ8LOB6TPvz8iBhTK3HVCav6ervE90eWYzZ5'))
            ->get('https://yfapi.net/'. $url);

        dd($response->body());
    }

    static public function call_curl($url) {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://yfapi.net/" . $url ,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "x-api-key: oh4YtZ8LOB6TPvz8iBhTK3HVCav6ervE90eWYzZ5"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            dd( "cURL Error #:" . $err);
        } else {
            return json_decode($response);
        }
    }
}
