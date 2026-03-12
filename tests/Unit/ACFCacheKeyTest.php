<?php

declare(strict_types=1);

namespace Network_Media_Library\Tests\Unit;

use Network_Media_Library\ACF\ValueFilter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests that ACF field value caching is keyed correctly to avoid
 * stale data in repeater fields (bug fix 3e).
 *
 * When the same field name appears multiple times in a repeater,
 * the cache key must include the post ID to differentiate them.
 */
class ACFCacheKeyTest extends TestCase {
    public function test_load_value_cache_key_includes_post_id(): void {
        $method = new ReflectionMethod(ValueFilter::class, 'filterLoadValue');
        $source = $this->getMethodSource($method);

        $this->assertStringContainsString(
            "\$field['name'] . '_' . \$post_id",
            $source,
            'ACF value cache key must include post_id to avoid repeater collisions.',
        );
    }

    public function test_format_value_cache_key_includes_post_id(): void {
        $method = new ReflectionMethod(ValueFilter::class, 'filterFormatValue');
        $source = $this->getMethodSource($method);

        $this->assertStringContainsString(
            "\$field['name'] . '_' . \$post_id",
            $source,
            'ACF format value cache key must include post_id to match load value key.',
        );
    }

    private function getMethodSource(ReflectionMethod $method): string {
        $file  = (string) file_get_contents((string) $method->getFileName());
        $start = $method->getStartLine();
        $end   = $method->getEndLine();
        $lines = array_slice(explode("\n", $file), $start - 1, $end - $start + 1);

        return implode("\n", $lines);
    }
}
