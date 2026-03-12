<?php

declare(strict_types=1);

namespace Network_Media_Library\ACF;

use function Network_Media_Library\get_site_id;
use function Network_Media_Library\is_media_site;

/**
 * Filters ACF field values so image/file fields resolve from the network media site.
 */
class ValueFilter {
    /**
     * Stores resolved field values.
     *
     * Fix 3e: Keyed by field name + post ID to avoid stale data in repeaters.
     *
     * @var array<string, mixed>
     */
    protected array $value = [];

    public function __construct() {
        $field_types = [
            'image',
            'file',
        ];

        foreach ($field_types as $type) {
            add_filter("acf/load_value/type={$type}", $this->filterLoadValue(...), 0, 3);
            add_filter("acf/format_value/type={$type}", $this->filterFormatValue(...), 9999, 3);
        }
    }

    /**
     * Filters the return value when using field retrieval functions in ACF.
     *
     * @param  mixed  $value  The field value.
     * @param  int|string|false  $post_id  The post ID for this value (false when no post context).
     * @param  array  $field  The field array.
     * @return mixed The updated value.
     */
    public function filterLoadValue(mixed $value, int|string|false $post_id, array $field): mixed {
        $image = $value;

        // On the frontend of non-media subsites, switch to the media site to
        // resolve attachment URLs/data, since the attachment only exists there.
        if (!is_media_site() && !is_admin()) {
            switch_to_blog(get_site_id());

            $image = match ($field['return_format']) {
                'url'   => wp_get_attachment_url($value),
                'array' => acf_get_attachment($value),
                default => $image,
            };

            restore_current_blog();
        } elseif (!is_admin()) {
            // On the media site's frontend, attachments exist locally — no switch needed.
            $image = match ($field['return_format']) {
                'url'   => wp_get_attachment_url($value),
                'array' => acf_get_attachment($value),
                default => $image,
            };
        }
        // In admin context, ACF handles its own attachment resolution — skip.

        // Fix 3e: Key by field name + post ID to handle repeater fields correctly.
        $cache_key                = $field['name'] . '_' . $post_id;
        $this->value[$cache_key]  = $image;

        return $image;
    }

    /**
     * Returns the pre-resolved value from filterLoadValue, bypassing ACF's
     * default format step which would try to look up the attachment on the
     * wrong site.
     *
     * @param  mixed  $value  The field value.
     * @param  int|string|false  $post_id  The post ID for this value (false when no post context).
     * @param  array  $field  The field array.
     * @return mixed The updated value.
     */
    public function filterFormatValue(mixed $value, int|string|false $post_id, array $field): mixed {
        $cache_key = $field['name'] . '_' . $post_id;

        return $this->value[$cache_key] ?? $value;
    }
}
