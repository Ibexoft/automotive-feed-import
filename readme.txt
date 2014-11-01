=== Automotive Feed Importer ===
Contributors: jawaid
Donate link: http://www.ibexofts.tk
Tags: automotiv, auto, vehicle, inventory, feed, import, 
Requires at least: 3.0.1
Tested up to: 4.0
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Import Vehicle Inventory from XML feed into database for Automotiv Theme.

== Description ==

Plugin runs every 10 minutes and import XML feed data into database. When importing data from feed, it searches if stock number (from feed) is associated with any listing (post). If not, it will create a new listing and associate the data from feed with listing by mapping stock number to post id. If it finds the association, it will update the data. It also displays the imported data on edit screen of listing.

= Demo =

You can see the working demo of plugin at http://automotive.site.bz

= Test Cases =

*	Typical case: Plugin runs every 10 minutes and load the XML feed, and update the listings against each unit from feed. If feed contains any unit for which it cannot find associated listing, it will create a new listing and then update data for it.
*	First time load:
o	When there are no listings: It will create a new list against each unit imported from feed.
o	When there are already some listings: Typically, there will not be any unit added to listings, in this case it will create new listing for each of the unit. If one needs to associate the units with existing listing, then it requires manual intervention either doing it by hand or by some custom script.

= Future Development =

There is still plenty of room for enhancement and optimization in this plugin as few things have been done by making assumptions. Enhancements/optimizations that we plan to do are:
1)	Currently the XML feed path is hardcoded to be picked from /wp-content/plugins/automotive-feed-import/ folder, which is root of this plugin. Folder name for plugin can be anything, it will pick automatically, but file should be present in this plugins root folder. For this, new options/settings page can be created for plugin so user can specify custom path to pick the XML feed from.
2)	At the moment, when creating new listing it sets the 
a)	Post title as manufacturer and brand concatenated, and
b)	Post content as designation, manufacturer, brand, model, and model year concatenated
This can be modified and further information can be set after clarifying, finalizing, and discussing requirements in detail.
3)	Following fields in XML feed are also made available by the theme in same or some other manner:
a)	Manufacturer
b)	Year
c)	Price
d)	Mileage
e)	Color
When importing data, plugin does not touch the data already provided by the theme, instead it adds data resulting in some redundant information. And, when creating new listing it copies the same value from feed into these fields. Again, this can be modified after clarifying, finalizing, and discussing requirements in detail.

== Installation ==

1. Upload `automotive-feed-import` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Does it have a paid version? =

No, not yet. Plugin is free and support is also free at the moment. Donations are appreciated though.


== Screenshots ==

1. Fields added in Admin section of custom post type of Automobile Listing after import from XML feed.

== Changelog ==

= 1.0 =
* Released and synced with GitHub at https://github.com/mjawaids/automotive-feed-import.
= 0.1 =
* First version release.

== Upgrade Notice ==

= 1.0 =
Compatible with first version v0.1.