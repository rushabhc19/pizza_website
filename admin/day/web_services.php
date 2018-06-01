<?php
// Functions to do the base web services needed
// Note that all needed web services are sent from this day directory
// The functions here should throw up to their callers, just like
// the functions in model.
//
// Post day number to server
// Returns if successful, or throws if not
function post_day($httpClient, $base_url, $day) {
    error_log('post_day to server: ' . $day);
    $url = $base_url . '/day/';
    $response = $httpClient->request('POST', $url, ['json' => $day]);
    $status = $response->getStatusCode();
    return $status;
}

// POST order and get back location
function post_order($httpClient, $base_url, $order) {
    $url = $base_url . '/orders/';
    // Guzzle does the json_encode for us--
    $response = $httpClient->request('POST', $url, ['json' => $order]);
    $location = $response->getHeader('Location');
    return $location[0];  // first entry in array is string Location
}

function get_supply_orders($httpClient, $base_url) {
    $url = $base_url . '/orders/';
    $response = $httpClient->get($url);
    $ordersJson = $response->getBody()->getContents();  // as StreamInterface, then string
//    echo '<br> Returned result of GET of product 1: ';
//     print_r($ordersJson);

    $orders = json_decode($ordersJson, true);
    error_log('supply orders After json_decode:');
    error_log(print_r($orders, true));
    return $orders;
}

function get_one_supply_order($httpClient, $base_url, $orderid) {
    $url = $base_url . '/orders/' . $orderid;
    $response = $httpClient->get($url);
    $orderJson = $response->getBody()->getContents();  // as StreamInterface, then string
    // echo '<br> Returned result of GET of product 1: ';
    //  print_r($orderJson);
    //  echo '<br> After json_decode:<br>';
    $order = json_decode($orderJson, true);
    // print_r($orders);
    return $order;
}
