<?php

// Use $server_orders vs. $undelivered_orders to find newly delivered orders
// Credit their new supplies to inventory and delete such orders from 
// the undelivered_orders table
// $server_orders: set of orders from server
function record_deliveries($db, $server_orders, $undelivered_orders) {
    $delivered_orders = array();  // build set of delivered orders
    for ($i = 0; $i < count($server_orders); $i++) {
        $orderid = $server_orders[$i]['orderID'];
        $delivered = $server_orders[$i]['delivered'];
        // Note PHP type coercion makes 'false' == true, see pg. 235
        if ($delivered==='true') {
            $delivered_orders[$orderid] = $server_orders[$i];  // remember order by id
        }
    }
    error_log('supply: ' . print_r($server_orders, true));
    error_log('delivered: ' . print_r($delivered_orders, true));
    // match delivered supply order with previously undelivered order
    for ($j = 0; $j < count($undelivered_orders); $j++) {
        $orderID = $undelivered_orders[$j]['orderID'];
        error_log('undel order ' . print_r($undelivered_orders[$j], true));
        if (array_key_exists($orderID, $delivered_orders)) {
            error_log("found delivered order $orderID");
            // delete $orderID from undelivered orders table
            delete_from_uo($db, $orderID);
            // get the quantities of flour and cheese in this order
            $order = $delivered_orders[$orderID];
            // and add them to the inventory table
            replenish_flour_inventory($db, $order['items'][0]['quantity']);
            replenish_cheese_inventory($db, $order['items'][1]['quantity']);
        }
    }
}

// Check $inventory, and order enough to aim for 150 units of both flour and cheese 
function order_supplies($db, $httpClient, $inventory, $base_url) {
    if ($inventory[0]['quantity'] < 150) {
        $flour_required_qty = 150 - $inventory[0]['quantity'];
        $flour_unit_bags = 1;
        $flour_order_qty = 0;
        // this can be done by division...but this is fine
        // $flour_unit_bags = int_div($flour_required_qty -1, 100) + 1; 
        while ($flour_order_qty < $flour_required_qty) {
            $flour_order_qty = 100 * $flour_unit_bags;
            $flour_unit_bags += 1;
        }
    } else {
        $flour_order_qty = 0;
    }

    if ($inventory[1]['quantity'] < 150) {
        $cheese_order_qty = 150 - $inventory[1]['quantity'];
    } else {
        $cheese_order_qty = 0;
    }

    if (($flour_order_qty > 0) || ($cheese_order_qty > 0)) {

        $item1 = array('productID' => 11, 'quantity' => $flour_order_qty);
        $item2 = array('productID' => 12, 'quantity' => $cheese_order_qty);
        $order = array('customerID' => 1, 'items' => array($item1, $item2));
        error_log('posting supply order ');

        $location = post_order($httpClient, $base_url, $order);

        error_log("get back location = $location");
        $parts = explode('/', $location);
        $order_id = $parts[count($parts) - 1];  // last part
        error_log("new supply order id $order_id");
        // add to undelivered orders table
        insert_to_uo($db, $order_id, $flour_order_qty, $cheese_order_qty);
    }
}
