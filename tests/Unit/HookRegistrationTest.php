<?php

declare(strict_types=1);

namespace Network_Media_Library\Tests\Unit;

use Network_Media_Library\MediaSwitcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests that MediaSwitcher::bootstrap() registers all expected hooks.
 *
 * Uses source inspection to verify hook registrations without needing
 * a full WordPress environment.
 */
class HookRegistrationTest extends TestCase {
    private string $source;

    protected function setUp(): void {
        $method       = new ReflectionMethod(MediaSwitcher::class, 'bootstrap');
        $this->source = (string) file_get_contents((string) $method->getFileName());
    }

    #[DataProvider('expectedHookProvider')]
    public function test_hook_is_registered(string $hook_name): void {
        $pattern = sprintf(
            '/add_(action|filter)\s*\(\s*\'%s\'/',
            preg_quote($hook_name, '/'),
        );

        $this->assertMatchesRegularExpression(
            $pattern,
            $this->source,
            "Hook '{$hook_name}' should be registered in MediaSwitcher using add_action() or add_filter().",
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function expectedHookProvider(): array {
        return [
            // Upload actions
            'load-async-upload.php'          => ['load-async-upload.php'],
            'wp_ajax_upload-attachment'      => ['wp_ajax_upload-attachment'],

            // Attachment AJAX actions
            'wp_ajax_get-attachment'           => ['wp_ajax_get-attachment'],
            'wp_ajax_save-attachment'          => ['wp_ajax_save-attachment'],
            'wp_ajax_save-attachment-compat'   => ['wp_ajax_save-attachment-compat'],
            'wp_ajax_set-attachment-thumbnail' => ['wp_ajax_set-attachment-thumbnail'],

            // Image editing
            'wp_ajax_image-editor'          => ['wp_ajax_image-editor'],
            'wp_ajax_imgedit-preview'       => ['wp_ajax_imgedit-preview'],
            'wp_ajax_crop-image'            => ['wp_ajax_crop-image'],

            // Query and insert
            'wp_ajax_query-attachments'         => ['wp_ajax_query-attachments'],
            'wp_ajax_send-attachment-to-editor' => ['wp_ajax_send-attachment-to-editor'],

            // Filters
            'map_meta_cap'                  => ['map_meta_cap'],
            'wp_get_attachment_image_src'   => ['wp_get_attachment_image_src'],
            'wp_calculate_image_srcset'     => ['wp_calculate_image_srcset'],
            'post_gallery'                  => ['post_gallery'],
            'admin_post_thumbnail_html'     => ['admin_post_thumbnail_html'],
            'wp_prepare_attachment_for_js'  => ['wp_prepare_attachment_for_js'],
            'rest_pre_dispatch'             => ['rest_pre_dispatch'],
            'xmlrpc_call'                   => ['xmlrpc_call'],
            'the_content'                   => ['the_content'],
            'wp_get_attachment_url'         => ['wp_get_attachment_url'],
            'wp_get_attachment_metadata'    => ['wp_get_attachment_metadata'],
            'wp_get_attachment_image'       => ['wp_get_attachment_image'],
            'get_custom_logo'               => ['get_custom_logo'],
            'upload_dir'                    => ['upload_dir'],
        ];
    }

    public function test_wp_filter_content_tags_is_removed(): void {
        $this->assertStringContainsString(
            "remove_filter('the_content', 'wp_filter_content_tags')",
            $this->source,
            'wp_filter_content_tags should be removed from the_content to avoid double processing.',
        );
    }
}
