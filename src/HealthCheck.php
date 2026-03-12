<?php

declare(strict_types=1);

namespace Network_Media_Library;

/**
 * Adds a Site Health check that verifies the configured media library
 * site exists and is accessible on the network.
 */
class HealthCheck {
    public function __construct() {
        add_filter('site_status_tests', $this->registerTest(...));
    }

    /**
     * Registers our test with the Site Health screen.
     */
    public function registerTest(array $tests): array {
        $tests['direct']['network_media_library'] = [
            'label' => 'Network Media Library',
            'test'  => $this->runTest(...),
        ];

        return $tests;
    }

    /**
     * Checks that the configured media site ID is valid and accessible.
     */
    public function runTest(): array {
        $site_id = get_site_id();
        $site    = get_blog_details($site_id);

        if (!$site) {
            return [
                'label'       => 'Network Media Library site not found',
                'status'      => 'critical',
                'badge'       => ['label' => 'Media', 'color' => 'red'],
                'description' => sprintf(
                    '<p>The Network Media Library is configured to use site ID <strong>%d</strong>, but this site does not exist on the network. Media uploads and display will not work correctly.</p><p>Set the correct site ID using the <code>network-media-library/site_id</code> filter.</p>',
                    $site_id,
                ),
                'test'        => 'network_media_library',
            ];
        }

        if ($site->archived || $site->deleted) {
            return [
                'label'       => 'Network Media Library site is inactive',
                'status'      => 'critical',
                'badge'       => ['label' => 'Media', 'color' => 'red'],
                'description' => sprintf(
                    '<p>The Network Media Library site "<strong>%s</strong>" (ID %d) is archived or deleted. Media uploads and display will not work correctly.</p>',
                    esc_html($site->blogname),
                    $site_id,
                ),
                'test'        => 'network_media_library',
            ];
        }

        return [
            'label'       => 'Network Media Library is configured correctly',
            'status'      => 'good',
            'badge'       => ['label' => 'Media', 'color' => 'blue'],
            'description' => sprintf(
                '<p>The Network Media Library is using "<strong>%s</strong>" (site ID %d) as the central media library.</p>',
                esc_html($site->blogname),
                $site_id,
            ),
            'test'        => 'network_media_library',
        ];
    }
}
