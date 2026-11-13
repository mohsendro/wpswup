# WP Swup (`wpswup`)

> SPA-like page transitions with [Swup.js 4](https://swup.js.org) for WordPress.  
> Works with **WooCommerce**, **Elementor**, and **WoodMart** — no theme file edits required.

**Repository:** [mohsendro/wpswup](https://github.com/mohsendro/wpswup)

## Features

- ⚡ Zero-reload navigation via Swup.js 4
- 🎨 Official Swup themes: fade, slide, overlay (+ directions), or none
- 🔍 **Auto-detect content containers** (WoodMart, Elementor, common WP themes)
- 🔧 PHP hooks + `window.SLL` JavaScript API
- 🏗️ Cache plugin headers (WP Rocket, LiteSpeed, …)
- 🧩 WoodMart overlay / side-panel fix on SPA navigations
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

## Changelog

### 1.3.0
- Removed Lazy Section Rendering (will ship as a separate plugin later)
- Plugin focused on Swup SPA transitions only

### 1.2.2
- Strengthened WoodMart overlay close after theme change / reinit
- Official Swup theme instantiation in JS

### 1.2.1
- Full WordPress i18n (pot / po / l10n.php)
- Official Swup themes selectable in settings

### 1.1.2
- Renamed project to **WP Swup** (`wpswup`) — main file `wpswup.php`
- Auto-detect containers (WoodMart / Elementor / common themes)

### 1.1.0
- Swup 4 hooks, Forms/Preload plugins, WooCommerce & Elementor re-init

### 1.0.0
- Initial release
