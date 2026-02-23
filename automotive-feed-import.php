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
		$log_entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
		
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
		// Check if file exists
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
		// loop through each field and update the database
		foreach ($unit as $key=>$value) 
		{
			update_post_meta( $post_id, $key, $value );
			
			// Maintain backward compatibility with Automotive Theme fields
			switch ($key) 
			{
				case 'manufacturer':
					update_post_meta( $post_id, 'manufacturer_level2_value', $value );
				break;
				
				case 'model_year':
					update_post_meta( $post_id, 'year_value', $value );
				break;
				
				case 'special_web_price':
					update_post_meta( $post_id, 'price_value', $value );
				break;
				
				case 'mileage':
					update_post_meta( $post_id, 'mileage_value', $value );
				break;
				
				case 'exterior_color':
					update_post_meta( $post_id, 'color_value', $value );
				break;
			}
		}
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
			}
		}
		
		$this->log("Import complete: {$created} created, {$updated} updated");
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
			'General Settings',
			array($this, 'render_general_section'),
			$this->plugin_slug
		);
		
		// XML File Path
		add_settings_field(
			'xml_file_path',
			'XML Feed File Path',
			array($this, 'render_xml_path_field'),
			$this->plugin_slug,
			'afi_general_section'
		);
		
		// Import Frequency
		add_settings_field(
			'import_frequency',
			'Import Frequency',
			array($this, 'render_frequency_field'),
			$this->plugin_slug,
			'afi_general_section'
		);
		
		// Post Format Section
		add_settings_section(
			'afi_format_section',
			'Post Format Settings',
			array($this, 'render_format_section'),
			$this->plugin_slug
		);
		
		// Post Title Format
		add_settings_field(
			'post_title_format',
			'Post Title Format',
			array($this, 'render_title_format_field'),
			$this->plugin_slug,
			'afi_format_section'
		);
		
		// Post Content Format
		add_settings_field(
			'post_content_format',
			'Post Content Format',
			array($this, 'render_content_format_field'),
			$this->plugin_slug,
			'afi_format_section'
		);
	}
	
	/**
	 * Render general section description
	 */
	public function render_general_section() {
		echo '<p>Configure XML feed import settings.</p>';
	}
	
	/**
	 * Render format section description
	 */
	public function render_format_section() {
		echo '<p>Customize how vehicle posts are formatted. Use tokens like {manufacturer}, {brand}, {model}, {model_year}, etc.</p>';
	}
	
	/**
	 * Render XML path field
	 */
	public function render_xml_path_field() {
		$value = $this->get_option('xml_file_path');
		echo '<input type="text" id="afi_xml_file_path" name="' . $this->plugin_slug . '_xml_file_path" value="' . esc_attr($value) . '" class="regular-text" />';
		echo '<button type="button" class="button" id="afi_browse_file">Browse Server</button>';
		echo '<p class="description">Paste the full server path to your vehicle feed file here, or click Browse Server to find it.</p>';
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
		echo '<p class="description">Choose how often WordPress checks your feed for new vehicles; if you change this later, you must deactivate and reactivate the plugin.</p>';
	}
	
	/**
	 * Render title format field
	 */
	public function render_title_format_field() {
		$value = $this->get_option('post_title_format', '{manufacturer} {brand}');
		echo '<input type="text" name="' . $this->plugin_slug . '_post_title_format" value="' . esc_attr($value) . '" class="regular-text" />';
		echo '<p class="description">Type how you want the vehicle post title to look, using tokens like {manufacturer} and {model_year}.</p>';
	}
	
	/**
	 * Render content format field
	 */
	public function render_content_format_field() {
		$value = $this->get_option('post_content_format', '{designation} {manufacturer} {brand} {model} {model_year}');
		echo '<textarea name="' . $this->plugin_slug . '_post_content_format" class="large-text" rows="3">' . esc_textarea($value) . '</textarea>';
		echo '<p class="description">Type how you want the main vehicle description to look, using tokens like {designation}, {manufacturer}, and {model}.</p>';
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
				<a href="?page=<?php echo $this->plugin_slug; ?>&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General Settings</a>
				<a href="?page=<?php echo $this->plugin_slug; ?>&tab=format" class="nav-tab <?php echo $active_tab === 'format' ? 'nav-tab-active' : ''; ?>">Post Format</a>
				<a href="?page=<?php echo $this->plugin_slug; ?>&tab=log" class="nav-tab <?php echo $active_tab === 'log' ? 'nav-tab-active' : ''; ?>">Import Log</a>
			</h2>
			
			<div class="afi-tab-content">
				<?php if ($active_tab === 'general'): ?>
					<!-- General Settings Tab -->
					<form method="post" action="options.php">
						<?php
						settings_fields($this->plugin_slug . '_settings');
						?>
						<table class="form-table">
							<?php
							// Manually render general settings fields
							do_settings_fields($this->plugin_slug, 'afi_general_section');
							?>
						</table>
						<?php submit_button(); ?>
					</form>
					
				<?php elseif ($active_tab === 'format'): ?>
					<!-- Post Format Tab -->
					<form method="post" action="options.php">
						<?php
						settings_fields($this->plugin_slug . '_settings');
						?>
						<h3>Post Format Settings</h3>
						<p>Customize how vehicle posts are formatted. Use tokens like {manufacturer}, {brand}, {model}, {model_year}, etc.</p>
						<table class="form-table">
							<?php
							// Manually render format settings fields
							do_settings_fields($this->plugin_slug, 'afi_format_section');
							?>
						</table>
						<?php submit_button(); ?>
					</form>
					
				<?php elseif ($active_tab === 'log'): ?>
					<!-- Import Log Tab -->
					<h2>Import Log</h2>
					<div style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto; margin-top: 20px;">
						<?php $this->display_log(); ?>
					</div>
					<p style="margin-top: 15px;">
						<button type="button" class="button" onclick="if(confirm('Are you sure you want to clear the log?')) { window.location.href='<?php echo admin_url('options-general.php?page=' . $this->plugin_slug . '&tab=log&action=clear_log&_wpnonce=' . wp_create_nonce('clear_log')); ?>'; }">Clear Log</button>
						<button type="button" class="button button-primary" onclick="if(confirm('Run import now?')) { window.location.href='<?php echo admin_url('options-general.php?page=' . $this->plugin_slug . '&tab=log&action=run_import&_wpnonce=' . wp_create_nonce('run_import')); ?>'; }">Run Import Now</button>
						<a href="<?php echo admin_url('options-general.php?page=' . $this->plugin_slug . '&action=download_sample&_wpnonce=' . wp_create_nonce('download_sample')); ?>" class="button" style="margin-left: 10px;">Download Sample XML</a>
					</p>
				<?php endif; ?>
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
	 * Handle admin actions
	 */
	public function handle_admin_actions() {
		if (!isset($_GET['page']) || $_GET['page'] !== $this->plugin_slug) {
			return;
		}
		
		if (!isset($_GET['action'])) {
			return;
		}
		
		$action = sanitize_text_field($_GET['action']);
		$tab = isset($_GET['tab']) ? '&tab=' . sanitize_text_field($_GET['tab']) : '';
		
		// Clear log
		if ($action === 'clear_log' && check_admin_referer('clear_log')) {
			if (file_exists($this->log_file)) {
				file_put_contents($this->log_file, '');
				add_action('admin_notices', function() {
					echo '<div class="notice notice-success is-dismissible"><p>Log cleared successfully.</p></div>';
				});
			}
			wp_redirect(admin_url('options-general.php?page=' . $this->plugin_slug . $tab));
			exit;
		}
		
		// Run import
		if ($action === 'run_import' && check_admin_referer('run_import')) {
			$result = $this->update_data();
			if ($result && $result['success']) {
				add_action('admin_notices', function() use ($result) {
					echo '<div class="notice notice-success is-dismissible"><p><strong>Import Successful!</strong> ' . $result['created'] . ' vehicles created, ' . $result['updated'] . ' updated.</p></div>';
				});
			} else {
				add_action('admin_notices', function() {
					echo '<div class="notice notice-error is-dismissible"><p><strong>Import Failed!</strong> Check the log below for details.</p></div>';
				});
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
			
			$vehicle_data = '<div class="vehicle-details" style="background: #f9f9f9; padding: 20px; margin: 20px 0; border: 1px solid #ddd;">';
			$vehicle_data .= '<h3 style="margin-top: 0;">Vehicle Specifications</h3>';
			$vehicle_data .= '<table style="width: 100%; border-collapse: collapse;">';
			
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
					$vehicle_data .= '<tr style="border-bottom: 1px solid #e0e0e0;">';
					$vehicle_data .= '<td style="padding: 10px; font-weight: 600; width: 30%;">' . esc_html($label) . '</td>';
					$vehicle_data .= '<td style="padding: 10px;">' . esc_html($value) . '</td>';
					$vehicle_data .= '</tr>';
				}
			}
			
			$vehicle_data .= '</table>';
			$vehicle_data .= '</div>';
			
			$content .= $vehicle_data;
		}
		
		return $content;
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
add_action('update_xml_event', array($afi, 'update_data'));
add_action('save_post_vehicles', array($afi, 'save_vehicle_meta'));
add_action('wp_ajax_afi_dismiss_banner', array($afi, 'dismiss_banner'));
add_action('wp_ajax_afi_browse_files', array($afi, 'ajax_browse_files'));
add_action('admin_enqueue_scripts', array($afi, 'enqueue_admin_scripts'));

// Filters
add_filter('cron_schedules', array($afi, 'define_interval'));
add_filter('the_content', array($afi, 'display_vehicle_frontend'));
add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($afi, 'add_plugin_action_links'));

?>