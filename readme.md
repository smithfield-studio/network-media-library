# Network Media Library

Network Media Library is a WordPress Multisite plugin that provides a central media library shared across all sites on the network.

This is a maintained fork of [humanmade/network-media-library](https://github.com/humanmade/network-media-library) (last meaningful upstream release: 2019). This fork includes bug fixes, modern PHP tooling, and a class-based architecture.

## How it works

All media uploads are transparently redirected to a single designated "media site" on the network. Every other site queries and displays media from that central library. Nothing is copied, cloned, or synchronised — there's one attachment record and one file per upload.

This is particularly useful for multilingual multisite setups (e.g. WPML or Polylang with separate sites per language), where the primary language site acts as the media source for all translations.

## Requirements

- **PHP:** >= 8.4
- **WordPress:** >= 6.0
- **Multisite:** Required

## Installation

```sh
composer require smithfield-studio/network-media-library
```

The plugin should either be installed as a mu-plugin or network activated. It cannot be activated on individual sites.

### Configuration

Site ID `2` is used by default as the central media library. Configure it via the `network-media-library/site_id` filter:

```php
// Use the primary site (site 1) as the media library — common for translation setups.
add_filter('network-media-library/site_id', fn () => 1);
```

## Usage

Use the media library as you normally would. All media is transparently stored on and served from the central media site.

Attachments can only be deleted from within the admin area of the central media site.

### Loading attachments in bulk

On a subsite, each image's attachment is fetched from the media site the first time it's output. If you know the attachment IDs a page will output (from block attributes or ACF fields), load them in one go first:

```php
Network_Media_Library\MediaSwitcher::primeAttachments($attachment_ids);
```

## Compatibility

Works with all built-in WordPress media functionality: uploading, cropping, inserting into posts, featured images, galleries, site icons/logos, background/header images, audio/image widgets, and regular media management.

Supports the block editor, classic editor, REST API, XML-RPC, and all standard Ajax media endpoints.

### Explicitly supported plugins

- [Advanced Custom Fields (ACF)](https://wordpress.org/plugins/advanced-custom-fields/) — image and file fields, including within repeaters
- [Regenerate Thumbnails](https://wordpress.org/plugins/regenerate-thumbnails/)
- [WP User Avatars](https://wordpress.org/plugins/wp-user-avatars/)

### Confirmed compatible

- [BuddyPress](https://wordpress.org/plugins/buddypress/)
- [Extended CPTs](https://github.com/johnbillion/extended-cpts)
- [Stream](https://wordpress.org/plugins/stream/)
- [User Profile Picture](https://wordpress.org/plugins/metronet-profile-picture/)

## Changes from upstream

This fork includes the following fixes and improvements over `humanmade/network-media-library`:

- **Fixed double content image processing** — upstream removes `wp_make_content_images_responsive` which no longer exists in WP 5.5+, causing duplicate `srcset` processing. Now correctly removes `wp_filter_content_tags`.
- **Fixed `get_current_screen()` fatal** — null check added (from upstream PR #91).
- **Fixed brittle srcset URL rewriting** — replaced blind regex (`/sites\/\d+\//`) with proper URL resolution via `wp_get_upload_dir()`.
- **Fixed `get_post()` null safety** — `admin_post_thumbnail_html` no longer fatals on missing posts.
- **Fixed ACF repeater field cache collision** — value cache now keyed by field name + post ID, preventing stale data when the same field name appears multiple times in a repeater.
- **Fixed unsanitized REST input** — `featured_media` from REST requests is now sanitized with `absint()`.
- **Converted anonymous closures to named methods** — all hook callbacks are now removable by third-party code.
- **Restructured into classes** with PSR-4 autoloading.
- **Modern tooling** — Laravel Pint, Rector, PHPStan level 6, PHPUnit 11.
- **Fixed repeated attachment lookups on subsites**: images are now sized on the media site through `image_downsize`, before core looks the attachment up in the subsite's own tables. The media site's attachment and meta are cached under the same ID on the subsite for the rest of the request (with a persistent object cache they're removed at shutdown), so alt text, MIME type and metadata lookups no longer miss and query again for every image. Subsite images also get their alt text from the media library instead of an empty `alt`.
- **Fewer switches to the media site**: `upload_dir` is resolved once per request instead of on every `wp_upload_dir()` call, and content without images skips the switch in the responsive images filter. `MediaSwitcher::primeAttachments()` loads a page's attachments with a single switch.
- **Added `wp_get_attachment_url` filter** — themes/plugins calling `wp_get_attachment_url()` directly now resolve from the media site.
- **Added `wp_get_attachment_metadata` filter** — themes/plugins calling `wp_get_attachment_metadata()` directly now resolve from the media site.
- **Added custom logo support** — `has_custom_logo()` and `get_custom_logo()` now work correctly on subsites by re-generating logo HTML from the media site context.
- **Added `upload_dir` filter** — plugins that call `wp_upload_dir()` to manually build attachment URLs now get the media site's upload path.
- **Added media library notice** — shows an info notice on the Media Library page indicating which site media is shared from, with MultilingualPress support.
- **Added Site Health check** — verifies the configured media site exists and is accessible.
- **Added WP-CLI commands** — `wp network-media-library status` for config info, `wp network-media-library verify-thumbnails` to find/fix broken featured image references.

## Development

### Setup

```sh
composer install
```

### Commands

| Command | Description |
|---|---|
| `composer format` | Format code with Laravel Pint |
| `composer format:check` | Check formatting without changes |
| `composer phpstan` | Run PHPStan static analysis (level 6) |
| `composer rector` | Run Rector automated refactoring |
| `composer rector:dry` | Preview Rector changes without applying |
| `composer test` | Run PHPUnit tests |

### Architecture

```
network-media-library.php          Bootstrap, constants, get_site_id(), is_media_site()
src/
  MediaSwitcher.php                Core site-switching logic + all hook registrations
  AdminBar.php                     Media library page notice
  HealthCheck.php                  Site Health integration
  CLI.php                          WP-CLI commands (status, verify-thumbnails)
  ACF/
    ValueFilter.php                ACF image/file field value resolution
    FieldRendering.php             ACF admin field rendering (file fields)
  Thumbnail/
    PostSaver.php                  Featured image persistence (classic editor)
    RestSaver.php                  Featured image persistence (Gutenberg/REST)
```

## WP-CLI

```sh
# Show media library configuration and status
wp network-media-library status

# Check for broken featured image references on the current site
wp network-media-library verify-thumbnails

# Fix broken references (removes invalid _thumbnail_id meta)
wp network-media-library verify-thumbnails --fix
```

## License

MIT. See [LICENSE](./LICENSE).

## Credits

Maintained by [Smithfield Studio](https://smithfield.studio/).

Huge thanks to [John Blackbourn](https://johnblackbourn.com/), [Dominik Schilling](https://dominikschilling.de/), and [Frank Bültge](https://bueltge.de) for creating and developing this plugin. Their original work at [Human Made](https://humanmade.com/) and [Inpsyde](https://inpsyde.com/) — building on Frank and Dominik's earlier [Multisite Global Media](https://github.com/bueltge/multisite-global-media) plugin — made all of this possible.

## Alternatives

- [Multisite Global Media](https://github.com/bueltge/multisite-global-media)
- [Network Shared Media](https://wordpress.org/plugins/network-shared-media/)
