<?php

declare(strict_types=1);

namespace Network_Media_Library\Tests\Unit;

use Network_Media_Library\Thumbnail\RestSaver;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for input sanitization.
 *
 * Verifies that REST endpoint handlers properly sanitize input.
 */
class SanitizationTest extends TestCase {
    public function test_rest_saver_action_rest_insert_uses_absint(): void {
        // Verify the method exists and calls absint on featured_media.
        $method = new ReflectionMethod(RestSaver::class, 'actionRestInsert');

        $this->assertTrue($method->isPublic());
        $this->assertSame(3, $method->getNumberOfParameters());

        // Verify the source code contains absint sanitization.
        $source = file_get_contents((string) $method->getFileName());
        $this->assertStringContainsString('absint($request_json[\'featured_media\'])', $source);
    }
}
