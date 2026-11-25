<?php
// Test site only (copy to mu-plugins): answers Graph API calls instead of Instagram.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'graph.instagram.com' ) ) {
		return $pre;
	}
	$log   = (array) get_option( 'bkb_test_log', array() );
	$log[] = array( 'method' => $args['method'] ?? 'GET', 'url' => $url, 'body' => $args['body'] ?? '' );
	update_option( 'bkb_test_log', $log, false );
	$path = (string) parse_url( $url, PHP_URL_PATH );
	$ans  = array( 'error' => array( 'message' => 'not mocked: ' . $path ) );
	if ( preg_match( '~/me$~', $path ) ) {
		$ans = 'bad' === get_option( 'bkb_test_token_state' ) ? array( 'error' => array( 'message' => 'Invalid OAuth access token.' ) ) : array( 'user_id' => '17841400000000001', 'username' => 'teststudio' );
	} elseif ( preg_match( '~/me/subscribed_apps$~', $path ) ) {
		$ans = array( 'success' => true );
	} elseif ( preg_match( '~/me/conversations$~', $path ) ) {
		$ans = array( 'data' => (array) get_option( 'bkb_test_convs', array() ) );
	} elseif ( preg_match( '~/messages$~', $path ) ) {
		$ans = array( 'recipient_id' => '1', 'message_id' => 'mid.sent.' . count( $log ) );
	} elseif ( preg_match( '~/refresh_access_token$~', $path ) ) {
		$ans = array( 'access_token' => 'refreshed-token', 'expires_in' => 5184000 );
	} elseif ( preg_match( '~/(\d+)$~', $path, $m ) ) {
		$people = array( '1111' => array( 'name' => 'Anna K', 'username' => 'anna.k' ), '2222' => array( 'name' => 'Olena', 'username' => 'known.client' ) );
		$ans    = $people[ $m[1] ] ?? array( 'username' => 'user' . $m[1] );
	}
	return array( 'headers' => array(), 'body' => wp_json_encode( $ans ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
}, 10, 3 );
