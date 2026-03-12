<?php

declare(strict_types=1);

namespace Network_Media_Library;

use WP_CLI;

/**
 * WP-CLI commands for the Network Media Library.
 *
 * Registered only when WP-CLI is active. Provides commands for
 * status checking and media operations that respect the shared library.
 */
class CLI {
    /**
     * Register WP-CLI commands.
     */
    public static function register(): void {
        WP_CLI::add_command('network-media-library', self::class);
    }

    /**
     * Display the current Network Media Library configuration and status.
     *
     * ## EXAMPLES
     *
     *     wp network-media-library status
     *
     * @subcommand status
     */
    public function status(array $args, array $assoc_args): void {
        $site_id = get_site_id();
        $site    = get_blog_details($site_id);

        if (!$site) {
            WP_CLI::error(sprintf(
                'Media site ID %d does not exist on this network.',
                $site_id,
            ));

            return; // WP_CLI::error() exits, but PHPStan doesn't know that.
        }

        $current_site_id = get_current_blog_id();
        $is_media_site   = is_media_site();

        WP_CLI::log('Network Media Library Status');
        WP_CLI::log('---');
        WP_CLI::log(sprintf('Media site:     %s (ID %d)', html_entity_decode($site->blogname), $site_id));
        WP_CLI::log(sprintf('Media site URL: %s', $site->siteurl));
        WP_CLI::log(sprintf('Current site:   ID %d%s', $current_site_id, $is_media_site ? ' (this is the media site)' : ''));
        WP_CLI::log(sprintf('Site status:    %s', $site->archived || $site->deleted ? 'INACTIVE' : 'Active'));

        // Count attachments on the media site.
        switch_to_blog($site_id);
        $count = wp_count_posts('attachment');
        restore_current_blog();

        $total = 0;

        foreach ((array) $count as $status_count) {
            $total += (int) $status_count;
        }

        WP_CLI::log(sprintf('Attachments:    %s', number_format($total)));
        WP_CLI::success('Media library is operational.');
    }

    /**
     * Verify that media references on the current site point to valid attachments.
     *
     * Checks featured images (_thumbnail_id) on the current site and reports
     * any that reference non-existent attachments on the media site.
     *
     * ## OPTIONS
     *
     * [--fix]
     * : Remove invalid thumbnail references.
     *
     * ## EXAMPLES
     *
     *     wp network-media-library verify-thumbnails
     *     wp network-media-library verify-thumbnails --fix
     *
     * @subcommand verify-thumbnails
     */
    public function verifyThumbnails(array $args, array $assoc_args): void {
        $fix = (bool) ($assoc_args['fix'] ?? false);

        global $wpdb;

        // Get all posts with featured images on the current site.
        $results = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value > 0",
        );

        if (empty($results)) {
            WP_CLI::success('No featured images found on this site.');

            return;
        }

        WP_CLI::log(sprintf('Checking %d featured image references...', count($results)));

        // Switch to media site to check if attachments exist.
        switch_to_blog(get_site_id());

        $invalid = [];

        foreach ($results as $row) {
            $attachment = get_post((int) $row->meta_value);

            if (!$attachment || $attachment->post_type !== 'attachment') {
                $invalid[] = $row;
            }
        }

        restore_current_blog();

        if ($invalid === []) {
            WP_CLI::success(sprintf('All %d featured image references are valid.', count($results)));

            return;
        }

        WP_CLI::warning(sprintf('Found %d invalid featured image references.', count($invalid)));

        foreach ($invalid as $row) {
            if ($fix) {
                delete_post_meta((int) $row->post_id, '_thumbnail_id');
                WP_CLI::log(sprintf('  Removed invalid thumbnail %d from post %d', $row->meta_value, $row->post_id));
            } else {
                WP_CLI::log(sprintf('  Post %d references non-existent attachment %d', $row->post_id, $row->meta_value));
            }
        }

        if (!$fix) {
            WP_CLI::log('Run with --fix to remove invalid references.');
        } else {
            WP_CLI::success(sprintf('Removed %d invalid references.', count($invalid)));
        }
    }
}
