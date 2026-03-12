<?php

declare(strict_types=1);

namespace Network_Media_Library;

use WP_Admin_Bar;

/**
 * Adds a small indicator to the admin bar showing which site is
 * the network media library. Helpful for debugging and awareness.
 */
class AdminBar {
    public function __construct() {
        add_action('admin_bar_menu', $this->addMediaSiteIndicator(...), 100);
    }

    /**
     * Adds a "Media: Site Name" node to the admin bar.
     */
    public function addMediaSiteIndicator(WP_Admin_Bar $admin_bar): void {
        if (!is_admin() || !current_user_can('upload_files')) {
            return;
        }

        $media_site = get_blog_details(get_site_id());

        if (!$media_site) {
            return;
        }

        $is_current = is_media_site();

        $admin_bar->add_node([
            'id'    => 'network-media-library',
            'title' => sprintf(
                '<span class="ab-icon dashicons dashicons-admin-media" style="font-size:16px;margin-top:4px;"></span> %s%s',
                esc_html($media_site->blogname),
                $is_current ? ' (current)' : '',
            ),
            'href'  => get_admin_url(get_site_id(), 'upload.php'),
            'meta'  => [
                'title' => sprintf(
                    'Network Media Library — Site ID %d%s',
                    get_site_id(),
                    $is_current ? ' (you are on the media site)' : '',
                ),
            ],
        ]);
    }
}
