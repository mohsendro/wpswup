# Swup Lazy Loader

> SPA-like page transitions (Swup 4) + scroll-based lazy section rendering for WordPress theme developers.  
> Works with **WooCommerce** and **Elementor**.

## Features

- ⚡ **Zero-reload navigation** via [Swup.js 4](https://swup.js.org) (hooks system, visit object, built-in scroll)
- 📦 **Scroll-lazy section rendering** – break pages into sections loaded on demand
- 🎨 **Skeleton placeholders** – custom or auto-generated shimmer while content loads
- 🔧 **Extensible API** – PHP hooks, JS events, and a clean public `window.SLL` object
- 🏗️ **Cache & page-builder compatible** – WP Rocket, LiteSpeed, Elementor, WooCommerce
- ⚙️ **Settings panel** – tabbed UI in WP Admin → Settings → Swup Lazy Loader
- 🆕 **Swup 4 ready**: Head, Preload, Forms, optional Scroll plugins; animationScope; native View Transitions

---

## Requirements

- WordPress 5.9+
- PHP 8.0+

---

## Installation

1. Upload the `swup-lazy-loader/` folder to `/wp-content/plugins/`.
2. Activate the plugin in *Plugins → Installed Plugins*.
3. Configure under *Settings → Swup Lazy Loader*.

---

## Theme setup (important)

Your theme must mark the content area that Swup will replace and animate.

1. Give the main content wrapper a **unique id** (e.g. `#page` or `#content`) and the class `transition-swup`:

```html
<main id="page" class="transition-swup">
  <?php // theme content ?>
</main>
```

2. In plugin settings, set **Swap Containers** to that id (default: `#page`).

3. Optional: add `data-no-swup` on links that must force a full page load:

```html
<a href="/cart/" data-no-swup>Cart</a>
```

---

## template-lazy Directory

Each section lives in its own sub-folder under `template-lazy/`. Resolution order:

1. **Active theme** (or custom path via `define`): `your-theme/template-lazy/{name}/`
2. **Plugin fallback**: `swup-lazy-loader/template-lazy/{name}/`

### Changing the base path in `wp-config.php`

```php
define( 'SLL_TEMPLATE_LAZY_PATH', '/var/www/html/wp-content/lazy-sections' );
define( 'SLL_TEMPLATE_LAZY_URL',  'https://example.com/wp-content/lazy-sections' );
```

### Required files per section

| File            | Required | Description                              |
|-----------------|----------|------------------------------------------|
| `render.php`    | ✅        | Section markup. Has `$args` available.   |
| `skeleton.html` | ✅        | Placeholder shown while loading.         |
| `app.css`       | optional | Auto-enqueued when section is rendered.  |
| `app.js`        | optional | Auto-enqueued when section is rendered.  |

---

## Usage in Templates

```php
<?php
// PHP helper
sll_section( 'hero' );
sll_section( 'features', [ 'limit' => 6 ], 'div', 'my-class' );
?>
```

```html
<!-- Or raw attributes -->
<section data-render="hero"></section>
<div data-render="features" data-render-args='{"limit":6}'></div>

<!-- Exclude a link from Swup -->
<a href="/external" data-no-swup>External</a>
```

Shortcode:

```
[sll_section name="hero"]
[sll_section name="features" limit="6"]
```

---

## PHP Hooks

```php
add_filter( 'sll_js_config', function( $config ) {
    $config['duration'] = 500;
    return $config;
} );

add_filter( 'sll_template_candidates', function( $paths, $name ) {
    array_unshift( $paths, WP_CONTENT_DIR . '/shared-lazy/' . $name );
    return $paths;
}, 10, 2 );

add_filter( 'sll_render_args', function( $args, $template ) {
    if ( $template === 'hero' ) $args['image_id'] = get_theme_mod( 'hero_image' );
    return $args;
}, 10, 2 );

add_action( 'sll_before_render_hero', function( $args ) { /* … */ } );

add_filter( 'sll_render_response', function( $data, $template, $args ) {
    $data['extra'] = 'payload';
    return $data;
}, 10, 3 );
```

---

## JavaScript API (`window.SLL`)

```js
SLL.on('ready',           ({ SLL }) => {});
SLL.on('lazyBeforeLoad',  ({ el, template, args }) => {});
SLL.on('lazyLoaded',      ({ el, template, html }) => {});
SLL.on('lazyError',       ({ el, template, error }) => {});
SLL.on('transitionStart', ({ swup, visit }) => {});
SLL.on('pageView',        ({ swup, visit }) => {});
SLL.on('visitEnd',        ({ swup, visit }) => {});

// Inject extra Swup plugins before init
SLL.on('swupPlugins', ({ plugins }) => {
  // plugins.push(new SwupA11yPlugin());
});

// Reload a lazy section
SLL.reload('[data-render="hero"]');

// Raw Swup 4 instance
SLL.swup.hooks.on('page:view', (visit) => console.log(visit.to.url));
```

---

## Swup 4 notes

- Uses the new **hooks** API (`swup.hooks.on`, `before`, `once`) and **visit** object.
- Built-in scroll reset + `#anchor` handling (Scroll Plugin is optional for animated scroll).
- Containers should be unique selectors (prefer IDs).
- Animation timing is driven by elements matching `[class*="transition-"]` (we use `.transition-swup`).
- Forms Plugin is enabled by default for better WooCommerce form handling.
- After every `page:view` the plugin re-triggers WooCommerce fragments and Elementor frontend handlers.

---

## Cache Plugin Notes

The plugin adds `Vary: X-Requested-With` and `X-Rocket-No-Cache: 1` for Swup navigation requests so cache plugins keep full-page and partial responses separate.

---

## Changelog

### 1.1.0
- Full Swup 4 support (hooks, visit object, animationScope, native option)
- Correct CDN packages (`swup`, `@swup/head-plugin`, …)
- Forms Plugin + improved WooCommerce / Elementor re-init
- Optional Scroll Plugin (built-in scroll is enough for most sites)
- `sll_section()` helper enabled
- Settings UI updated for new options

### 1.0.0
- Initial release
"# wpswup" 
