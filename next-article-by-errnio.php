<?php
/*
Plugin Name: Next Article by errnio
Plugin URI: http://errnio.com
Description: Next Article by errnio moves the reader to the next available article on your site, when swiping right on mobile. 
Version: 1.4
Author: Errnio
Author URI: http://errnio.com
*/

/***** Constants ******/

define('NEXT_ARTICLE_BY_ERRNIO_VERSION', '1.4');
define('NEXT_ARTICLE_BY_ERRNIO_INSTALLER_NAME', 'wordpress_nextarticle_by_errnio');

define('NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGID', 'errnio_id');
define('NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGTYPE', 'errnio_id_type');

define('NEXT_ARTICLE_BY_ERRNIO_EVENT_NAME_ACTIVATE', 'wordpress_activated');
define('NEXT_ARTICLE_BY_ERRNIO_EVENT_NAME_DEACTIVATE', 'wordpress_deactivated');
define('NEXT_ARTICLE_BY_ERRNIO_EVENT_NAME_UNINSTALL', 'wordpress_uninstalled');

define('NEXT_ARTICLE_BY_ERRNIO_TAGTYPE_TEMP', 'temporary');
define('NEXT_ARTICLE_BY_ERRNIO_TAGTYPE_PERM', 'permanent');

define('NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_NOTIFICATION', 'errnio_notification_status');

/***** Utils ******/

function nextarticle_by_errnio_do_wp_post_request($url, $data) {
	$data = json_encode( $data );
	$header = array('Content-type' => 'application/json');
	$response = wp_remote_post($url, array(
	    'headers' => $header,
	    'body' => $data
	));

	return json_decode(wp_remote_retrieve_body($response));
}

function nextarticle_by_errnio_send_event($eventType) {
	$tagId = get_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGID);
	if ($tagId) {
		$urlpre = 'http://customer.errnio.com';
	 	$createTagUrl = $urlpre.'/sendEvent';

	 	$params = array('tagId' => $tagId, 'eventName' => $eventType);
	 	$response = nextarticle_by_errnio_do_wp_post_request($createTagUrl, $params);
	}
	// No tagId - no point sending an event
}

function nextarticle_by_errnio_create_tagid() {
	$urlpre = 'http://customer.errnio.com';
 	$createTagUrl = $urlpre.'/createTag';
 	$params = array('installerName' => NEXT_ARTICLE_BY_ERRNIO_INSTALLER_NAME);
 	$response = nextarticle_by_errnio_do_wp_post_request($createTagUrl, $params);

	if ($response && $response->success) {
		$tagId = $response->tagId;
		add_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGID, $tagId);
	 	add_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGTYPE, NEXT_ARTICLE_BY_ERRNIO_TAGTYPE_TEMP);
		return $tagId;
	}
	
	return NULL;
}

function nextarticle_by_errnio_check_need_register() {
	$tagtype = get_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGTYPE);
	$needregister = true;
	
	if ($tagtype == NEXT_ARTICLE_BY_ERRNIO_TAGTYPE_PERM) {
		$needregister = false;
	}
	
	return $needregister;
}

/***** Activation / Deactivation / Uninstall hooks ******/

function nextarticle_by_errnio_activate() {
	if ( ! current_user_can( 'activate_plugins' ) )
	        return;
	
	$tagId = get_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGID);

	if ( $tagId === FALSE || empty($tagId) ) {
		// First time activation
		$tagId = nextarticle_by_errnio_create_tagid();
	} else {
		// Previously activated - meaning tagType + tagId should exists
	}
	
	// Send event - activated
	nextarticle_by_errnio_send_event(NEXT_ARTICLE_BY_ERRNIO_EVENT_NAME_ACTIVATE);
}

function nextarticle_by_errnio_deactivate() {
	if ( ! current_user_can( 'activate_plugins' ) )
	        return;
	
	// Send event - deactivated
	nextarticle_by_errnio_send_event(NEXT_ARTICLE_BY_ERRNIO_EVENT_NAME_DEACTIVATE);

	delete_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_NOTIFICATION);
}

function nextarticle_by_errnio_uninstall() {
	if ( ! current_user_can( 'activate_plugins' ) )
	        return;

	$plugins = nextarticle_by_errnio_multiple_plugins();

	if (count($plugins['all_active']) == 0) {
        // Send event - uninstall
        nextarticle_by_errnio_send_event(NEXT_ARTICLE_BY_ERRNIO_EVENT_NAME_UNINSTALL);

        delete_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGID);
        delete_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGTYPE);
	}
}

register_activation_hook( __FILE__, 'nextarticle_by_errnio_activate' );
register_deactivation_hook( __FILE__, 'nextarticle_by_errnio_deactivate' );
register_uninstall_hook( __FILE__, 'nextarticle_by_errnio_uninstall' );

/***** Client side script load ******/

