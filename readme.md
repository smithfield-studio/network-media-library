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
  ACF/
    ValueFilter.php                ACF image/file field value resolution
    FieldRendering.php             ACF admin field rendering (file fields)
  Thumbnail/
    PostSaver.php                  Featured image persistence (classic editor)
    RestSaver.php                  Featured image persistence (Gutenberg/REST)
```

## License

MIT. See [LICENSE](./LICENSE).

## History

This plugin started as a fork of the [Multisite Global Media plugin](https://github.com/bueltge/multisite-global-media) by Frank Bültge and Dominik Schilling at [Inpsyde](https://inpsyde.com/). The initial fork was made as part of a client project at [Human Made](https://humanmade.com/). This maintained fork is by [Smithfield Studio](https://smithfield.studio/).

## Alternatives

- [Multisite Global Media](https://github.com/bueltge/multisite-global-media)
- [Network Shared Media](https://wordpress.org/plugins/network-shared-media/)
