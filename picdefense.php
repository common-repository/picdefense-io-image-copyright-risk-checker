<?php
/*
Plugin Name: PicDefense.io - Your Guard Against Image Copyright Infringement
Plugin URI: https://picdefense.io
Description: Check your website for high-risk copyrighted images and replace them with copyright-safe alternatives, reducing the risk of copyright infringement.
Version: 1.1.3
Author: PicDefense.io
Author URI: https://picdefense.io/
Text Domain: picdefense-io
License: GPLv2 or later
*/

class PicdefenseIO {

	private $picdio_plugin_path;
	private $picdio_plugin_url;
	protected $picdio_API_URL;

	public function __construct() {

		$this->picdio_plugin_path = plugin_dir_path( __FILE__ );
		$this->picdio_plugin_url  = plugin_dir_url( __FILE__ );
		$this->picdio_API_URL  = 'https://app.picdefense.io/api/v1';

		register_activation_hook( __FILE__, array( &$this, 'picdio_activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'picdio_deactivate' ) );

		load_plugin_textdomain( 'picdefense-io' );

		add_action( 'init', array( &$this, 'picdio_init' ), 1 );
		add_action( 'rest_api_init', array( &$this, 'picdio_register_api_hooks' ) );
		add_action( 'admin_notices', array( &$this, 'picdio_cache_clear_message' ) );
		add_action( 'picdio_scheduled_images_scan', array( &$this, 'picdio_get_picd_images_count' ) );
		add_action( 'picdio_scheduled_images_job_submit', array( &$this, 'picdio_scheduled_images_job' ) );
		add_action( 'wp_ajax_picdefense_dismiss_notice', array( &$this, 'picdefense_dismiss_notice' ) );
		add_action( 'wp_ajax_picdefense_scheduled_event_verify', array( &$this, 'picdefense_scheduled_event_verify' ) );
		add_filter( 'intermediate_image_sizes_advanced', array( &$this, 'picdio_all_upload_sizes' ), 10, 3);
	}
	
	public function picdio_all_upload_sizes($sizes, $image_meta, $attachmentID){
		$old_attachment_id = get_option('old_attachment_id_to_delete');
		if($old_attachment_id > 0) {
			$meta = wp_get_attachment_metadata( $old_attachment_id );
			$new_sizes = array();
			$new_sizes['original']['width'] = $meta['width'];
			$new_sizes['original']['height'] = $meta['height'];
			$new_sizes['original']['crop'] = 1;
			foreach($meta['sizes'] as $key => $val){
				$new_sizes[$key]['width'] = $val['width'];
				$new_sizes[$key]['height'] = $val['height'];
				$new_sizes[$key]['crop'] = 1;
			}
			$sizes = $new_sizes;
			delete_option('old_attachment_id_to_delete');
		}
		return $sizes;
	}
	
	public function picdio_register_api_hooks(){
		register_rest_route(
			'auth-api', '/picdio-image-replace/',
			array(
			  'methods'  => 'POST',
			  'callback' => array(&$this, 'picdio_image_replace_callback'),
			  'permission_callback' => array( &$this, 'picdio_check_access_callback' )
			)
		  );
	}
	
	public function picdio_check_access_callback($request){
		$getheader = $request->get_header('authorization');	
		if ( strpos( $getheader, 'Basic' ) !== false ) {
			$headerArr = explode('Basic', $getheader);
			if(isset($headerArr[1]) && !empty($headerArr[1])){
				$keys = explode(':', trim($headerArr[1]));
				$uid = trim($keys[0]);
				$api_key = trim($keys[1]);
				if($uid == get_option('picdefense_user_ID') && $api_key == get_option('picdefense_api_key')){
					return true;
				}
			}
		}
		return false;
	}
	
	public function picdio_image_replace_callback($request){
		if(!$this->picdio_check_access_callback($request)){
			return new WP_REST_Response( array(	'status' => 'error', 'message' => 'Not able to upload', 'data' => array() ));
		}	
		// Get sent data and set default value
		$params = wp_parse_args( $request->get_params(), [
		  'url' => '',
		  'alt' => '',
		  'title' => '',
		  'caption' => '',
		  'description' => '',
		  'attachment_id' => '',
		] );

		$new_attachment_id = $this->picdio_upload_file_by_url($params);

		if($new_attachment_id <= 0){
			return new WP_REST_Response( array(	'status' => 'error', 'message' => 'Not able to upload', 'data' => array() ));
		}
		$old_attachment_id = $params['attachment_id'];
		
		$oldmeta = wp_get_attachment_metadata( $old_attachment_id );
		
		$uploadDir = wp_upload_dir();
		$newFile = $uploadDir['basedir'].'/'.get_post_meta($new_attachment_id, '_wp_attached_file', true);
		// Make sure the new file exists before proceeding
		if (!is_file($newFile)) {
			return new WP_REST_Response( array(	'status' => 'error', 'message' => 'Not able to upload', 'data' => array() ));
		}
		$newfiledata = pathinfo($newFile);
		$filedata = pathinfo($params['url']);
		$originalFile = $newfiledata['dirname'].'/'.str_replace('scaled', '', $newfiledata['filename']).$oldmeta['width'].'x'.$oldmeta['height'].'.'.$filedata['extension'];
		wp_delete_file( $newFile );
		rename($originalFile,$newFile);

		// Delete the old attachment's files
		$this->picdio_old_image_delete_attachment($old_attachment_id);
		$oldFile = $uploadDir['basedir'].'/'.get_post_meta($old_attachment_id, '_wp_attached_file', true);
		$this->copy_new_to_old($oldFile, $newFile, $oldmeta, $new_attachment_id);
		
		$meta = wp_generate_attachment_metadata($old_attachment_id, $oldFile);
		wp_update_attachment_metadata($old_attachment_id, $meta);
		wp_delete_attachment($new_attachment_id, true);
		update_option( 'picdio_cache_clear_message_dismissed', 0 );
		return new WP_REST_Response( array( 'status' => 'success', 'message' => 'Replaced successfully', 'data' => array('file' => $newFile) ));
	}
	
	public function copy_new_to_old($oldFile, $newFile, $oldmeta, $new_attachment_id){
		$newmeta = wp_get_attachment_metadata( $new_attachment_id );
		if (!file_exists(dirname($oldFile)))
			wp_mkdir_p(dirname($oldFile), 0777, true);
		copy($newFile, $oldFile);
		
		$newfiledata = pathinfo($newFile);
		$oldfiledata = pathinfo($oldFile);
		foreach($oldmeta['sizes'] as $key=>$val){
			copy($newfiledata['dirname'].'/'.$newmeta['sizes'][$key]['file'], $oldfiledata['dirname'].'/'.$val['file']);
		}
	}
	
	public function picdio_old_image_delete_attachment($post_id){
		$allFiles = array();
		$uploadpath = wp_get_upload_dir();

		$meta = wp_get_attachment_metadata( $post_id );
		$backup_sizes = get_post_meta( $post_id, '_wp_attachment_backup_sizes', true );
		$file = get_attached_file( $post_id );
		
		$filedata = pathinfo($meta['file']);
		$allFiles = $this->findFiles($uploadpath['basedir'].'/'.$filedata['dirname'].'/'.$filedata['filename'], array("apng", "avif", "gif", "jpg", "jpeg", "jfif", "pjpeg", "pjp", "png", "svg", "webp", "bmp", "ico", "cur", "tif", "tiff"));

		if ( is_multisite() )
			delete_transient( 'dirsize_cache' );

		if ( ! empty($meta['thumb']) ) {
			$thumbfile = str_replace(basename($file), $meta['thumb'], $file);
			$thumbfile = apply_filters( 'wp_delete_file', $thumbfile );
			$thumbdata = $thumbFiles = array();
			$thumbdata = pathinfo($thumbfile);
			$thumbFiles = $this->findFiles($uploadpath['basedir'].'/'.$filedata['dirname'].'/'.$thumbdata['filename'], array("apng", "avif", "gif", "jpg", "jpeg", "jfif", "pjpeg", "pjp", "png", "svg", "webp", "bmp", "ico", "cur", "tif", "tiff"));
			$allFiles = array_merge($allFiles, $thumbFiles); 
		}
		
		// Remove intermediate and backup images if there are any.
		if ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size => $sizeinfo ) {
				$intermediate_file = str_replace( basename( $file ), $sizeinfo['file'], $file );
				$intermediate_file = apply_filters( 'wp_delete_file', $intermediate_file );
				$sizedata = $sizeFiles = array();
				$sizedata = pathinfo($intermediate_file);
				$sizeFiles = $this->findFiles($uploadpath['basedir'].'/'.$filedata['dirname'].'/'.$sizedata['filename'], array("apng", "avif", "gif", "jpg", "jpeg", "jfif", "pjpeg", "pjp", "png", "svg", "webp", "bmp", "ico", "cur", "tif", "tiff"));
				$allFiles = array_merge($allFiles, $sizeFiles);
			}
		}

		if ( is_array($backup_sizes) ) {
			foreach ( $backup_sizes as $size ) {
				$del_file = path_join( dirname($meta['file']), $size['file'] );
				$del_file = apply_filters( 'wp_delete_file', $del_file );
				$backupdata = $backupFiles = array();
				$backupdata = pathinfo($del_file);
				$backupFiles = $this->findFiles($uploadpath['basedir'].'/'.$filedata['dirname'].'/'.$backupdata['filename'], array("apng", "avif", "gif", "jpg", "jpeg", "jfif", "pjpeg", "pjp", "png", "svg", "webp", "bmp", "ico", "cur", "tif", "tiff"));
				$allFiles = array_merge($allFiles, $backupFiles);
			}
		}
		
		foreach ( $allFiles as $dfile ) {
			@ unlink( path_join($uploadpath['basedir'], $dfile) );
		}
		wp_delete_file( $file );
	}
	
