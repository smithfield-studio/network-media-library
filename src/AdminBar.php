<?php

declare(strict_types=1);

namespace Network_Media_Library;

/**
 * Shows an admin notice on the Media Library page indicating that
 * media is being served from the central network media site.
 */
class AdminBar {
    public function __construct() {
        add_action('admin_notices', $this->showMediaLibraryNotice(...));
    }

    /**
     * Displays an info notice on the upload.php screen for non-media subsites.
     */
    public function showMediaLibraryNotice(): void {
        $screen = get_current_screen();

        if (!$screen || $screen->id !== 'upload') {
            return;
        }

        if (!current_user_can('upload_files')) {
            return;
        }

        if (is_media_site()) {
            return;
        }

        $site_id    = get_site_id();
        $media_site = get_blog_details($site_id);

        if (!$media_site) {
            return;
        }

        // Use MultilingualPress alt language title if available (e.g. "🇬🇧 English").
        $site_name = get_blog_option($site_id, 'multilingualpress_alt_language_title');

        if (empty($site_name)) {
            $site_name = $media_site->blogname;
        }

        printf(
            '<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
            sprintf(
                'Media is shared across the network from <strong>%s</strong> (ID: %d).',
                esc_html($site_name),
                $site_id,
            ),
            esc_url(get_admin_url($site_id, 'upload.php')),
            'View media library',
        );
    }
}
