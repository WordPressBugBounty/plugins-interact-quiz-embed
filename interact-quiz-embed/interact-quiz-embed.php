<?php
/*
Plugin Name: Interact Quiz & Form Embed
Plugin URI: https://www.tryinteract.com
Description: Use this plugin to embed your Interact quiz or form into your WordPress site.
Author: The Quiz Collective Inc.
Version: 3.3
Author URI: https://www.tryinteract.com

Copyright 2023 The Quiz Collective Inc.  (email: help@tryinteract.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
*
* Short Code Hook Legacy
*
*/

function is_number($value) {
	return ctype_digit(strval($value));
}

function interact_embed_sections() {
	return array(
		'quiz' => array(
			'title' => 'Embed your Quiz with a Shortcode',
			'noun' => 'quiz',
			'placeholder' => 'https://www.tryinteract.com/share/quiz/ID',
			'cover' => true,
		),
		'form' => array(
			'title' => 'Embed your Form with a Shortcode',
			'noun' => 'form',
			'placeholder' => 'https://form.tryinteract.com/f/ID',
			'help' => 'The URL can be found on the "Share Link" tab of the Share & Embed Dialog in your form dashboard. It looks like https://form.tryinteract.com/f/ID.',
			'cover' => false,
		),
	);
}

function interact_app_types() {
	return array_keys(interact_embed_sections());
}

function interact_allowed_value($atts, $key, $allowed, $default) {
	if (isset($atts[$key]) && in_array($atts[$key], $allowed, true)) {
		return $atts[$key];
	}

	return $default;
}

function interact_is_app_id($id, $length = null) {
	if (!is_string($id) || !preg_match('/^[A-Za-z0-9]+$/', $id)) {
		return false;
	}

	return $length === null || strlen($id) === $length;
}

function interact_share_url_with_scheme($url) {
	$url = trim($url);
	if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
		return $url;
	}
	if (strpos($url, '//') === 0) {
		return 'https:' . $url;
	}
	return 'https://' . $url;
}

function interact_app_from_share_url($url) {
	if (!is_string($url) || trim($url) === '') {
		return null;
	}

	$parsed = parse_url(interact_share_url_with_scheme($url));

	if (!is_array($parsed) || empty($parsed['host']) || empty($parsed['path'])) {
		return null;
	}

	$host = strtolower($parsed['host']);
	$segments = explode('/', trim($parsed['path'], '/'));
	$id = array_pop($segments);
	$form_host = $host === 'form.tryinteract.com' || $host === 'form.tryinteract.dev';

	if ($form_host && $segments === array('f') && interact_is_app_id($id, 8)) {
		return array('id' => $id, 'type' => 'form');
	}

	$quiz_host = $host === 'www.tryinteract.com' || $host === 'tryinteract.com';
	if ($quiz_host && $segments === array('share', 'quiz') && interact_is_app_id($id, 24)) {
		return array('id' => $id, 'type' => 'quiz');
	}

	return null;
}

function interact_quiz_embed($atts) {
	shortcode_atts(array('user'=>'', 'id'=>'', 'w'=>'600', 'h'=>'500'), $atts);

	if(isset($atts['w']) && is_number($atts['w']))
		$width = $atts['w'];
	else
		$width = '600';
	if(isset($atts['h']) && is_number($atts['h']))
		$height = $atts['h'];
	else
		$height = '500';

	if (isset($atts['user'])) {
		$username = $atts['user'];
		$id = $atts['id'];
		return '
			<link rel="stylesheet" type="text/css" href="https://www.tryinteract.com/css/interact.css">
			<iframe src="'.esc_url('https://quiz.tryinteract.com/#/'.$username.'/'.$id).'" class="interact-embed" width="'.esc_attr($width).'" height="'.esc_attr($height).'" frameborder="0"></iframe>
		';
	} else {
		$app_id = $atts['id'];
		return '<iframe src="'.esc_url('https://quiz.tryinteract.com/#/'.$app_id).'" class="interact-embed" width="'.esc_attr($width).'" height="'.esc_attr($height).'" frameborder="0" style="margin:0;max-width:100%;"></iframe>';
	}
}

