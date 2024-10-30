=== Community Blogs for BuddyPress ===

Contributors : Burt Adsit
Version : 1.0
Tags: buddypress, group, blogs, community
Requires at least: 2.7
Tested up to: 2.7
Stable tag: trunk
Plugin URL : http://wordpress.org/extend/plugins/bp-community-blogs/
License: GNU General Public License 3.0 (GPL) http://www.gnu.org/licenses/gpl.html

== Description ==

This is a BuddyPress plugin that allows a Blog or Site Admin to turn a normal blog into a Community Blog or a Group Blog.  By activating the Community Blogs plugin, Administrators can give immediate registered user status to any member of their BuddyPress enabled site on the blogs they choose. All the member has to do is visit the blog. The user is silently added as a registered user of that blog. Administrators can choose the role the new user has.  Either Contributor or Author. Admins can give access to all users or users from specific BuddyPress groups.

== Installation ==

Unzip and place the folder in your /wp-content/plugins folder. Activate this through the normal method. Visit Settings > Community Blog to configure the plugin.

== Configuration Options ==

This plugin lives in the /wp-content/plugins folder and can be enabled on a blog by blog basis. Site Admins can control the activation of the Community Blogs plugin through normal plugin management methods such as disabling the plugins menu or using any plugin admin utility such as Plugin Commander. Community Blogs settings are configured in the Settings > Community Blog admin form which is available to blog admins.
 

* Enable this plugin: which turns off new user registrations but leaves the plugin activated

* Default User Role: for new users as Editor, Author or Contributor

* Allow All Registered Users: when set to Yes then any member of the BP community can become a registered user at the default role on that blog. When set to No the Community Blogs plugin becomes a Group Blogs plugin.

* Groups To Allow Access: is a list of the BuddyPress Groups that can become registered users. More that one group can be given access if you like. The group slugs are used to specify what groups have immediate registration access to the blog. This allows blog admins to configure the plugin without the Site Admin having to give them group ids. If the blog admin can find the group slug they can configure access.

== Hooks ==
An action is triggered that can be trapped when a user is added to a blog or a user gets an upgrade from one role to the default role. You can listen for this action with:

function my_user_upgrade_hook($user_id, $old_role, $new_role){
// do something here
}
add_action('oci_upgrade_user','my_user_upgrade_hook',10,3);

The parameters $old_role, $new_role are those returned by the function
oci_get_current_role() and are strings such as 'norole', 'subscriber', etc..

