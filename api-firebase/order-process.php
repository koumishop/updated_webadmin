<?php
error_reporting(0);
header('Access-Control-Allow-Origin: *');
include_once('send-email.php');
include_once('../includes/crud.php');
include_once '../includes/functions.php';
include_once('../includes/custom-functions.php');
include_once('../includes/variables.php');
include_once('verify-token.php');
$fn = new custom_functions;
$db = new Database();
$db->connect();
$db->sql("SET NAMES utf8");
$function = new custom_functions();
$settings = $function->get_settings('system_timezone', true);
$app_name = $settings['app_name'];
$support_email = $settings['support_email'];
$config = $function->get_configurations();

if (isset($config['system_timezone']) && isset($config['system_timezone_gmt'])) {
    date_default_timezone_set($config['system_timezone']);
    $db->sql("SET `time_zone` = '" . $config['system_timezone_gmt'] . "'");
} else {
    date_default_timezone_set('Asia/Kolkata');
    $db->sql("SET `time_zone` = '+05:30'");
}

$generate_otp = $config['generate-otp'];
$response = array();
$cancel_order_from = "";
$order_cancelled =  $order_item_cancelled = false;
if (isset($_POST['ajaxCall']) && !empty($_POST['ajaxCall'])) {
    $accesskey = "90336";
    $cancel_order_from = "admin";
} else {
    if (isset($_POST['accesskey']) && !empty($_POST['accesskey'])) {
        $accesskey = $db->escapeString($function->xss_clean($_POST['accesskey']));
    } else {
        $response['error'] = true;
        $response['message'] = "accesskey required";
        print_r(json_encode($response));
        return false;
    }
}

if ($access_key != $accesskey) {
    $response['error'] = true;
    $response['message'] = "invalid accesskey";
    print_r(json_encode($response));
    return false;
}
/* 
  i. place_order
        accesskey:90336
        place_order:1
        user_id:441
        order_note:extra      // {optional}
        product_variant_id:[462,312]
        quantity:[3,3]
        total:552.69     (total price of products including tax)
        delivery_charge:0  (area wise)
        wallet_balance:0
        wallet_used:false
        address_id:996
        final_total:552.69  (total + delivery_charge - promo_discount - discount)
        payment_method:Paypal / Payumoney / COD / PAYTM
        promo_code:NEW20    // {optional}
        promo_discount:123  //{optional}
        delivery_time:morning 10:30 to 5:00
        status:received / awaiting_payment  //{optional}
*/

if (isset($_POST['place_order']) && isset($_POST['user_id']) && !empty($_POST['product_variant_id']) && !empty($_POST['place_order'])) {
    if (!verify_token()) {
        return false;
    }
    $res_msg = "";
    $res_msg .= (empty($_POST['total'])) ? "total," : "";
    $res_msg .= ($_POST['delivery_charge'] == "") ? "delivery_charge," : "";
    $res_msg .= (empty($_POST['delivery_time']) || $_POST['delivery_time'] == "") ? "delivery_time," : "";
    $res_msg .= ($_POST['final_total'] == "") ? "final_total," : "";
    $res_msg .= (empty($_POST['payment_method']) || $_POST['payment_method'] == "") ? "payment_method," : "";
    $res_msg .= (empty($_POST['address_id']) || $_POST['address_id'] == "") ? "address_id," : "";
    $res_msg .= (empty($_POST['quantity']) || $_POST['quantity'] == "") ? "quantity," : "";
    // $res_msg .= ($_POST['service_charge'] == "") ? "service_charge," : "";
    if ($res_msg != "") {
        $response['error'] = true;
        $response['message'] = "This fields " . trim($res_msg, ",") . " should be Passed!";
        print_r(json_encode($response));
        return false;
        exit();
    }
    $sql = "select status from users where id = " . $_POST['user_id'];
    $db->sql($sql);
    $result = $db->getResult();
    if (!isset($result[0]['status']) || $result[0]['status'] == 0) {
        $response['error'] = true;
        $response['message'] = "Vous ne pouvez pas passer de commande car votre compte est désactivé !";
        echo json_encode($response);
        return false;
    }
    $user_id = $db->escapeString($function->xss_clean($_POST['user_id']));
    $order_note = (isset($_POST['order_note']) && !empty($_POST['order_note'])) ? $db->escapeString($function->xss_clean($_POST['order_note'])) : "";
    $wallet_used = (isset($_POST['wallet_used']) && $function->xss_clean($_POST['wallet_used']) == 'true') ? 'true' : 'false';
    $items = $function->xss_clean($_POST['product_variant_id']);
    $total = $db->escapeString($function->xss_clean($_POST['total']));
    $delivery_charge = $db->escapeString($function->xss_clean($_POST['delivery_charge']));
    $address_id = $db->escapeString($function->xss_clean($_POST['address_id']));
    $devise = $db->escapeString($function->xss_clean($_POST['devise']));


    $delivery_charge = $function->get_delivery_charge($address_id, $total);
    $dc = $_POST['delivery_charge'] == 0 && $delivery_charge > 0 ? $delivery_charge : 0;
    $final_total = $db->escapeString($function->xss_clean($_POST['final_total']));
    $final_total = $final_total + $dc;

    $wallet_balance = (isset($_POST['wallet_balance']) && is_numeric($_POST['wallet_balance'])) ? $db->escapeString($function->xss_clean($_POST['wallet_balance'])) : 0;
    $payment_method = $db->escapeString($function->xss_clean($_POST['payment_method']));
    $delivery_time = (isset($_POST['delivery_time'])) ? $db->escapeString($function->xss_clean($_POST['delivery_time'])) : "";
    $promo_code = (isset($_POST['promo_code']) && !empty($_POST['promo_code'])) ? $db->escapeString($function->xss_clean($_POST['promo_code'])) : "";
    $promo_discount = (isset($_POST['promo_discount']) && !empty($_POST['promo_discount'])) ? $db->escapeString($function->xss_clean($_POST['promo_discount'])) : 0;
    $active_status = (isset($_POST['status']) && !empty($_POST['status'])) ? $db->escapeString($function->xss_clean($_POST['status'])) : 'received';
    $order_from = (isset($_POST['order_from']) && !empty($_POST['order_from'])) ? $db->escapeString($function->xss_clean($_POST['order_from'])) : 0;

    $status[] = array($active_status, date("d-m-Y h:i:sa"));
    $quantity = $function->xss_clean($_POST['quantity']);
    $quantity_arr = json_decode($quantity, true);
    $item_arr = json_decode($items, true);
    $Date = date('Y-m-d');

    for ($i = 0; $i < count($item_arr); $i++) {
        $res = $function->get_data($columns = ['id',], 'id=' . $item_arr[$i], 'product_variant');
        if (empty($res[0])) {
            $response['error'] = true;
            $response['message'] = "Un ou plusieurs articles de la commande ne sont pas disponibles pour la commande.";
            echo json_encode($response);
            return false;
        }
    }
    $item_details = $function->get_product_by_variant_id($items);

    $order_total_tax_amt = 0;
    $order_total_tax_per = 0;
    for ($i = 0; $i < count($item_details); $i++) {
        $price = $db->escapeString($item_details[$i]['price']);
        $discounted_price = (empty($item_details[$i]['discounted_price']) || $item_details[$i]['discounted_price'] == "") ? 0 : $db->escapeString($item_details[$i]['discounted_price']);
        $quantity = $db->escapeString($quantity_arr[$i]);
        $tax_percentage = (empty($item_details[$i]['tax_percentage']) || $item_details[$i]['tax_percentage'] == "") ? 0 : $db->escapeString($item_details[$i]['tax_percentage']);
        $final_price = ($discounted_price != 0) ? ($discounted_price * $quantity) : ($price * $quantity);
        $tax_count = ($tax_percentage / 100) * $final_price;
        $order_total_tax_amt += $tax_count;
        $order_total_tax_per += $tax_percentage;
    }

    $otp_number = $sub_total = $promo_code_discount = 0;
    if ($generate_otp == 1) {
        $otp_number = mt_rand(100000, 999999);
    } else {
        $otp_number = 0;
    }
    /* validate promo code if applied */
    if (isset($_POST['promo_code']) && $_POST['promo_code'] != '') {
        $promo_code = $db->escapeString($function->xss_clean($_POST['promo_code']));
        $response = $function->validate_promo_code($user_id, $promo_code, $total);
        $promo_code_discount = $response['discounted_amount'];
        if ($response['error'] == true) {
            echo json_encode($response);
            exit();
        }
    }

    /* check for wallet balance */
    if ($wallet_used == 'true') {
        $user_wallet_balance = $function->get_wallet_balance($user_id, 'users');
        if ($user_wallet_balance < $wallet_balance) {
            $response['error'] = true;
            $response['message'] = "Solde insuffisant du porte-monnaie.";
            echo json_encode($response);
            return false;
        }
    }

    /* check for minimum order amount */
    if ($total < $settings['min_order_amount']) {
        $response['error'] = true;
        $response['message'] = "Minimum order amount is " . $settings['min_order_amount'] . ".";
        echo json_encode($response);
        return false;
    }
    $walletvalue = ($wallet_used) ? $wallet_balance : 0;
    $order_status = $db->escapeString(json_encode($status));

    /* getting user address data */
    $user_address = $function->get_user_address($address_id);
    if (!empty($user_address)) {
        $address = $user_address['user_address'];
        $mobile = $user_address['mobile'];
        $latitude = $user_address['latitude'];
        $longitude = $user_address['longitude'];
        $pincode_id = $user_address['pincode_id'];
        $area_id = $user_address['city_id'];
    } else {
        $response['error'] = true;
        $response['message'] = "L'adresse n'est pas disponible ou le code postal est manquant.";
        echo json_encode($response);
        return false;
    }
    /* insert data into order table */
    $sql = "INSERT INTO `orders`(`user_id`,`currency`,`otp`,`mobile`,`order_note`, `total`, `delivery_charge`, `tax_amount`, `tax_percentage`, `wallet_balance`, `promo_code`,`promo_discount`, `final_total`, `payment_method`, `address`, `latitude`, `longitude`, `delivery_time`, `status`, `active_status`,`order_from`,`pincode_id`,`area_id`) VALUES ('$user_id','$devise','$otp_number','$mobile','$order_note','$total','$delivery_charge','$order_total_tax_amt','$order_total_tax_per','$walletvalue','$promo_code','$promo_discount', '$final_total','$payment_method','$address','$latitude','$longitude','$delivery_time','$order_status','$active_status','$order_from','$pincode_id','$area_id')";
    // $sql = "INSERT INTO `orders`(`user_id`,`otp`,`mobile`,`order_note`, `total`, `delivery_charge`, `tax_amount`, `tax_percentage`, `wallet_balance`, `promo_code`,`promo_discount`, `final_total`, `payment_method`, `address`, `latitude`, `longitude`, `delivery_time`, `status`, `active_status`,`order_from`,`pincode_id`,`area_id`, `service_charges`) VALUES ('$user_id','$otp_number','$mobile','$order_note','$total','$delivery_charge','$order_total_tax_amt','$order_total_tax_per','$walletvalue','$promo_code','$promo_discount', '$final_total','$payment_method','$address','$latitude','$longitude','$delivery_time','$order_status','$active_status','$order_from','$pincode_id','$area_id', '$sc')";
    $db->sql($sql);
    $sql = "SELECT id FROM orders where user_id=$user_id and active_status = '$active_status' order by id desc limit 1";
    $db->sql($sql);
    $res_order_id = $db->getResult();
    $order_id = $res_order_id[0]['id'];
    if (empty($order_id)) {
        $response['error'] = true;
        $response['message'] = "La commande ne peut pas être passée pour une raison quelconque ! Essayez à nouveau après un certain temps..";
        echo json_encode($response);
        return false;
    }

    /* process wallet balance */
    $user_wallet_balance = $function->get_wallet_balance($user_id, 'users');
    if ($wallet_used == 'true') {
        /* deduct the balance & set the wallet transaction */
        $new_balance = $user_wallet_balance < $wallet_balance ? 0 : $user_wallet_balance - $wallet_balance;
        $function->update_wallet_balance($new_balance, $user_id, 'users');
        $wallet_txn_id = $function->add_wallet_transaction($order_id, 0, $user_id, 'debit', $wallet_balance, 'Used against Order Placement', 'wallet_transactions');
    }

    /* process each product in order from variants of products */
    for ($i = 0; $i < count($item_details); $i++) {
        $product_id = $item_details[$i]['product_id'];
        $product_name = $item_details[$i]['name'];
        $measurement = $item_details[$i]['measurement'];
        $variant_name = $measurement . $item_details[$i]['measurement_unit_name'];
        $product_variant_id = $db->escapeString($item_details[$i]['id']);
        $measurement_unit_id = $item_details[$i]['measurement_unit_id'];
        $stock_unit_id = $item_details[$i]['stock_unit_id'];
        $price = $db->escapeString($item_details[$i]['price']);
        $discounted_price = (empty($item_details[$i]['discounted_price']) || $item_details[$i]['discounted_price'] == "") ? 0 : $db->escapeString($item_details[$i]['discounted_price']);
        $type = $item_details[$i]['product_type'];
        $total_stock = $item_details[$i]['stock'];
        $quantity = $db->escapeString($quantity_arr[$i]);
        $tax_title = $item_details[$i]['tax_title'];
        $seller_id = (!empty($item_details[$i]['seller_id'])) ? $db->escapeString($item_details[$i]['seller_id']) : "";
        $tax_percentage = (empty($item_details[$i]['tax_percentage']) || $item_details[$i]['tax_percentage'] == "") ? 0 : $db->escapeString($item_details[$i]['tax_percentage']);
        $tax_amt = $discounted_price != 0 ? (($tax_percentage / 100) * $discounted_price)  : (($tax_percentage / 100) * $price);
        $sub_total = $discounted_price != 0 ? ($discounted_price + ($tax_percentage / 100) * $discounted_price) * $quantity : ($price + ($tax_percentage / 100) * $price) * $quantity;

        $neworder_id = $db->escapeString($order_id);
        $tax_amount = $db->escapeString($tax_amt);
        $order_sub_total = $db->escapeString($sub_total);
        $order_item_status = $db->escapeString(json_encode($status));

        $product_name_regex = preg_replace('/\'/', '',$product_name);           

        $sql = "INSERT INTO `order_items`(`user_id`, `order_id`,`product_name`,`variant_name`, `product_variant_id`, `quantity`, `price`, `discounted_price`,`tax_amount`,`tax_percentage`, `sub_total`, `status`, `active_status`,`seller_id`) VALUES ('$user_id','$neworder_id','$product_name_regex','$variant_name','$product_variant_id','$quantity','$price','$discounted_price','$tax_amount', $tax_percentage,'$order_sub_total','$order_item_status','$active_status','$seller_id')";
        $db->sql($sql);
        $res = $db->getResult();
        if ($type == 'packet') {
            $stock = $total_stock - $quantity;
            $sql = "update product_variant set stock = $stock where id = $product_variant_id";
            $db->sql($sql);
            $res = $db->getResult();
            $db->select("product_variant", "stock", null, "id='" . $product_variant_id . "'");
            $variant_qty = $db->getResult();
            if ($variant_qty[0]['stock'] <= 0) {
                $data = array(
                    "serve_for" => "Sold Out",
                );
                $db->update("product_variant", $data, "id=$product_variant_id");
                $res = $db->getResult();
            }
        } elseif ($type == 'loose') {
            if ($measurement_unit_id == $stock_unit_id) {
                $stock = $quantity * $measurement;
            } else {
                $db->select('unit', '*', null, 'id=' . $measurement_unit_id);
                $unit = $db->getResult();
                $stock = $function->convert_to_parent(($measurement * $quantity), $unit[0]['id']);
            }

            $sql = "update product_variant set stock = stock - $stock where product_id = $product_id AND id=$product_variant_id AND type='loose'";
            $db->sql($sql);
            $res = $db->getResult();
            $sql = "select stock from product_variant where product_id=" . $product_id;
            $db->sql($sql);
            $res_stck = $db->getResult();
            if ($res_stck[0]['stock'] <= 0) {
                $sql = "update product_variant set serve_for='Sold Out' where product_id=" . $product_id;
                $db->sql($sql);
            }
        }
    }
    $data = array(
        'final_total' => $final_total
    );
    if ($db->update('orders', $data, 'id=' . $order_id)) {
        $res = $db->getResult();
        $response['error'] = false;
        $response['message'] = "Commande passée avec succès.";
        $response['order_id'] = $order_id;
        print_r(json_encode($response));
        /* send email notification for the order received */
        if ($active_status == "received") {
            // $sql = 'select name,email,mobile,country_code from users where id = ' . $user_id;
            // $res = $db->getResult();
            // // $res = $db->select("users","*",null,'id=' . $user_id);

            $res = $function->get_data($columns = ['name', 'email', 'mobile', 'country_code'], 'id=' . $user_id, 'users');
            $to = $res[0]['email'];
            $mobile = $res[0]['mobile'];
            $country_code = $res[0]['country_code'];
            $subject = "Votre commande a été reçue";
            $message = $user_msg = "Bonjour, cher(è) " . ucwords($res[0]['name']) . ", nous avons reçu votre commande avec succès.";
            $otp_msg = "Voici votre OTP. S'il vous plaît, donnez-le au livreur seulement quand vous prenez votre commande.";
            // $message .= "<b>Identifiant commande :</b> #" . $response['order_id'] . "<br><br>Articles commandés : <br>";
            $items = $function->xss_clean_array($_POST['product_variant_id']);
            $item_data1 = array();
            for ($i = 0; $i < count($item_details); $i++) {
                $product_id = $item_details[$i]['product_id'];
                $measurement = $item_details[$i]['measurement'];
                $product_variant_id = $item_details[$i]['id'];
                $measurement_unit_id = $item_details[$i]['measurement_unit_id'];
                $stock_unit_id = $item_details[$i]['stock_unit_id'];
                $price = $item_details[$i]['price'];
                $discounted_price = $item_details[$i]['discounted_price'];
                $type = $item_details[$i]['product_type'];
                $total_stock = $item_details[$i]['stock'];
                $seller_id = (!empty($item_details[$i]['seller_id'])) ? $db->escapeString($item_details[$i]['seller_id']) : "";
                $quantity = $quantity_arr[$i];
                $price = $item_details[$i]['discounted_price'] == 0 ? $item_details[$i]['price'] : $item_details[$i]['discounted_price'];
                $tax_percentage = (empty($item_details[$i]['tax_percentage']) || $item_details[$i]['tax_percentage'] == "") ? 0 : $db->escapeString($item_details[$i]['tax_percentage']);
                $tax_amt = $discounted_price != 0 ? (($tax_percentage / 100) * $discounted_price)  : (($tax_percentage / 100) * $price);
                if (!empty($seller_id)) {
                    //$store_details = $function->get_data($columns = ['email', 'store_name'], 'id=' . $seller_id, 'seller');
                    $sql = 'select email,store_name from seller where id = ' . $seller_id;
                    $store_details = $db->getResult();
                }

                // $message .= "<b>Name : </b>" . $item_details[$i]['name'] . "<b> Unit :</b>" . $item_details[$i]['measurement'] . " " . $item_details[$i]['measurement_unit_name'] . "<b> QTY :</b>" . $quantity . "<b> Subtotal :</b>" . $sub_total . "<br>";
                $item_data1[] = array('name' => $item_details[$i]['name'], 'store_name' => $store_details[0]['store_name'], 'tax_amount' => $order_total_tax_amt, 'tax_percentage' => $order_total_tax_per, 'tax_title' => $item_details[$i]['tax_title'], 'unit' =>  $item_details[$i]['measurement'] . " " . $item_details[$i]['measurement_unit_name'], 'qty' => $quantity, 'subtotal' => $sub_total);
                if (!empty($seller_id)) {
                    $seller_subject = "Nouvelle commande de " . $res[0]['name'];
                    $seller_message = "ID de nouvelle commande : #" . $response['order_id'] . " reçu, veuillez en prendre note et poursuivre la procédure";
                    send_email($store_details[0]['email'], $seller_subject, $seller_message);
                    $function->send_notification_to_seller($seller_id, $seller_subject, $seller_message, 'order', $response['order_id']);
                    //  notification to seller test is remain        
                }
            }
            $order_data = array('total_amount' => $total, 'delivery_charge' => $delivery_charge, 'wallet_used' => $wallet_balance, 'final_total' => $final_total, 'payment_method' => $payment_method, 'address' => $address, 'user_msg' => $user_msg, 'otp_msg' => $otp_msg, 'otp' => $otp_number);
            send_smtp_mail($to, $subject, $item_data1, $order_data);
            // send_email($support_email, $subject, $message);
            // $subject = "Nouvelle commande de $app_name";
            // $subject = "Une nouvelle commande est arrivée";
            // $message = "ID de nouvelle commande : #" . $response['order_id'] . " reçu, veuillez en prendre note et poursuivre la procédure";
            $function->send_notification_to_admin("Une nouvelle commande est arrivée.", $message, "admin_notification", $response['order_id']);
            // send_email($support_email, $subject, $message);
            $function->send_order_update_notification($user_id, "Votre commande a été reçue", $message, 'order', $response['order_id']);

            //Send to admin notification mail
            $subject = "Une nouvelle commande est arrivée";
            $functions1 = new functions();
            $system_configs = $functions1->get_system_configs();
            $to = "info@koumishop.com";
            send_smtp_mail($to, $subject, $item_data1, $order_data);
        }
    } else {
        $response['error'] = true;
        $response['message'] = "Impossible de passer la commande. Essayez à nouveau !";
        $response['order_id'] = 0;
        print_r(json_encode($response));
    }
} elseif (isset($_POST['place_order']) && isset($_POST['user_id']) && empty($_POST['product_variant_id'])) {
    $response['error'] = true;
    $response['message'] = "La commande sans articles dans le panier ne peut être passée !";
    $response['order_id'] = 0;
    print_r(json_encode($response));
}

