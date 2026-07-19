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

    public const STATUS_NEEDS_REVIEW = 'Necesita revisión';

    /** Statuses where the help desk team should take action. */
    private const ADMIN_ACTION_STATUSES = [
        'Open',
        'En revision',
        self::STATUS_NEEDS_REVIEW,
    ];

    /** Statuses where admin attention is no longer required. */
    private const ADMIN_TERMINAL_STATUSES = [
        'Resuelto',
        'Cerrado',
    ];

    /** @var list<string> */
    public const STATUSES = [
        'Open',
        'Esperando respuesta',
        'En revision',
        self::STATUS_NEEDS_REVIEW,
        'Resuelto',
        'Cerrado',
    ];

    public static function typeValidationRule(): string
    {
        return 'required|in:'.implode(',', self::TYPES);
    }

    public static function statusValidationRule(): string
    {
        return 'required|in:'.implode(',', self::STATUSES);
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
        'assigned_to_user_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
            'closed_at' => 'datetime',
            'assigned_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function replies()
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id');
    }

    public function attachments()
    {
        return $this->hasMany(SupportTicketAttachment::class, 'ticket_id');
    }

    /**
     * Tickets waiting on admin attention: unread user messages or actionable status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeAdminNeedsAttention($query)
    {
        return $query->whereNotIn('status', self::ADMIN_TERMINAL_STATUSES)
            ->where(function ($builder) {
                $builder->where('unread_for_admin', '>', 0)
                    ->orWhereIn('status', self::ADMIN_ACTION_STATUSES);
            });
    }

    public function isAssignedTo(int $adminId): bool
    {
        return $this->assigned_to_user_id !== null
            && (int) $this->assigned_to_user_id === $adminId;
    }

    public function isAssignmentExpired(?int $timeoutMinutes = null): bool
    {
        if ($this->assigned_to_user_id === null || $this->assigned_at === null) {
            return false;
        }

        $minutes = $timeoutMinutes ?? (int) config('carpoolear.support_ticket_assignment_timeout_minutes', 10);

        return $this->assigned_at->lte(now()->subMinutes($minutes));
    }

    public function hasActiveAssignment(?int $timeoutMinutes = null): bool
    {
        return $this->assigned_to_user_id !== null
            && $this->assigned_at !== null
            && ! $this->isAssignmentExpired($timeoutMinutes);
    }

    public static function countForUser(?int $userId): int
    {
        if ($userId === null) {
            return 0;
        }

        return (int) static::query()->where('user_id', $userId)->count();
    }
}
