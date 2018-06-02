<?php

$request_uri = $_SERVER['REQUEST_URI'];
$doc_root = filter_input(INPUT_SERVER, 'DOCUMENT_ROOT');
$dirs = explode(DIRECTORY_SEPARATOR, __DIR__);
array_pop($dirs); // remove last element
$project_root = implode('/', $dirs) . '/';
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', '0'); // displayed errors would mess up response
ini_set('log_errors', 1);
// the following file needs to exist, be accessible to apache
// and writable (chmod 777 php-server-errors.log)
// Use an absolute file path to create just one log for the web app
ini_set('error_log', $project_root . 'php-server-errors.log');
set_include_path($project_root);
// app_path is the part of $project_root past $doc_root
$app_path = substr($project_root, strlen($doc_root));
error_log('app_path = ' . $app_path);
// project uri is the part of $request_uri past $app_path, less '/'
$project_uri = substr($request_uri, strlen($app_path) - 1);
error_log('project uri = ' . $project_uri);
$parts = explode('/', $project_uri);
//like  /rest/products/1 ;
//    0    1     2    3    
// Get needed code
require_once('model/database.php');
require_once('model/product_db.php');
require_once('model/order_db.php');
require_once('model/day.php');

$server = $_SERVER['HTTP_HOST'];
$method = $_SERVER['REQUEST_METHOD'];
$proto = isset($_SERVER['HTTPS']) ? 'https:' : 'http:';
$url = $proto . '//' . $server . $request_uri;
$resource = trim($parts[2]);
error_log('resource = ' . $resource . 'len = ' . strlen($resource));
if (isset($parts[3])) {
    $id = $parts[3];
}
error_log('starting REST server request, method=' . $method . ', uri = ...' . $project_uri);
if ($method ==='POST') {
   error_log('body: ' . file_get_contents('php://input'));
}

switch ($resource) {
    // Access the specified product
    case 'products':
        error_log('request at case product');
        switch ($method) {
            case 'GET':
                handle_get_product($id);
                break;
            case 'POST':
                handle_post_product($url);
                break;
            default:
                $error_message = 'bad HTTP method : ' . $method;
                include_once('errors/server_error.php');
                server_error(405, $error_message);
                break;
        }
        break;
    case 'day':
        error_log('request at case day');
        switch ($method) {
            case 'GET':
                // get current day from DB and return it
                handle_get_day($db);
                break;
            case 'POST':
                // sets new day in DB
                handle_post_day($db);
                break;
            default:
                $error_message = 'bad HTTP method : ' . $method;
                include_once('errors/server_error.php');
                server_error(405, $error_message);
                break;
        }
        break;
    case 'orders':
        error_log('request at case orders');
        switch ($method) {
            case 'GET':
                // get information, including status of a supply order (i.e., delivered or not)
                if (!empty($id)) {
                    handle_get_order($db, $id);
                } else {
                    handle_get_orders($db);
                }
                break;
            case 'POST':
                // creates a new supply order (flour, cheese), returns new URI
                handle_post_order($db, $url);
                break;
            default:
                $error_message = 'bad HTTP method : ' . $method;
                include_once('errors/server_error.php');
                server_error(405, $error_message);
                break;
        }
        break;
    default:
        $error_message = 'Unknown REST resource: ' . $resource;
        include_once('errors/server_error.php');
        server_error(400, $error_message);  // blame client (but might be server's fault)
        break;
}

function handle_get_product($product_id) {
    try {
        if (!(is_numeric($product_id) && $product_id > 0)) {
            $error_message = 'Bad product_id in handle_get_product: ' . $product_id;
            include_once('errors/server_error.php');
            server_error(400, $error_message);  // bad client URL
            return;
        }
        $product = get_product($product_id);
        if (empty($product)) {  // no data found
            $error_message = 'failed to find product';
            include_once('errors/server_error.php');
            server_error(404, $error_message);
            return;
        }
        $data = json_encode($product);
        error_log('in handle_get_product, $product = ' . print_r($product, true));
        if ($data === FALSE) {  // failure of json_encode
            $error_message = 'JSON encode error' . json_last_error_msg();
            include_once('errors/server_error.php');
            server_error(500, $error_message);  // server problem
            return;
        }
    } catch (Exception $e) {
        $error_message = 'exception trying to get product' . $e->getMessage();
        include_once('errors/server_error.php');
        server_error(500, $error_message);  // server problem
        return;
    }
    echo $data;
}

function handle_post_product($url) {
    $bodyJson = file_get_contents('php://input');
    error_log('Server saw post data' . $bodyJson);
    $body = json_decode($bodyJson, true);
    if ($body === NULL) {  // failure of json_decode 
        $error_message = 'JSON decode error' . json_last_error_msg();
        include_once('errors/server_error.php');
        server_error(400, $error_message);  // client problem: sent bad JSON
        return;
    }
    try {
        $product_id = add_product($body['categoryID'], $body['productCode'], $body['productName'], $body['description'], $body['listPrice'], $body['discountPercent']);
        // return new URI in Location header
        $locHeader = 'Location: ' . $url . $product_id;
        header('Content-type: application/json');
        header($locHeader, true, 201);
        error_log('hi from handle_post_product, header = ' . $locHeader);
    } catch (Exception $e) {
        $error_message = 'Insert failed: ' . $e->getMessage();
        include_once('errors/server_error.php');
        server_error(500, $error_message);  // probably server error
    }
}

function handle_get_day($db) {
    $day = get_system_day();
    error_log('rest server in handle_get_day, day = ' . $day);
    echo $day;
}

