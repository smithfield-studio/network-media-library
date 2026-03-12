<?php

declare(strict_types=1);

namespace Network_Media_Library\Thumbnail;

use WP_Post;

/**
 * Handles saving the post's featured image ID.
 *
 * This is required because `wp_insert_post()` checks the validity of the featured image
 * ID before saving it to post meta, and deletes it if it's not an image/audio/video. These
 * hooks temporarily store and re-save the selected featured image ID.
 */
class PostSaver {
    /**
     * Featured image IDs keyed by post ID.
     *
     * @var array<int, int>
     */
    protected array $thumbnail_ids = [];

    public function __construct() {
        add_filter('wp_insert_post_data', $this->filterInsertPostData(...), 10, 2);
        add_action('save_post', $this->actionSavePost(...), 10, 3);
    }

    /**
     * Temporarily stores the featured image ID when the post is saved.
     *
     * @param  array  $data  An array of slashed post data.
     * @param  array  $postarr  An array of sanitized, but otherwise unmodified post data.
     */
    public function filterInsertPostData(array $data, array $postarr): array {
        if (!empty($postarr['_thumbnail_id'])) {
            $this->thumbnail_ids[$postarr['ID']] = (int) $postarr['_thumbnail_id'];
        }

        return $data;
    }

    /**
     * Re-saves the featured image ID for the given post.
     */
    public function actionSavePost(int $post_id, WP_Post $post, bool $update): void {
        if (!empty($this->thumbnail_ids[$post->ID]) && $this->thumbnail_ids[$post->ID] !== -1) {
            update_post_meta($post->ID, '_thumbnail_id', $this->thumbnail_ids[$post->ID]);
        }
    }
}
