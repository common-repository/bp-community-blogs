<?php
/*
Plugin Name: Community Blogs
Plugin URI: http://wordpress.org/extend/plugins/bp-community-blogs/
Description: BuddyPress plugin that allows Blog or Site Admins to configure any blog as a 'Community Blog'. Silently adds a registered user to this blog when user vists the blog.
Author: Burt Adsit
Version: 0.42
Author URI: http://buddypress.org/developers/burtadsit/
License: GNU General Public License 3.0 (GPL) http://www.gnu.org/licenses/gpl.html
*/


// hook up to the settings menu for blog admins
function oci_jointhis_addmenu() {
  add_submenu_page( 'options-general.php', 'Community Blog', __('Community Blog','oci'), 10, 'oci_jointhis_settings', 'oci_jointhis_settings' );
}

function oci_get_current_role() {
	global $bp,$blog_id;
	
	// determine users role, if any, on this blog
	$roles = get_usermeta( $bp->loggedin_user->id, 'wp_' . $blog_id . '_capabilities' );
	
	// this seems to be the only way to do this
	if ( isset( $roles['subscriber'] ) ) 
		$user_role = 'subscriber'; 
	elseif	( isset( $roles['contributor'] ) )
		$user_role = 'contributor';
	elseif	( isset( $roles['author'] ) )
		$user_role = 'author';
	elseif ( isset( $roles['editor'] ) )
		$user_role = 'editor';
	elseif ( isset( $roles['administrator'] ) )
		$user_role = 'administrator';
	elseif ( is_site_admin() )
		$user_role = 'siteadmin';	
	else $user_role = 'norole';
	return $user_role;
}

function oci_is_an_upgrade($user_role, $default_role) {
	// determine if user role on blog is an upgrade or a downgrade based on 'communityness'
	if ($user_role == $default_role) return false;
	if ($user_role == 'subscriber' || $user_role == 'norole') return true;
	if ($user_role == 'contributor' && $default_role == 'author') return true;
  if ($user_role == 'contributor' || $user_role == 'author' && $default_role == 'editor') return true;
  return false; // nothing else could be an upgrade, editor admin siteadmin have permissions
}

// given whitespace delimited list of group slugs return an array of ids and err msg if any
function oci_get_ids_from_slugs($slug_text) {
  $slug_text = trim($slug_text);
  
	if (strlen($slug_text)) {
		$slugs = explode("\n",trim($slug_text));
		foreach($slugs as $slug) {
			if (!$ids[]=BP_Groups_Group::get_id_from_slug(trim($slug))) {
				$e = "Invalid group slug: " . $slug;
			}
		}
	}
	return array( 'ids' => $ids, 'err' => $e);
}

function oci_join_this_blog() {
	require_once( ABSPATH . WPINC . '/registration.php'); // is this accessable already? dunno
	global $bp, $wpdb, $username, $blog_id, $userdata;

	if (!is_user_logged_in()) // do nothing
		return;

	$oci_jointhis_options = get_option('OCIjointhis');
  
	if ((int)$oci_jointhis_options['oci_enable'] != true) // blog admin has this disabled
		return;
	
	$user_role = oci_get_current_role();
	if (!oci_is_an_upgrade($user_role,$oci_jointhis_options['oci_default_role'])) return;
	
	// if option oci_all_users is false then see if there are any groups specified
	if ((int)$oci_jointhis_options['oci_all_users'] != true) {
		if (!empty($oci_jointhis_options['oci_include_group_slugs'])) {
			
			// get the users bp groups
			
			// get_group_ids() does not return private and hidden groups now unless 
			// we are in member theme and we're not, have to fool bp by setting 
      // displayed_user = loggedin_user
			
      $tmp = $bp->displayed_user->id; $bp->displayed_user->id = $bp->loggedin_user->id;
      $user_group_ids =  BP_Groups_Member::get_group_ids( $bp->loggedin_user->id, false, false, true, true);
      $bp->displayed_user->id = $tmp;
			
			$community_group_ids = oci_get_ids_from_slugs((string)$oci_jointhis_options['oci_include_group_slugs']); 
			$ok=false;
			foreach($user_group_ids['groups'] as $g) {
				// if user is member of any of included groups continue
				if (in_array($g, $community_group_ids['ids'])) {$ok = true; break;} 
			}
		}
		if (!$ok) return; // not all users and not in included groups
	}

  if (!is_user_member_of_blog($bp->loggedin_user->id, $blog_id)){
    // add user to blog
    add_user_to_blog($blog_id, $bp->loggedin_user->id, $oci_jointhis_options['oci_default_role']);
  }
  else {
    // change existing user's role
    $user = new WP_User($bp->loggedin_user->id);
    $user->set_role($oci_jointhis_options['oci_default_role']);
    wp_cache_delete($bp->loggedin_user->id, 'users' );
  }
	// user_id, old role, new role
	do_action('oci_upgrade_user',$bp->loggedin_user->id, $user_role, $oci_jointhis_options['oci_default_role']);
}

