<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class PriceAlert extends Model
{
    use HasFactory, Notifiable;

    public function routeNotificationForDiscord()
    {
        return '942481075018543155';
    }

}
