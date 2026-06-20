<?php

namespace App\Services\Security;

class SanitizationService
{
    /**
     * Sanitizes rich text HTML content to prevent XSS.
     * Leaves safe tags (b, i, u, p, br, ul, ol, li, h1, h2, h3, a, img, blockquote) but strips javascript, styles, inline event handlers.
     */
    public function sanitizeHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // 1. Remove script tags and their contents
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

        // 2. Remove style tags and their contents to prevent CSS injection
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // 3. Remove HTML comments
        $html = preg_replace('/<!--(.*?)-->/is', '', $html);

        // 4. Remove inline event handlers (e.g., onload, onclick, onerror)
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/is', '', $html);

        // 5. Remove javascript:, data:, vbscript: links inside href/src attributes
        $html = preg_replace_callback('/(href|src)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/is', function ($matches) {
            $attribute = $matches[1];
            $value = trim($matches[2], "\"' ");
            if (preg_match('/^(javascript|data|vbscript):/i', $value)) {
                return $attribute . '="#"';
            }
            return $matches[0];
        }, $html);

        // 6. Remove frame, iframe, object, embed, applet tags completely
        $html = preg_replace('/<(iframe|frame|object|embed|applet)\b[^>]*>(.*?)<\/\1>/is', '', $html);
        $html = preg_replace('/<(iframe|frame|object|embed|applet)\b[^>]*>/is', '', $html);

        return trim($html);
    }

    /**
     * Sanitizes plain text input by stripping all HTML/XML tags and encoding characters.
     */
    public function sanitizeString(string $string): string
    {
        return htmlspecialchars(strip_tags($string), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Deep sanitization of input array.
     */
    public function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                // If it looks like HTML, sanitize it as HTML; otherwise, sanitize string.
                if (preg_match('/<[^>]*>/', $value)) {
                    $data[$key] = $this->sanitizeHtml($value);
                } else {
                    $data[$key] = $this->sanitizeString($value);
                }
            }
        }
        return $data;
    }
}
