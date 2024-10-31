<?php 
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly 

$update_success = '';
$job_submitted = $connection = 0;

if(isset($_POST['action']) && $_POST['action'] == 'reset_form_picd'){
	$vresp = $this->picdio_wpversion_check();
	if($vresp['error'] == 1){
		$update_error = $vresp['message'];
	} else {
		if ( wp_next_scheduled( 'picdio_scheduled_images_scan' ) ) {
			$update_error = 'Image data gathering is in progress, please try again later.';
		} else if ( wp_next_scheduled( 'picdio_scheduled_images_job_submit' ) ) {
			$update_error = 'Image data submission in progress, please try again later.';
		} else {
			delete_option('picdefense_api_key');
			delete_option('picdefense_user_ID');
			delete_option('picdefense_test_connect');
			$update_success = 'Settings cleared successfully.';
		}
	}
}

if(isset($_POST['action']) && $_POST['action'] == 'picd_submit'){
	$vresp = $this->picdio_wpversion_check();
	if($vresp['error'] == 1){
		$update_error = $vresp['message'];
	} else {
		if(!empty(trim($_POST['picdefense_api_key'])) && strpos(trim($_POST['picdefense_api_key']), '*****') === false){
			$result = update_option( 'picdefense_api_key', sanitize_text_field(trim($_POST['picdefense_api_key'])) );
			if($result){
				delete_option( 'picdefense_test_connect');
				$update_success = 'Record updated successfully.';
			}
		}
		if(!empty(trim($_POST['picdefense_user_ID'])) && strpos(trim($_POST['picdefense_user_ID']), '*****') === false){
			$result = update_option( 'picdefense_user_ID', sanitize_text_field(trim($_POST['picdefense_user_ID'])) );
			if($result){
				delete_option( 'picdefense_test_connect');
				$update_success = 'Record updated successfully.';
			}
		}
	}
}

if(isset($_POST['action']) && $_POST['action'] == 'test_conn_picd'){
	$response = $this->picdio_conn_check();
	if($response['status'] == 1){
		update_option( 'picdefense_test_connect', 'yes' );
		$connection = 1;
	} else {
		$update_error = $response['message'];
	}
}

if(isset($_POST['action']) && $_POST['action'] == 'rescan_images_picd'){
	$vresp = $this->picdio_wpversion_check();
	if($vresp['error'] == 1){
		$update_error = $vresp['message'];
	} else {
		$incl = 0;
		if(isset($_POST['rescan_picd_inc']) && $_POST['rescan_picd_inc'] == 1){
			$incl = 1;
		}
		update_option( 'picdefense_inc_all_imgs', $incl );
		update_option( 'picdefense_rescan_images', 1 );
		if ( !wp_next_scheduled( 'picdio_scheduled_images_scan' ) ) {
			$this->picdio_schedule_job_scan_setup();
		}
	}
}

