<?php
/**
 * Plugin Name: Fluentform Active Campaign Bulk Importer
 * Description: Used to import mulitple contacts on a single form submission
 * Version:     1.2.0
 * Author:      Dustin Wight
 * Author URI:  https://dustinwight.com/
 * Text Domain: ffac_bulk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action('init', function (){

    if (!defined('FLUENTFORM') || !defined('FLUENTFORMPRO') ) {
        add_action( 'admin_notices', function(){
            
            $message = '<p>' . esc_html__( 'FluentForm Active Campaign Bulk Importer Add-On Requires FluentForm and Fluentform Pro with Active Campaign integration activated', 'ffac_bulk' ) . '</p>';

            print_error( $message );
            
        });
        return;
    }
    require plugin_dir_path( __FILE__ ) . 'includes/ActiveCampaignBulk.php';
    
    $plugin = new ActiveCampaignBulk();
});
