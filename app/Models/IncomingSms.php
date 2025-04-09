<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingSms extends Model
{
    protected $fillable = [
        'sender',
        'message',
        'received_at',
        'sim_number',
        'gateway_message_id',
        'raw_payload',
    ];
}
