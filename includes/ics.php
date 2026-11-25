<?php
/**
 * Minimal iCal (ICS) reader: returns busy intervals [start_ts, end_ts] within a window.
 * Supports DTSTART/DTEND/DURATION (UTC, TZID, floating, all-day), RRULE (DAILY/WEEKLY/MONTHLY/YEARLY
 * with INTERVAL, COUNT, UNTIL, BYDAY for WEEKLY), EXDATE, RECURRENCE-ID overrides,
 * STATUS:CANCELLED and TRANSP:TRANSPARENT (ignored as free).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bkb_ics_fetch( $url ) {
	if ( ! $url ) {
		return '';
	}
	$key    = 'bkb_ics_' . md5( $url );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached;
	}
	$res = wp_safe_remote_get( $url, array( 'timeout' => 15 ) );
	if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
		$stale = get_option( $key . '_stale', '' );
		return $stale;
	}
	$body = (string) wp_remote_retrieve_body( $res );
	set_transient( $key, $body, 5 * MINUTE_IN_SECONDS );
	update_option( $key . '_stale', $body, false );
	return $body;
}

function bkb_ics_parse_dt( $value, $params, $tz_default ) {
	$is_date = ( isset( $params['VALUE'] ) && 'DATE' === strtoupper( $params['VALUE'] ) ) || preg_match( '/^\d{8}$/', $value );
	try {
		if ( $is_date ) {
			$dt = DateTime::createFromFormat( '!Ymd', substr( $value, 0, 8 ), $tz_default );
			return $dt ? array( $dt->getTimestamp(), true ) : null;
		}
		if ( substr( $value, -1 ) === 'Z' ) {
			$dt = DateTime::createFromFormat( 'Ymd\THis\Z', $value, new DateTimeZone( 'UTC' ) );
		} else {
			$tz = $tz_default;
			if ( ! empty( $params['TZID'] ) ) {
				try {
					$tz = new DateTimeZone( trim( $params['TZID'], '"' ) );
				} catch ( Exception $e ) {
					$tz = $tz_default;
				}
			}
			$dt = DateTime::createFromFormat( 'Ymd\THis', substr( $value, 0, 15 ), $tz );
		}
		return $dt ? array( $dt->getTimestamp(), false ) : null;
	} catch ( Exception $e ) {
		return null;
	}
}

function bkb_ics_duration( $d ) {
	if ( ! preg_match( '/^([+-])?P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/', $d, $m ) ) {
		return 0;
	}
	$s = ( (int) ( $m[2] ?? 0 ) ) * 604800 + ( (int) ( $m[3] ?? 0 ) ) * 86400 + ( (int) ( $m[4] ?? 0 ) ) * 3600 + ( (int) ( $m[5] ?? 0 ) ) * 60 + (int) ( $m[6] ?? 0 );
	return ( isset( $m[1] ) && '-' === $m[1] ) ? -$s : $s;
}

function bkb_ics_busy( $ics, $from, $to ) {
	$tz    = wp_timezone();
	$ics   = str_replace( array( "\r\n", "\r" ), "\n", (string) $ics );
	$ics   = preg_replace( "/\n[ \t]/", '', $ics );
	$lines = explode( "\n", $ics );

	$events = array();
	$ev     = null;
	foreach ( $lines as $line ) {
		if ( 'BEGIN:VEVENT' === $line ) {
			$ev = array( 'exdate' => array() );
			continue;
		}
		if ( 'END:VEVENT' === $line ) {
			if ( $ev ) {
				$events[] = $ev;
			}
			$ev = null;
			continue;
		}
		if ( null === $ev || false === strpos( $line, ':' ) ) {
			continue;
		}
		list( $left, $value ) = explode( ':', $line, 2 );
		$parts  = explode( ';', $left );
		$name   = strtoupper( array_shift( $parts ) );
		$params = array();
		foreach ( $parts as $p ) {
			if ( false !== strpos( $p, '=' ) ) {
				list( $k, $v )               = explode( '=', $p, 2 );
				$params[ strtoupper( $k ) ] = $v;
			}
		}
		switch ( $name ) {
			case 'DTSTART':
			case 'DTEND':
			case 'RECURRENCE-ID':
				$ev[ $name ] = bkb_ics_parse_dt( $value, $params, $tz );
				break;
			case 'DURATION':
				$ev['DURATION'] = bkb_ics_duration( $value );
				break;
			case 'RRULE':
				$ev['RRULE'] = $value;
				break;
			case 'EXDATE':
				foreach ( explode( ',', $value ) as $x ) {
					$d = bkb_ics_parse_dt( $x, $params, $tz );
					if ( $d ) {
						$ev['exdate'][] = $d[0];
					}
				}
				break;
			case 'UID':
				$ev['UID'] = $value;
				break;
			case 'STATUS':
				$ev['STATUS'] = strtoupper( $value );
				break;
			case 'TRANSP':
				$ev['TRANSP'] = strtoupper( $value );
				break;
		}
	}

	// Overridden occurrences (RECURRENCE-ID) are removed from the master series.
	$overridden = array();
	foreach ( $events as $e ) {
		if ( ! empty( $e['RECURRENCE-ID'] ) && ! empty( $e['UID'] ) ) {
			$overridden[ $e['UID'] ][] = $e['RECURRENCE-ID'][0];
		}
	}

	$busy = array();
	foreach ( $events as $e ) {
		if ( empty( $e['DTSTART'] ) || ( isset( $e['STATUS'] ) && 'CANCELLED' === $e['STATUS'] ) ) {
			continue;
		}
		list( $start, $allday ) = $e['DTSTART'];
		// Timed events always block; an all-day event blocks only when marked "busy".
		if ( $allday && isset( $e['TRANSP'] ) && 'TRANSPARENT' === $e['TRANSP'] ) {
			continue;
		}
		if ( ! empty( $e['DTEND'] ) ) {
			$len = $e['DTEND'][0] - $start;
		} elseif ( ! empty( $e['DURATION'] ) ) {
			$len = $e['DURATION'];
		} else {
			$len = $allday ? 86400 : 0;
		}
		if ( $len <= 0 ) {
			continue;
		}
		$skip = $e['exdate'];
		if ( empty( $e['RECURRENCE-ID'] ) && ! empty( $e['UID'] ) && isset( $overridden[ $e['UID'] ] ) ) {
			$skip = array_merge( $skip, $overridden[ $e['UID'] ] );
		}
		$starts = empty( $e['RRULE'] ) || ! empty( $e['RECURRENCE-ID'] ) ? array( $start ) : bkb_ics_expand( $start, $e['RRULE'], $to, $tz );
		foreach ( $starts as $s ) {
			if ( in_array( $s, $skip, true ) ) {
				continue;
			}
			$end = $s + $len;
			if ( $end > $from && $s < $to ) {
				$busy[] = array( $s, $end );
			}
		}
	}
	return $busy;
}

function bkb_ics_expand( $start, $rrule, $until_ts, $tz ) {
	$r = array();
	foreach ( explode( ';', $rrule ) as $kv ) {
		if ( false !== strpos( $kv, '=' ) ) {
			list( $k, $v ) = explode( '=', $kv, 2 );
			$r[ strtoupper( $k ) ] = $v;
		}
	}
	$freq     = isset( $r['FREQ'] ) ? strtoupper( $r['FREQ'] ) : '';
	$interval = max( 1, (int) ( $r['INTERVAL'] ?? 1 ) );
	$count    = isset( $r['COUNT'] ) ? (int) $r['COUNT'] : 0;
	$until    = $until_ts;
	if ( ! empty( $r['UNTIL'] ) ) {
		$u = bkb_ics_parse_dt( $r['UNTIL'], array(), $tz );
		if ( $u ) {
			$until = min( $until_ts, $u[0] + ( $u[1] ? 86399 : 0 ) );
		}
	}
	$map   = array( 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7 );
	$byday = array();
	if ( ! empty( $r['BYDAY'] ) ) {
		foreach ( explode( ',', $r['BYDAY'] ) as $d ) {
			$d = strtoupper( substr( $d, -2 ) );
			if ( isset( $map[ $d ] ) ) {
				$byday[] = $map[ $d ];
			}
		}
	}
	$out = array();
	$dt  = ( new DateTime( '@' . $start ) )->setTimezone( $tz );
	$n   = 0;
	$guard = 0;
	if ( 'WEEKLY' === $freq && $byday ) {
		$week = clone $dt;
		$week->modify( '-' . ( (int) $week->format( 'N' ) - 1 ) . ' days' );
		while ( $guard++ < 600 ) {
			foreach ( $byday as $wd ) {
				$c = clone $week;
				$c->modify( '+' . ( $wd - 1 ) . ' days' );
				$c->setTime( (int) $dt->format( 'H' ), (int) $dt->format( 'i' ), (int) $dt->format( 's' ) );
				$ts = $c->getTimestamp();
				if ( $ts < $start ) {
					continue;
				}
				if ( $ts > $until || ( $count && $n >= $count ) ) {
					return $out;
				}
				$out[] = $ts;
				$n++;
			}
			$week->modify( '+' . $interval . ' weeks' );
		}
		return $out;
	}
	$step = array(
		'DAILY'   => '+' . $interval . ' days',
		'WEEKLY'  => '+' . $interval . ' weeks',
		'MONTHLY' => '+' . $interval . ' months',
		'YEARLY'  => '+' . $interval . ' years',
	);
	if ( ! isset( $step[ $freq ] ) ) {
		return array( $start );
	}
	while ( $guard++ < 2000 ) {
		$ts = $dt->getTimestamp();
		if ( $ts > $until || ( $count && $n >= $count ) ) {
			break;
		}
		$out[] = $ts;
		$n++;
		$dt->modify( $step[ $freq ] );
	}
	return $out;
}
