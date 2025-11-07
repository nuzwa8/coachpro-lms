<?php
/**
 * Plugin Name:       My Custom GPTs
 * Plugin URI:        https://coachproai.com
 * Description:       Manages and displays Custom GPTs with prompt builders using a shortcode.
 * Version:           1.0.0
 * Author:            Coach Pro AI
 * Author URI:        https://coachproai.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cpg
 * Domain Path:       /languages
 *
 * This file contains the main plugin logic, database setup, admin menus,
 * AJAX handlers, and shortcode registration.
 */

// Exit if accessed directly (Direct access protection)
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Part 1 — Activation Hook & Database Setup
 * * This part handles the plugin activation, creating the necessary
 * database table to store the GPTs and setting the plugin version.
 */

// Define constants
define( 'CPG_VERSION', '1.0.0' );
define( 'CPG_PLUGIN_FILE', __FILE__ );
define( 'CPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CPG_TEXT_DOMAIN', 'cpg' );

/**
 * Get centralized table names.
 * * @return array List of table names with WordPress prefix.
 */
function cpg_get_table_names() {
    global $wpdb;
    // We centralize table names here for easy maintenance.
    return [
        'gpts' => $wpdb->prefix . 'cpg_gpts',
    ];
}

/**
 * Runs on plugin activation.
 * Creates the database table using dbDelta.
 */
function cpg_activate_plugin() {
    // Check if the user has permission
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    global $wpdb;
    $tables = cpg_get_table_names();
    $gpts_table_name = $tables['gpts'];
    $charset_collate = $wpdb->get_charset_collate();

    // SQL to create the table
    // We store prompt_fields as JSON to allow flexibility.
    $sql = "CREATE TABLE $gpts_table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        gpt_url VARCHAR(2083) NOT NULL,
        prompt_template TEXT DEFAULT NULL,
        prompt_fields JSON DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // We need dbDelta to create/update the table
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Store the version number
    add_option( 'cpg_version', CPG_VERSION );
}
register_activation_hook( CPG_PLUGIN_FILE, 'cpg_activate_plugin' );

/**
 * Load text domain for translations.
 */
function cpg_load_textdomain() {
    load_plugin_textdomain( 
        CPG_TEXT_DOMAIN, 
        false, 
        dirname( plugin_basename( CPG_PLUGIN_FILE ) ) . '/languages' 
    );
}
add_action( 'plugins_loaded', 'cpg_load_textdomain' );

/**
 * Part 2 — Admin Menu and Page Structure
 * * This part adds the admin menu "My Custom GPTs" and renders the
 * main HTML container (<div id="cpg-admin-root">) and the
 * <template> block needed for the JavaScript app.
 */

/**
 * Adds the main menu page in the WordPress admin dashboard.
 */
function cpg_add_admin_menu() {
    add_menu_page(
        __( 'My Custom GPTs', CPG_TEXT_DOMAIN ), // Page Title
        __( 'My Custom GPTs', CPG_TEXT_DOMAIN ), // Menu Title
        'manage_options', // Capability required
        'cpg-main-menu', // Menu Slug
        'cpg_render_admin_page', // Function to render the page
        'dashicons-superhero-alt', // Icon
        25 // Position
    );
}
add_action( 'admin_menu', 'cpg_add_admin_menu' );

/**
 * Renders the main admin page.
 *
 * This function outputs the root container (div) where our JavaScript
 * app will mount. It also includes the HTML <template> for the UI.
 */
