<?php
// 🇵🇰 PHP Phase Start: Uninstaller 🇵🇰
// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Delete all plugin options/settings
delete_option( 'ssm_gpt_launcher_version' ); 

// 2. Delete all Custom GPT posts and their meta data
$gpts = get_posts( array( 
    'post_type' => 'custom_gpts', 
    'post_status' => 'any', 
    'numberposts' => -1 
) );

foreach ( $gpts as $gpt ) {
    wp_delete_post( $gpt->ID, true ); // true forces permanent deletion
}

// 3. Clear CPT rewrite rules
flush_rewrite_rules();

// Note: Taxonomy terms (gpt_category) are often best left alone 
// unless explicit confirmation is given to delete shared terms.
// 🇵🇰 PHP Phase End: Uninstaller 🇵🇰
