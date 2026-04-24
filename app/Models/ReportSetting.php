<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSetting extends Model
{
    protected $fillable = ['client_id','period','email_list','sms_enabled'];

    protected $casts = [
        'email_list' => 'array',
        'sms_enabled' => 'boolean'
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
