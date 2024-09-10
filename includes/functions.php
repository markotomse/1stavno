<?php

    function updateOrderInformation($order){
        //echo $order;
        $id = $order;

        $settings = new WC_Gateway_summit();
        
        global $wpdb;
        $order = new WC_Order($order);


        $tableName = $wpdb->prefix . '1Stavno_additionalInfo';
        $sql = "SELECT * FROM `".$tableName."` WHERE orderID='".$order."' ORDER BY id DESC LIMIT 1;";
        $results = $wpdb->get_results($sql);
        $results = json_encode($results);
        $results = json_decode($results,true);
                
        $cenaDDV =  $order->get_total();
        $items=array();
        foreach ($order->get_items() as $item_id => $item ) {
            $item_quantity  = $item->get_quantity(); // Get the item quantity
            $product_name   = $item->get_name(); // Get the item name (product name)
            $item_total     = $item->get_total(); // Get the item line total discounted
            // Displaying this data (to check)
            array_push($items, 'Product name: '.$product_name.' | Quantity: '.$item_quantity.' | Item total: '. number_format( $item_total, 2 ));
        }


        $tableName = $wpdb->prefix . '1Stavno_additionalInfo';
        $sql = "SELECT * FROM `".$tableName."` WHERE orderID='".$id."' ORDER BY id DESC LIMIT 1;";
        $results = $wpdb->get_results($sql);
        $results = json_encode($results);
        $results = json_decode($results,true);

        if($results[0]['production']==0){
            $url = 'https://pktest.takoleasy.si';
            $authToken = $settings->api_key_test;
        }elseif($results[0]['production']==1){
            $url = 'https://pk.takoleasy.si';
            $authToken = $settings->api_key_production;
        }
        
        $url .= "/webpayment/rest/v1/creditapi/sendOrderAdditionalInfo/json";

        $body = array(
            'APIKey'       => $authToken,
            'ReferenceNumber'=> $id,
            'StNarocila' =>  $id,
            "CenaDDV" => $cenaDDV,
            'CenaBrezDDV' => "0",
            'DDV' => "0",
            'Artikli' => $items
            
        );

        
        $args = array(
            'body'        => json_encode($body),
            'timeout'     => '7',
            'redirection' => '5',
            'blocking'    => true,
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'cookies'     => array(),
            'data_format' => 'body',
        );
        


        //header('Content-Type: application/json; charset=utf-8');
        $response = wp_remote_post( $url,$args);
        $obj = $response['body'];
        $obj = json_decode($obj,true);
        $obj = $obj['data']['status'];

        return $obj;
        

    }



    function updateOrderInformationCheck(){

        

        $settings = new WC_Gateway_summit();
        $settings->api_key;
        global $wpdb;
        
    
        $query = new WC_Order_Query( array(
            'orderby' => 'date',
            'status' => "wc-committed, wc-created, wc-cancelled",
            'order' => 'DESC',
            'payment_method_title' => $settings->title,
            'return' => 'ids',
        ) );
        $orders = $query->get_orders();
        
        foreach ($orders as $order){
        $tableName = $wpdb->prefix . '1Stavno_additionalInfo';
        $sql = "SELECT * FROM `".$tableName."` WHERE orderID='".$order."' ORDER BY id DESC LIMIT 1;";
        $results = $wpdb->get_results($sql);
        $results = json_encode($results);
        $results = json_decode($results,true);

        if(count($results)==0){
            $obj = updateOrderInformation($order);
            if($obj=="0"){
                $data = array('additionalInfo'=>"1");
                $where = array('id'=>$results[0]['id']);
                $wpdb->update($tableName,$data,$where);
            }

            $data = array('orderID'=>$order,'additionalInfo' =>1);
		    $wpdb->insert($tableName,$data);
        }elseif($results[0]['additionalInfo']=="0"){
            $obj = updateOrderInformation($order);
            if($obj=="0"){
                $data = array('additionalInfo'=>"1");
                $where = array('id'=>$results[0]['id']);
                $wpdb->update($tableName,$data,$where);
            }
            
                
        }
    }
       
    }

    function updateInstallments(){
        $settings = new WC_Gateway_summit();
        global $wpdb;
        $tableName = $wpdb->prefix . '1Stavno';
        
        if($settings->testing=="no"){
            $production = 1;
        }else{
            $production = 0;
        }

        //pripravimo tabelo, ki jo bomo poslali preko apija
        $args = array('limit'=>-1,'status'=>'publish');
        $products =  wc_get_products($args);
        $productsGetInfo=array();
        foreach ($products as $product){
            if(empty($product->get_price())==1){
                continue;
            }
            if($product->get_price()<=15000){
                $sql = "SELECT * FROM `".$tableName."` WHERE productID='".$product->id."' ORDER BY id DESC LIMIT 1;";
                $results = $wpdb->get_results($sql);
                $results = json_encode($results);
                $results = json_decode($results,true);
                $results = $results[0];
                if(empty($results)){
                    array_push($productsGetInfo,array('ID'=>$product->get_id(),"name"=>$product->get_name(),"status"=>"missing","amount"=>$product->get_price()));
                }else{
                    array_push($productsGetInfo,array('ID'=>$product->get_id(),"name"=>$product->get_name(),"status"=>"update","amount"=>$product->get_price()));
                }
            }
        }   
        
        
        //priprava API Klica
        $amounts = array();
        foreach ($productsGetInfo as $product) {
            $amounts[] = $product['amount'];
        }
        $amounts = array_unique($amounts);
        $amountsSplit =array_chunk($amounts, 300);
        foreach($amountsSplit as $amounts){
            $amounts = implode(";",$amounts);
            $settings = new WC_Gateway_summit();
            if($settings->testing == "yes"){
                $url = 'https://pktest.takoleasy.si';
                $authToken = $settings->api_key_test;
            }elseif($settings->testing == "no"){
                $url = 'https://pk.takoleasy.si';
                $authToken = $settings->api_key_production;
            }
            $url .= "/webpayment/rest/v1/creditapi/getInstallmentInfoMultiple/json";
            $body = array(
                'APIKey'       => $authToken,
                'CreditAmounts' => $amounts        );
            
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
            $installmentsApi = json_decode($obj)->{'data'}->{'installmentInfoResultList'};

            $newColumnName = 'installmentInfoList';
            $i = 0;

            while ($i < count($productsGetInfo)) {
                foreach ($installmentsApi as $installment) {
                    $minInstallment = array_reverse($installment->{'installmentInfoList'})[0]->{'installmentValue'};
                    $minInstallment = round($minInstallment,2);
                    $installmentInfoList = json_encode($installment->{'installmentInfoList'});
                    $amount = $installment->{'amount'};
                    $installmentData = '{"serviceStatus":"OK","data":{"status":"0","amount":'.$amount.',"installmentInfoList":'.$installmentInfoList.'}}';
                    if ($amount == $productsGetInfo[$i]['amount']) {
                        $productsGetInfo[$i]['installmentInfoList'] = $installmentData;
                        $productsGetInfo[$i]['minInstallment'] = $minInstallment;
                        break;
                }
            }
            $i++;
            }


            
            
            //gremo čez rezultate api klica in posodobimo/ustvarimo vrednosti 
            foreach($productsGetInfo as $product){

                $tableName = $wpdb->prefix . '1Stavno';
                $sql = "SELECT * FROM `".$tableName."` WHERE productID='".$product["ID"]."' ORDER BY id DESC LIMIT 1;";
                $results = $wpdb->get_results($sql);
                $results = json_encode($results);
                $results = json_decode($results,true);
                $results = $results[0];

                if(empty($results)){
                    $minInstallment = $product['minInstallment'];
                    $data = array('minInstallment'=>$minInstallment,'price'=>$product['amount'],'productID'=>$product["ID"],'production'=>$production,'installments'=>($product['installmentInfoList']));
                    $wpdb->insert($tableName,$data);
                }else{
                    $minInstallment = $product['minInstallment'];
                    $data = array('minInstallment'=>$minInstallment,'price'=>$product['amount'],'productID'=>$product["ID"],'production'=>$production,'installments'=>($product['installmentInfoList']));
                    $where = array('productID'=>$product['ID']);
                    $wpdb->update($tableName,$data,$where);
                }
            }
            sleep(2);
        }
    }
?>