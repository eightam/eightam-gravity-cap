=== Gravity Forms Cap CAPTCHA ===
Contributors: 8amgmbh
Tags: gravity forms, captcha, cap, proof-of-work, spam protection
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds a lightweight, privacy-first proof-of-work CAPTCHA field to Gravity Forms using Cap.

== Description ==

This plugin integrates [Cap](https://github.com/tiagozip/cap) — a modern, self-hosted CAPTCHA alternative — with Gravity Forms. Instead of visual puzzles, Cap uses SHA-256 proof-of-work challenges computed in the browser.

**Features:**

* Lightweight (~30KB client-side widget, bundled)
* Privacy-first — no user data sent to third parties
* Self-hosted — you control the CAPTCHA server
* Accessible — no image puzzles to solve
* Drag-and-drop field in the Gravity Forms editor
* Translatable widget labels (i18n)
* Auto-updates via GitHub Releases

**Requirements:**

* Gravity Forms 2.5+
* A self-hosted Cap server (Docker recommended)

== Installation ==

1. Upload the `eightam-gravity-cap` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to Forms > Settings > Cap CAPTCHA
4. Enter your Cap Server URL, Site Key, and API Secret
5. Edit a form and drag the "Cap CAPTCHA" field from Advanced Fields into your form

== Changelog ==

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