add_shortcode('interact-quiz','interact_quiz_embed');


/*
*
* Short Code Hook 
*
*/

function interact_embed($atts) {
	shortcode_atts(array('id'=>'', 'type'=>'quiz', 'w'=>'800', 'h'=>'800', 'no_cover'=>'false'), $atts);

	if (!is_array($atts) || !isset($atts['id']) || !interact_is_app_id($atts['id'])) {
		return '';
	}

	wp_enqueue_script('interact-embed-script');

	$boolean_values = array('true', 'false');

	$width = (isset($atts['w']) && is_number($atts['w'])) ? $atts['w'] : '800';
	$height = (isset($atts['h']) && is_number($atts['h'])) ? $atts['h'] : '800';
	$type = interact_allowed_value($atts, 'type', interact_app_types(), 'quiz');
	$no_cover = interact_allowed_value($atts, 'no_cover', $boolean_values, 'false');
	$mobile = interact_allowed_value($atts, 'mobile', $boolean_values, 'true');
	$align = interact_allowed_value($atts, 'align', array('left', 'right', 'center'), null);
	$redirect = interact_allowed_value($atts, 'redirect', array('host'), 'false');
	$auto_resize = interact_allowed_value($atts, 'auto_resize', $boolean_values, 'true');

	$app_id = $atts['id'];
	$ref = $app_id . md5($app_id . rand());

	if($align) {
		$align = 'text-align:'.$align.';';
	}

	$container = '<div id="interact-'.esc_attr($ref).'" style="'.esc_attr($align).'"></div>';

	return '
		' . $container . '
		<script type="text/javascript">
			(function(){				
				window.addEventListener("load", function(){
					var app_id = "'.esc_js($app_id).'";
					var ref = "'.esc_js($ref).'";
					var w = "'.esc_js($width).'";
					var h = "'.esc_js($height).'";
					var no_cover = '.esc_js($no_cover).';
					var mobile = '.esc_js($mobile).';
					var redirect = "'.esc_js($redirect).'";
					var params = { "ref": ref, "appId": app_id, "width": w, "height": h, "async": true, "auto_resize": '.esc_js($auto_resize).', "mobile":  mobile, "no_cover": no_cover, "type": "'.esc_js($type).'" };
					if(redirect === "host") { 
						params.redirect_host = true;
					}
					window[ref] = new InteractApp(); 
					window[ref].initialize(params); 
					window[ref].display(); 
				});
			})(window);
		</script>
	';
}

add_shortcode('interact','interact_embed');

/*
*
* Promotion Script Hook inject into <head>
*
*/

