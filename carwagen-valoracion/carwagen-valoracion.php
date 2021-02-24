<?php

/**
 * Plugin Name: CarwagenValoracion
 * Plugin URI:  https://www.enutt.net/
 * Description: Shortcode para generar un formulario de valoración de coches para Carwagen
 * Version:     1.0
 * Author:      Enutt S.L.
 * Author URI:  https://www.enutt.net/
 * License:     GNU General Public License v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: carwagen-valoracion
 *
 * PHP 7.3
 * WordPress 5.5.3
 */

//Variables globales
define('DAT_CUSTOMER_NUMBER', get_option("_carwagen_dat_customer_number")); 
define('DAT_USER', get_option("_carwagen_dat_user")); 
define('DAT_SIGNATURE', get_option("_carwagen_dat_signature")); 
define('DAT_PASSWORD',  get_option("_carwagen_dat_password")); 
define('DAT_GET_SELECTION_URL', 'http://www.datiberica.es/services/SelectVehicleFinal/');
define('DAT_GET_EVALUATION_URL', 'https://www.datgroup.com/FinanceLine/soap/Evaluation/'); 

$xml_send_get_selection = '<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sel="http://www.datiberica.es/services/SelectVehicleFinal">
   <soapenv:Header/>
   <soapenv:Body>
      <sel:getSelection soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
         <sel:request xsi:type="sel:selectionRequest" xmlns:sel="'.DAT_GET_SELECTION_URL.'">
            <datCustomerNumber xsi:type="xsd:string">'.DAT_CUSTOMER_NUMBER.'</datCustomerNumber>
            <userName xsi:type="xsd:string">'.DAT_USER.'</userName>
            <password xsi:type="xsd:string">'.DAT_PASSWORD.'</password>
            <vehicleType xsi:type="xsd:int">*|vehicleType|*</vehicleType>
            <manufacturer xsi:type="xsd:int">*|manufacturer|*</manufacturer>
            <baseModel xsi:type="xsd:string">*|baseModel|*</baseModel>
            <subModel xsi:type="xsd:string">*|subModel|*</subModel>
            <year xsi:type="xsd:int">*|year|*</year>
            <fuel xsi:type="xsd:string">*|fuel|*</fuel>
            <doors xsi:type="xsd:int">*|doors|*</doors>
            <gear xsi:type="xsd:int">*|gear|*</gear>
            <power xsi:type="xsd:int">*|power|*</power>
            <cylinder xsi:type="xsd:int">*|cylinder|*</cylinder>
            <line xsi:type="xsd:int">*|line|*</line>
            <container xsi:type="xsd:string">*|container|*</container>
            <datecode xsi:type="xsd:string"></datecode>
            <isRepair xsi:type="xsd:int">0</isRepair>
            <registrationDate xsi:type="xsd:date">*|registrationDate|*</registrationDate>
         </sel:request>
      </sel:getSelection>
   </soapenv:Body>
</soapenv:Envelope>';

$xml_send_get_evaluation = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:eval="http://sphinx.dat.de/services/Evaluation">
   <soapenv:Header/>
   <soapenv:Body>
      <eval:getVehicleEvaluation>
         <request>
            <locale country="ES" datCountryIndicator="ES" language="ES"/>
            <restriction>APPRAISAL</restriction>
            <datECode>*|datecode|*</datECode>
            <container>*|container|*</container>
            <constructionTime>*|constructionTime|*</constructionTime>
            <coverage>COMPLETE</coverage>
            <save>true</save>
            <mileage>*|kilometer|*</mileage>
            <registrationDate>*|registrationDate|*</registrationDate>
         </request>
      </eval:getVehicleEvaluation>
   </soapenv:Body>
</soapenv:Envelope>';

//Cargamos las funciones que crean las páginas en el WP-ADMIN
require_once(dirname(__FILE__)."/admin.php");

