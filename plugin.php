<?php
/**
 * Plugin Name: Events on Map
 * Description: Display upcoming events on Google Maps.
 * Version: 1.0
 * Author: Syed Mashiur Rahman
 * Author URI: https://github.com/syedshaon
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

 

 

// // Enqueue scripts and styles
 

function events_on_map_enqueue_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_events-on-map') return; // Ensure it's loaded only on the plugin page

    // Enqueue styles
    wp_enqueue_style('events-on-map-style', plugin_dir_url(__FILE__) . 'styles.css');

        wp_enqueue_media(); // WordPress Media Uploader
    wp_enqueue_script('events-on-map-admin-js', plugin_dir_url(__FILE__) . 'events-on-map-admin.js', array('jquery'), null, true);

    // Enqueue backend JavaScript
    wp_enqueue_script('events-on-map-backend-js', plugin_dir_url(__FILE__) . 'events-on-map-backend.js', array('jquery'), null, true);

    // Localize script for AJAX
    wp_localize_script('events-on-map-backend-js', 'eventsData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
}
add_action('admin_enqueue_scripts', 'events_on_map_enqueue_admin_scripts');

function events_on_map_enqueue_scripts() {
    $google_maps_api_key = get_option('events_on_map_api_key', '');

        // Enqueue styles
    wp_enqueue_style('events-on-map-style', plugin_dir_url(__FILE__) . 'styles.css');

    // Enqueue Google Maps API
    wp_enqueue_script('google-maps-api', 'https://maps.googleapis.com/maps/api/js?key=' . $google_maps_api_key . '&libraries=places&callback=initMap&loading=async&defer', [], null, true);

    // Enqueue the frontend script
    wp_enqueue_script('events-on-map-frontend-js', plugin_dir_url(__FILE__) . 'mapFront.js', ['jquery'], null, true);

    // Pass the events data to JavaScript
    wp_localize_script('events-on-map-frontend-js', 'eventsData', [
        'events'        => get_option('events_on_map_addresses', []),
        'mapHeight'     => get_option('events_on_map_height', '500px'),
        'mapWidth'      => get_option('events_on_map_width', '100%'),
        'eventsTitle'   => get_option('events_on_map_events_title', 'Upcoming Events'),
        'markerIcon'    => get_option('events_on_map_marker_icon', '') // Send marker icon URL
    
    ]);
}
add_action('wp_enqueue_scripts', 'events_on_map_enqueue_scripts');


 

// Admin menu
function events_on_map_admin_menu() {
    add_menu_page('Events on Map', 'Events on Map', 'manage_options', 'events-on-map', 'events_on_map_options_page');
}
add_action('admin_menu', 'events_on_map_admin_menu');

// Admin page
function events_on_map_options_page() {
    if (!current_user_can('manage_options')) return;

    // Retrieve stored events
    $addresses = get_option('events_on_map_addresses', []);
    if (!is_array($addresses)) {
        $addresses = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['events_on_map_nonce'])) {
        if (!wp_verify_nonce($_POST['events_on_map_nonce'], 'events_on_map_save')) {
            wp_die(__('Security check failed', 'events-on-map'));
        }

        $addresses = isset($_POST['addresses']) ? $_POST['addresses'] : [];
        $sanitized_addresses = [];

       foreach ($addresses as $index => $event) {
        $sanitized_event = [
            'name'      => sanitize_text_field($event['name'] ?? ''),
            'start_date'=> sanitize_text_field($event['start_date'] ?? ''),
            'end_date'  => sanitize_text_field($event['end_date'] ?? ''),
            'location'  => sanitize_text_field($event['location'] ?? ''),
            'latitude'  => sanitize_text_field($event['latitude'] ?? ''),
            'longitude' => sanitize_text_field($event['longitude'] ?? ''),
            'image'     => sanitize_text_field($event['image'] ?? ''),
            'organizer' => sanitize_text_field($event['organizer'] ?? '') // New field for organizer
        ];

        $sanitized_addresses[] = $sanitized_event;
    }

        update_option('events_on_map_addresses', $sanitized_addresses);

        $api_key = sanitize_text_field($_POST['events_on_map_api_key']);
        update_option('events_on_map_api_key', $api_key);

        echo '<script>location.reload();</script>'; // Refresh page after save
    }

    $google_maps_api_key = get_option('events_on_map_api_key', '');
    // Retrieve the stored map settings
    $map_height = get_option('events_on_map_height', '500px');
    $map_width = get_option('events_on_map_width', '100%'); // Default width
    $events_title = get_option('events_on_map_events_title', 'Upcoming Events');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['events_on_map_nonce'])) {
        if (!wp_verify_nonce($_POST['events_on_map_nonce'], 'events_on_map_save')) {
            wp_die(__('Security check failed', 'events-on-map'));
        }

        // Sanitize and save settings
        $map_height = sanitize_text_field($_POST['events_on_map_height']);
        update_option('events_on_map_height', $map_height);

        $map_width = sanitize_text_field($_POST['events_on_map_width']);
        update_option('events_on_map_width', $map_width);

        $events_title = sanitize_text_field($_POST['events_on_map_events_title']);
        update_option('events_on_map_events_title', $events_title);
          // Save custom marker icon
        $marker_icon = sanitize_text_field($_POST['events_on_map_marker_icon']);
        update_option('events_on_map_marker_icon', $marker_icon);
    }
    ?>
    <div class="wrap">
        <h1>Events on Map</h1>
        <p>This plugin allows you to display a map with upcoming events locations on map. Use the following shortcode to display the map on any page or post: <code>[events_on_map]</code></p>
        
        <h3>Instructions:</h3>
        <p>1. Add your Google Maps API key below to enable the map functionality.</p>
        <p>2. Add and manage events through the address manager in this settings page.</p>
        <p>3. Use the shortcode <code>[events_on_map]</code> to embed the map on any page or post.</p>

        <hr>
        <form method="POST">
            <?php wp_nonce_field('events_on_map_save', 'events_on_map_nonce'); ?>

            <p style="font-size: 20px;">Google Maps API Key: <input type="text" name="events_on_map_api_key" value="<?php echo esc_attr($google_maps_api_key); ?>" placeholder="Enter your Google Maps API key" style="width: 100%; max-width: 400px; margin-left: 50px;"> <sup style="color: red; font-weight: bold ; ">*</sup></p>
            
          <!-- Add Map Height Field -->
          <p >Map Height: 
              <input type="text" name="events_on_map_height" value="<?php echo esc_attr($map_height); ?>" placeholder="e.g. 500px or 60vh" style="width: 100%; max-width: 400px; margin-left: 50px;">
          </p>

          <!-- Add Map Width Field -->
          <p >Map Width: 
              <input type="text" name="events_on_map_width" value="<?php echo esc_attr($map_width); ?>" placeholder="e.g. 100% or 800px" style="width: 100%; max-width: 400px; margin-left: 50px;">
          </p>

          <!-- Add Upcoming Events Title Field -->
          <p >Upcoming Events Title: 
              <input type="text" name="events_on_map_events_title" value="<?php echo esc_attr($events_title); ?>" placeholder="Enter title for upcoming events" style="width: 100%; max-width: 400px; margin-left: 50px;">
          </p>

          <p style="display: flex; gap:20px; align-items:center;" >Custom Marker Icon URL:
            <input type="text" name="events_on_map_marker_icon" id="events_on_map_marker_icon" value="<?php echo esc_attr(get_option('events_on_map_marker_icon', '')); ?>" placeholder="Enter or upload marker icon URL" style="width: 100%; max-width: 400px; margin-left: 50px;">
            <button type="button" class="button select-marker-icon">Select Image</button>
              <img id="marker-icon-preview" src="<?php echo esc_url(get_option('events_on_map_marker_icon', '')); ?>" style="max-width: 40px; display: <?php echo get_option('events_on_map_marker_icon', '') ? 'block' : 'none'; ?>; margin-top: 10px;">
        </p>
      

        <br><br>

            <h2>Event Details</h2>
            <table id="events-table" class="widefat">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Organizer</th>
                        <th>Location</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Event Image</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($addresses as $index => $address) : ?>
                        <tr>
                            <td><input type="text" name="addresses[<?php echo $index; ?>][name]" value="<?php echo esc_attr($address['name'] ?? ''); ?>" /></td>
                            <td><input type="date" name="addresses[<?php echo $index; ?>][start_date]" value="<?php echo esc_attr($address['start_date'] ?? ''); ?>" /></td>
                            <td><input type="date" name="addresses[<?php echo $index; ?>][end_date]" value="<?php echo esc_attr($address['end_date'] ?? ''); ?>" /></td>
                            <td><input type="text" name="addresses[<?php echo $index; ?>][organizer]" value="<?php echo esc_attr($address['organizer'] ?? ''); ?>" /></td>

                            <td><input type="text" name="addresses[<?php echo $index; ?>][location]" value="<?php echo esc_attr($address['location'] ?? ''); ?>" /></td>
                            <td><input type="text" name="addresses[<?php echo $index; ?>][latitude]" value="<?php echo esc_attr($address['latitude'] ?? ''); ?>" /></td>
                            <td><input type="text" name="addresses[<?php echo $index; ?>][longitude]" value="<?php echo esc_attr($address['longitude'] ?? ''); ?>" /></td>
                            <td style="display: flex; align-items: center; gap: 10px;">
                              <img src="<?php echo esc_url($address['image']); ?>" width="50" class="event-image-preview" style="margin-top:5px; <?php echo empty($address['image']) ? 'display:none;' : ''; ?>">
                                <input type="hidden" name="addresses[<?php echo $index; ?>][image]" class="event-image-url" value="<?php echo esc_attr($address['image'] ?? ''); ?>">
                                <button type="button" class="button select-event-image">Select Image</button>
                                 
                                
                            </td>
                            <td><button type="button" class="remove-event button">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <br>
            <button type="button" id="add-event" class="button button-primary">Add New Event</button>
            <br><br>
            <input style="display: block; width: 50%; margin-left: auto; margin-right: auto ;" type="submit" class="button button-primary" value="Save Changes">
        </form>
    </div>
<?php
}

add_action('wp_ajax_delete_event', 'events_on_map_delete_event');
function events_on_map_delete_event() {
    if (!isset($_POST['event_name']) || !isset($_POST['event_date'])) {
        wp_send_json_error("Invalid request.");
    }

    $event_name = sanitize_text_field($_POST['event_name']);
    $event_date = sanitize_text_field($_POST['event_date']);

    $events = get_option('events_on_map_addresses', []);

    // Find the event in the array
    foreach ($events as $index => $event) {
        if ($event['name'] === $event_name && $event['start_date'] === $event_date) {
            unset($events[$index]); // Remove the event
            $events = array_values($events); // Re-index array
            update_option('events_on_map_addresses', $events);
            wp_send_json_success("Event deleted.");
        }
    }

    wp_send_json_error("Event not found.");
}

 

// Define the shortcode for displaying the map and events
 function events_on_map_shortcode($atts) {
    $events = get_option('events_on_map_addresses', []);
    if (!is_array($events)) {
        return '<p>No events available.</p>';
    }

    $upcoming_events = [];
    $current_time = current_time('Y-m-d H:i:s');
    foreach ($events as $event) {
        if ($event['start_date'] >= $current_time) {
            $upcoming_events[] = $event;
        }
    }

    // $upcoming_events = array_slice($upcoming_events, 0, 10);

    // Retrieve settings
    $map_height = esc_attr(get_option('events_on_map_height', '1000px'));
    
    $map_width = esc_attr(get_option('events_on_map_width', '100%'));
    $events_title = esc_html(get_option('events_on_map_events_title', 'Upcoming Events'));

    ob_start();
    ?>
    <div id="events-on-map-container" >
       <div id="map" style=" height: <?php echo $map_height?$map_height:"1000px" ?>; width: <?php echo $map_width?$map_width:"100%"; ?>;"></div>
        <div id="events-on-map-events-list"  >
          <?php if (empty($upcoming_events)) : ?>
                <p>No upcoming events available.</p>
            <?php endif; ?>
            <?php
            
            $events_title && print('<h4 id="events-title">' . $events_title . '</h4>');
            ?>
            
            <ul class="events-list">
                <?php foreach ($upcoming_events as $event) : ?>
                    <li>
                        <div class="image-in-front">
                            <img src="<?php echo esc_url($event['image']); ?>" alt="<?php echo esc_attr($event['name']); ?>">
                        </div>
                        <div class="details">
                          <strong><?php echo esc_html($event['name']); ?></strong><br>
                          <em><?php echo esc_html(date("d F Y", strtotime($event['start_date']))); ?> to <?php echo esc_html(date("d F Y", strtotime($event['end_date']))); ?> </em><br>
                          <strong>Organizer:</strong> <?php echo esc_html($event['organizer']); ?><br>
                          <!-- placeholder.png show from plugin  -->
                            
                         
                          <a href="javascript:void(0);" class="view-event-marker" data-lat="<?php echo esc_attr($event['latitude']); ?>" data-lng="<?php echo esc_attr($event['longitude']); ?>"> <img src="<?php echo plugin_dir_url(__FILE__) . 'placeholder.png'; ?>" alt="Event Image" style="width: 18px; height: auto;">   <?php echo esc_html($event['location']); ?>  </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
       
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('events_on_map', 'events_on_map_shortcode');
