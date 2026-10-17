<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Apache serves /storage/* directly, so these headers cannot be exercised
 * through Laravel. This guards the hosting configuration that ships with the
 * app; it still has to be confirmed on the live server (mod_headers enabled).
 */
class PublicStorageHeadersTest extends TestCase
{
    private function htaccess(): string
    {
        return (string) file_get_contents(public_path('.htaccess'));
    }

    public function test_uploaded_svgs_are_sandboxed_when_served_directly(): void
    {
        $contents = $this->htaccess();

        $this->assertMatchesRegularExpression('/<FilesMatch "\\\\\.\(svg\|svgz\)\$">/', $contents);
        $this->assertMatchesRegularExpression(
            '/Header always set Content-Security-Policy "default-src \'none\';[^"]*\bsandbox\b[^"]*"/',
            $contents
        );
        $this->assertStringNotContainsString('script-src', $contents);
    }

    public function test_static_files_are_served_with_nosniff(): void
    {
        $this->assertStringContainsString('Header always set X-Content-Type-Options "nosniff"', $this->htaccess());
    }

    public function test_front_controller_rewrite_is_preserved(): void
    {
        $contents = $this->htaccess();

        $this->assertStringContainsString('RewriteRule ^ index.php [L]', $contents);
        $this->assertStringContainsString('E=HTTP_AUTHORIZATION:%{HTTP:Authorization}', $contents);
    }
}
