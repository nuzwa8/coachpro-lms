<?php
/**
 * Plugin Name: SSM Custom GPT Launcher
 * Plugin URI: https://yourwebsite.com/
 * Description: Displays Custom GPTs, collects user input via a dynamic form, generates a perfect prompt, and directs users to the GPT with the prompt copied to their clipboard.
 * Version: 1.0.0
 * Author: Smart Solutions Maker
 * Author URI: https://yourwebsite.com/
 * License: GPL2
 * Text Domain: ssm-gpt-launcher
 */

// Define plugin constants
if ( ! defined( 'SSM_GPT_PATH' ) ) {
    define( 'SSM_GPT_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'SSM_GPT_URL' ) ) {
    define( 'SSM_GPT_URL', plugin_dir_url( __FILE__ ) );
}

// Security check
if ( ! function_exists( 'add_action' ) ) {
    die( 'Direct access denied.' );
}

// 🇵🇰 PHP Phase Start: Core Initialization 🇵🇰
require_once SSM_GPT_PATH . 'includes/class-ssm-activator.php';
require_once SSM_GPT_PATH . 'includes/class-ssm-core.php';

// Activation Hook
register_activation_hook( __FILE__, array( 'SSM_Activator', 'activate' ) );

// Core Plugin Initialization
function run_ssm_gpt_launcher() {
    $plugin = new SSM_Core();
    $plugin->run();
}
run_ssm_gpt_launcher();

// 🇵🇰 PHP Phase End: Core Initialization 🇵🇰

// NOTE: PHP closing tag ?> is intentionally omitted as per rule 6.