function cpg_render_admin_page() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', CPG_TEXT_DOMAIN ) );
    }

    // This is the main container for our JavaScript application.
    // The 'data-screen' attribute helps JS know what to render.
    echo '<div id="cpg-admin-root" class="cpg-root" data-screen="loading"></div>';

    // This template contains the complete HTML structure for the admin page.
    // JavaScript will clone this template to render the UI.
    ?>
    <template id="cpg-admin-template">
        <div class="cpg-admin-wrap">
            
            <header class="cpg-header">
                <div class="cpg-header-left">
                    <h1><?php _e( 'My Custom GPTs', CPG_TEXT_DOMAIN ); ?></h1>
                    <p><?php _e( 'Manage your Custom GPTs and prompt builders.', CPG_TEXT_DOMAIN ); ?></p>
                </div>
                <div class="cpg-header-right">
                    <button id="cpg-add-new-btn" class="button button-primary cpg-button-primary">
                        <?php _e( 'Add New GPT', CPG_TEXT_DOMAIN ); ?>
                    </button>
                </div>
            </header>

            <main class="cpg-main-content">
                
                <div id="cpg-gpts-list-container">
                    <table class="wp-list-table widefat fixed striped" id="cpg-gpts-table">
                        <thead>
                            <tr>
                                <th scope="col" style="width: 25%;"><?php _e( 'Name', CPG_TEXT_DOMAIN ); ?></th>
                                <th scope="col" style="width: 40%;"><?php _e( 'Description', CPG_TEXT_DOMAIN ); ?></th>
                                <th scope="col"><?php _e( 'Prompt Fields', CPG_TEXT_DOMAIN ); ?></th>
                                <th scope="col" style="width: 15%;"><?php _e( 'Actions', CPG_TEXT_DOMAIN ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="cpg-gpts-table-body">
                            <tr id="cpg-no-gpts-row">
                                <td colspan="4"><?php _e( 'No GPTs found. Add one to get started.', CPG_TEXT_DOMAIN ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <div id="cpg-list-loader" class="cpg-loader" style="display:none;">
                        <?php _e( 'Loading GPTs...', CPG_TEXT_DOMAIN ); ?>
                    </div>
                </div>

                <div id="cpg-form-drawer" class="cpg-drawer" style="display: none;">
                    <div class="cpg-drawer-overlay"></div>
                    <div class="cpg-drawer-content">
                        
                        <form id="cpg-gpt-form">
                            <input type="hidden" id="cpg-gpt-id" name="id" value="0">
                            
                            <header class="cpg-drawer-header">
                                <h2 id="cpg-form-title"><?php _e( 'Add New GPT', CPG_TEXT_DOMAIN ); ?></h2>
                                <button type="button" id="cpg-close-drawer-btn" class="button-link">
                                    <span class="screen-reader-text"><?php _e( 'Close', CPG_TEXT_DOMAIN ); ?></span>
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </header>

                            <div class="cpg-drawer-body">
                                
                                <div class="cpg-form-group">
                                    <label for="cpg-gpt-name"><?php _e( 'GPT Name', CPG_TEXT_DOMAIN ); ?></label>
                                    <input type="text" id="cpg-gpt-name" name="name" class="regular-text" required>
                                </div>

                                <div class="cpg-form-group">
                                    <label for="cpg-gpt-url"><?php _e( 'GPT URL', CPG_TEXT_DOMAIN ); ?></label>
                                    <input type="url" id="cpg-gpt-url" name="gpt_url" class="regular-text" placeholder="https://chat.openai.com/g/..." required>
                                </div>

                                <div class="cpg-form-group">
                                    <label for="cpg-gpt-description"><?php _e( 'Description', CPG_TEXT_DOMAIN ); ?></label>
                                    <textarea id="cpg-gpt-description" name="description" rows="3" class="large-text"></textarea>
                                </div>

                                <hr>
                                
                                <h3><?php _e( 'Prompt Builder', CPG_TEXT_DOMAIN ); ?></h3>
                                <p class="description"><?php _e( 'Define fields for the user to fill out. Use these fields in your template.', CPG_TEXT_DOMAIN ); ?></p>
                                
                                <div class="cpg-form-group">
                                    <label for="cpg-prompt-template"><?php _e( 'Prompt Template', CPG_TEXT_DOMAIN ); ?></label>
                                    <textarea id="cpg-prompt-template" name="prompt_template" rows="4" class="large-text"></textarea>
                                    <p class="description">
                                        <?php _e( 'Example: Make a lesson plan for {Class} about {Subject}.', CPG_TEXT_DOMAIN ); ?>
                                        <br>
                                        <?php _e( 'Use curly braces {} to define variables from your fields below.', CPG_TEXT_DOMAIN ); ?>
                                    </p>
                                </div>

                                <div class="cpg-form-group">
                                    <label><?php _e( 'Prompt Fields', CPG_TEXT_DOMAIN ); ?></label>
                                    <div id="cpg-prompt-fields-container">
                                        </div>
                                    <button type="button" id="cpg-add-field-btn" class="button">
                                        <?php _e( 'Add Field', CPG_TEXT_DOMAIN ); ?>
                                    </button>
                                    <p id="cpg-field-info" class="description" style="display:none;">
                                        <?php _e( 'Currently, only "text" and "select" types are supported.', CPG_TEXT_DOMAIN ); ?>
                                    </p>
                                </div>

                            </div> <footer class="cpg-drawer-footer">
                                <span id="cpg-form-message" class="cpg-message" style="display:none;"></span>
                                <button id="cpg-form-loader" class="cpg-loader" style="display:none;" disabled><?php _e( 'Saving...', CPG_TEXT_DOMAIN ); ?></button>
                                <button type="submit" id="cpg-save-gpt-btn" class="button button-primary cpg-button-primary">
                                    <?php _e( 'Save GPT', CPG_TEXT_DOMAIN ); ?>
                                </button>
                            </footer>

                        </form>
                    </div> </div> </main> </div> </template>
    <?php
}

/**
 * Part 3 — Enqueue Assets & Localize Data
 * * This part registers and enqueues the (CSS) and (JS) files for both
 * the admin area and the public shortcode.
 * * It also uses wp_localize_script to pass PHP data (like nonces,
 * AJAX URL, and strings) to our JavaScript files.
 */

/**
 * Enqueues scripts and styles for the admin page.
 *
 * @param string $hook The current admin page hook.
 */
function cpg_admin_enqueue_assets( $hook ) {
    // Only load our assets on our plugin's admin page
    if ( 'toplevel_page_cpg-main-menu' !== $hook ) {
        return;
    }

    // Get the plugin's base URL
    $plugin_url = plugin_dir_url( CPG_PLUGIN_FILE );

    // 1. Enqueue Admin CSS
    // (We will create this file in Phase 3)
    wp_enqueue_style(
        'cpg-admin-style',
        $plugin_url . 'assets/css/cpg-admin.css',
        [],
        CPG_VERSION
    );

    // 2. Enqueue Admin JavaScript
    // (We will create this file in Phase 2)
    wp_enqueue_script(
        'cpg-admin-script',
        $plugin_url . 'assets/js/cpg-admin.js',
        [ 'jquery' ], // WordPress includes jQuery
        CPG_VERSION,
        true // Load in footer
    );

    // 3. Localize data for JavaScript
    // This makes PHP data available in our .js file under the `cpgAdminData` object.
    wp_localize_script(
        'cpg-admin-script',
        'cpgAdminData',
        [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'cpg_admin_nonce' ),
            'strings'  => [
                // Strings for JS (to keep text translatable)
                'add_gpt'      => __( 'Add New GPT', CPG_TEXT_DOMAIN ),
                'edit_gpt'     => __( 'Edit GPT', CPG_TEXT_DOMAIN ),
                'saving'       => __( 'Saving...', CPG_TEXT_DOMAIN ),
                'save_gpt'     => __( 'Save GPT', CPG_TEXT_DOMAIN ),
                'confirm_delete' => __( 'Are you sure you want to delete this GPT? This action cannot be undone.', CPG_TEXT_DOMAIN ),
                'deleting'     => __( 'Deleting...', CPG_TEXT_DOMAIN ),
                'error_general' => __( 'An unknown error occurred.', CPG_TEXT_DOMAIN ),
                'error_fields' => __( 'Please fill in all required fields.', CPG_TEXT_DOMAIN ),
                'field_label_placeholder' => __( 'Field Label (e.g., Class)', CPG_TEXT_DOMAIN ),
                'field_type_placeholder'  => __( 'Field Type (text or select)', CPG_TEXT_DOMAIN ),
                'field_options_placeholder' => __( 'Options (comma-separated)', CPG_TEXT_DOMAIN ),
                'remove_field' => __( 'Remove', CPG_TEXT_DOMAIN ),
                'loading_gpts' => __( 'Loading GPTs...', CPG_TEXT_DOMAIN ),
                'no_gpts_found' => __( 'No GPTs found. Add one to get started.', CPG_TEXT_DOMAIN ),
            ],
            // Pass user capabilities to JS
            'caps' => [
                'can_manage' => current_user_can( 'manage_options' ),
            ],
        ]
    );
}
add_action( 'admin_enqueue_scripts', 'cpg_admin_enqueue_assets' );

