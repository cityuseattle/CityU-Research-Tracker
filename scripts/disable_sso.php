<?php
$s = get_option( 'rrp_portal_settings', array() );
$s['sso_enabled'] = false;
update_option( 'rrp_portal_settings', $s, false );
$check = get_option( 'rrp_portal_settings' );
echo 'sso_enabled=' . var_export( isset( $check['sso_enabled'] ) ? $check['sso_enabled'] : 'not_set', true ) . PHP_EOL;
