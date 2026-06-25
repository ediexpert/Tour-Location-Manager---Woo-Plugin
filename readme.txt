=== INTS Tour Location Manager ===
Contributors: imbajwa
Tags: woocommerce, location, taxonomy, tours, travel
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 5.0
WC tested up to: 9.4

Adds a hierarchical Location taxonomy (Country > State > City) for WooCommerce products, with grid shortcode, thumbnail support, and SEO archives.

== Description ==

**Tour Location Manager** adds a fully independent, hierarchical **Location** taxonomy to WooCommerce — completely separate from product categories and tags.

Organise your tours, activities, and services by **Country → State/Province → City**, with SEO-friendly archive pages, a collapsible location tree shortcode, and a WooCommerce-style thumbnail grid shortcode.

= Key Features =

* **Hierarchical Location taxonomy** (Country > State/Province > City) attached to WooCommerce products.
* **Location thumbnails** — upload a thumbnail image per location via the media library (same as WooCommerce product categories).
* **`[tour_locations]` shortcode** — displays a WooCommerce-compatible thumbnail grid (same markup and styling as `[product_categories]`).
* **`[tour_location_menu]` shortcode** — renders a collapsible Country > State > City navigation tree.
* **SEO-friendly archive URLs**, e.g. `example.com/location/usa/california/los-angeles/`
* **Custom archive template** — country/state pages show child locations + products; city pages show products only. Theme-overridable.
* **Admin product list integration** — Locations column and filter dropdown on the Products list table.
* **Display Order field** — manually sort locations with a numeric order field (lower numbers first).
* **Settings page** under Products → Location Settings.
* **WooCommerce HPOS compatible** (High Performance Order Storage).
* **Translation-ready** — `.pot` file included, `ints-tour-location-manager` text domain.

= Shortcodes =

**Location grid (like WooCommerce categories):**

`[tour_locations ids="1,2,3" orderby="include" columns="4" show_counts="yes"]`

Attributes: `ids`, `orderby` (name/count/slug/id/include), `order` (ASC/DESC), `columns` (1–6), `hide_empty` (0/1), `parent`, `show_counts` (yes/no).

**Location navigation tree:**

`[tour_location_menu parent="0" depth="3" show_counts="yes" expand_all="no"]`

= Requirements =

* WordPress 5.8+
* WooCommerce 5.0+
* PHP 7.4+

= Developer-Friendly =

* Filter taxonomy registration args via `tlm_taxonomy_args`.
* Override the archive template by placing `taxonomy-location.php` in your theme.
* Helper functions: `tlm_get_term_depth()`, `tlm_get_location_ancestors()`, `tlm_get_location_breadcrumb()`, `tlm_get_products_for_location()`, and more.

== Installation ==

1. Upload the `ints-tour-location-manager` folder to `/wp-content/plugins/`, or install via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin. WooCommerce must be active — the plugin will refuse to activate and explain why if it is not.
3. If location archive URLs return a 404, go to **Settings → Permalinks** and click **Save Changes** once to flush rewrite rules.
4. Go to **Products → Locations** to build your location hierarchy.
5. On any product edit screen, assign locations using the **Locations** meta box (like Categories).

== Frequently Asked Questions ==

= Does this replace WooCommerce product categories? =

No. The Location taxonomy is completely independent of WooCommerce categories and tags. Both can be used on the same products simultaneously.

= How do I add a thumbnail to a location? =

Go to **Products → Locations**, edit any location, and use the **Thumbnail** field to upload or select an image from the media library.

= How do I display locations as a grid like WooCommerce categories? =

Use the `[tour_locations]` shortcode. It uses identical HTML markup and CSS classes to WooCommerce's `[product_categories]`, so it will be styled automatically by any WooCommerce-compatible theme including Shoptimizer, Flatsome, OceanWP, and Storefront.

= How do I control the number of columns? =

Use the `columns` attribute: `[tour_locations columns="4"]`. Supports 1–6 columns.

= Can I display specific locations by ID? =

Yes: `[tour_locations ids="12,15,18" orderby="include"]` — the `orderby="include"` preserves the order of your IDs.

= Can I override the archive template in my theme? =

Yes. Copy `public/templates/taxonomy-location.php` from the plugin into your theme root as `taxonomy-location.php` and customise it freely.

= Will deleting the plugin remove my location data? =

Deactivating preserves all data. Deleting removes the plugin settings option. Location terms and product assignments are preserved by default. See `uninstall.php` for an optional full-purge code block.

= Is this compatible with WooCommerce HPOS? =

Yes. The plugin declares full compatibility with WooCommerce High Performance Order Storage (HPOS) and Cart & Checkout Blocks.

== Screenshots ==

1. Products → Locations admin screen showing the hierarchical location tree with Level and Thumbnail columns.
2. Edit Location screen with the Thumbnail upload field and Display Order field.
3. `[tour_locations]` shortcode rendered as a 4-column thumbnail grid on the frontend.
4. `[tour_location_menu]` shortcode rendered as a collapsible Country > State > City navigation tree.
5. Location archive page showing child locations and matching WooCommerce products.
6. Product edit screen with the Locations meta box (Country/State/City checkboxes).
7. Plugin settings page under Products → Location Settings.

== Changelog ==

= 1.0.0 =
* Initial release.
* Hierarchical Location taxonomy (Country > State/Province > City).
* Location thumbnail upload support (media library).
* `[tour_locations]` grid shortcode (WooCommerce-compatible markup).
* `[tour_location_menu]` collapsible tree shortcode.
* SEO-friendly hierarchical archive URLs.
* Custom archive template with theme-override support.
* Admin product list integration (Locations column and filter dropdown).
* Display Order field for manual location sorting.
* Settings page under Products → Location Settings.
* WooCommerce HPOS compatibility declaration.
* Translation-ready with `.pot` file.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
