=== Business Directory Plugin - Google Maps ===
Contributors: businessdirectoryplugin
Donate link: http://businessdirectoryplugin.com/premium-modules/
Requires at least: 3.7
Tested up to: 4.1.1
Last Updated: 2015-Apr-7
Stable tag: tags/3.6

Adds support for Google Maps for display in a Business Directory listing.  
Allows you to map any set of fields to the address for use by Google Maps.

== Description ==

Adds support for Google Maps for display in a Business Directory listing.  Allows you to map any set of fields to the address for use by Google Maps.


== Installation ==

   1. Download the ZIP file to your local machine.  DO NOT UNPACK THE ZIP FILE.
   
   2. Go to the Admin section of your website for WordPress.
   
   3. Go to Plugins -> Add New
   
   4. Click the "Upload" link at the top.
   
   5. Click the "Browse" button and find the ZIP file from Step 1 on your local machine.  
   
   6. Click "OK" and then "Install Now".
   
   7. When the installation completes, click then "Activate Plugin" link.
   
   8. You should now see a Google Maps tab in your BD installation under Directory Admin->Manage Options.
   
If you have any problems installing the plugin, please post on the [support forum](http://businessdirectoryplugin.com/support-forum/)


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


== Screenshots ==

[Visit the main site for feature lists and screenshots](http://businessdirectoryplugin.com/features/)


== Changelog ==
= Version 3.6 =
- Support for Lat/Long and directions added
- Google Maps integration is enabled by default after the plugin is activated.
- Use actually enqueued (not just registered) scripts when checking Google Maps API scripts.
- Show a warning when API returns REQUEST_DENIED errors.
- Allow users to modify their listing location manually using a location search JS widget.
- Improved display on mobile devices

= Version 3.3 =
* Add tree-like view for listings that share the same location (address).
* Change int columns to bigint for scalability reasons.

Version 1.4
   1. Geolocate listings server-side instead of using the JS API to avoid hitting Google Maps limits
   2. Performance enhancements
   3. Show all relevant listings in a category or search results page instead of just the ones visible on the page
   4. Use newlines instead of commas to separate address parts when displaying a map pin
   5. Use a special hash to mark listings that need to be geolocated to avoid unnecessary hits to the Maps API.

Version 1.3
   1. API cleanup.
   2. Allow listing map to be displayed at the top or bottom of the category, view-listings and search results pages. (#239).
   3. Map can now be sized to any dimensions
   4. Regions fields can now be used as Google Maps fields
   

Version 1.1 and 1.2
   1. Minor bug fixes, updates to handle 2.1.x and activation
   

Version 1.0
   1. Initial Version
   
   