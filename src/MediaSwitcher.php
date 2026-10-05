<?php

declare(strict_types=1);

namespace Network_Media_Library;

use WP_Post;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Core site-switching logic and hook registrations for the Network Media Library.
 *
 * All media-related AJAX, REST, and XML-RPC requests are intercepted and
 * redirected to the designated media site using WordPress's switch_to_blog().
 * This allows subsites to share a single, centralized media library.
 */
class MediaSwitcher {
    /**
     * Register all hooks. Called once from the main plugin file.
     *
     * Priority 0 is used on action hooks to ensure site-switching happens
     * before any other plugin or theme logic runs for that request.
     */
    public static function bootstrap(): void {
        $self = new self;

        // Redirect uploads to the media site so all files are stored centrally.
        add_action('load-async-upload.php', self::switchToMediaSite(...), 0);
        add_action('wp_ajax_upload-attachment', self::switchToMediaSite(...), 0);

        // Clear post_id from the request so uploads aren't attached to a
        // post that only exists on the local site (not the media site).
        add_action('load-async-upload.php', self::preventAttaching(...), 0);
        add_action('wp_ajax_upload-attachment', self::preventAttaching(...), 0);

        // Allow access to the "List" mode on the Media screen.
        add_action('parse_request', $self->handleParseRequest(...), 0);

        // Allow attachment details to be fetched and saved.
        add_action('wp_ajax_get-attachment', self::switchToMediaSite(...), 0);
        add_action('wp_ajax_save-attachment', self::switchToMediaSite(...), 0);
        add_action('wp_ajax_save-attachment-compat', self::switchToMediaSite(...), 0);
        add_action('wp_ajax_set-attachment-thumbnail', self::switchToMediaSite(...), 0);

        // Allow images to be edited and previewed.
        add_action('wp_ajax_image-editor', self::switchToMediaSite(...), 0);
        add_action('wp_ajax_imgedit-preview', self::switchToMediaSite(...), 0);
        add_action('wp_ajax_crop-image', self::switchToMediaSite(...), 0);

        // Allow attachments to be queried and inserted.
        add_action('wp_ajax_query-attachments', self::switchToMediaSite(...), 0);
        add_action('wp_ajax_send-attachment-to-editor', self::switchToMediaSite(...), 0);
        // Uses [$self, ...] because it remove/re-adds itself inside allowMediaLibraryAccess.
        add_filter('map_meta_cap', [$self, 'allowMediaLibraryAccess'], 10, 4);

        // Support for the WP User Avatars plugin.
        add_action('wp_ajax_assign_wp_user_avatars_media', self::switchToMediaSite(...), 0);

        // Image sizing: resolved on the media site before core looks the attachment up on the subsite.
        add_filter('image_downsize', $self->filterImageDownsize(...), 10, 3);

        // Attachment image src.
        add_filter('wp_get_attachment_image_src', $self->filterAttachmentImageSrc(...), 999, 4);

        // Srcset sources.
        add_filter('wp_calculate_image_srcset', $self->filterImageSrcset(...), 999, 5);

        // Gallery shortcode — uses [$self, ...] because it remove/re-adds itself.
        add_filter('post_gallery', [$self, 'filterPostGallery'], 0, 3);

        // Featured image HTML.
        add_filter('admin_post_thumbnail_html', $self->adminPostThumbnailHtml(...), 99, 3);

        // Attachment data for JS.
        add_filter('wp_prepare_attachment_for_js', $self->filterAttachmentForJs(...), 0, 3);

        // REST API media routing.
        add_filter('rest_pre_dispatch', $self->filterRestPreDispatch(...), 0, 3);

        // XML-RPC media routing.
        add_action('xmlrpc_call', $self->handleXmlrpcCall(...), 0);

        // Content image responsive handling.
        // Remove WP's default filter and replace with ours that switches to the media site.
        remove_filter('the_content', 'wp_filter_content_tags');
        add_filter('the_content', $self->makeContentImagesResponsive(...));

        // Attachment URL — themes/plugins calling wp_get_attachment_url() directly.
        add_filter('wp_get_attachment_url', $self->filterAttachmentUrl(...), 999, 2);

        // Attachment metadata — themes/plugins calling wp_get_attachment_metadata() directly.
        add_filter('wp_get_attachment_metadata', $self->filterAttachmentMetadata(...), 999, 2);

        // Custom logo — re-generate the logo HTML from the media site context
        // so has_custom_logo() and get_custom_logo() work on subsites.
        add_filter('get_custom_logo', $self->filterCustomLogo(...), 0);

        // Attachment image output — safety net for srcset. If wp_get_attachment_image()
        // produces an <img> without srcset (because metadata wasn't available locally),
        // re-generate the entire tag from the media site context.
        add_filter('wp_get_attachment_image', $self->filterAttachmentImage(...), 999, 5);

        // Upload dir — when plugins call wp_upload_dir() to manually build URLs,
        // return the media site's upload path so URLs resolve correctly.
        add_filter('upload_dir', $self->filterUploadDir(...), 0);
    }

