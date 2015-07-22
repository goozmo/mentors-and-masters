= Business Directory Plugin - Regions Module =
Contributors: businessdirectoryplugin.com
Donate link: http://businessdirectoryplugin.com/premium-modules/
Tags: business directory,classifieds,ads
Requires at least: 3.8
Tested up to: 4.2
Last Updated: 2015-Apr-23
Stable tag: tags/3.6.1

== Description ==
This module adds the ability to filter listings based on location in Business Directory plugin.  Allows you
to define what regions to use and to show/hide them on a sidelist.  Requires Business Directory Plugin 2.2 or higher.

The module works with Business Directory Plugin only (http://businessdirectoryplugin.com).

Full documentation here:  http://businessdirectoryplugin.com/docs/regions-module/

== Installation ==

   1. Download the ZIP file to your local machine.  DO NOT UNPACK THE ZIP FILE.
   
   2. Go to the Admin section of your website for WordPress.
   
   3. Go to Plugins -> Add New
   
   4. Click the "Upload" link at the top.
   
   5. Click the "Browse" button and find the ZIP file from Step 1 on your local machine.  
   
   6. Click "OK" and then "Install Now".
   
   7. When the installation completes, click then "Activate Plugin" link.
   
   8. Installation is now complete.
   
If you have any problems installing the plugin, please post on the [support forum](http://businessdirectoryplugin.com/support-forum/)

You configure your settings under Directory Admin->Manage Regions.  For more configuration information, please visit:
http://businessdirectoryplugin.com/docs/regions-module/

== Credits ==

Copyright 2012-5, D. Rodenbaugh 

This module is not included in the core of Business Directory Plugin.
It is a separate add-on premium module and is not subject to the terms of
the GPL license  used in the core package.

This module cannot be redistributed or resold in any modified versions of
the core Business Directory Plugin product. If you have this
module in your possession but did not purchase it via businessdirectoryplugin.com or otherwise
obtain it through businessdirectoryplugin.com please be aware that you have obtained it
through unauthorized means and cannot be given technical support through businessdirectoryplugin.com.


== Changelog ==
= Version 3.6.1 =
* Performance fix to not flush rewrite rules on plugins page

= Version 3.6 =
* Remove cookie/session filtering and start using Regions URLs for everything.
* Honor listing sort/order settings.
* Fix term_link for filter for new Regions URL scheme.
* Allow sidelist to remain open up to the active region.
* Add a Regions search sidebar widget.
* Improved display on mobile devices.
* Removed MyISAM declaration for table installation.
* Improve integration of new regions pages with Google Maps and other modules.
* Make `term_link()` default to region-filtered listings page.

= Version 3.3 =
* Added shortcode "wpbdp_regions_browser" for Craigslist style region browser. 
* Fix conflict with other taxonomies in tax_query array. 
* Change int columns to bigint for scalability reasons.

= Version 1.2 =
* Do not fail when an expected region form field was manually deleted by the admin.
* Workaround WordPress showing incorrect listing counts for the Regions taxonomy. 
* Make Regions cache regeneration work even if some region fields are not present. 
* Fix display of incorrect regions in the sidelist when no region field was visible. 
* Allow region selector to be completely hidden. 
* Show an admin warning when regions is incorrectly configured. (#366, #392)
* Allow listings to be assigned a region admin-side even before the first save. 
* Reset current page variable when changing the current region. 
* Move all settings to "Manage Options" section. 
* Add a display flag specific for the region selector. 
* Fix region fields display so that the order in which the fields appear doesn't affect functionality. 

= Version 1.1 =
* Fixed incompatibility issues with the Google Maps module. .
* Add ability to change regions slug. .
* Fixed various issues related to region filtering.
* Fixed an issue where the region selector was not displayed in category pages. .
* Perform a cleverer region matching when importing CSV files with region fields on them. .
* Fixed category counts being off when a region filter was active. .
* New configuration option to specify if the region selector should appear open or closed by default. .
* Include correct post type in Region archive pages to keep ordering in line with default BD behavior. .


= Version 1.0 =
* Initial version of Regions