<?php
/*
Plugin Name: Admin Bar Hopper 
Plugin URI: http://www.ninthlink.com/2011/02/25/wp-admin-bar-hopper-plugin/
Description: Adds another dropdown menu to the WP Admin Bar, with quick links to Hop To the various parts of your site. So while you are logged in, any Page / Category of your site can be just 1 quick hop away.
Author: Alex Chousmith
Version: 1.0
Author URI: http://www.ninthlink.com/
*/

add_action( 'wp_before_admin_bar_render', 'admin_bar_hopper_go' );
function admin_bar_hopper_go() {
	$opts = get_option( 'admin_bar_hopper_options' );
	
	global $wp_admin_bar;
	
	$wp_admin_bar->add_menu( array( 'id' => 'admin_bar_hopper', 'title' => __('Hop To...'), 'href' => admin_url( 'edit.php?post_type=page' ) ) );
	
	if ( $opts[abh_showpages] == 1 ) { // PAGES
		// get array of all pages
		$pages = get_pages( array( 'sort_column' => 'menu_order' ) );
		
		$numpages = count( $pages );
		$ongroup = 0;
		$ingroup = 0;
		$kiddupecheck = false;
		for ( $i=0; $i < $numpages; $i++ ) {
			// group (parent pages) by 10 at a time
			if ( ( $kiddupecheck == false ) && ( $ingroup%10 == 0 ) ) {
				$ingroup = 0;
				$ongroup++;
				$grouphandle = 'abh_g'. $ongroup;
				$wp_admin_bar->add_menu( array( 'parent' => 'admin_bar_hopper', 'id' => $grouphandle, 'title' => 'Pages '. (($ongroup-1)*10 +1) .' - '. ($ongroup*10), 'href' => admin_url( 'edit.php?post_type=page'. ( $ongroup>2 ? '&paged='. ( $ongroup - 1 ) : '' ) ) ) );
			}
			if ( $pages[$i]->post_parent ) {
				// add sub pages beneath the parent item
				$wp_admin_bar->add_menu( array( 'parent'=> 'abh_p'. $pages[$i]->post_parent, 'id' => 'abh_p'. $pages[$i]->ID, 'title' => esc_attr($pages[$i]->post_title), 'href' => get_permalink($pages[$i]->ID) ) );
				$kiddupecheck = true;
			} else {
			// add root-level pages to the initial page groups
				$wp_admin_bar->add_menu( array( 'parent' => $grouphandle, 'id' => 'abh_p'. $pages[$i]->ID, 'title' => esc_attr($pages[$i]->post_title), 'href' => get_permalink($pages[$i]->ID) ) );
				$ingroup++;
				$kiddupecheck = false;
			}
		}
	}
	
	// abh, now with more Hops
	if ( $opts[abh_showmenus] == 1 ) { // MENUS
		$wp_admin_bar->add_menu( array( 'parent' => 'admin_bar_hopper', 'id' => 'abh_menus', 'title' => __('Menus'), 'href' => admin_url( 'nav-menus.php' ) ) );
		$nav_menus = wp_get_nav_menus( array('orderby' => 'name') );
		foreach ( $nav_menus as $men ) {
			$grouphandle = 'abh_m'. $men->term_id;
			$wp_admin_bar->add_menu( array( 'parent' => 'abh_menus', 'id' => $grouphandle , 'title' => esc_attr($men->name), 'href' => admin_url( 'nav-menus.php?action=edit&menu='.$men->term_id ) ) );
			$items = wp_get_nav_menu_items( $men->term_id, array('post_status' => 'any') );
			foreach($items as $i) {
				$abh_args = array(
					'parent' => 'abh_m'. $i->menu_item_parent,
					'id' => 'abh_m'. $i->ID,
					'title' => esc_attr( $i->title ),
					'href' => esc_url( $i->url )
				);
				if ( $i->menu_item_parent == 0 ) {
					$abh_args['parent'] = $grouphandle;
				}
				if ( $i->target == '_blank' ) {
					$abh_args[meta] = array( 'target' => $i->target );
				}
				$wp_admin_bar->add_menu( $abh_args );
			}
		}
	}
	if ( $opts[abh_showcats] == 1 ) { // CATEGORIES
		$wp_admin_bar->add_menu( array( 'parent' => 'admin_bar_hopper', 'id' => 'abh_cats', 'title' => __('Categories'), 'href' => admin_url( 'edit-tags.php?taxonomy=category' ) ) );
		$cats = get_terms( 'category' ); // yay cats
		foreach ( $cats as $cat ) {
			$wp_admin_bar->add_menu( array( 'parent' => 'abh_cats', 'id' => 'abh_c'. $cat->term_id, 'title' => esc_attr($cat->name), 'href' => trailingslashit(get_bloginfo('url')).'category/'. $cat->slug .'/' ) );
		}
	}
	
	if ( ( $opts[abh_showpages] != 1 ) && ( $opts[abh_showmenus] != 1 ) && ( $opts[abh_showcats] != 1 ) ) {
		$wp_admin_bar->add_menu( array( 'parent' => 'admin_bar_hopper', 'id' => 'abh_why', 'title' => __('Admin Bar Hopper is installed, but all checkboxes are unchecked, all possible listings have been skipped. Why not check at least one?'), 'href' => admin_url( 'options-general.php?page=admin_bar_hopper' ) ) );
	}
}

