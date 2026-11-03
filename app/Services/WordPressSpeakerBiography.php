<?php

namespace App\Services;

use RuntimeException;

class WordPressSpeakerBiography
{
    // Limits for this import workflow.
    private const MAX_INPUT_BYTES = 262144;
    private const MAX_OUTPUT_BYTES = 65535;

    public function sanitize(string $html): string
    {
        if (strlen($html) > self::MAX_INPUT_BYTES) {
            throw new RuntimeException(
                'Speaker biography source exceeds the 256 KiB limit. '
                . 'Shorten it in WordPress, then reload the preview.'
            );
        }

        if (!mb_check_encoding($html, 'UTF-8')) {
            throw new RuntimeException(
                'Speaker biography contains invalid UTF-8 text. '
                . 'Correct it in WordPress, then reload the preview.'
            );
        }

        if (trim($html) === '') {
            return '';
        }

        // Preserve common text boundaries before discarding HTML.
        // This regex is for presentation, not security.
        $text = preg_replace(
            '/<\s*br\b[^>]*>|<\/\s*(?:p|div|li|blockquote|h[1-6]|tr)\s*>/i',
            "\n",
            $html
        );

        if ($text === null) {
            throw new RuntimeException(
                'Speaker biography conversion failed. '
                . 'No biography should be saved from this preview.'
            );
        }

        // Strip all source tags, without an allowed-tags list.
        $text = strip_tags($text);

        // Decode before the final escaping step.
        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $text = str_replace(
            ["\r\n", "\r", "\u{00A0}"],
            ["\n", "\n", ' '],
            $text
        );

        // Remove control characters, preserving tabs and line breaks.
        $text = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $text
        );

        if ($text === null) {
            throw new RuntimeException(
                'Speaker biography text normalization failed.'
            );
        }

        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException(
                'The non-empty WordPress biography produced no usable '
                . 'text. Review it before importing.'
            );
        }

        // Security boundary: escape all source-derived text.
        $escaped = htmlspecialchars(
            $text,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5,
            'UTF-8',
            true
        );

        // Only application-generated tags are included.
        $result = '<p>' . nl2br($escaped, false) . '</p>';

        // Escaping can increase the stored byte length.
        if (strlen($result) > self::MAX_OUTPUT_BYTES) {
            throw new RuntimeException(
                'Converted speaker biography exceeds the 65,535-byte '
                . 'storage limit. Shorten it in WordPress.'
            );
        }

        return $result;
    }
}