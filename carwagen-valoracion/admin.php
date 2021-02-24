<?php

//ADMIN -----------------------------------------

add_action( 'admin_menu', 'carwagen_valoracion_plugin_menu' );
function carwagen_valoracion_plugin_menu() {
	add_options_page( __('Administración Valoración', 'carwagen-valoracion'), __('Valoración', 'carwagen-valoracion'), 'manage_options', 'carwagen-valoracion', 'carwagen_valoracion_page_settings');
}

function carwagen_valoracion_page_settings() { 
	//echo "<pre>"; print_r($_REQUEST); echo "</pre>";
	if(isset($_REQUEST['send']) && $_REQUEST['send'] != '') { 
		update_option('_carwagen_dat_customer_number', $_POST['_carwagen_dat_customer_number']);
		update_option('_carwagen_dat_user', $_POST['_carwagen_dat_user']);
		update_option('_carwagen_dat_password', $_POST['_carwagen_dat_password']);
		update_option('_carwagen_dat_signature', $_POST['_carwagen_dat_signature']);

		update_option('_carwagen_valoracion_thanks', $_POST['_carwagen_valoracion_thanks']);
		update_option('_carwagen_user_email_title', $_POST['_carwagen_user_email_title']);
		update_option('_carwagen_user_email_html', $_POST['_carwagen_user_email_html']);
		update_option('_carwagen_valoracion_admin_email', $_POST['_carwagen_valoracion_admin_email']);
		?><p style="border: 1px solid green; color: green; text-align: center;"><?php _e("Datos guardados correctamente.", 'carwagen-valoracion'); ?></p><?php
	} ?>
	<form method="post">
		<?php $settings = array( 'media_buttons' => true, 'quicktags' => true, 'textarea_rows' => 15 ); ?>
		<h1><?php _e("Configuración de la conexión con DATIBERICA", 'carwagen-valoracion'); ?></h1>

		<h2><?php _e("DAT CUSTOMER NUMBER", 'carwagen-valoracion'); ?>:</h2>
		<input type="text" name="_carwagen_dat_customer_number" value="<?php echo get_option("_carwagen_dat_customer_number"); ?>" style="width: 100%;"/><br/><br/>
		<h2><?php _e("DAT USER", 'carwagen-valoracion'); ?>:</h2>
		<input type="text" name="_carwagen_dat_user" value="<?php echo get_option("_carwagen_dat_user"); ?>" style="width: 100%;"/><br/><br/>
		<h2><?php _e("DAT PASSWORD", 'carwagen-valoracion'); ?>:</h2>
		<input type="text" name="_carwagen_dat_password" value="<?php echo get_option("_carwagen_dat_password"); ?>" style="width: 100%;"/><br/><br/>
		<h2><?php _e("DAT SIGNATURE", 'carwagen-valoracion'); ?>:</h2>
		<input type="text" name="_carwagen_dat_signature" value="<?php echo get_option("_carwagen_dat_signature"); ?>" style="width: 100%;"/><br/><br/>




		<h1><?php _e("Configuración del plugin", 'carwagen-valoracion'); ?></h1>
		<h2><?php _e("Mensaje de gracias", 'carwagen-valoracion'); ?>:</h2>
		<?php wp_editor( stripslashes(get_option("_carwagen_valoracion_thanks")), '_carwagen_valoracion_thanks', $settings ); ?><br/><br/>
		<h2><?php _e("Título email usuario", 'carwagen-valoracion'); ?>:</h2>
		<input type="text" name="_carwagen_user_email_title" value="<?php echo get_option("_carwagen_user_email_title"); ?>" style="width: 100%;"/><br/><br/>
		<h2><?php _e("Plantilla HTML email usuario", 'carwagen-valoracion'); ?>:</h2>
		<?php wp_editor( stripslashes(get_option("_carwagen_user_email_html")), '_carwagen_user_email_html', $settings ); ?><br/>
		<?php _e("Tags", 'carwagen-valoracion'); ?>: *|valoracion|* *|nombre|*<br/><br/>
		<h2><?php _e("Email de aviso", 'carwagen-valoracion'); ?>:</h2>
		<input type="text" name="_carwagen_valoracion_admin_email" value="<?php echo get_option("_carwagen_valoracion_admin_email"); ?>" /><br/><br/>
		<input type="submit" name="send" class="button button-primary" value="<?php _e("Guardar"); ?>" />
	</form>
	<?php
}