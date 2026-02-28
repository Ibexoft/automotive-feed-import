=== Automotive Inventory Importer – Sync Car Dealer Feeds ===
Contributors: jawaid
Donate link: https://www.ibexoft.com
Tags: car dealer, automotive, inventory, vehicle, car listing
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2.2.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically update your car inventory on your website. No manual entry needed. Stop wasting hours uploading cars one by one.

== Description ==

If you run a car dealership, your time should be spent selling cars, not typing data into your website. **Automotive Inventory Importer** is the simplest way to keep your WordPress site perfectly synced with your physical lot.

Whether you have 10 cars or 500, this plugin pulls your latest vehicle data and creates professional listings automatically.

=== 🚀 Why choose this plugin? ===
* **Save Hours of Work:** Stop manual data entry. Sync your entire inventory in minutes.
* **Always Up-to-Date:** Set it and forget it. Your website updates itself when your inventory changes.
* **Professional Look:** Works with your existing theme to show price, mileage, and features clearly.
* **No Coding Required:** Designed for dealer owners and managers, not just developers.

=== 🛠️ How it works ===
1. **Paste your Link:** Enter the link to your vehicle data file (XML/CSV).
2. **Match your Info:** Tell the plugin which part is the "Price," the "Model," and the "Photos."
3. **Go Live:** Watch your cars appear on your site instantly.

=== 🎯 Perfect for: ===
* Used Car Dealerships
* Auto Brokers
* RV and Motorcycle Dealers
* Web Agencies building sites for the automotive industry

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/automotive-feed-import` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the **Automotive Importer** menu in your dashboard to start your first import.

== Frequently Asked Questions ==

= Does this work with any XML feed? =
Yes, as long as the feed is accessible via a URL or file upload, you can map the fields to your WordPress site.

= Will it slow down my site? =
No. The import runs in the background (via Cron) so your customers experience a fast, smooth website.

= How often does the import run? =
You can configure the frequency in settings. Options include every 5, 10, 15, 30 minutes, hourly, twice daily, or daily. Note: Frequency changes require plugin deactivation/reactivation.

= Can I edit the imported vehicle data? =
Yes! All imported fields are fully editable in the vehicle post edit screen.

= How do I troubleshoot import issues? =
Check the import log on the settings page for detailed error messages. You can also use the "Run Import Now" button to test imports manually.

= Does it support custom XML fields? =
Yes! The plugin automatically imports all fields from your XML feed as post metadata.

= How do I give feedback to developer?
We're constantly improving and your feedback is invaluable. Take our [quick 2-minute survey](https://forms.gle/qEneb8ZeBxnFXuV78) to tell us what features you need, report issues, or suggest improvements. Your input directly influences our development roadmap!

== Screenshots ==

1. Settings page
2. Vehicle edit screen showing imported and editable fields
3. Import history viewer with detailed operation history
4. Frontend listing
5. Frontend post
6. Field mapping between feed and post
7. Page template setting

== Changelog ==

= 2.2.2 =
* Frontend listing improvements
* Users can now map fields between feed and post
* Several UX improvements
* Import images, set featured image, and display them in post
* Users can now import remote feeds

= 2.1 =
* Fixed version mismatch
* Updated name, description, and tags

= 2.0 =
* Added sample XML download from settings page
* Added detailed import success/failure notifications
* Added vehicle data display on frontend
* Added Settings link on plugins page
* Added sample XML structure in readme
* Improved SQL injection protection (all queries use prepared statements)
* Added blueprint.json for WordPress Playground live previews
* Enhanced error messaging and logging
* Improved user experience with better notifications
* Complete rewrite for PHP 8 compatibility
* Added custom 'vehicles' post type (replaces 'listing')
* Added comprehensive settings page (Settings → Automotive Feed Import)
* Added configurable import frequency
* Added token-based post title/content templates
* Added file browser UI for XML path selection
* Added error logging and display system
* Added manual import trigger
* Added dismissible feedback/survey banners
* Made all vehicle fields editable and saveable
* Improved security with proper nonces and escaping
* Automatic migration from old 'listing' post type

= 0.1 =
* Initial release
* Basic XML import functionality
* Hardcoded 10-minute schedule
* Read-only field display

== Upgrade Notice ==

= 2.0 =
Major update with PHP 8 compatibility, custom post type, full settings page, and editable fields. Existing listings will be automatically migrated.