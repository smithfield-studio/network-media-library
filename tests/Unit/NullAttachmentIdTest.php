<?php

declare(strict_types=1);

namespace Network_Media_Library\Tests\Unit;

use Network_Media_Library\MediaSwitcher;
use PHPUnit\Framework\TestCase;

/**
 * Tests that image filters which receive the attachment ID exactly as passed to core accept an empty ID.
 *
 * image_downsize(), wp_get_attachment_image_src() and wp_get_attachment_image() pass the ID through
 * uncast, so themes calling them with an empty field value send null. A strict int|string type there
 * throws a TypeError and takes the page down.
 */
class NullAttachmentIdTest extends TestCase {
    public function test_image_downsize_accepts_null(): void {
        $this->assertFalse((new MediaSwitcher)->filterImageDownsize(false, null, 'full'));
    }

    public function test_attachment_image_src_accepts_null(): void {
        $this->assertFalse((new MediaSwitcher)->filterAttachmentImageSrc(false, null, 'full', false));
    }

    public function test_attachment_image_accepts_null(): void {
        $this->assertSame('', (new MediaSwitcher)->filterAttachmentImage('', null, 'full', false, []));
    }
}