function handle_post_day($db) {
    error_log('rest server in handle_post_day');
    $day = file_get_contents('php://input');  // just a digit string
    if (!(is_numeric($day) && $day >= 0)) {
        $error_message = 'Bad day number in handle_post_day: ' . $day;
        include_once('errors/server_error.php');
        server_error(400, $error_message);  // bad client data
        return;
    }
    error_log('Server saw POSTed day = ' . $day);
    //if $day = 0 then reinitialize the orders
    if ($day === '0') {
        //delete the orders and orderitems
        //set the autoincrement value of table orders back to 0 
        //so that the first order id will be 1
        //set daynumber to 1
        try {
            reinitialize_orders();
            update_system_day(1);
        } catch (Exception $e) {
            $error_message = 'reinit failed: ' . $e->getMessage();
            include_once('errors/server_error.php');
            server_error(500, $error_message);  // probably server error
        }
    } else {
        try {
            update_system_day($day);
        } catch (Exception $e) {
            $error_message = 'Day change failed: ' . $e->getMessage();
            include_once('errors/server_error.php');
            server_error(500, $error_message);  // probably server error
        }
    }
}

// get full information, including status of a supply order (i.e., delivered or not)
function handle_get_order($db, $order_id) {
    try {
        $order = get_order_data($db, $order_id);
    } catch (Exception $e) {
        $error_message = 'Get order failed: ' . $e->getMessage();
        include_once('errors/server_error.php');
        server_error(500, $error_message);  // probably server error
    }
    $data = json_encode($order);
    error_log('data after getting order: ' . print_r($data, true) . 'empty ' . print_r(empty($data), true));
    if ($data != null && is_string($data) && $data != 'null') {  // json_decode can return 'null'
        echo $data;
    } else {
        include_once('errors/server_error.php');
        $error = 'no such order: ' . $order_id;
        server_error(404, $error);
    }
}

function handle_get_orders($db) {
    try {
        $orders = get_orders();
        error_log("get_orders: see orders " . print_r($orders, true));
        $result = array();
        foreach ($orders as $order) {
            $order_id = $order['orderID'];
            error_log("get_orders: see orderid " . $order_id);
            $out_order = get_order_data($db, $order_id);
            if ($order != NULL) {
                $result[] = $out_order;
            } else {
                // this is an internal error, just report to log
                error_log("bad order id found in handle_get_orders");
            }
        }
    } catch (Exception $e) {
        $error_message = 'Get orders failed: ' . $e->getMessage();
        include_once('errors/server_error.php');
        server_error(500, $error_message);  // probably server error
    }
    $data = json_encode($result);
    echo $data;
}

// get the specified order info for return to client
// let caller handle any exceptions
function get_order_data($db, $order_id) {
    error_log('in get_order_data for id ' . $order_id);
    $order = get_order_details($db, $order_id);
    $current_day = get_system_day($db);
    $out_order = array();
    //show delivery status as "true" or "false"
    if ($order != NULL) {
        $out_order['orderID'] = $order['orderID'];
        $out_order['customerID'] = $order['customerID'];
        error_log('order: ' . print_r($order, true));
        if ($order['delivered'] <= $current_day) {
            $out_order['delivered'] = "true";
        } else if ($order['delivered'] > $current_day) {
            $out_order['delivered'] = "false";
        }
        $out_order['items'] = array();
        foreach ($order['items'] as $item) {
            $out_item = array();
            $out_item['productID'] = $item['productID'];
            $out_item['quantity'] = $item['quantity'];
            $out_order['items'][] = $out_item;
        }
        error_log('out_order: ' . print_r($out_order, true));
        return $out_order;
    } else
        return null;
}

function handle_post_order($db, $url) {
    // creates a new supply order (flour, cheese), returns new URI
    $bodyJson = file_get_contents('php://input');
    error_log('Server saw post data' . $bodyJson);
    $body = json_decode($bodyJson, true);
    if ($body === NULL) {  // failure of json_decode 
        $error_message = 'JSON decode error' . json_last_error_msg();
        include_once('errors/server_error.php');
        server_error(400, $error_message);  // client problem: sent bad JSON
        return;
    }
    try {
        $order_date = date("Y-m-d H:i:s");
        $shipAmount = 5.00;
        $taxAmount = 0.00;
        $shipAddressID = 7;
        $cardType = 2;
        $cardNumber = '4111111111111111';
        $cardExpires = '08/2016';
        $billingAddressID = 7;

        $currentDay = get_system_day($db);
        error_log('$currentDay = ' . $currentDay);
        // delivery day is next day if day number is odd, else day after
        $deliveryDay = $currentDay % 2 === 1 ? $currentDay + 1 : $currentDay + 2;

        error_log('flourID = ' . $body['items'][0]['productID']);
        error_log('cheeseID = ' . $body['items'][1]['productID']);
        error_log('quantity of flour = ' . $body['items'][0]['quantity']);
        error_log('quantity of cheese = ' . $body['items'][1]['quantity']);
        error_log('delivery day ' . $deliveryDay);

        $itemPrice = 10.00;
        $discountAmount = 0.00;

        $orderID = add_order($body['customerID'], $order_date, $deliveryDay);
        add_order_item($orderID, $body['items'][0]['productID'], $itemPrice, $discountAmount, $body['items'][0]['quantity']);
        add_order_item($orderID, $body['items'][1]['productID'], $itemPrice, $discountAmount, $body['items'][1]['quantity']);
    } catch (Exception $e) {
        $error_message = 'Order insert failed: ' . $e->getMessage();
        include_once('errors/server_error.php');
        server_error(500, $error_message);
    }
    // return new URI in Location header
    $locHeader = 'Location: ' . $url . $orderID;
    header('Content-type: application/json');
    header($locHeader, true, 201);
    error_log(' handle_post_order, header = ' . $locHeader);
}
