<?php
/*
Plugin Name: Relocate upload
Plugin URI: http://freakytrigger.co.uk/wordpress-setup/
Description: Moves uploads to special folders
Author: Alan Trewartha
Version: 0.13
Author URI: http://freakytrigger.co.uk/author/alan/
*/ 

// all paths are relative to the server document home
define('SERVER_DOC_ROOT', $GLOBALS['_SERVER']['DOCUMENT_ROOT']);


// Move folder request handled when called by GET AJAX
if (isset($_GET['ru_folder']))
{	// WP setup and function access
	define('WP_USE_THEMES', false);
	require_once(urldecode($_GET['abspath']).'/wp-load.php'); // save us looking for it, it's passed as a GET parameter
	check_admin_referer('ru_request_move');
	global $wpdb;


	// find default path
	$default_upload_path=str_replace(SERVER_DOC_ROOT,"",WP_CONTENT_DIR."/uploads/");
	if ( get_option( 'uploads_use_yearmonth_folders' ))
		$default_upload_path.="%YEAR%/%MONTH%/";


	// get folder options, with default added to the front
	if(!$ru_folders = get_option('relocate-upload-folders')) $ru_folders = array();
	array_unshift($ru_folders,array('name'=>"Default location", 'path' => $default_upload_path));


	// find attachment current info: PATH, DATE
	$id = $_GET['id'];
	$attachment_path=get_attached_file( $id, true);	//$attachment_path=get_post_meta($id,"_wp_attached_file",true);
	$attachment_record = & $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d LIMIT 1", $id));
	$attachment_date = $attachment_record->post_date;
	$attachment_guid = $attachment_record->guid;


	// find new path for attachment
	$folder=$_GET['ru_folder'];
	$new_path=SERVER_DOC_ROOT.$ru_folders[$folder]['path'].basename($attachment_path);
	$new_path=replace_month_year($new_path,$attachment_date);

	// attempt to move the file
	$result="FAIL";
	if (rename($attachment_path,$new_path))
	{	$result="WAIT";
		// move any thumbnails too
		$pm=get_post_meta($id,"_wp_attachment_metadata", true);
		if ($pm['sizes'])
			foreach($pm['sizes'] as $size=>$pm_size)
				rename(dirname($attachment_path)."/".$pm_size['file'],dirname($new_path)."/".$pm_size['file']);

		// update the metadata to reflect the new location
		$pm['file']=$new_path;
		update_post_meta($id,"_wp_attachment_metadata",$pm);
		update_post_meta($id, "_wp_attached_file", $new_path);
		
		// update the post/attachment GUID field
		$new_guid = str_replace( str_replace(SERVER_DOC_ROOT,"",$attachment_path), str_replace(SERVER_DOC_ROOT,"",$new_path), $attachment_guid );
		$attachment_record = & $wpdb->get_row($wpdb->prepare("UPDATE $wpdb->posts SET guid='%s' WHERE ID = %d ", $new_guid, $id));
		
		// let the client know all is well, and what the new guid is
		$result="DONE: $new_guid";
	}

	header("HTTP/1.0 200 OK");
	header('Content-type: text/plain;');
	echo "$result";
	exit;
}



// get the JS into the admin pages to run the AJAX request
// and to add the media library 'folder' filter
add_action('admin_head', 'relocate_upload_js');
function relocate_upload_js()
{	if (   strpos($_SERVER['REQUEST_URI'], "/wp-admin/media-upload.php")===false
		&& strpos($_SERVER['REQUEST_URI'], "/wp-admin/upload.php")===false
		&& strpos($_SERVER['REQUEST_URI'], "/wp-admin/media-new.php")===false
		&& strpos($_SERVER['REQUEST_URI'], "/wp-admin/media.php")===false )
		return;

	// first the basic ajax for the relocating folder
	?><script>	
	function ru_request_move($element)
	{	jQuery($element).attr({disabled: true});
		jQuery($element).siblings("span").html(' Moving...');
		jQuery.get(
			"<? echo WP_CONTENT_URL."/plugins/relocate-upload/relocate-upload.php"; ?>",
			{	ru_folder: $element.selectedIndex,
				       id: $element.getAttribute('media_id'),
				 _wpnonce: '<? echo wp_create_nonce("ru_request_move") ?>',
				  abspath: '<? echo ABSPATH ?>'
			},
			function(data)
			{	jQuery($element).attr({disabled: false});
				$m_item=jQuery($element).parents("div.media-item");

				if (data.substring(0,5)=='DONE:')
				{	jQuery($element).siblings("span").html('');
					$m_item.find("tr.url input").val('');
					$m_item.find("tr.url button:contains('File URL')").attr('title',data.substring(6));
					$m_item.find("tr.url button:contains('Audio Player')").attr('title','[audio:'+data.substring(6)+']');
				}
				else if (data=='')
					jQuery($element).siblings("span").html(' Error');
				else
					jQuery($element).siblings("span").html(' Failed');
			}
		);
	}
	<?

	
	// smuggle the menu into place with JS - no proper hook to get it in place
	// compile the HTML
	if(!$ru_folders = get_option('relocate-upload-folders')) $ru_folders = array();
	$i=0;
	$menu="<option value='' >All folders</option>";
	foreach($ru_folders as $ru_folder)
	{	$selected= ($_GET['ru_index']==="$i")?" selected":"";
		$menu.="<option value='".($i++)."'".$selected.">".$ru_folder['name']."</option>";
	}

	// get it in place
	?>	jQuery(document).ready(function() { jQuery("select[name='m']").after("<select name='ru_index'><? echo $menu ?></select>");})
		</script>
	<?
}



