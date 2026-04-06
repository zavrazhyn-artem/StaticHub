<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case En = 'en';
    case Uk = 'uk';
    case Pl = 'pl';
    case Fr = 'fr';
    case De = 'de';
    case Es = 'es';
    case It = 'it';
    case Pt = 'pt';
    case Ru = 'ru';
    case Zh = 'zh';
    case Ko = 'ko';

    public function fullName(): string
    {
        return match ($this) {
            self::En => 'English',
            self::Uk => 'Ukrainian',
            self::Pl => 'Polish',
            self::Fr => 'French',
            self::De => 'German',
            self::Es => 'Spanish',
            self::It => 'Italian',
            self::Pt => 'Portuguese',
            self::Ru => 'Russian',
            self::Zh => 'Chinese',
            self::Ko => 'Korean',
        };
    }

    /**
     * Safely resolve from a locale string, falling back to English.
     */
    public static function fromString(string $locale): self
    {
        return self::tryFrom($locale) ?? self::En;
    }
}