/**
 * Enqueues scripts and styles for the public-facing shortcode.
 *
 * This function will be used to load assets ONLY on pages
 * where the shortcode is present (we will add that logic later).
 * For now, we just register them.
 */
function cpg_public_enqueue_assets() {
    $plugin_url = plugin_dir_url( CPG_PLUGIN_FILE );

    // 1. Register Public CSS
    // (We will create this in Phase 3)
    wp_register_style(
        'cpg-public-style',
        $plugin_url . 'assets/css/cpg-public.css',
        [],
        CPG_VERSION
    );

    // 2. Register Public JavaScript
    // (We will create this in Phase 2)
    wp_register_script(
        'cpg-public-script',
        $plugin_url . 'assets/js/cpg-public.js',
        [ 'jquery' ],
        CPG_VERSION,
        true
    );

    // 3. Localize data for Public JS
    wp_localize_script(
        'cpg-public-script',
        'cpgPublicData',
        [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'cpg_public_nonce' ),
            'strings'  => [
                'copy_prompt' => __( 'Copy Prompt', CPG_TEXT_DOMAIN ),
                'prompt_copied' => __( 'Copied!', CPG_TEXT_DOMAIN ),
                'view_gpt' => __( 'Go to GPT', CPG_TEXT_DOMAIN ),
            ],
        ]
    );
}
// We use 'wp_enqueue_scripts' for the front-end
add_action( 'wp_enqueue_scripts', 'cpg_public_enqueue_assets' );

