<?php
/**
 * Plugin Name: UK Schools on Map
 * Description: A custom plugin to display a map with school locations and search form.
 * Version: 1.1
 * Author: Syed Mashiur Rahman
 * Author URI: https://mashi-portfolio.vercel.app/
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue the Google Maps API and custom JavaScript
function uk_schools_on_map_enqueue_scripts() {
    // Get the saved API key from the options table
    $google_maps_api_key = get_option('uk_schools_on_map_api_key', '');

      if (!empty($google_maps_api_key)) {
      
        // Enqueue the custom map script (newmap.js)
        wp_enqueue_script('custom-map-script', plugin_dir_url(__FILE__) . 'newmap.js', [], null, true);
        
        // Enqueue the Google Maps API
        wp_enqueue_script('google-maps-api', 'https://maps.googleapis.com/maps/api/js?key=' . $google_maps_api_key . '&callback=initMap&v=weekly&libraries=marker', [], null, true);
        
        // Localize script with AJAX URL and other needed values
        wp_localize_script('custom-map-script', 'ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }
}

// Register shortcode to display the map and form
function uk_schools_on_map_shortcode() {
    uk_schools_on_map_enqueue_scripts();

    ob_start();
    ?>
    <div class="address-container">
        <form id="postcode-form">
            <div class="postcode-form-row">
                <label class="postcode-form-label" for="postcode">Podaj Miasto lub Kod Pocztowy:</label>
                <input placeholder="AB15" type="text" id="postcode" />
                <input type="submit" value="Wyszukaj" />
            </div>
        </form>

        <!-- Div where the map will be displayed -->
        <div id="map"></div>

        <!-- Div where the address list will be shown -->
        <div id="address-list"></div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('uk_schools_on_map', 'uk_schools_on_map_shortcode');

// Enqueue CSS for the map and form styling
function uk_schools_on_map_enqueue_styles() {
    wp_enqueue_style('custom-map-styles', plugin_dir_url(__FILE__) . 'styles.css');
}
add_action('wp_enqueue_scripts', 'uk_schools_on_map_enqueue_styles');

// Admin Settings Page for API Key
function uk_schools_on_map_register_settings() {
    add_option('uk_schools_on_map_api_key', '');
    register_setting('uk_schools_on_map_options_group', 'uk_schools_on_map_api_key', 'uk_schools_on_map_callback');
}
add_action('admin_init', 'uk_schools_on_map_register_settings');

// Add API Key field and information page
function uk_schools_on_map_register_options_page() {
    add_menu_page(
        'UK Schools on Map Settings',
        'UK Schools on Map',
        'manage_options',
        'uk_schools_on_map',
        'uk_schools_on_map_options_page',
        'dashicons-location',
        80
    );
}
add_action('admin_menu', 'uk_schools_on_map_register_options_page');

// Add AJAX action to fetch addresses
function uk_schools_on_map_fetch_addresses() {
    $addresses = get_option('uk_schools_on_map_addresses', []);
    wp_send_json($addresses);
}
add_action('wp_ajax_fetch_addresses', 'uk_schools_on_map_fetch_addresses');
add_action('wp_ajax_nopriv_fetch_addresses', 'uk_schools_on_map_fetch_addresses');

 

// Display the options page with address management
function uk_schools_on_map_options_page() {
    $addresses = get_option('uk_schools_on_map_addresses', []);
    if (!is_array($addresses)) {
        $addresses = [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['delete_index'])) {
            $delete_index = (int) $_POST['delete_index'];
            if (isset($addresses[$delete_index])) {
                unset($addresses[$delete_index]);
                $addresses = array_values($addresses);
                update_option('uk_schools_on_map_addresses', $addresses);
                echo '<div class="updated"><p>Address deleted successfully.</p></div>';
            }
        } else {
             if (isset($_POST['addresses']) && is_array($_POST['addresses'])) {
                  $addresses = array_filter(array_map(function($address) {
                      // Only return the address array if 'address' has a value
                      if (!empty($address['address'])) {
                          return [
                              'name' => sanitize_text_field($address['name']),
                              'address' => sanitize_text_field($address['address']),
                              'director' => sanitize_text_field($address['director']),
                              'telephone' => sanitize_text_field($address['telephone']),
                              'email' => sanitize_email($address['email']),
                              'facebook' => esc_url($address['facebook']),
                              'instagram' => esc_url($address['instagram']),
                              'website' => esc_url($address['website']),
                              'logo' => esc_url($address['logo']),  // Sanitize and save Logo URL
                          ];
                      }
                      return null; // Return null if address is empty
                  }, $_POST['addresses']));

                  // Remove null entries created by array_map
                  $addresses = array_filter($addresses);

                  // Update the option only if there are valid addresses
                  if (!empty($addresses)) {
                      update_option('uk_schools_on_map_addresses', $addresses);
                      echo '<div class="updated"><p>Addresses updated successfully.</p></div>';
                  } else {
                      echo '<div class="error"><p>No addresses were saved. Please make sure each address has a valid value.</p></div>';
                  }
              }

        }
    }

    ?>
    <div>
        <h1>UK Schools on Map - Settings</h1>

           <h2>Plugin Information</h2>
        <p>This plugin allows you to display a map with UK school locations based on a searchable city or postal code.</p>
        <p>Use the following shortcode to display the map on any page or post:</p>
        <p><code>[uk_schools_on_map]</code></p>
        
        <h3>Instructions:</h3>
        <p>1. Add your Google Maps API key below to enable the map functionality.</p>
        <p>2. Add and manage school addresses through the address manager in this settings page.</p>
        <p>3. Use the shortcode <code>[uk_schools_on_map]</code> to embed the map on any page or post.</p>

        <hr>
        <form method="post" action="options.php">
    <?php settings_fields('uk_schools_on_map_options_group'); ?>
 

    <h2>Google Maps API Key</h2>
    <table class="form-table">
        <tr valign="top">
            <th scope="row">Google Maps API Key</th>
            <td>
                <input type="text" name="uk_schools_on_map_api_key" value="<?php echo esc_attr(get_option('uk_schools_on_map_api_key')); ?>" />
                <p class="description">Enter your Google Maps API key to enable the map functionality.</p>
            </td>
        </tr>
    </table>

    <?php submit_button('Save API Key'); ?>
</form>

        <form method="post">
        
            <?php do_settings_sections('uk_schools_on_map'); ?>

       
            <h2>Manage School Addresses</h2>
            <table class="form-table">
                <thead>
                    <tr>
                        <th>School Name</th>
                        <th>Address</th>
                        <th>Director</th>
                        <th>Telephone</th>
                        <th>Email</th> <!-- Add Email Column Header -->
                        <th>Facebook</th>
                        <th>Instagram</th>
                        <th>Website</th>
                        <th>Logo URL</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="address-rows">
                  <?php
                  foreach ($addresses as $index => $address) {
                      if (is_array($address)) {
                          echo '<tr>';
                          echo '<td><input  type="text" name="addresses[' . $index . '][name]" value="' . esc_attr($address['name']) . '" /></td>';
                          echo '<td style="display:flex; align-items:center;"><input class="address-field" required  type="text" name="addresses[' . $index . '][address]" value="' . esc_attr($address['address']) . '" /><span style="color:red; margin-left:2px; font-size:20px; margin-bottom:10px;">*</span></td>';

                          // Check if $address['director'] has a value before echoing the input field
                          if (!empty($address['director'])) {
                              echo '<td><input type="text" name="addresses[' . $index . '][director]" value="' . esc_attr($address['director']) . '" /></td>';
                          } else {
                              echo '<td><input type="text" name="addresses[' . $index . '][director]" value="" /></td>'; // Or provide a placeholder if you want to show an empty cell
                          }

                          // Check if $address['telephone'] has a value before echoing the input field
                          if (!empty($address['telephone'])) {
                              echo '<td><input type="text" name="addresses[' . $index . '][telephone]" value="' . esc_attr($address['telephone']) . '" /></td>';
                          } else {
                              echo '<td><input type="text" name="addresses[' . $index . '][telephone]" value="" /></td>'; // Or provide a placeholder if you want to show an empty cell
                          }

                            // Add this new email input field
                         if (!empty($address['email'])) {
                          echo '<td><input type="email" name="addresses[' . $index . '][email]" value="' . esc_attr($address['email'] ?? '') . '" placeholder="Email Address" /></td>';
                                } else {
                              echo '<td><input type="email" name="addresses[' . $index . '][email]" value="" /></td>'; // Or provide a placeholder if you want to show an empty cell
                          }


                          // Check if $address['facebook'] has a value before echoing the input field
                          if (!empty($address['facebook'])) {
                              echo '<td><input type="text" name="addresses[' . $index . '][facebook]" value="' . esc_attr($address['facebook']) . '" placeholder="Facebook Page URL" /></td>';
                          } else {
                              echo '<td><input type="text" name="addresses[' . $index . '][facebook]" value="" placeholder="Facebook Page URL" /></td>'; // Or provide a placeholder if you want to show an empty cell
                          }

                          
                          // Check if $address['Instagram'] has a value before echoing the input field
                          if (!empty($address['instagram'])) {
                               echo '<td><input type="text" name="addresses[' . $index . '][instagram]" value="' . esc_attr($address['instagram']) . '" placeholder="Instagram URL" /></td>'; // New Instagram input field          
                          } else {
                              echo '<td><input type="text" name="addresses[' . $index . '][instagram]" value="" placeholder="Instagram URL" /></td>'; // Or provide a placeholder if you want to show an empty cell
                          }



                          // Check if $address['website'] has a value before echoing the input field
                          if (!empty($address['website'])) {
                              echo '<td><input type="text" name="addresses[' . $index . '][website]" value="' . esc_attr($address['website']) . '" placeholder="Website URL" /></td>';
                          } else {
                              echo '<td><input type="text" name="addresses[' . $index . '][website]" value="" placeholder="Website URL" /></td>'; // Or provide a placeholder if you want to show an empty cell
                          }

                          if (!empty($address['logo'])) {
                            echo '<td><input type="text" name="addresses[' . $index . '][logo]" value="' . esc_attr($address['logo']) . '" placeholder="Logo URL" /></td>'; // New Logo URL input field
                             } else {
                              echo '<td><input type="text" name="addresses[' . $index . '][logo]" value="" placeholder="Logo URL" /></td>'; // Or provide a placeholder if you want to show an empty cell
                          }
           

                          echo '<td><button type="submit" name="delete_index" value="' . $index . '" class="button button-danger">Delete</button></td>';
                          echo '</tr>';
                      }
                  }
                  ?>
              </tbody>
            </table>
            <button type="button" id="add-address">Add New Address</button>
            <?php submit_button('Save Addresses'); ?>
        </form>
    </div>
    <script type="text/javascript">
        document.getElementById('add-address').addEventListener('click', function () {
            const table = document.getElementById('address-rows');
            const rowCount = table.rows.length;

            const newRow = document.createElement('tr');

            newRow.innerHTML = `
                <td><input type="text" name="addresses[${rowCount}][name]" value="" /></td>
                <td><input type="text" name="addresses[${rowCount}][address]" value="" /></td>
                <td><input type="text" name="addresses[${rowCount}][director]" value="" /></td>
                <td><input type="text" name="addresses[${rowCount}][telephone]" value="" /></td>
                <td><input type="email" name="addresses[${rowCount}][email]" value="" placeholder="Email Address" /></td>   
                <td><input type="text" name="addresses[${rowCount}][facebook]" value="" placeholder="Facebook Page URL" /></td>
                  <td><input type="text" name="addresses[${rowCount}][instagram]" value="" placeholder="Instagram URL" /></td> <!-- New Instagram field -->       
                <td><input type="text" name="addresses[${rowCount}][website]" value="" placeholder="Website URL" /></td>
                 <td><input type="text" name="addresses[${rowCount}][logo]" value="" placeholder="Logo URL" /></td> <!-- New Logo URL field -->
        
                <td></td>
            `;
            table.appendChild(newRow);
        });
    </script>
    <?php
}


