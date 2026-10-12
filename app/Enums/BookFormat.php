<?php

namespace App\Enums;

enum BookFormat: string
{
    case Pdf = 'pdf';
    case Epub = 'epub';

    public static function fromMimeOrExtension(string $extension): self
    {
        return self::from(strtolower($extension));
    }
}
