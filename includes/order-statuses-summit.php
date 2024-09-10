<?php



//adding order status

add_action( 'init', 'register_my_new_order_statuses' );

function register_my_new_order_statuses() {
    //create
    register_post_status( 'wc-Created', array(
        'label'                     => _x( 'Created', 'Order status', 'summit-payments-domain' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Created <span class="count">(%s)</span>', 'Created<span class="count">(%s)</span>', 'summit-payments-domain' )
    ) );
    
    //commit
    register_post_status( 'wc-Committed', array(
        'label'                     => _x( 'Commited', 'Order status', 'summit-payments-domain' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Commited <span class="count">(%s)</span>', 'Commited<span class="count">(%s)</span>', 'summit-payments-domain' )
    ) );

    //identification
    register_post_status( 'wc-identification', array(
        'label'                     => _x( 'Awaiting Identification', 'Order status', 'summit-payments-domain' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Awaiting Identification <span class="count">(%s)</span>', 'Awaiting Identification<span class="count">(%s)</span>', 'summit-payments-domain' )
    ) );
        
   
}




//display order novih statusov
add_filter( 'wc_order_statuses', 'my_new_wc_order_statuses' );

function my_new_wc_order_statuses( $order_statuses ) {
    
    $order_statuses['wc-created'] = _x( 'Created', 'Order status', 'summit-payments-domain' );
    $order_statuses['wc-committed'] = _x( 'Committed', 'Order status', 'summit-payments-domain' );
    $order_statuses['wc-identification'] = _x( 'Awaiting Identification', 'Order status', 'summit-payments-domain' );
    return $order_statuses;
}




//update of status
function getStatus($slStatus){
    if($slStatus == "create"){
        return "created";
    }elseif($slStatus == "commit"){
        return "committed";
    }elseif($slStatus == "cancel"){
        return "cancelled";
    }elseif($slStatus == "paid"){
        return "completed";
    }elseif($slStatus == "identification"){
        return "identification";
    }else{
        return "failed";
    }
}



function updateOrderStatuses(){
    $settings = new WC_Gateway_summit();
    $settings->api_key;
    global $wpdb;
    $query = new WC_Order_Query( array(
        'orderby' => 'date',
        'status' => "wc-on-hold",
        'order' => 'DESC',
        'payment_method_title' => $settings->title,
        'date_created' => ">".(time()-15*60),
        'return' => 'ids',
    ) );
    $new_orders = $query->get_orders();
    
    

    $query = new WC_Order_Query( array(
        'orderby' => 'date',
        'status' => "wc-on-hold, wc-committed, wc-created, wc-identification",
        'order' => 'DESC',
        'payment_method_title' => $settings->title,
        'date_created' => ">".(time()-60*60*24*30),
        'return' => 'ids',
    ) );
    $orders = $query->get_orders();


    $ordersToProcess=array_diff_assoc($orders,$new_orders);
    $ordersTemp = array();
    foreach($ordersToProcess as $orderToProcess){
        array_push($ordersTemp,$orderToProcess);
    }
    $orders = $ordersTemp;
    //echo json_encode($orders).'<br><br><br>';
    $referenceList = "";
    $referenceListTesting = "";
    //echo $referenceListTesting;
    $referenceListProduction ="";
    foreach ($orders as $order){
        $tableName = $wpdb->prefix . '1Stavno_additionalInfo';
        $sql = "SELECT * FROM `".$tableName."` WHERE orderID='".$order."' ORDER BY id DESC LIMIT 1;";
        $results = $wpdb->get_results($sql);
        $results = json_encode($results);
        //echo $results;
        $results = json_decode($results,true);
        if($results[0]['production']==0){
            $referenceListTesting.=$order."|";
        }elseif($results[0]['production']==1){
            $referenceListProduction.=$order."|";
        }

        $referenceList.=$order."|";
    }
   
    //echo "Production: ".$referenceListProduction."<br>";
    //echo "Comined: ".$referenceList."<br>";
    
    $referenceList= trim($referenceList,"|");
    $referenceListTesting= trim($referenceListTesting,"|");
    $referenceListProduction= trim($referenceListProduction,"|");
    if($settings->testing == "yes"){
		$url = 'https://pktest.takoleasy.si';
		$authToken = $settings->api_key_test;
	}elseif($settings->testing == "no"){
		$url = 'https://pk.takoleasy.si';
		$authToken = $settings->api_key_production;
	}

    

    
    if($referenceListTesting!=""){
        $authToken = $settings->api_key_test;
        //echo "Testing: ".$referenceListTesting."<br>";
		$url = 'https://pktest.takoleasy.si';
        $url .= "/webpayment/rest/v1/creditapi/getContractListStatus/json";

        $body = array(
            'APIKey'       => $authToken,
            'ReferenceList'=> $referenceListTesting
            
            
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
        $createdContracts = array();
        
        foreach ($contracts as $contract){
            $contractId = $contract->reference;
            array_push($createdContracts,$contractId);
            $order = new WC_Order($contractId);
            $status = $contract->status;
            $status = getStatus($status);
            $order->update_status($status);
        }
        
        //echo json_encode($orders);

        $failedOrders = array_diff(explode("|",$referenceListTesting),$createdContracts);
        foreach($failedOrders as $failedOrder){
            $order = new WC_Order($failedOrder);
            $order->update_status("failed");
        }
        
    }
    


    //echo "produkcija".$referenceListProduction;
    if($referenceListProduction!=""){
        $authToken = $settings->api_key_production;
        //echo "Produkcija: ".$referenceListProduction."<br>";
		$url = 'https://pk.takoleasy.si';
        $url .= "/webpayment/rest/v1/creditapi/getContractListStatus/json";

        $body = array(
            'APIKey'       => $authToken,
            'ReferenceList'=> $referenceListProduction
            
            
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
        $createdContracts = array();
        
        foreach ($contracts as $contract){
            $contractId = $contract->reference;
            array_push($createdContracts,$contractId);
            $order = new WC_Order($contractId);
            $status = $contract->status;
            $status = getStatus($status);
            $order->update_status($status);
        }
        
        
        $failedOrders = array_diff(explode("|",$referenceListProduction),$createdContracts);
        foreach($failedOrders as $failedOrder){
            $order = new WC_Order($failedOrder);
            $order->update_status("failed");
        }
        
    }
   
    
}

add_action('bulk_actions-edit-shop_order','updateOrderStatuses');



/**
 * @param array $statuses
 * @return array
 */



add_filter( 'woocommerce_order_is_paid_statuses', 'addPaidStatuses' ,1);

function addPaidStatuses( $statuses ) {
	// already included statuses are processing and completed
	array_push($statuses,'created');
	array_push($statuses,'committed');
    array_push($statuses,'identification');
    return $statuses;
}

















/*
//dodamo nov status med seznam statusov za blk edit s
function summit_add_bulk_invoice_order_status() {
    global $post_type;

    if ( $post_type == 'shop_order' ) {
        ?>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery('<option>').val('mark_invoiced').text('<?php _e( 'Change status to invoiced', 'summit-payments-domain' ); ?>').appendTo("select[name='action']");
                    jQuery('<option>').val('mark_invoiced').text('<?php _e( 'Change status to invoiced', 'summit-payments-domain' ); ?>').appendTo("select[name='action2']");   
                });
            </script>
        <?php
    }
}

add_action( 'admin_footer', 'summit_add_bulk_invoice_order_status' );
*/