	public function picdio_upload_file_by_url( $params ) {
		update_option( 'old_attachment_id_to_delete', $params['attachment_id'] );
		$image_url = $params['url'];
		// it allows us to use download_url() and wp_handle_sideload() functions
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		// download to temp dir
		$temp_file = download_url( $image_url );

		if( is_wp_error( $temp_file ) ) {
			return new WP_REST_Response( array(	'status' => 'error', 'message' => 'Not able to upload', 'data' => array() ));
		}

		// move the temp file into the uploads directory
		$file = array(
			'name'     => basename( strtok($image_url, '?') ),
			'type'     => mime_content_type( $temp_file ),
			'tmp_name' => $temp_file,
			'size'     => filesize( $temp_file ),
		);
		$sideload = wp_handle_sideload(
			$file,
			array(
				'test_form'   => false // no needs to check 'action' parameter
			)
		);

		if( ! empty( $sideload[ 'error' ] ) ) {
			// you may return error message if you want
			return new WP_REST_Response( array(	'status' => 'error', 'message' => 'Not able to upload', 'data' => array() ));
		}

		// it is time to add our uploaded image into WordPress media library
		$attachment_id = wp_insert_attachment(
			array(
				'guid'           => $sideload[ 'url' ],
				'post_mime_type' => $sideload[ 'type' ],
				'post_title'     => basename( $sideload[ 'file' ] ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$sideload[ 'file' ]
		);
		
		if( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_REST_Response( array(	'status' => 'error', 'message' => 'Not able to upload', 'data' => array() ));
		}

		// update medatata, regenerate image sizes
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $sideload[ 'file' ] );

		wp_update_attachment_metadata(
			$attachment_id,
			$attach_data
		);

		return $attachment_id;
	}

	public function picdio_activate( $network_wide ) {
		$response = $this->picdio_wpversion_check();
		if($response['error'] == 1){
			wp_die( $response['message'] );
		}

		if ( !wp_next_scheduled( 'picdio_scheduled_images_scan' ) ) {
			update_option( 'picdefense_inc_all_imgs', 1 );
			update_option( 'picdefense_rescan_images', 0 );
			$this->picdio_schedule_job_scan_setup();
		}
	}

	public function picdio_deactivate( $network_wide ) {
		if ( false !== wp_get_scheduled_event( 'picdio_scheduled_images_scan' ) ) {
			wp_clear_scheduled_hook( 'picdio_scheduled_images_scan' );
		}
		if ( false !== wp_get_scheduled_event( 'picdio_scheduled_images_job_submit' ) ) {
			wp_clear_scheduled_hook( 'picdio_scheduled_images_job_submit' );
		}
		
		delete_option('picdefense_api_key');
		delete_option('picdefense_user_ID');
		delete_option('picdefense_test_connect');
		delete_option('picdefense_inc_all_imgs');
		delete_option('picdio_cache_clear_message_dismissed');
		delete_option('picdefense_images_count');
		delete_option('picdefense_submit_domain');
		delete_option('picdefense_plugin_version');
	}
	
	public function picdio_schedule_job_scan_setup(){
		wp_schedule_single_event( time() + 10, 'picdio_scheduled_images_scan' );
	}
	
	public function picdio_schedule_job_submit_setup(){
		wp_schedule_single_event( time() + 10, 'picdio_scheduled_images_job_submit' );
	}

	public function picdio_init() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( &$this, 'picdio_admin_menu' ), 70 );
			add_action( 'admin_enqueue_scripts', array( &$this, 'picdio_admin_js' ));
		}
	}
	
	public function picdio_admin_menu() {
		add_menu_page(__('PicDefense.io','picdefense-io-setting'), __('PicDefense.io','picdefense-io-setting'), 'manage_options', 'picdefense-io-setting', array( $this, 'picdio_setting_page_func' ) );
	}
	
	public function picdio_admin_js(){
		wp_enqueue_style('picdefenseio-sweetalert-style', $this->picdio_plugin_url. 'assets/css/sweetalert2.min.css', array(), '1.0.0', 'all');
		wp_enqueue_style('picdefenseio-custom-style', $this->picdio_plugin_url. 'assets/css/picd-custom.css', array(), '1.0.0', 'all');
		wp_enqueue_script( 'picdefenseio-sweetalert-script', $this->picdio_plugin_url.'assets/js/sweetalert2.all.min.js', array('jquery'), TRUE );
		wp_enqueue_script( 'picdefenseio-custom-script', $this->picdio_plugin_url.'assets/js/picd-custom.js', array('jquery'), '1.0', TRUE );
	}
	
	public function picdio_setting_page_func() {
		global $wpdb;
		if (!current_user_can('manage_options')) {
		  wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' , 'picdefense-io' ) );
		}
		include('picdefense-io-setting.php');
	}
	
	public function picdio_conn_check(){
		$response = array();
		$PlulatestVersion = $this->picdio_get_plugin_version();		
		$endpoint = $this->picdio_API_URL.'/testconnection';
		$headers = array(
			'Authorization' => 'Basic '.get_option('picdefense_user_ID').':'.get_option('picdefense_api_key'),
			'x-picdio-v'	=> $PlulatestVersion
		);
		$args = array(
			'headers' => $headers
		);

		$resp = wp_remote_get( esc_url_raw($endpoint), $args );
		if ( is_wp_error( $resp ) ) {
			$response['error'] = 1;
			$response['message'] = 'Failed to connect, try again!';
		} else {
			$body = wp_remote_retrieve_body( $resp );
			$response = json_decode( $body, true );
			if($response['status'] == 1){
				$response['error'] = 0;
			} else {
				$response['error'] = 1;
			}
		}
		return $response;
	}
	
	public function picdio_wpversion_check(){
		$response = array();	
		$PlulatestVersion = $this->picdio_get_plugin_version();
		$endpoint = $this->picdio_API_URL.'/checkWPVersion';
		$postData = array('x-picdio-v' => $PlulatestVersion);
		$args = array(
			'body' => $postData
		);

		$resp = wp_remote_post( esc_url_raw($endpoint), $args );
		if ( is_wp_error( $resp ) ) {
			$response['error'] = 1;
			$response['message'] = 'Failed to connect, try again!';
		} else {
			$body = wp_remote_retrieve_body( $resp );
			$response = json_decode( $body, true );
			if($response['status'] != 1){
				$response['error'] = 1;
			} 
		}
		return $response;
	}
	
	public function picdio_get_plugin_version(){
		if ( is_admin() ) {
			$plugin_data = array();
			if( ! function_exists('get_plugin_data') ){
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$plugin_data = get_plugin_data( __FILE__ );
		}
		$pluginVersion = isset($plugin_data['Version']) && $plugin_data['Version'] != '' ? $plugin_data['Version'] : '1.0.0';
		return $pluginVersion;
	}
	
	public function picdio_get_picd_credits(){
		$response = array();
		$PlulatestVersion = $this->picdio_get_plugin_version();	
		$endpoint = $this->picdio_API_URL.'/credits';
		$headers = array(
			'Authorization' => 'Basic '.get_option('picdefense_user_ID').':'.get_option('picdefense_api_key'),
			'x-picdio-v' => $PlulatestVersion
		);
		$args = array(
			'headers' => $headers
		);

		$resp = wp_remote_get( esc_url_raw($endpoint), $args );
		if ( is_wp_error( $resp ) ) {
			$response['error'] = 1;
			$response['message'] = 'Failed to connect, try again!';
		} else {
			$body = wp_remote_retrieve_body( $resp );
			$response = json_decode( $body, true );
			if($response['status'] == 1){
				$response['error'] = 0;
			} else {
				$response['error'] = 1;
			}
		}
		return $response;
	}
	
	public function picdio_get_picd_images_count(){
		global $wpdb;
		$totalImages = 0;
		$incl = get_option('picdefense_inc_all_imgs');
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$args = array(
				'post_type'=> array('post', 'product'),
				'post_status' => array('publish'),
				'posts_per_page' => -1
			);
			$posts = new WP_Query( $args );
			foreach ( $posts->posts as $post ) {
				if($post->post_type == 'product'){
					$product_image_url = array();
					$product_image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail' );
					if(!empty($product_image_url[0])) {
						$totalImages++;
					}
				}
				
				if($post->post_type == 'post'){
					$content = $post->post_content;
					$regex = '/<img src="([^"]*)"/';
					preg_match_all( $regex, $content, $matches );
					$matches = array_reverse($matches);
					if(!empty($matches[0])){
						$totalImages = $totalImages + count($matches[0]);
					}
				}				
			}
			
			if($incl == 1) {
				$rows = $wpdb->get_results(
					$wpdb->prepare( "Select ID, guid, 'post' as type from `".$wpdb->prefix."posts` where post_type = 'attachment' and post_mime_type like 'image%' and post_status = 'inherit' and `post_title` != 'woocommerce-placeholder' and ID NOT IN (SELECT meta_value FROM `".$wpdb->prefix."postmeta` WHERE meta_key = '_thumbnail_id')
					UNION ALL
					Select ID, guid, 'product' as type from `".$wpdb->prefix."posts` where post_type = 'attachment' and post_mime_type like 'image%' and post_status = 'inherit' and ID IN (SELECT meta_value FROM `".$wpdb->prefix."postmeta` WHERE meta_key = '_thumbnail_id')") 
				);
				if(!empty($rows)){
					foreach($rows as $row){
						$img_rows = array();
						if($row->type == 'post'){
							$img_rows = $wpdb->get_results(
								$wpdb->prepare( "SELECT `ID` FROM `".$wpdb->prefix."posts` WHERE post_status = 'publish' and (post_type='post' or post_type = 'page') and `post_content` LIKE %s", '%' . $row->guid . '%' )
							);
						}
						if($row->type == 'product'){
							$img_rows = $wpdb->get_results(
								$wpdb->prepare( "SELECT post_id FROM `".$wpdb->prefix."postmeta` WHERE meta_key = '_thumbnail_id' and meta_value = %d", $row->ID ) 
							);
						}
						if(empty($img_rows)){
							$totalImages++;
						}
					}
				}
			}
		} else {
			$args = array(
				'post_type'=> 'post',
				'post_status' => array('publish'),
				'posts_per_page' => -1
			);
			$posts = new WP_Query( $args );
			foreach ( $posts->posts as $post ) {
				$content = $post->post_content;
				$regex = '/<img src="([^"]*)"/';
				preg_match_all( $regex, $content, $matches );
				$matches = array_reverse($matches);
				if(!empty($matches[0])){
					$totalImages = $totalImages + count($matches[0]);
				}
			}

			if($incl == 1) {
				$query_images_args = array(
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
				);

				$query_images = new WP_Query( $query_images_args );

				foreach ( $query_images->posts as $image ) {
					$img_url = '';
					$rows = array();
					$img_url = wp_get_attachment_url( $image->ID );
					$rows = $wpdb->get_results(
						$wpdb->prepare( "SELECT `ID` FROM `".$wpdb->prefix."posts` WHERE post_status = 'publish' and (post_type='post' or post_type = 'page') and `post_content` LIKE %s", '%' . $img_url . '%' ) 
					);
					if(empty($rows)){
						$totalImages++;
					}
				}
			}
		}
		update_option( 'picdefense_images_count', $totalImages );
		if(get_option('picdefense_rescan_images')){
			update_option( 'picdefense_rescan_images', 0 );
		}
	}
	
	public function picdio_scheduled_images_job(){
		$images_data = $this->picdio_get_picd_images_to_submit();
		if(!empty($images_data)){
			if (!file_exists($this->picdio_plugin_path.'CSV')) {
				mkdir($this->picdio_plugin_path.'CSV', 0777, true);
			}
			
			$sfile_path = $this->picdio_plugin_path.'CSV/'.time().'_picd_images_list.csv';			
			$fp = fopen($sfile_path, 'w'); 
			foreach ($images_data as $img) { 
				fputcsv($fp, $img); 
			}  
			fclose($fp); 
			$endpoint = $this->picdio_API_URL.'/submitWPUrls';
			$boundary = md5(time());
			$picdefense_submit_domain = !empty(get_option('picdefense_submit_domain')) ? get_option('picdefense_submit_domain') : 'localhost';
			$PlulatestVersion = !empty(get_option('picdefense_plugin_version')) ? get_option('picdefense_plugin_version') : '1.0.0';

			$post_fields = array(
				'domain' => sanitize_text_field($picdefense_submit_domain),
				'x-picdio-v' => $PlulatestVersion,
				'site_url' => get_site_url()
			);

			$payload = '';
			foreach ( $post_fields as $name => $value ) {
				$payload .= '--' . $boundary;
				$payload .= "\r\n";
				$payload .= 'Content-Disposition: form-data; name="' . $name .
					'"' . "\r\n\r\n";
				$payload .= $value;
				$payload .= "\r\n";
			}
			
			$payload .= '--' . $boundary . "\r\n";
			$payload .= 'Content-Disposition: form-data; name="file"; filename="' . basename($sfile_path) . '"' . "\r\n";
			$payload .= 'Content-Type: ' . mime_content_type($sfile_path) . "\r\n"; 
			$payload .= 'Content-Transfer-Encoding: binary' . "\r\n\r\n";
			$payload .= file_get_contents($sfile_path) . "\r\n";
			$payload .= '--' . $boundary . '--'; 
			$payload .= "\r\n\r\n";
			
			$headers  = array(
				'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
				'Authorization' => 'Basic '.get_option('picdefense_user_ID').':'.get_option('picdefense_api_key'),
				'Content-Length' => strlen($payload),
				'x-picdio-v' => $PlulatestVersion
			);
			
			$args = array(
				'headers' => $headers,
				'body' => $payload
			);
			
			$request = wp_remote_post($endpoint, $args);
			if ( is_wp_error( $request ) ) {
				$this->picd_write_log($request);
			} else {
				$body = wp_remote_retrieve_body( $request );
				$response_code = wp_remote_retrieve_response_code( $request );
				$response = json_decode($body, true);
				if ($response_code == 200) {
					$response = json_decode($body, true);
					if($response['status'] != 1) {
						$this->picd_write_log($response['message']);
					}
				} else {
					$this->picd_write_log($response['message']);
				}
			}
			
			if(get_option('picdefense_submit_images')){
				update_option( 'picdefense_submit_images', 0 );
			}
		}
	}
	
	public function picdio_get_picd_images_to_submit(){
		global $wpdb;
		$images_data = array();
		$incl = !empty(get_option('picdefense_inc_all_imgs')) ? get_option('picdefense_inc_all_imgs') : 0;
		$i = 0;
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$args = array(
				'post_type'=> array('post', 'product'),
				'post_status' => array('publish'),
				'posts_per_page' => -1
			);
			$posts = new WP_Query( $args );
			foreach ( $posts->posts as $post ) {
				if($post->post_type == 'product'){
					$product_image_url = $meta_data = array();
					$product_image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'single-post-thumbnail' );
					$meta_data = $this->picdio_get_image_metadata(get_post_thumbnail_id( $post->ID ));
					if(!empty($product_image_url[0])) {
						$images_data[$i][0] = $product_image_url[0];
						$images_data[$i][1] = esc_url( get_permalink($post->ID) );
						$images_data[$i][2] = esc_attr( $meta_data['alt'] );
						$images_data[$i][3] = esc_attr( $meta_data['caption'] );
						$images_data[$i][4] = esc_attr( $meta_data['description'] );
						$images_data[$i][5] = esc_url( $meta_data['href'] );
						$images_data[$i][6] = esc_url( $meta_data['src'] );
						$images_data[$i][7] = esc_attr( $meta_data['title'] );
						$images_data[$i][8] = esc_attr( $meta_data['height'] );
						$images_data[$i][9] = esc_attr( $meta_data['width'] );
						$images_data[$i][10] = esc_attr( $meta_data['filesize'] );
						$images_data[$i][11] = esc_attr( get_post_thumbnail_id( $post->ID ) );
						$i++;
					}
				}
				
				if($post->post_type == 'post'){
					$content = $post->post_content;
					$regex = '/<img src="([^"]*)"/';
					// we want all matches
					preg_match_all( $regex, $content, $matches );
					// reversing the matches array
					$matches = array_reverse($matches);
					if(!empty($matches[0])){
						foreach($matches[0] as $img_url){
							$image_ID = 0;
							$meta_data = array();
							$image_ID = $this->picdio_get_image_id($img_url);
							$meta_data = $this->picdio_get_image_metadata($image_ID);
							$images_data[$i][0] = $img_url;
							$images_data[$i][1] = esc_url( get_permalink($post->ID) );
							$images_data[$i][2] = esc_attr( $meta_data['alt'] );
							$images_data[$i][3] = esc_attr( $meta_data['caption'] );
							$images_data[$i][4] = esc_attr( $meta_data['description'] );
							$images_data[$i][5] = esc_url( $meta_data['href'] );
							$images_data[$i][6] = esc_url( $meta_data['src'] );
							$images_data[$i][7] = esc_attr( $meta_data['title'] );
							$images_data[$i][8] = esc_attr( $meta_data['height'] );
							$images_data[$i][9] = esc_attr( $meta_data['width'] );
							$images_data[$i][10] = esc_attr( $meta_data['filesize'] );
							$images_data[$i][11] = esc_attr( $image_ID );
							$i++;
						}
					}
				}				
			}
			
			if($incl == 1) {
				$rows = $wpdb->get_results(
					$wpdb->prepare( "Select ID, guid, 'post' as type from `".$wpdb->prefix."posts` where post_type = 'attachment' and post_mime_type like 'image%' and post_status = 'inherit' and `post_title` != 'woocommerce-placeholder' and ID NOT IN (SELECT meta_value FROM `".$wpdb->prefix."postmeta` WHERE meta_key = '_thumbnail_id')
					UNION ALL
					Select ID, guid, 'product' as type from `".$wpdb->prefix."posts` where post_type = 'attachment' and post_mime_type like 'image%' and post_status = 'inherit' and ID IN (SELECT meta_value FROM `".$wpdb->prefix."postmeta` WHERE meta_key = '_thumbnail_id')") 
				);
				if(!empty($rows)){
					foreach($rows as $row){
						$img_rows = array();
						if($row->type == 'post'){
							$img_rows = $wpdb->get_results(
								$wpdb->prepare( "SELECT `ID` FROM `".$wpdb->prefix."posts` WHERE post_status = 'publish' and (post_type='post' or post_type = 'page') and `post_content` LIKE %s", '%' . $row->guid . '%' )
							);
						}
						if($row->type == 'product'){
							$img_rows = $wpdb->get_results(
								$wpdb->prepare( "SELECT post_id FROM `".$wpdb->prefix."postmeta` WHERE meta_key = '_thumbnail_id' and meta_value = %d", $row->ID ) 
							);
						}
						if(empty($img_rows)){
							$meta_data = array();
							$meta_data = $this->picdio_get_image_metadata($row->ID);
							$images_data[$i][0] = $row->guid;
							$images_data[$i][1] = 'N/A';
							$images_data[$i][2] = esc_attr( $meta_data['alt'] );
							$images_data[$i][3] = esc_attr( $meta_data['caption'] );
							$images_data[$i][4] = esc_attr( $meta_data['description'] );
							$images_data[$i][5] = esc_url( $meta_data['href'] );
							$images_data[$i][6] = esc_url( $meta_data['src'] );
							$images_data[$i][7] = esc_attr( $meta_data['title'] );
							$images_data[$i][8] = esc_attr( $meta_data['height'] );
							$images_data[$i][9] = esc_attr( $meta_data['width'] );
							$images_data[$i][10] = esc_attr( $meta_data['filesize'] );
							$images_data[$i][11] = esc_attr( $row->ID );
							$i++;
						}
					}
				}	
			}	
		} else {
			$args = array(
				'post_type'=> 'post',
				'post_status' => array('publish'),
				'posts_per_page' => -1
			);
			$posts = new WP_Query( $args );
			foreach ( $posts->posts as $post ) {
				$content = $post->post_content;
				$regex = '/<img src="([^"]*)"/';
				// we want all matches
				preg_match_all( $regex, $content, $matches );
				// reversing the matches array
				$matches = array_reverse($matches);
				if(!empty($matches[0])){
					foreach($matches[0] as $img_url){
						$image_ID = 0;
						$meta_data = array();
						$image_ID = $this->picdio_get_image_id($img_url);
						$meta_data = $this->picdio_get_image_metadata($image_ID);
						$images_data[$i][0] = $img_url;
						$images_data[$i][1] = esc_url( get_permalink($post->ID) );
						$images_data[$i][2] = esc_attr( $meta_data['alt'] );
						$images_data[$i][3] = esc_attr( $meta_data['caption'] );
						$images_data[$i][4] = esc_attr( $meta_data['description'] );
						$images_data[$i][5] = esc_url( $meta_data['href'] );
						$images_data[$i][6] = esc_url( $meta_data['src'] );
						$images_data[$i][7] = esc_attr( $meta_data['title'] );
						$images_data[$i][8] = esc_attr( $meta_data['height'] );
						$images_data[$i][9] = esc_attr( $meta_data['width'] );
						$images_data[$i][10] = esc_attr( $meta_data['filesize'] );
						$images_data[$i][11] = esc_attr( $image_ID );
						$i++;
					}
				}
			}
			
			if($incl == 1) {
				$query_images_args = array(
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
				);

				$query_images = new WP_Query( $query_images_args );

				foreach ( $query_images->posts as $image ) {
					$img_url = '';
					$rows = array();
					$img_url = wp_get_attachment_url( $image->ID );
					$rows = $wpdb->get_results(
						$wpdb->prepare( "SELECT `ID` FROM `".$wpdb->prefix."posts` WHERE post_status = 'publish' and (post_type='post' or post_type = 'page') and `post_content` LIKE %s", '%' . $img_url . '%' ) 
					);
					if(empty($rows)){
						$meta_data = array();
						$meta_data = $this->picdio_get_image_metadata($image->ID);
						$images_data[$i][0] = $img_url;
						$images_data[$i][1] = 'N/A';
						$images_data[$i][2] = esc_attr( $meta_data['alt'] );
						$images_data[$i][3] = esc_attr( $meta_data['caption'] );
						$images_data[$i][4] = esc_attr( $meta_data['description'] );
						$images_data[$i][5] = esc_url( $meta_data['href'] );
						$images_data[$i][6] = esc_url( $meta_data['src'] );
						$images_data[$i][7] = esc_attr( $meta_data['title'] );
						$images_data[$i][8] = esc_attr( $meta_data['height'] );
						$images_data[$i][9] = esc_attr( $meta_data['width'] );
						$images_data[$i][10] = esc_attr( $meta_data['filesize'] );
						$images_data[$i][11] = esc_attr( $image->ID );
						$i++;
					}
				}
			}
		}
		return $images_data;
	}
	
	public function picdio_get_image_id($image_url) {
		global $wpdb;
		//Remove dimensions from url
		$image_url = preg_replace('/-\d+[Xx]\d+\./', ".", $image_url);
		$attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url )); 
		return $attachment[0]; 
	}
	
	public function picdio_get_image_metadata($attachment_id=''){
		$image_meta_data = $attachment = $meta_data = array();
		$attachment = get_post( $attachment_id );
		$meta_data = wp_get_attachment_metadata($attachment_id);
		$image_meta_data = array(
			'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'caption' => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'href' => get_permalink( $attachment->ID ),
            'src' => $attachment->guid,
            'title' => $attachment->post_title,
			'height' => $meta_data['height'],
			'width' => $meta_data['width'],
			'filesize' => $this->picdio_formatSizeUnits($meta_data['filesize'])
		);
		return $image_meta_data;
	}
	
	public function picdio_formatSizeUnits($bytes) {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }
        return $bytes;
	}
	
	public function picdio_maskAPIKey($cc, $maskFrom = 0, $maskTo = 8, $maskChar = '*', $maskSpacer = '') {
		$cc = str_replace(array('-', ' '), '', $cc);
		$ccLength = strlen($cc);
		if (empty($maskFrom) && $maskTo == $ccLength) {
			$cc = str_repeat($maskChar, $ccLength);
		} else {
			if($ccLength > $maskTo){
				$cc = substr($cc, 0, $maskFrom) . str_repeat($maskChar, $ccLength - $maskFrom - $maskTo) . substr($cc, -1 * $maskTo);
			}
		}

		if ($ccLength > 4) {
			$newApiKey = substr($cc, -4);
			for ($i = $ccLength - 5; $i >= 0; $i--) {
				if ((($i + 1) - $ccLength) % 4 == 0) {
					$newApiKey = $maskSpacer . $newApiKey;
				}
				$newApiKey = $cc[$i] . $newApiKey;
			}
		} else {
			$newApiKey = $cc;
		}
		return $newApiKey;
	}
	
	public function findFiles($directory, $extensions = array()) {
		$files = array ();
		foreach($extensions as $extension) {
			foreach(glob("{$directory}.{$extension}") as $file) {
				$files[] = $file;
			}
		}
		return $files;
	}
	
	public function picdefense_dismiss_notice() {
		update_option( 'picdio_cache_clear_message_dismissed', 1 );
	}
	
	public function picdefense_scheduled_event_verify() {
		if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON == true) {
			$result = array('disable' => 1, 'rescan' => 0, 'jobSubmit' => 0, 'scheduled' => false, 'picd_imgs_count' => 0);
		} else {
			$scheduled = false;
			if ( false !== wp_get_scheduled_event( 'picdio_scheduled_images_scan' )) {
				$scheduled = true;
			}
			if ( false !== wp_get_scheduled_event( 'picdio_scheduled_images_job_submit' )) {
				$scheduled = true;
			}
			$rescan = !empty(get_option('picdefense_rescan_images')) ? get_option('picdefense_rescan_images') : 0;
			$jobSubmit = !empty(get_option('picdefense_submit_images')) ? get_option('picdefense_submit_images') : 0;
			$result = array('disable' => 0, 'rescan' => intval($rescan), 'jobSubmit' => intval($jobSubmit), 'scheduled' => $scheduled, 'picd_imgs_count' => 0);
			$pic_imgs_count = !empty(get_option('picdefense_images_count')) ? get_option('picdefense_images_count') : 0;
			
			if(intval($pic_imgs_count) > 0){
				$result = array('disable' => 0, 'rescan' => intval($rescan), 'jobSubmit' => intval($jobSubmit), 'scheduled' => $scheduled, 'picd_imgs_count' => intval($pic_imgs_count));
			}
		}
		wp_send_json($result);
		wp_die();
	}
	
	public function picdio_cache_clear_message() {
		$dismissed = get_option( 'picdio_cache_clear_message_dismissed' );
		if($dismissed == 0) {
		?>
		<div class="notice notice-warning picdefense-notice is-dismissible">
			<p>Please clear your webserver and/or Cloudflare cache before/after image replacement (PicDefense.io).</p>
		</div>
		<?php
		}
	}
	
	public function picdio_notice() {
		?>
		<div id="message" class="error">
		  <p>Please upgrade PicDefense plugin to latest and resubmit job</p>
		</div>
		<?php
	}
	
    public function picd_write_log($log) {
        //if (true === WP_DEBUG) {
			if (!file_exists($this->picdio_plugin_path.'error_logs')) {
				mkdir($this->picdio_plugin_path.'error_logs', 0777, true);
			}
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true), 3, $this->picdio_plugin_path.'error_logs/picd_errors_'.date('Y-m-d').'.log' );
            } else {
                error_log($log. PHP_EOL, 3, $this->picdio_plugin_path.'error_logs/picd_errors_'.date('Y-m-d').'.log');
            }
        //}
    }
}
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 
new PicdefenseIO();