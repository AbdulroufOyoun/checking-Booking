<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $connection = 'mysql2';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'international_code',
        'mobile',
        'IdType',
        'IdNumber',
        'birth_date',
        'gender',
        'guest_type',
        'nationality',
    ];
}
