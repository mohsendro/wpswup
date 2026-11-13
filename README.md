# WP Swup (`wpswup`)

> SPA-like page transitions (Swup 4) + scroll-based lazy section rendering for WordPress.  
> Works with **WooCommerce**, **Elementor**, and **WoodMart** — no theme file edits required.

**Repository:** [mohsendro/wpswup](https://github.com/mohsendro/wpswup)

## Features

- ⚡ Zero-reload navigation via [Swup.js 4](https://swup.js.org)
- 📦 Scroll-lazy section rendering with skeleton placeholders
- 🔍 **Auto-detect content containers** (WoodMart, Elementor, common WP themes)
- 🔧 PHP hooks + `window.SLL` JavaScript API
- 🏗️ Cache plugin headers (WP Rocket, LiteSpeed, …)
- ⚙️ Settings → **WP Swup**

## Requirements

- WordPress 5.9+
- PHP 8.0+

## Installation

1. Clone or download into `wp-content/plugins/wpswup/`
2. Activate **WP Swup**
3. Configure under *Settings → WP Swup*
4. Leave **Swap Containers** empty for auto-detect (recommended)

## Theme setup

No theme edits required. The plugin auto-detects selectors such as:

- WoodMart: `.main-page-wrapper`, `.wd-page-content`
- Elementor page wrappers
- `#main`, `#content`, `#primary`, `main.site-main`, …

Optional: force full reload on specific links:

```html
<a href="/cart/" data-no-swup>Cart</a>
```

## Lazy sections

```php
sll_section( 'hero' );
sll_section( 'features', [ 'limit' => 6 ] );
```

```html
<section data-render="hero"></section>
```

```
[sll_section name="hero"]
```

Template folders resolve from:

1. Theme `template-lazy/{name}/`
2. Plugin `template-lazy/{name}/`

## Changelog

### 1.1.2
- Renamed project to **WP Swup** (`wpswup`) — main file `wpswup.php`
- Guard `sll()` / `sll_section()` with `function_exists` (safe if two copies load)
- Auto-detect containers (WoodMart / Elementor / common themes)
- Singleton boot hardened against memory recursion
- Text domain & admin menu: `wpswup`

### 1.1.0
- Swup 4 hooks, Forms/Preload plugins, WooCommerce & Elementor re-init

### 1.0.0
- Initial release