//Shortcode ------------------
function carwagenValoracionShortcode($params = array(), $content = null) {
  global $post;
  ob_start(); 

  if (isset($_POST['client-appraise']) && $_POST['client-appraise'] != '') {

    $email_admin = get_option("_carwagen_valoracion_admin_email");
    $email_user = strip_tags($_REQUEST['client-email']);
    $phone_user = strip_tags($_REQUEST['client-phone']);
    $name_user = strip_tags($_REQUEST['client-name']);

    $params = array();
    $params['registrationDate'] = strip_tags($_REQUEST['registrationdate']);
    $params['datecode'] = substr(strip_tags($_REQUEST['datecode']), 0, 15); 
    $params['container'] = substr(strip_tags($_REQUEST['datecode']), 15, 5); 
    $params['constructionTime'] = strip_tags($_REQUEST['AbBZ']); 
    $params['kilometer'] = strip_tags($_REQUEST['kilometer']); 

    //Obtenemos la valoración
    $api_request = apiCallGetEvaluationDataIberica ($params);
    $vehiculo = implode(" ", $api_request['vehiculo']);
    $step = $api_request['valoracion'] * 0.10; //+- 5% del precio original
    $max_valoracion = number_format((ceil(($api_request['valoracion'] + $step) / 100) * 100), 0, ",", ".")."€";
    $min_valoracion = number_format((floor(($api_request['valoracion'] - $step) / 100) * 100), 0, ",", ".")."€";
    $valoracion = number_format($api_request['valoracion'], 0, ",", ".")."€";
    $valoracion_text = sprintf(__("Desde %s hasta %s", 'carwagen-valoracion'), $min_valoracion, $max_valoracion). " (OCULTAR => ".number_format($api_request['valoracion'], 0, ",", ".")."€)";

    //Sacamos el mensaje de gracias
    echo str_replace("*|valoracion|*", $valoracion_text, get_option("_carwagen_valoracion_thanks"));    
    
    //Madamos el email al usuario
    if(is_email($email_user)) {
      $template = get_option("_carwagen_user_email_html");
      $template = str_replace("*|valoracion|*", $valoracion_text, $template);
      $template = str_replace("*|nombre|*", $name_user, $template);
      $headers = array('Content-Type: text/html; charset=UTF-8');
      wp_mail($email_user, get_option("_carwagen_user_email_title"), $template, $headers);
    }

    //Mandamos email al admin
    $template = sprintf(__("<b>Nombre:</b> %s<br/><b>Email:</b> %s<br/><b>Teléfono:</b> %s<br/><b>Valoración:</b> %s<br/><b>Kilometraje:</b> %s<br/><b>Código de coche:</b> %s<br/><b>Coche:</b> %s<br/><br/>---<br/><br/>", 'carwagen-valoracion'), $name_user, $email_admin, $phone_user, $valoracion." (".$valoracion_text.")", $params['kilometer'],  $params['datecode'], $vehiculo);
    $template .= sprintf(__("Puedes descargar todo los leads <a href='%s'>aquí</a>.", 'carwagen-valoracion'), plugin_dir_url(__FILE__)."csv/leads.csv");
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($email_admin, __("Nueva valoración de vehículo", 'carwagen-valoracion'), $template, $headers);

    //Guardamos log
    $f = fopen(dirname(__FILE__)."/csv/leads.csv", "a+");
    $line = date("Y-m-d H:i:s").',"'.$name_user.'","'.$email_user.'","'.$phone_user.'","'.$params['registrationDate'].'","'.$valoracion.'","'. $params['kilometer'].'","'. $params['datecode'].'","'. $vehiculo.'"'."\n";
    fwrite ($f, $line);
    fclose($f);

    $html = ob_get_clean(); 
    return $html;
  }

  $response = apiCallGetSelectionDataIberica (); ?>
  <div id="selector-div">
    <div class="shadow"></div>
    <div class="vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><?php _e("Selecciona un tipo de vehículo", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="vehicleType">
      <option value=""><?php _e("Elige una opción", 'carwagen-valoracion'); ?></option>
      <?php foreach ($response['vehicleType'] as $option) { ?>
        <option value="<?php echo $option['key']; ?>"><?php echo $option['value']; ?></option>
      <?php } ?>
    </select>
    
    <div class="manufacturer hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><i class="demo-icon icon-garantia"></i> <?php _e("Selecciona una marca", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="manufacturer" class="hiddenselect">
    </select>

    <div class="registrationDate hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><i class="demo-icon icon-calendario"></i> <?php _e("Fecha de matriculación", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="registrationDate" class="hiddenselect">
    </select>
    <select name="registrationDateMonth" class="hiddenselect">
    </select>
    
    
    <div class="baseModel hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><?php _e("Selecciona un modelo", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="baseModel" class="hiddenselect">
    </select>
    
    
    <div class="fuel hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><i class="demo-icon icon-fuel"></i> <?php _e("Selecciona un combustible", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="fuel" class="hiddenselect">
    </select>
    
    
    <div class="doors hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><i class="demo-icon icon-puerta"></i> <?php _e("Selecciona un numero de puertas", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="doors" class="hiddenselect">
    </select>
    
    
    <div class="gear hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><i class="demo-icon icon-cambio"></i> <?php _e("Selecciona una transmisión", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="gear" class="hiddenselect">
    </select>
    

    <div class="power hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><i class="demo-icon icon-consumo"></i></i> <?php _e("Selecciona una potencia", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="power" class="hiddenselect">
    </select>
    

    <div class="cylinder hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><i class="demo-icon icon-motor-cv"></i> <?php _e("Selecciona una cilindrada", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="cylinder" class="hiddenselect">
    </select>
    

    <div class="subModel hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><i class="demo-icon icon-color"></i> <?php _e("Selecciona un submodelo", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="subModel" class="hiddenselect">
    </select>
    
    
    <div class="year hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><?php _e("Selecciona un año", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="year" class="hiddenselect">
    </select>
    
    <div class="line hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><?php _e("Selecciona una LINE", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="line" class="hidden">
    </select>
    

    <div class="container hiddentitle vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><?php _e("Selecciona una CONTAINER", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
    <select name="container" class="hiddenselect">
    </select>
  </div>
  <div id="form-div">
    <form method="POST" action="<?php echo get_the_permalink(); ?>">
      <input type="hidden" name="datecode" value="" />
      <input type="hidden" name="AbBZ" value="" />
      <input type="hidden" name="BisBZ" value="" />
      <input type="hidden" name="registrationdate" value="" />
      <div class="vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><i class="demo-icon icon-kms" style="font-size: 17px; margin-right: 3px;"></i> <?php _e("Selecciona kilometraje", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
      <select name="kilometer">
        <option value="5000"><?php _e("Hasta 5.000 km", 'carwagen-valoracion'); ?></option>
        <option value="10000"><?php _e("Hasta 10.000 km", 'carwagen-valoracion'); ?></option>
        <option value="15000"><?php _e("Hasta 15.000 km", 'carwagen-valoracion'); ?></option>
        <option value="20000"><?php _e("Hasta 20.000 km", 'carwagen-valoracion'); ?></option>
        <option value="30000"><?php _e("Hasta 30.000 km", 'carwagen-valoracion'); ?></option>
        <option value="40000"><?php _e("Hasta 40.000 km", 'carwagen-valoracion'); ?></option>
        <option value="50000"><?php _e("Hasta 50.000 km", 'carwagen-valoracion'); ?></option>
        <option value="75000"><?php _e("Hasta 75.000 km", 'carwagen-valoracion'); ?></option>
        <option value="100000"><?php _e("Hasta 100.000 km", 'carwagen-valoracion'); ?></option>
        <option value="125000"><?php _e("Hasta 125.000 km", 'carwagen-valoracion'); ?></option>
        <option value="150000"><?php _e("Hasta 150.000 km", 'carwagen-valoracion'); ?></option>
        <option value="175000"><?php _e("Hasta 175.000 km", 'carwagen-valoracion'); ?></option>
        <option value="200000"><?php _e("Hasta 200.000 km", 'carwagen-valoracion'); ?></option>
        <option value="250000"><?php _e("Hasta 250.000 km", 'carwagen-valoracion'); ?></option>
        <option value="300000"><?php _e("Hasta 300.000 km", 'carwagen-valoracion'); ?></option>
        <option value="350000"><?php _e("Hasta 350.000 km", 'carwagen-valoracion'); ?></option>
        <option value="400000"><?php _e("Hasta 400.000 km", 'carwagen-valoracion'); ?></option>
        <option value="450000"><?php _e("Más de 400.000 km", 'carwagen-valoracion'); ?></option>
      </select>
      <div class="vc_separator wpb_content_element vc_separator_align_left vc_sep_width_100 vc_sep_double vc_sep_border_width_2 vc_sep_pos_align_center vc_sep_color_grey titulares-home vc_separator-has-text"><span class="vc_sep_holder vc_sep_holder_l"><span class="vc_sep_line"></span></span><h4><?php _e("Datos personales", 'carwagen-valoracion'); ?></h4><span class="vc_sep_holder vc_sep_holder_r"><span class="vc_sep_line"></span></span></div>
      <input type="text" name="client-name" required value="" placeholder="<?php _e('Nombre y apellido', 'carwagen-valoracion'); ?> *" /><br/>
      <input type="email" name="client-email" required value="" placeholder="<?php _e('Email', 'carwagen-valoracion'); ?> *" /> <br/>
      <input type="text" name="client-phone" required value="" placeholder="<?php _e('Teléfono', 'carwagen-valoracion'); ?> *" /><br/>
      <label><input type="checkbox" name="client-legal-advise" required value="" /> <?php _e("He leído y acepto la <a href='/aviso-legal/'>política de protección de datos</a>.", "carwagen-valoracion"); ?></label>
      <input type="submit" name="client-appraise" value="<?php _e('Tasar ahora', 'carwagen-valoracion'); ?>" />
    </form>
  </div>
  <style>
    .hiddenselect, .hiddentitle, #form-div {
      display: none;
    }
    #selector-div, #form-div {
      padding: 30px 30px 30px 30px;
      position: relative;
      background-color: #cecece;
    }
    
    #form-div {
       padding: 1px 30px 30px 30px;
       margin-top: -33px
    }
    #selector-div.loading .shadow {
      background: #21212733 url('<?php echo plugin_dir_url(__FILE__); ?>assets/img/ajax-loader.gif') center center no-repeat;
      display: block;
      height: 100%;
      left: 0px;
      min-height: 86px;
      position: absolute;
      top: 0px;
      width: 100%;
    }
    
    #selector-div .vc_separator,
    #form-div .vc_separator {
    	margin-top: 35px;
    	margin-bottom: 20px;
    }
    
    #selector-div select,
    #form-div select,
    #form-div input[type=text],
    #form-div input[type=email] {
	width: 100%;
    }
    
    #selector-div select[name=registrationDate],
    #selector-div select[name=registrationDateMonth] { 
        width: 49%;
    }
    
    #form-div label {
    	display: block;
    	font-size: 12px;
    	line-height: 12px;
    	margin: 10px 0px;
    }
    
    i.demo-icon:before {
    	color: #0a0a0a;
    }
    
    #form-div input[name=client-appraise] { 
        width: 100%;
    	font-size: 30px;
    	padding: 10px;
    	margin-top: 20px;
    }
  </style>
  <script>
    jQuery("#selector-div select").on('change', function() {
      jQuery("#form-div").fadeOut();
      var params = new Array();
      var select = jQuery(this).attr("name");
      var control = 0
      jQuery("#selector-div select").each(function() { 
        var current_select = jQuery(this).attr("name");
        if(control == 1) {
          jQuery(this).empty();
          jQuery(this).addClass("hiddenselect");
          jQuery("h5."+current_select).addClass("hiddentitle");
        }
        params.push(current_select + ":" + this.value);
        if(current_select == select) control = 1;
      });

      //Hacemos una llamada con los datos
      jQuery.ajax({
        type: "GET",
        url: "<?php echo admin_url('admin-ajax.php'); ?>",
        dataType: 'json',
        data: (
          { 
            action: 'carwagen_valoracion', 
            selects: params.join("|")
          }
        ),
        beforeSend:  function() {
          jQuery("#selector-div").addClass("loading");
        },
        complete:  function() {
          jQuery("#selector-div").removeClass("loading");
        },
        success: function(data){
          console.log(data);
          if (data['vehicle']) {
            jQuery("#form-div input[name=datecode]").val(data['vehicle']['datecode']);
            jQuery("#form-div input[name=AbBZ]").val(data['vehicle']['AbBZ']);
            jQuery("#form-div input[name=BisBZ]").val(data['vehicle']['BisBZ']);
            jQuery("#form-div input[name=registrationdate]").val(jQuery("#selector-div select[name=registrationDate]").val()+"-"+jQuery("#selector-div select[name=registrationDateMonth]").val()+"-15");
            jQuery("#form-div").fadeIn();
          } else {
            jQuery("#selector-div select").each(function() {
              var current = jQuery(this).attr("name");
              if(data[current]) {
                jQuery(this).empty();
                jQuery("select[name="+current+"]").append(new Option("Elige una opción", ""));
                jQuery("select[name="+current+"]").removeClass("hiddenselect");
                jQuery("."+current).removeClass("hiddentitle");
                jQuery.each(data[current], function() {
                  jQuery("select[name="+current+"]").append(new Option(this.value, this.key));
                });
                return false;
              }
            });
          }
        },
        error: function(data) {
          console.log("Error!"); //TODO: mensaje de error
          return false;
        }
      });
    });
  </script>
  <?php $html = ob_get_clean(); 
  return $html;
}
add_shortcode('carwagen-valoracion', 'carwagenValoracionShortcode');

