# Tour Location Manager

A WooCommerce plugin that adds a **hierarchical "Location" taxonomy**
(Country → State/Province → City) for tours, activities, and other
services — completely independent of WooCommerce product categories
and tags.

---

## Features

- Registers a hierarchical custom taxonomy `location`, attached to the
  `product` post type.
- 3-level structure: **Country > State/Province > City** (extendable
  to deeper levels if needed).
- Visible and manageable on the standard **Products → Locations** admin
  screen, and shown as a checkbox tree on the product edit screen
  (native WordPress hierarchical taxonomy UI).
- **SEO-friendly archive URLs**, e.g.:
  `https://example.com/location/usa/california/los-angeles/`
- Custom archive template:
  - Country/State pages show their child locations plus matching
    products.
  - City pages show only products assigned to that city.
- `[tour_location_menu]` shortcode renders a navigable, collapsible
  tree of all locations.
- Admin settings page (**Products → Location Settings**) for taxonomy
  slug, archive URL base, menu title, product counts, and default
  expand state.
- Manual drag-free ordering via a numeric "Display Order" field on each
  term (lower numbers first).
- Translation-ready (`tour-location-manager` text domain, `.pot` file
  included).
- Helper functions for theme developers (see below).
- Activation/deactivation hooks that flush rewrite rules automatically.

---

## File Structure

```
tour-location-manager/
├── tour-location-manager.php        # Main plugin bootstrap file
├── uninstall.php                    # Cleanup on plugin deletion
├── includes/
│   ├── class-tlm-taxonomy.php       # Taxonomy registration & ordering
│   ├── class-tlm-settings.php       # Admin settings page
│   └── tlm-helper-functions.php     # Public helper functions
├── admin/
│   └── class-tlm-admin-product.php  # Product list/edit screen integration
├── public/
│   ├── class-tlm-frontend.php       # Shortcode + archive template hooks
│   └── templates/
│       └── taxonomy-location.php    # Default archive template
├── assets/
│   ├── css/
│   │   ├── tlm-frontend.css
│   │   └── tlm-admin.css
│   └── js/
│       └── tlm-frontend.js
└── languages/
    └── tour-location-manager.pot
```

---

## Installation

1. Upload the `tour-location-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
   - WooCommerce must be active; the plugin will refuse to activate
     (and explain why) if it is not.
3. WordPress will automatically flush rewrite rules on activation. If
   location archive links return a 404, go to
   **Settings → Permalinks** and click **Save Changes** once.
4. Go to **Products → Locations** to add your location hierarchy.

---

## Building the Location Hierarchy

Go to **Products → Locations** in the WordPress admin (this is a new
top-level item under the "Products" menu, separate from Categories and
Tags).

1. Add a **Country** term with no parent, e.g. `USA`.
2. Add a **State/Province** term, e.g. `California`, and set its
   **Parent Location** to `USA`.
3. Add a **City** term, e.g. `Los Angeles`, with parent `California`.

Example resulting structure:

```
USA
├── California
│   └── Los Angeles
└── (other states...)
Germany
├── Bavaria
│   └── Munich
└── (other states...)
```

Each term has an optional **Display Order** field — lower numbers are
listed first within their level (ties are broken alphabetically).

The admin term list also shows a **Level** column (Country /
State-Province / City) so you can verify your hierarchy at a glance.

---

## Assigning Locations to Products

On the **Edit Product** screen, the "Locations" meta box (in the
sidebar, like Categories/Tags) lets you check one or more locations —
typically the most specific one (a city), but you can also assign a
product directly to a state or country if it serves an entire region.

The **Products** list table also shows a filterable **Locations**
column and a location filter dropdown above the list.

---

## Frontend Display

### Automatic Archive Pages

Visiting a location's URL automatically shows:

- **Country page** (`/location/usa/`): list of states/provinces +
  any products assigned directly to "USA".
- **State page** (`/location/usa/california/`): list of cities in
  California + products assigned to California (and, by default,
  products from its cities too).
- **City page** (`/location/usa/california/los-angeles/`): only
  products assigned to Los Angeles.

If your theme provides its own `taxonomy-location.php`, it will be
used instead of the bundled template automatically.

### Shortcode

```
[tour_location_menu]
```

Renders the full tree starting from all top-level countries. Click a
country to expand its states; click a state to expand its cities.
Every item links to its archive page.

#### Shortcode Attributes

| Attribute     | Default                 | Description                                              |
|---------------|-------------------------|-----------------------------------------------------------|
| `parent`      | `0`                     | Term ID to start from (`0` = all countries).              |
| `depth`       | `3`                     | Maximum levels to render (1–3).                           |
| `show_counts` | site setting            | `yes`/`no` — show product counts next to each location.   |
| `expand_all`  | site setting            | `yes`/`no` — render the tree fully expanded.              |
| `title`       | "Browse by Destination" | Heading shown above the tree (set blank to hide).         |

#### Examples

Show only countries and states (no cities):

```
[tour_location_menu depth="2"]
```

Show the tree starting from a specific country (find its term ID under
**Products → Locations**, e.g. `42` for USA), fully expanded:

```
[tour_location_menu parent="42" expand_all="yes" title="Explore the USA"]
```

### Adding the Menu to a WordPress Menu / Widget

Add a **Custom HTML** widget (Appearance → Widgets) anywhere in your
sidebar or footer, with the content:

```html
[tour_location_menu depth="2"]
```

Shortcodes in widgets are supported by default in modern WordPress.

---

## Helper Functions for Theme Developers

All functions live in `includes/tlm-helper-functions.php` and are safe
to call from any theme template (always check `function_exists()` if
you want extra safety against the plugin being deactivated).

```php
// Get the hierarchy depth of a term: 0 = Country, 1 = State, 2 = City.
$depth = tlm_get_term_depth( $term_id );

