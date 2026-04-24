<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $fillable = ['client_id','receiver','subject','sent_at'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
