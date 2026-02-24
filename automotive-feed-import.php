<?php
/*
Plugin Name: Automotive Inventory Importer – Sync Car Dealer Feeds
Plugin URI: https://www.ibexoft.com/product/automotive-feed-import/
Description: Automatically update your car inventory on your website. No manual entry needed. Stop wasting hours uploading cars one by one.
Version: 2.1
Author: Muhammad Jawaid Shamshad - Ibexoft
Author URI: https://ibexoft.com
License: GNU Public License
Requires PHP: 8.0
*/

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

/**
 * A class to import vehicle data periodically and display in vehicles edit screen.
 * @author Muhammad Jawaid Shamshad
 *
 */
class AutomotiveFeedImport
{
	private $xml_file;
	private $log_file;
	private $plugin_slug = 'automotive-feed-import';
	private $survey_url = 'https://forms.gle/qEneb8ZeBxnFXuV78'; // Configurable survey URL
	
	/**
	 * Constructor - PHP 8 compatible
	 */
	public function __construct()
	{
		// Get settings or use defaults
		$this->xml_file = $this->get_option('xml_file_path');
		
		// Set up log file
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/automotive-feed-import';
		if (!file_exists($log_dir)) {
			wp_mkdir_p($log_dir);
		}
		$this->log_file = $log_dir . '/import-log.txt';
	}
	
	/**
	 * Helper to get plugin options with default fallback
	 */
	private function get_option($key, $default = '') {
		return get_option($this->plugin_slug . '_' . $key, $default);
	}
	
	/**
	 * Helper to update plugin options
	 */
	private function update_option($key, $value) {
		return update_option($this->plugin_slug . '_' . $key, $value);
	}
	
	/**
	 * Log errors and messages
	 */
	private function log($message, $is_error = false) {
		$timestamp = current_time('mysql');
		$level = $is_error ? 'ERROR' : 'INFO';
		$log_entry = '[' . $timestamp . '] [' . $level . '] ' . $message . PHP_EOL;
		
		// Write to log file
		file_put_contents($this->log_file, $log_entry, FILE_APPEND);
		
		// Show admin notice for errors
		if ($is_error) {
			add_action('admin_notices', function() use ($message) {
				echo '<div class="notice notice-error is-dismissible"><p><strong>Automotive Inventory Importer Error:</strong> ' . esc_html($message) . '</p></div>';
			});
		}
	}
	
	/**
	 * Register custom post type 'vehicles'
	 */
	public function register_vehicles_post_type() {
		$labels = array(
			'name'               => 'Vehicles',
			'singular_name'      => 'Vehicle',
			'menu_name'          => 'Vehicles',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Vehicle',
			'edit_item'          => 'Edit Vehicle',
			'new_item'           => 'New Vehicle',
			'view_item'          => 'View Vehicle',
			'search_items'       => 'Search Vehicles',
			'not_found'          => 'No vehicles found',
			'not_found_in_trash' => 'No vehicles found in Trash',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array('slug' => 'vehicles'),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-car',
			'supports'           => array('title', 'editor', 'thumbnail', 'custom-fields'),
		);

		register_post_type('vehicles', $args);
	}
	
	/**
	 * Migrate existing 'listing' posts to 'vehicles' post type
	 */
	public function migrate_listings_to_vehicles() {
		// Check if migration already done
		if ($this->get_option('migration_done')) {
			return;
		}
		
		global $wpdb;
		
		// Update post type from 'listing' to 'vehicles'
		$updated = $wpdb->update(
			$wpdb->posts,
			array('post_type' => 'vehicles'),
			array('post_type' => 'listing'),
			array('%s'),
			array('%s')
		);
		
		if ($updated !== false) {
			$this->update_option('migration_done', true);
			$this->log("Migrated {$updated} listings to vehicles post type");
		}
	}
	
	/**
	 * Initialize plugin
	 */
	public function init()
	{
		// Register post type
		$this->register_vehicles_post_type();
		
		// Migrate existing listings
		$this->migrate_listings_to_vehicles();
		
		// schedule an event
		$this->schedule();
		
		// Flush rewrite rules
		flush_rewrite_rules();
		
		// Set activation notice flag
		set_transient('afi_activation_notice', true, 60 * 60 * 24); // 24 hours
	}
	
