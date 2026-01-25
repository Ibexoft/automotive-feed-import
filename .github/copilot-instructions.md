# Automotive Feed Import Plugin - AI Coding Instructions

## Project Overview
Modern WordPress plugin that imports vehicle inventory from XML feeds into a custom `vehicles` post type. Fully configurable with settings page, scheduled imports, and error logging. **PHP 8 compatible**. **Version 2.0** with frontend display and enhanced notifications.

## Architecture & Data Flow

**Core Flow**: XML Feed → Parse → Match by `stock_number` → Create/Update Post → Store as Post Meta → Log Results → Display Frontend

1. **Scheduled Import** (`update_xml_event` cron): Runs on configurable frequency (default 10 minutes)
2. **Matching Logic**: Queries `wp_postmeta` for existing `stock_number` to determine create vs update
3. **Post Creation**: New vehicles get `post_type='vehicles'` with customizable title/content templates using tokens
4. **Data Storage**: Each XML field stored as individual post meta entry via `update_post_meta()`
5. **Error Handling**: All operations logged to file + admin notices for errors
6. **Frontend Display**: Vehicle specifications automatically displayed on single vehicle pages

**Configuration**: Settings → Automotive Feed Import (XML path, frequency, post format templates)

## Critical Patterns

### Token-Based Post Formatting
Posts use configurable templates with token replacement:
```php
// Settings examples:
$title = '{manufacturer} {brand} {model_year}';
$content = '{designation} {manufacturer} {brand} {model} {model_year}';

// Parsed via parse_template() method
$this->parse_template($title_template, $unit);
```
**Available tokens**: Any XML field name wrapped in `{}` (e.g., `{stock_number}`, `{special_web_price}`, `{status}`)

### Post Meta Dual Storage (Backward Compatibility)
Plugin stores data twice to maintain compatibility with legacy Automotive Theme:
```php
update_post_meta($post_id, 'special_web_price', $value);        // Plugin's format
update_post_meta($post_id, 'price_value', $value);              // Theme's legacy format
```
**Also applies to**: `manufacturer`→`manufacturer_level2_value`, `model_year`→`year_value`, `mileage`→`mileage_value`, `exterior_color`→`color_value`

### Custom Post Type 'vehicles'
- Registered via `register_vehicles_post_type()` on `init` hook
- Dashboard icon: `dashicons-car`, menu position: 5
- Supports: title, editor, thumbnail, custom-fields
- **Migration**: Activation automatically migrates old `listing` posts to `vehicles` post type (one-time operation tracked in options)

### Error Handling & Logging
Centralized logging system with detailed notifications:
```php
$this->log($message, $is_error);  // Writes to wp-content/uploads/automotive-feed-import/import-log.txt
```
- **Admin Notices**: Errors show dismissible notices in WP admin
- **Import Notifications**: Success shows count of created/updated vehicles, failure shows error message
- **Log File**: Viewable/clearable from settings page
- **Manual Trigger**: "Run Import Now" button for testing
- **Sample XML**: "Download Sample XML" button on settings page

## XML Feed Structure
Sample unit from feed:
- Root: `<Inventory>` → Multiple `<Unit>` children
- Key fields: `stock_number` (unique ID), `manufacturer`, `brand`, `model`, `special_web_price`, `status`
- Uses SimpleXML for parsing with error handling

## WordPress Conventions

### Settings API Implementation
- Page: Settings → Automotive Feed Import
- Options: `automotive-feed-import_*` (XML path, frequency, title format, content format)
- Sections: General Settings, Post Format Settings
- Custom Actions: Clear Log, Run Import Now (with nonces)

### Cron Scheduling
- Custom intervals: `fiveminute`, `tenminute`, `fifteenminute`, `thirtyminute` (plus WP defaults)
- Event: `update_xml_event` triggers `update_data()` method
- **Frequency Changes**: Require plugin deactivation/reactivation to take effect

