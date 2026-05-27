<?php

namespace STS\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use STS\Models\SupportTicketAttachment;
use STS\Services\SupportTicketService;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait StreamsSupportTicketAttachments
{
    private function streamSupportTicketAttachment(
        SupportTicketService $supportTicketService,
        int $ticketId,
        int $attachmentId,
    ): StreamedResponse|JsonResponse {
        $attachment = $supportTicketService->findTicketAttachment($ticketId, $attachmentId);
        if ($attachment === null) {
            return response()->json(['error' => 'Attachment not found'], 404);
        }

        return $this->streamSupportTicketAttachmentModel($supportTicketService, $attachment);
    }

    private function streamSupportTicketAttachmentModel(
        SupportTicketService $supportTicketService,
        SupportTicketAttachment $attachment,
    ): StreamedResponse|JsonResponse {
        $disk = $supportTicketService->diskForAttachmentPath($attachment->path);
        if ($disk === null) {
            return response()->json(['error' => 'Attachment not found'], 404);
        }

        $mime = $attachment->mime ?: $disk->mimeType($attachment->path) ?: 'application/octet-stream';

        return response()->stream(function () use ($disk, $attachment) {
            $stream = $disk->readStream($attachment->path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
        ]);
    }
}