//AJAX ----------------------
function carwagenValoracionAjax() {
  foreach(explode("|", $_REQUEST['selects']) as $item) {
    $temp = explode(":", $item);
    $data[$temp[0]] = $temp[1];
  }
  $response = apiCallGetSelectionDataIberica ($data);
  echo json_encode($response);
  wp_die();
}

add_action('wp_ajax_carwagen_valoracion', 'carwagenValoracionAjax');
add_action('wp_ajax_nopriv_carwagen_valoracion', 'carwagenValoracionAjax');

//Lib -----------------------
function apiCallGetSelectionDataIberica ($params = array( "vehicleType" => '', 'manufacturer' => '', 'baseModel' => '', 'subModel' => '', 'year' => '', 'fuel' => '', 'doors' => '', 'gear' => '', 'power' => '', 'cylinder' => '', 'line' => '', 'container' => '', 'datecode' => '' )) {
  global $post, $xml_send_get_selection;

  if (isset($params['registrationDate']) && $params['registrationDate'] != '') {
    $years= array (
      "key" => $params['registrationDate'],
      "value" =>  $params['registrationDate']
    );
  } else {
    $years = array();
    for ($i = date("Y"); $i >= (date("Y") - 30); $i--) {
      $years[] = array ("key" => $i, "value" => $i);
    }
  }

  if (isset($params['registrationDateMonth']) && $params['registrationDateMonth'] != '') {
    $months= array (
      "key" => $params['registrationDateMonth'],
      "value" =>  $params['registrationDateMonth']
    );
  } else {
    $months = array();
    $months[] = array ("key" => "01", "value" => __("enero", 'carwagen-valoracion'));
    $months[] = array ("key" => "02", "value" => __("febrero", 'carwagen-valoracion'));
    $months[] = array ("key" => "03", "value" => __("marzo", 'carwagen-valoracion'));
    $months[] = array ("key" => "04", "value" => __("abríl", 'carwagen-valoracion'));
    $months[] = array ("key" => "05", "value" => __("mayo", 'carwagen-valoracion'));
    $months[] = array ("key" => "06", "value" => __("junio", 'carwagen-valoracion'));
    $months[] = array ("key" => "07", "value" => __("julio", 'carwagen-valoracion'));
    $months[] = array ("key" => "08", "value" => __("agosto", 'carwagen-valoracion'));
    $months[] = array ("key" => "09", "value" => __("septiembre", 'carwagen-valoracion'));
    $months[] = array ("key" => "10", "value" => __("octubre", 'carwagen-valoracion'));
    $months[] = array ("key" => "11", "value" => __("noviembre", 'carwagen-valoracion'));
    $months[] = array ("key" => "12", "value" => __("diciembre", 'carwagen-valoracion'));
    $months[] = array ("key" => "06", "value" => __("No lo sé exactamente", 'carwagen-valoracion'));
  }

  if (isset($params['registrationDate']) && $params['registrationDate'] != '' && isset($params['registrationDateMonth']) && $params['registrationDateMonth'] != '') {
    $xml_send_get_selection = str_replace("*|registrationDate|*", $params['registrationDate']."-".$params['registrationDateMonth']."-01", $xml_send_get_selection);
  } else if (isset($params['registrationDate']) && $params['registrationDate'] != '') {
    $xml_send_get_selection = str_replace("*|registrationDate|*", $params['registrationDate']."-06-01", $xml_send_get_selection);
  }

  foreach ($params as $label => $value) {
    $xml_send_get_selection = str_replace("*|".$label."|*", $value, $xml_send_get_selection);
  }

  $headers = array(
	 "Content-type: text/xml;charset=\"utf-8\"",
	 "Accept: text/xml",
	 "Cache-Control: no-cache",
	 "Pragma: no-cache",
	 "SOAPAction: getSelection", 
	 "Content-length: ".strlen($xml_send_get_selection),
	);

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl, CURLOPT_URL, DAT_GET_SELECTION_URL);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $xml_send_get_selection);
  $response = curl_exec($curl); 
  curl_close($curl); 
  $responseArray = json_decode(json_encode(simplexml_load_string(str_replace(':', '', $response))),true);
  $data = json_decode(json_encode(simplexml_load_string($responseArray['SOAP-ENVBody']['ns1getSelectionResponse']['result'])),true);
  $data['registrationDates']['registrationDate'] = $years;
  $data['registrationDateMonths']['registrationDateMonth'] = $months;
  $rest = array();
  foreach ($data as $label => $value) {
    if (count($value) > 0) {
      foreach ($value as $key => $item) {
        if(!isset($value[$key]['key']) && !isset($value[$key]['value'])) {
          $rest[$key] = $item;
        }
      }
    }
  }
  return $rest;
}

