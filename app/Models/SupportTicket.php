<?php

namespace STS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    /** @var list<string> */
    public const TYPES = [
        'bug_report',
        'contact',
        'feedback',
        'report',
        'account_verification',
        'account_recovery',
    ];

    /** @var array<string, string> */
    public const TYPE_DEFAULT_PRIORITIES = [
        'report' => 'high',
        'bug_report' => 'normal',
        'contact' => 'normal',
        'feedback' => 'low',
        'account_verification' => 'high',
        'account_recovery' => 'high',
    ];

    public static function typeValidationRule(): string
    {
        return 'required|in:'.implode(',', self::TYPES);
    }

    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'status',
        'priority',
        'unread_for_user',
        'unread_for_admin',
        'internal_note_markdown',
        'last_reply_at',
        'created_by',
        'updated_by',
        'closed_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function replies()
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id');
    }

    public function attachments()
    {
        return $this->hasMany(SupportTicketAttachment::class, 'ticket_id');
    }
}
