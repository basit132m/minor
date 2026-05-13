<?php
/**
 * NSWPedia Child Theme Functions
 *
 * @package NSWPedia-Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================
// ENQUEUE STYLES
// ============================================================
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

// ============================================================
// CUSTOM REWRITE RULES — /type/, /genre/, /badge/, /publisher/, /download/
// After adding: go to Settings > Permalinks and click Save to flush rules.
// ============================================================
add_action( 'init', 'nswpedia_register_rewrites' );
function nswpedia_register_rewrites() {
	add_rewrite_rule( '^type/([^/]+)/?$',                           'index.php?nsw_type=$matches[1]',                                   'top' );
	add_rewrite_rule( '^genre/([^/]+)/?$',                          'index.php?nsw_genre=$matches[1]',                                  'top' );
	add_rewrite_rule( '^badge/([^/]+)/?$',                          'index.php?nsw_badge=$matches[1]',                                  'top' );
	add_rewrite_rule( '^publisher/([^/]+)/?$',                      'index.php?nsw_publisher=$matches[1]',                              'top' );
	add_rewrite_rule( '^download/([^/]+)/([0-9]+)/?$',              'index.php?nsw_download_slug=$matches[1]&nsw_download_index=$matches[2]', 'top' );
}

add_filter( 'query_vars', 'nswpedia_query_vars' );
function nswpedia_query_vars( $vars ) {
	$vars[] = 'nsw_type';
	$vars[] = 'nsw_genre';
	$vars[] = 'nsw_badge';
	$vars[] = 'nsw_publisher';
	$vars[] = 'nsw_download_slug';
	$vars[] = 'nsw_download_index';
	return $vars;
}

add_action( 'template_redirect', 'nswpedia_handle_custom_pages' );
function nswpedia_handle_custom_pages() {
	$nsw_type           = get_query_var( 'nsw_type' );
	$nsw_genre          = get_query_var( 'nsw_genre' );
	$nsw_badge          = get_query_var( 'nsw_badge' );
	$nsw_publisher      = get_query_var( 'nsw_publisher' );
	$nsw_download_slug  = get_query_var( 'nsw_download_slug' );
	$nsw_download_index = get_query_var( 'nsw_download_index' );

	if ( $nsw_type ) {
		nswpedia_render_archive_page( 'type', $nsw_type, ucwords( str_replace( '-', ' ', $nsw_type ) ) . ' ROMs' );
		exit;
	}
	if ( $nsw_genre ) {
		nswpedia_render_archive_page( 'genre', $nsw_genre, ucwords( str_replace( '-', ' ', $nsw_genre ) ) . ' Games' );
		exit;
	}
	if ( $nsw_badge ) {
		nswpedia_render_archive_page( 'badge', $nsw_badge, ucwords( str_replace( '-', ' ', $nsw_badge ) ) );
		exit;
	}
	if ( $nsw_publisher ) {
		nswpedia_render_archive_page( 'publisher', $nsw_publisher, ucwords( str_replace( '-', ' ', $nsw_publisher ) ) . ' Games' );
		exit;
	}
	if ( $nsw_download_slug && '' !== $nsw_download_index ) {
		nswpedia_render_download_page( $nsw_download_slug, (int) $nsw_download_index );
		exit;
	}
}

// ============================================================
// CATEGORY ARCHIVE — handles /category-slug/ and /category-slug/page/N/
// Page 2+ 404s because WordPress marks them 404 before template_redirect.
// We intercept early (priority 1), parse the URL, and render ourselves.
// ============================================================
add_action( 'template_redirect', 'nswpedia_category_archive', 1 );
function nswpedia_category_archive() {
	global $wp_query;

	$category = null;
	$paged    = 1;

	if ( is_category() ) {
		$category = get_queried_object();
		$paged    = max( 1, (int) get_query_var( 'paged' ) );
	} elseif ( $wp_query->is_404 ) {
		$path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
		if ( preg_match( '#^([^/]+)/page/(\d+)/?$#', $path, $m ) ) {
			$cat = get_category_by_slug( $m[1] );
			if ( $cat ) {
				$category = $cat;
				$paged    = (int) $m[2];
			}
		}
	}

	if ( ! $category ) return;

	$wp_query->is_404  = false;
	$wp_query->is_home = false;
	status_header( 200 );

	$query = new WP_Query( array(
		'post_type'      => 'post',
		'posts_per_page' => 24,
		'paged'          => $paged,
		'cat'            => $category->term_id,
	) );

	get_header();
	echo '<div class="nsw-archive-page ast-container">';
	echo '<h1 class="nsw-archive-heading">' . esc_html( $category->name ) . '</h1>';

	if ( $query->have_posts() ) {
		echo '<div class="nsw-rom-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
			nswpedia_rom_card( get_the_ID() );
		}
		echo '</div>';
		echo '<div class="nsw-pagination">' . paginate_links( array(
			'total'   => $query->max_num_pages,
			'current' => $paged,
			'base'    => get_category_link( $category->term_id ) . '%_%',
			'format'  => 'page/%#%/',
		) ) . '</div>';
	} else {
		echo '<p class="nsw-no-results">No games found.</p>';
	}

	echo '</div>';
	wp_reset_postdata();
	get_footer();
	exit;
}

// ============================================================
// ARCHIVE PAGE RENDERER
// ============================================================
function nswpedia_render_archive_page( $type, $slug, $heading ) {
	$meta_field_map = array(
		'type'      => '_game_format',
		'genre'     => '_game_genre',
		'badge'     => '_game_badge',
		'publisher' => '_game_publisher',
	);
	$field = isset( $meta_field_map[ $type ] ) ? $meta_field_map[ $type ] : '_game_' . $type;

	// Try both the raw slug and humanised version for format values like "NSP", "XCI"
	$meta_value = $slug;
	// For type archives, try uppercase first (NSP, XCI, NSP+XCI)
	if ( 'type' === $type ) {
		$meta_value = strtoupper( str_replace( '-', '+', $slug ) );
	}

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$query = new WP_Query( array(
		'post_type'      => 'post',
		'posts_per_page' => 24,
		'paged'          => $paged,
		'meta_query'     => array(
			array(
				'key'     => $field,
				'value'   => $meta_value,
				'compare' => '=',
			),
		),
	) );

	get_header();
	echo '<div class="nsw-archive-page ast-container">';
	echo '<h1 class="nsw-archive-heading">' . esc_html( $heading ) . '</h1>';

	if ( $query->have_posts() ) {
		echo '<div class="nsw-rom-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
			nswpedia_rom_card( get_the_ID() );
		}
		echo '</div>';
		echo '<div class="nsw-pagination">' . paginate_links( array(
			'total'   => $query->max_num_pages,
			'current' => $paged,
		) ) . '</div>';
	} else {
		echo '<p class="nsw-no-results">No games found.</p>';
	}

	echo '</div>';
	wp_reset_postdata();
	get_footer();
}

// ============================================================
// DOWNLOAD INTERSTITIAL PAGE
// ============================================================
function nswpedia_render_download_page( $post_slug, $index ) {
	$post = get_page_by_path( $post_slug, OBJECT, 'post' );
	if ( ! $post ) {
		status_header( 404 );
		wp_die( 'Download not found.', 'Not Found', array( 'response' => 404 ) );
	}
	$downloads = get_post_meta( $post->ID, '_game_downloads', true );
	if ( ! is_array( $downloads ) || ! isset( $downloads[ $index ] ) ) {
		status_header( 404 );
		wp_die( 'Download not found.', 'Not Found', array( 'response' => 404 ) );
	}
	$dl    = $downloads[ $index ];
	$url   = esc_url( $dl['url'] ?? '' );
	$type  = esc_html( $dl['type'] ?? '' );
	$size  = esc_html( $dl['size'] ?? '' );
	$title = esc_html( $post->post_title );
	$back  = esc_url( get_permalink( $post->ID ) );
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Download <?php echo $title; ?> &mdash; NSWPedia.net</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f2f2f2;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.dl-wrap{background:#fff;border-radius:14px;padding:44px 52px;max-width:500px;width:100%;text-align:center;box-shadow:0 6px 32px rgba(0,0,0,.10)}
.dl-site{font-size:13px;font-weight:700;letter-spacing:1px;color:#f0a500;text-transform:uppercase;margin-bottom:20px}
.dl-title{font-size:22px;font-weight:800;color:#111;margin-bottom:6px;line-height:1.3}
.dl-meta{font-size:14px;color:#888;margin-bottom:32px}
.dl-btn{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(135deg,#f0a500,#e09200);color:#fff;font-size:17px;font-weight:700;padding:16px 44px;border-radius:10px;text-decoration:none;letter-spacing:.2px;transition:all .2s;box-shadow:0 4px 14px rgba(240,165,0,.35)}
.dl-btn:hover{background:linear-gradient(135deg,#e09200,#c97f00);box-shadow:0 6px 20px rgba(240,165,0,.45);color:#fff;text-decoration:none}
.dl-btn svg{flex-shrink:0}
.dl-note{font-size:12px;color:#bbb;margin-top:20px;line-height:1.6}
.dl-note a{color:#f0a500;text-decoration:none}
.dl-back{display:inline-block;margin-top:18px;font-size:13px;color:#999;text-decoration:none}
.dl-back:hover{color:#f0a500}
</style>
</head>
<body>
<div class="dl-wrap">
	<div class="dl-site">NSWPedia.net</div>
	<div class="dl-title"><?php echo $title; ?></div>
	<div class="dl-meta"><?php echo $type; ?><?php if ( $size ) echo ' &bull; ' . $size; ?></div>
	<a class="dl-btn" href="<?php echo $url; ?>" target="_blank" rel="nofollow">
		<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
		Download Now
	</a>
	<p class="dl-note">Your download opens in a new tab.<br>If it does not start, <a href="<?php echo $url; ?>" target="_blank" rel="nofollow">click here</a>.</p>
	<a class="dl-back" href="<?php echo $back; ?>">&larr; Back to <?php echo $title; ?></a>
</div>
</body>
</html>
	<?php
}

// ============================================================
// GAME INFO META BOX
// ============================================================
add_action( 'add_meta_boxes', 'nswpedia_game_meta_box' );
function nswpedia_game_meta_box() {
	add_meta_box( 'nswpedia_game_info', 'Game Info', 'nswpedia_game_meta_box_html', 'post', 'normal', 'high' );
}

function nswpedia_game_meta_box_html( $post ) {
	wp_nonce_field( 'nswpedia_game_save', 'nswpedia_game_nonce' );
	$fields = array(
		'_game_app_name'          => 'App Name',
		'_game_genre'             => 'Genre',
		'_game_publisher'         => 'Publisher',
		'_game_version'           => 'Version',
		'_game_size'              => 'Size (e.g. 4.2 GB)',
		'_game_release_date'      => 'Release Date',
		'_game_format'            => 'Format (NSP / XCI / NSP+XCI)',
		'_game_language'          => 'Language',
		'_game_title_id'          => 'Title ID',
		'_game_required_firmware' => 'Required Firmware',
		'_game_badge'             => 'Badge slug (e.g. exclusives-games)',
	);
	echo '<table class="form-table">';
	foreach ( $fields as $key => $label ) {
		$val = get_post_meta( $post->ID, $key, true );
		echo '<tr>';
		echo '<th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '" class="regular-text"></td>';
		echo '</tr>';
	}
	echo '</table>';
}

add_action( 'save_post', 'nswpedia_game_meta_save' );
function nswpedia_game_meta_save( $post_id ) {
	if ( ! isset( $_POST['nswpedia_game_nonce'] ) || ! wp_verify_nonce( $_POST['nswpedia_game_nonce'], 'nswpedia_game_save' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$fields = array(
		'_game_app_name', '_game_genre', '_game_publisher', '_game_version',
		'_game_size', '_game_release_date', '_game_format', '_game_language',
		'_game_title_id', '_game_required_firmware', '_game_badge',
	);
	foreach ( $fields as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
		}
	}
}

// ============================================================
// SCREENSHOTS META BOX
// ============================================================
add_action( 'admin_enqueue_scripts', 'nswpedia_enqueue_media_uploader' );
function nswpedia_enqueue_media_uploader( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) return;
	wp_enqueue_media();
}

add_action( 'add_meta_boxes', 'nswpedia_screenshots_meta_box' );
function nswpedia_screenshots_meta_box() {
	add_meta_box( 'nswpedia_screenshots', 'Screenshots', 'nswpedia_screenshots_meta_box_html', 'post', 'normal', 'default' );
}

function nswpedia_screenshots_meta_box_html( $post ) {
	wp_nonce_field( 'nswpedia_screenshots_save', 'nswpedia_screenshots_nonce' );
	echo '<div id="nsw-screenshots-wrap" style="display:flex;flex-wrap:wrap;gap:14px;padding:4px 0;">';
	for ( $i = 1; $i <= 8; $i++ ) {
		$img_id = get_post_meta( $post->ID, "_screenshot_id_{$i}", true );
		$thumb  = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
		echo '<div class="nsw-shot-slot" style="width:130px;text-align:center;">';
		echo '<div class="nsw-shot-preview" style="width:130px;height:74px;background:#f0f0f0;border:1px solid #ccc;border-radius:4px;display:flex;align-items:center;justify-content:center;overflow:hidden;">';
		if ( $thumb ) {
			echo '<img src="' . esc_url( $thumb ) . '" style="max-width:100%;max-height:100%;">';
		}
		echo '</div>';
		echo '<input type="hidden" name="_screenshot_id_' . $i . '" id="screenshot_id_' . $i . '" value="' . esc_attr( $img_id ) . '">';
		echo '<button type="button" class="button nsw-upload-btn" data-slot="' . $i . '" style="margin-top:5px;width:100%;">Select</button>';
		if ( $img_id ) {
			echo '<button type="button" class="button nsw-remove-btn" data-slot="' . $i . '" style="margin-top:3px;width:100%;">Remove</button>';
		}
		echo '</div>';
	}
	echo '</div>';
	?>
	<script>
	jQuery(function($){
		$('.nsw-upload-btn').on('click',function(){
			var slot=$(this).data('slot');
			var frame=wp.media({title:'Select Screenshot',multiple:false});
			frame.on('select',function(){
				var att=frame.state().get('selection').first().toJSON();
				$('#screenshot_id_'+slot).val(att.id);
				var preview=$('[data-slot="'+slot+'"].nsw-upload-btn').siblings('.nsw-shot-preview');
				preview.html('<img src="'+att.url+'" style="max-width:100%;max-height:100%;">');
			});
			frame.open();
		});
		$(document).on('click','.nsw-remove-btn',function(){
			var slot=$(this).data('slot');
			$('#screenshot_id_'+slot).val('');
			$('[data-slot="'+slot+'"].nsw-upload-btn').siblings('.nsw-shot-preview').html('');
		});
	});
	</script>
	<?php
}

add_action( 'save_post', 'nswpedia_screenshots_save' );
function nswpedia_screenshots_save( $post_id ) {
	if ( ! isset( $_POST['nswpedia_screenshots_nonce'] ) || ! wp_verify_nonce( $_POST['nswpedia_screenshots_nonce'], 'nswpedia_screenshots_save' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;
	for ( $i = 1; $i <= 8; $i++ ) {
		$key = "_screenshot_id_{$i}";
		if ( array_key_exists( $key, $_POST ) ) {
			$val = absint( $_POST[ $key ] );
			if ( $val ) {
				update_post_meta( $post_id, $key, $val );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
	}
}

// ============================================================
// DOWNLOADS META BOX
// ============================================================
add_action( 'add_meta_boxes', 'nswpedia_downloads_meta_box' );
function nswpedia_downloads_meta_box() {
	add_meta_box( 'nswpedia_downloads', 'Download Links', 'nswpedia_downloads_meta_box_html', 'post', 'normal', 'default' );
}

function nswpedia_downloads_meta_box_html( $post ) {
	wp_nonce_field( 'nswpedia_downloads_save', 'nswpedia_downloads_nonce' );
	$downloads = get_post_meta( $post->ID, '_game_downloads', true );
	if ( ! is_array( $downloads ) ) $downloads = array();
	if ( empty( $downloads ) ) $downloads = array( array( 'url' => '', 'type' => 'NSP', 'size' => '' ) );
	echo '<div id="nsw-downloads-wrap">';
	foreach ( $downloads as $i => $dl ) {
		echo '<div class="nsw-dl-row" style="display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">';
		echo '<input type="text" name="_game_downloads[' . $i . '][url]" placeholder="Direct download URL" value="' . esc_attr( $dl['url'] ?? '' ) . '" class="regular-text" style="flex:2;min-width:200px;">';
		echo '<select name="_game_downloads[' . $i . '][type]">';
		foreach ( array( 'NSP', 'XCI', 'NSP+XCI' ) as $t ) {
			$sel = ( ( $dl['type'] ?? 'NSP' ) === $t ) ? ' selected' : '';
			echo '<option' . $sel . '>' . esc_html( $t ) . '</option>';
		}
		echo '</select>';
		echo '<input type="text" name="_game_downloads[' . $i . '][size]" placeholder="e.g. 4.2 GB" value="' . esc_attr( $dl['size'] ?? '' ) . '" style="width:110px;">';
		echo '<button type="button" class="button nsw-remove-dl" style="color:#a00;">&#10005; Remove</button>';
		echo '</div>';
	}
	echo '</div>';
	echo '<button type="button" class="button" id="nsw-add-dl">+ Add Download Link</button>';
	?>
	<script>
	jQuery(function($){
		$('#nsw-add-dl').on('click',function(){
			var i=$('#nsw-downloads-wrap .nsw-dl-row').length;
			var html='<div class="nsw-dl-row" style="display:flex;gap:10px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">'
				+'<input type="text" name="_game_downloads['+i+'][url]" placeholder="Direct download URL" class="regular-text" style="flex:2;min-width:200px;">'
				+'<select name="_game_downloads['+i+'][type]"><option>NSP</option><option>XCI</option><option>NSP+XCI</option></select>'
				+'<input type="text" name="_game_downloads['+i+'][size]" placeholder="e.g. 4.2 GB" style="width:110px;">'
				+'<button type="button" class="button nsw-remove-dl" style="color:#a00;">&#10005; Remove</button>'
				+'</div>';
			$('#nsw-downloads-wrap').append(html);
		});
		$(document).on('click','.nsw-remove-dl',function(){
			$(this).closest('.nsw-dl-row').remove();
		});
	});
	</script>
	<?php
}

add_action( 'save_post', 'nswpedia_downloads_save' );
function nswpedia_downloads_save( $post_id ) {
	if ( ! isset( $_POST['nswpedia_downloads_nonce'] ) || ! wp_verify_nonce( $_POST['nswpedia_downloads_nonce'], 'nswpedia_downloads_save' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;
	if ( isset( $_POST['_game_downloads'] ) && is_array( $_POST['_game_downloads'] ) ) {
		$clean = array();
		foreach ( $_POST['_game_downloads'] as $dl ) {
			if ( ! empty( $dl['url'] ) ) {
				$clean[] = array(
					'url'  => esc_url_raw( $dl['url'] ),
					'type' => sanitize_text_field( $dl['type'] ?? 'NSP' ),
					'size' => sanitize_text_field( $dl['size'] ?? '' ),
				);
			}
		}
		update_post_meta( $post_id, '_game_downloads', $clean );
	} else {
		delete_post_meta( $post_id, '_game_downloads' );
	}
}

// ============================================================
// ARTICLE HERO — featured image + H1 + badges + star rating
// Star ratings live ONLY here; removed from game info table.
// ============================================================
add_action( 'astra_primary_content_top', 'nswpedia_article_hero' );
function nswpedia_article_hero() {
	if ( ! is_single() ) return;
	$post_id = get_the_ID();
	$version = get_post_meta( $post_id, '_game_version', true );
	$format  = get_post_meta( $post_id, '_game_format', true );
	$genre   = get_post_meta( $post_id, '_game_genre', true );
	$badge   = get_post_meta( $post_id, '_game_badge', true );
	$thumb   = get_the_post_thumbnail_url( $post_id, 'large' );
	?>
	<div class="nsw-article-hero">
		<?php if ( $thumb ) : ?>
		<div class="nsw-hero-img-wrap">
			<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php the_title_attribute(); ?>" class="nsw-hero-img">
		</div>
		<?php endif; ?>
		<div class="nsw-hero-meta">
			<h1 class="nsw-article-title"><?php the_title(); ?></h1>
			<div class="nsw-hero-badges">
				<?php if ( $version ) : ?>
				<span class="nsw-pill nsw-pill-version">v<?php echo esc_html( $version ); ?></span>
				<?php endif; ?>
				<?php if ( $format ) : ?>
				<a href="<?php echo esc_url( home_url( '/type/' . sanitize_title( $format ) ) ); ?>" class="nsw-pill nsw-pill-format"><?php echo esc_html( $format ); ?></a>
				<?php endif; ?>
				<?php if ( $genre ) : ?>
				<a href="<?php echo esc_url( home_url( '/genre/' . sanitize_title( $genre ) ) ); ?>" class="nsw-pill nsw-pill-genre"><?php echo esc_html( $genre ); ?></a>
				<?php endif; ?>
				<?php if ( $badge ) : ?>
				<a href="<?php echo esc_url( home_url( '/badge/' . sanitize_title( $badge ) ) ); ?>" class="nsw-pill nsw-pill-badge"><?php echo esc_html( ucwords( str_replace( '-', ' ', $badge ) ) ); ?></a>
				<?php endif; ?>
			</div>
			<div class="nsw-hero-rating">
				<?php echo do_shortcode( '[kkstarratings]' ); ?>
			</div>
		</div>
	</div>
	<?php
}

// ============================================================
// GAME INFO TABLE — injected before post content
// No star ratings here (moved to hero only).
// ============================================================
add_filter( 'the_content', 'nswpedia_display_game_info', 10 );
function nswpedia_display_game_info( $content ) {
	if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) return $content;
	$post_id = get_the_ID();

	$fields = array(
		'_game_app_name'          => 'App Name',
		'_game_genre'             => 'Genre',
		'_game_publisher'         => 'Publisher',
		'_game_version'           => 'Version',
		'_game_size'              => 'Size',
		'_game_release_date'      => 'Release Date',
		'_game_format'            => 'Format',
		'_game_language'          => 'Language',
		'_game_title_id'          => 'Title ID',
		'_game_required_firmware' => 'Required Firmware',
	);

	$rows     = '';
	$has_data = false;
	foreach ( $fields as $key => $label ) {
		$val = get_post_meta( $post_id, $key, true );
		if ( ! $val ) continue;
		$has_data = true;
		if ( '_game_genre' === $key ) {
			$display = '<a href="' . esc_url( home_url( '/genre/' . sanitize_title( $val ) ) ) . '">' . esc_html( $val ) . '</a>';
		} elseif ( '_game_format' === $key ) {
			$display = '<a href="' . esc_url( home_url( '/type/' . sanitize_title( $val ) ) ) . '">' . esc_html( $val ) . '</a>';
		} elseif ( '_game_publisher' === $key ) {
			$display = '<a href="' . esc_url( home_url( '/publisher/' . sanitize_title( $val ) ) ) . '">' . esc_html( $val ) . '</a>';
		} else {
			$display = esc_html( $val );
		}
		$rows .= '<tr><th>' . esc_html( $label ) . '</th><td>' . $display . '</td></tr>';
	}

	if ( ! $has_data ) return $content;

	$table = '<div class="nsw-game-info-table"><table><tbody>' . $rows . '</tbody></table></div>';
	return $table . $content;
}

// ============================================================
// SCREENSHOTS — grid + lightbox (full-size images, no dimensions)
// ============================================================
add_filter( 'the_content', 'nswpedia_display_screenshots', 9 );
function nswpedia_display_screenshots( $content ) {
	if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) return $content;
	$post_id = get_the_ID();

	$shots = array();
	for ( $i = 1; $i <= 8; $i++ ) {
		$img_id = get_post_meta( $post_id, "_screenshot_id_{$i}", true );
		if ( ! $img_id ) continue;
		$thumb = wp_get_attachment_image_url( $img_id, 'medium_large' );
		$full  = wp_get_attachment_image_url( $img_id, 'full' );
		if ( $thumb && $full ) {
			$shots[] = array( 'thumb' => $thumb, 'full' => $full );
		}
	}
	if ( empty( $shots ) ) return $content;

	$html = '<div class="nsw-screenshots"><h3 class="nsw-screenshots-heading">Screenshots</h3><div class="nsw-shots-grid">';
	foreach ( $shots as $idx => $shot ) {
		$html .= '<a href="' . esc_url( $shot['full'] ) . '" class="nsw-shot-thumb" data-index="' . $idx . '">';
		$html .= '<img src="' . esc_url( $shot['thumb'] ) . '" alt="Screenshot ' . ( $idx + 1 ) . '" loading="lazy">';
		$html .= '</a>';
	}
	$html .= '</div></div>';

	// Lightbox
	$html .= '<div id="nsw-lightbox" class="nsw-lightbox" aria-hidden="true">';
	$html .= '<div class="nsw-lb-overlay"></div>';
	$html .= '<div class="nsw-lb-inner">';
	$html .= '<button class="nsw-lb-close" aria-label="Close">&times;</button>';
	$html .= '<button class="nsw-lb-prev" aria-label="Previous">&#10094;</button>';
	$html .= '<img class="nsw-lb-img" src="" alt="">';
	$html .= '<button class="nsw-lb-next" aria-label="Next">&#10095;</button>';
	$html .= '</div></div>';

	$shots_json = wp_json_encode( array_map( function( $s ) { return $s['full']; }, $shots ) );

	$html .= '<script>
(function(){
var shots=' . $shots_json . ';
var cur=0;
var lb=document.getElementById("nsw-lightbox");
if(!lb)return;
var lbImg=lb.querySelector(".nsw-lb-img");
function show(i){cur=(i+shots.length)%shots.length;lbImg.src=shots[cur];lb.classList.add("is-open");document.body.style.overflow="hidden";}
function hide(){lb.classList.remove("is-open");document.body.style.overflow="";}
document.querySelectorAll(".nsw-shot-thumb").forEach(function(el){
  el.addEventListener("click",function(e){e.preventDefault();show(parseInt(this.dataset.index,10));});
});
lb.querySelector(".nsw-lb-close").addEventListener("click",hide);
lb.querySelector(".nsw-lb-overlay").addEventListener("click",hide);
lb.querySelector(".nsw-lb-prev").addEventListener("click",function(){show(cur-1);});
lb.querySelector(".nsw-lb-next").addEventListener("click",function(){show(cur+1);});
document.addEventListener("keydown",function(e){
  if(!lb.classList.contains("is-open"))return;
  if(e.key==="Escape")hide();
  if(e.key==="ArrowLeft")show(cur-1);
  if(e.key==="ArrowRight")show(cur+1);
});
})();
</script>';

	return $html . $content;
}

// ============================================================
// DOWNLOAD BUTTONS — appended after content
// Each button links to /download/{slug}/{index}/ (noindex interstitial)
// ============================================================
add_filter( 'the_content', 'nswpedia_display_downloads', 20 );
function nswpedia_display_downloads( $content ) {
	if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) return $content;
	$post_id   = get_the_ID();
	$downloads = get_post_meta( $post_id, '_game_downloads', true );
	if ( ! is_array( $downloads ) || empty( $downloads ) ) return $content;

	$slug     = get_post_field( 'post_name', $post_id );
	$app_name = get_post_meta( $post_id, '_game_app_name', true ) ?: get_the_title( $post_id );
	$html     = '<div class="nsw-downloads"><h3 class="nsw-downloads-heading">Download</h3>';
	foreach ( $downloads as $i => $dl ) {
		if ( empty( $dl['url'] ) ) continue;
		$type   = $dl['type'] ?? 'NSP';
		$size   = esc_html( $dl['size'] ?? '' );
		$dl_url = esc_url( home_url( '/download/' . $slug . '/' . $i . '/' ) );
		$label  = esc_html( $app_name ) . ' <span class="nsw-dl-type">- ' . esc_html( strtolower( $type ) ) . '</span>';
		$html  .= '<a href="' . $dl_url . '" class="nsw-dl-btn" target="_blank" rel="nofollow">';
		$html  .= '<span class="nsw-dl-left">';
		$html  .= '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>';
		$html  .= '<span class="nsw-dl-label">' . $label . '</span>';
		$html  .= '</span>';
		$html  .= '<span class="nsw-dl-size">Download' . ( $size ? ' &bull; ' . $size : '' ) . '</span>';
		$html  .= '</a>';
	}
	$html .= '</div>';

	return $content . $html;
}

// ============================================================
// ROM CARD
// ============================================================
function nswpedia_rom_card( $post_id ) {
	$title   = get_the_title( $post_id );
	$link    = get_permalink( $post_id );
	$thumb   = get_the_post_thumbnail_url( $post_id, 'medium' );
	$genre   = get_post_meta( $post_id, '_game_genre', true );
	$format  = get_post_meta( $post_id, '_game_format', true );
	$size    = get_post_meta( $post_id, '_game_size', true );
	$version = get_post_meta( $post_id, '_game_version', true );
	?>
	<div class="nsw-rom-card">
		<a href="<?php echo esc_url( $link ); ?>" class="nsw-card-img-link">
			<?php if ( $thumb ) : ?>
			<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
			<?php else : ?>
			<div class="nsw-card-no-img"></div>
			<?php endif; ?>
		</a>
		<div class="nsw-card-body">
			<h3 class="nsw-card-title"><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a></h3>
			<div class="nsw-card-meta">
				<?php if ( $genre ) : ?>
				<span class="nsw-card-genre"><?php echo esc_html( $genre ); ?></span>
				<?php endif; ?>
				<?php if ( $format ) : ?>
				<span class="nsw-card-format"><?php echo esc_html( $format ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $size || $version ) : ?>
			<div class="nsw-card-footer">
				<?php if ( $size ) : ?>
				<span class="nsw-card-size"><?php echo esc_html( $size ); ?></span>
				<?php endif; ?>
				<?php if ( $version ) : ?>
				<span class="nsw-card-version">v<?php echo esc_html( $version ); ?></span>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

// ============================================================
// ROM SECTION RENDERER
// ============================================================
function nswpedia_rom_section( $heading, $post_ids, $see_all_url = '' ) {
	if ( empty( $post_ids ) ) return '';
	ob_start();
	?>
	<section class="nsw-rom-section">
		<div class="nsw-section-header">
			<h2 class="nsw-section-title"><?php echo esc_html( $heading ); ?></h2>
			<?php if ( $see_all_url ) : ?>
			<a href="<?php echo esc_url( $see_all_url ); ?>" class="nsw-see-all">See All &rarr;</a>
			<?php endif; ?>
		</div>
		<div class="nsw-rom-grid">
			<?php foreach ( $post_ids as $pid ) : nswpedia_rom_card( $pid ); endforeach; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

// ============================================================
// SHORTCODES
// ============================================================
add_shortcode( 'latest_roms', 'nswpedia_latest_roms_shortcode' );
function nswpedia_latest_roms_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'count' => 12, 'see_all_url' => '' ), $atts );
	$q    = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => (int) $atts['count'], 'orderby' => 'date', 'order' => 'DESC' ) );
	$ids  = wp_list_pluck( $q->posts, 'ID' );
	wp_reset_postdata();
	return nswpedia_rom_section( 'Latest ROMs', $ids, $atts['see_all_url'] );
}

add_shortcode( 'popular_roms', 'nswpedia_popular_roms_shortcode' );
function nswpedia_popular_roms_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'count' => 8, 'see_all_url' => '' ), $atts );
	$q    = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => (int) $atts['count'], 'orderby' => 'comment_count', 'order' => 'DESC' ) );
	$ids  = wp_list_pluck( $q->posts, 'ID' );
	wp_reset_postdata();
	return nswpedia_rom_section( 'Popular ROMs', $ids, $atts['see_all_url'] );
}

add_shortcode( 'hero_search', 'nswpedia_hero_search_shortcode' );
function nswpedia_hero_search_shortcode() {
	$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
	$home_url = esc_url( home_url( '/' ) );
	ob_start();
	?>
	<div class="nsw-hero-search-wrap">
		<div class="nsw-hero-search-box">
			<input type="text" id="nsw-hero-input" placeholder="Search Nintendo Switch ROMs..." autocomplete="off">
			<button type="button" id="nsw-hero-btn">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			</button>
		</div>
		<div id="nsw-hero-results" class="nsw-hero-results"></div>
	</div>
	<script>
	(function(){
		var inp=document.getElementById('nsw-hero-input');
		var res=document.getElementById('nsw-hero-results');
		var timer;
		inp.addEventListener('input',function(){
			clearTimeout(timer);
			var q=this.value.trim();
			if(q.length<2){res.style.display='none';return;}
			timer=setTimeout(function(){doSearch(q);},300);
		});
		document.getElementById('nsw-hero-btn').addEventListener('click',function(){
			var q=inp.value.trim();
			if(q) window.location.href='<?php echo $home_url; ?>?s='+encodeURIComponent(q);
		});
		inp.addEventListener('keydown',function(e){
			if(e.key==='Enter'){var q=this.value.trim();if(q) window.location.href='<?php echo $home_url; ?>?s='+encodeURIComponent(q);}
		});
		function doSearch(q){
			var xhr=new XMLHttpRequest();
			xhr.open('POST','<?php echo $ajax_url; ?>');
			xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
			xhr.onload=function(){
				try{var data=JSON.parse(xhr.responseText);}catch(e){return;}
				if(!data.length){res.innerHTML='<div class="nsw-no-results-msg">No results found.</div>';res.style.display='block';return;}
				var html='';
				data.forEach(function(item){
					html+='<a class="nsw-sri" href="'+item.url+'">';
					if(item.img) html+='<img src="'+item.img+'" alt="">';
					html+='<span>'+item.title+'</span></a>';
				});
				res.innerHTML=html;res.style.display='block';
			};
			xhr.send('action=nswpedia_live_search&q='+encodeURIComponent(q));
		}
		document.addEventListener('click',function(e){if(!inp.contains(e.target)&&!res.contains(e.target)) res.style.display='none';});
	})();
	</script>
	<?php
	return ob_get_clean();
}

// ============================================================
// LIVE SEARCH AJAX HANDLER
// ============================================================
add_action( 'wp_ajax_nswpedia_live_search',        'nswpedia_live_search_handler' );
add_action( 'wp_ajax_nopriv_nswpedia_live_search', 'nswpedia_live_search_handler' );
function nswpedia_live_search_handler() {
	$q = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
	if ( strlen( $q ) < 2 ) { wp_send_json( array() ); }
	$query = new WP_Query( array(
		'post_type'      => 'post',
		'posts_per_page' => 6,
		's'              => $q,
	) );
	$results = array();
	foreach ( $query->posts as $post ) {
		$results[] = array(
			'title' => $post->post_title,
			'url'   => get_permalink( $post->ID ),
			'img'   => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: '',
		);
	}
	wp_send_json( $results );
}

// Live search for the header search bar
add_action( 'wp_footer', 'nswpedia_header_search_script' );
function nswpedia_header_search_script() {
	if ( is_admin() ) return;
	$ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
	?>
	<script>
	(function(){
		var inp=document.querySelector('.ast-header-search .search-field,.ast-search-menu-icon .search-field');
		if(!inp) return;
		var form=inp.closest('form');
		if(!form) return;
		var res=document.createElement('div');
		res.className='nsw-hsr';
		res.style.cssText='display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #ddd;border-radius:0 0 7px 7px;z-index:9999;max-height:340px;overflow-y:auto;box-shadow:0 6px 18px rgba(0,0,0,.10);';
		form.style.position='relative';
		form.appendChild(res);
		var timer;
		inp.addEventListener('input',function(){
			clearTimeout(timer);
			var q=this.value.trim();
			if(q.length<2){res.style.display='none';return;}
			timer=setTimeout(function(){doSearch(q);},300);
		});
		function doSearch(q){
			var xhr=new XMLHttpRequest();
			xhr.open('POST','<?php echo $ajax_url; ?>');
			xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
			xhr.onload=function(){
				try{var data=JSON.parse(xhr.responseText);}catch(e){return;}
				if(!data.length){res.innerHTML='<div style="padding:12px 16px;color:#999;font-size:13px;">No results.</div>';res.style.display='block';return;}
				var html='';
				data.forEach(function(item){
					html+='<a href="'+item.url+'" style="display:flex;align-items:center;gap:10px;padding:9px 14px;text-decoration:none;color:#111;border-bottom:1px solid #f5f5f5;font-size:13px;">';
					if(item.img) html+='<img src="'+item.img+'" style="width:38px;height:38px;object-fit:cover;border-radius:4px;flex-shrink:0;" alt="">';
					html+='<span>'+item.title+'</span></a>';
				});
				res.innerHTML=html;res.style.display='block';
			};
			xhr.send('action=nswpedia_live_search&q='+encodeURIComponent(q));
		}
		document.addEventListener('click',function(e){if(!form.contains(e.target)) res.style.display='none';});
	})();
	</script>
	<?php
}

// ============================================================
// TUTORIALS PAGE — /tutorials/
// ============================================================
add_action( 'init', 'nswpedia_tutorials_rewrite' );
function nswpedia_tutorials_rewrite() {
	add_rewrite_rule( '^tutorials/?$', 'index.php?nsw_tutorials=1', 'top' );
	add_rewrite_rule( '^tutorials/page/([0-9]+)/?$', 'index.php?nsw_tutorials=1&paged=$matches[1]', 'top' );
}

add_filter( 'query_vars', 'nswpedia_tutorials_query_var' );
function nswpedia_tutorials_query_var( $vars ) {
	$vars[] = 'nsw_tutorials';
	return $vars;
}

add_action( 'template_redirect', 'nswpedia_tutorials_page', 2 );
function nswpedia_tutorials_page() {
	if ( ! get_query_var( 'nsw_tutorials' ) ) return;

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	$query = new WP_Query( array(
		'post_type'      => 'post',
		'posts_per_page' => 12,
		'paged'          => $paged,
		'category_name'  => 'tutorials',
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	get_header();
	echo '<div class="nsw-archive-page ast-container">';
	echo '<h1 class="nsw-archive-heading">Tutorials</h1>';

	if ( $query->have_posts() ) {
		echo '<div class="nsw-tutorial-grid">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$pid     = get_the_ID();
			$thumb   = get_the_post_thumbnail_url( $pid, 'medium_large' );
			$link    = get_permalink( $pid );
			$title   = get_the_title( $pid );
			$excerpt = get_the_excerpt();
			$date    = get_the_date( 'M j, Y', $pid );
			echo '<div class="nsw-tutorial-card">';
			if ( $thumb ) {
				echo '<a href="' . esc_url( $link ) . '" class="nsw-tc-img-wrap">';
				echo '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $title ) . '" loading="lazy">';
				echo '</a>';
			} else {
				echo '<div class="nsw-tc-no-img"></div>';
			}
			echo '<div class="nsw-tc-body">';
			echo '<span class="nsw-tc-date">' . esc_html( $date ) . '</span>';
			echo '<h2 class="nsw-tc-title"><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h2>';
			if ( $excerpt ) {
				echo '<p class="nsw-tc-excerpt">' . esc_html( wp_trim_words( $excerpt, 18, '…' ) ) . '</p>';
			}
			echo '<a href="' . esc_url( $link ) . '" class="nsw-tc-read-more">Read More &rarr;</a>';
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
		echo '<div class="nsw-pagination">' . paginate_links( array(
			'total'   => $query->max_num_pages,
			'current' => $paged,
			'base'    => home_url( '/tutorials/page/%#%/' ),
			'format'  => '',
		) ) . '</div>';
	} else {
		echo '<p class="nsw-no-results">No tutorials found. Create posts in the <strong>Tutorials</strong> category to show them here.</p>';
	}

	echo '</div>';
	wp_reset_postdata();
	get_footer();
	exit;
}

// ============================================================
// EMULATORS — CPT, Meta Boxes, Rewrites, Templates
// ============================================================

// --- Register CPT ---
add_action( 'init', 'nswpedia_register_emulator_cpt' );
function nswpedia_register_emulator_cpt() {
	register_post_type( 'emulator', array(
		'labels'       => array(
			'name'               => 'Emulators',
			'singular_name'      => 'Emulator',
			'add_new_item'       => 'Add New Emulator',
			'edit_item'          => 'Edit Emulator',
			'new_item'           => 'New Emulator',
			'view_item'          => 'View Emulator',
			'search_items'       => 'Search Emulators',
			'not_found'          => 'No emulators found',
			'not_found_in_trash' => 'No emulators found in Trash',
		),
		'public'       => true,
		'has_archive'  => false,
		'rewrite'      => false,
		'menu_icon'    => 'dashicons-games',
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'show_in_rest' => true,
	) );
}

// --- Custom permalink: /emulators/{platform}/{slug}/ ---
add_filter( 'post_type_link', 'nswpedia_emulator_permalink', 10, 2 );
function nswpedia_emulator_permalink( $url, $post ) {
	if ( $post->post_type !== 'emulator' ) return $url;
	$platform_slug = sanitize_title( get_post_meta( $post->ID, '_emulator_platform', true ) );
	if ( ! $platform_slug ) $platform_slug = 'other';
	return home_url( '/emulators/' . $platform_slug . '/' . $post->post_name . '/' );
}

// --- Rewrite rules (most specific last so 'top' puts them first) ---
add_action( 'init', 'nswpedia_emulator_rewrites' );
function nswpedia_emulator_rewrites() {
	add_rewrite_rule( '^emulators/?$',                          'index.php?nsw_emulators_index=1',                              'top' );
	add_rewrite_rule( '^emulators/([^/]+)/?$',                  'index.php?nsw_emulator_platform=$matches[1]',                  'top' );
	add_rewrite_rule( '^emulators/([^/]+)/([^/]+)/?$',          'index.php?post_type=emulator&name=$matches[2]',                'top' );
}

add_filter( 'query_vars', 'nswpedia_emulator_query_vars' );
function nswpedia_emulator_query_vars( $vars ) {
	$vars[] = 'nsw_emulators_index';
	$vars[] = 'nsw_emulator_platform';
	return $vars;
}

// --- Template redirect ---
add_action( 'template_redirect', 'nswpedia_emulator_template_redirect', 2 );
function nswpedia_emulator_template_redirect() {
	if ( is_singular( 'emulator' ) ) {
		nswpedia_render_single_emulator( get_queried_object() );
		exit;
	}
	$platform_slug = get_query_var( 'nsw_emulator_platform' );
	if ( $platform_slug ) {
		nswpedia_render_emulator_platform( $platform_slug );
		exit;
	}
	if ( get_query_var( 'nsw_emulators_index' ) ) {
		nswpedia_render_emulators_index();
		exit;
	}
}

// --- /emulators/ index page ---
function nswpedia_render_emulators_index() {
	global $wp_query;
	$wp_query->is_404  = false;
	$wp_query->is_home = false;
	status_header( 200 );

	$posts = get_posts( array(
		'post_type'      => 'emulator',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );

	// Group by platform
	$platforms = array();
	foreach ( $posts as $p ) {
		$platform = get_post_meta( $p->ID, '_emulator_platform', true );
		if ( ! $platform ) $platform = 'Other';
		$platforms[ $platform ][] = $p;
	}
	ksort( $platforms );

	get_header();
	?>
	<div class="ast-container">
	<div style="padding:40px 20px;">

	<h1 style="font-size:2rem;font-weight:700;margin-bottom:8px;">Emulators</h1>
	<p style="color:#666;margin-bottom:32px;">Browse emulators by platform to play your favorite games.</p>

	<?php if ( empty( $platforms ) ) : ?>
		<p>No emulators found. Add emulators from the admin panel.</p>
	<?php else : ?>
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px;">
		<?php foreach ( $platforms as $platform_name => $platform_posts ) :
			$platform_slug = sanitize_title( $platform_name );
			$platform_url  = home_url( '/emulators/' . $platform_slug . '/' );
			$count         = count( $platform_posts );
			$first         = $platform_posts[0];
			$thumb         = get_the_post_thumbnail_url( $first->ID, 'medium' );
		?>
			<a href="<?php echo esc_url( $platform_url ); ?>" style="display:block;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);text-decoration:none;color:inherit;transition:transform .2s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 6px 24px rgba(0,0,0,.14)';" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,.08)';">
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $platform_name ); ?>" style="width:100%;height:130px;object-fit:cover;">
				<?php else : ?>
					<div style="width:100%;height:130px;background:linear-gradient(135deg,#e8100a,#ff6b35);display:flex;align-items:center;justify-content:center;">
						<span style="font-size:2.5rem;">🎮</span>
					</div>
				<?php endif; ?>
				<div style="padding:16px;">
					<div style="font-weight:700;font-size:1rem;margin-bottom:4px;"><?php echo esc_html( $platform_name ); ?></div>
					<div style="color:#888;font-size:.85rem;"><?php echo $count; ?> emulator<?php echo $count !== 1 ? 's' : ''; ?></div>
				</div>
			</a>
		<?php endforeach; ?>
		</div>
	<?php endif; ?>

	</div><!-- /padding -->
	</div><!-- /ast-container -->

	<?php
	// Schema markup
	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'CollectionPage',
		'name'        => 'Emulators — NSWPedia',
		'description' => 'Browse Nintendo Switch emulators by platform.',
		'url'         => home_url( '/emulators/' ),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';

	get_footer();
}

// --- /emulators/{platform}/ platform page ---
function nswpedia_render_emulator_platform( $platform_slug ) {
	global $wp_query;
	$wp_query->is_404  = false;
	$wp_query->is_home = false;
	status_header( 200 );

	$posts = get_posts( array(
		'post_type'      => 'emulator',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array( array(
			'key'     => '_emulator_platform',
			'value'   => $platform_slug,
			'compare' => 'LIKE',
		) ),
	) );

	// Try matching by sanitized title if no results
	if ( empty( $posts ) ) {
		$all_posts = get_posts( array( 'post_type' => 'emulator', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
		foreach ( $all_posts as $p ) {
			$pf = get_post_meta( $p->ID, '_emulator_platform', true );
			if ( sanitize_title( $pf ) === $platform_slug ) {
				$posts[] = $p;
			}
		}
	}

	$platform_label = $platform_slug;
	if ( ! empty( $posts ) ) {
		$platform_label = get_post_meta( $posts[0]->ID, '_emulator_platform', true );
	}

	get_header();
	?>
	<div class="ast-container">
	<div style="padding:40px 20px;">

	<div style="margin-bottom:16px;font-size:.9rem;"><a href="<?php echo esc_url( home_url( '/emulators/' ) ); ?>" style="color:#e8100a;text-decoration:none;">← All Emulators</a></div>
	<h1 style="font-size:2rem;font-weight:700;margin-bottom:8px;"><?php echo esc_html( $platform_label ); ?> Emulators</h1>
	<p style="color:#666;margin-bottom:32px;"><?php echo count( $posts ); ?> emulator<?php echo count( $posts ) !== 1 ? 's' : ''; ?> found for <?php echo esc_html( $platform_label ); ?>.</p>

	<?php if ( empty( $posts ) ) : ?>
		<p>No emulators found for this platform.</p>
	<?php else : ?>
		<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px;">
		<?php foreach ( $posts as $p ) :
			$thumb      = get_the_post_thumbnail_url( $p->ID, 'medium_large' );
			$link       = get_permalink( $p );
			$version    = get_post_meta( $p->ID, '_emulator_version', true );
			$developer  = get_post_meta( $p->ID, '_emulator_developer', true );
			$os_support = get_post_meta( $p->ID, '_emulator_os_support', true );
			if ( ! is_array( $os_support ) ) $os_support = array_filter( (array) $os_support );
		?>
			<a href="<?php echo esc_url( $link ); ?>" style="display:block;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);text-decoration:none;color:inherit;transition:transform .2s,box-shadow .2s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 6px 24px rgba(0,0,0,.14)';" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 12px rgba(0,0,0,.08)';">
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $p->post_title ); ?>" style="width:100%;height:160px;object-fit:cover;">
				<?php else : ?>
					<div style="width:100%;height:160px;background:linear-gradient(135deg,#1a1a2e,#16213e);display:flex;align-items:center;justify-content:center;font-size:3rem;">🎮</div>
				<?php endif; ?>
				<div style="padding:20px;">
					<h2 style="font-size:1.1rem;font-weight:700;margin:0 0 8px;"><?php echo esc_html( $p->post_title ); ?></h2>
					<?php if ( $developer ) : ?>
						<div style="color:#666;font-size:.85rem;margin-bottom:6px;">by <?php echo esc_html( $developer ); ?></div>
					<?php endif; ?>
					<?php if ( $version ) : ?>
						<div style="display:inline-block;background:#f0f0f0;border-radius:20px;padding:2px 10px;font-size:.8rem;color:#444;">v<?php echo esc_html( $version ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $os_support ) ) : ?>
						<div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:4px;">
						<?php foreach ( $os_support as $os ) : ?>
							<span style="background:#e8f4fd;color:#0066cc;border-radius:4px;padding:2px 8px;font-size:.75rem;"><?php echo esc_html( $os ); ?></span>
						<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</a>
		<?php endforeach; ?>
		</div>
	<?php endif; ?>

	</div><!-- /padding -->
	</div><!-- /ast-container -->

	<?php
	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'CollectionPage',
		'name'        => esc_html( $platform_label ) . ' Emulators — NSWPedia',
		'description' => 'Best ' . esc_html( $platform_label ) . ' emulators reviewed and listed.',
		'url'         => home_url( '/emulators/' . $platform_slug . '/' ),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';

	get_footer();
}

// --- Single emulator page ---
function nswpedia_render_single_emulator( $post ) {
	setup_postdata( $post );

	$title       = get_the_title( $post );
	$thumb       = get_the_post_thumbnail_url( $post->ID, 'large' );
	$version     = get_post_meta( $post->ID, '_emulator_version', true );
	$developer   = get_post_meta( $post->ID, '_emulator_developer', true );
	$website     = get_post_meta( $post->ID, '_emulator_website', true );
	$download    = get_post_meta( $post->ID, '_emulator_download_url', true );
	$github      = get_post_meta( $post->ID, '_emulator_github_url', true );
	$os_support  = get_post_meta( $post->ID, '_emulator_os_support', true );
	$compat      = get_post_meta( $post->ID, '_emulator_compatibility', true );
	$open_source = get_post_meta( $post->ID, '_emulator_open_source', true );
	$platform    = get_post_meta( $post->ID, '_emulator_platform', true );
	$platform_slug = sanitize_title( $platform );
	if ( ! is_array( $os_support ) ) $os_support = array_filter( (array) $os_support );

	get_header();
	?>
	<div class="ast-container">
	<div style="padding:40px 20px;">

	<div style="margin-bottom:16px;font-size:.9rem;">
		<a href="<?php echo esc_url( home_url( '/emulators/' ) ); ?>" style="color:#e8100a;text-decoration:none;">Emulators</a>
		<?php if ( $platform ) : ?>
			&nbsp;/&nbsp;<a href="<?php echo esc_url( home_url( '/emulators/' . $platform_slug . '/' ) ); ?>" style="color:#e8100a;text-decoration:none;"><?php echo esc_html( $platform ); ?></a>
		<?php endif; ?>
	</div>

	<div style="display:grid;grid-template-columns:1fr 2fr;gap:40px;align-items:start;">

		<!-- Left: image + download buttons -->
		<div>
			<?php if ( $thumb ) : ?>
				<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" style="width:100%;border-radius:14px;box-shadow:0 4px 20px rgba(0,0,0,.12);">
			<?php else : ?>
				<div style="width:100%;aspect-ratio:4/3;background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:4rem;">🎮</div>
			<?php endif; ?>

			<div style="margin-top:20px;display:flex;flex-direction:column;gap:10px;">
				<?php if ( $download ) : ?>
					<a href="<?php echo esc_url( $download ); ?>" target="_blank" rel="nofollow noopener" style="display:block;background:#e8100a;color:#fff;text-align:center;padding:12px 20px;border-radius:8px;font-weight:700;text-decoration:none;">⬇ Download</a>
				<?php endif; ?>
				<?php if ( $github ) : ?>
					<a href="<?php echo esc_url( $github ); ?>" target="_blank" rel="nofollow noopener" style="display:block;background:#24292e;color:#fff;text-align:center;padding:12px 20px;border-radius:8px;font-weight:700;text-decoration:none;">GitHub</a>
				<?php endif; ?>
				<?php if ( $website ) : ?>
					<a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="nofollow noopener" style="display:block;background:#f0f0f0;color:#333;text-align:center;padding:12px 20px;border-radius:8px;font-weight:700;text-decoration:none;">Official Website</a>
				<?php endif; ?>
			</div>
		</div>

		<!-- Right: details -->
		<div>
			<h1 style="font-size:2rem;font-weight:700;margin:0 0 8px;"><?php echo esc_html( $title ); ?></h1>

			<?php if ( $developer ) : ?>
				<div style="color:#666;margin-bottom:16px;">by <?php echo esc_html( $developer ); ?></div>
			<?php endif; ?>

			<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px;">
				<?php if ( $version ) : ?>
					<span style="background:#e8f4fd;color:#0066cc;border-radius:20px;padding:4px 14px;font-size:.85rem;font-weight:600;">v<?php echo esc_html( $version ); ?></span>
				<?php endif; ?>
				<?php if ( $open_source === 'yes' ) : ?>
					<span style="background:#e8fdf0;color:#007733;border-radius:20px;padding:4px 14px;font-size:.85rem;font-weight:600;">Open Source</span>
				<?php endif; ?>
				<?php if ( $compat ) : ?>
					<span style="background:#fff3e0;color:#e65100;border-radius:20px;padding:4px 14px;font-size:.85rem;font-weight:600;"><?php echo esc_html( $compat ); ?> Compatibility</span>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $os_support ) ) : ?>
				<div style="margin-bottom:24px;">
					<div style="font-weight:600;margin-bottom:8px;">Supported Platforms</div>
					<div style="display:flex;flex-wrap:wrap;gap:6px;">
					<?php foreach ( $os_support as $os ) : ?>
						<span style="background:#f5f5f5;border:1px solid #ddd;border-radius:6px;padding:4px 12px;font-size:.85rem;"><?php echo esc_html( $os ); ?></span>
					<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="entry-content" style="line-height:1.7;">
				<?php the_content(); ?>
			</div>
		</div>

	</div><!-- /grid -->

	</div><!-- /padding -->
	</div><!-- /ast-container -->

	<?php
	$schema = array(
		'@context'       => 'https://schema.org',
		'@type'          => 'SoftwareApplication',
		'name'           => $title,
		'applicationCategory' => 'GameApplication',
		'operatingSystem'     => implode( ', ', $os_support ),
		'url'            => get_permalink( $post->ID ),
	);
	if ( $developer ) $schema['author'] = array( '@type' => 'Organization', 'name' => $developer );
	if ( $version )   $schema['softwareVersion'] = $version;
	if ( $download )  $schema['downloadUrl'] = $download;
	echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';

	wp_reset_postdata();
	get_footer();
}

// --- Emulator meta boxes ---
add_action( 'add_meta_boxes', 'nswpedia_emulator_meta_boxes' );
function nswpedia_emulator_meta_boxes() {
	add_meta_box( 'nswpedia_emulator_details', 'Emulator Details', 'nswpedia_emulator_meta_box_html', 'emulator', 'normal', 'high' );
}

function nswpedia_emulator_meta_box_html( $post ) {
	wp_nonce_field( 'nswpedia_emulator_save', 'nswpedia_emulator_nonce' );
	$platform    = get_post_meta( $post->ID, '_emulator_platform', true );
	$version     = get_post_meta( $post->ID, '_emulator_version', true );
	$developer   = get_post_meta( $post->ID, '_emulator_developer', true );
	$website     = get_post_meta( $post->ID, '_emulator_website', true );
	$download    = get_post_meta( $post->ID, '_emulator_download_url', true );
	$github      = get_post_meta( $post->ID, '_emulator_github_url', true );
	$os_support  = get_post_meta( $post->ID, '_emulator_os_support', true );
	if ( ! is_array( $os_support ) ) $os_support = array();
	$compat      = get_post_meta( $post->ID, '_emulator_compatibility', true );
	$open_source = get_post_meta( $post->ID, '_emulator_open_source', true );
	$all_os      = array( 'Windows', 'macOS', 'Linux', 'Android', 'iOS' );
	$compat_opts = array( 'Excellent', 'Good', 'Fair', 'Poor' );
	?>
	<table class="form-table">
		<tr><th>Platform</th><td><input type="text" name="emulator_platform" value="<?php echo esc_attr( $platform ); ?>" style="width:100%;" placeholder="e.g. Nintendo Switch"></td></tr>
		<tr><th>Version</th><td><input type="text" name="emulator_version" value="<?php echo esc_attr( $version ); ?>" style="width:300px;"></td></tr>
		<tr><th>Developer</th><td><input type="text" name="emulator_developer" value="<?php echo esc_attr( $developer ); ?>" style="width:100%;"></td></tr>
		<tr><th>Official Website</th><td><input type="url" name="emulator_website" value="<?php echo esc_attr( $website ); ?>" style="width:100%;"></td></tr>
		<tr><th>Download URL</th><td><input type="url" name="emulator_download_url" value="<?php echo esc_attr( $download ); ?>" style="width:100%;"></td></tr>
		<tr><th>GitHub URL</th><td><input type="url" name="emulator_github_url" value="<?php echo esc_attr( $github ); ?>" style="width:100%;"></td></tr>
		<tr>
			<th>OS Support</th>
			<td><?php foreach ( $all_os as $os ) : ?>
				<label style="margin-right:14px;"><input type="checkbox" name="emulator_os_support[]" value="<?php echo esc_attr( $os ); ?>" <?php checked( in_array( $os, $os_support ) ); ?>> <?php echo esc_html( $os ); ?></label>
			<?php endforeach; ?></td>
		</tr>
		<tr>
			<th>Compatibility</th>
			<td><select name="emulator_compatibility">
				<option value="">— Select —</option>
				<?php foreach ( $compat_opts as $opt ) : ?>
					<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $compat, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
				<?php endforeach; ?>
			</select></td>
		</tr>
		<tr><th>Open Source?</th><td><label><input type="checkbox" name="emulator_open_source" value="yes" <?php checked( $open_source, 'yes' ); ?>> Yes, this emulator is open source</label></td></tr>
	</table>
	<?php
}

add_action( 'save_post_emulator', 'nswpedia_emulator_meta_save' );
function nswpedia_emulator_meta_save( $post_id ) {
	if ( ! isset( $_POST['nswpedia_emulator_nonce'] ) ) return;
	if ( ! wp_verify_nonce( $_POST['nswpedia_emulator_nonce'], 'nswpedia_emulator_save' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

	$fields = array(
		'_emulator_platform'     => sanitize_text_field( $_POST['emulator_platform'] ?? '' ),
		'_emulator_version'      => sanitize_text_field( $_POST['emulator_version'] ?? '' ),
		'_emulator_developer'    => sanitize_text_field( $_POST['emulator_developer'] ?? '' ),
		'_emulator_website'      => esc_url_raw( $_POST['emulator_website'] ?? '' ),
		'_emulator_download_url' => esc_url_raw( $_POST['emulator_download_url'] ?? '' ),
		'_emulator_github_url'   => esc_url_raw( $_POST['emulator_github_url'] ?? '' ),
		'_emulator_compatibility'=> sanitize_text_field( $_POST['emulator_compatibility'] ?? '' ),
		'_emulator_open_source'  => ( isset( $_POST['emulator_open_source'] ) && $_POST['emulator_open_source'] === 'yes' ) ? 'yes' : 'no',
	);
	foreach ( $fields as $key => $value ) {
		update_post_meta( $post_id, $key, $value );
	}
	$os = isset( $_POST['emulator_os_support'] ) ? array_map( 'sanitize_text_field', (array) $_POST['emulator_os_support'] ) : array();
	update_post_meta( $post_id, '_emulator_os_support', $os );
}
