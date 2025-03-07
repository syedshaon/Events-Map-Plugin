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

 function events_on_map_load_textdomain() {
    load_plugin_textdomain('events-on-map', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'events_on_map_load_textdomain');


 

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

    // Enqueue FullCalendar (Calendar)
    wp_enqueue_script('moment-js', plugin_dir_url(__FILE__) . 'moment.js', array('jquery'), null, true);
    wp_enqueue_script('fullcalendar-js', plugin_dir_url(__FILE__) . 'fullcalendar.js', array('jquery'), null, true);
    wp_enqueue_script('custom-calendar-locale-js', plugin_dir_url(__FILE__) . 'fullcalendarLocale.js', array('fullcalendar-js, moment-js'), null, true);
    wp_enqueue_script('custom-calendar-js', plugin_dir_url(__FILE__) . 'calendar.js', array('fullcalendar-js'), null, true);
    wp_enqueue_style('fullcalendar-css', plugin_dir_url(__FILE__) . 'fullcalendar.css');

    // Enqueue styles
    wp_enqueue_style('events-on-map-style', plugin_dir_url(__FILE__) . 'mapstyles.css');

    // ✅ Enqueue Google Maps API first
    wp_enqueue_script(
        'google-maps-api',
        'https://maps.googleapis.com/maps/api/js?key=' . $google_maps_api_key . '&libraries=places&callback=initMap&loading=async&defer',
        [],
        null,
        true
    );

    // ✅ Enqueue the frontend script
    wp_enqueue_script('events-on-map-frontend-js', plugin_dir_url(__FILE__) . 'mapFront.js', ['jquery'], null, true);

    $localized_months = [
                            esc_html__('January', 'events-on-map'),
                            esc_html__('February', 'events-on-map'),
                            esc_html__('March', 'events-on-map'),
                            esc_html__('April', 'events-on-map'),
                            esc_html__('May', 'events-on-map'),
                            esc_html__('June', 'events-on-map'),
                            esc_html__('July', 'events-on-map'),
                            esc_html__('August', 'events-on-map'),
                            esc_html__('September', 'events-on-map'),
                            esc_html__('October', 'events-on-map'),
                            esc_html__('November', 'events-on-map'),
                            esc_html__('December', 'events-on-map'),
                        ];

    // ✅ Localize events data AFTER enqueuing frontend script
    wp_localize_script('events-on-map-frontend-js', 'eventsData', [
        'events'        => get_option('events_on_map_addresses', []),
        'markerIcon'    => get_option('events_on_map_marker_icon', '') ,
        'months'     => $localized_months,
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
    

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['events_on_map_nonce'])) {
        if (!wp_verify_nonce($_POST['events_on_map_nonce'], 'events_on_map_save')) {
            wp_die(__('Security check failed', 'events-on-map'));
        }

       
          // Save custom marker icon
        $marker_icon = sanitize_text_field($_POST['events_on_map_marker_icon']);
        update_option('events_on_map_marker_icon', $marker_icon);
    }
    ?>
    <div class="wrap">
         

        <h1><?php esc_html_e('Events on Map', 'events-on-map'); ?></h1>
        <p><?php esc_html_e('This plugin allows you to display a map with upcoming events locations on map. Use the following shortcode to display the map on any page or post:', 'events-on-map'); ?> <code>[events_on_map]</code></p>

        
        <h3><?php esc_html_e('Instructions:', 'events-on-map'); ?></h3>
        <p><?php esc_html_e('1. Add your Google Maps API key below to enable the map functionality.', 'events-on-map'); ?></p>
        <p><?php esc_html_e('2. Add and manage events through the address manager in this settings page.', 'events-on-map'); ?></p>
        <p><?php esc_html_e('3. Use the shortcode <code>[events_on_map]</code> to embed the map on any page or post.', 'events-on-map'); ?></p>

        <hr>
        <form method="POST">
            <?php wp_nonce_field('events_on_map_save', 'events_on_map_nonce'); ?>

            <p style="font-size: 20px;"><?php esc_html_e('Google Maps API Key: ', 'events-on-map'); ?> <input type="text" name="events_on_map_api_key" value="<?php echo esc_attr($google_maps_api_key); ?>" placeholder="Enter your Google Maps API key" style="width: 100%; max-width: 400px; margin-left: 50px;"> <sup style="color: red; font-weight: bold ; ">*</sup></p>
            
       

          <p style="display: flex; gap:20px; align-items:center;" ><?php esc_html_e('Custom Marker Icon URL: ', 'events-on-map'); ?>
            <input type="text" name="events_on_map_marker_icon" id="events_on_map_marker_icon" value="<?php echo esc_attr(get_option('events_on_map_marker_icon', '')); ?>" placeholder="Enter or upload marker icon URL" style="width: 100%; max-width: 400px; margin-left: 50px;">
            <button type="button" class="button select-marker-icon"><?php esc_html_e('Select Image', 'events-on-map'); ?></button>
              <img id="marker-icon-preview" src="<?php echo esc_url(get_option('events_on_map_marker_icon', '')); ?>" style="max-width: 40px; display: <?php echo get_option('events_on_map_marker_icon', '') ? 'block' : 'none'; ?>; margin-top: 10px;">
        </p>
      

        <br><br>

            <h2>Event Details</h2>
            <table id="events-table" class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Event Name', 'events-on-map'); ?></th>
                        <th><?php esc_html_e('Start Date', 'events-on-map'); ?></th>
                        <th><?php esc_html_e('End Date', 'events-on-map'); ?></th>
                        <th><?php esc_html_e('Organizer', 'events-on-map'); ?></th>
                        <th><?php esc_html_e('Location', 'events-on-map'); ?></th> 
                        <th><?php esc_html_e('Event Image', 'events-on-map'); ?></th>
                        <th><?php esc_html_e('Action', 'events-on-map'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($addresses as $index => $address) : ?>
                        <tr>
                            <td><input required type="text" name="addresses[<?php echo $index; ?>][name]" value="<?php echo esc_attr($address['name'] ?? ''); ?>" /></td>
                            <td><input required type="date" name="addresses[<?php echo $index; ?>][start_date]" value="<?php echo esc_attr($address['start_date'] ?? ''); ?>" /></td>
                            <td><input type="date" name="addresses[<?php echo $index; ?>][end_date]" value="<?php echo esc_attr($address['end_date'] ?? ''); ?>" /></td>
                            <td><input type="text" name="addresses[<?php echo $index; ?>][organizer]" value="<?php echo esc_attr($address['organizer'] ?? ''); ?>" /></td>

                            <td><input type="text" name="addresses[<?php echo $index; ?>][location]" value="<?php echo esc_attr($address['location'] ?? ''); ?>" /></td>
                       
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

    $event_data = [];
    foreach ($events as $event) {
        if (!empty($event['start_date'])) {
            $event_data[] = [
                'title' => esc_html($event['name']),
                'start' => esc_html($event['start_date']),
                'end' => esc_html($event['end_date']),
                'location' => esc_html($event['location']),
                'image' => esc_url($event['image']),
                'organizer' => esc_html($event['organizer']),
            ];
        }
    }

    ob_start(); ?>

    <div class="events-container" style="display: flex;">
     
      <div id="map"  ></div>
       <div id="calendar"></div>
    </div>

    <script>
        var eventsData = <?php echo json_encode($event_data); ?>;
    </script>

    <?php return ob_get_clean();
}
add_shortcode('events_on_map', 'events_on_map_shortcode');


 