/**
 * Part 4 — Admin AJAX Handlers (CRUD Operations)
 * * This part defines all the backend functions that respond to
 * (AJAX) requests from the admin JavaScript.
 * * This includes fetching, saving (create/update), and deleting GPTs.
 * * Security is enforced with nonces and capability checks.
 */

/**
 * AJAX Handler: Get all GPTs
 * Fetches all GPTs from the database.
 */
function cpg_ajax_get_gpts() {
    // 1. Security Check
    check_ajax_referer( 'cpg_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', CPG_TEXT_DOMAIN ) ], 403 );
    }

    global $wpdb;
    $tables = cpg_get_table_names();

    // 2. Database Query
    $gpts = $wpdb->get_results(
        "SELECT id, name, description, gpt_url, prompt_template, prompt_fields FROM {$tables['gpts']} ORDER BY id DESC"
    );

    // 3. Decode JSON fields before sending
    // The 'prompt_fields' are stored as JSON, so we decode them for JS.
    if ( $gpts ) {
        foreach ( $gpts as $gpt ) {
            if ( ! empty( $gpt->prompt_fields ) ) {
                $gpt->prompt_fields = json_decode( $gpt->prompt_fields, true );
            } else {
                $gpt->prompt_fields = []; // Ensure it's an array
            }
        }
    }

    // 4. Send Success Response
    wp_send_json_success( [ 'gpts' => $gpts ] );
}
add_action( 'wp_ajax_cpg_get_gpts', 'cpg_ajax_get_gpts' );

/**
 * AJAX Handler: Save GPT (Create or Update)
 * Saves a single GPT's data to the database.
 */