if(isset($_POST['action']) && $_POST['action'] == 'scan_picd'){
	$response = $this->picdio_get_picd_credits();
	if($response['status'] == 1){
		$credits = $response['data']['credits'];
		$incl = 0;
		if(isset($_POST['incl_lib_imgs']) && $_POST['incl_lib_imgs'] == 1){
			$incl = 1;
		}
		update_option( 'picdefense_inc_all_imgs', $incl );
		update_option( 'picdefense_submit_domain', sanitize_text_field(trim($_SERVER['SERVER_NAME'])) );
		update_option( 'picdefense_plugin_version', $this->picdio_get_plugin_version() );
		update_option( 'picdefense_submit_images', 1 );
		if ( !wp_next_scheduled( 'picdio_scheduled_images_job_submit' ) ) {
			$this->picdio_schedule_job_submit_setup();
		}
		if ( wp_next_scheduled( 'picdio_scheduled_images_job_submit' ) ) {
			$job_submitted = 1;
		} else {
			$update_error = 'There is an error. Please try again!';
		}
	} else {
		$update_error = $response['message'];
	}
}
if(!empty(get_option('picdefense_test_connect')) && get_option('picdefense_test_connect') == 'yes') {
	$resp = $this->picdio_get_picd_credits();
	$credits = $resp['data']['credits'];
}
wp_localize_script('picdefenseio-custom-script', 'picdioData', array( 'jobSubmitted' => $job_submitted));
?>
<div class="wrap">
  <?php if(!empty($update_success)) { ?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html($update_success); ?></p></div>
  <?php } ?>  
  <?php if(!empty($update_error)) { ?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html($update_error); ?></p></div>
  <?php } ?>
  <div class="inst_outer">
	<h2>PicDefense.io Settings</h2>
	<p>Allow PicDefense.io to scan and check all your images.</p>
	<h2>User ID and API Key Settings</h2>
	<p>Read our <a href="https://picdefense.io/docs/how-to-get-your-user-id-and-api-key-for-picdefense-io" target="_blank">documentation</a> on how to obtain the User ID and API Key.</p>
	<p>Input your User ID and API Key settings found in the <a href="https://app.picdefense.io/dashboard/settings" target="_blank">PicDefense.io Dashboard</a>.</p>
	<p>After inputting your User ID and API Key, click on "Save Settings" button.</p>
	<p>To test if PicDefense.io is able to communicate with your website, click on the "Test connection to PicDefense" button to check.</p>
	<h2>Instructions</h2>
	<ol>
		<li>Input your User ID and API Key Settings and Test your Connectivity.</li>
		<li>Click on the "Step 1: Submit Job to PicDefense" button. Once you click on this button, we will obtain a list of your images on your website and submit them to PicDefense.io for processing. You can exit this page after clicking on the button and the process will still continue in the background.</li>
		<li>Visit <a href="https://app.picdefense.io/dashboard" target="_blank">PicDefense.io Dashboard</a> to approve the job. Our system will let you know how many credits you need to process the check. Once you click on the "Approve Now" button, the report will be displayed in the <a href="https://app.picdefense.io/dashboard" target="_blank">PicDefense.io Dashboard</a>.</li>
	</ol>
  </div>
  <form method="POST" action=""> 
    <input type="hidden" name="action" value="picd_submit">
	<table>
		<tr>
			<td><label>User ID</label></td>
			<td>:</td>
			<td><input type="text" name="picdefense_user_ID" id="picdefense_user_ID" size="50" class="tl_text_editor" value="<?php echo !empty(get_option('picdefense_user_ID')) ? esc_html($this->picdio_maskAPIKey(get_option('picdefense_user_ID'))) : ''; ?>"></td>
		</tr>
		
		<tr>
			<td><label>API Key</label></td>
			<td>:</td>
			<td><input type="text" name="picdefense_api_key" id="picdefense_api_key" size="50" class="tl_text_editor" value="<?php echo !empty(get_option('picdefense_api_key')) ? esc_html($this->picdio_maskAPIKey(get_option('picdefense_api_key'))) : ''; ?>"> <a class="ev_link" href="https://app.picdefense.io/dashboard/settings" target="_blank">Get the API key</a></td>
		</tr>

		<tr>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td>
				<input type="submit" name="submit" class="button-primary" value="<?php esc_html_e('Save Settings', 'picdefense-io'); ?>">
				<input type="button" name="reset" onclick="picdio_reset_picd_form();" class="button-primary" value="<?php esc_html_e('Clear Settings', 'picdefense-io'); ?>">
				<?php if(!empty(get_option('picdefense_api_key')) && !empty(get_option('picdefense_user_ID')) && empty(get_option('picdefense_test_connect'))) { ?>
				<input type="button" name="conn_check" onclick="picdio_test_connect();" class="button-primary" value="<?php esc_html_e('Test connection to PicDefense', 'picdefense-io'); ?>">
				<?php } ?>
			</td>
		</tr>
		<?php if($connection == 1) { ?>
		<tr>
			<td>&nbsp;</td>
			<td>&nbsp;</td>
			<td><div class="notice_picd notice-success is-dismissible"><p>Connection successful</p><button type="button" class="notice-dismiss notice-dismiss-picd" onclick="picdio_dismiss_notice();"><span class="screen-reader-text">Dismiss this notice.</span></button></div></td>
		</tr>
		<?php } ?>
	</table>
  </form>
  <form method="POST" action="" id="reset_form_picd"> 
	<input type="hidden" name="action" value="reset_form_picd">
  </form>
  <?php if(!empty(get_option('picdefense_api_key')) && !empty(get_option('picdefense_user_ID'))) { ?>
  <div>
	<?php if(empty(get_option('picdefense_test_connect'))) { ?>
	<form method="POST" action="" id="test_conn_form"> 
		<input type="hidden" name="action" value="test_conn_picd">
	</form>
	<?php } ?>
  <?php 
	if(!empty(get_option('picdefense_test_connect')) && get_option('picdefense_test_connect') == 'yes') {
		$disable = 0;
		if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON == true) {
			$disable = 1;
		}
		
		$img_counts = !empty(get_option('picdefense_images_count')) ? get_option('picdefense_images_count') : '';
		$scheduled = false;
		if ( false !== wp_get_scheduled_event( 'picdio_scheduled_images_scan' ) ) {
			$scheduled = true;
		}
		if ( false !== wp_get_scheduled_event( 'picdio_scheduled_images_job_submit' )) {
			$scheduled = true;
		}
	?>
	<div class="picd_sbmt_section">
		<div class="picd_credits">Credits : <?php echo esc_html($credits); ?></div>
		<form method="POST" action="" id="submit_job_picd_form" <?php if($disable == 1) { ?>style="display:none;"<?php } ?>> 
			<input type="hidden" name="action" value="scan_picd">
			<table>
				<tr>
					<td><label>&nbsp;</label></td>
					<td></td>
					<td>
						<input type="button" name="submit_jb_picd" onclick="picdio_submit_job_picd();" id="submit_job_picd_btn" class="button-primary" value="<?php printf( esc_html__( 'Submit %s Images to PicDefense', 'picdefense-io' ), esc_html( $img_counts ) ); ?>" <?php if($scheduled) { ?>disabled="disabled"<?php } ?>>
						<img class="picdio_loader" src="<?php echo $this->picdio_plugin_url.'assets/images/loader.gif'; ?>">
						<input type="button" name="rescan_images" onclick="picdio_rescan_images_picd();" id="rescan_images_picd_btn" class="button-primary" value="<?php esc_html_e('Scan for New Images', 'picdefense-io'); ?>" <?php if($scheduled) { ?>disabled="disabled"<?php } ?>>
					</td>
				</tr>
				<tr>
					<td><label>&nbsp;</label></td>
					<td></td>
					<td <?php if($scheduled) { ?>class="picd_disabled_txt"<?php } ?>><input type="checkbox" name="incl_lib_imgs" id="incl_lib_imgs" value="1" <?php if(get_option('picdefense_inc_all_imgs') == 1) { ?>checked="checked"<?php } ?> <?php if($scheduled) { ?>disabled="disabled"<?php } ?>> Include ALL Images from Media Library? (Must click 'Scan For New Images' to update count) </td>
				</tr>
			</table>
		</form>
		<form method="POST" action="" id="rescan_images_form" <?php if($disable == 1) { ?>style="display:none;"<?php } ?>> 
			<input type="hidden" name="action" value="rescan_images_picd">
			<input type="hidden" name="rescan_picd_inc" id="rescan_picd_inc" value="<?php echo esc_html(get_option('picdefense_inc_all_imgs')); ?>">
		</form>
	</div>
	<?php 
	$rescan = !empty(get_option('picdefense_rescan_images')) ? get_option('picdefense_rescan_images') : 0;
	$imgSubmit = !empty(get_option('picdefense_submit_images')) ? get_option('picdefense_submit_images') : 0;

	$scheduled_msg = 'Images data is being gathered in the background, please wait...';
	if($rescan){
		$scheduled_msg = 'Image data re-scan is in progress in the background, please wait...';
	}
	if($imgSubmit){
		$scheduled_msg = 'Your image data is being submitted to PicDefense.io, this message will disappear when this process has completed. Please wait...';
	}
	?>
	<div id="wp_scheduled_picd_job" <?php if(!$scheduled || $disable == 1) { ?>style="display:none;"<?php } ?>>
		<div class="notice_picd notice-success"><p><?php echo $scheduled_msg; ?></p></div>
	</div>
	<div id="wp_cron_disabled_picd_msg" <?php if($disable == 0) { ?>style="display:none;"<?php } ?>>
		<div class="notice_picd notice-success"><p>WP Cron is disabled, you must enable WP Cron for this plugin.</p></div>
	</div>
	<?php } ?>
  </div>
  <?php } ?>
</div>