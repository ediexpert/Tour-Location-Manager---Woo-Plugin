# Tour Location Manager - Project Instructions

These instructions apply to all code changes in this repository.

## Ownership
- Author: Imran Bajwa
- Company: INT SERVICES LLC

## Project Context
- This is a WordPress plugin with WooCommerce integration.
- Keep compatibility with current WordPress and WooCommerce APIs.
- Follow established project structure:
  - `admin/` for wp-admin behavior
  - `public/` for frontend behavior
  - `includes/` for shared/domain logic
  - `assets/` for CSS/JS only

## Core Standards
- Follow WordPress Coding Standards (WPCS) for PHP.
- Use WordPress and WooCommerce APIs before writing custom solutions.
- Prefer hooks (actions/filters) over editing core behavior directly.
- Keep functions/classes small and single-purpose.
- Do not introduce breaking changes to existing hooks, options, or public function signatures unless explicitly requested.

## Security Requirements
- Sanitize all input as early as possible (`sanitize_text_field`, `absint`, `wc_clean`, etc.).
- Validate data types and expected values before use.
- Escape all output as late as possible (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Verify nonces for form submissions and AJAX requests.
- Enforce capabilities (`current_user_can`) for admin-only operations.
- Never trust `$_GET`, `$_POST`, `$_REQUEST`, or AJAX payloads.

## WooCommerce Best Practices
- Use WooCommerce helper/API functions where available.
- For product/order/cart data, use WooCommerce CRUD objects and accessors instead of direct post meta or direct SQL where possible.
- Keep WooCommerce compatibility in mind when touching checkout, cart, pricing, order lifecycle, or product data.
- When adding WooCommerce hooks, include clear priorities and argument counts where needed.
- Use `wc_get_logger()` for WooCommerce-specific debug logging when appropriate.

## Database and Performance
- Avoid direct SQL unless there is no API alternative; if required, use `$wpdb->prepare`.
- Cache expensive lookups when practical (transients/object cache) and invalidate correctly.
- Avoid loading admin-only code on frontend requests and vice versa.
- Enqueue scripts/styles only where needed.

## Admin and UX
- Keep admin settings pages consistent with WordPress UI patterns.
- Show actionable, localized admin notices for validation errors/success states.
- Do not block page rendering with heavy synchronous operations.

## Internationalization
- Wrap user-facing strings in translation functions with the plugin text domain.
- Use context-aware functions (`esc_html__`, `esc_attr__`, `_x`, etc.) as appropriate.
- Keep text domain usage consistent across all files.

## JavaScript and Assets
- Use WordPress enqueue APIs (`wp_enqueue_script`, `wp_enqueue_style`).
- Localize frontend/admin script data through WordPress APIs, not inline globals when avoidable.
- Keep JS framework-free unless a dependency is explicitly required.

## Testing and Validation Checklist
Before finalizing changes:
- PHP syntax passes.
- No fatal errors on plugin activation.
- WooCommerce flows affected by the change are manually verified.
- Security checks (sanitization, escaping, nonce, capability) are present.
- Strings are translatable.
- Uninstall behavior remains safe and expected.

## Change Policy
- Prefer minimal, focused diffs.
- Preserve backward compatibility unless explicitly told otherwise.
- Document new hooks, options, and behavioral changes in `README.md` when relevant.