### Direct DB Queries
Uses `$wpdb->prepare()` with proper sanitization:
```php
$wpdb->get_var($wpdb->prepare(
    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'stock_number' AND meta_value = %s",
    $unit['stock_number']
));
```
**Security**: All database queries use prepared statements or parameterized updates to prevent SQL injection.

### Frontend Vehicle Display
- Vehicle specifications automatically displayed on single vehicle pages
- Styled table shows: stock number, manufacturer, brand, model, year, condition, type, mileage, color, length, price, status
- Filters via `the_content` hook on `vehicles` post type
- Handler: `display_vehicle_frontend()` method

### Plugin Action Links
- "Settings" link added to plugin list page
- Direct link to plugin settings from Plugins → Installed Plugins
- Filter: `plugin_action_links_` with plugin basename

### AJAX & Dismissible Banners
- **Feedback Banner**: Link to WordPress.org reviews (dismissible per user)
- **Survey Banner**: Configurable URL in `$survey_url` property (dismissible per user)
- Dismissal state stored in `user_meta` via AJAX handler `afi_dismiss_banner`

### File Browser UI
- AJAX-powered file browser for selecting XML feed path
- Navigates server directories within ABSPATH security boundary
- Filters to show only folders and .xml files
- Uses WordPress Thickbox for modal display
- Handler: `ajax_browse_files()` method with `afi_browse_files` action

### Editable Vehicle Fields
- All imported fields displayed as editable `<input>` elements (not readonly)
- Saved via `save_vehicle_meta()` on `save_post_vehicles` hook
- Proper nonce verification and capability checks
- Automatically updates both plugin and theme compatibility fields

## Development Workflow

### Testing Imports
```bash
# Manual trigger via WP-CLI
wp cron event run update_xml_event

# Or use settings page "Run Import Now" button
```

### Settings Configuration
1. Navigate to Settings → Automotive Feed Import
2. Use the **Browse Server** button to select XML file via file browser UI (shows folders and .xml files only)
3. Choose import frequency from dropdown
4. Customize post templates using tokens (e.g., `{manufacturer} {brand} {model_year}`)
5. Monitor import log in real-time
6. Clear log or run manual imports as needed

### Editing Vehicle Data
1. Navigate to Vehicles in WordPress admin
2. Edit any vehicle post
3. Vehicle Information meta box shows all imported fields as **editable text inputs**
4. Save post to persist changes
5. Fields automatically update both plugin format and theme compatibility fields

### First-Time Setup
On activation:
1. Creates `vehicles` custom post type
2. Migrates existing `listing` posts (if any)
3. Schedules cron event
4. Flushes rewrite rules
5. Creates log directory in uploads folder

### Error Debugging
1. Check Settings page log viewer for detailed errors
2. XML parsing errors show specific libxml messages
3. Failed imports logged with stock_number context
4. Clear log via settings page when needed

## File Structure
```
automotive-feed-import.php         # Single-file plugin (~1000 lines)
Web_Inventory_999.xml              # Sample XML feed
readme.txt                         # WordPress.org readme
.github/copilot-instructions.md    # This file
.wordpress-org/blueprint.json      # WordPress Playground live preview config
assets/                            # Screenshots only
wp-content/uploads/automotive-feed-import/
    import-log.txt                 # Runtime log file
```

## PHP 8 Modernizations
- **Constructor**: Uses `__construct()` instead of PHP 4 style
- **Visibility**: All methods use proper `public`/`private` keywords
- **Null Coalescing**: Uses `??` operator for array access (`$custom_fields['field'][0] ?? ''`)
- **Security**: Proper escaping (`esc_attr()`, `esc_html()`, `esc_url()`)
- **Nonces**: AJAX and admin actions protected with `wp_create_nonce()`

## When Adding Features
- **New XML fields**: Automatically imported as post meta; add to `get_inventory()` for display
- **New tokens**: Already supported - any XML field name works in templates
- **Theme mappings**: Add to `update_inventory()` switch statement
- **Custom intervals**: Add to `define_interval()` return array
- **Settings fields**: Use WordPress Settings API pattern in `register_settings()`
- **Admin notices**: Use `$this->log($message, true)` for error visibility
