=== SFLWA Elementor Restriction Updater ===
Contributors: sflwa
Tags: elementor, wp-cli, display-conditions, migration, restrict-for-elementor
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 1.3.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automate the migration from legacy "Restrict for Elementor" settings to native Elementor Display Conditions via WP-CLI or WP-Admin.

== Description ==

**SFLWA Elementor Restriction Updater** provides an agency-grade, automated utility to scan, back up, migrate, and clean up legacy "Restrict for Elementor" settings across your site, replacing them seamlessly with native Elementor Display Conditions (`e_display_conditions`).

Effortlessly transition away from buggy third-party restriction plugins by executing granular updates on specific post IDs, appending fallback templates for logged-out users, or running site-wide migrations—either directly through WP-CLI or via a native dashboard interface under **Tools > Elementor Migration**.

### Key Features:
* **Dual Execution Modes:** Perform scans and migrations using dedicated WP-CLI commands or through the built-in WP-Admin dashboard interface under **Tools > Elementor Migration**.
* **Granular Control & Post ID Filtering:** Pass specific post IDs (e.g., `--post_id=2438,6980`) via CLI or the Admin UI filter to run targeted migrations without touching the rest of your site.
* **Optional Fallback Template Injection:** Select or pass an Elementor Template ID (`--append_template=2429`) to automatically build and append a new bottom-level container housing that template, restricted natively to Logged-Out users.
* **Post Status Awareness:** Instantly distinguish between live pages (`publish`), revisions (`revision`), draft, or private post states during scanning.
* **1:1 Migration Mapping:** Maps legacy roles (`user_role`), logged-in status (`logged_in_users`), and logged-out status (`logged_out_users`) cleanly into native Elementor Display Conditions payload structures.
* **Orphaned Metadata Cleanup:** Detects and strips leftover plugin meta keys and corrupted settings left behind by earlier plugin versions.
* **Automated Safety Backups:** Automatically clones `_elementor_data` to a temporary `_elementor_data_backup_legacy` meta key before running updates.
* **One-Click Rollback & Cleanup:** Easily restore original data states using native rollback commands, or permanently flush temporary backup keys when finished.
* **Automatic Cache Management:** Clears post-specific CSS meta and flushes Elementor's global file manager cache upon migration or rollback.

== Installation ==

1. Upload the `sflwa-elementor-restriction-updater` folder to the `/wp-content/plugins/` directory (or upload the `.zip` archive via **Plugins > Add New**).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Access the plugin interface via **Tools > Elementor Migration** in your WP Admin sidebar, or run `wp find-restrictions` in terminal via WP-CLI.

== WP-CLI Commands & Usage ==

= 1. Scan for Restrictions =
Locates all posts and specific Elementor elements containing legacy settings or orphaned keys:
`wp find-restrictions`

*Filter by Post ID(s):*
`wp find-restrictions --post_id=2438,6980`

= 2. Dry Run (Preview Migration) =
Test the migration process without modifying the database:
`wp migrate-elementor-restrictions --dry-run`

= 3. Execute Migration =
Converts legacy restrictions to Elementor's native Display Conditions, strips old metadata keys, clears CSS cache, and creates an automatic backup:
`wp migrate-elementor-restrictions`

*Optional Flags:*
* `--post_id=2438,6980` : Restricts migration to specific post IDs.
* `--append_template=2429` : Appends a root-level container with the specified Elementor Template ID set to Logged-Out visibility.
* `--skip-backup` : Runs the migration without creating a backup postmeta key.

*Example combining filters and template appending:*
`wp migrate-elementor-restrictions --post_id=2438 --append_template=2429`

= 4. Rollback Changes =
Restores the original `_elementor_data` state from `_elementor_data_backup_legacy`:
`wp rollback-elementor-restrictions`
`wp rollback-elementor-restrictions --post_id=2438`

= 5. Cleanup Backup Keys =
Permanently removes the temporary backup postmeta keys when migration is verified:
`wp cleanup-restriction-backups`
`wp cleanup-restriction-backups --post_id=2438`

== Screenshots ==

1. **Admin Tools View:** Clean table interface displaying post IDs, titles, live/revision post statuses, element IDs, and restriction modes.
2. **Post ID Filtering & Template Selector:** Filter panel allowing granular control over post IDs and optional selection of an Elementor fallback template for logged-out users.

== Changelog ==

= 1.3.0 =
* Feature: Added Logged-Out Fallback Template appending option (`--append_template` CLI flag and Admin UI dropdown selector).
* Architecture: Generates standalone root-level flex containers housing `template` widgets cleanly appended to `_elementor_data`.

= 1.2.0 =
* Feature: Added granular Post ID filtering (`--post_id`) across CLI commands and Admin UI.
* Feature: Added Post Status column (`publish`, `revision`, `draft`, etc.) to scan results to separate live pages from autosaves.
* Standard: Updated PHP requirement to 8.1 and WordPress compatibility testing to 7.0.

= 1.1.0 =
* Complete plugin rebranding to **SFLWA Elementor Restriction Updater**.
* Added WP-Admin dashboard UI under **Tools > Elementor Migration**.
* Standardized class and function prefixes (`sflwa_eru_`) and wrapped database queries with `$wpdb->prepare()`.

= 1.0.0 =
* Initial release with WP-CLI scan, 1:1 condition mapping, rollback, and backup cleanup functionality.
