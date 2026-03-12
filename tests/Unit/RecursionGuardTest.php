<?php

declare(strict_types=1);

namespace Network_Media_Library\Tests\Unit;

use Network_Media_Library\MediaSwitcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests that all filter methods which switch to the media site have
 * static recursion guards to prevent infinite loops.
 *
 * When a filter callback calls a WP function that triggers the same filter
 * (e.g. filterAttachmentImageSrc calls wp_get_attachment_image_src),
 * a static $switched guard is essential to prevent infinite recursion.
 */
class RecursionGuardTest extends TestCase {
    #[DataProvider('methodsRequiringGuardProvider')]
    public function test_method_has_recursion_guard(string $method_name): void {
        $method = new ReflectionMethod(MediaSwitcher::class, $method_name);
        $file   = (string) file_get_contents((string) $method->getFileName());

        // Extract just this method's body.
        $start = $method->getStartLine();
        $end   = $method->getEndLine();
        $lines = array_slice(explode("\n", $file), $start - 1, $end - $start + 1);
        $body  = implode("\n", $lines);

        $this->assertStringContainsString(
            'static $switched',
            $body,
            "Method {$method_name} switches to media site and must have a static \$switched recursion guard.",
        );
    }

    /**
     * Methods that call switch_to_blog() and then invoke a WP function
     * that could re-trigger the same filter.
     *
     * @return array<string, array{string}>
     */
    public static function methodsRequiringGuardProvider(): array {
        return [
            'filterAttachmentImageSrc' => ['filterAttachmentImageSrc'],
            'adminPostThumbnailHtml'   => ['adminPostThumbnailHtml'],
            'filterAttachmentUrl'      => ['filterAttachmentUrl'],
            'filterAttachmentMetadata' => ['filterAttachmentMetadata'],
            'filterAttachmentImage'    => ['filterAttachmentImage'],
            'filterCustomLogo'         => ['filterCustomLogo'],
            'filterUploadDir'          => ['filterUploadDir'],
        ];
    }
}
