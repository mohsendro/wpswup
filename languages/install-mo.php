<?php
/**
 * Compile languages/wpswup-fa_IR.mo from the .po file if .mo is missing or too small.
 * Pure PHP — no gettext binaries required.
 */
defined( 'ABSPATH' ) || exit;

$mo_path = SLL_DIR . 'languages/wpswup-fa_IR.mo';
$po_path = SLL_DIR . 'languages/wpswup-fa_IR.po';

if ( is_readable( $mo_path ) && filesize( $mo_path ) > 500 ) {
	return;
}
if ( ! is_readable( $po_path ) ) {
	return;
}

$po = file_get_contents( $po_path );
if ( ! is_string( $po ) || $po === '' ) {
	return;
}

// Parse simple msgid/msgstr pairs (no plurals in this plugin).
$entries = [];
$msgid  = null;
$msgstr = null;
$in_id  = false;
$in_str = false;
$buf    = '';

$lines = preg_split( "/\r\n|\n|\r/", $po );
foreach ( $lines as $line ) {
	if ( preg_match( '/^msgid\s+"(.*)"\s*$/', $line, $m ) ) {
		if ( $msgid !== null && $msgstr !== null && $msgid !== '' ) {
			$entries[ $msgid ] = $msgstr;
		}
		$msgid  = stripcslashes( $m[1] );
		$msgstr = null;
		$in_id  = true;
		$in_str = false;
		continue;
	}
	if ( preg_match( '/^msgstr\s+"(.*)"\s*$/', $line, $m ) ) {
		$msgstr = stripcslashes( $m[1] );
		$in_id  = false;
		$in_str = true;
		continue;
	}
	if ( preg_match( '/^"(.*)"\s*$/', $line, $m ) ) {
		$chunk = stripcslashes( $m[1] );
		if ( $in_id && $msgid !== null ) {
			$msgid .= $chunk;
		} elseif ( $in_str && $msgstr !== null ) {
			$msgstr .= $chunk;
		}
		continue;
	}
	// blank / comment → flush
	if ( $line === '' || $line[0] === '#' ) {
		if ( $msgid !== null && $msgstr !== null && $msgid !== '' ) {
			$entries[ $msgid ] = $msgstr;
		}
		$msgid = $msgstr = null;
		$in_id = $in_str = false;
	}
}
if ( $msgid !== null && $msgstr !== null && $msgid !== '' ) {
	$entries[ $msgid ] = $msgstr;
}

if ( empty( $entries ) ) {
	return;
}

// Build GNU MO (little-endian).
ksort( $entries, SORT_STRING );
$ids   = array_keys( $entries );
$strs  = array_values( $entries );
$count = count( $ids );

// Originals and translations blobs (NUL-terminated).
$o_blob = '';
$t_blob = '';
$o_table = [];
$t_table = [];
foreach ( $ids as $i => $id ) {
	$o_table[] = [ strlen( $id ), strlen( $o_blob ) ];
	$o_blob   .= $id . "\0";
	$t_table[] = [ strlen( $strs[ $i ] ), strlen( $t_blob ) ];
	$t_blob   .= $strs[ $i ] . "\0";
}

// Header: magic, revision, N, offset originals, offset translations, hash size, hash offset.
$header_size = 28;
$o_offset    = $header_size;
$t_offset    = $header_size + ( 8 * $count );
$data_offset = $t_offset + ( 8 * $count );

$out  = pack( 'V*', 0x950412de, 0, $count, $o_offset, $t_offset, 0, 0 );
foreach ( $o_table as $row ) {
	$out .= pack( 'V*', $row[0], $data_offset + $row[1] );
}
foreach ( $t_table as $row ) {
	$out .= pack( 'V*', $row[0], $data_offset + strlen( $o_blob ) + $row[1] );
}
$out .= $o_blob . $t_blob;

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
@file_put_contents( $mo_path, $out );
