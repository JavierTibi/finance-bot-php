<?php

namespace App\Services;

use Telegram\Bot\Api;

class TelegramService
{
    static public function new(){

        return new Api(env('TOKEN_TELEGRAM'));
    }
}
