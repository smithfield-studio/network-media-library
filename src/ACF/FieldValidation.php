<?php

declare(strict_types=1);

namespace Network_Media_Library\ACF;

use Network_Media_Library\MediaSwitcher;

/**
 * Validates image and gallery field values against the media site.
 *
 * ACF 6.8.7+ rejects any value that fails wp_attachment_is_image(), and on a
 * subsite the attachment only exists on the media site.
 */
class FieldValidation {
    public function __construct() {
        foreach (['image', 'gallery'] as $type) {
            add_filter("acf/validate_value/type={$type}", MediaSwitcher::switchToMediaSite(...), 0);
            add_filter("acf/validate_value/type={$type}", $this->restoreCurrentBlog(...), PHP_INT_MAX);
        }
    }

    public function restoreCurrentBlog(mixed $valid): mixed {
        restore_current_blog();

        return $valid;
    }
}
