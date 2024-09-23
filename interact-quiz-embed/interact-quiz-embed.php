<?php
/*
Plugin Name: Interact Quiz Embed
Plugin URI: https://www.tryinteract.com
Description: Use this plugin to embed your Interact quiz into your Wordpress site.
Author: The Quiz Collective Inc.
Version: 3.1
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

	wp_enqueue_script('interact-embed-script');

	$valid_app_types = array('quiz', 'poll', 'giveaway');
	$valid_align_values = array('left', 'right', 'center');
	$valid_boolean_values = array('true', 'false');

	if(isset($atts['w']) && is_number($atts['w'])) { $width = $atts['w']; } else { $width = '800'; }
 	if(isset($atts['h']) && is_number($atts['h'])) { $height = $atts['h']; } else { $height = '800'; }
 	if(isset($atts['type']) && in_array($atts['type'], $valid_app_types)) { $type = $atts['type']; } else { $type = 'quiz'; }
 	if(isset($atts['no_cover']) && in_array($atts['no_cover'], $valid_boolean_values)) { $no_cover = $atts['no_cover']; } else { $no_cover = 'false'; }
 	if(isset($atts['mobile']) && in_array($atts['mobile'], $valid_boolean_values)) { $mobile = $atts['mobile']; } else { $mobile = 'true'; }
 	if(isset($atts['align']) && in_array($atts['align'], $valid_align_values)) { $align = $atts['align']; } else { $align = null; }
 	if(isset($atts['redirect']) && $atts['redirect'] === 'host') { $redirect = 'host'; } else { $redirect = 'false'; }

	$app_id = $atts['id'];
	$host = $type.'.tryinteract.com';
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
					var host = "'.esc_js($host).'";
					var no_cover = '.esc_js($no_cover).';
					var mobile = '.esc_js($mobile).';
					var redirect = "'.esc_js($redirect).'";
					var params = { "ref": ref, "appId": app_id, "width": w, "height": h, "async": true, "host": host, "auto_resize": true, "mobile":  mobile, "no_cover": no_cover };
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

function interact_option_page(){

	if(isset($_POST['interact_promotion_id'])) {
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

	?>
	<div class="wrap">
		<h1>Interact Quiz Embed Plugin</h1>
		<hr/>
		<h2>Embed your Quiz, Poll, or Giveaway with a Shortcode</h2>
		<p>This plugin generates a shortcode which embeds your Interact App into your WordPress content. <a href='https://en.support.wordpress.com/shortcodes/' target='_blank'>How do I use a shortcode?</a></p>
		<?php
		if(isset($_POST['app_url'])) {

			$app_url = sanitize_url($_POST['app_url']);
			$parts = explode('/', $app_url);

			$app_id = null;
			$app_type = null;
			$valid_app_types = array('quiz', 'poll', 'giveaway');

			if(count($parts) === 6) {
				if(in_array($parts[4], $valid_app_types) && isset($parts[5]) && strlen($parts[5]) === 24) {
					$app_id = $parts[5];
					$app_type = $parts[4];
				}
			}


			if(isset($app_id) && isset($app_type)) {
				echo '<h4>Copy &amp; Paste your shortcode into your Post:';

				$inner_html = '[interact id="'. $app_id .'" type="'. $app_type .'"';
				
				if(isset($_POST['interact_size_w']) && !empty($_POST['interact_size_w'])){
					$sanitized_width = sanitize_text_field($_POST['interact_size_w']);
					$inner_html .= ' w="'.$sanitized_width.'"';
				}
				if(isset($_POST['interact_size_h']) && !empty($_POST['interact_size_h'])){
					$sanitized_height = sanitize_text_field($_POST['interact_size_h']);
					$inner_html .= ' h="'.$sanitized_height.'"';
				}
				
				if(isset($_POST['interact_disable_cover'])){
					$inner_html .= ' no_cover="true"';
				}

				echo '<pre style="display:block;max-width:720px;background: #333;padding: 20px;border-radius: 4px;color: white;font-weight: 400;">'.esc_html($inner_html.']').'</pre>';
				echo '</h4>';
			} else {
				echo '<h4 style="color: red;">Invalid App URL...</h4>';
			}
		}
		?>
		<form action="" method="post" id="interact-embed-form">
			<table class="form-table">
				<tr>
					<th scope="row"><label for="app_id">Interact App URL</label></th>
					<td><input name="app_url" type="text" id="app_id" placeholder="https://www.tryinteract.com/share/app/ID" value="" class="regular-text" />
					<p>The URL above can be found in your dashboard under <br/><b>'Embed &amp; Share' &gt; 'Embed in your Website' &gt; 'WordPress'.</b></p>
					</td>
				</tr>
				<tr>
					<th scope="row">Embed Size</th>
					<td>
						<label for="interact_size_w">Width</label>
						<input name="interact_size_w" type="number" step="1" min="0" id="interact_size_w" value="" class="small-text" />
						<label for="interact_size_h">Height</label>
						<input name="interact_size_h" type="number" step="1" min="0" id="interact_size_h" value="" class="small-text" />
						<p class="description">Default size is 600x500px (optional)</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Cover Page</th>
					<td>
						<label for="interact_disable_cover">
							<input name="interact_disable_cover" id="interact_disable_cover" type="checkbox"/> Disable Cover Page
							<p class="description">Quiz will begin on the first question and skip the cover page (optional)</p>
						</label>
					</td>
				</tr>
			</table>

			<p><input type="submit" name="submit" value="Generate Shortcode" class="button button-primary"></p>
		</form>
		<br/>
		<br/>

		<hr/>
		<h2>Promote your Quiz with a Popup or Announcement Bar</h2>
		<form action="" method="post">
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
