<?php

namespace STS\Support;

final class SupportTicketAttachmentRules
{
    public const DEFAULT_MAX = 6;

    public const HARD_CAP = 20;

    public static function maxAttachments(): int
    {
        $raw = config('carpoolear.support_ticket_max_attachments');
        if ($raw === null || $raw === false || $raw === '') {
            $configured = self::DEFAULT_MAX;
        } else {
            $configured = (int) $raw;
        }

        return max(1, min(self::HARD_CAP, $configured));
    }

    /** @return string Laravel validation rule for the attachments array */
    public static function attachmentsArrayRule(): string
    {
        return 'nullable|array|max:'.self::maxAttachments();
    }
}