function interact_scripts(){
	global $post;

	// promotion script
	if(get_option('interact_promotion_id') !== false) {
	  ?>
	  	<script type="text/javascript">
			(function(i,n,t,e,r,a,c){i['InteractPromotionObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=n.createElement(t),c=n.getElementsByTagName(t)[0];a.async=1;a.src=e;c.parentNode.insertBefore(a,c)})(window,document,'script','https://i.tryinteract.com/promotions/init.js','i_promo');
			i_promo('init', '<?php echo esc_js(get_option('interact_promotion_id')) ?>');
		</script>
		<?php
	}
}

add_action( 'wp_head', 'interact_scripts' );



/*
*
* Register the Script
*
*/

function interact_enqueue_scripts(){
	wp_register_script('interact-embed-script', plugins_url('interact-embed.js', __FILE__), null, '1.0', true);
}

add_action( 'wp_enqueue_scripts', 'interact_enqueue_scripts' );  



/*
*
* Options Page for Plugin
*
*/

function interact_shortcode_text($app, $options) {
	$shortcode = '[interact id="' . $app['id'] . '" type="' . $app['type'] . '"';

	if (isset($options['width']) && $options['width'] !== '') {
		$shortcode .= ' w="' . $options['width'] . '"';
	}
	if (isset($options['height']) && $options['height'] !== '') {
		$shortcode .= ' h="' . $options['height'] . '"';
	}
	$section = interact_embed_sections()[$app['type']];
	if (!empty($section['cover']) && !empty($options['no_cover'])) {
		$shortcode .= ' no_cover="true"';
	}

	$auto_resize = !empty($options['auto_resize']) ? 'true' : 'false';
	$shortcode .= ' auto_resize="' . $auto_resize . '"]';

	return $shortcode;
}

function interact_submitted_shortcode() {
	if (!isset($_POST['interact_shortcode_kind'])) {
		return null;
	}

	$kind = $_POST['interact_shortcode_kind'];
	if (!in_array($kind, interact_app_types(), true)) {
		return null;
	}

	$nonce_key = 'interact_shortcode_nonce_' . $kind;
	$nonce = isset($_POST[$nonce_key]) ? $_POST[$nonce_key] : '';
	if (!wp_verify_nonce($nonce, 'interact_shortcode_' . $kind)) {
		return null;
	}

	$app_url = isset($_POST['app_url']) ? sanitize_url($_POST['app_url']) : '';
	$app = interact_app_from_share_url($app_url);
	if (!$app || $app['type'] !== $kind) {
		return array('kind' => $kind, 'error' => true);
	}

	$width = isset($_POST['interact_size_w']) ? sanitize_text_field($_POST['interact_size_w']) : '';
	$height = isset($_POST['interact_size_h']) ? sanitize_text_field($_POST['interact_size_h']) : '';

	return array(
		'kind' => $kind,
		'error' => false,
		'shortcode' => interact_shortcode_text($app, array(
			'width' => $width,
			'height' => $height,
			'no_cover' => $kind === 'quiz' && isset($_POST['interact_disable_cover']),
			'auto_resize' => isset($_POST['interact_auto_resize']),
		)),
	);
}

function interact_shortcode_section($kind, $result) {
	$sections = interact_embed_sections();
	$section = $sections[$kind];
	$noun = $section['noun'];
	$help = isset($section['help'])
		? $section['help']
		: 'The URL can be found on the "Share Link" tab of the Share & Embed Dialog in your ' . $noun . ' dashboard.';

	$html = '<hr/><h2>' . esc_html($section['title']) . '</h2>';
	$html .= '<p>This plugin generates a shortcode which embeds your Interact ' . $noun . ' into your WordPress content. <a href="https://en.support.wordpress.com/shortcodes/" target="_blank">How do I use a shortcode?</a></p>';

	if (is_array($result) && isset($result['kind']) && $result['kind'] === $kind) {
		if (!empty($result['error'])) {
			$html .= '<h4 style="color: red;">Invalid App URL...</h4>';
		} else {
			$html .= '<h4>Copy &amp; Paste your shortcode into your Post:';
			$html .= '<pre style="display:block;max-width:720px;background: #333;padding: 20px;border-radius: 4px;color: white;font-weight: 400;">' . esc_html($result['shortcode']) . '</pre>';
			$html .= '</h4>';
		}
	}

	$html .= '<form action="" method="post" id="interact-' . $noun . '-embed-form">';
	$html .= '<input type="hidden" name="interact_shortcode_kind" value="' . esc_attr($kind) . '" />';
	$html .= wp_nonce_field('interact_shortcode_' . $kind, 'interact_shortcode_nonce_' . $kind, true, false);
	$html .= '<table class="form-table">';
	$html .= '<tr><th scope="row"><label for="' . $noun . '_app_url">Interact App URL</label></th><td>';
	$html .= '<input name="app_url" type="text" id="' . $noun . '_app_url" placeholder="' . esc_attr($section['placeholder']) . '" value="" class="regular-text" />';
	$html .= '<p>' . esc_html($help) . '</p></td></tr>';
	$html .= '<tr><th scope="row">Embed Size</th><td>';
	$html .= '<label for="' . $noun . '_size_w">Width</label> ';
	$html .= '<input name="interact_size_w" type="number" step="1" min="0" id="' . $noun . '_size_w" value="" class="small-text" /> ';
	$html .= '<label for="' . $noun . '_size_h">Height</label> ';
	$html .= '<input name="interact_size_h" type="number" step="1" min="0" id="' . $noun . '_size_h" value="" class="small-text" />';
	$html .= '<p class="description">Default size is 800x800px (optional)</p></td></tr>';

	if ($section['cover']) {
		$html .= '<tr><th scope="row">Cover Page</th><td><label for="' . $noun . '_disable_cover">';
		$html .= '<input name="interact_disable_cover" id="' . $noun . '_disable_cover" type="checkbox"/> Disable Cover Page';
		$html .= '<p class="description">Quiz will begin on the first question and skip the cover page (optional)</p>';
		$html .= '</label></td></tr>';
	}

	$html .= '<tr><th scope="row">Auto-Resize</th><td><label for="' . $noun . '_auto_resize">';
	$html .= '<input name="interact_auto_resize" id="' . $noun . '_auto_resize" type="checkbox" checked="checked" /> Auto-Resize';
	$html .= '</label></td></tr></table>';
	$html .= '<p><input type="submit" name="submit" value="Generate Shortcode" class="button button-primary"></p></form>';

	return $html;
}

function interact_option_page(){

	if(isset($_POST['interact_promotion_id']) && wp_verify_nonce($_POST['interact_promotion_nonce'], 'interact_promotion_action')) {
		$updatedPromoId = false;
		$newId = sanitize_text_field($_POST['interact_promotion_id']);
		
		if($newId === '') {
			$updatedPromoId = true;
			delete_option('interact_promotion_id');
		} 
		
		if(strlen($newId) > 7) {
			$updatedPromoId = true;
			update_option('interact_promotion_id', $newId);
		}
	}

	$id = get_option('interact_promotion_id');
	$generated = interact_submitted_shortcode();

	?>
	<div class="wrap">
		<h1>Interact Quiz &amp; Form Embed</h1>
		<?php
		echo interact_shortcode_section('quiz', $generated);
		echo interact_shortcode_section('form', $generated);
		?>
		<br/>
		<br/>

		<hr/>
		<h2>Promote your Quiz with a Popup or Announcement Bar</h2>
		<form action="" method="post">
			<?php wp_nonce_field('interact_promotion_action', 'interact_promotion_nonce'); ?>
			<p>Enter your <b>Promotion ID</b> which can be found in your dashboard under 'Embed &amp; Share'</p>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="app_id">Promotion ID</label></th>
					<td>
						<input name="interact_promotion_id" type="text" id="interact_promotion_id" class="code" value="<?php if($id){ echo esc_attr($id); } ?>" />
					</td>
				</tr>
			</table>

			<?php if($id !== false): ?>
				<p>Promotions are now <b>configured</b> and can be configured in your dashboard under 'Embed &amp; Share'.</p>
			<?php endif; ?>
			<?php if(isset($updatedPromoId)): ?><p><b>Success:</b> Promotion ID was updated...</p><?php endif;?>	
			<?php if(isset($newId) && !isset($updatedPromoId)): ?><p><b>Warning:</b> Promotion ID was not updated...</p><?php endif;?>	
			<p><input type="submit" name="submit" value="<?php if($id === false): ?>Set<?php else: ?>Update<?php endif;?> Promotion ID" class="button button-primary"></p>
		</form>
	</div>

	<?php
}

function interact_plugin_menu(){
	add_options_page('Interact Embed Shortcode Generator','Interact','manage_options','interact_plugin','interact_option_page');
}

add_action('admin_menu','interact_plugin_menu');
