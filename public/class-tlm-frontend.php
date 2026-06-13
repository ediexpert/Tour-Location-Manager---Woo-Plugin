<?php
/**
 * Frontend functionality: the [tour_location_menu] shortcode that
 * renders a navigable Country > State > City tree, plus archive
 * template handling so taxonomy archives list child locations and
 * matching products.
 *
 * @package Tour_Location_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TLM_Frontend {

	/**
	 * Singleton instance.
	 *
	 * @var TLM_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return TLM_Frontend
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_shortcode( 'tour_location_menu', array( $this, 'render_location_menu_shortcode' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Use our own template for "location" taxonomy archives, falling
		// back gracefully if the theme provides a more specific one.
		add_filter( 'taxonomy_template', array( $this, 'taxonomy_template' ) );

		// SEO: make sure the queried object's title/description are usable
		// by SEO plugins (Yoast/RankMath read term descriptions natively;
		// we simply ensure descriptions exist and document_title is correct).
		add_filter( 'document_title_parts', array( $this, 'filter_document_title' ) );

		// Add body class for styling hooks.
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Enqueue frontend CSS/JS for the location tree widget.
	 */
	public function enqueue_assets() {
		wp_register_style(
			'tlm-frontend',
			TLM_PLUGIN_URL . 'assets/css/tlm-frontend.css',
			array(),
			TLM_VERSION
		);

		wp_register_script(
			'tlm-frontend',
			TLM_PLUGIN_URL . 'assets/js/tlm-frontend.js',
			array(),
			TLM_VERSION,
			true
		);

		// Only enqueue when actually needed: shortcode pages or taxonomy archives.
		if ( is_tax( TLM_TAXONOMY ) || $this->page_has_shortcode() ) {
			wp_enqueue_style( 'tlm-frontend' );
			wp_enqueue_script( 'tlm-frontend' );
		}
	}

	/**
	 * Check whether the current queried post contains our shortcode.
	 *
	 * @return bool
	 */
	private function page_has_shortcode() {
		global $post;

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'tour_location_menu' );
	}

	/**
	 * Add a body class on location taxonomy pages for theme styling.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function add_body_class( $classes ) {
		if ( is_tax( TLM_TAXONOMY ) ) {
			$classes[] = 'tlm-location-archive';

			$queried = get_queried_object();
			if ( $queried instanceof WP_Term ) {
				$classes[] = 'tlm-location-level-' . tlm_get_term_depth( $queried->term_id );
			}
		}

		return $classes;
	}

	/**
	 * SEO: refine the document title on location archives to include
	 * the full hierarchy, e.g. "Los Angeles Tours - California - USA".
	 *
	 * @param array $title_parts Title parts.
	 * @return array
	 */
	public function filter_document_title( $title_parts ) {
		if ( ! is_tax( TLM_TAXONOMY ) ) {
			return $title_parts;
		}

		$queried = get_queried_object();

		if ( ! $queried instanceof WP_Term ) {
			return $title_parts;
		}

		// If the term already has an SEO-plugin-managed title, leave it alone.
		// We only enrich the default WordPress title.
		if ( empty( $title_parts['title'] ) || $title_parts['title'] === $queried->name ) {
			$ancestors = tlm_get_location_ancestors( $queried->term_id );
			$names     = wp_list_pluck( $ancestors, 'name' );
			$names     = array_reverse( $names );

			$label = sprintf(
				/* translators: %s: location name */
				__( '%s Tours & Services', 'tour-location-manager' ),
				$queried->name
			);

			if ( ! empty( $names ) ) {
				$label .= ' - ' . implode( ' - ', $names );
			}

			$title_parts['title'] = $label;
		}

		return $title_parts;
	}

	/**
	 * Point "location" taxonomy archives at our bundled template unless
	 * the active theme already provides taxonomy-location.php.
	 *
	 * @param string $template Template path resolved by WordPress.
	 * @return string
	 */
	public function taxonomy_template( $template ) {
		if ( ! is_tax( TLM_TAXONOMY ) ) {
			return $template;
		}

		// Respect theme overrides: taxonomy-location.php, taxonomy-location-{slug}.php.
		$theme_override = locate_template(
			array(
				'taxonomy-' . TLM_TAXONOMY . '.php',
			)
		);

		if ( $theme_override ) {
			return $template;
		}

		$plugin_template = TLM_PLUGIN_DIR . 'public/templates/taxonomy-location.php';

		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $template;
	}

	/**
	 * Shortcode handler: [tour_location_menu parent="0" depth="3" show_counts="yes"]
	 *
	 * Renders the full hierarchical location tree as nested, navigable
	 * unordered lists. Clicking a country reveals states/cities (via CSS/JS
	 * toggle), and each item links to its taxonomy archive where matching
	 * products are listed.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_location_menu_shortcode( $atts ) {
		$settings = TLM_Settings::get_settings();

		$atts = shortcode_atts(
			array(
				'parent'      => 0,
				'depth'       => 3,
				'show_counts' => $settings['show_counts'] ? 'yes' : 'no',
				'title'       => $settings['breadcrumb_label'],
				'expand_all'  => $settings['expand_all'] ? 'yes' : 'no',
			),
			$atts,
			'tour_location_menu'
		);

		$parent_id   = absint( $atts['parent'] );
		$max_depth   = max( 1, min( 3, absint( $atts['depth'] ) ) );
		$show_counts = ( 'yes' === strtolower( $atts['show_counts'] ) );
		$expand_all  = ( 'yes' === strtolower( $atts['expand_all'] ) );

		$top_terms = tlm_get_child_locations( $parent_id, false );

		// Apply manual ordering: meta "tlm_order" then name.
		$top_terms = $this->order_terms( $top_terms );

		if ( empty( $top_terms ) ) {
			return '<p class="tlm-empty">' . esc_html__( 'No locations have been added yet.', 'tour-location-manager' ) . '</p>';
		}

		ob_start();
		?>
		<div class="tlm-location-menu<?php echo $expand_all ? ' tlm-expanded' : ''; ?>">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h3 class="tlm-location-menu-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>
			<?php echo $this->render_term_list( $top_terms, 1, $max_depth, $show_counts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Sort an array of WP_Term objects by "tlm_order" term meta, then by name.
	 *
	 * @param WP_Term[] $terms Terms to sort.
	 * @return WP_Term[]
	 */
	private function order_terms( $terms ) {
		if ( empty( $terms ) ) {
			return $terms;
		}

		usort(
			$terms,
			function ( $a, $b ) {
				$order_a = (int) get_term_meta( $a->term_id, 'tlm_order', true );
				$order_b = (int) get_term_meta( $b->term_id, 'tlm_order', true );

				if ( $order_a === $order_b ) {
					return strcasecmp( $a->name, $b->name );
				}

				return ( $order_a < $order_b ) ? -1 : 1;
			}
		);

		return $terms;
	}

	/**
	 * Recursively render a <ul> of location terms with nested children.
	 *
	 * @param WP_Term[] $terms       Terms at the current level.
	 * @param int       $level       Current depth (1 = countries).
	 * @param int       $max_depth   Maximum depth to render.
	 * @param bool      $show_counts Whether to display product counts.
	 * @return string HTML.
	 */
	private function render_term_list( $terms, $level, $max_depth, $show_counts ) {
		if ( empty( $terms ) ) {
			return '';
		}

		$html  = '<ul class="tlm-location-list tlm-level-' . absint( $level ) . '">';

		foreach ( $terms as $term ) {
			$children = array();

			if ( $level < $max_depth ) {
				$children = $this->order_terms( tlm_get_child_locations( $term->term_id, false ) );
			}

			$has_children = ! empty( $children );

			$item_classes = array( 'tlm-location-item' );
			if ( $has_children ) {
				$item_classes[] = 'tlm-has-children';
			}

			$html .= '<li class="' . esc_attr( implode( ' ', $item_classes ) ) . '">';

			$html .= '<div class="tlm-location-row">';

			if ( $has_children ) {
				$html .= '<button type="button" class="tlm-toggle" aria-expanded="false" aria-label="' .
					/* translators: %s: location name */
					esc_attr( sprintf( __( 'Toggle %s', 'tour-location-manager' ), $term->name ) ) .
					'"><span aria-hidden="true">+</span></button>';
			} else {
				$html .= '<span class="tlm-toggle tlm-toggle-empty" aria-hidden="true"></span>';
			}

			$html .= sprintf(
				'<a href="%1$s" class="tlm-location-link">%2$s</a>',
				esc_url( get_term_link( $term ) ),
				esc_html( $term->name )
			);

			if ( $show_counts ) {
				$html .= sprintf(
					' <span class="tlm-count">(%d)</span>',
					(int) $term->count
				);
			}

			$html .= '</div>'; // .tlm-location-row

			if ( $has_children ) {
				$html .= '<div class="tlm-location-children">';
				$html .= $this->render_term_list( $children, $level + 1, $max_depth, $show_counts );
				$html .= '</div>';
			}

			$html .= '</li>';
		}

		$html .= '</ul>';

		return $html;
	}
}