function nextarticle_by_errnio_get_first_post_image( $postID ) {
    $args = array(
    'numberposts' => 1,
    'order' => 'ASC',
    'post_mime_type' => 'image',
    'post_parent' => $postID,
    'post_status' => null,
    'post_type' => 'attachment',
    );

    $attachments = get_children( $args );
    if ( $attachments ) {
        foreach ( $attachments as $attachment ) {
            $image_attributes = wp_get_attachment_image_src( $attachment->ID, 'thumbnail' )?
                wp_get_attachment_image_src( $attachment->ID, 'thumbnail' ) : wp_get_attachment_image_src( $attachment->ID, 'full' );
            if ($image_attributes) {
                return $image_attributes[0];
            } else {
                return null;
            }
        }
    }
}

function nextarticle_by_errnio_load_client_script() {
	$list = 'enqueued';
	$handle = 'errnio_script';

	// Script already running on this page
	if (wp_script_is($handle, $list)) {
		return;
	}

	$tagId = get_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGID);

	if (!$tagId || empty($tagId)) {
		return;
	}

    $script_url = "//service.errnio.com/loader?tagid=".$tagId;
    wp_register_script($handle, $script_url, false, NEXT_ARTICLE_BY_ERRNIO_VERSION, true);
    wp_enqueue_script($handle );

    $prev_post = get_adjacent_post( false, '', true);
    $next_post = get_adjacent_post( false, '', false);
    $posts_obj = array();

    if ( !empty($next_post) ) {
        $next_post_obj = array();
        $next_post_obj['title'] = $next_post->post_title;
        $next_post_obj['clickUrl'] = get_permalink( $next_post->ID );
        $next_post_thumb = nextarticle_by_errnio_get_first_post_image($next_post->ID);
        if ($next_post_thumb) {
            $next_post_obj['thumbnailUrl'] = $next_post_thumb;
        }
        $posts_obj['nextPage'] = $next_post_obj;
    }
    if ( !empty($prev_post) ) {
        $prev_post_obj = array();
        $prev_post_obj['title'] = $prev_post->post_title;
        $prev_post_obj['clickUrl'] = get_permalink( $prev_post->ID );

        $prev_post_thumb = nextarticle_by_errnio_get_first_post_image($prev_post->ID);
        if ($prev_post_thumb) {
            $prev_post_obj['thumbnailUrl'] = $prev_post_thumb;
        }
        $posts_obj['previousPage'] = $prev_post_obj;
    }

    wp_localize_script($handle, '_errniowp', $posts_obj);
}


function nextarticle_by_errnio_load_client_script_add_async_attr( $url ) {
	if(FALSE === strpos( $url, 'service.errnio.com')){
		return $url;
	}

	return "$url' async='async";
}

add_filter('clean_url', 'nextarticle_by_errnio_load_client_script_add_async_attr', 11, 1);
add_action('wp_enqueue_scripts', 'nextarticle_by_errnio_load_client_script', 99999 );

/***** Admin ******/

function nextarticle_by_errnio_add_settings_menu_option() {
    add_menu_page (
        'Errnio Options',   //page title
        'Next Post Settings',  //menu title
        'manage_options',   //capability
        'errnio-options-nextarticle',   //menu_slug
        'nextarticle_by_errnio_admin_page',  //function
        plugin_dir_url( __FILE__ ) . '/assets/img/errnio-icon.png'  //icon_url
        //There is another parameter - position
    );
}

function nextarticle_by_errnio_add_settings_link_on_plugin($links, $file) {
    static $this_plugin;

    if (!$this_plugin) {
        $this_plugin = plugin_basename(__FILE__);
    }

    if ($file == $this_plugin) {
		$adminpage_url = admin_url( 'admin.php?page=errnio-options-nextarticle' );
        $settings_link = '<a href="'.$adminpage_url.'">Settings</a>';
        array_unshift($links, $settings_link);
    }

    return $links;
}

function nextarticle_by_errnio_admin_notice() {
	global $hook_suffix;
	$needregister = nextarticle_by_errnio_check_need_register();
	$settingsurl = admin_url( 'admin.php?page=errnio-options-nextarticle' );

	$shouldshow = get_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_NOTIFICATION);

	if($hook_suffix == 'plugins.php' && $needregister && $shouldshow != 'hide'){
		$plugin_name = "Next Post";
		$message_id = "mobile-next-post-errnio";
		$class = "activated errnio-notice notice is-dismissible";
		$message = "Congratulations! Your ".$plugin_name." plugin is up and running. For more options and features you're welcome to register <a href='".$settingsurl."' style='color: #ECFFFD;text-decoration:underline;'>here</a>";
	    echo "<div id=\"$message_id\" class=\"$class\" style='border-radius: 3px;border-color: #4DB6AC;background-color: #81D8D0;box-shadow: 0 1px 1px 0 rgba(0,0,0,.2);-webkit-box-shadow: 0 1px 1px 0 rgba(0,0,0,.2);'> <p style='color:#555;font-weight:bold;font-size:110%;'>$message</p></div>";

		$jshandle = 'errnio-admin-js';
		wp_register_script($jshandle, plugins_url('assets/js/errnio-admin.js', __FILE__), array('jquery'));
		wp_enqueue_script($jshandle);
		wp_localize_script($jshandle, 'errniowp', array('ajax_url' => admin_url( 'admin-ajax.php' )));
	}
}

function nextarticle_by_errnio_close_notification() {
	add_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_NOTIFICATION, 'hide');
}

