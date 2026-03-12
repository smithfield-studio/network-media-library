<?php

/**
 * PHPUnit bootstrap file.
 *
 * Requires a WordPress test environment. Set WP_TESTS_DIR to point to
 * your WordPress test library (wp-phpunit). For unit tests that don't
 * need the full WP environment, we load the autoloader only.
 */
$plugin_dir = dirname(__DIR__);

// Load Composer autoloader.
require_once $plugin_dir . '/vendor/autoload.php';

// If WP test suite is available, bootstrap it.
$wp_tests_dir = getenv('WP_TESTS_DIR') ?: rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';

if (file_exists($wp_tests_dir . '/includes/functions.php')) {
    // Load WP test suite functions.
    require_once $wp_tests_dir . '/includes/functions.php';

    // Load the plugin.
    tests_add_filter('muplugins_loaded', function () use ($plugin_dir) {
        require $plugin_dir . '/network-media-library.php';
    });

    // Start up the WP testing environment.
    require $wp_tests_dir . '/includes/bootstrap.php';
}
