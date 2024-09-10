<?php

    require_once('../../../../wp-load.php');
    //updateOrderStatuses();
    //updateOrderInformationCheck();
    $production = "";
    
    $settings = new WC_Gateway_summit();
    if($settings->testing == "yes"){
		$url = 'https://pktest.takoleasy.si';
		$authToken = $settings->api_key_test;
        $production = 0;
	}elseif($settings->testing == "no"){
		$url = 'https://pk.takoleasy.si';
		$authToken = $settings->api_key_production;
        $production = 1;
	}


    
    $url .= "/webpayment/rest/v1/creditapi/getContractListStatus/json";
    $body = array(
        'APIKey'       => $authToken,
        'ReferenceList'=> $_GET['id']
        
        
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
    $obj = json_decode($obj);
    $contracts = ($obj->data->contractList);
    $contracts = $contracts[0];
    $contracts =  json_encode($contracts);   
    $contracts =  json_decode($contracts,true);
    $status = getStatus($contracts['status']);
    $order =  new WC_Order($_GET['id']);
    $order->update_status($status);
    //echo $status;
    if($status == "created" || $status == "cancelled" || $status == "committed"){

        $tableName = $wpdb->prefix . '1Stavno_additionalInfo';
        $sql = "SELECT * FROM `".$tableName."` WHERE orderID='".$_GET['id']."' ORDER BY id DESC LIMIT 1;";
        $results = $wpdb->get_results($sql);
        $results = json_encode($results);
        $results = json_decode($results,true);
        //echo $_GET['id'];
        
        
        if(count($results)==0){
            $obj = updateOrderInformation($_GET['id']);
            //echo $obj;
            if($obj=="0"){
                $tableName = $wpdb->prefix . '1Stavno_additionalInfo';
                $data = array('orderID'=>$_GET['id'],'additionalInfo' =>1,'production'=>$production);
                $wpdb->insert($tableName,$data);
            }
        }else{
            $obj = updateOrderInformation($_GET['id']);
            //echo $obj;
            if($obj=="0"){

                $tableName = $wpdb->prefix . '1Stavno_additionalInfo';
                $data = array('additionalInfo' =>1);
                $where = array('orderID'=>$_GET['id']);
                $wpdb->update($tableName,$data,$where);
            }
        }
    }


  wp_redirect($settings->get_return_url( $order ));
        
?>