if (isset($_POST['update_order_status']) && isset($_POST['order_item_id']) && isset($_POST['order_id'])) {
    $id = $db->escapeString($function->xss_clean($_POST['order_id']));
    $order_item_id = $db->escapeString($function->xss_clean($_POST['order_item_id']));
    $postStatus = $db->escapeString($function->xss_clean($_POST['status']));
    $is_available = $db->escapeString($function->xss_clean($_POST['is_available']));
    $res = $function->get_data($columns = ['user_id', 'payment_method', 'wallet_balance', 'total', 'delivery_charge', 'tax_amount'], 'id=' . $id, 'orders');
    $res_order_item = $function->get_data($columns = ['active_status', 'status'], 'id=' . $order_item_id, 'order_items');
    $delivery_boy_id = 0;

    if (isset($is_available)) {
        $final_status = array(
            'is_available' => $is_available
        );
        $sql = "SELECT oi.*, o.active_status as orders_active_status FROM order_items oi INNER JOIN orders o ON o.id = oi.order_id WHERE oi.id=" . $order_item_id;
        $db->sql($sql);
        $res_seller = $db->getResult();
        $res_seller[0]['orders_active_status'];
        if (strtolower($res_seller[0]['orders_active_status']) !== 'delivered') {
            if ($db->update('order_items', $final_status, 'id=' . $order_item_id)) {
                $response['error'] = false;
                $response['message'] = "L'article mise à jour avec succès";
                print_r(json_encode($response));
                return false;
            } else {
                $response['error'] = true;
                $response['message'] = "Impossible de mettre à jour l'article";
                print_r(json_encode($response));
                return false;
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Impossible de mettre à jour l'article, car la commande a été déjà livrée";
            print_r(json_encode($response));
            return false;
        }
    }
    if ($postStatus == 'awaiting_payment') {
        $response['error'] = true;
        $response['message'] = "La commande ne peut pas être en attente de statut. Parce qu'elle est sur " . $res_order_item[0]['active_status'] . ".";
        print_r(json_encode($response));
        return false;
    }

    /* check for awaiting status */
    if ($res_order_item[0]['active_status'] == 'awaiting_payment' && ($postStatus == 'returned' || $postStatus == 'delivered' || $postStatus == 'shipped' || $postStatus == 'processed')) {
        $response['error'] = true;
        $response['message'] = "La commande ne peut être $postStatus. Parce qu'elle est en attente.";
        print_r(json_encode($response));
        return false;
    }

    /* update delivery boy to the order item */
    if (isset($_POST['delivery_boy_id']) && !empty($_POST['delivery_boy_id']) && $_POST['delivery_boy_id'] != "") {
        $delivery_boy_id = $db->escapeString($function->xss_clean($_POST['delivery_boy_id']));
        $res_delivery_boy_id = $function->get_data($columns = ['active_status', 'status', 'delivery_boy_id'], 'id=' . $order_item_id, 'order_items');
        if ($res_delivery_boy_id[0]['active_status'] == "awaiting_payment") {
            $response['error'] = true;
            $response['message'] = " Vous ne pouvez pas assigner de livreur. Parce que la commande est en statut attente.";
            print_r(json_encode($response));
            return false;
        } else {
            if (($res_delivery_boy_id[0]['delivery_boy_id'] == 0)
                || ($res_delivery_boy_id[0]['delivery_boy_id'] != $delivery_boy_id && $res_delivery_boy_id[0]['active_status'] != 'cancelled')
            ) {
                $delivery_boy_name = $function->get_data($columns = ['name'], 'id=' . $delivery_boy_id, 'delivery_boys');
                if ($postStatus == 'delivered') {
                    $message_delivery_boy = "Bonjour, cher(è) " . ucwords($delivery_boy_name[0]['name']) . ", votre commande a été livrée. ID : #" . $order_item_id . ". Veuillez en prendre note.";
                } else {
                    $message_delivery_boy = "Bonjour, cher(è) " . ucwords($delivery_boy_name[0]['name']) . ", Vous avez une nouvelle commande à livrer. Voici votre identifiant : #" . $order_item_id . ". Veuillez en prendre note.";
                }
                $function->send_notification_to_delivery_boy($delivery_boy_id, "Votre nouveau poste de commande avec ID : #$order_item_id a été " . ucwords($postStatus), $message_delivery_boy, 'delivery_boys', $order_item_id);
                $function->store_delivery_boy_notification($delivery_boy_id, $order_item_id, "Votre nouveau poste de commande avec ID : #$order_item_id  a été " . ucwords($postStatus), $message_delivery_boy, 'order_reward');
                $sql = "UPDATE order_items SET `delivery_boy_id`='" . $delivery_boy_id . "' WHERE id=" . $order_item_id;
                $db->sql($sql);
            }
        }
    }
    if ($res_order_item[0]['active_status'] == 'delivered' && $postStatus == 'cancelled') {
        $response['error'] = true;
        $response['message'] = '';
        $response['message'] = ($delivery_boy_id != 0) ? 'Mise à jour du livreur, Impossible d\'annuler la commande livrée' : 'Impossible d\'annuler la commande livrée';
        print_r(json_encode($response));
        return false;
    }
    /* Could not update order status once cancelled or returned! */
    if ($function->is_order_item_cancelled($order_item_id)) {
        $response['error'] = true;
        $response['message'] = 'Impossible de mettre à jour le statut de la commande annulée ou retournée !';
        print_r(json_encode($response));
        return false;
    }

    /* Cannot return order unless it is delivered */
    if ($function->is_order_item_returned($res_order_item[0]['active_status'], $postStatus)) {
        $response['error'] = true;
        $response['message'] = 'Impossible de retourner la commande si elle n\'est pas livrée !';
        print_r(json_encode($response));
        return false;
    }
    $sql = "SELECT * FROM `users` WHERE id=" . $res[0]['user_id'];
    $db->sql($sql);
    $res_user = $db->getResult();
    if (!empty($postStatus) && $postStatus != $res_order_item[0]['active_status']) {
        /* return if only delivery boy will update and order status is already changed */
        $sql = "SELECT COUNT(id) as total FROM `orders` WHERE user_id=" . $res[0]['user_id'] . " && status LIKE '%delivered%'";
        $db->sql($sql);
        $res_count = $db->getResult();

        if (!empty($res)) {
            $status = json_decode($res_order_item[0]['status']);
            $user_id =  $res[0]['user_id'];
            foreach ($status as $each) {
                if (in_array($postStatus, $each)) {
                    $response['error'] = true;
                    $response['message'] = ($delivery_boy_id != 0) ? 'Livreur mis à jour, commande déjà ' . $postStatus : 'Commandez déjà ' . $postStatus;
                    print_r(json_encode($response));
                    return false;
                }
            }

            /* if given status is cancel or return */
            if ($postStatus == 'cancelled' || $postStatus == 'returned') {

                /* fetch order items details */
                $sql = 'SELECT oi.`id` as order_item_id,oi.`product_variant_id`,oi.`quantity`,pv.`product_id`,pv.`type`,pv.`stock`,pv.`stock_unit_id`,pv.`measurement`,pv.`measurement_unit_id` FROM `order_items` oi join `product_variant` pv on pv.id = oi.product_variant_id WHERE oi.`id`=' . $order_item_id;
                $db->sql($sql);
                $res_oi = $db->getResult();

                /* check for item cancellable or not */
                if ($postStatus == 'cancelled') {
                    if ($cancel_order_from == "") {
                        $cancelation_error = 0;
                        $resp = $function->is_product_cancellable($res_oi[0]['order_item_id']);
                        if ($resp['till_status_error'] == 1 || $resp['cancellable_status_error'] == 1) {
                            $cancelation_error = 1;
                        }
                        if ($cancelation_error == 1) {
                            $resp['error'] = true;
                            $resp['message'] = "J'ai trouvé un ou plusieurs articles dans une commande qui n'est pas annulable ou qui ne correspond pas aux critères d'annulation !";
                            print_r(json_encode($resp));
                            return false;
                        }
                    }

                    if ($function->cancel_order_item($id, $order_item_id)) {
                        $order_item_cancelled = true;
                    } else {
                        $order_item_cancelled = false;
                    }
                } else if ($postStatus == 'returned') {
                    /* check for item returnable or not */
                    $return_error = 0;
                    $resp = $function->is_product_returnable($res_oi[0]['order_item_id']);
                    if ($resp['return_status_error'] == 1) {
                        $return_error = 1;
                    }
                    if ($return_error == 1) {
                        $resp['error'] = true;
                        $resp['message'] = "Trouvé un ou plusieurs articles dans la commande qui n'est pas retournable !";
                        print_r(json_encode($resp));
                        return false;
                    }
                    $is_item_delivered = 0;
                    $product_details = $function->get_product_by_variant_id2($res_oi[0]['product_variant_id']);

                    $return_days = $function->get_data($columns = ['return_days'], 'id=' . $product_details['product_id'], 'products');
                    $return_day = $return_days[0]['return_days'];
                    foreach ($status as $each_status) {
                        if (in_array('delivered', $each_status)) {
                            $is_item_delivered = 1;
                            $now = time(); // or your date as well
                            $status_date = strtotime($each_status[1]);
                            $datediff = $now - $status_date;
                            $no_of_days = round($datediff / (60 * 60 * 24));
                            if ($no_of_days > $return_day) {
                                $response['error'] = true;
                                $response['message'] = "Oups ! Désolé, vous ne pouvez pas retourner l'article maintenant. Vous avez dépassé la période maximale de retour du produit";
                                print_r(json_encode($response));
                                return false;
                            }
                        }
                    }
                    if (!$is_item_delivered) {
                        $response['error'] = true;
                        $response['message'] = "Impossible de retourner l'article s'il n'est pas livré !";
                        print_r(json_encode($response));
                        return false;
                    }
                    if ($function->is_return_request_exists($res[0]['user_id'], $order_item_id)) {
                        $response['error'] = true;
                        $response['message'] = 'Déjà demandé pour le retour';
                        print_r(json_encode($response));
                        return false;
                    }
                    /* store return request */
                    $function->store_return_request($res[0]['user_id'], $id, $order_item_id);

                    $response['error'] = false;
                    $response['message'] = "La demande de retour de l'article commandé a été reçue avec succès ! Veuillez attendre l'approbation.";

                    /*if (strtolower($res[0]['payment_method']) != 'cod') {
                        // update user's wallet
                        $user_id = $res[0]['user_id'];
                        $total = $res[0]['total'] + $res[0]['delivery_charge'] + $res[0]['tax_amount'];
                        $user_wallet_balance = $function->get_wallet_balance($user_id, 'users');
                        $new_balance = $user_wallet_balance + $total;
                        $function->update_wallet_balance($new_balance, $user_id, 'users');
                        // add wallet transaction 
                        $wallet_txn_id = $function->add_wallet_transaction($id, $order_item_id, $user_id, 'credit', $total, 'Balance credited against item cancellation..', 'wallet_transactions', '1');
                    }else {
                        if ($res[0]['wallet_balance'] != 0) {
                            // update user's wallet 
                            $user_id = $res[0]['user_id'];
                            $total = $res[0]['total'] + $res[0]['delivery_charge'] + $res[0]['tax_amount'];
                            $user_wallet_balance = $function->get_wallet_balance($user_id, 'users');
                            $new_balance = ($user_wallet_balance + $res[0]['wallet_balance']);
                            $function->update_wallet_balance($new_balance, $user_id, 'users');
                            // add wallet transaction 
                            $wallet_txn_id = $function->add_wallet_transaction($id, $order_item_id, $user_id, 'credit', $total, 'Balance credited against item cancellation!', 'wallet_transactions');
                        }
                    }*/

                    if ($res_oi[0]['type'] == 'packet') {
                        $sql = "UPDATE product_variant SET stock = stock + " . $res_oi[0]['quantity'] . " WHERE id='" . $res_oi[0]['product_variant_id'] . "'";
                        $db->sql($sql);
                        $sql = "select stock from product_variant where id=" . $res_oi[0]['product_variant_id'];
                        $db->sql($sql);
                        $res_stock = $db->getResult();
                        if ($res_stock[0]['stock'] > 0) {
                            $sql = "UPDATE product_variant set serve_for='Available' WHERE id='" . $res_oi[0]['product_variant_id'] . "'";
                            $db->sql($sql);
                        }
                    } else {
                        /* When product type is loose */
                        if ($res_oi[0]['measurement_unit_id'] != $res_oi[0]['stock_unit_id']) {
                            $stock = $function->convert_to_parent($res_oi[0]['measurement'], $res_oi[0]['measurement_unit_id']);
                            $stock = $stock * $res_oi[0]['quantity'];
                            $sql = "UPDATE product_variant SET stock = stock + " . $stock . " WHERE product_id='" . $res_oi[0]['product_id'] . "'" .  " AND id='" . $res_oi[0]['product_variant_id'] . "'";
                            $db->sql($sql);
                        } else {
                            $stock = $res_oi[0]['measurement'] * $res_oi[0]['quantity'];
                            $sql = "UPDATE product_variant SET stock = stock + " . $stock . " WHERE product_id='" . $res_oi[0]['product_id'] . "'" .  " AND id='" . $res_oi[0]['product_variant_id'] . "'";
                            $db->sql($sql);
                        }
                    }
                }
            }
            if ($postStatus == 'delivered') {
                $sql = "SELECT oi.delivery_boy_id,oi.sub_total,o.final_total,o.total,o.payment_method,o.delivery_charge, s.id, s.commission FROM orders o join order_items oi on oi.order_id=o.id join seller s on s.id = oi.seller_id WHERE oi.id=" . $order_item_id;
                $db->sql($sql);
                $res_boy = $db->getResult();
                if ($res_boy[0]['delivery_boy_id'] != 0) {
                    if (strtolower($res_boy[0]['payment_method']) == 'cod') {
                        $cash_received = $res_boy[0]['sub_total'] + $res_boy[0]['delivery_charge'];
                        $sql = "UPDATE delivery_boys SET cash_received = cash_received + $cash_received WHERE id=" . $res_boy[0]['delivery_boy_id'];
                        $db->sql($sql);
                        $function->add_transaction($order_item_id, $res_boy[0]['delivery_boy_id'], 'delivery_boy_cash', $cash_received, 'Delivery boy collected COD');
                    }
                    $sql = "select name,bonus from delivery_boys where id=" . $res_boy[0]['delivery_boy_id'];
                    $db->sql($sql);
                    $res_bonus = $db->getResult();
                    $reward = $res_boy[0]['sub_total'] / 100 * $res_bonus[0]['bonus'];
                    if ($reward > 0) {
                        $sql = "UPDATE delivery_boys SET balance = balance + $reward WHERE id=" . $res_boy[0]['delivery_boy_id'];
                        $db->sql($sql);
                        $comission = $function->add_delivery_boy_commission($delivery_boy_id, 'credit', $reward, 'Order Delivery Commission.');
                        $currency = $function->get_settings('currency');
                        $message_delivery_boy = "Bonjour, cher(è) " . ucwords($res_bonus[0]['name']) . ", Voici la nouvelle mise à jour de votre commande pour l'ID du poste de commande : #" . $order_item_id . ". Votre Commission de " . $reward . " est créditée. Veuillez en prendre note.";
                        $function->send_notification_to_delivery_boy($delivery_boy_id, "Votre Commission de " . $reward . " " . $currency . " a été creditée ", "$message_delivery_boy", 'delivery_boys', $order_item_id);
                        $function->store_delivery_boy_notification($delivery_boy_id, $order_item_id, "Votre Commission de " . $reward . " " . $currency . " a été creditée ", $message_delivery_boy, 'order_reward');
                    }
                    $id = $res_boy[0]["id"];
                    $type = "credit";
                    $message = "Balance $type to seller";
                    $balance = $function->get_wallet_balance($id, 'seller');
                    $amount = $res_boy[0]['sub_total'] - ($res_boy[0]['sub_total'] / 100 * $res_boy[0]['commission']);
                    $new_balance =  $balance + $amount;
                    $function->update_wallet_balance($new_balance, $id, 'seller');
                    $function->add_wallet_transaction("", 0, $id, $type, $amount, $message, 'seller_wallet_transactions');
                }

                /* referal system processing */
                if ($config['is-refer-earn-on'] == 1) {
                    if ($res_boy[0]['total'] >= $config['min-refer-earn-order-amount']) {
                        if ($res_count[0]['total'] == 0) {
                            if ($res_user[0]['friends_code'] != '') {
                                if ($config['refer-earn-method'] == 'percentage') {
                                    $percentage = $config['refer-earn-bonus'];
                                    $bonus_amount = $res_boy[0]['total'] / 100 * $percentage;
                                    if ($bonus_amount > $config['max-refer-earn-amount']) {
                                        $bonus_amount = $config['max-refer-earn-amount'];
                                    }
                                } else {
                                    $bonus_amount = $config['refer-earn-bonus'];
                                }
                                $res_data = $function->get_data($columns = ['friends_code', 'name'], "referral_code='" . $res[0]['user_id'] . "'", 'users');
                                $friend_user = $function->get_data($columns = ['id'], "referral_code='" . $res_data[0]['friends_code'] . "'", 'users');
                                if (!empty($friend_user))
                                    $function->add_wallet_transaction($id, 0, $friend_user[0]['id'], 'credit', floor($bonus_amount), 'Refer & Earn Bonus on first order by ' . ucwords($res_data[0]['name']), 'wallet_transactions');

                                $friend_code = $res_data[0]['friends_code'];
                                $sql = "UPDATE users SET balance = balance + floor($bonus_amount) WHERE referral_code='$friend_code' ";
                                $db->sql($sql);
                            }
                        }
                    }
                }
            }
            $temp = [];
            foreach ($status as $s) {
                array_push($temp, $s[0]);
            }
            if ($postStatus == 'cancelled') {
                if ($order_item_cancelled == true) {
                    if (!in_array('cancelled', $temp)) {
                        $status[] = array('cancelled', date("d-m-Y h:i:sa"));
                        $data = array(
                            'status' =>  $db->escapeString(json_encode($status)),
                        );
                    }
                    $db->update('order_items', $data, 'id=' . $order_item_id);
                }
            }

            if ($postStatus == 'processed') {
                if (!in_array('processed', $temp)) {
                    $status[] = array('processed', date("d-m-Y h:i:sa"));
                    $data = array(
                        'status' => $db->escapeString(json_encode($status))
                    );
                }
                $db->update('order_items', $data, 'id=' . $order_item_id);
            }

            if ($postStatus == 'received') {
                if (!in_array('received', $temp)) {
                    $status[] = array('received', date("d-m-Y h:i:sa"));
                    $data = array(
                        'status' => $db->escapeString(json_encode($status)),
                        'active_status' => 'received'
                    );
                }
                // $db->update('order_items', $data, 'id=' . $order_item_id);
                $db->update('order_items', $data, 'order_id=' . $id);
                // $db->update('order_items', "received", 'order_id=' . $id);

                /* get order data */
                $user_id1 = $function->get_data($columns = ['user_id', 'total', 'delivery_charge', 'discount', 'final_total', 'payment_method', 'address', 'otp'], 'id=' . $id, 'orders');

                /* get user data */
                $user_email = $function->get_data($columns = ['email', 'name'], 'id=' . $user_id1[0]['user_id'], 'users');
                $subject = "Order received successfully";

                /* get order item by order id */
                $order_item = $function->get_order_item_by_order_id($id);
                $item_ids = array_column($order_item, 'product_variant_id');

                /* get product details by varient id */
                $item_details = $function->get_product_by_variant_id(json_encode($item_ids));

                for ($i = 0; $i < count($item_details); $i++) {
                    $seller_id = $item_details[$i]['seller_id'];
                    if (!empty($seller_id)) {
                        $store_details = $function->get_data($columns = ['email', 'store_name'], 'id=' . $seller_id, 'seller');
                    }
                    $item_data1[] = array(
                        'name' => $item_details[$i]['name'], 'store_name' => $store_details[0]['store_name'], 'tax_amount' => $order_item[$i]['tax_amount'], 'tax_percentage' => $order_item[$i]['tax_percentage'], 'tax_title' => $item_details[$i]['tax_title'], 'unit' =>  $item_details[$i]['measurement'] . " " . $item_details[$i]['measurement_unit_name'],
                        'qty' => $order_item[$i]['quantity'], 'subtotal' => $order_item[$i]['sub_total']
                    );
                    if (!empty($seller_id)) {
                        $seller_subject = "Nouvelle commande de " . $store_details[0]['store_name'];
                        $seller_message = "ID du poste de la nouvelle commande : #" . $order_item_id . " reçu, veuillez en prendre note et poursuivre la procédure";
                        send_email($store_details[0]['email'], $seller_subject, $seller_message);
                        $function->send_notification_to_seller($seller_id, $seller_subject, $seller_message, 'order', $order_item_id);
                        //  notification to seller test is  remain
                    }
                }
                $user_wallet_balance = $function->get_wallet_balance($user_id1[0]['user_id'], 'users');
                $user_msg = "Bonjour, cher(è) " . $user_email[0]['name'] . ", Nous avons reçu votre commande avec succès. Les résumés de votre commande sont les suivants:<br><br>";
                $otp_msg = "Voici votre OTP. S'il vous plaît, ne le donnez au livreur que lorsque vous prenez votre commande.";

                $order_data = array('total_amount' => $user_id1[0]['total'], 'delivery_charge' => $user_id1[0]['delivery_charge'], 'discount' => $user_id1[0]['discount'], 'wallet_used' => $user_wallet_balance, 'final_total' => $user_id1[0]['final_total'], 'payment_method' => $user_id1[0]['payment_method'], 'address' => $user_id1[0]['address'], 'user_msg' => $user_msg, 'otp_msg' => $otp_msg, 'otp' => $user_id1[0]['otp']);
                send_smtp_mail($user_email[0]['email'], $subject, $item_data1, $order_data);
                $function->send_order_update_notification($user_id1[0]['user_id'], "Votre commande a été " . ucwords($postStatus), $user_msg, 'order', $id);
                $subject = "Nouvelle commande de $app_name";
                $message = "ID de nouvelle commande : #" . $id . " reçu, veuillez en prendre note et poursuivre la procédure";
                $function->send_notification_to_admin("Une nouvelle commande est arrivée.", $message, "admin_notification", $id);
                send_email($support_email, $subject, $message);
            }
            if ($postStatus == 'shipped') {
                if (!in_array('processed', $temp)) {
                    $status[] = array('processed', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                if (!in_array('shipped', $temp)) {
                    $status[] = array('shipped', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                $db->update('order_items', $data, 'id=' . $order_item_id);
            }
            if ($postStatus == 'delivered') {
                if (!in_array('processed', $temp)) {
                    $status[] = array('processed', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                if (!in_array('shipped', $temp)) {
                    $status[] = array('shipped', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                if (!in_array('delivered', $temp)) {
                    $status[] = array('delivered', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                $db->update('order_items', $data, 'id=' . $order_item_id);
                $item_data = array(
                    'status' => $db->escapeString(json_encode($status)),
                    'active_status' => 'delivered'
                );
            }

            /*if ($postStatus == 'returned') {
                $status[] = array('returned', date("d-m-Y h:i:sa"));
                $data = array('status' => $db->escapeString(json_encode($status)));
                $db->update('order_items', $data, 'id=' . $order_item_id);
                $item_data = array(
                    'status' => $db->escapeString(json_encode($status)),
                    'active_status' => 'returned'
                );
            }*/

            $i = sizeof($status);
            $currentStatus = $status[$i - 1][0];
            $final_status = array(
                'active_status' => $currentStatus
            );
            if ($db->update('order_items', $final_status, 'id=' . $order_item_id)) {
                $response['error'] = false;
                if ($postStatus == 'cancelled') {
                    $response['message'] = "La commande a été annulée!";
                } elseif ($postStatus == 'returned') {
                    $response['message'] = "La demande de retour de l'article commandé a été reçue avec succès ! Veuillez attendre l'approbation.";
                } else {
                    $response['message'] = "La commande a été mise à jour avec succès.";
                }
                if ($postStatus != 'received' && $postStatus != 'returned') {
                    $user_data = $function->get_data($columns = ['name', 'email', 'mobile', 'country_code'], 'id=' . $user_id, 'users');
                    $to = $user_data[0]['email'];
                    $mobile = $user_data[0]['mobile'];
                    $country_code = $user_data[0]['country_code'];
                    if ($postStatus == "") {
                        $function->send_order_update_notification($user_id, "Votre commande a été assignée à un livreur", $message, 'order', $id);
                        $subject = "Votre commande a été assignée à un livreur " . ucwords($postStatus);
                        $message = "Bonjour, cher(è) " . ucwords($user_data[0]['name']) . ",  Voici la nouvelle mise à jour de votre commande pour l'ID de commande : #" . $id . ". Votre commande a été assignée à " . ucwords($delivery_boy_name[0]['name']) . ". Veuillez en prendre note.";
                        $message .= "Merci d'utiliser nos services !";
                    } else {
                        $subject = "Votre commande a été " . ucwords($postStatus);
                        $message = "Bonjour, cher(è) " . ucwords($user_data[0]['name']) . ",  Voici la nouvelle mise à jour de votre commande pour l'ID de commande : #" . $id . ". Votre commande été " . ucwords($postStatus) . ". Veuillez en prendre note.";
                        $message .= "Merci d'utiliser nos services ! Vous recevrez des mises à jour sur votre commande par e-mail !";
                        $function->send_order_update_notification($user_id, "Votre commande été " . ucwords($postStatus), $message, 'order', $id);
                    }

                    // $function->send_order_update_notification($user_id, "Votre commande été " . ucwords($postStatus), $message, 'order', $id);
                    send_email($to, $subject, $message);
                    $message = "Bonjour, cher(è) " . ucwords($user_data[0]['name']) . ",  Voici la nouvelle mise à jour de votre commande pour l'ID de commande : #" . $id . ". Votre commande été " . ucwords($postStatus) . ". Veuillez en prendre note.";
                    $message .= "Merci d'utiliser nos services ! Contactez nous pour plus d'informations";
                    // need to send notification to seller for update order
                }
                $res = $db->getResult();

                print_r(json_encode($response));
            } else {
                $response['error'] = true;
                $response['message'] = isset($_POST['delivery_boy_id']) && $_POST['delivery_boy_id'] != '' ? "Le livreur a été mis à jour, mais il n'a pas pu mettre à jour le statut de la commande, essayez à nouveau !" : 'Impossible de mettre à jour le statut de la commande, essayez à nouveau !';
                print_r(json_encode($response));
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Désolé l'identifiant de votre commande est invalide";
            print_r(json_encode($response));
        }
    } else {
        if ($delivery_boy_id != 0 && $res_delivery_boy_id[0]['delivery_boy_id'] != $delivery_boy_id) {
            $response['error'] = false;
            $response['message'] = "Livreur mise à jour avec succès";
            print_r(json_encode($response));
        } else {
            $response['error'] = false;
            $response['message'] = "Aucune modification n'a été apportée";
            print_r(json_encode($response));
        }
    }
}

if (isset($_POST['update_order_items']) && $_POST['update_order_items'] == 1) {
    $order_items = $function->xss_clean_array($_POST['order_items']);
    $delivery_boy_id = "";
    $postStatus = "";
    // $res_order_item_rank = $function->get_data($columns = ['order_id'], 'id=' . $order_items, 'order_items');
    if (isset($_POST['status']) && !empty($_POST['status'])) {
        $postStatus = $db->escapeString($_POST['status']);
    } else {
        if (isset($_POST['status_bottom']) && !empty($_POST['status_bottom'])) {
            $postStatus = $db->escapeString($_POST['status_bottom']);
        }
    }
    if (isset($_POST['delivery_boy_id']) && !empty($_POST['delivery_boy_id'])) {
        $delivery_boy_id = $db->escapeString($_POST['delivery_boy_id']);
    } else {
        if (isset($_POST['delivery_boy_id_bottom']) && !empty($_POST['delivery_boy_id_bottom'])) {
            $delivery_boy_id = $db->escapeString($_POST['delivery_boy_id_bottom']);
        }
    }

    if (empty($delivery_boy_id) && empty($postStatus)) {
        $response['error'] = true;
        $response['message'] = '<p class="alert alert-info">Aucune modification n\'a été apportée.</p>';
        print_r(json_encode($response));
        return false;
    }

    if (!empty($order_items)) {
        for ($j = 0; $j < count($order_items); $j++) {
            $order_item_id = $order_items[$j];
            $res_order_item = $function->get_data($columns = ['user_id', 'order_id', 'seller_id','delivery_boy_id', 'active_status', 'status'], 'id=' . $order_item_id, 'order_items');
            $order_id = $res_order_item[0]['order_id'];
            $user_id = $res_order_item[0]['user_id'];
            $seller_id = $res_order_item[0]['seller_id'];
            $delivery_boy_id_res = $res_order_item[0]['delivery_boy_id'];

            if (!empty($seller_id)) {
                $delivery_boy_name = $function->get_data($columns = ['name'], 'id=' . $delivery_boy_id, 'delivery_boys');
                $seller_subject = "Mise à jour de la commande";
                $seller_message = "ID de nouvelle commande : #" . $order_id . " a été affectée à ". $delivery_boy_name[0]['name'] . " , veuillez en prendre note et poursuivre la procédure";
                // send_email($store_details[0]['email'], $seller_subject, $seller_message);
                $function->send_notification_to_seller($seller_id, $seller_subject, $seller_message, 'order', $order_id);
            }
            if ($postStatus != '' && $postStatus == 'awaiting_payment') {
                $response['error'] = true;
                $response['message'] = "<p class='alert alert-danger'>La commande ne peut pas être en attente de statut. Parce qu'elle est sur " . $res_order_item[0]['active_status'] . ". ID de l'article de la commande " . $order_item_id . '</p>';
                print_r(json_encode($response));
                return false;
            }
            if (!empty($postStatus)) {
                // if (!empty($res)) {
                $status = json_decode($res_order_item[0]['status']);
                // $user_id =  $res[0]['user_id'];
                foreach ($status as $each) {
                    if (in_array($postStatus, $each)) {
                        $response['error'] = true;
                        $response['message'] = '<p class="alert alert-danger">Commandez déjà ' . $postStatus . ". ID de l'article de la commande " . $order_item_id . '</p>';
                        print_r(json_encode($response));
                        return false;
                    }
                }
                /* Cannot return order unless it is delivered */
                if ($function->is_order_item_returned($res_order_item[0]['active_status'], $postStatus)) {
                    $response['error'] = true;
                    $response['message'] = '<p class="alert alert-danger">Impossible de retourner la commande si elle n\'est pas livrée ! veuillez vérifier l\'ID de l\'article de la commande ' . $order_item_id . '</p>';
                    print_r(json_encode($response));
                    return false;
                }
                // }
            }
            if ($res_order_item[0]['active_status'] == 'delivered' && $postStatus == 'cancelled') {
                $response['error'] = true;
                $response['message'] = '';
                $response['message'] = '<p class="alert alert-danger">Impossible d\'annuler l\'article livré. Veuillez vérifier l\'ID de l\'article de la commande ' . $order_item_id . '</p>';
                print_r(json_encode($response));
                return false;
            }
            /* check for awaiting status */
            if ($res_order_item[0]['active_status'] == 'awaiting_payment' && ($postStatus == 'returned' || $postStatus == 'delivered' || $postStatus == 'shipped' || $postStatus == 'processed')) {
                $response['error'] = true;
                $response['message'] = "<p class='alert alert-danger'>La commande ne peut être $postStatus. Parce qu'elle est en attente. ID de l'article de la commande " . $order_item_id . '</p>';
                print_r(json_encode($response));
                return false;
            }
            /* Could not update order status once cancelled or returned! */
            if ($function->is_order_item_cancelled($order_item_id)) {
                $response['error'] = true;
                $response['message'] = '<p class="alert alert-danger">Impossible de mettre à jour le statut de la commande annulée ou retournée ! veuillez vérifier l\'ID de l\'article de la commande ' . $order_item_id . '</p>';
                print_r(json_encode($response));
                return false;
            }

            /* if given status is cancel or return */
            if ($postStatus == 'cancelled' || $postStatus == 'returned') {

                /* fetch order items details */
                $sql = 'SELECT oi.`id` as order_item_id,oi.`product_variant_id`,oi.`quantity`,oi.`user_id`,oi.`order_id`,pv.`product_id`,pv.`type`,pv.`stock`,pv.`stock_unit_id`,pv.`measurement`,pv.`measurement_unit_id` FROM `order_items` oi join `product_variant` pv on pv.id = oi.product_variant_id WHERE oi.`id`=' . $order_item_id;
                $db->sql($sql);
                $res_oi = $db->getResult();

                /* check for item cancellable or not */
                if ($postStatus == 'cancelled') {
                    if ($cancel_order_from == "") {
                        $cancelation_error = 0;
                        $resp = $function->is_product_cancellable($res_oi[0]['order_item_id']);
                        if ($resp['till_status_error'] == 1 || $resp['cancellable_status_error'] == 1) {
                            $cancelation_error = 1;
                        }
                        if ($cancelation_error == 1) {
                            $resp['error'] = true;
                            $resp['message'] = "<p class='alert alert-danger'>ID de l'article de la commande " . $order_item_id . " is not cancelable or not matching cancelation criteria!</p>";
                            print_r(json_encode($resp));
                            return false;
                        }
                    }

                    if ($function->cancel_order_item($order_id, $order_item_id)) {
                        $order_item_cancelled = true;
                    } else {
                        $order_item_cancelled = false;
                    }
                } else if ($postStatus == 'returned') {
                    /* check for item returnable or not */
                    $return_error = 0;
                    $resp = $function->is_product_returnable($res_oi[0]['order_item_id']);
                    if ($resp['return_status_error'] == 1) {
                        $return_error = 1;
                    }
                    if ($return_error == 1) {
                        $resp['error'] = true;
                        $resp['message'] = "<p class='alert alert-danger'>ID de l'article de la commande " . $order_item_id . " is not returnable</p>";
                        print_r(json_encode($resp));
                        return false;
                    }
                    if ($function->is_return_request_exists($res_oi[0]['user_id'], $order_item_id)) {
                        $response['error'] = true;
                        $response['message'] = '<p class="alert alert-danger">ID de l\'article de la commande ' . $order_item_id . ' Déjà demandé pour le retour.</p>';
                        print_r(json_encode($response));
                        return false;
                    }
                    $is_item_delivered = 0;
                    $product_details = $function->get_product_by_variant_id2($res_oi[0]['product_variant_id']);

                    $return_days = $function->get_data($columns = ['return_days'], 'id=' . $product_details['product_id'], 'products');
                    $return_day = $return_days[0]['return_days'];
                    foreach ($status as $each_status) {
                        if (in_array('delivered', $each_status)) {
                            $is_item_delivered = 1;
                            $status_date = $each_status[1];
                            $status_date = date("d-m-Y", strtotime($status_date));
                            $last_date = date('Y-m-d', strtotime($status_date . ' + ' . $return_day . ' days'));
                            $today = date('d-m-Y');
                            $today = strval($today);
                            $last_date = strval($last_date);

                            $datediff = $now - $status_date;
                            $no_of_days = round($datediff / (60 * 60 * 24));
                            // echo $no_of_days;
                            if ($today > $status_date) {
                                $response['error'] = true;
                                $response['message'] = '<p class="alert alert-danger">Oups ! Désolé, vous ne pouvez pas retourner l\'article maintenant. Vous avez franchi la période maximale de retour du produit dont l\'ID de l\'article de la commande ' . $order_item_id . '</p>';
                                print_r(json_encode($response));
                                return false;
                            }
                        }
                    }

                    /* store return request */
                    $function->store_return_request($res_oi[0]['user_id'], $res_oi[0]['order_id'], $order_item_id);

                    // $response['error'] = false;
                    // $response['message'] = '<La demande de retour de l'article commandé a été reçue avec succès ! Veuillez attendre l'approbation.';

                    if ($res_oi[0]['type'] == 'packet') {
                        $sql = "UPDATE product_variant SET stock = stock + " . $res_oi[0]['quantity'] . " WHERE id='" . $res_oi[0]['product_variant_id'] . "'";
                        $db->sql($sql);
                        $sql = "select stock from product_variant where id=" . $res_oi[0]['product_variant_id'];
                        $db->sql($sql);
                        $res_stock = $db->getResult();
                        if ($res_stock[0]['stock'] > 0) {
                            $sql = "UPDATE product_variant set serve_for='Available' WHERE id='" . $res_oi[0]['product_variant_id'] . "'";
                            $db->sql($sql);
                        }
                    } else {
                        /* When product type is loose */
                        if ($res_oi[0]['measurement_unit_id'] != $res_oi[0]['stock_unit_id']) {
                            $stock = $function->convert_to_parent($res_oi[0]['measurement'], $res_oi[0]['measurement_unit_id']);
                            $stock = $stock * $res_oi[0]['quantity'];
                            $sql = "UPDATE product_variant SET stock = stock + " . $stock . " WHERE product_id='" . $res_oi[0]['product_id'] . "'" .  " AND id='" . $res_oi[0]['product_variant_id'] . "'";
                            $db->sql($sql);
                        } else {
                            $stock = $res_oi[0]['measurement'] * $res_oi[0]['quantity'];
                            $sql = "UPDATE product_variant SET stock = stock + " . $stock . " WHERE product_id='" . $res_oi[0]['product_id'] . "'" .  " AND id='" . $res_oi[0]['product_variant_id'] . "'";
                            $db->sql($sql);
                        }
                    }
                }
            }
            $res_delivery_boy_id = $function->get_data($columns = ['active_status', 'status', 'delivery_boy_id'], 'id=' . $order_item_id, 'order_items');
            if (!empty($delivery_boy_id)) {
                if ($res_delivery_boy_id[0]['active_status'] == "awaiting_payment") {
                    $response['error'] = true;
                    $response['message'] = "<p class='alert alert-danger'>Vous ne pouvez pas assigner de livreur. Parce que la commande est en statut Attente. ID de l'article de la commande " . $order_item_id . '</p>';
                    print_r(json_encode($response));
                    return false;
                } else {
                    if ($res_delivery_boy_id[0]['active_status'] != 'cancelled') {
                        if ($postStatus != '') {
                            $delivery_boy_name = $function->get_data($columns = ['name'], 'id=' . $delivery_boy_id, 'delivery_boys');
                            if ($postStatus == 'delivered') {
                                $title_delivery_boy = "Commande d'un article avec ID : #" . $order_item_id . "  a été livrée";
                                $message_delivery_boy = "Bonjour, cher(è) " . ucwords($delivery_boy_name[0]['name']) . ", la commande a été livrée. ID : #" . $order_item_id . ". Veuillez en prendre note.";
                            } else {
                                if ($postStatus == 'received') {
                                    $title_delivery_boy = "Nouvelle Commande d'un article avec ID : #" . $order_item_id . "  a été reçue";
                                    $message_delivery_boy = "Bonjour, cher(è) " . ucwords($delivery_boy_name[0]['name']) . ", Vous avez une nouvelle commande à livrer. Voici l'identifiant de la commande : #" . $order_id . ". Veuillez en prendre note.";
                                } else {
                                    $title_delivery_boy = "Commande d'un article avec ID : #" . $order_item_id . "  a été " . ucwords($postStatus);
                                    $message_delivery_boy = "Bonjour, cher(è) " . ucwords($delivery_boy_name[0]['name']) . ", Votre ID de l'article de la commande #" . $order_item_id . ". a été " . $postStatus . " Veuillez en prendre note.";
                                }
                            }
                            if ($j == 0) {
                                $function->send_notification_to_delivery_boy($delivery_boy_id, $title_delivery_boy, $message_delivery_boy, 'delivery_boys', $order_item_id);
                                $function->store_delivery_boy_notification($delivery_boy_id, $order_item_id, $title_delivery_boy, $message_delivery_boy, 'order_reward');
                            }
                        }

                        $sql = "UPDATE order_items SET `delivery_boy_id`='" . $delivery_boy_id . "' WHERE id=" . $order_item_id;
                        $db->sql($sql);


                        if (!empty($_POST["delivery_rank"]) && !empty($order_items)) {
                            $res_order_item_rank = $function->get_data($columns = ['order_id'], 'id=' . $order_items[0], 'order_items');
                            $id = $res_order_item_rank[0]["order_id"];
                            $delivery_rank = $db->escapeString($function->xss_clean($_POST["delivery_rank"]));

                            $sql = "UPDATE orders SET `delivery_rank`='" . $delivery_rank . "' WHERE id=" . $id;
                            $db->sql($sql);
                            $res = $db->getResult();

                            if (!$db->sql($sql)) {
                                $response['error'] = true;
                                $response['message'] = '<p class="alert alert-info">Aucune modification n\'a été apportée </p>';
                                print_r(json_encode($response));
                                return false;
                            }
                        }
                    }
                }
            } else {
                if (isset($res_delivery_boy_id[0]['delivery_boy_id']) && !empty($res_delivery_boy_id[0]['delivery_boy_id'])) {
                    if ($postStatus != '') {
                        $delivery_boy_name = $function->get_data($columns = ['name'], 'id=' . $res_delivery_boy_id[0]['delivery_boy_id'], 'delivery_boys');
                        if ($postStatus == 'delivered') {
                            $title_delivery_boy = "Commande d'un article avec ID : #" . $order_item_id . "  a été livrée";
                            $message_delivery_boy = "Bonjour, cher(è) " . ucwords($delivery_boy_name[0]['name']) . ", votre commande a été livrée. ID : #" . $order_item_id . ". Veuillez en prendre note.";
                        } else {
                            if ($postStatus == 'received') {
                                $title_delivery_boy = "Nouvelle Commande d'un article avec ID : #" . $order_item_id . "  a été reçue";
                                $message_delivery_boy = "Bonjour, cher(è) " . ucwords($delivery_boy_name[0]['name']) . ", Vous avez une nouvelle commande à livrer. Voici votre identifiant : #" . $order_item_id . ". Veuillez en prendre note.";
                            } else {
                                $title_delivery_boy = "Commande d'un article avec ID : #" . $order_item_id . "  a été " . ucwords($postStatus);
                                $message_delivery_boy = "Bonjour, cher(è) " . ucwords($delivery_boy_name[0]['name']) . ", Your ID de l'article de la commande #" . $order_item_id . ". a été " . $postStatus . " Veuillez en prendre note.";
                            }
                        }
                        if ($j == 0) {
                            $function->send_notification_to_delivery_boy($res_delivery_boy_id[0]['delivery_boy_id'], $title_delivery_boy, $message_delivery_boy, 'delivery_boys', $order_item_id);
                            $function->store_delivery_boy_notification($res_delivery_boy_id[0]['delivery_boy_id'], $order_item_id, $title_delivery_boy, $message_delivery_boy, 'order_reward');
                        }
                    }
                }
            }
            if ($postStatus == 'delivered') {
                $sql = "SELECT oi.order_id,oi.user_id,oi.delivery_boy_id,oi.sub_total,o.final_total,o.total,o.payment_method,o.delivery_charge,oi.seller_id, s.commission FROM orders o join order_items oi on oi.order_id=o.id join seller s on s.id = oi.seller_id  WHERE oi.id=" . $order_item_id;
                $db->sql($sql);
                $res_boy = $db->getResult();
                if ($res_boy[0]['delivery_boy_id'] != 0) {
                    if (strtolower($res_boy[0]['payment_method']) == 'cod') {
                        $cash_received = $res_boy[0]['sub_total'] + $res_boy[0]['delivery_charge'];
                        $sql = "UPDATE delivery_boys SET cash_received = cash_received + $cash_received WHERE id=" . $res_boy[0]['delivery_boy_id'];
                        $db->sql($sql);
                        $function->add_transaction($order_item_id, $res_boy[0]['delivery_boy_id'], 'delivery_boy_cash', $cash_received, 'Delivery boy collected COD');
                    }
                    $res_bonus = $function->get_data($columns = ['name', 'bonus'], 'id=' . $res_boy[0]['delivery_boy_id'], 'delivery_boys');
                    $sql = 'select name,bonus from delivery_boys where id = ' . $res_boy[0]['delivery_boy_id'];
                    $db->sql($sql);
                    $res_bonus = $db->getResult();
                    $reward = $res_boy[0]['sub_total'] / 100 * $res_bonus[0]['bonus'];
                    $new_balance = $res_boy[0]['sub_total'] - ($res_boy[0]['sub_total'] / 100 * $res_boy[0]['commission']);
                    $amount = $new_balance;
                    $id = $res_boy[0]["seller_id"];
                    $message = "La commande avec l'id $res_boy[0]['order_id'] a été livrée";
                    if ($reward > 0) {
                        $sql = "UPDATE delivery_boys SET balance = balance + $reward WHERE id=" . $res_boy[0]['delivery_boy_id'];
                        $fn->update_wallet_balance($new_balance, $id, 'seller');
                        // $fn->update_wallet_balance($new_balance, $type_id, 'seller');
                        $fn->add_wallet_transaction($order_id = "", 0, $id, 'credit', $amount, $message, 'seller_wallet_transactions');
                        $db->sql($sql);
                        $delivery_boy_id = $res_boy[0]['delivery_boy_id'];
                        $comission = $function->add_delivery_boy_commission($delivery_boy_id, 'credit', $reward, 'Order Delivery Commission.');
                        $currency = $function->get_settings('currency');
                        $message_delivery_boy = "Bonjour, cher(è) " . ucwords($res_bonus[0]['name']) . ", Voici la nouvelle mise à jour de votre commande pour l'ID du poste de commande : #" . $order_item_id . ". Votre Commission de" . $reward . " is credited. Veuillez en prendre note.";
                        $function->send_notification_to_delivery_boy($delivery_boy_id, "Votre Commission de " . $reward . " " . $currency . " a été creditée", "$message_delivery_boy", 'delivery_boys', $order_item_id);
                        $function->store_delivery_boy_notification($delivery_boy_id, $order_item_id, "Votre Commission de " . $reward . " " . $currency . " a été creditée", $message_delivery_boy, 'order_reward');
                    }
                }
                $sql = "SELECT COUNT(id) as total FROM `order_items` WHERE user_id=" . $res_boy[0]['user_id'] . " && status LIKE '%delivered%'";
                $db->sql($sql);
                $res_count = $db->getResult();

                $sql = "SELECT friends_code,referral_code FROM `users` WHERE id=" . $user_id;
                $db->sql($sql);
                $res_user = $db->getResult();
                /* referal system processing */
                if ($config['is-refer-earn-on'] == 1) {
                    if ($res_boy[0]['total'] >= $config['min-refer-earn-order-amount']) {
                        if ($res_count[0]['total'] == 0) {
                            if ($res_user[0]['friends_code'] != '') {
                                if ($config['refer-earn-method'] == 'percentage') {
                                    $percentage = $config['refer-earn-bonus'];
                                    $bonus_amount = $res_boy[0]['total'] / 100 * $percentage;
                                    if ($bonus_amount > $config['max-refer-earn-amount']) {
                                        $bonus_amount = $config['max-refer-earn-amount'];
                                    }
                                } else {
                                    $bonus_amount = $config['refer-earn-bonus'];
                                }
                                $res_data = $function->get_data($columns = ['friends_code', 'name'], "referral_code='" . $res_user[0]['referral_code'] . "'", 'users');
                                $friend_user = $function->get_data($columns = ['id'], "referral_code='" . $res_data[0]['friends_code'] . "'", 'users');
                                if (!empty($friend_user))
                                    $function->add_wallet_transaction($res_boy[0]['order_id'], $order_item_id, $friend_user[0]['id'], 'credit', floor($bonus_amount), 'Refer & Earn Bonus on first order by ' . ucwords($res_data[0]['name']), 'wallet_transactions');

                                $friend_code = $res_data[0]['friends_code'];
                                $sql = "UPDATE users SET balance = balance + floor($bonus_amount) WHERE referral_code='$friend_code' ";
                                $db->sql($sql);
                            }
                        }
                    }
                }
            }
            $temp = [];
            foreach ($status as $s) {
                array_push($temp, $s[0]);
            }
            if ($postStatus == 'cancelled') {
                if ($order_item_cancelled == true) {
                    if (!in_array('cancelled', $temp)) {
                        $status[] = array('cancelled', date("d-m-Y h:i:sa"));
                        $data = array(
                            'status' =>  $db->escapeString(json_encode($status)),
                        );
                    }
                    $db->update('order_items', $data, 'id=' . $order_item_id);
                }
            }
            if ($postStatus == 'processed') {
                if (!in_array('processed', $temp)) {
                    $status[] = array('processed', date("d-m-Y h:i:sa"));
                    $data = array(
                        'status' => $db->escapeString(json_encode($status))
                    );
                }
                // $db->update('order_items', $data, 'id=' . $order_item_id);
                $db->update('orders', $data, 'id=' . $order_id);
            }

            if ($postStatus == 'received') {
                if (!in_array('received', $temp)) {
                    $status[] = array('received', date("d-m-Y h:i:sa"));
                    $data = array(
                        'status' => $db->escapeString(json_encode($status)),
                        'active_status' => 'received'
                    );
                }
                // $db->update('order_items', $data, 'id=' . $order_item_id);
                $db->update('order_items', $data, 'order_id=' . $order_id);
                // $db->update('order_items', "received", 'order_id=' . $id);

                /* get order data */
                $user_id1 = $function->get_data($columns = ['user_id', 'total', 'delivery_charge', 'discount', 'final_total', 'payment_method', 'address', 'otp'], 'id=' . $order_id, 'orders');

                /* get user data */
                $user_email = $function->get_data($columns = ['email', 'name'], 'id=' . $user_id1[0]['user_id'], 'users');
                $subject = "Commande reçue avec succès";

                /* get order item by order id */
                $order_item = $function->get_order_item_by_order_id($order_id);
                $item_ids = array_column($order_item, 'product_variant_id');

                /* get product details by varient id */
                $item_details = $function->get_product_by_variant_id(json_encode($item_ids));

                for ($i = 0; $i < count($item_details); $i++) {
                    $seller_id = $item_details[$i]['seller_id'];
                    if (!empty($seller_id)) {
                        $store_details = $function->get_data($columns = ['email', 'store_name'], 'id=' . $seller_id, 'seller');
                    }
                    $item_data1[] = array(
                        'name' => $item_details[$i]['name'], 'store_name' => $store_details[0]['store_name'], 'tax_amount' => $order_item[$i]['tax_amount'], 'tax_percentage' => $order_item[$i]['tax_percentage'], 'tax_title' => $item_details[$i]['tax_title'], 'unit' =>  $item_details[$i]['measurement'] . " " . $item_details[$i]['measurement_unit_name'],
                        'qty' => $order_item[$i]['quantity'], 'subtotal' => $order_item[$i]['sub_total']
                    );
                    if (!empty($seller_id)) {
                        $seller_subject = "Nouvelle commande de " . $store_details[0]['store_name'];
                        $seller_message = "New ID de l'article de la commande : #" . $order_item_id . " reçu, veuillez en prendre note et poursuivre la procédure";
                        send_email($store_details[0]['email'], $seller_subject, $seller_message);
                        $function->send_notification_to_seller($seller_id, $seller_subject, $seller_message, 'order', $order_item_id);
                        //  notification to seller test is  remain
                    }
                }
                $user_wallet_balance = $function->get_wallet_balance($user_id1[0]['user_id'], 'users');
                $user_msg = "Bonjour, cher(è) " . $user_email[0]['name'] . ", Nous avons reçu votre commande avec succès. Les résumés de votre commande sont les suivants:<br><br>";
                $otp_msg = "Voici votre OTP. S'il vous plaît, ne le donnez au livreur que lorsque vous prenez votre commande.";

                $order_data = array('total_amount' => $user_id1[0]['total'], 'delivery_charge' => $user_id1[0]['delivery_charge'], 'discount' => $user_id1[0]['discount'], 'wallet_used' => $user_wallet_balance, 'final_total' => $user_id1[0]['final_total'], 'payment_method' => $user_id1[0]['payment_method'], 'address' => $user_id1[0]['address'], 'user_msg' => $user_msg, 'otp_msg' => $otp_msg, 'otp' => $user_id1[0]['otp']);
                send_smtp_mail($user_email[0]['email'], $subject, $item_data1, $order_data);
                $function->send_order_update_notification($user_id1[0]['user_id'], "Votre commande été " . ucwords($postStatus), $user_msg, 'order', $id);
                $subject = "Nouvelle commande de $app_name";
                $message = "ID de nouvelle commande : #" . $id . " reçu, veuillez en prendre note et poursuivre la procédure";
                $function->send_notification_to_admin("Une nouvelle commande est arrivée.", $message, "admin_notification", $id);
                send_email($support_email, $subject, $message);
            }
            if ($postStatus == 'shipped') {
                if (!in_array('processed', $temp)) {
                    $status[] = array('processed', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                if (!in_array('shipped', $temp)) {
                    $status[] = array('shipped', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                $db->update('order_items', $data, 'id=' . $order_item_id);
            }
            if ($postStatus == 'delivered') {
                if (!in_array('processed', $temp)) {
                    $status[] = array('processed', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                if (!in_array('shipped', $temp)) {
                    $status[] = array('shipped', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                if (!in_array('delivered', $temp)) {
                    $status[] = array('delivered', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                $db->update('order_items', $data, 'id=' . $order_item_id);
                $item_data = array(
                    'status' => $db->escapeString(json_encode($status)),
                    'active_status' => 'delivered'
                );
            }
            if ($postStatus == 'returned') {
                $status[] = array('returned', date("d-m-Y h:i:sa"));
                $data = array('status' => $db->escapeString(json_encode($status)));
                $db->update('order_items', $data, 'id=' . $order_item_id);
                $item_data = array(
                    'status' => $db->escapeString(json_encode($status)),
                    'active_status' => 'returned'
                );
            }
            $i = sizeof($status);
            $currentStatus = $status[$i - 1][0];
            $final_status = array(
                'active_status' => $currentStatus
            );
            if ($j == 0) {
                /*if(!in_array('processed', $temp)) {
                    $status[] = array('processed', date("d-m-Y h:i:sa"));
                    $data = array(
                        'status' => $db->escapeString(json_encode($status))
                    );
                }
                $db->update('orders', $data, 'id=' . $order_id);*/
                if ($db->update('orders', $final_status, 'id=' . $order_id)) {

                    if ($postStatus != 'received') {
                        $user_data = $function->get_data($columns = ['name', 'email', 'mobile', 'country_code'], 'id=' . $user_id, 'users');
                        $to = $user_data[0]['email'];
                        $mobile = $user_data[0]['mobile'];
                        $country_code = $user_data[0]['country_code'];

                        if ($postStatus == "" || $postStatus == "processed") {
                            $delivery_boy_name = $function->get_data($columns = ['name'], 'id=' . $delivery_boy_id, 'delivery_boys');

                            $title_delivery_boy = "Nouvelle Commande d'un article avec ID : #" . $order_id . "  a été reçue";
                            $message_delivery_boy = "Bonjour, cher(è) " . ucwords($delivery_boy_name[0]['name']) . ", Vous avez une nouvelle commande à livrer. Voici l'identifiant de la commande : #" . $order_id . ". Veuillez en prendre note.";

                            $subject = "Commande assigné à un livreur ";
                            $message = "Bonjour, cher(è) " . ucwords($user_data[0]['name']) . ",  Voici la nouvelle mise à jour de votre commande pour l'ID de commande : #" . $id . ". Votre commande a été assignée à " . ucwords($delivery_boy_name[0]['name']) . ". Veuillez en prendre note.";
                            $message .= "Merci d'utiliser nos services !";
                            if ($j == 0) {
                                $function->send_order_update_notification($user_id, $subject, $message, 'order', $id);
                                $function->send_order_update_notification($user_id, $subject, $message, 'order', $id);
                                $function->send_notification_to_delivery_boy($delivery_boy_id, $title_delivery_boy, $message_delivery_boy, 'delivery_boys', $order_id);
                            }
                        } else {
                            $subject = "Votre commande a été " . ucwords($postStatus);
                            $message = "Bonjour, cher(è) " . ucwords($user_data[0]['name']) . ",  Voici la nouvelle mise à jour de votre commande pour l'ID de commande : #" . $id . ". Votre commande été " . ucwords($postStatus) . ". Veuillez en prendre note.";
                            $message .= "Merci d'utiliser nos services ! Vous recevrez des mises à jour sur votre commande par e-mail !";
                            $function->send_order_update_notification($user_id, "Votre commande été " . ucwords($postStatus), $message, 'order', $id);
                        }

                        send_email($to, $subject, $message);
                        // $message = "Bonjour, cher(è) " . ucwords($user_data[0]['name']) . ",  Voici la nouvelle mise à jour de votre commande pour l'ID de commande : #" . $order_id . ". Votre commande été " . ucwords($postStatus) . ". Veuillez en prendre note.";
                        // $message .= "Merci d'utiliser nos services ! Contactez nous pour plus d'informations";
                        // need to send notification to seller for update order
                    }
                    $res = $db->getResult();
                }
            }
        }
        $response['error'] = false;
        $response['message'] = '<p class="alert alert-success">Les éléments de la commande ont été mis à jour avec succès</p>';
        print_r(json_encode($response));
        return false;
    } else {
        $response['error'] = true;
        $response['message'] = "<p class='alert alert-danger'>Aucun élément sélectionné pour la mise à jour</p>";
        print_r(json_encode($response));
        return false;
    }
}

if (isset($_POST['get_orders']) && isset($_POST['user_id'])) {
    if (!verify_token()) {
        return false;
    }
    $where = '';
    $user_id = $db->escapeString($function->xss_clean($_POST['user_id']));
    $order_id = (isset($_POST['order_id']) && !empty($_POST['order_id']) && is_numeric($_POST['order_id'])) ? $db->escapeString($function->xss_clean($_POST['order_id'])) : "";
    $limit = (isset($_POST['limit']) && !empty($_POST['limit']) && is_numeric($_POST['limit'])) ? $db->escapeString($function->xss_clean($_POST['limit'])) : 10;
    $offset = (isset($_POST['offset']) && !empty($_POST['offset']) && is_numeric($_POST['offset'])) ? $db->escapeString($function->xss_clean($_POST['offset'])) : 0;
    $where = !empty($order_id) ? " AND id = " . $order_id : "";
    $sql = "select count(o.id) as total from orders o where user_id=" . $user_id . $where;
    $db->sql($sql);
    $res = $db->getResult();
    $total = $res[0]['total'];
    $sql = "select *,(select name from users u where u.id=o.user_id) as user_name from orders o where user_id=" . $user_id . $where . " ORDER BY date_added DESC LIMIT $offset,$limit";
    $db->sql($sql);
    $res = $db->getResult();
    $i = 0;
    $j = 0;
    foreach ($res as $row) {
        if ($row['discount'] > 0) {
            $discounted_amount = $row['total'] * $row['discount'] / 100;
            $final_total = $row['total'] - $discounted_amount;
            $discount_in_rupees = $row['total'] - $final_total;
        } else {
            $discount_in_rupees = 0;
        }

        $res[$i]['discount_rupees'] = "$discount_in_rupees";
        $final_total = ceil($res[$i]['final_total']);
        $res[$i]['final_total'] = "$final_total";
        $res[$i]['date_added'] = date('d-m-Y h:i:sa', strtotime($res[$i]['date_added']));
        $sql = "select oi.*,v.id as variant_id, p.name,p.image,p.manufacturer,p.made_in,p.return_status,p.cancelable_status,p.till_status,v.measurement,(select short_code from unit u where u.id=v.measurement_unit_id) as unit from order_items oi join product_variant v on oi.product_variant_id=v.id join products p on p.id=v.product_id where order_id=" . $row['id'];
        $db->sql($sql);
        $res[$i]['items'] = $db->getResult();
        $res[$i]['status'] = json_decode($res[$i]['status']);
        // unset($res[$i]['status']);
        // unset($res[$i]['active_status']);
        $res[$i]['status'];
        $res[$i]['active_status'];
        for ($j = 0; $j < count($res[$i]['items']); $j++) {
            $res[$i]['items'][$j]['status'] = (!empty($res[$i]['items'][$j]['status'])) ? json_decode($res[$i]['items'][$j]['status']) : array();

            if (!empty($res[$i]['items'][$j]['status'])) {
                if (count($res[$i]['items'][$j]['status']) > 1) {
                    if (in_array("awaiting_payment", $res[$i]['items'][$j]['status'][0]) && in_array("received", $res[$i]['items'][$j]['status'][1])) {
                        unset($res[$i]['items'][$j]['status'][0]);
                    }
                    $res[$i]['items'][$j]['status'] = array_values($res[$i]['items'][$j]['status']);
                }
            } else {
                $res[$i]['items'][$j]['status'] = array();
            }

            $res[$i]['items'][$j]['delivery_boy_id'] = (!empty($res[$i]['items'][$j]['delivery_boy_id'])) ? $res[$i]['items'][$j]['delivery_boy_id'] : "";
            if (!empty($res[$i]['items'][$j]['seller_id'])) {
                $seller_info = $function->get_data($columns = ['name', 'store_name'], "id=" . $res[$i]['items'][$j]['seller_id'], 'seller');
                $res[$i]['items'][$j]['seller_name'] = $seller_info[0]['name'];
                $res[$i]['items'][$j]['seller_store_name'] = $seller_info[0]['store_name'];
            } else {
                $res[$i]['items'][$j]['seller_id'] = "";
                $res[$i]['items'][$j]['seller_name'] = "";
                $res[$i]['items'][$j]['seller_store_name'] = "";
            }
            $item_details = $function->get_product_by_variant_id2($res[$i]['items'][$j]['product_variant_id']);
            $res[$i]['items'][$j]['return_days'] = ($item_details['return_days'] != "") ? $item_details['return_days'] : '0';

            /*
            if (!empty($res[$i]['items'][$j]['status'])) {
                if (in_array('awaiting_payment', array_column($res[$i]['items'][$j]['status'], '0'))) {
                    $temp_array = array_column($res[$i]['items'][$j]['status'], '0');
                    $index = array_search("awaiting_payment", $temp_array);
                    unset($res[$i]['items'][$j]['status'][$index]);
                    $res[$i]['items'][$j]['status'] = array_values($res[$i]['items'][$j]['status']);
                }
            }
            */
            $res[$i]['items'][$j]['image'] = DOMAIN_URL . $res[$i]['items'][$j]['image'];
            $sql = "SELECT id from return_requests where product_variant_id = " . $res[$i]['items'][$j]['variant_id'] . " AND user_id = " . $user_id;
            $db->sql($sql);
            $return_request = $db->getResult();
            if (empty($return_request)) {
                $res[$i]['items'][$j]['applied_for_return'] = false;
            } else {
                $res[$i]['items'][$j]['applied_for_return'] = true;
            }
        }
        $i++;
    }
    $orders = $order = array();

    if (!empty($res)) {
        $orders['error'] = false;
        $orders['total'] = $total;
        $orders['data'] = array_values($res);
        print_r(json_encode($orders));
    } else {
        $res['error'] = true;
        $res['message'] = "No orders found!";
        print_r(json_encode($res));
    }
}

if (isset($_POST['get_reorder_data']) && !empty($_POST['get_reorder_data'])) {
    if (!verify_token()) {
        return false;
    }
    $id = $db->escapeString($function->xss_clean($_POST['id']));
    $sql = "select * from `orders` where id=$id";
    $db->sql($sql);
    $res = $db->getResult();
    if (empty($res)) {
        $response['error'] = true;
        $response['message'] = "Désolé l'identifiant de votre commande est invalide";
        print_r(json_encode($response));
    } else {
        $sql = "select * from `order_items` where order_id=$id";
        $db->sql($sql);
        $order_items = $db->getResult();

        $items = $temp = [];
        foreach ($order_items as $item) {
            $temp['product_variant_id'] = $item['product_variant_id'];
            $temp['quantity'] = $item['quantity'];
            $items[] = $temp;
        }
        unset($res[0]['status']);
        unset($res[0]['active_status']);

        $res[0]['items'] = $items;
        $response['error'] = true;
        $response['message'] = "Les données de la commande ont été récupérées avec succès";
        $response['data'] = $res[0];
        print_r(json_encode($response));
    }
}

if (isset($_POST['update_order_total_payable']) && isset($_POST['id'])) {

    $id = $db->escapeString($function->xss_clean($_POST['id']));
    $discount = $db->escapeString($function->xss_clean($_POST['discount']));
    // $deliver_by = $db->escapeString($function->xss_clean($_POST['deliver_by']));
    $total_payble = $db->escapeString($function->xss_clean($_POST['total_payble']));
    $total_payble = round($total_payble, 2);
    // $data = array(
    //     'discount' => $discount,
    //     'deliver_by' => $deliver_by,
    // );
    $data1 = array(
        'discount' => $discount,
        'final_total' => $total_payble,
    );


    if ($discount >= 0) {
        // $db->update('order_items', $data, 'order_id=' . $id);
        $db->update('orders', $data1, 'id=' . $id);
        $res = $db->getResult();
        if (!empty($res)) {
            $response['error'] = false;
            $response['message'] = "Remise mise à jour avec succès.";
            print_r(json_encode($response));
        } else {
            $response['error'] = true;
            $response['message'] = "Impossible de mettre à jour la commande. Essayez à nouveau !";
            print_r(json_encode($response));
        }
    }
}

if (isset($_POST['add_transaction']) && $_POST['add_transaction'] == true) {
    if (!verify_token()) {
        return false;
    }
    /*add data to transaction table*/
    $user_id = $db->escapeString($function->xss_clean($_POST['user_id']));
    $order_id = $db->escapeString($function->xss_clean($_POST['order_id']));
    $type = $db->escapeString($function->xss_clean($_POST['type']));
    $txn_id = $db->escapeString($function->xss_clean($_POST['txn_id']));
    $amount = $db->escapeString($function->xss_clean($_POST['amount']));
    $status = $db->escapeString($function->xss_clean($_POST['status']));
    $message = $db->escapeString($function->xss_clean($_POST['message']));
    $transaction_date = (isset($_POST['transaction_date']) && !empty($_POST['transaction_date'])) ? $db->escapeString($function->xss_clean($_POST['transaction_date'])) : date('Y-m-d H:i:s');
    $data = array(
        'user_id' => $user_id,
        'order_id' => $order_id,
        'type' => $type,
        'txn_id' => $txn_id,
        'amount' => $amount,
        'status' => $status,
        'message' => $message,
        'transaction_date' => $transaction_date
    );
    $db->insert('transactions', $data);
    $res = $db->getResult();
    $response['error'] = false;
    $response['transaction_id'] = $res[0];
    $response['message'] = "Transaction ajoutée avec succès !";
    print_r(json_encode($response));
}

if (isset($_POST['update_order_rank']) && isset($_POST['order_id'])) {
    $id = $db->escapeString($function->xss_clean($_POST['order_id']));
    $delivery_rank = $db->escapeString($function->xss_clean($_POST["delivery_rank"]));

    $sql = "UPDATE orders SET `delivery_rank`='" . $delivery_rank . "' WHERE id=" . $id;
    $db->sql($sql);
    $res_order = $db->getResult();
    if (!empty($res_order)) {
        $response['error'] = false;
        $response['message'] = "La commande mise à jour avec succès";
        print_r(json_encode($response));
    } else {
        $response['error'] = true;
        $response['message'] = "La mise à jour de la commande échoue";
        print_r(json_encode($response));
    }
}
/* 
	accesskey:90336
	delete_order:1 
    order_id:73
*/
if (isset($_POST['delete_order']) && $_POST['delete_order'] == true) {
    if (!verify_token()) {
        return false;
    }
    /*add data to transaction table*/

    $order_id = $db->escapeString($function->xss_clean($_POST['order_id']));

    // delete data from pemesanan table
    $sql_query = "DELETE FROM orders WHERE ID =" . $order_id;
    if ($db->sql($sql_query)) {
        $sql = "DELETE FROM order_items WHERE order_id =" . $order_id;
        $db->sql($sql);

        $response['error'] = false;
        $response['message'] = "Commande supprimée avec succès !";
    } else {
        $response['error'] = true;
        $response['message'] = "Commande non supprimée !";
    }
    echo json_encode($response);
}

if (isset($_POST['test']) && $_POST['test'] == true) {
    $res = $function->send_notification_to_admin("test", "hello", "admin_notification", 12);
    print_r($res);
}
//Update status by order
if (isset($_POST['update_status_by_order']) && $_POST['update_status_by_order'] == 1) {
    $id = $db->escapeString($function->xss_clean($_POST['order_id']));
    $postStatus = $db->escapeString($function->xss_clean($_POST['status']));
    $res = $function->get_data($columns = ['id', 'user_id', 'payment_method', 'wallet_balance', 'total', 'delivery_charge', 'tax_amount', 'status', 'active_status'], 'id=' . $id, 'orders');
    $delivery_boy_id = 0;
    if (isset($_POST['delivery_boy_id']) && !empty($_POST['delivery_boy_id']) && $_POST['delivery_boy_id'] != "") {
        $delivery_boy_id = $db->escapeString($function->xss_clean($_POST['delivery_boy_id']));
        if ($postStatus == 'awaiting_payment') {
            $response['error'] = true;
            switch ($res[0]['active_status']) {
                case 'received':
                    $response['message'] = "La commande ne peut pas être en attente de paiement. Parce qu'elle est reçue";
                    break;
                case 'shipped':
                    $response['message'] = "La commande ne peut pas être en attente de paiement. Parce qu'elle est en cours de livraison";
                    break;
                case 'processed':
                    $response['message'] = "La commande ne peut pas être en attente de paiement. Parce qu'elle est en cours de traitement";
                    break;
                case 'delivered':
                    $response['message'] = "La commande ne peut être livrée. Parce qu'elle est en attente de paiement.";
                    break;
                default:
                    $response['message'] = "La commande ne peut pas être en attente de paiement. Parce qu'elle est sur " . $res[0]['active_status'] . ".";
                    break;
            }
            // $response['message'] = "La commande ne peut pas être en attente de paiement. Parce qu'elle est sur " . $res[0]['active_status'] . ".";
            print_r(json_encode($response));
            return false;
        }

        /* check for awaiting status */
        if ($res[0]['active_status'] == 'awaiting_payment' && ($postStatus == 'delivered' || $postStatus == 'received' || $postStatus == 'shipped' || $postStatus == 'processed')) {
            $response['error'] = true;
            switch ($postStatus) {
                case 'received':
                    $response['message'] = "La commande ne peut être reçue. Parce qu'elle est en attente de paiement.";
                    break;
                case 'shipped':
                    $response['message'] = "La commande ne peut être en cours de livraison. Parce qu'elle est en attente de paiement.";
                    break;
                case 'processed':
                    $response['message'] = "La commande ne peut être en cours de traitement. Parce qu'elle est en attente de paiement.";
                    break;
                case 'delivered':
                    $response['message'] = "La commande ne peut être livrée. Parce qu'elle est en attente de paiement.";
                    break;
                default:
                    $response['message'] = "La commande ne peut être $postStatus. Parce qu'elle est en attente de paiement.";
                    break;
            }
            // $response['message'] = "La commande ne peut être $postStatus. Parce qu'elle est en attente de paiement.";
            print_r(json_encode($response));
            return false;
        }

        $delivery_boy_id = $db->escapeString($function->xss_clean($_POST['delivery_boy_id']));
        // $res_delivery_boy_id = $function->get_data($columns = ['active_status', 'status', 'delivery_boy_id'], 'id=' . $order_item_id, 'order_items');
        // $delivery_boy_name = $function->get_data($columns = ['name'], 'id=' . $delivery_boy_id, 'delivery_boys');

        if ($res[0]['active_status'] == 'delivered' && ($postStatus == 'shipped'  || $postStatus == 'processed')) {
            $response['error'] = true;
            $response['message'] = '';
            $response['message'] = ($delivery_boy_id != 0) ? 'Mise à jour du échouée, Impossible de mettre la commande ne cours de livraison' : 'Impossible de mettre la commande en cours de traitement car la commande est livrée';
            print_r(json_encode($response));
            return false;
        }

        /* $sql = "SELECT * FROM `users` WHERE id=" . $res[0]['user_id'];
        $db->sql($sql);
        $res_user = $db->getResult();
        if (!empty($postStatus) && $postStatus != $res[0]['active_status']) {
            /* return if only delivery boy will update and order status is already changed */
        $sql = "SELECT COUNT(id) as total FROM `orders` WHERE user_id=" . $res[0]['user_id'] . " && status LIKE '%delivered%'";
        $db->sql($sql);
        $res_count = $db->getResult();

        if (!empty($res)) {
            $status = json_decode($res[0]['status']);
            $user_id =  $res[0]['user_id'];
            foreach ($status as $each) {
                if (in_array($postStatus, $each)) {
                    $response['error'] = true;
                    switch ($postStatus) {
                        case 'received':
                            $response['message'] = "La commande a été déjà reçue.";
                            break;
                        case 'shipped':
                            $response['message'] = "La commande est déjà en cours de livraison.";
                            break;
                        case 'processed':
                            $response['message'] = "La commande est déjà en cours de traitement.";
                            break;
                        case 'delivered':
                            $response['message'] = "La commande a été dejà livrée.";
                            break;
                        default:
                            $response['message'] = "La commande dejà  $postStatus.";
                            break;
                    }
                    // $response['message'] = ($delivery_boy_id != 0) ? 'Livreur mis à jour, commande déjà ' . $postStatus : 'Commandez déjà ' . $postStatus;
                    print_r(json_encode($response));
                    return false;
                }
            }

            /* if given status is cancel or return */
            if ($postStatus == 'cancelled' || $postStatus == 'returned') {
                $resp['error'] = true;
                $resp['message'] = "La commande n'est peut pas être annulée ou retournée  !";
                print_r(json_encode($resp));
                return false;
            }

            if ($postStatus == 'delivered') {
                $sql = "SELECT oi.id as order_items, oi.is_available,oi.sub_total, oi.quantity, oi.price,oi.price_original, o.total,o.delivery_charge,o.final_total,o.payment_method,oi.delivery_boy_id,o.service_charge, s.id as seller_id, s.commission FROM orders o join order_items oi on oi.order_id=o.id join seller s on s.id = oi.seller_id WHERE o.id=" . $res[0]['id'];
                // $sql = "SELECT o.*, oi.* FROM order_items oi INNER JOIN orders o ON  o.id = oi.order_items";
                $db->sql($sql);
                $res_boy = $db->getResult();
                $seller_bonus_commission = 0;

                for ($i = 0; $i < count($res_boy); $i++) {
                    if ($res_boy[$i]['delivery_boy_id'] != 0 && $res_boy[$i]['is_available'] == 1) {
                        if (strtolower($res_boy[$i]['payment_method']) == 'cod') {
                            $cash_received = $res_boy[$i]['sub_total'] + $res_boy[$i]['delivery_charge'];
                            // $cash_received = $res_boy[0]['sub_total'] + $res_boy[0]['delivery_charge'] + $sc;
                            $sql = "UPDATE delivery_boys SET cash_received = cash_received + $cash_received WHERE id=" . $res_boy[$i]['delivery_boy_id'];
                            $db->sql($sql);
                            $function->add_transaction($order_item_id, $res_boy[$i]['delivery_boy_id'], 'delivery_boy_cash', $cash_received, 'Delivery boy collected COD');
                        }
                        $seller_bonus_commission = $res_boy[$i]['sub_total'];
                        $order_id = $res[0]['id'];
                        $order_items = $res_boy[$i]['order_items'];
                        $id = $res_boy[$i]['seller_id'];
                        $type = "credit";
                        $message = "Balance $type to seller";
                        $balance = $function->get_wallet_balance($id, 'seller');
                        $amount = $res_boy[$i]['sub_total'] - ($res_boy[$i]['sub_total'] / 100 * $res_boy[$i]['commission']) - ((($res_boy[$id]['price_original'] * $service_charge) / 100) * $res_boy[$id]['quantity']);
                        $new_balance =  $balance + $amount;
                        $function->update_wallet_balance($new_balance, $id, 'seller');
                        // $function->add_wallet_transaction("", 0, $id, $type, $amount, $message, 'seller_wallet_transactions');
                        $function->add_wallet_transaction($order_id, $order_items, $id, $type, $amount, $message, 'seller_wallet_transactions');
                    }
                }
                $response['message'] = "La commande livrée avec succès";
                /*
                $sql = "select name,bonus from delivery_boys where id=" . $res_boy[0]['delivery_boy_id'];
                $db->sql($sql);
                $res_bonus = $db->getResult();
                // $reward = $res_boy[0]['sub_total'] / 100 * $res_bonus[0]['bonus'];
                $reward = $seller_bonus_commission / 100 * $res_bonus[0]['bonus'];
    
                if ($reward > 0) {
                    $sql = "UPDATE delivery_boys SET balance = balance + $reward WHERE id=" . $res_boy[0]['delivery_boy_id'];
                    $db->sql($sql);
                    $comission = $function->add_delivery_boy_commission($delivery_boy_id, 'credit', $reward, 'Order Delivery Commission.');
                    $currency = $function->get_settings('currency');
                    // $message_delivery_boy = "Bonjour, cher(è) " . ucwords($res_bonus[0]['name']) . ", Voici la nouvelle mise à jour de votre commande pour l'ID du poste de commande : #" . $order_item_id . ". Votre Commission de " . $reward . " est créditée. Veuillez en prendre note.";
                    // $function->send_notification_to_delivery_boy($delivery_boy_id, "Votre Commission de " . $reward . " " . $currency . " a été creditée ", "$message_delivery_boy", 'delivery_boys', $order_item_id);
                    // $function->store_delivery_boy_notification($delivery_boy_id, $order_item_id, "Votre Commission de " . $reward . " " . $currency . " a été creditée ", $message_delivery_boy, 'order_reward');
                }*/

                /* referal system processing */
                if ($config['is-refer-earn-on'] == 1) {
                    if ($res_boy[0]['total'] >= $config['min-refer-earn-order-amount']) {
                        if ($res_count[0]['total'] == 0) {
                            if ($res_user[0]['friends_code'] != '') {
                                if ($config['refer-earn-method'] == 'percentage') {
                                    $percentage = $config['refer-earn-bonus'];
                                    $bonus_amount = $res_boy[0]['total'] / 100 * $percentage;
                                    if ($bonus_amount > $config['max-refer-earn-amount']) {
                                        $bonus_amount = $config['max-refer-earn-amount'];
                                    }
                                } else {
                                    $bonus_amount = $config['refer-earn-bonus'];
                                }
                                $res_data = $function->get_data($columns = ['friends_code', 'name'], "referral_code='" . $res[0]['user_id'] . "'", 'users');
                                $friend_user = $function->get_data($columns = ['id'], "referral_code='" . $res_data[0]['friends_code'] . "'", 'users');
                                if (!empty($friend_user))
                                    $function->add_wallet_transaction($id, 0, $friend_user[0]['id'], 'credit', floor($bonus_amount), 'Refer & Earn Bonus on first order by ' . ucwords($res_data[0]['name']), 'wallet_transactions');

                                $friend_code = $res_data[0]['friends_code'];
                                $sql = "UPDATE users SET balance = balance + floor($bonus_amount) WHERE referral_code='$friend_code' ";
                                $db->sql($sql);
                            }
                        }
                    }
                }
            }

            $temp = [];
            foreach ($status as $s) {
                array_push($temp, $s[0]);
            }
            /*if ($postStatus == 'cancelled') {
                    if ($order_item_cancelled == true) {
                        if (!in_array('cancelled', $temp)) {
                            $status[] = array('cancelled', date("d-m-Y h:i:sa"));
                            $data = array(
                                'status' =>  $db->escapeString(json_encode($status)),
                            );
                        }
                        $db->update('order_items', $data, 'id=' . $order_item_id);
                    }
                }*/


            if ($postStatus == 'processed') {
                if (!in_array('processed', $temp)) {
                    $status[] = array('processed', date("d-m-Y h:i:sa"));
                    $data = array(
                        'status' => $db->escapeString(json_encode($status))
                    );
                }
                $db->update('orders', $data, 'id=' . $res[0]['id']);
            }

            if ($postStatus == 'shipped') {
                if (!in_array('processed', $temp)) {
                    $status[] = array('processed', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                if (!in_array('shipped', $temp)) {
                    $status[] = array('shipped', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                $db->update('orders', $data, 'id=' . $res[0]['id']);
            }
            if ($postStatus == 'delivered') {
                if (!in_array('processed', $temp)) {
                    $status[] = array('processed', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                if (!in_array('shipped', $temp)) {
                    $status[] = array('shipped', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                if (!in_array('delivered', $temp)) {
                    $status[] = array('delivered', date("d-m-Y h:i:sa"));
                    $data = array('status' => $db->escapeString(json_encode($status)));
                }
                $db->update('orders', $data, 'id=' . $res[0]['id']);
                $item_data = array(
                    'status' => $db->escapeString(json_encode($status)),
                    'active_status' => 'delivered'
                );
            }

            /*
            if ($postStatus == 'returned') {
                        $status[] = array('returned', date("d-m-Y h:i:sa"));
                        $data = array('status' => $db->escapeString(json_encode($status)));
                        $db->update('order_items', $data, 'id=' . $order_item_id);
                        $item_data = array(
                            'status' => $db->escapeString(json_encode($status)),
                            'active_status' => 'returned'
                        );
                    }
            */

            $i = sizeof($status);
            $currentStatus = $status[$i - 1][0];
            $final_status = array(
                'active_status' => $currentStatus
            );
            if ($db->update('orders', $final_status, 'id=' . $res[0]['id'])) {
                $response['error'] = false;
                if ($postStatus != 'received' && $postStatus != 'returned') {
                    $user_data = $function->get_data($columns = ['name', 'email', 'mobile', 'country_code'], 'id=' . $user_id, 'users');
                    $to = $user_data[0]['email'];
                    $mobile = $user_data[0]['mobile'];
                    $country_code = $user_data[0]['country_code'];
                    $subject = "Votre commande a été " . ucwords($postStatus);
                    $message = "Bonjour, cher(è) " . ucwords($user_data[0]['name']) . ",  Voici la nouvelle mise à jour de votre commande pour l'ID de commande : #" . $id . ". Votre commande été " . ucwords($postStatus) . ". Veuillez en prendre note.";
                    $message .= "Merci d'utiliser nos services ! Vous recevrez des mises à jour sur votre commande par e-mail !";
                    $function->send_order_update_notification($user_id, "Votre commande été " . ucwords($postStatus), $message, 'order', $id);
                    send_email($to, $subject, $message);
                    $message = "Bonjour, cher(è) " . ucwords($user_data[0]['name']) . ",  Voici la nouvelle mise à jour de votre commande pour l'ID de commande : #" . $id . ". Votre commande été " . ucwords($postStatus) . ". Veuillez en prendre note.";
                    $message .= "Merci d'utiliser nos services ! Contactez nous pour plus d'informations";
                    // need to send notification to seller for update order
                }
                $res = $db->getResult();

                print_r(json_encode($response));
            } else {
                $response['error'] = true;
                $response['message'] = isset($_POST['delivery_boy_id']) && $_POST['delivery_boy_id'] != '' ? "Le livreur a été mis à jour, mais il n'a pas pu mettre à jour le statut de la commande, essayez à nouveau !" : 'Impossible de mettre à jour le statut de la commande, essayez à nouveau !';
                print_r(json_encode($response));
            }
        } else {
            $response['error'] = true;
            $response['message'] = "Désolé l'identifiant de votre commande est invalide";
            print_r(json_encode($response));
        }
    }
    /* else {
        $response['error'] = false;
        $response['message'] = "Aucune modification n'a été apportée";
        print_r(json_encode($response));
    */
}

function findKey($array, $keySearch)
{
    foreach ($array as $key => $item) {
        if ($key == $keySearch) {
            return true;
        } elseif (is_array($item) && findKey($item, $keySearch)) {
            return true;
        }
    }
    return false;
}
