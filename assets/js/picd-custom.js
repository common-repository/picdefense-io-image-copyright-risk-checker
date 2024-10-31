jQuery(document).on( 'click', '.picdefense-notice .notice-dismiss', function() {
    jQuery.ajax({
        url: ajaxurl,
		method: "POST",
        data: {
            action: 'picdefense_dismiss_notice'
        }
    });
});

function picdio_dismiss_notice(){
	jQuery('.notice-dismiss-picd').parent().parent().hide();
}
function picdio_reset_picd_form(){
	jQuery('#reset_form_picd').submit();
}
function picdio_test_connect(){
	jQuery('#test_conn_form').submit();
}
function picdio_rescan_images_picd(){
	var incl = 0;
	if(jQuery('#incl_lib_imgs').prop("checked") == true){
		var incl = 1;
	}
	jQuery('#rescan_picd_inc').val(incl);
	jQuery('#rescan_images_form').submit();
}
function picdio_submit_job_picd(){
	jQuery('#submit_job_picd_btn').attr('disabled', 'disabled');
	//jQuery('#rescan_images_picd_btn').attr('disabled', 'disabled');
	jQuery('.picdio_loader').css('display', 'inline-block');
	jQuery('#submit_job_picd_form').submit();
}

if (picdioData.jobSubmitted == 1) {
	Swal.fire({
		icon: "success",
		html: "Images submission in progress. Please visit <a target=\"_blank\" href=\"https://app.picdefense.io/dashboard\">Dashboard</a> to review status.",
		showCloseButton: true
	});
}

setInterval(function(){
	jQuery.ajax({
		url: ajaxurl,
		method: "POST",
		data: {
			action: 'picdefense_scheduled_event_verify'
		}
	})  
	.done(function( response ) {
		if(response.disable){
			jQuery('#wp_scheduled_picd_job').hide();
			jQuery('#rescan_images_form').hide();
			jQuery('#submit_job_picd_form').hide();
			jQuery('#wp_cron_disabled_picd_msg').show();
		} else {
			if(response.rescan){
				jQuery('#wp_scheduled_picd_job').html('<div class="notice_picd notice-success"><p>Image data re-scan is in progress in the background, please wait...</p></div>');
			} if(response.jobSubmit){
				jQuery('#wp_scheduled_picd_job').html('<div class="notice_picd notice-success"><p>Your image data is being submitted to PicDefense.io, this message will disappear when this process has completed. Please wait...</p></div>');
			} else {
				jQuery('#wp_scheduled_picd_job').html('<div class="notice_picd notice-success"><p>Images data is being gathered in the background, please wait...</p></div>');
			}
			
			if(response.scheduled == false){
				jQuery('#submit_job_picd_btn').val('Submit '+response.picd_imgs_count+' Images to PicDefense');
				jQuery('#submit_job_picd_btn').prop('disabled', false);
				jQuery('#rescan_images_picd_btn').prop('disabled', false);
				jQuery('#incl_lib_imgs').prop('disabled', false);
				jQuery('#incl_lib_imgs').parent().removeClass('picd_disabled_txt');
				jQuery('#wp_scheduled_picd_job').hide();
			} else {
				jQuery('#submit_job_picd_btn').prop('disabled', true);
				jQuery('#rescan_images_picd_btn').prop('disabled', true);
				jQuery('#incl_lib_imgs').prop('disabled', true);
				jQuery('#incl_lib_imgs').parent().addClass('picd_disabled_txt');
				jQuery('#wp_scheduled_picd_job').show();
			}
			
			jQuery('#rescan_images_form').show();
			jQuery('#submit_job_picd_form').show();
			jQuery('#wp_cron_disabled_picd_msg').hide();
		}
	});
}, 5000); //Every 5 seconds