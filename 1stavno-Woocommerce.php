<?php
/**
 * Plugin Name: 1Stavno
 * Plugin URI: https://1stavno.si/
 * Author: Summit Leasing Slovenija d.o.o.
 * Author URI: https://1stavno.si/
 * Description: Plugin za prejemanje plačil preko 1Stavno.
 * Version: 1.4.5.
 * text-domain: summit-payments-domain
 * 
 * Class WC_Gateway_summit file.
 *
 * @package WooCommerce\summit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_action( 'plugins_loaded', 'summit_payment_init', 11 );
add_filter( 'woocommerce_payment_gateways', 'add_to_woo_summit_payment_gateway');

function summit_payment_init() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-payment-gateway-summit.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/order-statuses-summit.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/summit-cron.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/functions.php';
	}
}

function add_to_woo_summit_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_summit';
    return $gateways;
}






function getInstallments($price){
	$settings = new WC_Gateway_summit();
	if($settings->testing == "yes"){
		$url = 'https://pktest.takoleasy.si';
		$authToken = $settings->api_key_test;
	}elseif($settings->testing == "no"){
		$url = 'https://pk.takoleasy.si';
		$authToken = $settings->api_key_production;
	}
	$url .= "/webpayment/rest/v1/creditapi/getInstallmentInfo/json";
	$body = array(
		'APIKey'       => $authToken,
		'CreditAmount' => $price
	);
	
	$args = array(
		'body'        => $body,
		'timeout'     => '7',
		'redirection' => '5',
		'blocking'    => true,
		'headers'     => array(),
		'cookies'     => array(),
	);
	$response = wp_remote_post( $url,$args);
	$obj = $response['body'];
	$obj = json_decode($obj,true);
	return $obj;

	
}






//razrezane cene na strani kategorij
function addSummitCalculationToCatalogLoop(){
	$settings = new WC_Gateway_summit();
	$display = $settings->displayCatalogPrices;
    $font_size = $settings->installments_size;
	if($display == "yes"){

    global $product;
	global $charCollate;
	global $wpdb;

    $price = $product->get_price();
	if($price == ""){
		return "";
	}

    $tableName = $wpdb->prefix . '1Stavno';
	$sql = "SELECT * FROM `".$tableName."` WHERE productID='".$product->id."' ORDER BY id DESC LIMIT 1;";
	$results = $wpdb->get_results($sql);
	$results = json_encode($results);
	$results = json_decode($results,true);
	if(!empty($results)){
		$results = $results[0];
	}

    if($settings->testing=="no"){
        $production = 1;
    }else{
        $production = 0;
    }
	
	//print_r($results);
	if(empty($results)){
		$obj = getInstallments($product->get_price());
		$installments = $obj['data']['installmentInfoList'];
		$installments = array_reverse($installments);
		if(empty($installments)){
			return "";
		}
		$minInstallment = $installments[0]['installmentValue'];
		$minInstallment = round($minInstallment,2);
		$data = array('minInstallment'=>$minInstallment,'price'=>$product->get_price(),'productID'=>$product->id, 'production'=>$production, 'installments'=>json_encode($obj));
		$wpdb->insert($tableName,$data);
	}elseif($results['price'] != $product->get_price() || $results['minInstallment']==0 || is_null($results['production']) ||($production==1 && $results['production']==0) || ($production==0 && $results['production']==1)){
		$obj = getInstallments($product->get_price());
		$installments = $obj['data']['installmentInfoList'];
		$installments = array_reverse($installments);
		if(empty($installments)){
			return "";
		}
		$minInstallment = $installments[0]['installmentValue'];
		$minInstallment = round($minInstallment,2);
		$data = array('minInstallment'=>$minInstallment,'price'=>$product->get_price(),'productID'=>$product->id, 'production'=>$production, 'installments'=>json_encode($obj));
		$where = array('productID'=>$product->id);
		$wpdb->update($tableName,$data,$where);

	}else{
		$minInstallment =$results['minInstallment'];
	}

	
    $show = '';
    if(!empty($font_size)){ $font_size_over = 'font-size:'.$font_size.'px!important;'; }


    
	$show = '<div class="" style="font-style:italic;font-size:'.$font_size.'px">'."že od ".str_replace('.',',',$minInstallment)."€ / mesec".'</div>';
	
	if($minInstallment!=0)
	return $show;
	
	}
	
    
} 


add_filter( 'woocommerce_after_shop_loop_item_title', 'summit_before_cart_btn', 10, 3 );
function summit_before_cart_btn(){
	$before = addSummitCalculationToCatalogLoop();
	echo $before;
}



//razrezane cene na strani izdelka - normal product
function getInstalmentJson(){
	global $product;
	$pid = $product->get_id();
	global $wpdb;
    $tableName = $wpdb->prefix . '1Stavno';
	$xxx = $wpdb->get_results( "SELECT installments FROM $tableName WHERE productID = $pid" ); 
	return json_encode($xxx[0]);
}



//add_action( 'woocommerce_before_add_to_cart_form', 'addSummitCalculationToProductPage', 10);
add_action( 'woocommerce_simple_add_to_cart', 'addSummitCalculationToProductPage', 3);
//add_action( 'woocommerce_variable_add_to_cart', 'addSummitCalculationToProductPage', 10);
//add_action( 'woocommerce_composite_add_to_cart', 'addSummitCalculationToProductPage', 10);
function addSummitCalculationToProductPage( ){
	$settings = new WC_Gateway_summit();
    $font_size = $settings->installments_size;
	$display = $settings->displayProductPrices;
	
	if($display == "yes"){
    global $product;
	global $charCollate;
	global $wpdb;

    $price = $product->get_price();
    //echo json_encode($product->get_data());
	if($price == ""){
		return "";
	}

    $tableName = $wpdb->prefix . '1Stavno';
	$sql = "SELECT * FROM `".$tableName."` WHERE productID='".$product->id."' ORDER BY id DESC LIMIT 1;";
	$results = $wpdb->get_results($sql);
	$results = json_encode($results);
	$results = json_decode($results,true);
	if(!empty($results)){
		$results = $results[0];
	}

    if($settings->testing=="no"){
        $production = 1;
    }else{
        $production = 0;
    }
	
	if(empty($results)){
		$obj = getInstallments($product->get_price());
		$installments = $obj['data']['installmentInfoList'];
		$installments = array_reverse($installments);
		if(empty($installments)){
			return "";
		}
		$minInstallment = $installments[0]['installmentValue'];
		$minInstallment = round($minInstallment,2);
		$data = array('minInstallment'=>$minInstallment,'price'=>$product->get_price(),'productID'=>$product->id, 'production'=>$production, 'installments'=>json_encode($obj));
		$wpdb->insert($tableName,$data);
	}elseif($results['price'] != $product->get_price() || $results['minInstallment']==0 || is_null($results['production']) ||($production==1 && $results['production']==0) || ($production==0 && $results['production']==1)){

		$obj = getInstallments($product->get_price());
		$installments = $obj['data']['installmentInfoList'];
		$installments = array_reverse($installments);
		if(empty($installments)){
			return "";
		}
		$minInstallment = $installments[0]['installmentValue'];
		$minInstallment = round($minInstallment,2);
		$data = array('minInstallment'=>$minInstallment,'price'=>$product->get_price(),'productID'=>$product->id,'production'=>$production, 'installments'=>json_encode($obj));
		$where = array('productID'=>$product->id);
		$wpdb->update($tableName,$data,$where);

		$where = array('productID'=>$product->id);
		$wpdb->update($tableName,$data,$where);
    }
    else{
		$minInstallment =$results['minInstallment'];
	}

    $show = '';
    if(!empty($font_size)){ $font_size_over = 'font-size:'.$font_size.'px!important;'; }

	if(($minInstallment!=0)){






echo '
<html>
    
    <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link
            href="https://fonts.googleapis.com/css2?family=Poppins&display=swap"
            rel="stylesheet"
        />
        <link href="'.plugin_dir_url( __FILE__ ).'includes/prikaz.css?v=1.2'.'" rel="stylesheet" />
        
        
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <script>
			const myJson =' .getInstalmentJson(). '
			const items = JSON.parse(myJson.installments);
            const itemsArray = items.data.installmentInfoList;

            function createOptions() {
                var options = "";
                for (var i = 0; i < itemsArray.length; i++) {
                    var installmentNr = itemsArray[i].installmentNr;
					if (installmentNr == 3 || installmentNr ==4){
                        options += "<option value=\'" + installmentNr + "\'>" + installmentNr + " obroki</option>";
                    }else{
                        options += "<option value=\'" + installmentNr + "\'>" + installmentNr + " obrokov</option>";
                    }
                }
                document.getElementById("obroki").innerHTML = options;
                updateValue({value:itemsArray[0].installmentNr});
            }
            
            function updateValue(element) {
                let calculation;
                
                for (var i = 0; i < itemsArray.length; i++) {
                    if(itemsArray[i].installmentNr== element.value){
                        calculation = itemsArray[i].installmentValue;
                        
                    }
                }
                calculation += " €";
                calculation = calculation.replace(".", ",");
                document.getElementById("term-amount").innerHTML = calculation;
            };

        </script>
    </head>
    
    <body onload="createOptions()">
        
        <div class="card-wrap">
        
            <!-- Card -->
            <div class="card">
                
                <!-- Content -->
                <div class="card-content">
                
                    <!-- Header (logo + text)-->
                    <img class="img-logo" src="'.plugin_dir_url( __FILE__ ).'assets/stavno-logo.png" alt="Stavno logo" />
                    <!-- <span>Informativni izračun obrokov</span> -->
                    
                    <!-- Calculation -->
                    <div class="calculation">
                    
                        <!-- Select -->
                        <div class="loan-term">
                            <select name="obroki" id="obroki" onchange="updateValue(this)">
                                
                            </select>
                            <svg class="caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z"/></svg>                    </div>
                        
                        <div class="icon-times">x</div>
                        
                        <div class="term-amount" id="term-amount">20,00 €</div>
                        
                        <div class="tooltip top">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Pro 6.4.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license (Commercial License) Copyright 2023 Fonticons, Inc. --><path d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216c-13.3 0-24-10.7-24-24s10.7-24 24-24h48c13.3 0 24 10.7 24 24v88h8c13.3 0 24 10.7 24 24s-10.7 24-24 24H216c-13.3 0-24-10.7-24-24s10.7-24 24-24zm40-208a32 32 0 1 1 0 64 32 32 0 1 1 0-64z"/></svg>
                        </div>

                        
                    </div>
                    
                </div>
                
                
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <span>Možnost plačila na obroke tudi za tujce in upokojence.<br/></span>
                <span>Brez pologa. Brez skritih stroškov.</span>
            </div>
             
        </div>
                
    </body>
    
</html>


';
}
}
}


// ustvarimo mySQL tabelo 
function summitProductTable() {
    global $charCollate;
	global $wpdb;
    $tableName = $wpdb->prefix . '1Stavno';
    $sql = "CREATE TABLE $tableName (
		id int not null AUTO_INCREMENT,
		productID int not null, 
		price decimal(15,2) not null, 
		minInstallment decimal(15,2) not null,
        production tinyint(1),
		installments varchar(30000),
		primary key (id) 
	) $charCollate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );


	$tableName = $wpdb->prefix . '1Stavno_additionalInfo';
    $sql = "CREATE TABLE IF NOT EXISTS $tableName (
		id int not null AUTO_INCREMENT,
		orderID int not null, 
		additionalInfo boolean,
		production boolean,
		primary key (id) 
	) $charCollate;";
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );


    $table_name = $wpdb->prefix . '1Stavno';
    $wpdb->query("DELETE FROM $table_name");


}
    
register_activation_hook(__FILE__,'summitProductTable');





