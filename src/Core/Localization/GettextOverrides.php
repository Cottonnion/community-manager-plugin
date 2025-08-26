<?php

declare(strict_types=1);

namespace LABGENZ_CM\Core\Localization;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles gettext overrides for English language only.
 */
class GettextOverrides {

    /**
     * Initialize hooks.
     */
    public static function init(): void {
        add_filter( 'gettext', [ self::class, 'override_texts' ], 20, 3 );
    }

    /**
     * Override specific text strings for English only.
     *
     * @param string $translated_text The translated text.
     * @param string $text The original text.
     * @param string $domain The text domain.
     * @return string
     */
    public static function override_texts( string $translated_text, string $text, string $domain ): string {
        if ( get_locale() !== 'en_US' ) {
            return $translated_text;
        }

        $replacements = [
            'Organizer'        => 'Group Leader',
            'organizer'        => 'group leader',
            'Organizers'       => 'Group Leaders',
            'organizers'       => 'group leaders',
        ];

        return $replacements[$text] ?? $translated_text;
    }
}