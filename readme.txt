=== Gravity Forms Cap CAPTCHA ===
Contributors: 8amgmbh
Tags: gravity forms, captcha, cap, proof-of-work, spam protection
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.2.3
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a lightweight, privacy-first proof-of-work CAPTCHA field to Gravity Forms using Cap.

== Description ==

This plugin integrates [Cap](https://github.com/tiagozip/cap) — a modern, self-hosted CAPTCHA alternative — with Gravity Forms. Instead of visual puzzles, Cap uses SHA-256 proof-of-work challenges computed in the browser.

**Features:**

* Lightweight client-side proof-of-work widget (served by your Cap server)
* Privacy-first — no user data sent to third parties
* Self-hosted — you control the CAPTCHA server and the widget
* Accessible — no image puzzles to solve
* Drag-and-drop field in the Gravity Forms editor
* Translatable widget labels (i18n)
* Auto-updates via GitHub Releases

**Requirements:**

* Gravity Forms 2.5+
* A self-hosted Cap server (Docker recommended) with `ENABLE_ASSETS_SERVER` enabled — the widget is loaded from `{cap_server_url}/assets/widget.js`

== Installation ==

1. Upload the `eightam-gravity-cap` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Forms > Settings > Cap CAPTCHA
4. Enter your Cap Server URL, Site Key, and API Secret
5. Edit a form and drag the "Cap CAPTCHA" field from Advanced Fields into your form

== Changelog ==

= 1.2.3 =
* Fixed duplicate widget rendering caused by an `id` collision between the outer Gravity Forms `.gfield` wrapper and the inner `ginput_container`. The inner container now uses `input_X_Y` (matching the label's `for` attribute), which also avoids re-triggering the web component's `connectedCallback` when other scripts (e.g. collapsible sections, conditional logic) reattach the node.

= 1.2.2 =
* Probe the Cap server's assets endpoint when settings are saved; fall back to a public CDN (jsDelivr, pinned to @cap.js/widget@0.1.51) if `ENABLE_ASSETS_SERVER` isn't enabled or the endpoint is unreachable. Re-checked every 6 hours.
* Fail-open on `siteverify` network errors and 5xx responses — if the Cap server is unreachable or broken, allow submissions through instead of locking the form. Logged as an error so admins can notice.
* Removed dead `type="module"` script tag filter (widget is an IIFE, not a module).
* Updated server URL tooltip to mention the `ENABLE_ASSETS_SERVER` requirement.

= 1.2.1 =
* Load the Cap widget directly from the configured Cap server (`/assets/widget.js`) instead of bundling it. Requires `ENABLE_ASSETS_SERVER=true` on the Cap server. The widget now stays in sync with the server version automatically.

= 1.2.0 =
* Added auto-updater via GitHub Releases
* Widget label hidden by default (cleaner look)
* Full-width widget (--cap-widget-width: 100%)
* Shorter German widget translations
* Translatable widget labels (i18n via data-cap-i18n-* attributes)
* Bundled Cap widget JS locally (no CDN dependency)

= 1.0.0 =
* Initial release
* Cap CAPTCHA field type for Gravity Forms
* Server-side token verification
* Settings page under Forms > Settings
* German translations (de_DE, de_CH)
