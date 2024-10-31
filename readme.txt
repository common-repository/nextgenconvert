=== NextGenConvert ===
Contributors: nextgenconvert
Tags: images,performance,webp,convert,SEO
Tested up to: 6.4
License: GPL2
Stable Tag: 1.0.4
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Optimise your site with plug-and-play WebP image conversion for quicker image load times via a nextgenconvert.com subscription

== Description ==

NextGenConvert is a tool that helps you transform your website's images into the Next-Gen format, primarily WebP. This format makes your images smaller in size while maintaining quality, ensuring your website images loads faster, improving your visitors' experience. This WordPress plugin enables you to use nextgenconvert.com automaticly in your WordPress website in a plug-and-play way, making it straightforward and hassle-free to optimize your images directly within your WordPress dashboard.

This plugin, while still in its early stages, is set for continuous enhancement. We warmly welcome any requests for new features or bug reports — your feedback is crucial for our improvement. 

== Third-Party Services ==

Our plugin integrates with NextGenConvert (https://nextgenconvert.com), a service for converting images to next-generation formats. This integration is essential for converting your images with this plugin. To use this plugin you need a valid api key from NextGenConvert.com 

For more information about NextGenConvert, visit: https://nextgenconvert.com.

Please review NextGenConvert's Terms of Use and Privacy Policy for more details:
- Terms of Use: https://api.nextgenconvert.com/#terms
- Privacy Policy: https://api.nextgenconvert.com/#privacy
  
== Installation ==

1. Download the NextGenConvert plugin from the WordPress plugin directory.
2. Upload the plugin files to the `/wp-content/plugins/nextgenconvert` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. After activation, navigate to the NextGenConvert settings page to configure the plugin.
5. If you don't have a subscription, purchase one from nextgenconvert.com.
6. Enter your API key from nextgenconvert.com into the designated field and click 'Save'.
7. Choose your desired image quality settings within the plugin and click 'Save'.
8. Begin the conversion process to transform your images into the WebP format.
9. Verify that your website is now serving images in WebP format. Remember to clear any website caches to properly see the changes.

== Changelog ==

= 1.0.4 =
* Fixed escaping issue
* Used wpfs for rmdir
* Used wpfs for file_put_contents
* Used wpfs for mkdir 
* Added the hook rather than $_GET to enqueue_admin_scripts & enqueue_admin_styles

= 1.0.3 =
* Updated readme.txt to document the use of third-party services, including privacy policy and terms of use links.
* Removed external dependencies, included all files locally within the plugin specificly Jquery Data Tables.
* Ensured permission checks using `current_user_can()` for secure functionality access.
* Implemented nonce checks for enhanced security in POST calls.
* Enhanced data handling by sanitizing, validating, and escaping input and output data.
* Escaped variables and options when echoed to prevent XSS vulnerabilities.

= 1.0.2 =
* Fixes for initial submission to the WordPress Plugin Repository.

= 1.0.1 =
* General cleanup and optimization of code.
* Preliminary fixes in preparation for submission to the WordPress Plugin Repository.

= 1.0.0 =
* Introduced WebP conversion feature, allowing users to convert their images to the WebP format for improved performance and reduced file size.
* Added a settings page for easy plugin configuration and API key integration.
* Included a bulk conversion tool, enabling users to convert all images.
* Provided detailed documentation and instructions within the plugin to assist users in setup and usage.