function cpg_ajax_save_gpt() {
    // 1. Security Check
    // We are using JSON input, so $_POST['nonce'] is not set.
    // We must manually check the nonce from the $_GET parameter (which we added to the URL in JS).
    if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_GET['nonce'] ), 'cpg_admin_nonce' ) ) {
        wp_send_json_error( [ 'message' => __( 'Nonce verification failed.', CPG_TEXT_DOMAIN ) ], 403 );
    }
    
    // Check capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', CPG_TEXT_DOMAIN ) ], 403 );
    }

    global $wpdb;
    $tables = cpg_get_table_names();

    // 2. Sanitize Input Data
    // We expect the data to be posted as JSON, we decode it.
    // Using file_get_contents is a reliable way to get raw JSON POST data.
    $raw_data = json_decode( file_get_contents( 'php://input' ), true );

    if ( ! $raw_data ) {
        wp_send_json_error( [ 'message' => __( 'Invalid data provided.', CPG_TEXT_DOMAIN ) ], 400 );
    }

    // Sanitize each field
    $gpt_id = isset( $raw_data['id'] ) ? absint( $raw_data['id'] ) : 0;
    $name = isset( $raw_data['name'] ) ? sanitize_text_field( $raw_data['name'] ) : '';
    $gpt_url = isset( $raw_data['gpt_url'] ) ? esc_url_raw( $raw_data['gpt_url'] ) : '';
    $description = isset( $raw_data['description'] ) ? sanitize_textarea_field( $raw_data['description'] ) : '';
    $prompt_template = isset( $raw_data['prompt_template'] ) ? sanitize_textarea_field( $raw_data['prompt_template'] ) : '';
    
    // Sanitize prompt_fields (which is an array of objects)
    $prompt_fields_raw = isset( $raw_data['prompt_fields'] ) && is_array( $raw_data['prompt_fields'] ) ? $raw_data['prompt_fields'] : [];
    $prompt_fields_sanitized = [];

    foreach ( $prompt_fields_raw as $field ) {
        if ( empty( $field['label'] ) || empty( $field['type'] ) ) {
            continue; // Skip incomplete fields
        }
        $sanitized_field = [
            'label'   => sanitize_text_field( $field['label'] ),
            'type'    => sanitize_text_field( $field['type'] ), // e.g., 'text', 'select'
            'options' => isset( $field['options'] ) ? sanitize_text_field( $field['options'] ) : '',
        ];
        // Only allow 'text' or 'select' types
        if ( ! in_array( $sanitized_field['type'], [ 'text', 'select' ] ) ) {
            $sanitized_field['type'] = 'text';
        }
        $prompt_fields_sanitized[] = $sanitized_field;
    }
    
    // Convert fields array back to JSON for database storage
    $prompt_fields_json = wp_json_encode( $prompt_fields_sanitized );

    // Check for required fields
    if ( empty( $name ) || empty( $gpt_url ) ) {
        wp_send_json_error( [ 'message' => __( 'Name and GPT URL are required.', CPG_TEXT_DOMAIN ) ], 400 );
    }

    // 3. Prepare data for Database
    $data = [
        'name'            => $name,
        'gpt_url'         => $gpt_url,
        'description'     => $description,
        'prompt_template' => $prompt_template,
        'prompt_fields'   => $prompt_fields_json,
    ];
    $format = [ '%s', '%s', '%s', '%s', '%s' ];

    // 4. Insert or Update
    if ( $gpt_id > 0 ) {
        // Update
        $result = $wpdb->update(
            $tables['gpts'],
            $data,
            [ 'id' => $gpt_id ], // WHERE condition
            $format,
            [ '%d' ] // WHERE format
        );
    } else {
        // Insert
        $data['created_at'] = current_time( 'mysql' );
        $format[] = '%s';
        $result = $wpdb->insert( $tables['gpts'], $data, $format );
        if ( $result ) {
            $gpt_id = $wpdb->insert_id; // Get the new ID
        }
    }

    if ( $result === false ) {
        wp_send_json_error( [ 'message' => __( 'Database error. Could not save GPT.', CPG_TEXT_DOMAIN ) ], 500 );
    }

    // 5. Send Success Response
    // Return the saved (and sanitized) data, including the ID
    $data['id'] = $gpt_id;
    $data['prompt_fields'] = $prompt_fields_sanitized; // Send decoded version
    wp_send_json_success( [ 'gpt' => $data, 'message' => __( 'GPT saved successfully.', CPG_TEXT_DOMAIN ) ] );
}
add_action( 'wp_ajax_cpg_save_gpt', 'cpg_ajax_save_gpt' );

/**
 * AJAX Handler: Delete GPT
 * Deletes a GPT based on its ID.
 */
function cpg_ajax_delete_gpt() {
    // 1. Security Check
    check_ajax_referer( 'cpg_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', CPG_TEXT_DOMAIN ) ], 403 );
    }

    global $wpdb;
    $tables = cpg_get_table_names();

    // 2. Sanitize Input
    $gpt_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

    if ( $gpt_id <= 0 ) {
        wp_send_json_error( [ 'message' => __( 'Invalid GPT ID.', CPG_TEXT_DOMAIN ) ], 400 );
    }

    // 3. Database Delete
    $result = $wpdb->delete(
        $tables['gpts'],
        [ 'id' => $gpt_id ], // WHERE condition
        [ '%d' ] // WHERE format
    );

    if ( $result === false ) {
        wp_send_json_error( [ 'message' => __( 'Database error. Could not delete GPT.', CPG_TEXT_DOMAIN ) ], 500 );
    }

    // 4. Send Success Response
    wp_send_json_success( [ 'message' => __( 'GPT deleted successfully.', CPG_TEXT_DOMAIN ) ] );
}
add_action( 'wp_ajax_cpg_delete_gpt', 'cpg_ajax_delete_gpt' );

