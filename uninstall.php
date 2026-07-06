<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove transient used for caching scan results.
delete_transient( 'mediasweep_unused_ids' );

// If you later add plugin options, remove them here using delete_option().