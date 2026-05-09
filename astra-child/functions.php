<?php
/**
 * NSWPedia Child Theme Functions
 *
 * @package NSWPedia-Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'nswpedia_child_enqueue' );
function nswpedia_child_enqueue() {
	wp_enqueue_style(
		'astra-parent-style',
		get_template_directory_uri() . '/style.css'
	);
	wp_enqueue_style(
		'nswpedia-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'astra-parent-style' ),
		filemtime( get_stylesheet_directory() . '/style.css' )
	);
}
