<?php

declare(strict_types=1);

namespace Network_Media_Library\ACF;

use function Network_Media_Library\get_site_id;

/**
 * Handles switching to the media site when rendering ACF file field controls.
 */
class FieldRendering {
    /**
     * Whether the previous field triggered a switch to the central media site.
     */
    protected bool $switched = false;

    public function __construct() {
        add_action('acf/render_field', $this->maybeRestoreCurrentBlog(...), -999);
        add_action('acf/render_field/type=file', $this->maybeSwitchToMediaSite(...), 0);
    }

    /**
     * Switches to the central media site.
     */
    public function maybeSwitchToMediaSite(): void {
        $this->switched = true;
        switch_to_blog(get_site_id());
    }

    /**
     * Switches back to the current site if the previous field triggered a switch.
     */
    public function maybeRestoreCurrentBlog(): void {
        if ($this->switched) {
            restore_current_blog();
        }

        $this->switched = false;
    }
}
