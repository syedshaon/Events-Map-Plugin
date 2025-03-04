<?php
/**
 * Plugin Name: Events on Map
 * Description: Display upcoming events on Google Maps.
 * Version: 1.1
 * Author: Syed Mashiur Rahman
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Enqueue scripts and styles
function events_on_map_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_events-on-map') return;

    wp_enqueue_media(); // Load WP media uploader
    wp_enqueue_script('events-on-map-script', plugin_dir_url(__FILE__) . 'events-on-map.js', ['jquery'], null, true);
}
add_action('admin_enqueue_scripts', 'events_on_map_enqueue_scripts');

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
            

            <h2>Event Locations</h2>
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
        if ($event['name'] === $event_name && $event['date'] === $event_date) {
            unset($events[$index]); // Remove the event
            $events = array_values($events); // Re-index array
            update_option('events_on_map_addresses', $events);
            wp_send_json_success("Event deleted.");
        }
    }

    wp_send_json_error("Event not found.");
}











// Enqueue the plugin's CSS file in the WordPress admin area
function events_on_map_enqueue_styles($hook) {
    if ($hook !== 'toplevel_page_events-on-map') return; // Ensure it's loaded only on the plugin page

    // Enqueue the styles.css file
    wp_enqueue_style('events-on-map-style', plugin_dir_url(__FILE__) . 'styles.css');
}
add_action('admin_enqueue_scripts', 'events_on_map_enqueue_styles');