function nextarticle_by_errnio_multiple_plugins() {
	$url = 'http://customer.errnio.com/wordpressplugins';
	$response = wp_remote_get( esc_url_raw( $url ) );
	$plugins_array = json_decode( wp_remote_retrieve_body( $response ), true );

	$active_errnios = array();
	$this_plugin = plugin_basename(__FILE__);

	if ($plugins_array) {
		foreach($plugins_array as $pluginobj) {
			if ( $this_plugin != $pluginobj['plugin'] && is_plugin_active($pluginobj['plugin'])) {
				array_push($active_errnios, $pluginobj['name']);
			} else if ($this_plugin == $pluginobj['plugin']) {
				$this_plugin = $pluginobj['name'];
			}
		}
	}

	return array('current' => $this_plugin, 'all_active' => $active_errnios);
}

function nextarticle_by_errnio_admin_page() {
	$stylehandle = 'errnio-style';
	$jshandle = 'errnio-js';
	wp_register_style('googleFonts', 'https://fonts.googleapis.com/css?family=Roboto:100,300,400');
	wp_enqueue_style('googleFonts');
	wp_register_style($stylehandle, plugins_url('assets/css/errnio.css', __FILE__));
	wp_enqueue_style($stylehandle);
	wp_register_script($jshandle, plugins_url('assets/js/errnio.js', __FILE__), array('jquery'));
	wp_enqueue_script($jshandle);
	wp_localize_script($jshandle, 'errniowp', array('ajax_url' => admin_url( 'admin-ajax.php' )));
    ?>
    <div class="errnio-settings-wrap">
		<?php
		$needregister = nextarticle_by_errnio_check_need_register();
		$tagId = get_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGID);

		if (!$needregister) {
			$plugins = nextarticle_by_errnio_multiple_plugins();
			if (count($plugins['all_active']) > 0) {
				echo '<div class="errnio-settings-screen"><div class="errnio-settings-header2"><div class="container-fluid no-padding max-size"><div class="col-sm-4 col-md-4 col-lg-4"><h1>Welcome</h1></div></div></div><div class="errnio-settings-middle"><div class="container-fluid no-padding max-size"><div class="col-sm-12 col-md-12 col-lg-12"><div class="errnio-info"><br/><p>The <i><b>'.$plugins['current'].'</i></b> plugin is from the same home as <i><b>'.implode(', ', $plugins['all_active']).'</i></b>.<br/><br/>You have already registered with us and you are able to <b>manage the settings of all plugins</b> on your personal dashboard <a href="http://brutus.errnio.com/">here</a>.</p></div></div></div></div><div class="errnio-settings-footer"><p>Having trouble? contact us: <a href="mailto:support@errnio.com">support@errnio.com</a></p><a class="errnio-settings-logo" href="http://errnio.com" target="_blank"></a></div></div>';
			} else {
				echo '<div class="errnio-settings-screen"><div class="errnio-settings-header2"><div class="container-fluid no-padding max-size"><div class="col-sm-4 col-md-4 col-lg-4"><h1>Welcome</h1></div></div></div><div class="errnio-settings-middle"><div class="container-fluid no-padding max-size"><div class="col-sm-12 col-md-12 col-lg-12"><div class="errnio-info"><br/><p>Your errnio plugin is up and running.<br/>For configuration and reports please visit your dashboard at <a href="http://brutus.errnio.com/">brutus.errnio.com</a></p></div></div></div></div><div class="errnio-settings-footer"><p>Having trouble? contact us: <a href="mailto:support@errnio.com">support@errnio.com</a></p><a class="errnio-settings-logo" href="http://errnio.com" target="_blank"></a></div></div>';
			}
		} else {
			if ($tagId) {
				echo '<div id="errnioSettingsAdmin" class="errnio-settings-screen" data-tagId="'.$tagId.'" data-installName="'.NEXT_ARTICLE_BY_ERRNIO_INSTALLER_NAME.'">';
				include 'assets/includes/errnio-admin.php';
				echo '</div>';
			} else {
				echo '<p>There was an error :( Contact <a href="mailto:support@errnio.com">support@errnio.com</a> for help.</p>';
			}
		};

		?>
    </div>
    <?php
}

function nextarticle_by_errnio_register_callback() {
	$type = $_POST['type'];
	$tagId = $_POST['tag_id'];

	if ($type == 'switchTag') {
		update_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGID, $tagId);
	}

	update_option(NEXT_ARTICLE_BY_ERRNIO_OPTION_NAME_TAGTYPE, NEXT_ARTICLE_BY_ERRNIO_TAGTYPE_PERM);

	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action('admin_menu', 'nextarticle_by_errnio_add_settings_menu_option');
add_filter('plugin_action_links', 'nextarticle_by_errnio_add_settings_link_on_plugin', 10, 2);
add_action('admin_notices', 'nextarticle_by_errnio_admin_notice');
add_action('wp_ajax_nextarticle_by_errnio_register', 'nextarticle_by_errnio_register_callback');

add_action('wp_ajax_nextarticle_by_errnio_close_notification', 'nextarticle_by_errnio_close_notification');