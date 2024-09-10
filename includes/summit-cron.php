<?php 

//nastavimo po meri narejen interval za izvajanje crona
add_filter( 'cron_schedules', 'summit_add_cron_interval' );
function summit_add_cron_interval( $schedules ) { 
    $schedules['six_hours'] = array(
        'interval' => (6*60*60)+rand(-1800,1800),
        'display'  => esc_html__( 'Every Six Hours' ), );
    return $schedules;
}

add_filter( 'cron_schedules', 'summit_add_cron_interval_update_prices' );
function summit_add_cron_interval_update_prices( $schedules ) { 
    $schedules['one_day'] = array(
        'interval' => (24*60*60)+rand(-1800,1800),
        'display'  => esc_html__( 'Everyday' ), );
    return $schedules;
}



//nastavimo custom hook za wp-cron 
add_action('summit_cron_hook', 'summit_cron_event');
add_action('summit_cron_hook_update_prices', 'summit_cron_event_update_prices');

//nastavimo event, ki se izvede vsak časovni interval
function summit_cron_event(){
    updateOrderStatuses();
    updateOrderInformationCheck();
}

function summit_cron_event_update_prices(){
    updateInstallments();
}




//preprečimo podvojeno izvajanje in začeno cron job 
if(!wp_next_scheduled('summit_cron_hook')){
    wp_schedule_event(time(),'six_hours','summit_cron_hook');
}


if(!wp_next_scheduled('summit_cron_hook_update_prices')){
    wp_schedule_event(time(),'one_day','summit_cron_hook_update_prices');
}