	/**
	 * Display activation notice after plugin is activated
	 */
	public function display_activation_notice() {
		// Only show if activation flag is set
		if (!get_transient('afi_activation_notice')) {
			return;
		}

		// Only show to users who can manage options
		if (!current_user_can('manage_options')) {
			return;
		}

		// If this user already dismissed the notice, do not show again
		if (get_user_meta(get_current_user_id(), 'afi_activation_notice_dismissed', true)) {
			return;
		}

		?>
		<div class="notice notice-info is-dismissible" data-dismiss-type="activation">
			<p style="font-size: 16px;">
				<strong>Automotive Inventory Importer is ready!</strong>
				Ready to see your cars on your site?
				<a href="<?php echo esc_url( admin_url('options-general.php?page=' . $this->plugin_slug) ); ?>"
				   class="button button-primary" style="margin-left: 10px;">
					Start Your First Import
				</a>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Display transient admin notices (used after redirects)
	 */
	public function display_transient_notices() {
		$notice = get_transient('afi_admin_notice');
		if ($notice) {
			$type = isset($notice['type']) ? $notice['type'] : 'info';
			$message = isset($notice['message']) ? $notice['message'] : '';
			
			if ($message) {
				echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
			}
			
			// Delete transient so it only shows once
			delete_transient('afi_admin_notice');
		}
	}
	
	/**
	 * Uninitialize plugin
	 */
	public function uninit()
	{
		// clear all events
		$this->unschedule();
	}
	
	/**
	 * Clear schedule the plugin
	 */
	public function unschedule()
	{
		wp_clear_scheduled_hook('update_xml_event');
	}
	
	/**
	 * Schedule the plugin
	 */
	public function schedule()
	{
		// Get frequency from settings (default 10 minutes)
		$frequency = $this->get_option('import_frequency', 'tenminute');
		
		// check if event is not defined, then schedule one
		if( !wp_next_scheduled( 'update_xml_event' )){
			wp_schedule_event( time(), $frequency, 'update_xml_event' );
		}
	}
	
	/**
	 * Defines custom intervals including 10 minute default
	 * @return Interval array  
	 */
	public function define_interval($schedules)
	{
		$schedules['tenminute'] = array(
		      'interval'=> 60*10,
		      'display'=>  __('Once Every 10 Minutes')
		  );
		$schedules['fiveminute'] = array(
		      'interval'=> 60*5,
		      'display'=>  __('Once Every 5 Minutes')
		  );
		$schedules['fifteenminute'] = array(
		      'interval'=> 60*15,
		      'display'=>  __('Once Every 15 Minutes')
		  );
		$schedules['thirtyminute'] = array(
		      'interval'=> 60*30,
		      'display'=>  __('Once Every 30 Minutes')
		  );
		  
		return $schedules;
	}

	/**
	 * Load the data from xml file and return as an array
	 * @return Data array on success, false on failure
	 */
	public function load_xml()
	{
		// Handle remote URL (http/https)
		if (preg_match('#^https?://#i', $this->xml_file)) {
			$response = wp_remote_get($this->xml_file, array('timeout' => 30));
			
			if (is_wp_error($response)) {
				$this->log("Failed to fetch remote XML: " . $response->get_error_message(), true);
				return false;
			}
			
			$code = wp_remote_retrieve_response_code($response);
			if ($code < 200 || $code >= 300) {
				$this->log("Remote XML returned error code: {$code}", true);
				return false;
			}
			
			$body = wp_remote_retrieve_body($response);
			if (empty($body)) {
				$this->log("Remote XML file is empty: {$this->xml_file}", true);
				return false;
			}
			
			libxml_use_internal_errors(true);
			$xml = simplexml_load_string($body);
			
			if (!$xml) {
				$error_msg = "Failed loading XML from {$this->xml_file}";
				$this->log($error_msg, true);
				
				foreach(libxml_get_errors() as $error) {
					$this->log("XML Error: " . $error->message, true);
				}
				libxml_clear_errors();
				
				return false;
			}
		} else {
			// Handle local file path
			if (!file_exists($this->xml_file)) {
				$this->log("XML file not found: {$this->xml_file}", true);
				return false;
			}
			
			// load the xml file
			$xml = simplexml_load_file($this->xml_file);

			if (!$xml) 
			{
			    $error_msg = "Failed loading XML from {$this->xml_file}";
			    $this->log($error_msg, true);
			    
			    foreach(libxml_get_errors() as $error)
			    {
			        $this->log("XML Error: " . $error->message, true);
			    }
			        
			    return false;
		    }
		}
	    
		$units = array();
		
		// loop through the xml and generate array to return
		foreach($xml->children() as $child)
		{
			$unit = array();
			
			foreach($child as $grand_child)
			{
				$unit[$grand_child->getName()] = strip_tags($grand_child->asXML());
			}
			
			array_push($units, $unit);
		}
		
		$this->log("Successfully loaded " . count($units) . " units from XML");
		return $units;
	}
	
	/**
	 * Parse post format template with tokens
	 */
	private function parse_template($template, $unit) {
		// Replace tokens like {manufacturer}, {brand}, etc.
		$replacements = array();
		foreach ($unit as $key => $value) {
			$replacements['{' . $key . '}'] = $value;
		}
		return str_replace(array_keys($replacements), array_values($replacements), $template);
	}
	
	/**
	 * Inserts data into database
	 * @param Associative array containing data to be inserted in database
	 * @return boolean: Post Id on success, false on failure
	 */
	public function add_listing($unit)
	{
		// check for valid unit
		if(!isset($unit))
		{
			$this->log("Invalid unit data provided", true);
			return false;
		}
		
		// Get custom formats from settings or use defaults
		$title_template = $this->get_option('post_title_format', '{manufacturer} {brand}');
		$content_template = $this->get_option('post_content_format', '{designation} {manufacturer} {brand} {model} {model_year}');
		
		// Parse templates
		$title = $this->parse_template($title_template, $unit);
		$content = $this->parse_template($content_template, $unit);
		
		// define post object
		$new_post = array(
				    'post_title' 	=> $title,
				    'post_content' 	=> $content,
				    'post_status' 	=> 'publish',
					'post_type' 	=> 'vehicles',
				);
				
		// insert the post into the database
		$post_id = wp_insert_post( $new_post, true );
		
		// check if post added successfully
		if( is_wp_error($post_id) )
		{
			// error, cannot insert post
			$error_msg = $post_id->get_error_message();
			$this->log("Failed to create vehicle: " . $error_msg, true);
			return false;
		}

		$this->log("Created new vehicle (ID: {$post_id}): " . $title);
		return $post_id;
	}

	/**
	 * Add/Update data in database
	 * @param Post Id to update
	 * @param Unit data to be updated
	 */
	public function update_inventory($post_id, $unit)
	{
		// Get field mappings
		$mappings = $this->get_field_mappings();
		
		// If no mappings exist, use default behavior (import all fields as-is)
		if (empty($mappings)) {
			foreach ($unit as $key => $value) {
				update_post_meta($post_id, $key, $value);
				
				// Maintain backward compatibility with Automotive Theme fields
				$this->update_theme_compatibility_field($post_id, $key, $value);
			}
			return;
		}
		
		// Use mappings to import only enabled fields to their target fields
		foreach ($unit as $source_field => $value) {
			// Skip if field not in mappings or not enabled
			if (!isset($mappings[$source_field]) || !$mappings[$source_field]['enabled']) {
				continue;
			}
			
			$target_field = $mappings[$source_field]['target'];
			
			// Update the target meta field
			update_post_meta($post_id, $target_field, $value);
			
			// Also store with source field name for backward compatibility
			if ($target_field !== $source_field) {
				update_post_meta($post_id, $source_field, $value);
			}
			
			// Maintain backward compatibility with Automotive Theme fields
			$this->update_theme_compatibility_field($post_id, $source_field, $value);
		}
	}
	
	/**
	 * Update theme compatibility fields
	 * @param int $post_id Post ID
	 * @param string $key Field key
	 * @param mixed $value Field value
	 */
	private function update_theme_compatibility_field($post_id, $key, $value) {
		switch ($key) {
			case 'manufacturer':
				update_post_meta($post_id, 'manufacturer_level2_value', $value);
				break;
			
			case 'model_year':
				update_post_meta($post_id, 'year_value', $value);
				break;
			
			case 'special_web_price':
				update_post_meta($post_id, 'price_value', $value);
				break;
			
			case 'mileage':
				update_post_meta($post_id, 'mileage_value', $value);
				break;
			
			case 'exterior_color':
				update_post_meta($post_id, 'color_value', $value);
				break;
		}
	}

	/**
	 * Import images from feed into Media Library and attach to vehicle
	 */
	private function process_images($post_id, $unit)
	{
		$image_urls = array();

		// Combined list fields (comma / pipe / whitespace separated)
		foreach (array('image_urls', 'images', 'photos') as $field) {
			if (!empty($unit[$field]) && is_string($unit[$field])) {
				$parts = preg_split('/[,\|\s]+/', $unit[$field]);
				foreach ($parts as $url) {
					$url = trim($url);
					if ($url !== '' && preg_match('#^https?://#i', $url)) {
						$image_urls[] = esc_url_raw($url);
					}
				}
			}
		}

		// Individual image/photo fields: image1, image_2, photo3, etc.
		foreach ($unit as $key => $value) {
			if (!is_string($value) || $value === '') {
				continue;
			}
			if (preg_match('/^(image|photo)[_\-]?\d*$/i', (string) $key) && preg_match('#^https?://#i', $value)) {
				$image_urls[] = esc_url_raw($value);
			}
		}

		$image_urls = array_values(array_unique($image_urls));

		if (empty($image_urls)) {
			// No images in feed: ensure placeholder is set if needed
			if (!has_post_thumbnail($post_id)) {
				$placeholder_id = $this->ensure_placeholder_attachment();
				if ($placeholder_id) {
					set_post_thumbnail($post_id, $placeholder_id);
				}
			}
			return;
		}

		// Avoid re-importing the same URLs
		$already_imported = get_post_meta($post_id, '_afi_imported_image_urls', true);
		if (!is_array($already_imported)) {
			$already_imported = array();
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$new_imported = $already_imported;
		$index        = 0;

		foreach ($image_urls as $url) {
			if (in_array($url, $already_imported, true)) {
				$index++;
				continue;
			}

			$attachment_id = media_sideload_image($url, $post_id, null, 'id');

			if (is_wp_error($attachment_id)) {
				$this->log("Failed to import image {$url} for post {$post_id}: " . $attachment_id->get_error_message(), true);
				$index++;
				continue;
			}

			$new_imported[] = $url;
			// First imported image becomes featured image if none set yet
			if ($index === 0 && !has_post_thumbnail($post_id)) {
				set_post_thumbnail($post_id, $attachment_id);
			}

			$index++;
		}

		update_post_meta($post_id, '_afi_imported_image_urls', array_values(array_unique($new_imported)));

		// If, for some reason, no thumbnail exists after processing, set placeholder
		if (!has_post_thumbnail($post_id)) {
			$placeholder_id = $this->ensure_placeholder_attachment();
			if ($placeholder_id) {
				set_post_thumbnail($post_id, $placeholder_id);
			}
		}
	}

	/**
	 * Ensure a reusable placeholder image exists in Media Library and return its attachment ID
	 */
	private function ensure_placeholder_attachment()
	{
		// Try cached attachment ID first
		$cached_id = $this->get_option('placeholder_attachment_id');
		if ($cached_id) {
			$cached_id = (int) $cached_id;
			if ($cached_id > 0 && get_post($cached_id)) {
				return $cached_id;
			}
		}

		$placeholder_path = plugin_dir_path(__FILE__) . 'assets/car_placeholder.png';
		if (!file_exists($placeholder_path)) {
			$this->log('Placeholder image not found at ' . $placeholder_path, true);
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload_dir = wp_upload_dir();
		if (!empty($upload_dir['error'])) {
			$this->log('Failed to access upload directory for placeholder image: ' . $upload_dir['error'], true);
			return 0;
		}

		$filename   = 'car_placeholder.png';
		$dest_path  = trailingslashit($upload_dir['path']) . $filename;
		$dest_url   = trailingslashit($upload_dir['url']) . $filename;

		// Copy file into current uploads folder if it does not exist yet
		if (!file_exists($dest_path)) {
			if (!@copy($placeholder_path, $dest_path)) {
				$this->log('Failed to copy placeholder image into uploads directory.', true);
				return 0;
			}
		}

		$filetype = wp_check_filetype($filename, null);
		$attachment = array(
			'guid'           => $dest_url,
			'post_mime_type' => $filetype['type'],
			'post_title'     => 'Vehicle Placeholder Image',
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attach_id = wp_insert_attachment($attachment, $dest_path);
		if (is_wp_error($attach_id)) {
			$this->log('Failed to create placeholder attachment: ' . $attach_id->get_error_message(), true);
			return 0;
		}

		$attach_data = wp_generate_attachment_metadata($attach_id, $dest_path);
		wp_update_attachment_metadata($attach_id, $attach_data);

		$this->update_option('placeholder_attachment_id', $attach_id);

		return (int) $attach_id;
	}
	
	/**
	 * Fetches the data from xml then add/update database
	 */
	public function update_data()
	{
		global $wpdb;
		
		$this->log("Starting XML import process");
		
		// get the data from xml feed
		$units = $this->load_xml();
		
		if ($units === false) {
			return; // Error already logged
		}

		$created = 0;
		$updated = 0;
		
		foreach($units as $unit)
		{
			if (!isset($unit['stock_number'])) {
				$this->log("Unit missing stock_number, skipping", true);
				continue;
			}
			
			// check if listing already exist, if not create new listing			
			$post_id = $wpdb->get_var( $wpdb->prepare( 
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'stock_number' AND meta_value = %s",
				$unit['stock_number']
			));
			
			if( $post_id == NULL )
			{
				// listing does not exist, therefore, create a new listing
				$post_id = $this->add_listing($unit);
				if ($post_id) {
					$created++;
				}
			} else {
				$updated++;
			}
			
			// now add/update the plugin data against the listing
			if ($post_id) {
				$this->update_inventory($post_id, $unit);
				$this->process_images($post_id, $unit);
			}
		}
		
		$this->log("Import complete: {$created} created, {$updated} updated");
		
		// Remove activation banner after first successful import
		delete_transient('afi_activation_notice');
		
		return array('created' => $created, 'updated' => $updated, 'success' => true);
	}

	/**
	 * Fetch the data from database for the current listing
	 * @return Associative array containing data from database 
	 */
	public function get_inventory()
	{
		$unit = array();
		
		// fetch the inventory from database for current listing
		$custom_fields = get_post_custom();

		// generate array to return
	  	$unit['stock_number'] 		= array('Stock Number', 		$custom_fields['stock_number'][0] ?? ''		);
	  	$unit['body_type'] 			= array('Body Type', 			$custom_fields['body_type_value'][0] ?? ''	);
	  	$unit['mileage'] 			= array('Mileage', 				$custom_fields['mileage'][0] ?? ''			);
	  	$unit['designation'] 		= array('Designation', 			$custom_fields['designation'][0] ?? ''		); 
	  	$unit['special_web_price'] 	= array('Special Web Price', 	$custom_fields['special_web_price'][0] ?? ''	); 
	  	$unit['type']  				= array('Type', 				$custom_fields['type'][0] ?? ''				);
	  	$unit['manufacturer'] 		= array('Manufacturer', 		$custom_fields['manufacturer'][0] ?? ''		);
	  	$unit['brand'] 				= array('Brand', 				$custom_fields['brand'][0] ?? ''				);
	  	$unit['model'] 				= array('Model', 				$custom_fields['model'][0] ?? ''				);
	  	$unit['length'] 			= array('Length', 				$custom_fields['length'][0] ?? ''				);
	  	$unit['color'] 				= array('Color', 				$custom_fields['exterior_color'][0] ?? ''		);
	  	$unit['price'] 				= array('Price', 				$custom_fields['price_value'][0] ?? ''		);
	  	$unit['status'] 			= array('Status', 				$custom_fields['status'][0] ?? ''				);
	  		    
	    return $unit;
	}

	/**
	 * Display data on listing post
	 * @param Array of array containing text and value to be displayed
	 */
	public function display_inventory($unit)
	{
		echo '<table class="form-table">';
  		
		// loop through each field and display
		foreach ($unit as $key => $val) 
		{
	  		echo '<tr><th scope="row">';
		  	echo '<label for="afi_field_' . esc_attr($key) . '">';
		       echo esc_html($val[0]);
		  	echo '</label>';
		  	echo '</th><td>';  	
		  	echo '<input type="text" class="regular-text" id="afi_field_' . esc_attr($key) . '" name="afi_field[' . esc_attr($key) . ']" value="' . esc_attr($val[1]) . '" />';
		  	echo '</td></tr>';
		}

	  	echo '</table>';
	}
	
	/**
	 * Save vehicle post meta data
	 */
	public function save_vehicle_meta($post_id) {
		// Check if this is an autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		
		// Verify nonce
		if (!isset($_POST['afi_noncename']) || !wp_verify_nonce($_POST['afi_noncename'], plugin_basename(__FILE__))) {
			return;
		}
		
		// Check user permissions
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		
		// Save the fields
		if (isset($_POST['afi_field']) && is_array($_POST['afi_field'])) {
			foreach ($_POST['afi_field'] as $key => $value) {
				$sanitized_value = sanitize_text_field($value);
				update_post_meta($post_id, $key, $sanitized_value);
				
				// Update theme compatibility fields
				switch ($key) {
					case 'manufacturer':
						update_post_meta($post_id, 'manufacturer_level2_value', $sanitized_value);
					break;
					case 'model_year':
						update_post_meta($post_id, 'year_value', $sanitized_value);
					break;
					case 'special_web_price':
						update_post_meta($post_id, 'price_value', $sanitized_value);
					break;
					case 'mileage':
						update_post_meta($post_id, 'mileage_value', $sanitized_value);
					break;
					case 'exterior_color':
						update_post_meta($post_id, 'color_value', $sanitized_value);
					break;
				}
			}
		}
	}

	/**
	 * Adds a box to the main column on the Vehicle edit screen
	 */
	public function add_custom_box()
	{
		add_meta_box( 
	        'afi_vehicle_info',
	        __( 'Vehicle Information (Imported from Feed)', 'automotive-feed-import' ),
	        array($this, 'inner_custom_box'),
	        'vehicles',
	        'normal',
	        'high'
	    );
	}

	/**
	 * Fetches the data from database and prints on the vehicle edit screen
	 */
	public function inner_custom_box() 
	{
		// Use nonce for verification
	  	wp_nonce_field( plugin_basename( __FILE__ ), 'afi_noncename' );
	
	  	// get the information from database
	  	$unit = $this->get_inventory();
	  	
  		// display the unit
  		$this->display_inventory($unit);
	}
	
	/**
	 * Register settings page
	 */
	public function add_settings_page() {
		add_options_page(
			'Automotive Inventory Importer Settings',
			'Automotive Inventory Importer',
			'manage_options',
			$this->plugin_slug,
			array($this, 'render_settings_page')
		);
	}
	
	/**
	 * Register settings
	 */
	public function register_settings() {
		// Register settings
		register_setting($this->plugin_slug . '_settings', $this->plugin_slug . '_xml_file_path');
		register_setting($this->plugin_slug . '_settings', $this->plugin_slug . '_import_frequency');
		register_setting($this->plugin_slug . '_settings', $this->plugin_slug . '_post_title_format');
		register_setting($this->plugin_slug . '_settings', $this->plugin_slug . '_post_content_format');
		
		// General Settings Section
		add_settings_section(
			'afi_general_section',
			'Feed & Sync Settings',
			array($this, 'render_general_section'),
			$this->plugin_slug
		);
		
		// XML File Path
		add_settings_field(
			'xml_file_path',
			'Your Inventory Link',
			array($this, 'render_xml_path_field'),
			$this->plugin_slug,
			'afi_general_section'
		);
		
		// Import Frequency
		add_settings_field(
			'import_frequency',
			'How Often to Sync',
			array($this, 'render_frequency_field'),
			$this->plugin_slug,
			'afi_general_section'
		);
		
		// Post Format Section
		add_settings_section(
			'afi_format_section',
			'How Vehicle Pages Look',
			array($this, 'render_format_section'),
			$this->plugin_slug
		);
		
		// Post Title Format
		add_settings_field(
			'post_title_format',
			'Vehicle Page Title Template',
			array($this, 'render_title_format_field'),
			$this->plugin_slug,
			'afi_format_section'
		);
		
		// Post Content Format
		add_settings_field(
			'post_content_format',
			'Vehicle Description Template',
			array($this, 'render_content_format_field'),
			$this->plugin_slug,
			'afi_format_section'
		);
	}
	
	/**
	 * Extract available field names from the XML feed
	 * @return array|false Array of field names on success, false on failure
	 */
	public function extract_xml_fields() {
		if (empty($this->xml_file)) {
			return false;
		}
		
		// Load XML and get first unit to extract field names
		$units = $this->load_xml();
		
		if ($units === false || empty($units)) {
			return false;
		}
		
		// Get field names from first unit
		$first_unit = reset($units);
		$fields = array_keys($first_unit);
		
		return $fields;
	}
	
	/**
	 * Get available target field options for mapping
	 * @return array Target fields with labels
	 */
	public function get_available_target_fields() {
		return array(
			// Core vehicle fields
			'stock_number' => 'Stock Number',
			'vin' => 'VIN',
			'manufacturer' => 'Manufacturer',
			'brand' => 'Brand',
			'model' => 'Model',
			'model_year' => 'Year',
			'designation' => 'Condition/Designation',
			'type' => 'Type',
			'body_type' => 'Body Type',
			'style' => 'Style',
			
			// Pricing fields
			'special_web_price' => 'Special Web Price',
			'base_list' => 'Base List Price',
			'factory_list' => 'Factory List Price',
			'total_list' => 'Total List Price',
			'take_price' => 'Take Price',
			'show_web_price' => 'Show Web Price',
			
			// Physical attributes
			'mileage' => 'Mileage',
			'exterior_color' => 'Exterior Color',
			'interior_color' => 'Interior Color',
			'length' => 'Length',
			'width' => 'Width',
			'height' => 'Height',
			'weight' => 'Weight',
			
			// Identification
			'chassis_no' => 'Chassis Number',
			'serial_no' => 'Serial Number',
			
			// Status and location
			'status' => 'Status',
			'status_code' => 'Status Code',
			'lot_location' => 'Lot Location',
			'lot_location_code' => 'Lot Location Code',
			'gl_location_code' => 'GL Location Code',
			
			// Dates
			'received_date' => 'Received Date',
			'sold_date' => 'Sold Date',
			
			// Other
			'web_dealer_id' => 'Web Dealer ID',
			'description' => 'Description',
			'features' => 'Features',
			'options' => 'Options',
			'notes' => 'Notes',
			
			// Compatibility fields
			'manufacturer_level2_value' => 'Manufacturer (Theme Compat)',
			'year_value' => 'Year (Theme Compat)',
			'price_value' => 'Price (Theme Compat)',
			'mileage_value' => 'Mileage (Theme Compat)',
			'color_value' => 'Color (Theme Compat)',
			'body_type_value' => 'Body Type (Theme Compat)',
		);
	}
	
	/**
	 * Get saved field mappings or return default mappings
	 * @return array Field mappings
	 */
	public function get_field_mappings() {
		$mappings = $this->get_option('field_mappings', array());
		
		// If no mappings exist, return default 1:1 mapping
		if (empty($mappings)) {
			$fields = $this->extract_xml_fields();
			if ($fields !== false) {
				foreach ($fields as $field) {
					$mappings[$field] = array(
						'enabled' => true,
						'target' => $field
					);
				}
			}
		}
		
		return $mappings;
	}
	
	/**
	 * Save field mappings
	 * @param array $mappings Field mappings to save
	 */
	public function save_field_mappings($mappings) {
		$this->update_option('field_mappings', $mappings);
	}
	
	/**
	 * Check if feed URL has changed
	 * @param string $new_url New feed URL
	 * @return bool True if changed, false otherwise
	 */
	public function has_feed_url_changed($new_url) {
		$old_url = $this->get_option('last_feed_url', '');
		return $old_url !== $new_url && !empty($new_url);
	}
	
	/**
	 * Check if field mapping is needed
	 * @return bool True if mapping needed, false otherwise
	 */
	public function needs_field_mapping() {
		$mappings = $this->get_option('field_mappings', array());
		$feed_url = $this->get_option('xml_file_path', '');
		$last_feed_url = $this->get_option('last_feed_url', '');
		
		// Need mapping if no mappings exist or feed URL changed
		return empty($mappings) || ($feed_url !== $last_feed_url && !empty($feed_url));
	}
	
	/**
	 * Render general section description
	 */
	public function render_general_section() {
		echo '<p>Tell us where your inventory feed lives and how often to sync it.</p>';
	}
	
	/**
	 * Render format section description
	 */
	public function render_format_section() {
		echo '<p>Control how vehicle titles and descriptions are built using tokens like {manufacturer}, {brand}, {model}, {model_year}, etc.</p>';
	}
	
	/**
	 * Render XML path field
	 */
	public function render_xml_path_field() {
		$value = $this->get_option('xml_file_path');
		echo '<input type="text" id="afi_xml_file_path" name="' . $this->plugin_slug . '_xml_file_path" value="' . esc_attr($value) . '" class="regular-text" />';
		echo '<button type="button" class="button" id="afi_browse_file">Browse Server</button>';
		echo '<p class="description">Paste the full URL or server path to your inventory XML feed, or click Browse Server to locate the file on this site.</p>';
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#afi_browse_file').on('click', function(e) {
				e.preventDefault();
				var currentPath = $('#afi_xml_file_path').val() || '<?php echo esc_js(ABSPATH); ?>';
				
				// Create modal
				var modal = $('<div id="afi-file-browser-modal" style="display:none;"><div id="afi-file-browser-content"></div></div>');
				$('body').append(modal);
				
				// Open modal with WordPress media library style
				$.post(ajaxurl, {
					action: 'afi_browse_files',
					path: currentPath,
					nonce: '<?php echo wp_create_nonce('afi_browse_files'); ?>'
				}, function(response) {
					if (response.success) {
						$('#afi-file-browser-content').html(response.data.html);
						
						// Show modal using WordPress thickbox style
						tb_show('Browse Server Files', '#TB_inline?inlineId=afi-file-browser-modal&width=600&height=400');
					}
				});
			});
			
			// Handle file/folder clicks
			$(document).on('click', '.afi-file-item', function(e) {
				e.preventDefault();
				var path = $(this).data('path');
				$('#afi_xml_file_path').val(path);
				tb_remove();
			});
			
			$(document).on('click', '.afi-folder-item', function(e) {
				e.preventDefault();
				var path = $(this).data('path');
				
				$.post(ajaxurl, {
					action: 'afi_browse_files',
					path: path,
					nonce: '<?php echo wp_create_nonce('afi_browse_files'); ?>'
				}, function(response) {
					if (response.success) {
						$('#afi-file-browser-content').html(response.data.html);
					}
				});
			});
		});
		</script>
		<style>
		#afi-file-browser-modal { padding: 20px; }
		.afi-file-list { list-style: none; padding: 0; margin: 10px 0; max-height: 400px; overflow-y: auto; border: 1px solid #ddd; background: #fff; }
		.afi-file-list li { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; cursor: pointer; }
		.afi-file-list li:hover { background: #f0f0f0; }
		.afi-folder-item { color: #0073aa; font-weight: 600; }
		.afi-file-item { color: #333; }
		.afi-current-path { padding: 10px; background: #f5f5f5; margin-bottom: 10px; font-family: monospace; }
		</style>
		<?php
	}
	
	/**
	 * Render frequency field
	 */
	public function render_frequency_field() {
		$value = $this->get_option('import_frequency', 'tenminute');
		$frequencies = array(
			'fiveminute' => 'Every 5 Minutes',
			'tenminute' => 'Every 10 Minutes',
			'fifteenminute' => 'Every 15 Minutes',
			'thirtyminute' => 'Every 30 Minutes',
			'hourly' => 'Hourly',
			'twicedaily' => 'Twice Daily',
			'daily' => 'Daily'
		);
		
		echo '<select name="' . $this->plugin_slug . '_import_frequency">';
		foreach ($frequencies as $key => $label) {
			$selected = ($value === $key) ? 'selected' : '';
			echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">Choose how often we should check your feed for new or updated vehicles. If you change this later, deactivate and reactivate the plugin to apply the new schedule.</p>';
	}
	
	/**
	 * Render title format field
	 */
	public function render_title_format_field() {
		$value = $this->get_option('post_title_format', '{manufacturer} {brand}');
		echo '<input type="text" name="' . $this->plugin_slug . '_post_title_format" value="' . esc_attr($value) . '" class="regular-text" />';
		echo '<p class="description">Set the pattern for your vehicle page titles. Example: {manufacturer} {brand} {model_year}. Use any field from your feed inside {curly_braces}.</p>';
	}
	
	/**
	 * Render content format field
	 */
	public function render_content_format_field() {
		$value = $this->get_option('post_content_format', '{designation} {manufacturer} {brand} {model} {model_year}');
		echo '<textarea name="' . $this->plugin_slug . '_post_content_format" class="large-text" rows="3">' . esc_textarea($value) . '</textarea>';
		echo '<p class="description">Write the default description for each vehicle using tokens such as {designation}, {manufacturer}, {brand}, {model}, {model_year}, etc.</p>';
	}
	
	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Get active tab from URL or default to 'general'
		$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
		?>
		<div class="wrap">
			<h1>Automotive Inventory Importer Settings</h1>
			
			<?php $this->render_feedback_banner(); ?>
			<?php $this->render_survey_banner(); ?>
			
			<!-- Tab Navigation -->
			<h2 class="nav-tab-wrapper">
				<a href="?page=<?php echo $this->plugin_slug; ?>&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">Feed & Sync</a>
				<a href="?page=<?php echo $this->plugin_slug; ?>&tab=mapping" class="nav-tab <?php echo $active_tab === 'mapping' ? 'nav-tab-active' : ''; ?>">Field Mapping</a>
				<a href="?page=<?php echo $this->plugin_slug; ?>&tab=format" class="nav-tab <?php echo $active_tab === 'format' ? 'nav-tab-active' : ''; ?>">Page Templates</a>
				<a href="?page=<?php echo $this->plugin_slug; ?>&tab=log" class="nav-tab <?php echo $active_tab === 'log' ? 'nav-tab-active' : ''; ?>">Import History</a>
			</h2>
			
			<div class="afi-tab-content">
				<div class="afi-settings-layout">
					<div class="afi-settings-main">
						<?php if ($active_tab === 'general'): ?>
							<!-- General Settings Tab -->
							<form method="post" action="options.php">
								<?php
								settings_fields($this->plugin_slug . '_settings');
								// Hidden fields to preserve format settings when saving general settings
								$title_format = $this->get_option('post_title_format', '{manufacturer} {brand}');
								$content_format = $this->get_option('post_content_format', '{designation} {manufacturer} {brand} {model} {model_year}');
								echo '<input type="hidden" name="' . $this->plugin_slug . '_post_title_format" value="' . esc_attr($title_format) . '" />';
								echo '<input type="hidden" name="' . $this->plugin_slug . '_post_content_format" value="' . esc_attr($content_format) . '" />';
								?>
								<table class="form-table">
									<?php
									// Manually render general settings fields
									do_settings_fields($this->plugin_slug, 'afi_general_section');
									?>
								</table>
								<?php submit_button(); ?>
								<p>
									<button type="button" class="button" onclick="if(confirm('Test your saved inventory link now?')) { window.location.href='<?php echo esc_url( admin_url('options-general.php?page=' . $this->plugin_slug . '&tab=general&action=test_connection&_wpnonce=' . wp_create_nonce('test_connection')) ); ?>'; }">Test Inventory Link</button>
									<button type="button" class="button button-primary" style="margin-left: 8px;" onclick="if(confirm('Run a fresh import now?')) { window.location.href='<?php echo admin_url('options-general.php?page=' . $this->plugin_slug . '&tab=general&action=run_import&_wpnonce=' . wp_create_nonce('run_import')); ?>'; }">Sync Inventory Now</button>
									<a href="<?php echo admin_url('options-general.php?page=' . $this->plugin_slug . '&action=download_sample&_wpnonce=' . wp_create_nonce('download_sample')); ?>" class="button" style="margin-left: 8px;">Download Sample Feed</a>
									<a href="<?php echo admin_url('options-general.php?page=' . $this->plugin_slug . '&tab=mapping'); ?>" class="button" style="margin-left: 8px;">Configure Field Mapping</a>
								</p>
							</form>
							
						<?php elseif ($active_tab === 'format'): ?>
							<!-- Post Format Tab -->
							<form method="post" action="options.php">
								<?php
								settings_fields($this->plugin_slug . '_settings');
								// Hidden fields to preserve general settings when saving format settings
								$xml_file_path = $this->get_option('xml_file_path');
								$import_frequency = $this->get_option('import_frequency', 'tenminute');
								echo '<input type="hidden" name="' . $this->plugin_slug . '_xml_file_path" value="' . esc_attr($xml_file_path) . '" />';
								echo '<input type="hidden" name="' . $this->plugin_slug . '_import_frequency" value="' . esc_attr($import_frequency) . '" />';
								?>
								<h3>How Vehicle Pages Look</h3>
								<p>Decide how titles and descriptions should be built from your feed fields, using tokens like {manufacturer}, {brand}, {model}, {model_year}, etc.</p>
								<table class="form-table">
									<?php
									// Manually render format settings fields
									do_settings_fields($this->plugin_slug, 'afi_format_section');
									?>
								</table>
								<?php submit_button(); ?>
							</form>
							
						<?php elseif ($active_tab === 'mapping'): ?>
							<!-- Field Mapping Tab -->
							<h2>Field Mapping</h2>
							<p>Map fields from your XML feed to vehicle post meta fields. Enable or disable fields and select the target meta field from the dropdown for each feed field.</p>
							
							<?php
							// Show notification if redirected after feed URL change
							if (isset($_GET['feed_changed']) && $_GET['feed_changed'] === '1') {
								echo '<div class="notice notice-info is-dismissible"><p><strong>Feed URL updated!</strong> Please review and configure your field mappings below, then save them to start importing vehicles.</p></div>';
							}
							
							// Check if feed URL is set
							$feed_url = $this->get_option('xml_file_path', '');
							if (empty($feed_url)) {
								echo '<div class="notice notice-warning"><p><strong>No feed URL configured.</strong> Please configure your inventory link on the <a href="?page=' . $this->plugin_slug . '&tab=general">Feed & Sync</a> tab first.</p></div>';
							} else {
								// Extract fields from XML
								$xml_fields = $this->extract_xml_fields();
								$current_mappings = $this->get_field_mappings();
								
								if ($xml_fields === false) {
									echo '<div class="notice notice-error"><p><strong>Could not load XML feed.</strong> Please verify your feed URL is correct and accessible.</p></div>';
								} else {
									?>
									<form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
										<input type="hidden" name="action" value="afi_save_field_mappings" />
										<?php wp_nonce_field('afi_save_field_mappings', 'afi_mapping_nonce'); ?>
										
										<div style="background: #f0f0f1; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa;">
											<p style="margin: 0;"><strong>💡 Tip:</strong> Found <strong><?php echo count($xml_fields); ?> fields</strong> in your feed. Enable the ones you want to import and select a target meta field from the dropdown for each.</p>
										</div>
										
										<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
											<thead>
												<tr>
													<th style="width: 60px;">Enable</th>
													<th style="width: 40%;">Feed Field (Source)</th>
													<th style="width: 40%;">Target Meta Field</th>
													<th style="width: 200px;">Sample Value</th>
												</tr>
											</thead>
											<tbody>
												<?php
												// Load first unit to show sample values
												$units = $this->load_xml();
												$sample_unit = !empty($units) ? reset($units) : array();
												
												// Get available target fields
												$available_targets = $this->get_available_target_fields();
												
												foreach ($xml_fields as $field):
													$is_enabled = isset($current_mappings[$field]['enabled']) ? $current_mappings[$field]['enabled'] : true;
													$target_field = isset($current_mappings[$field]['target']) ? $current_mappings[$field]['target'] : $field;
													$sample_value = isset($sample_unit[$field]) ? $sample_unit[$field] : '';
													
													// Truncate long sample values
													if (strlen($sample_value) > 50) {
														$sample_value = substr($sample_value, 0, 50) . '...';
													}
												?>
												<tr>
													<td>
														<input type="checkbox" name="afi_mapping[<?php echo esc_attr($field); ?>][enabled]" value="1" <?php checked($is_enabled, true); ?> />
													</td>
													<td>
														<strong><?php echo esc_html($field); ?></strong>
													</td>
													<td>
														<select name="afi_mapping[<?php echo esc_attr($field); ?>][target]" class="regular-text" style="width: 100%;">
															<?php foreach ($available_targets as $target_key => $target_label): ?>
																<option value="<?php echo esc_attr($target_key); ?>" <?php selected($target_field, $target_key); ?>>
																	<?php echo esc_html($target_label); ?> (<?php echo esc_html($target_key); ?>)
																</option>
															<?php endforeach; ?>
															<!-- Add current field if not in list -->
															<?php if (!isset($available_targets[$field])): ?>
																<option value="<?php echo esc_attr($field); ?>" <?php selected($target_field, $field); ?>>
																	<?php echo esc_html($field); ?> (custom)
																</option>
															<?php endif; ?>
															<!-- Add selected field if different and not in list -->
															<?php if ($target_field !== $field && !isset($available_targets[$target_field])): ?>
																<option value="<?php echo esc_attr($target_field); ?>" selected>
																	<?php echo esc_html($target_field); ?> (custom)
																</option>
															<?php endif; ?>
														</select>
													</td>
													<td>
														<code style="font-size: 11px; color: #666;"><?php echo esc_html($sample_value); ?></code>
													</td>
												</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
										
										<?php submit_button('Save Field Mappings'); ?>
										
										<p style="margin-top: 20px;">
											<button type="button" class="button" onclick="if(confirm('Reset all mappings to default (1:1 mapping)?')) { document.getElementById('afi-reset-mappings').value='1'; this.form.submit(); }">Reset to Defaults</button>
											<input type="hidden" id="afi-reset-mappings" name="afi_reset_mappings" value="0" />
										</p>
									</form>
									<?php
								}
							}
							?>
							
						<?php elseif ($active_tab === 'log'): ?>
							<!-- Import Log Tab -->
							<h2>Import History</h2>
							<div style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto; margin-top: 20px;">
								<?php $this->display_log(); ?>
							</div>
							<p style="margin-top: 15px;">
								<button type="button" class="button" onclick="if(confirm('Clear all saved import history?')) { window.location.href='<?php echo admin_url('options-general.php?page=' . $this->plugin_slug . '&tab=log&action=clear_log&_wpnonce=' . wp_create_nonce('clear_log')); ?>'; }">Clear History</button>
							</p>
						<?php endif; ?>
					</div>
					<div class="afi-settings-sidebar">
						<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin-top: 0; border-left: 4px solid #0073aa;">
							<h3 style="margin-top: 0;">🚀 Quick Start Guide</h3>
							<ol>
								<li><strong>Enter your Inventory Link:</strong> Paste the URL or server path to your XML vehicle feed on the <strong>Feed &amp; Sync</strong> tab.</li>
								<li><strong>Configure Field Mapping:</strong> After saving your feed URL, map which fields to import on the <strong>Field Mapping</strong> tab.</li>
								<li><strong>Choose how often to sync:</strong> Pick a schedule that matches how often your provider updates the feed, then click <strong>Save Changes</strong>.</li>
								<li><strong>Sync &amp; review:</strong> Click <strong>Sync Inventory Now</strong> to run your first import, then review your vehicles under the <strong>Vehicles</strong> menu.</li>
							</ol>
							<p><em>Not sure about your link? Check your inventory provider's documentation or portal for your "XML Feed" or "Inventory Export" URL.</em></p>
						</div>
					</div>
				</div>
			</div>
			
			<style>
				.afi-tab-content {
					background: #fff;
					padding: 20px;
					border: 1px solid #ccd0d4;
					border-top: none;
					margin-top: 0;
				}
				.nav-tab-wrapper {
					margin-bottom: 0 !important;
				}
				.afi-settings-layout {
					display: flex;
					gap: 20px;
					align-items: flex-start;
					flex-wrap: wrap;
				}
				.afi-settings-main {
					flex: 1 1 0;
					min-width: 0;
				}
				.afi-settings-sidebar {
					flex: 0 0 280px;
					max-width: 100%;
				}
				@media (max-width: 900px) {
					.afi-settings-layout {
						flex-direction: column;
					}
					.afi-settings-sidebar {
						flex: 1 1 auto;
					}
				}
			</style>
		</div>
		<?php
	}
	
	/**
	 * Display import log
	 */
	private function display_log() {
		if (file_exists($this->log_file)) {
			$log_content = file_get_contents($this->log_file);
			if (!empty($log_content)) {
				echo '<pre style="margin: 0; white-space: pre-wrap;">' . esc_html($log_content) . '</pre>';
			} else {
				echo '<p>Log is empty.</p>';
			}
		} else {
			echo '<p>No log file found.</p>';
		}
	}
	
	/**
	 * Test XML feed connection and show an admin notice
	 */
	private function test_connection() {
		$path = $this->get_option('xml_file_path');

		if (empty($path)) {
			add_action('admin_notices', function () {
				echo '<div class="notice notice-error is-dismissible"><p>Please save your feed path first, then click Test Connection again.</p></div>';
			});
			return;
		}

		// Remote URL (http/https)
		if (preg_match('#^https?://#i', $path)) {
			$response = wp_remote_get($path, array('timeout' => 15));

			if (is_wp_error($response)) {
				$message = $response->get_error_message();
				add_action('admin_notices', function () use ($message) {
					echo '<div class="notice notice-error is-dismissible"><p>We could not reach your feed URL: ' . esc_html($message) . '.</p></div>';
				});
				return;
			}

			$code = wp_remote_retrieve_response_code($response);
			if ($code < 200 || $code >= 300) {
				add_action('admin_notices', function () use ($code) {
					echo '<div class="notice notice-error is-dismissible"><p>Your feed URL responded, but with an error code: ' . intval($code) . '.</p></div>';
				});
				return;
			}

			$body = wp_remote_retrieve_body($response);
			if ($body === '') {
				add_action('admin_notices', function () {
					echo '<div class="notice notice-error is-dismissible"><p>Your feed URL responded, but the file was empty.</p></div>';
				});
				return;
			}

			libxml_use_internal_errors(true);
			$xml = simplexml_load_string($body);
			if ($xml === false) {
				$errors  = libxml_get_errors();
				libxml_clear_errors();
				$first   = reset($errors);
				$err_msg = $first ? trim($first->message) : 'Unknown XML error.';
				add_action('admin_notices', function () use ($err_msg) {
					echo '<div class="notice notice-error is-dismissible"><p>We reached your feed URL, but the XML is not valid: ' . esc_html($err_msg) . '.</p></div>';
				});
				return;
			}
		} else {
			// Local file path
			if (!file_exists($path)) {
				add_action('admin_notices', function () use ($path) {
					echo '<div class="notice notice-error is-dismissible"><p>We could not find a feed file at this path: ' . esc_html($path) . '.</p></div>';
				});
				return;
			}

			if (!is_readable($path)) {
				add_action('admin_notices', function () use ($path) {
					echo '<div class="notice notice-error is-dismissible"><p>The feed file exists but cannot be read. Please check permissions on: ' . esc_html($path) . '.</p></div>';
				});
				return;
			}

			libxml_use_internal_errors(true);
			$xml = simplexml_load_file($path);
			if ($xml === false) {
				$errors  = libxml_get_errors();
				libxml_clear_errors();
				$first   = reset($errors);
				$err_msg = $first ? trim($first->message) : 'Unknown XML error.';
				add_action('admin_notices', function () use ($err_msg) {
					echo '<div class="notice notice-error is-dismissible"><p>We found your feed file, but the XML is not valid: ' . esc_html($err_msg) . '.</p></div>';
				});
				return;
			}
		}

		add_action('admin_notices', function () {
			echo '<div class="notice notice-success is-dismissible"><p>Success! We could connect to your feed and read the XML file.</p></div>';
		});
	}
	
	/**
	 * Handle admin actions
	 */
	public function handle_admin_actions() {
		if (!isset($_GET['page']) || $_GET['page'] !== $this->plugin_slug) {
			return;
		}
		
		if (!isset($_GET['action'])) {
			// Check if we should redirect to field mapping after settings save
			if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
				$current_feed_url = $this->get_option('xml_file_path', '');
				$last_feed_url = $this->get_option('last_feed_url', '');
				
				// If feed URL changed and not empty, redirect to mapping tab
				if (!empty($current_feed_url) && $current_feed_url !== $last_feed_url) {
					// Update the last feed URL
					$this->update_option('last_feed_url', $current_feed_url);
					
					// Redirect to mapping tab
					wp_redirect(admin_url('options-general.php?page=' . $this->plugin_slug . '&tab=mapping&feed_changed=1'));
					exit;
				}
			}
			return;
		}
		
		$action = sanitize_text_field($_GET['action']);
		$tab    = isset($_GET['tab']) ? '&tab=' . sanitize_text_field($_GET['tab']) : '';

		// Test connection (no redirect so user sees the result immediately)
		if ($action === 'test_connection' && check_admin_referer('test_connection')) {
			$this->test_connection();
			return;
		}
		
		// Clear log
		if ($action === 'clear_log' && check_admin_referer('clear_log')) {
			if (file_exists($this->log_file)) {
				file_put_contents($this->log_file, '');
				set_transient('afi_admin_notice', array(
					'type' => 'success',
					'message' => 'Log cleared successfully.'
				), 30);
			}
			wp_redirect(admin_url('options-general.php?page=' . $this->plugin_slug . $tab));
			exit;
		}
		
		// Run import
		if ($action === 'run_import' && check_admin_referer('run_import')) {
			$result = $this->update_data();
			if ($result && $result['success']) {
				set_transient('afi_admin_notice', array(
					'type' => 'success',
					'message' => '<strong>Import Successful!</strong> ' . $result['created'] . ' vehicles created, ' . $result['updated'] . ' updated.'
				), 30);
			} else {
				set_transient('afi_admin_notice', array(
					'type' => 'error',
					'message' => '<strong>Import Failed!</strong> Check the log below for details.'
				), 30);
			}
			wp_redirect(admin_url('options-general.php?page=' . $this->plugin_slug . $tab));
			exit;
		}
		
		// Download sample XML
		if ($action === 'download_sample' && check_admin_referer('download_sample')) {
			$sample_file = plugin_dir_path(__FILE__) . 'Web_Inventory_999.xml';
			if (file_exists($sample_file)) {
				header('Content-Type: application/xml');
				header('Content-Disposition: attachment; filename="sample-vehicle-feed.xml"');
				header('Content-Length: ' . filesize($sample_file));
				readfile($sample_file);
				exit;
			}
		}
	}
	
	/**
	 * Handle field mapping save
	 */
	public function handle_field_mapping_save() {
		// Verify nonce
		if (!isset($_POST['afi_mapping_nonce']) || !wp_verify_nonce($_POST['afi_mapping_nonce'], 'afi_save_field_mappings')) {
			wp_die('Security check failed');
		}
		
		// Check user permissions
		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized access');
		}
		
		// Check if reset was requested
		if (isset($_POST['afi_reset_mappings']) && $_POST['afi_reset_mappings'] === '1') {
			// Delete existing mappings to trigger default generation
			delete_option($this->plugin_slug . '_field_mappings');
			
			set_transient('afi_admin_notice', array(
				'type' => 'success',
				'message' => 'Field mappings reset to defaults successfully.'
			), 30);
			
			wp_redirect(admin_url('options-general.php?page=' . $this->plugin_slug . '&tab=mapping'));
			exit;
		}
		
		// Process mappings
		$mappings = array();
		if (isset($_POST['afi_mapping']) && is_array($_POST['afi_mapping'])) {
			foreach ($_POST['afi_mapping'] as $source_field => $config) {
				$source_field = sanitize_text_field($source_field);
				$target_field = isset($config['target']) ? sanitize_text_field($config['target']) : $source_field;
				$enabled = isset($config['enabled']) && $config['enabled'] === '1';
				
				$mappings[$source_field] = array(
					'enabled' => $enabled,
					'target' => $target_field
				);
			}
		}
		
		// Save mappings
		$this->save_field_mappings($mappings);
		
		set_transient('afi_admin_notice', array(
			'type' => 'success',
			'message' => 'Field mappings saved successfully. Import will now use these mappings.'
		), 30);
		
		wp_redirect(admin_url('options-general.php?page=' . $this->plugin_slug . '&tab=mapping'));
		exit;
	}
	
	/**
	 * Render feedback banner
	 */
	private function render_feedback_banner() {
		$dismissed = get_user_meta(get_current_user_id(), 'afi_feedback_dismissed', true);
		if ($dismissed) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible" data-dismiss-type="feedback">
			<p><strong>Enjoying Automotive Inventory Importer?</strong> Please consider leaving a review on the <a href="https://wordpress.org/plugins/automotive-feed-import/" target="_blank">WordPress Plugin Directory</a>. Your feedback helps us improve!</p>
		</div>
		<?php
	}
	
	/**
	 * Render survey banner
	 */
	private function render_survey_banner() {
		$dismissed = get_user_meta(get_current_user_id(), 'afi_survey_dismissed', true);
		if ($dismissed) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible" data-dismiss-type="survey">
			<p><strong>Help us improve!</strong> Take our quick 2-minute survey: <a href="<?php echo esc_url($this->survey_url); ?>" target="_blank">Take Survey</a></p>
		</div>
		<?php
	}
	
	/**
	 * Handle AJAX banner dismissal
	 */
	public function dismiss_banner() {
		check_ajax_referer('afi_dismiss_banner', 'nonce');
		
		$type = sanitize_text_field($_POST['type']);
		$user_id = get_current_user_id();
		
		if ($type === 'feedback') {
			update_user_meta($user_id, 'afi_feedback_dismissed', true);
		} elseif ($type === 'survey') {
			update_user_meta($user_id, 'afi_survey_dismissed', true);
		} elseif ($type === 'activation') {
			update_user_meta($user_id, 'afi_activation_notice_dismissed', true);
			delete_transient('afi_activation_notice');
		}
		
		wp_send_json_success();
	}
	
	/**
	 * Handle AJAX file browser
	 */
	public function ajax_browse_files() {
		check_ajax_referer('afi_browse_files', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Insufficient permissions');
		}
		
		$path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : ABSPATH;
		
		// Security: ensure path is within ABSPATH
		$real_path = realpath($path);
		$real_abspath = realpath(ABSPATH);
		
		if ($real_path === false || strpos($real_path, $real_abspath) !== 0) {
			$path = ABSPATH;
			$real_path = $real_abspath;
		}
		
		$html = '<div class="afi-current-path">Current: ' . esc_html($real_path) . '</div>';
		
		if (!is_readable($real_path)) {
			wp_send_json_error('Directory not readable');
		}
		
		$items = scandir($real_path);
		$html .= '<ul class="afi-file-list">';
		
		// Add parent directory link if not at root
		if ($real_path !== $real_abspath) {
			$parent = dirname($real_path);
			$html .= '<li><a href="#" class="afi-folder-item" data-path="' . esc_attr($parent) . '">📁 ..</a></li>';
		}
		
		// List directories first, then files
		$dirs = [];
		$files = [];
		
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') continue;
			
			$item_path = $real_path . DIRECTORY_SEPARATOR . $item;
			
			if (is_dir($item_path)) {
				$dirs[] = array('name' => $item, 'path' => $item_path);
			} elseif (pathinfo($item, PATHINFO_EXTENSION) === 'xml') {
				$files[] = array('name' => $item, 'path' => $item_path);
			}
		}
		
		// Sort and display
		sort($dirs);
		sort($files);
		
		foreach ($dirs as $dir) {
			$html .= '<li><a href="#" class="afi-folder-item" data-path="' . esc_attr($dir['path']) . '">📁 ' . esc_html($dir['name']) . '</a></li>';
		}
		
		foreach ($files as $file) {
			$html .= '<li><a href="#" class="afi-file-item" data-path="' . esc_attr($file['path']) . '">📄 ' . esc_html($file['name']) . '</a></li>';
		}
		
		if (empty($dirs) && empty($files)) {
			$html .= '<li style="color: #666; font-style: italic;">No XML files or folders found</li>';
		}
		
		$html .= '</ul>';
		
		wp_send_json_success(array('html' => $html));
	}
	
	/**
	 * Display vehicle data on frontend
	 */
	public function display_vehicle_frontend($content) {
		if (is_singular('vehicles') && is_main_query()) {
			global $post;
			
			$custom_fields = get_post_custom($post->ID);
			
			$vehicle_data = '<div class="vehicle-details">';
			$vehicle_data .= '<h3>Vehicle Specifications</h3>';
			$vehicle_data .= '<table class="vehicle-specs-table">';
			$vehicle_data .= '<thead><tr><th>Specification</th><th>Details</th></tr></thead>';
			$vehicle_data .= '<tbody>';
			
			$fields = array(
				'stock_number' => 'Stock Number',
				'manufacturer' => 'Manufacturer',
				'brand' => 'Brand',
				'model' => 'Model',
				'model_year' => 'Year',
				'designation' => 'Condition',
				'type' => 'Type',
				'mileage' => 'Mileage',
				'exterior_color' => 'Color',
				'length' => 'Length',
				'special_web_price' => 'Price',
				'status' => 'Status'
			);
			
			foreach ($fields as $key => $label) {
				$value = $custom_fields[$key][0] ?? '';
				if (!empty($value)) {
					$vehicle_data .= '<tr>';
					$vehicle_data .= '<td>' . esc_html($label) . '</td>';
					$vehicle_data .= '<td>' . esc_html($value) . '</td>';
					$vehicle_data .= '</tr>';
				}
			}
			
			$vehicle_data .= '</tbody>';
			$vehicle_data .= '</table>';
			$vehicle_data .= '</div>';
			
			$content .= $vehicle_data;
		}
		
		return $content;
	}
	
	/**
	 * Display vehicle info in archive/listing pages
	 */
	public function display_vehicle_archive($content) {
		if (is_post_type_archive('vehicles') || is_tax()) {
			if (in_the_loop() && is_main_query()) {
				global $post;
				$custom_fields = get_post_custom($post->ID);
				
				$vehicle_card = '<div class="vehicle-archive-card">';
				$vehicle_card .= '<div class="vehicle-info">';
				
				// Key info
				$manufacturer = $custom_fields['manufacturer'][0] ?? '';
				$model = $custom_fields['model'][0] ?? '';
				$year = $custom_fields['model_year'][0] ?? '';
				$price = $custom_fields['special_web_price'][0] ?? '';
				$mileage = $custom_fields['mileage'][0] ?? '';
				$color = $custom_fields['exterior_color'][0] ?? '';
				$designation = $custom_fields['designation'][0] ?? '';
				
				if ($price) {
					$vehicle_card .= '<div class="vehicle-price">$' . esc_html(number_format((float)$price)) . '</div>';
				}
				
				$vehicle_card .= '<div class="vehicle-quick-specs">';
				if ($year) $vehicle_card .= '<span class="spec-item"><strong>Year:</strong> ' . esc_html($year) . '</span>';
				if ($mileage) $vehicle_card .= '<span class="spec-item"><strong>Mileage:</strong> ' . esc_html(number_format((float)$mileage)) . ' mi</span>';
				if ($color) $vehicle_card .= '<span class="spec-item"><strong>Color:</strong> ' . esc_html($color) . '</span>';
				if ($designation) $vehicle_card .= '<span class="spec-item"><strong>Condition:</strong> ' . esc_html($designation) . '</span>';
				$vehicle_card .= '</div>';
				
				$vehicle_card .= '<a href="' . get_permalink() . '" class="view-details-btn">View Details →</a>';
				$vehicle_card .= '</div>'; // Close vehicle-info
				$vehicle_card .= '</div>'; // Close vehicle-archive-card
				
				return $vehicle_card;
			}
		}
		return $content;
	}
	
	/**
	 * Enqueue frontend styles for vehicle specifications and archive
	 */
	public function enqueue_frontend_styles() {
		if (is_singular('vehicles') || is_post_type_archive('vehicles') || is_tax()) {
			wp_add_inline_style('wp-block-library', '
				/* Professional Dealer Table Styling */
				.vehicle-specs-table {
					width: 100%;
					border-collapse: collapse;
					margin: 20px 0;
					font-family: sans-serif;
					box-shadow: 0 0 20px rgba(0,0,0,0.1);
				}
				.vehicle-specs-table th {
					background-color: #0073aa;
					color: #ffffff;
					text-align: left;
					padding: 12px 15px;
				}
				.vehicle-specs-table td {
					padding: 12px 15px;
					border-bottom: 1px solid #dddddd;
				}
				.vehicle-specs-table tbody tr:nth-of-type(even) {
					background-color: #f3f3f3;
				}
				.vehicle-details h3 {
					margin-top: 30px;
					margin-bottom: 10px;
					font-size: 24px;
				}
				
				/* Vehicle Archive/Listing Grid Styling */
				.vehicle-archive-card {
					background: #ffffff;
					border: 1px solid #e0e0e0;
					border-radius: 8px;
					overflow: hidden;
					box-shadow: 0 2px 8px rgba(0,0,0,0.1);
					transition: transform 0.3s ease, box-shadow 0.3s ease;
					margin-bottom: 30px;
				}
				.vehicle-archive-card:hover {
					transform: translateY(-5px);
					box-shadow: 0 4px 16px rgba(0,0,0,0.15);
				}
				.vehicle-archive-card .vehicle-info {
					padding: 20px;
				}
				.vehicle-archive-card .vehicle-price {
					font-size: 28px;
					font-weight: bold;
					color: #0073aa;
					margin-bottom: 15px;
				}
				.vehicle-archive-card .vehicle-quick-specs {
					display: flex;
					flex-wrap: wrap;
					gap: 15px;
					margin-bottom: 20px;
					padding-bottom: 15px;
					border-bottom: 1px solid #e0e0e0;
				}
				.vehicle-archive-card .spec-item {
					font-size: 14px;
					color: #555;
				}
				.vehicle-archive-card .spec-item strong {
					color: #333;
				}
				.vehicle-archive-card .view-details-btn {
					display: inline-block;
					background: #0073aa;
					color: #ffffff;
					padding: 10px 20px;
					border-radius: 4px;
					text-decoration: none;
					transition: background 0.3s ease;
				}
				.vehicle-archive-card .view-details-btn:hover {
					background: #005a87;
					color: #ffffff;
				}
				
				/* Grid layout for archive pages */
				@media (min-width: 768px) {
					.post-type-archive-vehicles .site-main,
					.tax-vehicles .site-main {
						display: grid;
						grid-template-columns: repeat(2, 1fr);
						gap: 30px;
					}
				}
				@media (min-width: 1024px) {
					.post-type-archive-vehicles .site-main,
					.tax-vehicles .site-main {
						grid-template-columns: repeat(3, 1fr);
					}
				}
			');
		}
	}
	
	/**
	 * Add Settings link to plugin action links
	 */
	public function add_plugin_action_links($links) {
		$settings_link = '<a href="' . admin_url('options-general.php?page=' . $this->plugin_slug) . '">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}
	
	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts($hook) {
		if ($hook !== 'settings_page_' . $this->plugin_slug) {
			return;
		}
		
		wp_enqueue_script('jquery');
		add_thickbox();
		wp_add_inline_script('jquery', "
			jQuery(document).ready(function($) {
				$('.notice[data-dismiss-type]').on('click', '.notice-dismiss', function() {
					var type = $(this).closest('.notice').data('dismiss-type');
					$.post(ajaxurl, {
						action: 'afi_dismiss_banner',
						type: type,
						nonce: '" . wp_create_nonce('afi_dismiss_banner') . "'
					});
				});
			});
		");
	}
	
} // end of class

/////////////////////////////////////////////////////////////////////

$afi = new AutomotiveFeedImport();

/* The activation hook is executed when the plugin is activated. */
register_activation_hook(__FILE__, array($afi, 'init'));

/* The deactivation hook is executed when the plugin is deactivated */
register_deactivation_hook(__FILE__, array($afi, 'uninit'));

// Actions
add_action('init', array($afi, 'register_vehicles_post_type'));
add_action('admin_init', array($afi, 'add_custom_box'), 1);
add_action('admin_init', array($afi, 'register_settings'));
add_action('admin_init', array($afi, 'handle_admin_actions'));
add_action('admin_menu', array($afi, 'add_settings_page'));
add_action('admin_notices', array($afi, 'display_activation_notice'));
add_action('admin_notices', array($afi, 'display_transient_notices'));
add_action('update_xml_event', array($afi, 'update_data'));
add_action('save_post_vehicles', array($afi, 'save_vehicle_meta'));
add_action('wp_ajax_afi_dismiss_banner', array($afi, 'dismiss_banner'));
add_action('wp_ajax_afi_browse_files', array($afi, 'ajax_browse_files'));
add_action('admin_enqueue_scripts', array($afi, 'enqueue_admin_scripts'));
add_action('admin_post_afi_save_field_mappings', array($afi, 'handle_field_mapping_save'));
add_action('wp_enqueue_scripts', array($afi, 'enqueue_frontend_styles'));

// Filters
add_filter('cron_schedules', array($afi, 'define_interval'));
add_filter('the_content', array($afi, 'display_vehicle_frontend'));
add_filter('the_content', array($afi, 'display_vehicle_archive'));
add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($afi, 'add_plugin_action_links'));

?>