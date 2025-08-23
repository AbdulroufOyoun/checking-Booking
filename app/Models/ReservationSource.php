<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationSource extends Model
{
    use HasFactory;
    public $table = 'reservation_sources';
    protected $fillable = ['NameAr', 'NameEn'];
}