    /**
     * Switches the current site ID to the network media library site ID.
     *
     * Accepts and returns a passthrough $value so it can be used as both
     * an action callback (no return needed) and a filter callback (returns
     * the original value unchanged after switching).
     */
    public static function switchToMediaSite(mixed $value = null): mixed {
        switch_to_blog(get_site_id());

        return $value;
    }

    /**
     * Prevents attempts to attach an attachment to a post ID during upload.
     */
    public static function preventAttaching(): void {
        unset($_REQUEST['post_id']);
    }

    /**
     * Handles the parse_request action for the Media Library "List" mode screen.
     *
     * Switches to the media site for the main query, then restores the original
     * site before and after the loop so WP renders the admin UI from the local site.
     */
    public function handleParseRequest(): void {
        if (is_media_site()) {
            return;
        }

        // Fix 3b: Null-safe get_current_screen() check.
        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();

        if (!$screen || $screen->id !== 'upload') {
            return;
        }

        self::switchToMediaSite();

        // Restore local site context after the query is prepared but before
        // the admin page renders, then re-switch for the actual post loop
        // so attachment data is fetched from the media site.
        add_filter('posts_pre_query', static function ($value) {
            restore_current_blog();

            return $value;
        });

        add_action('loop_start', self::switchToMediaSite(...), 0);
        add_action('loop_stop', 'restore_current_blog', 999);
    }

    /**
     * Filters the image src result so its URL points to the network media library site.
     *
     * @param  array|false  $image  Either array with src, width & height, icon src, or false.
     * @param  mixed  $attachment_id  Image attachment ID, as passed to wp_get_attachment_image_src() (may be null).
     * @param  string|array  $size  Size of image.
     * @param  bool  $icon  Whether the image should be treated as an icon.
     */
    public function filterAttachmentImageSrc(array|false $image, mixed $attachment_id, string|array $size, bool $icon): array|false {
        // Static guard prevents infinite recursion: wp_get_attachment_image_src()
        // below triggers this same filter, so we bail on re-entry.
        static $switched = false;
        static $cache    = [];

        if ($switched) {
            return $image;
        }

        if (!is_numeric($attachment_id) || (int) $attachment_id <= 0 || is_media_site()) {
            return $image;
        }

        $cache_key = $attachment_id . ':' . (is_array($size) ? implode('x', $size) : $size) . ':' . ($icon ? '1' : '0');

        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        self::switchToMediaSite();

        $switched          = true;
        $image             = wp_get_attachment_image_src((int) $attachment_id, $size, $icon);
        $switched          = false;
        $cache[$cache_key] = $image;

        restore_current_blog();

        return $image;
    }