/**
 * Part 5 — Public Shortcode Registration and Rendering
 * * This part defines the shortcode [my_custom_gpts] that users
 * can place on any page.
 * * It fetches the GPT data and provides the HTML root container
 * and template for the public-facing JavaScript.
 */

/**
 * Registers the shortcode [my_custom_gpts].
 */
function cpg_register_shortcode() {
    add_shortcode( 'my_custom_gpts', 'cpg_render_shortcode' );
}
add_action( 'init', 'cpg_register_shortcode' );

/**
 * Renders the HTML for the [my_custom_gpts] shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string The HTML output for the shortcode.
 */
function cpg_render_shortcode( $atts ) {
    // Enqueue the public assets (JS and CSS) only when the shortcode is used.
    wp_enqueue_style( 'cpg-public-style' );
    wp_enqueue_script( 'cpg-public-script' );

    // Fetch all GPTs from the database
    global $wpdb;
    $tables = cpg_get_table_names();
    $gpts_data = $wpdb->get_results(
        "SELECT name, description, gpt_url, prompt_template, prompt_fields FROM {$tables['gpts']} ORDER BY id ASC"
    );

    // Sanitize data and decode JSON fields
    $gpts_clean = [];
    if ( $gpts_data ) {
        foreach ( $gpts_data as $gpt ) {
            $clean_fields = [];
            if ( ! empty( $gpt->prompt_fields ) ) {
                $fields = json_decode( $gpt->prompt_fields, true );
                if ( is_array( $fields ) ) {
                    foreach ( $fields as $field ) {
                        $clean_fields[] = [
                            'label'   => esc_attr( $field['label'] ),
                            'type'    => esc_attr( $field['type'] ),
                            'options' => esc_attr( $field['options'] ),
                        ];
                    }
                }
            }

            $gpts_clean[] = [
                'name'            => esc_html( $gpt->name ),
                'description'     => esc_html( $gpt->description ),
                'gpt_url'         => esc_url( $gpt->gpt_url ),
                'prompt_template' => esc_html( $gpt->prompt_template ),
                'prompt_fields'   => $clean_fields,
            ];
        }
    }

    // Pass the clean data to our public JavaScript
    // We do this by embedding it directly, so JS doesn't need a separate AJAX call.
    wp_add_inline_script(
        'cpg-public-script',
        'const cpgGptData = ' . wp_json_encode( $gpts_clean ) . ';',
        'before' // Add it before our script runs
    );

    // Start output buffering to capture HTML
    ob_start();

    // This is the main container for our public JavaScript app.
    // The 'data-screen' attribute helps JS know what to render.
    ?>
    <div id="cpg-public-root" class="cpg-root" data-screen="loading">
        <div class="cpg-loader"><?php _e( 'Loading Custom GPTs...', CPG_TEXT_DOMAIN ); ?></div>
    </div>

    <template id="cpg-card-template">
        <div class="cpg-card">
            <div class="cpg-card-header">
                <h3 class="cpg-card-title"></h3>
            </div>
            <div class="cpg-card-body">
                <p class="cpg-card-description"></p>
                
                <div class="cpg-prompt-builder" style="display: none;">
                    <form class="cpg-prompt-form">
                        <div class="cpg-prompt-fields-container">
                            </div>
                    </form>
                </div>
            </div>
            <div class="cpg-card-footer">
                <button class="cpg-button-secondary cpg-copy-prompt-btn" style="display: none;">
                    <?php _e( 'Copy Prompt', CPG_TEXT_DOMAIN ); ?>
                </button>
                <a href="#" class="cpg-button-primary cpg-view-gpt-link" target="_blank" rel="noopener noreferrer">
                    <?php _e( 'Go to GPT', CPG_TEXT_DOMAIN ); ?>
                </a>
            </div>
        </div>
    </template>
    <?php

    // Return the buffered HTML
    return ob_get_clean();
}

// No closing ?> tag is needed in PHP files that are purely code.
