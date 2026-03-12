<?php

declare(strict_types=1);

namespace Network_Media_Library\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for plugin constants.
 *
 * The plugin file itself requires ABSPATH + is_multisite(), so we test
 * the constant value directly as a known-good baseline.
 */
class SiteIdTest extends TestCase {
    public function test_site_id_constant_default(): void {
        // The constant is defined in the plugin file; without a full WP
        // bootstrap we verify the expected value via source inspection.
        $source = file_get_contents(dirname(__DIR__, 2) . '/network-media-library.php');
        $this->assertStringContainsString('const SITE_ID = 2;', $source);
    }

    public function test_get_site_id_function_exists_in_source(): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/network-media-library.php');
        $this->assertStringContainsString('function get_site_id(): int', $source);
    }

    public function test_is_media_site_function_exists_in_source(): void {
        $source = file_get_contents(dirname(__DIR__, 2) . '/network-media-library.php');
        $this->assertStringContainsString('function is_media_site(): bool', $source);
    }
}