// Human-readable level label ("Country", "State/Province", "City").
$label = tlm_get_level_label( $term_id );

// Direct children of a location term (0 = top-level countries).
$states = tlm_get_child_locations( $usa_term_id );

// Full ancestor chain, top-down (e.g. [USA, California]).
$ancestors = tlm_get_location_ancestors( $los_angeles_term_id );

// Linked breadcrumb string: "USA » California » Los Angeles".
echo tlm_get_location_breadcrumb( $los_angeles_term_id );

// WP_Query of products assigned to a location (optionally incl. children).
$query = tlm_get_products_for_location( $term_id, true );
while ( $query->have_posts() ) {
    $query->the_post();
    wc_get_template_part( 'content', 'product' );
}
wp_reset_postdata();

// Locations assigned to a specific product.
$locations = tlm_get_product_locations( $product_id );

// Echo the location tree from PHP without using do_shortcode() directly.
tlm_the_location_menu( array( 'depth' => 2 ) );
```

---

## Settings

Go to **Products → Location Settings**:

- **Taxonomy Slug** — internal taxonomy identifier (advanced).
- **Archive URL Base** — the URL prefix for location archives
  (default `location`, producing `/location/usa/...`).
- **Frontend Menu Title** — default heading text (currently informational;
  primarily used as the default `title` attribute for the shortcode).
- **Tree Heading Label** — default heading shown above the
  `[tour_location_menu]` tree.
- **Show Product Counts** — show `(N)` counts next to each location.
- **Expand Tree by Default** — render the tree fully expanded instead
  of collapsed-by-default with click-to-expand.

Changing the slug/base automatically flushes rewrite rules.

---

## Developer Notes / Extensibility

- The taxonomy registration arguments are filterable:

  ```php
  add_filter( 'tlm_taxonomy_args', function ( $args ) {
      // e.g. restrict who can assign terms
      $args['capabilities'] = array(
          'assign_terms' => 'edit_products',
          'manage_terms' => 'manage_product_terms',
      );
      return $args;
  } );
  ```

- To override the archive layout entirely, copy
  `public/templates/taxonomy-location.php` into your theme as
  `taxonomy-location.php` and customize it — the plugin detects and
  defers to theme overrides automatically.

- The plugin does **not** modify product categories or tags in any
  way; "Locations" is a fully independent taxonomy and will not
  conflict with existing category/tag-based filtering, plugins, or
  themes.

---

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+

---

## Uninstalling

Deactivating the plugin only flushes rewrite rules — your location
terms, assignments, and settings are preserved. Deleting the plugin
via the Plugins screen removes the plugin's settings option. Location
terms and product assignments are preserved by default (see
`uninstall.php` for an optional full-purge code block).
