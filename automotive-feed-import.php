<?php
/*
Plugin Name: Automotive Feed Import Plugin
Plugin URI: http://www.ibexoft.com/product/automotive-feed-import/
Description: Imports vehicle data from XML feed into database for Automotive theme. It checks the XML feed every 10 minutes and add/update the data in database. This imported data is then displayed in every listing edit screen.
Version: 0.1
Author: Muhammad Jawaid Shamshad
Author URI: http://www.ibexoft.com
License: GNU Public License
*/

/**
 * A class to import vehicle data periodically and display in listing edit screen.
 * @author Muhammad Jawaid Shamshad
 *
 */
class AutomotiveFeedImport 
{
	var $xml_file;		// xml file path

	/**
	 * Constructor
	 */
	function AutomotiveFeedImport()
	{
		// TODO: this should be set from options/settings page
		$this->xml_file = plugin_dir_path(__FILE__)."Web_Inventory_999.xml";
	}
	
	/**
	 * Initialize plugin
	 */
	function init()
	{
		// schedule an event
		$this->schedule();
	}
	
	/**
	 * Uninitialize plugin
	 */
	function uninit()
	{
		// clear all events
		$this->unschedule();
	}
	
	/**
	 * Clear schedule the plugin
	 */
	function unschedule()
	{
		wp_clear_scheduled_hook('update_xml_event');
	}
	
	/**
	 * Schedule the plugin
	 */
	function schedule()
	{
		// check if event is not defined, then schedule one
		if( !wp_next_scheduled( 'update_xml_event' )){
			wp_schedule_event( time(), 'tenminute', 'update_xml_event' );
		}
	}
	
	/**
	 * Defines the 10 minute interval
	 * @return Interval array  
	 */
	function define_interval()
	{
		$schedules['tenminute'] = array(
		      'interval'=> 60*10,
		      'display'=>  __('Once Every 10 Minutes')
		  );
		  
		return $schedules;
	}