//
add_filter('posts_where', 'relocate_upload_library_filter');
function relocate_upload_library_filter($where)
{	if ($_GET['ru_index']==null)
		return $where;
	
	if (   strpos($_SERVER['REQUEST_URI'], "/wp-admin/media-upload.php")===false
		&& strpos($_SERVER['REQUEST_URI'], "/wp-admin/upload.php")===false )
		return $where;
		
	if ( strpos($_SERVER['REQUEST_URI'], "/wp-admin/media-upload.php") && ($_GET['tab']!="library"))
		return $where;
	
	if(!$ru_folders = get_option('relocate-upload-folders')) $ru_folders = array();
	$where.=" AND wp_posts.guid LIKE '%".($ru_folders[$_GET['ru_index']]['path'])."%'";
	return $where;
}



// hook in to the media library to make the extra control
add_filter('attachment_fields_to_edit', 'relocate_upload_menu', 3, 2);
function relocate_upload_menu($form_fields, $post)
{	
	// find default path
	$attachment_date = $post->post_date;
	$default_upload_path=WP_CONTENT_DIR."/uploads/";
	if ( get_option( 'uploads_use_yearmonth_folders' ))
		$default_upload_path.="%YEAR%/%MONTH%/";	


	// get folder options, with default location added to the front
	if(!$ru_folders = get_option('relocate-upload-folders')) $ru_folders = array();
	array_unshift($ru_folders,array('name'=>"Default location", 'path' => $default_upload_path));
	array_walk($ru_folders,'replace_month_year_cb', $attachment_date);


	// compile menu, set selected item where path matches attachments current path
	foreach($ru_folders as $ru_folder)
	{	$selected=(strpos(get_attached_file($post->ID),$ru_folder['path'])!==false)?" selected":"";
		$menu.="<option $selected>".$ru_folder['name']."</option>";
	}

	
	// this is how you add the menu
	$form_fields['ru_location'] = array(
		'label' => "Folder",
		'input' => 'html',
		'html'  => "<select media_id='$post->ID' onchange='ru_request_move(this);'>".$menu."</select><span></span>"
	);
	return $form_fields;

}



// put in the options page
add_action ('admin_menu', 'RU_admin_items');
function RU_admin_items() {	add_options_page("Relocate Upload", "Relocate Upload", 1,  __FILE__, "RU_admin_options"); }
function RU_admin_options()
{	
	// if there is post data from the form, read it in
	if($_POST['ru_folder_name'])
	{	// generate ru_folders array
		for($i=0; $i<count($_POST['ru_folder_name']); $i++)
			if ($_POST['ru_folder_name'][$i] !="")
				$ru_folders[]=array('name' => $_POST['ru_folder_name'][$i], 'path' => $_POST['ru_folder_path'][$i]);

		// save it as a WP option
		update_option('relocate-upload-folders', $ru_folders);
	}
	else
		// just read the WP option or use a blank array as default
		if(!$ru_folders = get_option('relocate-upload-folders')) $ru_folders = array();


	// prefix with default location
	$default_upload_path=str_replace(SERVER_DOC_ROOT,"",WP_CONTENT_DIR."/uploads");
	if ( get_option( 'uploads_use_yearmonth_folders' ))
		$default_upload_path.="/%YEAR%/%MONTH%/";
	array_unshift($ru_folders,array('name'=>"Default location", 'path' => $default_upload_path));

	// suffix with a blank row to add a new locations
	array_push($ru_folders,array());
		
	?>
	<style>
		li input {width:120px;}
		li input + input {width:400px}
		li.bad_folder input {border: 1px solid red;}
	</style>
	<div class="wrap">   
	<h2>Relocate Upload &ndash; Locations</h2>
	<form action="options-general.php?page=relocate-upload/relocate-upload.php" method="POST">
	<div>
		<ul>
	<?
		$disabled="disabled=true";
		foreach($ru_folders as $ru_folder)
		{	$bad_folder=($disabled=="" && $ru_folder['path'] && !is_writable(SERVER_DOC_ROOT.replace_month_year($ru_folder['path'],date("Y-m"))))?" class='bad_folder'":"";
			echo "<li $bad_folder >";
				echo "<input name='ru_folder_name[]' type='text' value='".$ru_folder['name']."' ".$disabled." />";
				echo "<input name='ru_folder_path[]' type='text' value='".$ru_folder['path']."' ".$disabled." />";
			echo"</li>";
			$disabled="";
		}

	?>
		</ul>
		<p>Use the last row to add a new location. To delete a location, blank its display name.</p>
		<p>Paths are relative to the blog root <b><? echo SERVER_DOC_ROOT; ?></b>. An unwritable folder is indicated by a red border.</p>
		<p class="submit"><input type="Submit" value="Update" /></p>
	</div>
	</form>
	<?
}



// callback to replace year and month tokens in the ru_folders array
function replace_month_year_cb(&$value, $key, $date)
{	$value['path']= replace_month_year($value['path'], $date);
}

// generic token replacement
function replace_month_year($path, $date)
{	$path=str_replace("%YEAR%", substr($date,0,4),$path);
	$path=str_replace("%MONTH%",substr($date,5,2),$path);
	return $path;
}


// thing is if you have an absolute path to your file, WP will give you an url like
// http://domain.co.uk/path_to/wp-content/uploads//home/useraccount/public_html/another_path_to/media/media_item.gif
// note the double /
//
// as SERVER_DOC_ROOT = /home/useraccount/public_html
//
// we do a search for (http://.*?/).*?/SERVER_DOC_ROOT/ and replace with \1


add_filter( 'wp_get_attachment_url', "wp_get_attachment_url_absolute_path_fix");
function wp_get_attachment_url_absolute_path_fix($url) {	return preg_replace('#(http://.*?/).*?/'.(SERVER_DOC_ROOT).'/#','\1',$url); }

?>