register_activation_hook( __FILE__, 'admin_bar_hopper_activation' );
function admin_bar_hopper_activation() {
	$opts = get_option( 'admin_bar_hopper_options' );
	$cbs = array(
		'abh_showpages' => 1,
		'abh_showmenus' => 1,
		'abh_showcats' => 1
	);
	if ( $opts == false ) {
		$opts = $cbs;
	} else {
		foreach ( $cbs as $k => $v ) {
			if ( !isset( $opts[$k] ) ) {
				$opts[$k] = 1;
			}
		}
	}
	update_option( 'admin_bar_hopper_options', $opts );
}

add_action( 'admin_init', 'admin_bar_hopper_init' );
function admin_bar_hopper_init() {
	register_setting( 'admin_bar_hopper_options', 'admin_bar_hopper_options', 'admin_bar_hopper_validate' );
	
	add_settings_section( 'admin_bar_hopper_checks', 'List the following areas...', 'admin_bar_hopper_nothin', 'admin_bar_hopper_settings' );
	add_settings_field( 'abh_showpages', 'Show Pages', 'admin_bar_hopper_checkbox', 'admin_bar_hopper_settings', 'admin_bar_hopper_checks', 'abh_showpages' );
	add_settings_field( 'abh_showmenus', 'Show Menus', 'admin_bar_hopper_checkbox', 'admin_bar_hopper_settings', 'admin_bar_hopper_checks', 'abh_showmenus' );
	add_settings_field( 'abh_showcats', 'Show Categories', 'admin_bar_hopper_checkbox', 'admin_bar_hopper_settings', 'admin_bar_hopper_checks', 'abh_showcats' );
}

add_action( 'admin_menu', 'admin_bar_hopper_init_menu' );
function admin_bar_hopper_init_menu() {
	add_options_page( 'Admin Bar Hopper Options', 'Admin Bar Hopper', 'manage_options', 'admin_bar_hopper', 'admin_bar_hopper_settings_page' );
}

function admin_bar_hopper_nothin() {
	// just need some callback for the add_settings_section...
}

function admin_bar_hopper_settings_page() {
?>
	<div class="wrap">
		<h2>Admin Bar Hopper Options</h2>
        <em>Created by <a href="http://www.ninthlink.com/author/alex/" target="_blank">Alex Chousmith</a> of <a href="http://www.ninthlink.com/" target="_blank" title="High Performance Websites">Ninthlink, Inc.</a></em>
		<form action="options.php" method="post" enctype="multipart/form-data"><?php
		settings_fields( 'admin_bar_hopper_options' );
		do_settings_sections( 'admin_bar_hopper_settings' );
		?><p class="submit"><input type="submit" name="updateoption" value="Update &raquo;" /></p>
		</form>
	</div>
<?php
}

function admin_bar_hopper_checkbox( $arg ) {
	$opts = get_option( 'admin_bar_hopper_options' );
	$chk = '';
	if ( isset( $opts[$arg] ) ) {
		if ( $opts[$arg] == 1 ) {
			$chk = ' checked="checked"';
		}
	}
	echo '<input id="'. esc_attr( $arg ) .'" name="admin_bar_hopper_options['. esc_attr( $arg ) .']" type="checkbox" value="1"'. $chk .' />';
}

function admin_bar_hopper_validate( $input ) {
	// do validation here...
	$cbs = array( 'abh_showpages', 'abh_showmenus', 'abh_showcats' );
	$oot = array();
	foreach ( $cbs as $cb ) {
		if ( isset( $input[$cb] ) ) {
			$oot[$cb] = $input[$cb];
		} else {
			$oot[$cb] = 0;
		}
	}
	return $oot;
}

function admin_bar_hopper_action_links( $links, $file ) {
	if ( $file == plugin_basename( dirname(__FILE__) .'/admin-bar-hopper.php' ) ) {
		$links[] = '<a href="options-general.php?page=admin_bar_hopper">'.__('Settings').'</a>';
	}

	return $links;
}
add_filter( 'plugin_action_links', 'admin_bar_hopper_action_links', 10, 2 );