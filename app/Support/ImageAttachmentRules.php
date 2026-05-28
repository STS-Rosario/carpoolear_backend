<?php

namespace STS\Support;

final class ImageAttachmentRules
{
    /** @var string Laravel validation rule for a single uploaded image attachment */
    public const FILE = 'file|mimes:jpg,jpeg,png,webp|max:10240';

    /** @var list<string> */
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function requiredImage(): string
    {
        return 'required|'.self::FILE;
    }
}
