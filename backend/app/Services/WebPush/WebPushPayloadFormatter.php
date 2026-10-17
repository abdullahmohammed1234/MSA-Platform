<?php

namespace App\Services\WebPush;

use App\Notifications\DirectMessageNotification;
use App\Notifications\SupportReplyNotification;
use App\Notifications\SupportStatusUpdatedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class WebPushPayloadFormatter
{
    /**
     * Format a privacy-preserving push notification payload for the given notification.
     *
     * @param Notification $notification
     * @param mixed $notifiable
     * @return array{title: string, body: string, url: string, tag: string, icon: string, badge: string, timestamp: int}
     */
    public static function format(Notification $notification, $notifiable): array
    {
        $tag = 'msa-general';
        $icon = '/favicon.svg';
        $badge = '/favicon.svg';
        $title = 'SFU MSA Connect';
        $body = 'You have a new update in SFU MSA Connect.';
        $targetUrl = '/app/notifications';

        // 1. Direct Messaging — Strictly Privacy-Preserving
        if ($notification instanceof DirectMessageNotification) {
            $title = 'New Direct Message';
            $body = 'You have received a new private message.';
            $convUuid = $notification->conversation->uuid ?? '';
            $targetUrl = $convUuid ? "/app/messages/{$convUuid}" : '/app/messages';
            $tag = 'dm-' . ($convUuid ?: 'chat');
        }
        // 2. Support Reply — Strictly Privacy-Preserving
        elseif ($notification instanceof SupportReplyNotification) {
            $title = 'Support Inquiry Update';
            $ref = $notification->inquiry->reference_code ?? '';
            $body = $ref ? "An update was posted to inquiry #{$ref}." : 'An update was posted to your support inquiry.';
            $targetUrl = $ref ? "/app/support/{$ref}" : '/app/support';
            $tag = 'support-' . ($ref ?: 'inquiry');
        }
        // 3. Support Status Updated — Strictly Privacy-Preserving
        elseif ($notification instanceof SupportStatusUpdatedNotification) {
            $title = 'Support Inquiry Status Changed';
            $ref = $notification->inquiry->reference_code ?? '';
            $body = $ref ? "The status of inquiry #{$ref} has been updated." : 'Your inquiry status has been updated.';
            $targetUrl = $ref ? "/app/support/{$ref}" : '/app/support';
            $tag = 'support-' . ($ref ?: 'inquiry');
        }
        // 4. Custom toWebPush() method on notification if declared
        elseif (method_exists($notification, 'toWebPush')) {
            $custom = $notification->toWebPush($notifiable);
            if (is_array($custom)) {
                $title = $custom['title'] ?? $title;
                $body = $custom['body'] ?? $body;
                $targetUrl = $custom['url'] ?? $targetUrl;
                $tag = $custom['tag'] ?? $tag;
            }
        }
        // 5. Standard toArray() fallback
        elseif (method_exists($notification, 'toArray')) {
            $data = $notification->toArray($notifiable);
            if (is_array($data)) {
                $rawTitle = $data['title'] ?? '';
                $rawUrl = $data['action_url'] ?? $data['url'] ?? '/app/notifications';
                
                if (!empty($rawTitle) && is_string($rawTitle)) {
                    $title = Str::limit(strip_tags($rawTitle), 80);
                }
                $targetUrl = is_string($rawUrl) ? $rawUrl : '/app/notifications';
            }
        }

        // Validate and sanitize navigation target URL
        $safeUrl = self::sanitizeTargetUrl($targetUrl);

        return [
            'title' => $title,
            'body' => $body,
            'url' => $safeUrl,
            'tag' => $tag,
            'icon' => $icon,
            'badge' => $badge,
            'timestamp' => (int) (now()->getTimestamp() * 1000),
        ];
    }

    /**
     * Validate navigation target against strict application-relative allowlist.
     */
    public static function sanitizeTargetUrl(?string $url): string
    {
        if (empty($url) || !is_string($url)) {
            return '/app/notifications';
        }

        $trimmed = trim($url);

        // Reject external schemes (http://, https://, javascript:, data:, file:)
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#i', $trimmed)) {
            return '/app/notifications';
        }

        // Reject protocol-relative URLs (//evil.com) and backslashes
        if (str_starts_with($trimmed, '//') || str_contains($trimmed, '\\')) {
            return '/app/notifications';
        }

        // Must start with '/'
        if (!str_starts_with($trimmed, '/')) {
            return '/app/notifications';
        }

        // Check against allowed prefixes in config or defaults
        $allowedPrefixes = config('webpush.allowed_navigation_prefixes', ['/app/', '/']);
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($trimmed, $prefix)) {
                return $trimmed;
            }
        }

        return '/app/notifications';
    }
}
