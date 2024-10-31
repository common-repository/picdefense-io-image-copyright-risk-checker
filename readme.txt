=== PicDefense.io - Your Guard Against Image Copyright Infringement ===
Contributors: picdefense
Tags: images, replacement, watermark, copyright, picdefense
Requires at least: 6.0.2
Tested up to: 6.6.1
Stable tag: 1.1.3
Requires PHP: 7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compile list of images on your Wordpress site and submit to PicDefense.io for copyright risk analysis.

== Description ==

Has a copyright enforcer served you with a demand letter for an image you used on your site? Are you concerned about potential copyright risks associated with your current images? PicDefense.io is your ultimate solution.

Our WordPress plugin doesn't just do a reverse image search; it acts as your shield against potential copyright infringement. It scans your website's images, assesses their copyright risk and now, with our new feature, allows you to replace any high-risk images with free stock photography from Pexels.

After installing this plugin, it will gather a list of image links from your wordpress site and send them to us (PicDefense.io) via our custom API endpoints. Follow the instructions to create your account and link your WordPress site with your PicDefense.io account. Then, let us do the rest.

== Frequently Asked Questions ==

= Why should I use PicDefense? =

PicDefense.io is designed to protect your website from potential copyright issues by scanning all of the images on your website and alerting you to any that may be at risk for copyright infringement.

= Does this plugin use any third-party services? =

Yes, this plugin integrates with the following third-party service:

- **Image Processing Service**: This service processes all images uploaded to your WordPress site. It provides feature to replace the high-risk one with, ensuring a smooth and hassle-free replacement.

= What data is shared with third-party services? =

The plugin shares the following data with the Image Processing Service:

- **Images**: All images uploaded to your WordPress site are sent to the third-party service for processing. This includes image metadata such as file name, size, and format.

= How is user data protected? =

The plugin ensures user data is protected by following these measures:

- **Encryption**: All data transmitted between your site and the third-party service is encrypted using SSL.
- **Anonymization**: Personal information related to the images is anonymized where possible to protect user privacy.
- **Data Retention**: The third-party service retains processed images for a limited time, after which they are deleted from their servers.

= What permissions does the plugin require? =

The plugin requires the following permissions to function correctly:

- **Upload Files**: The plugin needs permission to upload and retrieve images from your WordPress site.
- **Manage Options**: The plugin requires access to manage its settings within the WordPress admin panel.

= Where can I find the third-party service's terms and privacy policy? =

You can review the terms and privacy policy of the Image Processing Service at the following links:
- **Terms of Service**: [Image Processing Service Terms](https://picdefense.io/terms-conditions)
- **Privacy Policy**: [Image Processing Service Privacy Policy](https://picdefense.io/privacy-cookie-policy)

= If an Image is not Copyrighted, am I free to use it in any way? =

No, not always. Even if a copyright is not immediately apparent, it does not mean the image is free to use without restrictions. There may be other ownership rights that could still put you at risk.

= How quickly will I receive my report? =

Usually, the report is generated within a few minutes, depending on the number of images involved in your check. Your dashboard will display the current status of your check.

= Can PicDefense perform a Reverse Image Search on my images? =

Absolutely! Each image that PicDefense.io checks goes through a reverse image search. We provide details such as size dimensions, a list of backlinking websites that contain the image, similarity score, as well as our unique PicRisk Score.

= Aren't the Images I Use "Royalty-Free" - does that mean they're free to use? =

The term "royalty-free" is often misunderstood. It does not mean that an image is free to use without restrictions. Instead, it means that the user does not have to pay a royalty or license fee for each use of the image. However, royalty-free images may still have terms of use, and the user may need to pay a one-time fee to use the image.

= What does the 'Replace Image' feature do? =

Our new feature allows you to replace high-risk images on your WordPress website easily with free stock images from Pexels. After a scan, you will find a 'Replace Image' button next to each high-risk image. Upon clicking, a window will pop up, presenting related images from Pexels. You can choose the image you'd like to replace the high-risk one with, ensuring a smooth and hassle-free replacement.

= Where can I find more documentation? =

For more detailed instructions and features, please visit our documentation at https://picdefense.io/docs/.

Remember, prevention is better than cure. Don't wait for a demand letter to hit your mailbox. Make PicDefense.io your first line of defense against image copyright infringement today.

== Screenshots ==

1. Initial installation of plugin without user ID or API key.  This is what your plugin settings will look like if you've never installed or added settings to the plugin.
2. User ID / API Keys entered and the 'Save Settings' button clicked.  This shows the credentials saved, but have been tested.
3. 'Test connection to PicDefense' button was clicked and this shows the connection was successful.  Account credits from PicDefense.io are shown here, as well as number of images found in Wordpress.  Also, is a 'Scan for New Images' button in case you add images later and need to submit a new list.
4. After Submit ... Images to PicDefense button is clicked, this confirmation appears.  It includes a link to the Dashboard, where you will further manage your job/request submission.
5. When clicking the 'Replace' button on your risk report you will see a popup to choose a relevant image from Pexels to replace the high-risk image with.  

== Changelog ==

= 1.1.3 =
Bug Fix - Automated image resizing when uploading images to media library

= 1.1.2 =
Guidelines update and Wordpress version testing

= 1.1.1 =
* Enhancement - image data gathering and submission to PicDefense.io are now background process.  WP_CRON/wp_scheduler is required and used to schedule the necessary background processes.
* Added - error logging to plugin directory for troubleshooting purposes

= 1.1.0 =
* Added - version checking on plugin activation, job submissions, and save settings
* Added - API endpoints to enable 'Replace' image functionality based on PicDefense.io risk reports

= 1.0.0 =
* Initial release

== Upgrade Notice ==
= 1.1.3 =
Bug Fix - Automated image resizing when uploading images to media library