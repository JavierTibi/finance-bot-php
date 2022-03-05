<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogHistorial extends Model
{
    use HasFactory;

    protected $table = 'log_historial';

    protected $fillable = [
        'name',
        'price',
        'signal'
    ];


}
