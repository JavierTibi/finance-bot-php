<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cryptos extends Model
{
    use HasFactory;

    protected $table = 'cryptos';

    protected $fillable = [
        'name',
        'last_signal',
        'date_last_signal',
        'price'
    ];
}
