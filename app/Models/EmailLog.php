<?php
// app/Models/EmailLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'type', 'recipient_email', 'subject', 
        'content', 'sent_successfully', 'error_message'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}