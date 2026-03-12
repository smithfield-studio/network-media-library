<?php

declare(strict_types=1);

namespace Network_Media_Library\Thumbnail;

use WP_Post;
use WP_REST_Request;

/**
 * Handles saving the post's featured image ID when submitted from a REST request.
 *
 * The Gutenberg editor fires a REST request to `/wp-json/wp/v2/posts/<id>` with `featured_media`.
 * WordPress validates the ID exists locally, which fails for cross-site media. This re-applies
 * the featured image after the post is saved.
 */
class RestSaver {
    public function __construct() {
        // Only hook into pre_post_update during REST requests — classic editor
        // saves are handled by PostSaver via wp_insert_post_data / save_post.
        add_action('rest_api_init', function (): void {
            add_action('pre_post_update', $this->actionPrePostUpdate(...), 10, 2);
        });
    }

    /**
     * Dynamically hooks into the post-type-specific rest_insert_{type} action
     * so we can re-apply the featured image after WP's REST controller rejects it.
     */
    public function actionPrePostUpdate(int $post_id, array $data): void {
        add_action('rest_insert_' . get_post_type($post_id), $this->actionRestInsert(...), 10, 3);
    }

    /**
     * Re-saves the featured image ID for the given post.
     *
     * Fix 3f: Sanitize featured_media with absint().
     */
    public function actionRestInsert(WP_Post $post, WP_REST_Request $request, bool $creating): void {
        $request_json = $request->get_json_params();

        if (array_key_exists('featured_media', $request_json)) {
            update_post_meta($post->ID, '_thumbnail_id', absint($request_json['featured_media']));
        }
    }
}
