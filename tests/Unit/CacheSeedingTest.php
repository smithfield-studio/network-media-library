<?php

declare(strict_types=1);

namespace Network_Media_Library\Tests\Unit;

use Network_Media_Library\MediaSwitcher;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests that media site attachments cached on a subsite never outlive the request.
 *
 * The copies sit in core's per-site posts and post_meta groups, so under a persistent object cache they
 * would otherwise shadow a subsite post later given the same ID.
 */
class CacheSeedingTest extends TestCase {
    private function body(string $method): string {
        $reflection = new ReflectionMethod(MediaSwitcher::class, $method);
        $lines      = explode("\n", (string) file_get_contents((string) $reflection->getFileName()));

        return implode("\n", array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    }

    public function test_attachment_is_added_not_set(): void {
        // wp_cache_add() respects wp_suspend_cache_addition(); wp_cache_set() would not.
        $this->assertStringContainsString("wp_cache_add(\$id, \$attachment, 'posts'", $this->body('cacheLocally'));
        $this->assertStringNotContainsString("wp_cache_set(\$id, \$attachment, 'posts'", $this->body('cacheLocally'));
    }

    public function test_seeded_copies_are_removed_at_shutdown(): void {
        $this->assertStringContainsString("add_action('shutdown'", $this->body('cacheLocally'));
        $this->assertStringContainsString("wp_cache_delete(\$id, 'posts')", $this->body('forgetSeeded'));
        $this->assertStringContainsString("wp_cache_delete(\$id, 'post_meta')", $this->body('forgetSeeded'));
    }
}
