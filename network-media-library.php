<?php

/**
 * Network Media Library plugin for WordPress
 *
 * This plugin originally started life as a fork of the Multisite Global Media plugin by Frank Bültge and Dominik
 * Schilling, but has since diverged entirely and retains little of the original functionality. If the Network Media
 * Library plugin doesn't suit your needs, try these alternatives:
 *
 * - [Multisite Global Media](https://github.com/bueltge/multisite-global-media)
 * - [Network Shared Media](https://wordpress.org/plugins/network-shared-media/)
 *
 * @link      https://github.com/humanmade/network-media-library
 *
 * @author    John Blackbourn <john@johnblackbourn.com>, Dominik Schilling <d.schilling@inpsyde.com>, Frank Bültge <f.bueltge@inpsyde.com>
 * @copyright 2019 Human Made
 * @license   https://opensource.org/licenses/MIT
 *
 * Plugin Name: Network Media Library
 * Description: Network Media Library provides a central media library that's shared across all sites on the Multisite network.
 * Network:     true
 * Plugin URI:  https://github.com/humanmade/network-media-library
 * Version:     3.0.0
 * Author:      John Blackbourn, Dominik Schilling, Frank Bültge
 * Author URI:  https://github.com/humanmade/network-media-library/graphs/contributors
 * License:     MIT
 * License URI: ./LICENSE
 * Text Domain: network-media-library
 * Domain Path: /languages
 * Requires PHP: 8.4
 */

declare(strict_types=1);

namespace Network_Media_Library;

defined('ABSPATH') || exit();

if (!is_multisite()) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Default media site ID. In a typical multisite, site 1 is the main site and
 * site 2 is the first subsite — commonly used as a dedicated media library.
 * Override via the 'network-media-library/site_id' filter (e.g. set to 1 if
 * the primary site should be the media source for translations).
 */
const SITE_ID = 2;

/**
 * Returns the ID of the site which acts as the network media library.
 */
function get_site_id(): int {
    $site_id = SITE_ID;

    /**
     * Filters the ID of the site which acts as the network media library.
     *
     * @since 1.0.0
     *
     * @param  int  $site_id  The network media library site ID.
     */
    $site_id = (int) apply_filters('network-media-library/site_id', $site_id);

    /**
     * Legacy filter for compatibility with the Multisite Global Media plugin.
     *
     * @since 0.0.3
     * @deprecated 1.0.0 Use 'network-media-library/site_id' instead.
     *
     * @param  int  $site_id  The network media library site ID.
     */
    $site_id = (int) apply_filters_deprecated('global_media.site_id', [$site_id], '1.0.0', 'network-media-library/site_id');

    return $site_id;
}

/**
 * Returns whether we're currently on the network media library site, regardless of any switching.
 *
 * `$current_blog` can be used to determine the "actual" site as it doesn't change when switching sites.
 */
function is_media_site(): bool {
    return get_site_id() === (int) $GLOBALS['current_blog']->blog_id;
}

// Register all WordPress hooks for media site-switching.
MediaSwitcher::bootstrap();

// ACF integration: resolve image/file field values from the media site.
new ACF\ValueFilter;
new ACF\FieldRendering;

// Featured image persistence: WP deletes cross-site thumbnail IDs during
// wp_insert_post() validation. These classes re-save the ID after save.
new Thumbnail\PostSaver;
new Thumbnail\RestSaver;

// Admin bar indicator: shows which site is the media library.
new AdminBar;

// Site Health integration: verifies the media site exists and is accessible.
new HealthCheck;

// WP-CLI commands: status and diagnostics.
if (defined('WP_CLI') && WP_CLI) {
    CLI::register();
}
