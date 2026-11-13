<?php
/**
 * Writes languages/wpswup-fa_IR.mo if missing.
 */
defined( 'ABSPATH' ) || exit;

$mo_path = SLL_DIR . 'languages/wpswup-fa_IR.mo';
if ( file_exists( $mo_path ) && filesize( $mo_path ) > 100 ) {
	return;
}

$p1 = SLL_DIR . 'languages/wpswup-fa_IR.mo.b64.1';
$p2 = SLL_DIR . 'languages/wpswup-fa_IR.mo.b64.2';
if ( ! file_exists( $p1 ) || ! file_exists( $p2 ) ) {
	return;
}
$b64 = trim( (string) file_get_contents( $p1 ) ) . trim( (string) file_get_contents( $p2 ) );
$bin = base64_decode( $b64, true );
if ( ! is_string( $bin ) || strlen( $bin ) < 100 ) {
	return;
}
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_system_operations_file_put_contents
file_put_contents( $mo_path, $bin );
