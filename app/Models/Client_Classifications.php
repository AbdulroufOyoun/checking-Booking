<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client_Classifications extends Model
{
    use HasFactory;

    public $table = 'client_classifications';
    protected $fillable = ['classifications_id', 'client_id'];
    public $timestamps = false;

    public function guestClassification()
    {
return $this->belongsTo(Guest_classification::class, 'classifications_id', 'id');    }
}
