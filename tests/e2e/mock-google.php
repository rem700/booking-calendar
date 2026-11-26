<?php
// Test site only (copy to mu-plugins): answers Google sign-in and Calendar API calls with test data, nothing goes to Google.
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$host = (string) parse_url( $url, PHP_URL_HOST );
	if ( ! in_array( $host, array( 'oauth2.googleapis.com', 'www.googleapis.com' ), true ) ) {
		return $pre;
	}
	$method = strtoupper( $args['method'] ?? 'GET' );
	$log    = (array) get_option( 'bkb_test_gcal_log', array() );
	$log[]  = array( 'method' => $method, 'url' => $url, 'body' => is_string( $args['body'] ?? '' ) ? ( $args['body'] ?? '' ) : wp_json_encode( $args['body'] ) );
	update_option( 'bkb_test_gcal_log', $log, false );
	if ( get_option( 'bkb_test_gcal_down' ) ) {
		return new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' );
	}
	$ans  = function ( $code, $body ) {
		return array( 'headers' => array(), 'body' => null === $body ? '' : wp_json_encode( $body ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array() );
	};
	$path = (string) parse_url( $url, PHP_URL_PATH );
	if ( 'oauth2.googleapis.com' === $host ) {
		if ( '/revoke' === $path ) {
			return $ans( 200, array() );
		}
		$b = (array) $args['body'];
		if ( 'authorization_code' === ( $b['grant_type'] ?? '' ) ) {
			return 'good-code' === $b['code'] ? $ans( 200, array( 'access_token' => 'at-1', 'expires_in' => 3600, 'refresh_token' => 'rt-1' ) ) : $ans( 400, array( 'error' => 'invalid_grant', 'error_description' => 'Bad Request' ) );
		}
		return get_option( 'bkb_test_gcal_revoked' ) ? $ans( 400, array( 'error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.' ) ) : $ans( 200, array( 'access_token' => 'at-2', 'expires_in' => 3600 ) );
	}
	$path = substr( $path, strlen( '/calendar/v3/' ) );
	if ( 'users/me/calendarList' === $path ) {
		return $ans( 200, array( 'items' => array(
			array( 'id' => 'studio@example.test', 'summary' => 'studio@example.test', 'primary' => true ),
			array( 'id' => 'team@group.calendar.google.com', 'summary' => 'Team' ),
		) ) );
	}
	if ( ! preg_match( '~^calendars/([^/]+)/events(?:/([^/]+))?$~', $path, $m ) ) {
		return $ans( 404, array( 'error' => array( 'message' => 'not mocked: ' . $path ) ) );
	}
	$cal = rawurldecode( $m[1] );
	$eid = isset( $m[2] ) ? rawurldecode( $m[2] ) : '';
	$all = (array) get_option( 'bkb_test_gcal_events', array() );
	$evs = (array) ( $all[ $cal ] ?? array() );
	$in  = json_decode( (string) ( $args['body'] ?? '' ), true );
	if ( 'POST' === $method ) {
		$eid         = 'ev' . ( count( $log ) + 1000 );
		$evs[ $eid ] = array_merge( (array) $in, array( 'id' => $eid, 'iCalUID' => $eid . '@google.com' ) );
		$out         = $ans( 200, $evs[ $eid ] );
	} elseif ( 'PATCH' === $method ) {
		if ( ! isset( $evs[ $eid ] ) ) {
			return $ans( 404, array( 'error' => array( 'message' => 'Not Found' ) ) );
		}
		$evs[ $eid ] = array_merge( $evs[ $eid ], (array) $in );
		$out         = $ans( 200, $evs[ $eid ] );
	} elseif ( 'DELETE' === $method ) {
		if ( ! isset( $evs[ $eid ] ) ) {
			return $ans( 410, array( 'error' => array( 'message' => 'Resource has been deleted' ) ) );
		}
		unset( $evs[ $eid ] );
		$out = $ans( 204, null );
	} else {
		$items = array_values( array_merge( $evs, (array) get_option( 'bkb_test_gcal_foreign', array() ) ) );
		return $ans( 200, array( 'items' => $items ) );
	}
	$all[ $cal ] = $evs;
	update_option( 'bkb_test_gcal_events', $all, false );
	return $out;
}, 10, 3 );