    /**
     * Sizes images on the media site before core looks them up on the subsite.
     *
     * On a subsite, image_downsize(), wp_get_attachment_image() and plugins such as Safe SVG look the
     * attachment up in the subsite's own tables. It isn't there, and WordPress doesn't cache a post that
     * wasn't found, so each of those lookups queries again (several per image) before the filters here fetch
     * the result from the media site. The alt text also comes out empty, as it's read from local post meta.
     *
     * This returns the media site's image_downsize() result, then caches the media site's attachment and meta
     * under the same ID on the subsite, so the image's remaining lookups (alt text, MIME type, metadata) are
     * served from the cache. That only happens when the subsite has no post of its own with that ID:
     * wp_attachment_is_image() looks the ID up just before this filter runs, so an empty posts cache means it
     * doesn't exist.
     *
     * @param  array|false  $downsize  False, unless an earlier filter has already resolved the image.
     * @param  mixed  $attachment_id  Image attachment ID, as passed to image_downsize() (may be null).
     * @param  string|array  $size  Size of image.
     */
    public function filterImageDownsize(array|false $downsize, mixed $attachment_id, string|array $size): array|false {
        // Static guard prevents infinite recursion: image_downsize() below triggers this same filter.
        static $switched = false;
        static $cache    = [];

        if ($switched || $downsize !== false || !is_numeric($attachment_id) || (int) $attachment_id <= 0 || is_media_site()) {
            return $downsize;
        }

        // Always sized on the media site: image size settings are per site, so a subsite would give the
        // media site's files its own dimensions.
        $id        = (int) $attachment_id;
        $cache_key = $id . ':' . (is_array($size) ? implode('x', $size) : $size);

        if (!isset($cache[$cache_key])) {
            self::switchToMediaSite();

            $switched          = true;
            $attachment        = get_post($id);
            $is_attachment     = $attachment instanceof WP_Post && $attachment->post_type === 'attachment';
            $cache[$cache_key] = [
                'downsize'   => image_downsize($id, $size),
                'attachment' => $is_attachment ? $attachment : null,
                'meta'       => $is_attachment ? (get_post_meta($id) ?: []) : [],
            ];
            $switched          = false;

            restore_current_blog();
        }

        ['downsize' => $downsize, 'attachment' => $attachment, 'meta' => $meta] = $cache[$cache_key];

        if ($attachment !== null && wp_cache_get($id, 'posts') === false) {
            self::cacheLocally($id, $attachment, $meta);
        }

        return $downsize;
    }

    /**
     * Loads attachments from the media site and caches them on this subsite in one go.
     *
     * Themes can call this with the attachment IDs a page is about to output, so lookups of those images
     * on the subsite (alt text, MIME type, metadata) are served from the cache with a single switch to the
     * media site. IDs the subsite has a post of its own for are left alone.
     *
     * @param  array<int|string>  $ids  Attachment IDs.
     */
    public static function primeAttachments(array $ids): void {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return;
        }

        if (is_media_site()) {
            _prime_post_caches($ids, false, true);

            return;
        }

        // Load any posts of the subsite's own with these IDs, so they aren't shadowed.
        _prime_post_caches($ids, false, false);
        $ids = array_values(array_filter($ids, static fn (int $id): bool => wp_cache_get($id, 'posts') === false));

        if ($ids === []) {
            return;
        }

        self::switchToMediaSite();

        _prime_post_caches($ids, false, true);
        $found = [];

        foreach ($ids as $id) {
            $attachment = get_post($id);

            if ($attachment instanceof WP_Post && $attachment->post_type === 'attachment') {
                $meta       = wp_cache_get($id, 'post_meta');
                $found[$id] = [$attachment, is_array($meta) ? $meta : []];
            }
        }

        restore_current_blog();

