<?php
/**
 * Plugin Name: Events on Map
 * Description: Display upcoming events on Google Maps.
 * Version: 1.0
 * Author: Syed Mashiur Rahman
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Enqueue scripts and styles
function events_on_map_enqueue_scripts() {
    $google_maps_api_key = get_option('events_on_map_api_key', '');

    wp_enqueue_script('google-maps-api', 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($google_maps_api_key) . '&callback=initMap&v=weekly&libraries=places', [], null, true);
    wp_enqueue_script('custom-map-script', plugin_dir_url(__FILE__) . 'newmap.js', ['google-maps-api'], null, true);
}
add_action('wp_enqueue_scripts', 'events_on_map_enqueue_scripts');

// Admin menu
function events_on_map_admin_menu() {
    add_menu_page('Events on Map', 'Events on Map', 'manage_options', 'events-on-map', 'events_on_map_options_page');
}
add_action('admin_menu', 'events_on_map_admin_menu');

// Admin page
function events_on_map_options_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

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
                'date'      => sanitize_text_field($event['date'] ?? ''),
                'location'  => sanitize_text_field($event['location'] ?? ''),
                'latitude'  => sanitize_text_field($event['latitude'] ?? ''),
                'longitude' => sanitize_text_field($event['longitude'] ?? ''),
                'image'     => $event['existing_image'] ?? '', // Use existing image by default
            ];

            // Handle new image upload
            if (!empty($_FILES['addresses']['name'][$index]['image'])) {
                $file = [
                    'name'     => $_FILES['addresses']['name'][$index]['image'],
                    'type'     => $_FILES['addresses']['type'][$index]['image'],
                    'tmp_name' => $_FILES['addresses']['tmp_name'][$index]['image'],
                    'error'    => $_FILES['addresses']['error'][$index]['image'],
                    'size'     => $_FILES['addresses']['size'][$index]['image']
                ];

                $upload = wp_handle_upload($file, ['test_form' => false]);
                if ($upload && !isset($upload['error'])) {
                    $sanitized_event['image'] = $upload['url']; // Update with new image URL
                }
            }

            $sanitized_addresses[] = $sanitized_event;
        }

        update_option('events_on_map_addresses', $sanitized_addresses);

        $api_key = sanitize_text_field($_POST['events_on_map_api_key']);
        update_option('events_on_map_api_key', $api_key);

        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $google_maps_api_key = get_option('events_on_map_api_key', '');
    ?>
    <div class="wrap">
        <h1>Events on Map</h1>
        <form method="POST" enctype="multipart/form-data">
            <?php wp_nonce_field('events_on_map_save', 'events_on_map_nonce'); ?>

            <h2>Google Maps API Key</h2>
            <input type="text" name="events_on_map_api_key" value="<?php echo esc_attr($google_maps_api_key); ?>" placeholder="Enter your Google Maps API key" style="width: 100%; max-width: 400px;">

            <h2>Event Locations</h2>
            <table id="events-table" class="widefat">
                <thead>
                    <tr>
                        <th>Event Name</th>
                        <th>Date</th>
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
                            <td><input type="date" name="addresses[<?php echo $index; ?>][date]" value="<?php echo esc_attr($address['date'] ?? ''); ?>" /></td>
                            <td><input type="text" name="addresses[<?php echo $index; ?>][location]" value="<?php echo esc_attr($address['location'] ?? ''); ?>" /></td>
                            <td><input type="text" name="addresses[<?php echo $index; ?>][latitude]" value="<?php echo esc_attr($address['latitude'] ?? ''); ?>" /></td>
                            <td><input type="text" name="addresses[<?php echo $index; ?>][longitude]" value="<?php echo esc_attr($address['longitude'] ?? ''); ?>" /></td>
                            <td>
                                <input type="file" name="addresses[<?php echo $index; ?>][image]" accept="image/*" />
                                <input type="hidden" name="addresses[<?php echo $index; ?>][existing_image]" value="<?php echo esc_attr($address['image'] ?? ''); ?>">
                                <?php if (!empty($address['image'])) : ?>
                                    <br><img src="<?php echo esc_url($address['image']); ?>" width="50">
                                <?php endif; ?>
                            </td>
                            <td><button type="button" class="remove-event">Remove</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="button" id="add-event" class="button button-primary">Add New Event</button>
            <br><br>
            <input type="submit" class="button button-primary" value="Save Changes">
        </form>
    </div>

   <script>
document.addEventListener("DOMContentLoaded", function() {
    let eventCounter = document.querySelectorAll("#events-table tbody tr").length;

    document.getElementById("add-event").addEventListener("click", function() {
        let table = document.getElementById("events-table").getElementsByTagName('tbody')[0];
        let row = table.insertRow();

        row.innerHTML = `
            <td><input type="text" name="addresses[${eventCounter}][name]" placeholder="Event Name" required /></td>
            <td><input type="date" name="addresses[${eventCounter}][date]" required /></td>
            <td><input type="text" name="addresses[${eventCounter}][location]" placeholder="Location" required /></td>
            <td><input type="text" name="addresses[${eventCounter}][latitude]" placeholder="Latitude" required /></td>
            <td><input type="text" name="addresses[${eventCounter}][longitude]" placeholder="Longitude" required /></td>
            <td><input type="file" name="addresses[${eventCounter}][image]" accept="image/*" /></td>
            <td><button type="button" class="remove-event">Remove</button></td>
        `;

        eventCounter++;
    });

    document.getElementById("events-table").addEventListener("click", function(event) {
        if (event.target.classList.contains("remove-event")) {
            event.target.closest("tr").remove();
        }
    });

    // Handle form submission via AJAX
    document.querySelector("form").addEventListener("submit", function(event) {
        event.preventDefault(); // Prevent default form submission

        let formData = new FormData(this); // Get form data
        let xhr = new XMLHttpRequest();

        xhr.open("POST", "", true); // Submit to the same page
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                location.reload(); // Reload after successful save
            }
        };

        xhr.send(formData);
    });
});
</script>




    <?php
}