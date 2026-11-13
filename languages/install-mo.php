<?php
/**
 * Writes languages/wpswup-fa_IR.mo from embedded base64 if missing.
 *
 * @package WPSWup
 */
defined( 'ABSPATH' ) || exit;

$mo_path = SLL_DIR . 'languages/wpswup-fa_IR.mo';
if ( file_exists( $mo_path ) && filesize( $mo_path ) > 100 ) {
	return;
}

// Generated from languages/wpswup-fa_IR.po (49 strings, 100% translated).
$b64 = file_get_contents( SLL_DIR . 'languages/wpswup-fa_IR.mo.b64' );
if ( ! is_string( $b64 ) || $b64 === '' ) {
	return;
}
$bin = base64_decode( trim( $b64 ), true );
if ( ! is_string( $bin ) || strlen( $bin ) < 100 ) {
	return;
}
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
file_put_contents( $mo_path, $bin );
