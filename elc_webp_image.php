<?php

// This is a PLUGIN TEMPLATE.

// Copy this file to a new name like abc_myplugin.php.  Edit the code, then
// run this file at the command line to produce a plugin for distribution:
// $ php abc_myplugin.php > abc_myplugin-0.1.txt

// Plugin name is optional.  If unset, it will be extracted from the current
// file name. Plugin names should start with a three letter prefix which is
// unique and reserved for each plugin author ('abc' is just an example).
// Uncomment and edit this line to override:
$plugin['name'] = 'elc_image_webp';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 0;

$plugin['version'] = '0.1';
$plugin['author'] = 'Ericki Chites';
$plugin['author_uri'] = '#';
$plugin['description'] = 'Plugin to convert images to webp';

// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
# $plugin['order'] = 5;

// Plugin 'type' defines where the plugin is loaded
// 0 = public       : only on the public side of the website (default)
// 1 = public+admin : on both the public and non-AJAX admin side
// 2 = library      : only when include_plugin() or require_plugin() is called
// 3 = admin        : only on the non-AJAX admin side
// 4 = admin+ajax   : only on admin side
// 5 = public+admin+ajax   : on both the public and admin side
$plugin['type'] = 4;

// Plugin 'flags' signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use.
//if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = PLUGIN_LIFECYCLE_NOTIFY;

// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
// Syntax:
// ## arbitrary comment
// #@event
// #@language ISO-LANGUAGE-CODE
// abc_string_name => Localized String
/*
$plugin['textpack'] = <<< EOT
#@admin
#@language en, en-gb, en-us
EOT;
*/
// End of textpack

if (!defined('txpinterface'))
	@include_once('elc_image_webp.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---
# --- END PLUGIN HELP ---
<?php
}

# --- BEGIN PLUGIN CODE ---
register_callback('elc_image_webp_set_prefs', 'plugin_lifecycle.elc_image_webp', 'installed');
register_callback('elc_image_webp_remove_prefs', 'plugin_lifecycle.elc_image_webp', 'deleted');

register_callback('elc_image_webp_upload', 'image_uploaded', 'image');
register_callback('elc_image_webp_delete_image', 'image_deleted', 'image');
register_callback('elc_image_webp_delete_thumb', 'image_deleted', 'image');
register_callback('elc_image_webp_delete_thumb', 'thumbnail_deleted');
register_callback('elc_image_webp_create_thumb_manually', 'image', 'thumbnail_create');
register_callback('elc_image_webp_create_thumb_insert', 'image', 'thumbnail_insert');


function elc_image_webp_set_prefs() 
{
  // Set default WebP quality preference
  set_pref('elc_image_webp_quality', 50, 'publish', position: 400);
}


function elc_image_webp_remove_prefs()
{
  remove_pref('elc_image_webp_quality');
}


function elc_image_webp_upload($evt, $stp, $id)
{
  // Fetch original image info from database
  $image = safe_row('*', 'txp_image', "id = '$id'", false);
  $original_image_path = IMPATH . $id . $image['ext'];

  if (!file_exists($original_image_path) || $image['ext'] == '.webp') {
    return;
  }

  $new_path = IMPATH . $id . '.webp';

  // Convert original image to WebP format
  $result = elc_image_webp_convert_to_webp($image['ext'], $original_image_path, $new_path);

  if(!$result) {
    return;
  }

  // Automatically create thumbnail if preferences are set
  if (get_pref('thumb_w') > 0 || get_pref('thumb_h') > 0){
    elc_image_webp_create_thumb($id);
  }
}


function elc_image_webp_convert_to_webp(string $original_image_ext, string $original_image_path, string $new_image_path): bool
{
  // Convert image to WebP based on original format
  switch ($original_image_ext) {
    case '.jpg':
    case '.jpeg':
        $image = imagecreatefromjpeg($original_image_path);
        break;
    case '.png':
        $image = imagecreatefrompng($original_image_path);
        break;
    default:
        return false; // Unsupported format
  }

  return imagewebp($image, $new_image_path, get_pref('elc_image_webp_quality'));
}


function elc_image_webp_delete_image($evt, $stp, $id)
{
  $image = safe_row('*', 'txp_image', "id = '$id'", false);

  if($image['ext'] == '.webp' || $image['ext'] == '.gif') {
    return;
  }

  $webp_image_path = IMPATH . $id . '.webp';

  if (file_exists($webp_image_path) && !unlink($webp_image_path)) {
    unlink($webp_image_path);
  }
}


function elc_image_webp_delete_thumb($evt, $stp, $id)
{
  $image = safe_row('*', 'txp_image', "id = '$id'", false);

  if($image['ext'] == '.webp' || $image['ext'] == '.gif') {
    return;
  }

  $webp_thumb_path = IMPATH . $id . 't.webp';

  if (file_exists($webp_thumb_path)) {
    unlink($webp_thumb_path);
  }
}


function elc_image_webp_create_thumb($id, $fromgps = false): void 
{
  $t = new txp_thumb($id);
  $t->crop = $fromgps ? (bool)gps('crop') : (bool)get_pref('thumb_crop');
  $t->hint = '0';
  $t->width = $fromgps ? (int)gps('width') : (int) get_pref('thumb_w');
  $t->height = $fromgps ? (int)gps('height') : (int) get_pref('thumb_h');
  $t->m_ext = '.webp';
  $t->write();
}


function elc_image_webp_create_thumb_manually() 
{
  if ((int)gps('width') > 0 || (int)gps('height') > 0){
    elc_image_webp_create_thumb((int)gps('id'), true);
  }
}


function elc_image_webp_create_thumb_insert()
{
  $original_image_path = IMPATH . (int)gps('id') . 't';

  $extensions = ['.png', '.jpeg', '.jpg', '.webp'];

  $found_image_path = '';
  $found_extension = '';

  foreach ($extensions as $extension) {
      $full_path = $original_image_path . $extension;

      if (file_exists($full_path)) {
          $found_image_path = $full_path;
          $found_extension = $extension;
          break;
      }
  }

  if ($found_image_path === '') {
      return;
  }

  $new_path = IMPATH . (int)gps('id') . 't.webp';

  if($found_extension != '.webp'){
    elc_image_webp_convert_to_webp($found_extension, $found_image_path, $new_path);
  }
}

# --- END PLUGIN CODE ---

?>
