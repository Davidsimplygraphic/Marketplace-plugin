<?php
/**
 * Plugin Name: Provider Marketplace for WooCommerce
 * Description: Modular marketplace: Provider role, Provider Details + Your Services tabs, 80% commissions, avatar, public profiles, samples uploader, and workrooms (messaging + withdrawals).
 * Version: 1.3.0
 * Author: SimplyGraphic
 * License: GPLv2 or later
 * Text Domain: provider-marketplace
 */

if (!defined('ABSPATH')) exit;

define('SGPM_PLUGIN_FILE', __FILE__);
define('SGPM_PLUGIN_DIR', plugin_dir_path(__FILE__));

/** Core */
require_once SGPM_PLUGIN_DIR . 'includes/Constants.php';
require_once SGPM_PLUGIN_DIR . 'includes/Helpers.php';

/** Modules */
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Roles.php';
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Endpoints.php';
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Commissions.php';
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Avatar.php';
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Profiles.php';
require_once SGPM_PLUGIN_DIR . 'includes/Modules/ProductProviderLink.php';
require_once SGPM_PLUGIN_DIR . 'includes/Modules/MessagesBadge.php';  // ✅ NEW

/** Account pages */
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Account/ProviderDetails.php';
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Account/YourServices.php';
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Account/RegisterProvider.php';   // ✅ NEW (shortcode)

/** ✅ NEW: Workrooms + Withdrawals */
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Conversations.php';
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Account/OrderPortal.php';
require_once SGPM_PLUGIN_DIR . 'includes/Modules/Account/Withdrawals.php';

use SGPM\Modules\Roles;
use SGPM\Modules\Endpoints;
use SGPM\Modules\Commissions;
use SGPM\Modules\Avatar;
use SGPM\Modules\Profiles;
use SGPM\Modules\ProductProviderLink;
use SGPM\Modules\Conversations;                 // ✅ NEW
use SGPM\Modules\MessagesBadge;                 // ✅ NEW
use SGPM\Modules\Account\ProviderDetails;
use SGPM\Modules\Account\YourServices;
use SGPM\Modules\Account\OrderPortal;           // ✅ NEW
use SGPM\Modules\Account\Withdrawals;           // ✅ NEW
use SGPM\Modules\Account\RegisterProvider;      // ✅ NEW

/** Activation / Deactivation */
register_activation_hook(SGPM_PLUGIN_FILE, function(){
    Roles::activate();
    Profiles::activate();
    Endpoints::activate(); // registers endpoints (order portal + withdrawals included)
    flush_rewrite_rules();
});
register_deactivation_hook(SGPM_PLUGIN_FILE, function(){
    flush_rewrite_rules();
});

// Force the Messages badge/link to use the Order Portal endpoint
add_filter('sgpm_messages_endpoint', function($slug){
    return 'order-portal';
});

/** Bootstrap */
add_action('plugins_loaded', function(){
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error"><p><strong>Provider Marketplace for WooCommerce</strong> requires WooCommerce to be active.</p></div>';
        });
        return;
    }

    // Core modules
    Roles::init();
    Endpoints::init();
    Profiles::init();
    (new Avatar())->init();
    (new Commissions())->init();

    // Account pages
    (new ProviderDetails())->init();
    (new YourServices())->init();
    (new RegisterProvider())->init(); // ✅ ensure shortcode is registered

    // Product page meta
    ProductProviderLink::init();

    // ✅ NEW: Messages unread badge
    MessagesBadge::init();

    // ✅ NEW: Workrooms (Conversations) + Order Portal UI + Withdrawals
    Conversations::init();
    (new OrderPortal())->init();
    (new Withdrawals())->init();
});
