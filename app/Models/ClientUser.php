<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientUser extends Model
{
    protected $fillable = [
        'client_id', 'name', 'email', 'password', 'receive_report_emails'
    ];

    protected $casts = [
        'receive_report_emails' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