$valoracion = 0;
$vehiculo = array();

function apiCallGetEvaluationDataIberica ($params = array( "datecode" => '', 'kilometer' => '', 'registrationDate' => '', 'container' => '', 'constructionTime' => '')) {
  global $post, $xml_send_get_evaluation, $valoracion, $vehiculo;

  foreach ($params as $label => $value) {
    $xml_send_get_evaluation = str_replace("*|".$label."|*", $value, $xml_send_get_evaluation);
  }

  $headers = array(
    "Content-type: text/xml;charset=\"utf-8\"",
    "Accept: text/xml",
    "Cache-Control: no-cache",
    "Pragma: no-cache",
    //"SOAPAction: getSelection", 
    "Content-length: ".strlen($xml_send_get_evaluation),
    "customerNumber: ".DAT_CUSTOMER_NUMBER,
    "customerLogin: ".DAT_USER,
    "customerSignature: ".DAT_SIGNATURE,
    "interfacePartnerNumber: 3300000",
    "interfacePartnerSignature: jA0EAwMCXKMe4M0cn/JgySkpwLxh7nqru6YusgPe6/n+M9910BOthq4r3aSgvzs0eKJdd0wFbYT8eQ==",
  );
  
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl, CURLOPT_URL, DAT_GET_EVALUATION_URL);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $xml_send_get_evaluation);
  $response = curl_exec($curl); 
  $responseArray = json_decode(json_encode(simplexml_load_string(str_replace(':', '', $response))),true);

  $valoracion = 0;
  array_walk_recursive($responseArray, 'searchValue');
  array_walk_recursive($responseArray, 'searchVehicle');

  curl_close($curl); 
  return array(
    "valoracion" => $valoracion,
    "vehiculo" => $vehiculo
  );
}

function searchValue($item, $clave) {
  global $valoracion;
  if ($clave == 'ns1SalesPriceGross') {
    $valoracion = $item;
  }
}

function searchVehicle($item, $clave) {
  global $vehiculo;
  if($clave == 'ns1VehicleTypeNameN' || $clave == 'ns1ManufacturerName' || $clave == 'ns1BaseModelName' || $clave == 'ns1ContainerNameN') {
    $vehiculo[$clave] = $item;
  }
}