        foreach ($found as $id => [$attachment, $meta]) {
            self::cacheLocally($id, $attachment, $meta);
        }
    }

    /**
     * Whether an attachment with this ID is already cached on the current site.
     */
    private static function resolvesLocally(int $id): bool {
        $post = wp_cache_get($id, 'posts');

        return is_object($post) && ($post->post_type ?? '') === 'attachment';
    }

    /**
     * Caches the media site's attachment and meta under the same ID on the current site.
     *
     * With a persistent object cache the copies expire after a few minutes, so alt text edited on the media
     * site isn't held on subsites for long.
     *
     * @param  array<string, mixed>  $meta  Raw post meta, as held in the post_meta cache group.
     */
    private static function cacheLocally(int $id, WP_Post $attachment, array $meta): void {
        $expire = wp_using_ext_object_cache() ? 5 * MINUTE_IN_SECONDS : 0;

        wp_cache_set($id, $attachment, 'posts', $expire);
        wp_cache_set($id, $meta, 'post_meta', $expire);
    }

    /**
     * Filters an image's 'srcset' sources.
     *
     * Fix 3c: Use proper URL resolution instead of brittle regex.
     *
     * @param  array|false  $sources  One or more arrays of source data, or false from a prior filter.
     * @param  array  $size_array  Requested width and height values.
     * @param  string  $image_src  The 'src' of the image.
     * @param  array  $image_meta  Image meta data.
     * @param  int  $attachment_id  Image attachment ID or 0.
     */
    public function filterImageSrcset(array|false $sources, array $size_array, string $image_src, array $image_meta, int|string $attachment_id): array|false {
        if (!$sources || is_media_site() || (int) $attachment_id === 0) {
            return $sources;
        }

        // Get the media site's upload base URL so we can reconstruct srcset URLs.
        // Subsites have /wp-content/uploads/sites/N/ paths; the media site may not.
        // Cached per-request since the base URL never changes.
        static $base_url = null;

        if ($base_url === null) {
            switch_to_blog(get_site_id());
            $base_url = wp_get_upload_dir()['baseurl'];
            restore_current_blog();
        }

        foreach ($sources as $key => $source) {
            // Extract just the filename (e.g. "photo-300x200.jpg") from the
            // current URL, then prepend the media site's base URL + subdirectory
            // from the attachment metadata (e.g. "2024/03").
            $filename = wp_basename($source['url']);

            if (!empty($image_meta['file'])) {
                $subdir               = dirname((string) $image_meta['file']);
                $sources[$key]['url'] = $subdir !== '.' ? $base_url . '/' . $subdir . '/' . $filename : $base_url . '/' . $filename;
            }
        }

        return $sources;
    }

    /**
     * Filters the default gallery shortcode output so it renders media from the media site.
     *
     * Temporarily removes itself to prevent recursion when gallery_shortcode()
     * triggers the same filter, then re-registers after rendering.
     */
    public function filterPostGallery(string $output, array $attr, int $instance): string {
        remove_filter('post_gallery', [$this, 'filterPostGallery'], 0);

        self::switchToMediaSite();
        $output = gallery_shortcode($attr);
        restore_current_blog();

        add_filter('post_gallery', [$this, 'filterPostGallery'], 0, 3);

        return $output;
    }

    /**
     * Filters the admin post thumbnail HTML markup.
     *
     * Fix 3d: Null-safe get_post() check.
     */
    public function adminPostThumbnailHtml(string $content, int $post_id, ?int $thumbnail_id): string {
        // Static guard prevents recursion — _wp_post_thumbnail_html() re-triggers this filter.
        static $switched = false;

        if ($switched) {
            return $content;
        }

        if (!$thumbnail_id) {
            return $content;
        }

        switch_to_blog(get_site_id());
        $switched = true;

        $post_type_check = get_post_type($thumbnail_id);

        // If the thumbnail ID doesn't correspond to an attachment on the media site,
        // it's stale/invalid — clear it from post meta so the editor doesn't show a broken image.
        if ($post_type_check !== 'attachment') {
            $switched = false;
            restore_current_blog();
            update_post_meta($post_id, '_thumbnail_id', null);

            return $content;
        }

        // $thumbnail_id is passed instead of post_id to avoid warning messages of nonexistent post object.
        $content  = _wp_post_thumbnail_html($thumbnail_id, $thumbnail_id);
        $switched = false;
        restore_current_blog();

        // Fix 3d: Null-safe get_post() check.
        $post = get_post($post_id);

        if (!$post) {
            return $content;
        }

        $post_type_object = get_post_type_object($post->post_type);

        if (!$post_type_object) {
            return $content;
        }

        $has_thumbnail_url = get_the_post_thumbnail_url($post_id) !== false;

        if ($has_thumbnail_url === false) {
            $search  = 'class="thickbox"></a>';
            $replace = 'class="thickbox">' . esc_html($post_type_object->labels->set_featured_image) . '</a>';
        } else {
            $search  = '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail"></a></p>';
            $replace = '<p class="hide-if-no-js"><a href="#" id="remove-post-thumbnail">' . esc_html($post_type_object->labels->remove_featured_image) . '</a></p>';
        }

        return str_replace($search, $replace, $content);
    }

    /**
     * Filters the attachment data prepared for JavaScript.
     *
     * @param  array  $response  Array of prepared attachment data.
     * @param  WP_Post  $attachment  Attachment object.
     * @param  array|bool  $meta  Array of attachment meta data.
     */
    public function filterAttachmentForJs(array $response, WP_Post $attachment, array|bool $meta): array {
        if (is_media_site()) {
            return $response;
        }

        // Prevent media from being deleted from subsites. Deleting an attachment from
        // a subsite would actually delete a local post with that ID, not the media site
        // attachment. Removing the nonce hides the "Delete" button in the media modal.
        unset($response['nonces']['delete']);

        return $response;
    }

    /**
     * Filters the pre-dispatch value of REST API requests to switch to media site when querying media.
     */
    public function filterRestPreDispatch(mixed $result, WP_REST_Server $server, WP_REST_Request $request): mixed {
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return $result;
        }

        if (is_media_site()) {
            return $result;
        }

        $media_routes = [
            '/wp/v2/media',
            '/regenerate-thumbnails/',
        ];

        foreach ($media_routes as $route) {
            if (str_starts_with($request->get_route(), $route)) {
                // Clear the parent post param — the post only exists on the local site.
                $request->set_param('post', null);
                self::switchToMediaSite();

                break;
            }
        }

        return $result;
    }

    /**
     * Handles XML-RPC calls for media methods.
     */
    public function handleXmlrpcCall(string $name): void {
        $media_methods = [
            'metaWeblog.newMediaObject',
            'wp.getMediaItem',
            'wp.getMediaLibrary',
        ];

        if (in_array($name, $media_methods, true)) {
            self::switchToMediaSite();
        }
    }

    /**
     * Filters 'img' elements in post content to add 'srcset' and 'sizes' attributes.
     *
     * WP's built-in wp_filter_content_tags() only looks up attachments on the current site.
     * We remove WP's default filter in bootstrap() and replace it with this, which switches
     * to the media site first so attachment lookups resolve correctly.
     */
    public function makeContentImagesResponsive(string $content): string {
        // Nothing to do without images, so skip the switch (excerpts and text-only content).
        if (!str_contains($content, '<img') || is_media_site()) {
            return $content;
        }

        self::switchToMediaSite();
        $content = wp_filter_content_tags($content);
        restore_current_blog();

        return $content;
    }

    /**
     * Apply the current site's `upload_files` capability to the network media site.
     *
     * When a user on subsite A uploads to the shared media library (site B), WordPress
     * checks if they have `upload_files` on site B — which they typically don't.
     * This filter temporarily switches back to the original site to check permissions
     * there, granting access if the user can upload on their own site.
     *
     * @param  string[]  $caps  Capabilities for meta capability.
     * @param  string  $cap  Capability name.
     * @param  int  $user_id  The user ID.
     * @param  array  $args  Context for the capability check.
     * @return string[]
     */
    public function allowMediaLibraryAccess(array $caps, string $cap, int $user_id, array $args): array {
        if (get_current_blog_id() !== get_site_id()) {
            return $caps;
        }

        if (!in_array($cap, ['edit_post', 'upload_files'], true)) {
            return $caps;
        }

        if ($cap === 'edit_post') {
            $content = get_post($args[0]);

            if (!$content || $content->post_type !== 'attachment') {
                return $caps;
            }

            // Substitute edit_post because the attachment exists only on the network media site.
            $post_type_object = get_post_type_object($content->post_type);

            if (!$post_type_object) {
                return $caps;
            }

            $cap = $post_type_object->cap->create_posts;
        }

        /*
         * By the time this function is called, we've already switched context to the network media site.
         * Switch back to the original site -- where the initial request came in from.
         */
        switch_to_blog((int) $GLOBALS['current_blog']->blog_id);

        // Remove ourselves to prevent infinite recursion — user_can() triggers
        // map_meta_cap again. Re-added immediately after the check.
        remove_filter('map_meta_cap', [$this, 'allowMediaLibraryAccess'], 10);
        $user_has_permission = user_can($user_id, $cap);
        add_filter('map_meta_cap', [$this, 'allowMediaLibraryAccess'], 10, 4);

        restore_current_blog();

        // 'exist' is a primitive cap that any logged-in user has — effectively grants access.
        return $user_has_permission ? ['exist'] : $caps;
    }

    /**
     * Filters the attachment URL to resolve from the media site.
     *
     * Many themes and plugins call wp_get_attachment_url() directly. Without
     * this filter, they get an empty string because the attachment ID doesn't
     * exist on the current (local) site.
     *
     * @param  string  $url  The attachment URL.
     * @param  int  $attachment_id  Attachment post ID.
     */
    public function filterAttachmentUrl(string $url, int|string $attachment_id): string {
        // Static guard prevents infinite recursion.
        static $switched = false;
        static $cache    = [];

        if ($switched || is_media_site()) {
            return $url;
        }

        // If we already got a valid URL, don't re-fetch. This happens when
        // the attachment exists locally (e.g. during an upload switch context).
        if ($url !== '' && !str_contains($url, '?attachment_id=')) {
            return $url;
        }

        if (isset($cache[$attachment_id])) {
            return $cache[$attachment_id];
        }

        self::switchToMediaSite();

        $switched                = true;
        $media_url               = wp_get_attachment_url((int) $attachment_id);
        $switched                = false;
        $cache[$attachment_id]   = $media_url ?: $url;

        restore_current_blog();

        return $cache[$attachment_id];
    }

    /**
     * Filters attachment metadata to resolve from the media site.
     *
     * wp_get_attachment_metadata() calls get_post_meta() which returns ''
     * (empty string) when the attachment doesn't exist on the local site.
     * WordPress then passes this to the filter. We detect any empty/falsy
     * value and re-fetch from the media site where the attachment lives.
     *
     * @param  mixed  $data  Attachment metadata — array when found, '' or false when not.
     * @param  int  $attachment_id  Attachment post ID.
     */
    public function filterAttachmentMetadata(mixed $data, int|string $attachment_id): mixed {
        static $switched = false;
        static $cache    = [];

        if ($switched || is_media_site()) {
            return $data;
        }

        // If metadata already resolved (non-empty array), don't re-fetch.
        if (!empty($data)) {
            return $data;
        }

        if (isset($cache[$attachment_id])) {
            return $cache[$attachment_id];
        }

        self::switchToMediaSite();

        $switched                = true;
        $data                    = wp_get_attachment_metadata((int) $attachment_id);
        $switched                = false;
        $cache[$attachment_id]   = $data;

        restore_current_blog();

        return $data;
    }

    /**
     * Filters the attachment image HTML to ensure srcset is present.
     *
     * When wp_get_attachment_image() runs on a subsite, it may produce an <img>
     * with a correct src (via our filterAttachmentImageSrc) but no srcset
     * (because metadata wasn't resolved in time). This safety net detects
     * missing srcset and re-generates the full <img> from the media site.
     *
     * When the attachment resolves on this site (filterImageDownsize() caches the media site's copy here),
     * the local output is already complete, so it's kept rather than rendered a second time.
     *
     * @param  string  $html  HTML img element or empty string on failure.
     * @param  mixed  $attachment_id  Image attachment ID, as passed to wp_get_attachment_image() (may be null).
     * @param  string|array  $size  Requested image size.
     * @param  bool  $icon  Whether it's a mime-type icon.
     * @param  array  $attr  Array of attribute values for the image markup.
     */
    public function filterAttachmentImage(string $html, mixed $attachment_id, string|array $size, bool $icon, string|array $attr): string {
        static $switched = false;
        static $cache    = [];

        // Only intervene if the HTML is missing srcset but has a src.
        if ($switched || $html === '' || str_contains($html, 'srcset=') || !is_numeric($attachment_id) || is_media_site()) {
            return $html;
        }

        if (self::resolvesLocally((int) $attachment_id)) {
            return $html;
        }

        // The attributes are part of the key: the same image can be output with a different class or alt.
        $cache_key = $attachment_id . ':' . (is_array($size) ? implode('x', $size) : $size) . ':' . ($icon ? '1' : '0') . ':' . md5(serialize($attr));

        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        self::switchToMediaSite();

        $switched   = true;
        $media_html = wp_get_attachment_image((int) $attachment_id, $size, $icon, is_array($attr) ? $attr : []);
        $switched   = false;

        restore_current_blog();

        $result             = $media_html !== '' ? $media_html : $html;
        $cache[$cache_key]  = $result;

        return $result;
    }

    /**
     * Filters the custom logo HTML to resolve images from the media site.
     *
     * get_custom_logo() calls wp_get_attachment_image() which goes through
     * our wp_get_attachment_image_src filter. However, it also checks if the
     * attachment exists locally via get_post(). This filter re-generates
     * the logo HTML with the media site context if the default output is empty.
     */
    public function filterCustomLogo(string $html): string {
        static $switched = false;

        if ($switched || is_media_site()) {
            return $html;
        }

        // If WP already generated valid HTML, our image_src filter handled it.
        if ($html !== '') {
            return $html;
        }

        $custom_logo_id = get_theme_mod('custom_logo');

        if (empty($custom_logo_id)) {
            return $html;
        }

        // Re-generate the logo HTML from the media site context.
        self::switchToMediaSite();
        $switched = true;

        $image = wp_get_attachment_image(
            $custom_logo_id,
            'full',
            false,
            [
                'class'   => 'custom-logo',
                'loading' => false,
            ],
        );

        $switched = false;
        restore_current_blog();

        if (empty($image)) {
            return $html;
        }

        return sprintf(
            '<a href="%1$s" class="custom-logo-link" rel="home">%2$s</a>',
            esc_url(home_url('/')),
            $image,
        );
    }

    /**
     * Filters the upload directory to return the media site's upload path.
     *
     * Some plugins call wp_upload_dir() to build attachment URLs manually
     * instead of using wp_get_attachment_url(). Without this filter, those
     * URLs point to the local site's upload directory, which is wrong.
     *
     * Only applies on frontend requests and non-upload admin contexts to
     * avoid redirecting actual file uploads to the wrong location (uploads
     * are already handled via the AJAX/REST hooks).
     *
     * @param  array  $dirs  Upload directory data.
     */
    public function filterUploadDir(array $dirs): array {
        static $switched = false;
        static $cache    = [];

        if ($switched || is_media_site()) {
            return $dirs;
        }

        // Don't filter during actual uploads — those are already redirected
        // to the media site via the AJAX/REST action hooks.
        if ($this->isUploadContext()) {
            return $dirs;
        }

        // wp_upload_dir() runs this filter on every call (core caches before the filter, not after),
        // and the media site's directories don't change within a request, so switch once per media site.
        // Keyed on the site ID, as calls before a theme's network-media-library/site_id filter is added
        // resolve against the default site.
        $site_id = get_site_id();

        if (isset($cache[$site_id])) {
            return $cache[$site_id];
        }

        self::switchToMediaSite();
        $switched         = true;
        $cache[$site_id]  = wp_upload_dir();
        $switched         = false;
        restore_current_blog();

        return $cache[$site_id];
    }

    /**
     * Detects whether the current request is an upload operation.
     *
     * During uploads, the AJAX/REST hooks already switch to the media site,
     * so we should NOT also filter upload_dir or it would double-switch.
     */
    private function isUploadContext(): bool {
        if (wp_doing_ajax()) {
            $action = $_REQUEST['action'] ?? '';

            return in_array($action, [
                'upload-attachment',
                'image-editor',
                'imgedit-preview',
                'crop-image',
            ], true);
        }

        // async-upload.php
        return str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', 'async-upload.php');
    }
}
