<?php

namespace STS\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use STS\Models\SupportTicketAttachment;
use STS\Models\SupportTicketReply;
use STS\Support\ImageAttachmentRules;

class SupportTicketAttachmentStorage
{
    public const PATH_PREFIX = 'support_tickets';

    public function __construct(
        private readonly ImageUploadValidator $imageUploadValidator,
        private readonly HeicToJpegConverter $heicToJpegConverter,
        private readonly ImageExifOrientationNormalizer $exifOrientationNormalizer,
    ) {}

    public function storeForReply(UploadedFile $file, int $ticketId, int $replyId, int $userId): SupportTicketAttachment
    {
        $result = $this->imageUploadValidator->validate(
            $file,
            'attachments',
            ImageAttachmentRules::ALLOWED_MIMES,
            ImageAttachmentRules::ALLOWED_EXTENSIONS,
        );
        if (! ($result['valid'] ?? false)) {
            throw ValidationException::withMessages($result['errors'] ?? [
                'attachments' => ['Invalid image upload.'],
            ]);
        }

        $basePath = self::PATH_PREFIX.'/'.$ticketId.'/'.$replyId;
        $path = $this->storeImageFile($file, $basePath);

        return SupportTicketAttachment::create([
            'reply_id' => $replyId,
            'ticket_id' => null,
            'user_id' => $userId,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $this->mimeForPath($path, $file),
            'size_bytes' => (int) Storage::disk('local')->size($path),
        ]);
    }

    public function findForTicket(int $ticketId, int $attachmentId): ?SupportTicketAttachment
    {
        $attachment = SupportTicketAttachment::query()->find($attachmentId);
        if ($attachment === null) {
            return null;
        }

        if ($attachment->reply_id !== null) {
            $ownsReply = SupportTicketReply::query()
                ->whereKey($attachment->reply_id)
                ->where('ticket_id', $ticketId)
                ->exists();

            return $ownsReply ? $attachment : null;
        }

        return (int) $attachment->ticket_id === $ticketId ? $attachment : null;
    }

    public function purgeForTicket(int $ticketId): int
    {
        $replyIds = SupportTicketReply::query()
            ->where('ticket_id', $ticketId)
            ->pluck('id');

        $attachments = SupportTicketAttachment::query()
            ->where(function ($query) use ($ticketId, $replyIds) {
                $query->where('ticket_id', $ticketId);
                if ($replyIds->isNotEmpty()) {
                    $query->orWhereIn('reply_id', $replyIds);
                }
            })
            ->get();

        foreach ($attachments as $attachment) {
            $this->deleteStoredFile($attachment->path);
            $attachment->delete();
        }

        return $attachments->count();
    }

    public function diskForPath(string $path): ?Filesystem
    {
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local');
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public');
        }

        return null;
    }

    private function storeImageFile(UploadedFile $file, string $basePath): string
    {
        $jpegContent = $this->heicToJpegConverter->convert($file);
        $content = $jpegContent ?? file_get_contents($file->getRealPath());
        if ($content === false) {
            throw ValidationException::withMessages([
                'attachments' => ['Could not read uploaded image.'],
            ]);
        }

        $content = $this->exifOrientationNormalizer->normalize($content);
        $extension = $jpegContent !== null ? 'jpg' : $file->getClientOriginalExtension();
        $path = $basePath.'/'.Str::random(40).'.'.$extension;
        Storage::disk('local')->put($path, $content);

        return $path;
    }

    private function deleteStoredFile(string $path): void
    {
        $disk = $this->diskForPath($path);
        if ($disk !== null) {
            $disk->delete($path);
        }
    }

    private function mimeForPath(string $path, UploadedFile $file): string
    {
        return Storage::disk('local')->mimeType($path)
            ?: $file->getMimeType()
            ?: 'application/octet-stream';
    }
}