// backend admin settings form 
function oci_jointhis_settings() {
	$oci_jointhis_default_settings = Array(
		'oci_default_role' => 'contributor',
		'oci_enable' => true, // turn the widget on and of for whatever reason, leaves it installed
		'oci_all_users' => true, // true to ignore oci_include_group_slugs and let everone become author
	  'oci_include_group_slugs' => '' // user entered list of slugs
   );

	$oci_jointhis_options=get_option('OCIjointhis');

	// set defaults
  if (empty($oci_jointhis_options)) {
      add_option('OCIjointhis', $oci_jointhis_default_settings);
			$oci_jointhis_options=$oci_jointhis_default_settings;
	}
				
	// we got here by the user hitting save, so save				
  if (isset($_POST['oci-jointhis-admin'])) {

  	$oci_jointhis_options["oci_default_role"] = $_POST['oci-default-role'];
  	$oci_jointhis_options["oci_enable"] = $_POST['oci-enable'];
  	$oci_jointhis_options["oci_all_users"] = $_POST['oci-all-users'];
  	$oci_jointhis_options["oci_include_group_slugs"] = $_POST['oci-include-group-slugs'];
		
		$group_ids = oci_get_ids_from_slugs($oci_jointhis_options["oci_include_group_slugs"]);
		if(!isset($group_ids['err'])) {
	  	update_option('OCIjointhis', $oci_jointhis_options);
	    echo '<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204); width: 300px; margin-left: 20px"><p>' . __('Settings saved','oci') . '</p></div>';
		} else {
			// problem
	    echo '<div class="error fade" id="message" style="background-color: rgb(255, 0, 0); width: 300px; margin-left: 20px"><p>' . __('Settings Not Saved: ','oci') . $group_ids['err'] . '</p></div>';
		}
  }
?>  
	<div class="jointhis">
		<h3><?php _e( 'Community Blog Settings', 'oci' ) ?></h3>

		<form action="<?php $_SERVER['PHP_SELF'] ?>" method="post" class="ocijointhisform" >
			<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php _e('Enable this plugin', 'oci') ?></th>
					<td>
						<input type="radio" name="oci-enable"<?php if ( (int)$oci_jointhis_options['oci_enable'] ) : ?> checked="checked"<?php endif; ?> id="oci-enable-yes" value="1" /> <?php _e('Yes','oci') ?> &nbsp;
						<input type="radio" name="oci-enable"<?php if ( !(int)$oci_jointhis_options['oci_enable'] ) : ?> checked="checked"<?php endif; ?> id="oci-enable-no" value="0" /> <?php _e('No','oci') ?>
						<p><?php _e( 'Turns off the plugin but leaves it activated.', 'oci' ) ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('Default User Role', 'oci') ?></th>
					<td>
						<input type="radio" name="oci-default-role"<?php if ( $oci_jointhis_options['oci_default_role'] == 'contributor' ) : ?> checked="checked"<?php endif; ?> id="oci-default-role-contributor" value="contributor" /> <?php _e('Contributor','oci') ?> &nbsp;
						<input type="radio" name="oci-default-role"<?php if ( $oci_jointhis_options['oci_default_role'] == 'author') : ?> checked="checked"<?php endif; ?> id="oci-default-role-author" value="author" /> <?php _e('Author','oci') ?>
						<input type="radio" name="oci-default-role"<?php if ( $oci_jointhis_options['oci_default_role'] == 'editor') : ?> checked="checked"<?php endif; ?> id="oci-default-role-editor" value="editor" /> <?php _e('Editor','oci') ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('Allow All Registered Users','oci') ?></th>
					<td>
						<input type="radio" name="oci-all-users"<?php if ( (int)$oci_jointhis_options['oci_all_users'] ) : ?> checked="checked"<?php endif; ?> id="oci-all-users-yes" value="1" />  <?php _e('Yes','oci') ?> &nbsp;
						<input type="radio" name="oci-all-users"<?php if ( !(int)$oci_jointhis_options['oci_all_users'] ) : ?> checked="checked"<?php endif; ?> id="oci-all-users-no" value="0" /> <?php _e('No','oci') ?>
						<p><?php _e( 'If set to Yes, then Groups list is ignored.', 'oci' ) ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('Groups to Allow Access','oci') ?></th>
					<td>
						<textarea name="oci-include-group-slugs" id="oci-include-group-slugs" rows="6" cols="40"><?php echo $oci_jointhis_options['oci_include_group_slugs'] ?></textarea>
						<p><?php _e( 'Enter a list of group slugs. One slug per line.', 'oci' ) ?></p>
					</td>
				</tr>
			</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="oci-jointhis-admin" id="oci-jointhis-admin" value="<?php _e( 'Save Settings', 'oci' ) ?>" />
			</p>
		</form>
	</div>

<?php	
}
add_action( 'admin_menu', 'oci_jointhis_addmenu' ); // backend admin menu
add_action('wp_head','oci_join_this_blog',99);
add_action('admin_head','oci_join_this_blog',99);
?>
