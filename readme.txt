=== Automotive Vehicle Inventory Feed Import ===
Contributors: jawaid
Donate link: https://www.ibexoft.com
Tags: automotive, vehicle, inventory, feed, dealership
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: trunk
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import vehicle inventory from XML feeds into WordPress. Features configurable imports, custom post types, and fully editable vehicle data.

== Description ==

**🎯 Help Shape the Future of This Plugin!**  
We're constantly improving and your feedback is invaluable. Take our [quick 2-minute survey](https://forms.gle/qEneb8ZeBxnFXuV78) to tell us what features you need, report issues, or suggest improvements. Your input directly influences our development roadmap!

Automotive Feed Import Plugin imports vehicle inventory from XML feeds into a custom "vehicles" post type. This modern, PHP 8-compatible plugin offers full customization through an intuitive settings page.

= Key Features =

* **Custom Post Type**: Creates a dedicated "vehicles" post type with car icon in dashboard
* **Configurable Import Schedule**: Choose from multiple frequencies (5/10/15/30 minutes, hourly, daily)
* **Customizable Post Formats**: Use token-based templates for titles and content (e.g., `{manufacturer} {brand} {model_year}`)
* **File Browser UI**: Select XML feed files through an intuitive server file browser
* **Error Logging**: Comprehensive logging system with viewable/clearable logs in admin
* **Editable Vehicle Data**: All imported fields are fully editable in WordPress admin
* **Manual Import Trigger**: Run imports on-demand from settings page
* **Backward Compatible**: Maintains compatibility with old plugin field structure

= How It Works =

1. Plugin runs on a scheduled basis (configurable frequency)
2. Loads XML feed and parses vehicle data
3. Matches vehicles by stock number to existing posts
4. Creates new vehicle posts or updates existing ones
5. Stores all XML fields as post metadata
6. Logs all operations with detailed error reporting

= Token-Based Templates =

Customize how vehicle posts are created using tokens from your XML feed:

**Example Title**: `{manufacturer} {brand} {model_year}`  
**Example Content**: `{designation} {manufacturer} {brand} {model} - {special_web_price}`

Available tokens include: manufacturer, brand, model, model_year, stock_number, designation, special_web_price, mileage, exterior_color, status, and any other fields from your XML feed.

= XML Feed Structure =

The plugin expects an XML feed with the following structure:

`
<?xml version="1.0"?>
<Inventory>
   <Unit rec_id="999*3698A">
      <web_dealer_id>999</web_dealer_id>
      <stock_number>1440</stock_number>
      <designation>NEW</designation>
      <manufacturer>JAYCO</manufacturer>
      <brand>EAGLE</brand>
      <model>SUPER LITE</model>
      <model_year>2011</model_year>
      <type>FIFTH WHEEL</type>
      <status>AVAILABLE</status>
      <exterior_color>WHITE</exterior_color>
      <mileage>12000</mileage>
      <length>29' 2"</length>
      <special_web_price>26000.00</special_web_price>
      <!-- Additional fields supported -->
   </Unit>
   <!-- More Unit entries -->
</Inventory>
`

* Root element: `<Inventory>`
* Child elements: Multiple `<Unit>` entries
* Required field: `stock_number` (used for matching/updating)
* Common fields: manufacturer, brand, model, model_year, special_web_price, status, etc.
* Download a sample XML file from the plugin settings page

== Installation ==

1. Upload `automotive-feed-import` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Settings → Automotive Feed Import
4. Configure your XML feed path and import settings
5. Save settings and run your first import

== Frequently Asked Questions ==

= Where do I configure the plugin? =

Navigate to Settings → Automotive Feed Import in your WordPress admin panel.

= How do I set the XML feed path? =

Use the file browser button on the settings page to navigate your server and select the XML file, or enter the full server path manually.

= Can I customize how vehicle posts are created? =

Yes! Use the token-based template system to customize post titles and content. Any field from your XML feed can be used as a token.

= How often does the import run? =

You can configure the frequency in settings. Options include every 5, 10, 15, 30 minutes, hourly, twice daily, or daily. Note: Frequency changes require plugin deactivation/reactivation.

= Can I edit the imported vehicle data? =

Yes! All imported fields are fully editable in the vehicle post edit screen.

= What happens to existing Automotive Theme listings? =

On first activation, the plugin automatically migrates any existing 'listing' posts to the new 'vehicles' post type.

= How do I troubleshoot import issues? =

Check the import log on the settings page for detailed error messages. You can also use the "Run Import Now" button to test imports manually.

= Does it support custom XML fields? =

Yes! The plugin automatically imports all fields from your XML feed as post metadata.

= Does it have a paid or pro version? =

Pro version is under development and your feedback is very important. Please, participate in the survey to shape the future of the plugin.

== Screenshots ==

1. Settings page with XML path, frequency, and template configuration
2. Vehicle edit screen showing imported and editable fields
3. Import log viewer with detailed operation history

== Changelog ==

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