	/**
	 * Load the data from xml file and return as an array
	 * @return Data array on success, false on failure
	 */
	function load_xml()
	{
		// load the xml file
		$xml = simplexml_load_file($this->xml_file);

		if (!$xml) 
		{
		    echo "Failed loading XML\n";
		    
		    foreach(libxml_get_errors() as $error)
		    {
		        echo "\t", $error->message;
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
		
		return $units;
	}
	
	/**
	 * Inserts data into database
	 * @param Associative array containing data to be inserted in database
	 * @return boolean: Post Id on success, false on failure
	 */
	function add_listing($unit)
	{
		// check for valid unit
		if(!isset($unit))
		{
			return false;
		}
		
		// define post object
		$new_post = array(
				    'post_title' 	=> $unit['manufacturer'] . " " . $unit['brand'],
				    'post_content' 	=> $unit['designation'] . " " . $unit['manufacturer'] . " " . $unit['brand'] . " " . $unit['model'] . " " . $unit['model_year'],
				    'post_status' 	=> 'publish',
					'post_type' 	=> 'listing',
				);
				
		// insert the post into the database
		$post_id = wp_insert_post( $new_post, true );
		
		// check if post added successfully
		if( is_wp_error($post_id) )
		{
			// error, cannot insert post
			echo $post_id->get_error_message();
			return false;
		}

		return $post_id;
	}

	/**
	 * Add/Update data in database
	 * @param Post Id to update
	 * @param Unit data to be updated
	 */
	function update_inventory($post_id, $unit)
	{
		// loop through each field and update the database
		foreach ($unit as $key=>$value) 
		{
			update_post_meta( $post_id, $key, $value );
			
			// Note: Following data is duplicated in XML feed and fields provided by the Automotive Theme
			//			Theme data is untouched, this can be changed later on after having requirements cleared

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
				
				default:
					;
				break;
			}
		}
	}
	
	/**
	 * Fetches the data from xml then add/update database
	 */
	function update_data()
	{
		global $wpdb;
		
		// get the data from xml feed
		$units = $this->load_xml();

		foreach($units as $unit)
		{
			// check if listing already exist, if not create new listing			
			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta where meta_key = 'stock_number' AND meta_value = '" . $unit['stock_number'] . "';" ) );
			
			if( $post_id == NULL )
			{
				// listing does not exist, therefore, create a new listing
				$post_id = $this->add_listing($unit);
			}
			
			// now add/update the plugin data against the listing
			$this->update_inventory($post_id, $unit);
		}
	}

	/**
	 * Fetch the data from database for the current listing
	 * @return Associative array containing data from database 
	 */
	function get_inventory()
	{
		$unit = array();
		
		// fetch the inventory from database for current listing
		$custom_fields = get_post_custom();

		// generate array to return
	  	$unit['stock_number'] 		= array('Stock Number', 		$custom_fields['stock_number'][0]		);
	  	$unit['body_type'] 			= array('Body Type', 			$custom_fields['body_type_value'][0]	);
	  	$unit['mileage'] 			= array('Mileage', 				$custom_fields['mileage'][0]			);
	  	$unit['designation'] 		= array('Designation', 			$custom_fields['designation'][0]		); 
	  	$unit['special_web_price'] 	= array('Special Web Price', 	$custom_fields['special_web_price'][0]	); 
	  	$unit['type']  				= array('Type', 				$custom_fields['type'][0]				);
	  	$unit['manufacturer'] 		= array('Manfacturer', 			$custom_fields['manufacturer'][0]		);
	  	$unit['brand'] 				= array('Brand', 				$custom_fields['brand'][0]				);
	  	$unit['model'] 				= array('Modle', 				$custom_fields['model'][0]				);
	  	$unit['length'] 			= array('Length', 				$custom_fields['length'][0]				);
	  	$unit['color'] 				= array('Color', 				$custom_fields['exterior_color'][0]		);
	  	$unit['price'] 				= array('Price', 				$custom_fields['price_value'][0]		);
	  	$unit['status'] 			= array('Status', 				$custom_fields['status'][0]				);
	  		    
	    return $unit;
	}

	/**
	 * Display data on listing post
	 * @param Array of array containing text and value to be displayed
	 */
	function display_inventory($unit)
	{
		echo '<table>';
  		
		// loop through each field and display
		foreach ($unit as $key => $val) 
		{
	  		echo '<tr><td>';
		  	echo '<label for="myplugin_new_field">';
		       _e($val[0], 'myplugin_textdomain' );
		  	echo '</label> ';
		  	echo '</td><td>';	  	
		  	echo '<input type="text" id="myplugin_new_field" name="myplugin_new_field" value="'.$val[1].'" size="25" readonly="readonly" />';
		  	echo '</td></tr>';
		}

	  	echo '</table>';
	}

	/**
	 * Adds a box to the main column on the Listing edit screen
	 */
	function add_custom_box()
	{
		add_meta_box( 
	        'myplugin_sectionid',
	        __( 'Vehicle information', 'myplugin_textdomain' ),
	        array(&$this, 'inner_custom_box'),
	        'listing' 
	    );
	}

	/**
	 * Fetches the data from database and prints on the listing edit screen
	 */
	function inner_custom_box() 
	{
		// Use nonce for verification
	  	wp_nonce_field( plugin_basename( __FILE__ ), 'myplugin_noncename' );
	
	  	// get the information from database
	  	$unit = $this->get_inventory();
	  	
  		// display the unit
  		$this->display_inventory($unit);
	}
	
} // end of class

/////////////////////////////////////////////////////////////////////

$afi = new AutomotiveFeedImport();

/* The activation hook is executed when the plugin is activated. */
register_activation_hook(__FILE__, array(&$afi, 'init'));			// initialize plugin

/* The deactivation hook is executed when the plugin is deactivated */
register_deactivation_hook(__FILE__, array(&$afi, 'uninit'));		// uninitialize plugin

// Actions
add_action('admin_init', array(&$afi, 'add_custom_box'), 1);		// to display data listing edit screen
add_action('update_xml_event', array(&$afi, 'update_data'));		// check for and update every 10 min

// Filters
add_filter('cron_schedules', array(&$afi, 'define_interval'));		// define custom 10 min interval

?>