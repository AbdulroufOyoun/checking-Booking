<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
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

    public function notes()
    {
        return $this->hasMany(ClientNote::class);
    }

    public function classifications()
    {
        return $this->hasMany(Client_Classifications::class, 'client_id');
    }
}
