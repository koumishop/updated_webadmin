<?php
header("Content-Type: application/json");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Access-Control-Allow-Origin: *');


include('../../includes/crud.php');
include('../../includes/custom-functions.php');
include('verify-token.php');
$fn = new custom_functions();
$function = new custom_functions();
$db = new Database();
$db->connect();
include('../../includes/variables.php');

include('send-email.php');

/* 
-------------------------------------------
APIs for Delivery Boys
-------------------------------------------
1. login
2. get_delivery_boy_by_id  
3. get_orders_by_delivery_boy_id
4. get_fund_transfers 
5. update_delivery_boy_profile
6. update_order_status
7. delivery_boy_forgot_password
8. get_notifications
9. update_delivery_boy_fcm_id
10. check_delivery_boy_by_mobile
11. send_withdrawal_request
12. get_withdrawal_requests
13. change_availability
-------------------------------------------

-------------------------------------------

*/

if (!verify_token()) {
    return false;
}


if (!isset($_POST['accesskey'])  || trim($_POST['accesskey']) != $access_key) {
    $response['error'] = true;
    $response['message'] = "No Accsess key found!";
    print_r(json_encode($response));
    return false;
    exit();
}

if (isset($_POST['login'])) {
    /* 
    1.Login
        accesskey:90336
        mobile:9876543210
        password:12345678
        fcm_id:YOUR_FCM_ID  // {optional}
        login:1
    */

    if (empty(trim($_POST['mobile']))) {
        $response['error'] = true;
        $response['message'] = "Mobile should be filled!";
        print_r(json_encode($response));
        return false;
        exit();
    }
    if (empty($_POST['password'])) {
        $response['error'] = true;
        $response['message'] = "Password should be filled!";
        print_r(json_encode($response));
        return false;
        exit();
    }


    $mobile = $db->escapeString($fn->xss_clean($_POST['mobile']));
    $password = md5($db->escapeString($fn->xss_clean($_POST['password'])));
    $sql = "SELECT * FROM delivery_boys	WHERE mobile = '" . $mobile . "' AND password = '" . $password . "'";
    $db->sql($sql);
    $res = $db->getResult();
    $num = $db->numRows($res);
    $rows = $tempRow = array();

    if ($num == 1) {
        if ($res[0]['status'] == 0) {
            $response['error'] = true;
            $response['message'] = "It seems your acount is not active please contact admin for more info!";
            $response['data'] = array();
        } else {
            /* update fcm_id in delivery boy table */
            $delivery_boy_id = $res[0]['id'];

            $fcm_id = (isset($_POST['fcm_id']) && !empty($_POST['fcm_id'])) ? $db->escapeString($fn->xss_clean($_POST['fcm_id'])) : "";
            if (!empty($fcm_id)) {
                $sql1 = "update delivery_boys set `fcm_id` ='$fcm_id' where id = '" . $delivery_boy_id . "'";
                $db->sql($sql1);
                $db->sql($sql);
                $res1 = $db->getResult();
                foreach ($res1 as $row) {
                    $tempRow['id'] = $row['id'];
                    $tempRow['name'] = $row['name'];
                    $tempRow['mobile'] = $row['mobile'];
                    $tempRow['password'] = $row['password'];
                    $tempRow['address'] = $row['address'];
                    $tempRow['bonus'] = $row['bonus'];
                    $tempRow['balance'] = $row['balance'];
                    $tempRow['dob'] = $row['dob'];
                    $tempRow['bank_account_number'] = $row['bank_account_number'];
                    $tempRow['account_name'] = $row['account_name'];
                    $tempRow['bank_name'] = $row['bank_name'];
                    $tempRow['ifsc_code'] = $row['ifsc_code'];
                    $tempRow['other_payment_information'] = (!empty($row['other_payment_information'])) ? $row['other_payment_information'] : "";
                    $tempRow['status'] = $row['status'];
                    $tempRow['is_available'] = $row['is_available'];
                    $tempRow['date_created'] = $row['date_created'];
                    $tempRow['fcm_id'] = !empty($row['fcm_id']) ? $row['fcm_id'] : "";
                    $tempRow['driving_license'] = (!empty($row['driving_license'])) ? DOMAIN_URL . 'upload/delivery-boy/' . $row['driving_license'] : "";
                    $tempRow['national_identity_card'] = (!empty($row['national_identity_card'])) ? DOMAIN_URL . 'upload/delivery-boy/' . $row['national_identity_card'] : "";

                    $rows[] = $tempRow;
                }
                $db->disconnect();
            } else {
                foreach ($res as $row) {
                    $tempRow['id'] = $row['id'];
                    $tempRow['name'] = $row['name'];
                    $tempRow['mobile'] = $row['mobile'];
                    $tempRow['password'] = $row['password'];
                    $tempRow['address'] = $row['address'];
                    $tempRow['bonus'] = $row['bonus'];
                    $tempRow['balance'] = $row['balance'];
                    $tempRow['dob'] = $row['dob'];
                    $tempRow['bank_account_number'] = $row['bank_account_number'];
                    $tempRow['account_name'] = $row['account_name'];
                    $tempRow['bank_name'] = $row['bank_name'];
                    $tempRow['ifsc_code'] = $row['ifsc_code'];
                    $tempRow['other_payment_information'] = (!empty($row['other_payment_information'])) ? $row['other_payment_information'] : "";
                    $tempRow['status'] = $row['status'];
                    $tempRow['date_created'] = $row['date_created'];
                    $tempRow['fcm_id'] = !empty($row['fcm_id']) ? $row['fcm_id'] : "";
                    $tempRow['driving_license'] = (!empty($row['driving_license'])) ? DOMAIN_URL . 'upload/delivery-boy/' . $row['driving_license'] : "";
                    $tempRow['national_identity_card'] = (!empty($row['national_identity_card'])) ? DOMAIN_URL . 'upload/delivery-boy/' . $row['national_identity_card'] : "";

                    $rows[] = $tempRow;
                }
            }
            $db->disconnect();
            $response['error'] = false;
            $response['message'] = "Delivery Boy Login Successfully";
            $response['currency'] =  $fn->get_settings('currency');
            $response['data'] = $rows;
        }
    } else {
        $response['error'] = true;
        $response['message'] = "Invalid number or password, Try again.";
    }
    print_r(json_encode($response));
}


/* 
---------------------------------------------------------------------------------------------------------
*/

if (isset($_POST['get_delivery_boy_by_id'])) {

    /* 
    2.get_delivery_boy_by_id
        accesskey:90336
        delivery_boy_id:78
        get_delivery_boy_by_id:1
    */
    if (empty($_POST['delivery_boy_id'])) {
        $response['error'] = true;
        $response['message'] = "Delivery boy id should be Passed!";
        print_r(json_encode($response));
        return false;
        exit();
    }
    $id = $db->escapeString($fn->xss_clean($_POST['delivery_boy_id']));
    $sql = "SELECT * FROM delivery_boys	WHERE id = '" . $id . "'";
    $db->sql($sql);
    $res = $db->getResult();
    $num = $db->numRows($res);
    $db->disconnect();
    $rows = $tempRow = array();
    if ($num == 1) {
        foreach ($res as $row) {
            $tempRow['id'] = $row['id'];
            $tempRow['name'] = $row['name'];
            $tempRow['mobile'] = $row['mobile'];
            $tempRow['password'] = $row['password'];
            $tempRow['address'] = $row['address'];
            $tempRow['bonus'] = $row['bonus'];
            $tempRow['balance'] = $row['balance'];
            $tempRow['dob'] = $row['dob'];
            $tempRow['bank_account_number'] = $row['bank_account_number'];
            $tempRow['account_name'] = $row['account_name'];
            $tempRow['bank_name'] = $row['bank_name'];
            $tempRow['ifsc_code'] = $row['ifsc_code'];
            $tempRow['other_payment_information'] = (!empty($row['other_payment_information'])) ? $row['other_payment_information'] : "";
            $tempRow['status'] = $row['status'];
            $tempRow['date_created'] = $row['date_created'];
            $tempRow['fcm_id'] = !empty($row['fcm_id']) ? $row['fcm_id'] : "";
            $tempRow['driving_license'] = (!empty($row['driving_license'])) ? DOMAIN_URL . 'upload/delivery-boy/' . $row['driving_license'] : "";
            $tempRow['national_identity_card'] = (!empty($row['national_identity_card'])) ? DOMAIN_URL . 'upload/delivery-boy/' . $row['national_identity_card'] : "";
            $rows[] = $tempRow;
        }
        $response['error'] = false;
        $response['message'] = "Delivery Boy Data Fetched Successfully";
        $response['currency'] =  $fn->get_settings('currency');
        $response['data'] = $rows;
        $response['data'][0]['balance'] = strval(round($response['data'][0]['balance']));
    } else {
        $response['error'] = true;
        $response['message'] = "No data found!";
    }
    print_r(json_encode($response));
}

/* 
---------------------------------------------------------------------------------------------------------
*/

if (isset($_POST['get_orders_by_delivery_boy_id']) && !empty($_POST['get_orders_by_delivery_boy_id'])) {
    /*
    3.get_orders_by_delivery_boy_id
        accesskey:90336
        get_orders_by_delivery_boy_id:1
        delivery_boy_id:1
        order_item_id:12         // {optional}
        order_id:12608          // {optional}
        limit:10                // {optional}
        offset:0                // {optional}
        filter_order:received | processed | shipped | delivered | cancelled | returned    // {optional}
    */

    if (empty($_POST['delivery_boy_id'])) {
        $response['error'] = true;
        $response['message'] = "Delivery Boy ID should be filled!";
        print_r(json_encode($response));
        return false;
    }
    $where = '';
    $id = $db->escapeString($fn->xss_clean($_POST['delivery_boy_id']));
    $order_id = (isset($_POST['order_id']) && !empty($_POST['order_id']) && is_numeric($_POST['order_id'])) ? $db->escapeString($fn->xss_clean($_POST['order_id'])) : "";
    $order_item_id = (isset($_POST['order_item_id']) && !empty($_POST['order_item_id']) && is_numeric($_POST['order_item_id'])) ? $db->escapeString($fn->xss_clean($_POST['order_item_id'])) : "";
    $limit = (isset($_POST['limit']) && !empty($_POST['limit']) && is_numeric($_POST['limit'])) ? $db->escapeString($fn->xss_clean($_POST['limit'])) : 10;
    $offset = (isset($_POST['offset']) && !empty($_POST['offset']) && is_numeric($_POST['offset'])) ? $db->escapeString($fn->xss_clean($_POST['offset'])) : 0;
    $sort = (isset($_POST['sort']) && !empty($fn->xss_clean($_POST['sort']))) ? $db->escapeString($fn->xss_clean($_POST['sort'])) : 'oi.id';
    $order = (isset($_POST['order']) && !empty($fn->xss_clean($_POST['order']))) ? $db->escapeString($fn->xss_clean($_POST['order'])) : 'DESC';

    if (isset($_POST['order_id']) && $_POST['order_id'] != '') {
        $where .= " AND oi.order_id= $order_id";
    }
    if (isset($_POST['order_item_id']) && $_POST['order_item_id'] != '') {
        $where .= " AND oi.id= $order_item_id";
    }
    if (isset($_POST['filter_order']) && $_POST['filter_order'] != '') {
        $filter_order = $db->escapeString($fn->xss_clean($_POST['filter_order']));
        $where .= " AND oi.`active_status`='" . $filter_order . "'";
    }

    // $sql = "select count(oi.id) as total from order_items oi where delivery_boy_id=" . $delivery_boy_id . $where;
    // $db->sql($sql);
    // $res = $db->getResult();
    // $total = $res[0]['total'];

    $sql = "select COUNT(oi.id) as total from `order_items` oi left join product_variant v on oi.product_variant_id=v.id left join products p on p.id=v.product_id left join users u ON u.id=oi.user_id left join orders o ON o.id=oi.order_id where oi.order_id=o.id AND oi.active_status NOT IN ('awaiting_payment') and oi.delivery_boy_id = $id " . $where;
    $db->sql($sql);
    $res = $db->getResult();
    foreach ($res as $row) {
        $total = $row['total'];
    }
    $sql = "select oi.*,o.mobile,o.otp,o.longitude,o.latitude,o.order_note,o.total ,o.delivery_charge,o.discount,o.promo_code,o.promo_discount,o.wallet_balance,o.final_total,o.payment_method,o.address,o.delivery_time,p.name as name,p.image, u.name as uname,v.measurement, (SELECT short_code FROM unit un where un.id=v.measurement_unit_id)as mesurement_unit_name,oi.status as order_status from `order_items` oi left join product_variant v on oi.product_variant_id=v.id left join products p on p.id=v.product_id left join users u ON u.id=oi.user_id left join orders o ON o.id=oi.order_id where oi.order_id=o.id AND oi.active_status NOT IN ('awaiting_payment') and oi.delivery_boy_id = $id  $where ORDER BY $sort $order LIMIT $offset , $limit";
    $db->sql($sql);
    $res = $db->getResult();

    $bulkData = array();
    $bulkData['total'] = $total;
    $rows = array();
    $tempRow = array();
    $i = 0;
    foreach ($res as $row) {
        $res_dboy = $fn->get_data($columns = ['name'], "id=" . $row['delivery_boy_id'], 'delivery_boys');
        $seller_address = $fn->get_seller_address($row['seller_id']);
        $res_seller = $fn->get_data($columns = ['name', 'mobile', 'latitude', 'longitude', 'state', 'street', 'pincode_id'], "id=" . $row['seller_id'], 'seller');

        $discounted_amount = $row['total'] * $row['discount'] / 100; /*  */
        $final_total = $row['total'] - $discounted_amount;
        $discount_in_rupees = $row['total'] - $final_total;
        $discount_in_rupees = floor($discount_in_rupees);
        $tempRow['id'] = $row['id'];
        $tempRow['order_id'] = $row['order_id'];
        $tempRow['user_id'] = $row['user_id'];
        $tempRow['name'] = $row['uname'];

        $tempRow['mobile'] = $row['mobile'];

        $tempRow['order_note'] = $row['order_note'];
        $tempRow['product_name'] = (!empty($row['product_name'])) ? $row['product_name'] : "";;
        $tempRow['image'] = DOMAIN_URL . $row['image'];
        $tempRow['variant_name'] = (!empty($row['variant_name'])) ? $row['variant_name'] : "";
        $tempRow['product_variant_id'] = $row['product_variant_id'];
        $tempRow['delivery_charge'] = $row['delivery_charge'];
        $tempRow['is_available'] = $row['is_available'];
        $tempRow['total'] = $row['sub_total'];
        $tempRow['tax'] = $row['tax_amount'] . '(' . $row['tax_percentage'] . '%)';
        $tempRow['promo_discount'] = $row['promo_discount'];
        $tempRow['wallet_balance'] = $row['wallet_balance'];
        $tempRow['discount'] = $discount_in_rupees . '(' . $row['discount'] . '%)';
        $tempRow['qty'] = $row['quantity'];
        $tempRow['final_total'] = $row['final_total'];
        // if ($i == 0) {
        //     $tempRow['final_total'] = strval($row['sub_total'] + $row['delivery_charge'] + $row['tax_amount'] - $row['promo_discount']);
        // } else {
        //     $tempRow['final_total'] = strval($row['sub_total'] + $row['tax_amount'] - $row['promo_discount']);
        // }
        $tempRow['promo_code'] = $row['promo_code'];
        $tempRow['deliver_by'] = !empty($res_dboy[0]['name']) ? $res_dboy[0]['name'] : 'Not Assigned';
        $tempRow['seller_name'] = !empty($res_seller[0]['name']) ? $res_seller[0]['name'] : '';
        $tempRow['seller_mobile'] = $res_seller[0]['mobile'];
        $tempRow['seller_address'] = $seller_address;
        $tempRow['seller_latitude'] = !empty($res_seller[0]['latitude']) ? $res_seller[0]['latitude'] : '';
        $tempRow['seller_longitude'] = !empty($res_seller[0]['longitude']) ? $res_seller[0]['longitude'] : '';
        $tempRow['payment_method'] = $row['payment_method'];
        $tempRow['seller_id'] = !empty($row['seller_id']) ? $row['seller_id'] : '';
        $tempRow['address'] = $row['address'];
        $tempRow['delivery_time'] = $row['delivery_time'];
        $tempRow['active_status'] = $row['active_status'];
        $tempRow['wallet_balance'] = $row['wallet_balance'];
        $tempRow['date_added'] = date('d-m-Y', strtotime($row['date_added']));
        $tempRow['otp'] = !empty($row['otp']) ? $row['otp'] : '0';
        $tempRow['longitude'] = !empty($row['longitude']) ? $row['longitude'] : '';
        $tempRow['latitude'] = !empty($row['latitude']) ? $row['latitude'] : '';
        $tempRow['price'] = $row['price'];
        $tempRow['discounted_price'] = $row['discounted_price'];
        $tempRow['tax_amount'] = $row['tax_amount'];
        $tempRow['tax_percentage'] = $row['tax_percentage'];
        $tempRow['sub_total'] = $row['sub_total'];

        $rows[] = $tempRow;
        $i++;
    }


    if (!empty($res)) {
        $orders['error'] = false;
        $orders['total'] = $total;
        $orders['data'] = $rows;
        print_r(json_encode($orders));
    } else {
        $res['error'] = true;
        $res['message'] = "No orders found!";
        print_r(json_encode($res));
        return false;
    }
}

/*
---------------------------------------------------------------------------------------------------------
*/

if (isset($_POST['get_fund_transfers'])) {

    /* 
    4. get_fund_transfers
        accesskey:90336
        delivery_boy_id:82
        offset:0        // {optional}
        limit:10        // {optional}
        
        sort:id           // {optional}
        order:DESC / ASC            // {optional}
        
        search:search_value         // {optional}
        get_fund_transfers:1
        
    */

    $json_response = array();
    $id =  $db->escapeString(trim($fn->xss_clean($_POST['delivery_boy_id'])));
    $where = '';
    $offset = (isset($_POST['offset']) && !empty(trim($_POST['offset'])) && is_numeric($_POST['offset'])) ? $db->escapeString(trim($fn->xss_clean($_POST['offset']))) : 0;
    $limit = (isset($_POST['limit']) && !empty(trim($_POST['limit'])) && is_numeric($_POST['limit'])) ? $db->escapeString(trim($fn->xss_clean($_POST['limit']))) : 10;

    $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $db->escapeString(trim($fn->xss_clean($_POST['sort']))) : 'id';
    $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $db->escapeString(trim($fn->xss_clean($_POST['order']))) : 'DESC';
    if (isset($_POST['search']) && !empty($_POST['search'])) {
        $search = $db->escapeString($fn->xss_clean($_POST['search']));
        $where = " Where f.`id` like '%" . $search . "%' OR d.`name` like '%" . $search . "%' OR f.`message` like '%" . $search . "%' OR d.`mobile` like '%" . $search . "%' OR d.`address` like '%" . $search . "%' OR f.`opening_balance` like '%" . $search . "%' OR f.`closing_balance` like '%" . $search . "%' OR d.`balance` like '%" . $search . "%' OR f.`date_created` like '%" . $search . "%'";
    }

    if (empty($where)) {
        $where .= " WHERE delivery_boy_id = " . $id;
    } else {
        $where .= " AND delivery_boy_id = " . $id;
    }

    $sql = "SELECT COUNT(f.id) as total FROM `fund_transfers` f JOIN `delivery_boys` d ON f.delivery_boy_id=d.id" . $where;
    $db->sql($sql);
    $res = $db->getResult();
    foreach ($res as $row)
        $total = $row['total'];
    $sql = "SELECT f.*,d.name,d.mobile,d.address FROM `fund_transfers` f JOIN `delivery_boys` d ON f.delivery_boy_id=d.id " . $where . " ORDER BY " . $sort . " " . $order . " LIMIT " . $offset . ", " . $limit;
    $db->sql($sql);
    $res = $db->getResult();

    $json_response['total'] = $total;
    $rows = array();
    $tempRow = array();
    foreach ($res as $row) {
        $tempRow['id'] = $row['id'];
        $tempRow['name'] = $row['name'];
        $tempRow['mobile'] = $row['mobile'];
        $tempRow['address'] = $row['address'];
        $tempRow['delivery_boy_id'] = $row['delivery_boy_id'];
        $tempRow['type'] = $row['type'];
        $tempRow['amount'] = $row['amount'];
        $tempRow['opening_balance'] = $row['opening_balance'];
        $tempRow['closing_balance'] = $row['closing_balance'];
        $tempRow['status'] = $row['status'];
        $tempRow['message'] = $row['message'];
        $tempRow['date_created'] = $row['date_created'];

        $rows[] = $tempRow;
    }
    $json_response['error'] = false;
    $json_response['data'] = $rows;
    print_r(json_encode($json_response));
}
/* 
---------------------------------------------------------------------------------------------------------
*/

if (isset($_POST['update_delivery_boy_profile'])) {

    /* 
    5.update_delivery_boy_profile
        accesskey:90336
        delivery_boy_id:87
        name:any value       
		address:Jl Komplek Polri 
		dob:1992-07-07
		bank_name:SBI
		account_number: 12345678976543
		account_name: any value
		ifsc_code:ASDFGH45
		new_driving_license: image_file  { jpg, png, gif, jpeg }
		new_national_identity_card: image_file  { jpg, png, gif, jpeg }
		other_payment_info: value   // {optional}
        old_password:        // {optional}
        update_password:        // {optional}
		confirm_password:        // {optional}
        update_delivery_boy_profile:1
    */
    $json_response = array();
    $id =  $db->escapeString(trim($fn->xss_clean($_POST['delivery_boy_id'])));
    $name = $db->escapeString(trim($fn->xss_clean($_POST['name'])));
    $address = $db->escapeString(trim($fn->xss_clean($_POST['address'])));
    $old_password = (isset($_POST['old_password']) && !empty(trim($_POST['old_password']))) ? $db->escapeString(trim($fn->xss_clean($_POST['old_password']))) : "";
    $update_password = (isset($_POST['update_password']) && !empty(trim($_POST['update_password']))) ? $db->escapeString(trim($fn->xss_clean($_POST['update_password']))) : "";
    $confirm_password = (isset($_POST['confirm_password']) && !empty(trim($_POST['confirm_password']))) ? $db->escapeString(trim($fn->xss_clean($_POST['confirm_password']))) : "";
    $change_password = false;
    $update_dob = $db->escapeString($fn->xss_clean($_POST['dob']));
    $update_bank_name = $db->escapeString($fn->xss_clean($_POST['bank_name']));
    $update_account_number = $db->escapeString($fn->xss_clean($_POST['account_number']));
    $update_account_name = $db->escapeString($fn->xss_clean($_POST['account_name']));
    $update_ifsc_code = $db->escapeString($fn->xss_clean($_POST['ifsc_code']));
    $update_other_payment_info = !empty($_POST['other_payment_info']) ? $db->escapeString($fn->xss_clean($_POST['other_payment_info'])) : '';


    /* check if id is not empty and there is valid data in it */
    if (!isset($_POST['delivery_boy_id']) || empty(trim($_POST['delivery_boy_id'])) || !is_numeric($_POST['delivery_boy_id'])) {
        $json_response['error'] = true;
        $json_response['message'] = "Invalid Id of Delivery Boy";
        print_r(json_encode($json_response));
        return false;
        exit();
    }

    $sql = "SELECT * from delivery_boys where id='$id'";
    $db->sql($sql);
    $res_id = $db->getResult();
    // print_r($res_id);
    $num = $db->numRows($res_id);
    if ($num != 1) {
        $json_response['error'] = true;
        $json_response['message'] = "Delivery Boy is not Registered.";
        print_r(json_encode($json_response));
        return false;
        exit();
    }

    /* if any of the password field is set and old password is not set */
    if ((!empty($confirm_password) || !empty($update_password)) && empty($old_password)) {
        $json_response['error'] = true;
        $json_response['message'] = "Please enter old password.";
        print_r(json_encode($json_response));
        return false;
        exit();
    }

    /* either of the password field is not empty and is they don't match */
    if ((!empty($confirm_password) || !empty($update_password)) && ($update_password != $confirm_password)) {
        $json_response['error'] = true;
        $json_response['message'] = "Password and Confirm Password mismatched.";
        print_r(json_encode($json_response));
        return false;
        exit();
    }

    /* when all conditions are met check for old password in database */
    if (!empty($confirm_password) && !empty($update_password) && !empty($old_password)) {
        $old_password = md5($old_password);
        $sql = "Select password from `delivery_boys` where id = '$id' and password = '$old_password' ";
        $db->sql($sql);
        $res = $db->getResult();

        if (empty($res)) {
            $json_response['error'] = true;
            $json_response['message'] = "Old password mismatched.";
            print_r(json_encode($json_response));
            return false;
            exit();
        }
        $change_password = true;
        $confirm_password = md5($confirm_password);
    }

    $sql = "Update delivery_boys set `name`='" . $name . "',`address`='" . $address . "' ,`dob`='$update_dob',`bank_account_number`='$update_account_number',`bank_name`='$update_bank_name',`account_name`='$update_account_name',`ifsc_code`='$update_ifsc_code' ";
    $sql .= ($change_password) ? ", `password`='" . $confirm_password . "' " : "";
    $sql .= ($update_other_payment_info != "") ? ",`other_payment_information`='$update_other_payment_info'" : "";
    $sql .= " where `id` = '$id' ";

    if ($db->sql($sql)) {
        if (isset($_FILES['new_driving_license']) && $_FILES['new_driving_license']['size'] != 0 && $_FILES['new_driving_license']['error'] == 0 && !empty($_FILES['new_driving_license'])) {
            //image isn't empty and update the image
            $dr_image = $res_id[0]['driving_license'];
            // common image file extensions
            $result = $fn->validate_image($_FILES["new_driving_license"]);
            if (!$result) {
                $json_response['error'] = true;
                $json_response['message'] = "driving_license image type must jpg, jpeg, gif, or png!.";;
                return false;
                exit();
            }
            $target_path = '../../upload/delivery-boy/';
            $dr_filename = microtime(true) . '.' . strtolower($extension);
            $dr_full_path = $target_path . "" . $dr_filename;
            if (!move_uploaded_file($_FILES["new_driving_license"]["tmp_name"], $dr_full_path)) {
                $json_response['error'] = true;
                $json_response['message'] = "Can not upload image.";
                return false;
                exit();
            }
            if (!empty($dr_image)) {
                unlink($target_path . $dr_image);
            }
            $sql = "UPDATE delivery_boys SET `driving_license`='" . $dr_filename . "' WHERE `id`=" . $id;
            $db->sql($sql);
        }
        if (isset($_FILES['new_national_identity_card']) && $_FILES['new_national_identity_card']['size'] != 0 && $_FILES['new_national_identity_card']['error'] == 0 && !empty($_FILES['new_national_identity_card'])) {
            //image isn't empty and update the image
            $nic_image = $res_id[0]['national_identity_card'];
            // common image file extensions
            $result = $fn->validate_image($_FILES["new_driving_license"]);
            if (!$result) {
                $json_response['error'] = true;
                $json_response['message'] = "national_identity_card image type must jpg, jpeg, gif, or png!.";;
                return false;
                exit();
            }
            $target_path = '../../upload/delivery-boy/';
            $nic_filename = microtime(true) . '.' . strtolower($extension);
            $nic_full_path = $target_path . "" . $nic_filename;
            if (!move_uploaded_file($_FILES["new_national_identity_card"]["tmp_name"], $nic_full_path)) {
                $json_response['error'] = true;
                $json_response['message'] = "Can not upload image.";
                return false;
                exit();
            }
            if (!empty($nic_image)) {
                unlink($target_path . $nic_image);
            }
            $sql = "UPDATE delivery_boys SET `national_identity_card`='" . $nic_filename . "' WHERE `id`=" . $id;
            $db->sql($sql);
        }
        $json_response['error'] = false;
        $json_response['message'] = "Information Updated Successfully.";
        $json_response['message'] .= ($change_password) ? " and password also updated successfully." : "";
    } else {
        $json_response['error'] = true;
        $json_response['message'] = "Some Error Occurred! Please Try Again.";
    }
    print_r(json_encode($json_response));
}

/* 
---------------------------------------------------------------------------------------------------------
*/

if (isset($_POST['update_order_status']) && !empty($_POST['update_order_status'])) {

    /* 
    6.update_order_status
        accesskey:90336
		update_order_status:1
		order_id:169
        order_item_id:12577
        status:received | processed | shipped | delivered | cancelled | returned //optionnel
		delivery_boy_id:40

    */

    if (empty($_POST['order_id']) || empty($_POST['order_item_id'])  || empty($_POST['delivery_boy_id']) || !isset($_POST['is_available'])) {
        $response['error'] = true;
        $response['message'] = "Please pass all mandatory fields!";
        print_r(json_encode($response));
        return false;
    }
    $id = $db->escapeString(trim($fn->xss_clean($_POST['order_id'])));
    $postStatus = $db->escapeString($fn->xss_clean($_POST['status']));
    $delivery_boy_id = $db->escapeString(trim($fn->xss_clean(($_POST['delivery_boy_id']))));
    $order_item_id = $db->escapeString(trim($fn->xss_clean(($_POST['order_item_id']))));
    $is_available = $db->escapeString($function->xss_clean($_POST['is_available']));

    $sql = "SELECT delivery_boy_id FROM `order_items` WHERE id=" . $order_item_id;
    $db->sql($sql);
    $result = $db->getResult();
    $dboy_id = $result[0]['delivery_boy_id'];
    if ($delivery_boy_id != $dboy_id) {
        $response['error'] = true;
        $response['message'] = 'You are not authorized to update status of this order!';
        print_r(json_encode($response));
        return false;
    }

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
                $user_id = $res_seller[0]['user_id'];
                $id = $res_seller[0]['order_id'];
                if ($is_available == 1) {
                    $message = "L'article : " . $res_seller[0]['product_name'] . " est disponible";
                } else {
                    $message = "L'article : " . $res_seller[0]['product_name'] . " est indisponible";
                }
                $responseStatus = "Mise à jour du status de l'article";
                $function->send_order_update_notification($user_id, $responseStatus, $message, 'order', $id);
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
    // $response = $fn->update_order_status($id, $order_item_id, $postStatus, $delivery_boy_id);
    // print_r($response);
}

/* 
---------------------------------------------------------------------------------------------------------
*/

if (isset($_POST['delivery_boy_forgot_password']) && isset($_POST['mobile'])) {

    /* 
    7.delivery_boy_forgot_password
        accesskey:90336
		mobile:8989898989
		password:1234567
		delivery_boy_forgot_password:1
    */
    if (empty($_POST['password'])) {
        $response['error'] = true;
        $response['message'] = "Password should be filled!";
        print_r(json_encode($response));
        return false;
        exit();
    }

    if (empty($_POST['mobile'])) {
        $response['error'] = true;
        $response['message'] = "Mobile Number id not passed!";
        print_r(json_encode($response));
        return false;
        exit();
    }
    $mobile = $db->escapeString(trim($fn->xss_clean($_POST['mobile'])));
    $password = md5($db->escapeString($fn->xss_clean($_POST['password'])));

    $sql = "SELECT mobile from delivery_boys where mobile='$mobile'";
    $db->sql($sql);
    $res_mobile = $db->getResult();

    if ($res_mobile[0]['mobile'] == $mobile) {
        $sql_update = "UPDATE `delivery_boys` SET `password`='$password' WHERE `mobile`='$mobile'";
        $db->sql($sql_update);
        $response["error"]   = false;
        $response["message"] = "Password updated successfully";
    } else {
        $response["error"]   = true;
        $response["message"] = "Mobile number id not Registered!";
    }
    print_r(json_encode($response));
}

/* 
---------------------------------------------------------------------------------------------------------
*/

if (isset($_POST['get_notifications'])) {

    /* 
    8. get_notifications
        accesskey:90336
        delivery_boy_id:114
        offset:0        // {optional}
        limit:10        // {optional}
        
        sort:id           // {optional}
        order:DESC / ASC            // {optional}
        
        search:search_value         // {optional}
        get_notifications:1
        
    */

    $json_response = array();
    $id =  $db->escapeString(trim($fn->xss_clean($_POST['delivery_boy_id'])));
    $where = '';
    $offset = (isset($_POST['offset']) && !empty(trim($_POST['offset'])) && is_numeric($_POST['offset'])) ? $db->escapeString(trim($fn->xss_clean($_POST['offset']))) : 0;
    $limit = (isset($_POST['limit']) && !empty(trim($_POST['limit'])) && is_numeric($_POST['limit'])) ? $db->escapeString(trim($fn->xss_clean($_POST['limit']))) : 10;

    $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $db->escapeString(trim($fn->xss_clean($_POST['sort']))) : 'id';
    $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $db->escapeString(trim($fn->xss_clean($_POST['order']))) : 'DESC';
    if (isset($_POST['search']) && !empty($_POST['search'])) {
        $search = $db->escapeString($fn->xss_clean($_POST['search']));
        $where = " Where `id` like '%" . $search . "%' OR `title` like '%" . $search . "%' OR `message` like '%" . $search . "%' OR `type` like '%" . $search . "%' OR `date_created` like '%" . $search . "%'  ";
    }

    if (empty($where)) {
        $where .= " WHERE delivery_boy_id = " . $id;
    } else {
        $where .= " AND delivery_boy_id = " . $id;
    }

    $sql = "SELECT COUNT(id) as total FROM `delivery_boy_notifications` " . $where;
    $db->sql($sql);
    $res = $db->getResult();
    foreach ($res as $row)
        $total = $row['total'];
    $sql = "SELECT * FROM `delivery_boy_notifications` " . $where . " ORDER BY " . $sort . " " . $order . " LIMIT " . $offset . ", " . $limit;
    $db->sql($sql);
    $res = $db->getResult();

    $json_response['total'] = $total;
    $rows = array();
    $tempRow = array();
    foreach ($res as $row) {
        $tempRow['id'] = $row['id'];
        $tempRow['delivery_boy_id'] = $row['delivery_boy_id'];
        $tempRow['order_id'] = $row['order_item_id'];
        $tempRow['title'] = $row['title'];
        $tempRow['message'] = $row['message'];
        $tempRow['type'] = $row['type'];
        $tempRow['date_created'] = $row['date_created'];

        $rows[] = $tempRow;
    }
    $json_response['error'] = false;
    $json_response['data'] = $rows;
    print_r(json_encode($json_response));
}

/* 
---------------------------------------------------------------------------------------------------------
*/

if (isset($_POST['update_delivery_boy_fcm_id'])) {
    /* 
    9.update_delivery_boy_fcm_id
        accesskey:90336
        delivery_boy_id:114
        fcm_id:YOUR_FCM_ID
        update_delivery_boy_fcm_id:1
    */

    if (empty($_POST['fcm_id'])) {
        $response['error'] = true;
        $response['message'] = "Please pass the fcm_id!";
        print_r(json_encode($response));
        return false;
        exit();
    }

    $id = $db->escapeString(trim($_POST['delivery_boy_id']));
    if (isset($_POST['fcm_id']) && !empty($_POST['fcm_id'])) {
        $fcm_id = $db->escapeString($fn->xss_clean($_POST['fcm_id']));
        $sql1 = "update delivery_boys set `fcm_id` ='$fcm_id' where id = '" . $id . "'";
        if ($db->sql($sql1)) {
            $response['error'] = false;
            $response['message'] = "Delivery Boy fcm_id Updeted successfully.";
            print_r(json_encode($response));
        } else {
            $response['error'] = true;
            $response['message'] = "Can not update fcm_id of delivery boy.";
            print_r(json_encode($response));
        }
    }
}

/* 
---------------------------------------------------------------------------------------------------------
*/

if (isset($_POST['check_delivery_boy_by_mobile']) && isset($_POST['mobile'])) {

    /* 
    10.check_delivery_boy_by_mobile
        accesskey:90336
		mobile:8989898989
		check_delivery_boy_by_mobile:1
    */

    if (empty($_POST['mobile'])) {
        $response['error'] = true;
        $response['message'] = "Mobile Number id not passed!";
        print_r(json_encode($response));
        return false;
        exit();
    }
    $mobile = $db->escapeString(trim($fn->xss_clean($_POST['mobile'])));

    $sql = "SELECT mobile from delivery_boys where mobile='$mobile'";
    $db->sql($sql);
    $res_mobile = $db->getResult();

    if ($res_mobile[0]['mobile'] == $mobile) {
        $response["error"]   = false;
        $response["message"] = "Mobile number is Registered.";
    } else {
        $response["error"]   = true;
        $response["message"] = "Mobile number is not Registered!";
    }
    print_r(json_encode($response));
}

/* 
---------------------------------------------------------------------------------------------------------
*/

if ((isset($_POST['send_withdrawal_request'])) && ($_POST['send_withdrawal_request'] == 1)) {

    /* 
	11.send_withdrawal_request
		accesskey:90336
		send_withdrawal_request:1
		type:user/delivery_boy
		type_id:3
		amount:1000
		message:Message {optional}
*/

    $type = (isset($_POST['type']) && !empty($_POST['type'])) ? $db->escapeString($fn->xss_clean($_POST['type'])) : "";
    $type_id = (isset($_POST['type_id']) && !empty($_POST['type_id'])) ? $db->escapeString($fn->xss_clean($_POST['type_id'])) : "";
    $amount  = (isset($_POST['amount']) && !empty($_POST['amount'])) ? $db->escapeString($fn->xss_clean($_POST['amount'])) : "";
    $message = (isset($_POST['message']) && !empty($_POST['message'])) ? $db->escapeString($fn->xss_clean($_POST['message'])) : "";
    $type1 = $type =  ($type == 'user') ? 'users' : 'delivery_boys';
    if (!empty($type) && !empty($type_id) && !empty($amount)) {
        // check if such user or delivery boy exists or not
        if ($fn->is_user_or_dboy_exists($type, $type_id)) {
            // checking if balance is greater than amount requested or not 
            $balance = $fn->get_user_or_delivery_boy_balance($type, $type_id);
            if ($balance >= $amount) {
                // Debit amount requeted
                $new_balance =  $balance - $amount;
                if ($fn->debit_balance($type, $type_id, $new_balance)) {
                    // store wallet transaction
                    if ($type == 'delivery_boys') {
                        $sql = "INSERT INTO `fund_transfers` (`delivery_boy_id`,`type`,`amount`,`opening_balance`,`closing_balance`,`status`,`message`) VALUES ('" . $type_id . "','debit','" . $amount . "','" . $balance . "','" . $new_balance . "','SUCCESS','Balance debited against withdrawal request.')";
                        $db->sql($sql);
                    }
                    if ($type == 'users') {
                        $fn->add_wallet_transaction($order_id = "", $type_id, 'debit', $amount, 'Balance debited against withdrawal request.', 'wallet_transactions', '1');
                    }
                    $new_balance = 0;
                    // store withdrawal request
                    if ($fn->store_withdrawal_request($type, $type_id, $amount, $message)) {
                        if ($type == "users") {
                            $sql = "select  balance from  `users` WHERE id = $type_id";
                            $db->sql($sql);
                            $new_balance = $db->getResult();
                        } else if ($type == "delivery_boys") {
                            $sql = "select  balance from  `delivery_boys` WHERE id = $type_id";
                            $db->sql($sql);
                            $new_balance = $db->getResult();
                        }

                        $response['error'] = false;
                        $response['message'] = 'Withdrawal request accepted successfully!please wait for confirmation.';
                        $response['updated_balance'] = $new_balance[0]['balance'];
                    } else {
                        $response['error'] = true;
                        $response['message'] = 'Something went wrong please try again later!';
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = 'Something went wrong please try again later!';
                }
            } else {
                $response['error'] = true;
                $response['message'] = 'Insufficient balance';
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'No such ' . $type1 . ' exists';
        }
    } else {
        $response['error'] = true;
        $response['message'] = 'Please pass all the fields!';
    }

    print_r(json_encode($response));
    return false;
}

/* 
---------------------------------------------------------------------------------------------------------
*/

if ((isset($_POST['get_withdrawal_requests'])) && ($_POST['get_withdrawal_requests'] == 1)) {

    /*
12.get_withdrawal_requests
    accesskey:90336
    get_withdrawal_requests:1
	type:user/delivery_boy
	data_type:withdrawal_requests / fund_transfers  {optional}
    type_id:3
    offset:0 {optional}
    limit:5 {optional}
    sort:id          {optional}
    order:DESC / ASC           {optional}

*/

    $type  = (isset($_POST['type']) && !empty($_POST['type'])) ? $db->escapeString($fn->xss_clean($_POST['type'])) : "";
    $data_type  = (isset($_POST['data_type']) && !empty($_POST['data_type'])) ? $db->escapeString($fn->xss_clean($_POST['data_type'])) : "";
    $type_id = (isset($_POST['type_id']) && !empty($_POST['type_id'])) ? $db->escapeString($fn->xss_clean($_POST['type_id'])) : "";
    $limit = (isset($_POST['limit']) && !empty($_POST['limit']) && is_numeric($_POST['limit'])) ? $db->escapeString($fn->xss_clean($_POST['limit'])) : 10;
    $offset = (isset($_POST['offset']) && !empty($_POST['offset']) && is_numeric($_POST['offset'])) ? $db->escapeString($fn->xss_clean($_POST['offset'])) : 0;
    $sort = (isset($_POST['sort']) && !empty(trim($_POST['sort']))) ? $db->escapeString(trim($fn->xss_clean($_POST['sort']))) : 'id';
    $order = (isset($_POST['order']) && !empty(trim($_POST['order']))) ? $db->escapeString(trim($fn->xss_clean($_POST['order']))) : 'DESC';
    if (!empty($type) && !empty($type_id)) {


        /* if records found return data */
        if ($data_type == "withdrawal_requests") {
            $result = $fn->is_records_exists($type, $type_id, $offset, $limit);
            if (!empty($result)) {
                $sql = "SELECT count(id) as total from withdrawal_requests where `type` = '" . $type . "' AND `type_id` = " . $type_id;
                $db->sql($sql);
                $total = $db->getResult();
                $response['error'] = false;
                $response['total'] = $total[0]['total'];
                $response['data'] = array_values($result);
            } else {
                $response['error'] = true;
                $response['message'] = "Data does't exists!";
            }
        } elseif ($data_type == "fund_transfers") {

            $sql = "SELECT COUNT(f.id) as total FROM `fund_transfers` f JOIN `delivery_boys` d ON f.delivery_boy_id=d.id where f.delivery_boy_id = $type_id ";
            $db->sql($sql);
            $res = $db->getResult();
            foreach ($res as $row)
                $total = $row['total'];
            $sql = "SELECT f.*,d.name,d.mobile,d.address FROM `fund_transfers` f JOIN `delivery_boys` d ON f.delivery_boy_id=d.id where f.delivery_boy_id = $type_id ORDER BY " . $sort . " " . $order . " LIMIT " . $offset . ", " . $limit;
            $db->sql($sql);
            $res = $db->getResult();


            $rows = array();
            $tempRow = array();
            foreach ($res as $row) {
                $tempRow['id'] = $row['id'];
                $tempRow['name'] = $row['name'];
                $tempRow['mobile'] = $row['mobile'];
                $tempRow['address'] = $row['address'];
                $tempRow['delivery_boy_id'] = $row['delivery_boy_id'];
                $tempRow['type'] = $row['type'];
                $tempRow['amount'] = $row['amount'];
                $tempRow['opening_balance'] = $row['opening_balance'];
                $tempRow['closing_balance'] = $row['closing_balance'];
                $tempRow['status'] = $row['status'];
                $tempRow['message'] = $row['message'];
                $tempRow['date_created'] = $row['date_created'];

                $rows[] = $tempRow;
            }
            $response['error'] = false;
            $response['total'] = $total;
            $response['data'] = $rows;
        } else {
            $sql = "SELECT count(id) as total from withdrawal_requests where `type` = '" . $type . "' AND `type_id` = " . $type_id;
            $db->sql($sql);
            $total = $db->getResult();
            $response['error'] = false;
            $response['total'] = $total[0]['total'];
            $response['data'] = array_values($result);
        }
    } else {
        $response['error'] = true;
        $response['message'] = 'Please pass all the fields!';
    }

    print_r(json_encode($response));
    return false;
}

/* 
---------------------------------------------------------------------------------------------------------
*/

if (isset($_POST['change_availability']) && $_POST['change_availability'] != '') {
    /* 
    13.change_availability
        accesskey:90336
        delivery_boy_id:114
        is_available:1/0
        change_availability:1
    */

    if (!isset($_POST['delivery_boy_id']) || empty($_POST['delivery_boy_id'])) {
        $response['error'] = true;
        $response['message'] = "Please pass the delivery boy id!";
        print_r(json_encode($response));
        return false;
        exit();
    }

    $id = $db->escapeString(trim($_POST['delivery_boy_id']));
    $is_exist = $fn->rows_count('delivery_boys', 'id', 'id=' . $id);
    if ($is_exist == 1) {
        if (isset($_POST['change_availability']) && $_POST['change_availability'] != '') {
            $is_available = $db->escapeString($fn->xss_clean($_POST['is_available']));
            $sql1 = "update delivery_boys set `is_available` ='$is_available' where id = '" . $id . "'";
            if ($db->sql($sql1)) {
                $response['error'] = false;
                $response['message'] = "Availability updated successfully.";
                print_r(json_encode($response));
            } else {
                $response['error'] = true;
                $response['message'] = "Can not update availability.";
                print_r(json_encode($response));
            }
        }
    } else {
        $response['error'] = true;
        $response['message'] = "Delivery boy not exists.";
        print_r(json_encode($response));
    }
}
/* 
---------------------------------------------------------------------------------------------------------
*/
if (isset($_POST['get_orders_details_by_delivery_boy_id']) && !empty($_POST['get_orders_details_by_delivery_boy_id'])) {
    /*
    3.get_orders_by_delivery_boy_id
        accesskey:90336
        get_orders_by_delivery_boy_id:1
        delivery_boy_id:1
        order_item_id:12         // {optional}
        order_id:12608          // {optional}
        limit:10                // {optional}
        offset:0                // {optional}
        filter_order:received | processed | shipped | delivered | cancelled | returned    // {optional}
    */

    if (empty($_POST['delivery_boy_id'])) {
        $response['error'] = true;
        $response['message'] = "Delivery Boy ID should be filled!";
        print_r(json_encode($response));
        return false;
    }
    $where = '';
    $isToDay = (isset($_POST['isToDay']) && !empty($_POST['isToDay'])) ? $db->escapeString($fn->xss_clean($_POST['isToDay'])) : 0;
    $id = $db->escapeString($fn->xss_clean($_POST['delivery_boy_id']));
    $limit = (isset($_POST['limit']) && !empty($_POST['limit']) && is_numeric($_POST['limit'])) ? $db->escapeString($fn->xss_clean($_POST['limit'])) : 10;
    $offset = (isset($_POST['offset']) && !empty($_POST['offset']) && is_numeric($_POST['offset'])) ? $db->escapeString($fn->xss_clean($_POST['offset'])) : 0;


    if ($isToDay) {

        $sql = "SELECT COUNT( DISTINCT o.id) as total_row
        FROM
            orders o
        LEFT JOIN order_items oi ON
            oi.order_id = o.id
        LEFT JOIN users u ON
            u.id = oi.user_id
        WHERE
            oi.delivery_boy_id = $id
            and (oi.date_added BETWEEN DATE_SUB(NOW(), INTERVAL 1 DAY) AND NOW())";

        $db->sql($sql);
        $res = $db->getResult();
        foreach ($res as $row) {
            $total = $row['total_row'];
        }

        $sql = "SELECT DISTINCT
                o.id AS order_id,
                COUNT(oi.order_id) AS number_article,
                o.total,
                o.otp,
                o.delivery_rank,
                o.delivery_charge,
                o.final_total,
                o.payment_method,
                oi.date_added,
                o.active_status,
                o.delivery_time,
                u.id AS user_id,
                u.name AS user_name,
                u.mobile,
                o.latitude,
                o.longitude
            FROM
                orders o
            LEFT JOIN order_items oi ON
                oi.order_id = o.id
            LEFT JOIN users u ON
                u.id = oi.user_id
            WHERE
                oi.delivery_boy_id = $id
                and (oi.date_added BETWEEN DATE_SUB(NOW(), INTERVAL 1 DAY) AND NOW())
            GROUP BY
                o.id
            ORDER BY
                o.delivery_rank
            ASC
            LIMIT $offset , $limit
    ";
    } else {
        $sql = "SELECT COUNT( DISTINCT o.id) as total_row
        FROM
            orders o
        LEFT JOIN order_items oi ON
            oi.order_id = o.id
        LEFT JOIN users u ON
            u.id = oi.user_id
        WHERE
            oi.delivery_boy_id = $id";

        $db->sql($sql);
        $res = $db->getResult();
        foreach ($res as $row) {
            $total = $row['total_row'];
        }

        $sql = "SELECT DISTINCT
                o.id AS order_id,
                COUNT(oi.order_id) AS number_article,
                o.total,
                o.otp,
                o.delivery_rank,
                o.delivery_charge,
                o.final_total,
                o.payment_method,
                oi.date_added,
                o.active_status,
                o.delivery_time,
                u.id AS user_id,
                u.name AS user_name,
                u.mobile,
                o.latitude,
                o.longitude
            FROM
                orders o
            LEFT JOIN order_items oi ON
                oi.order_id = o.id
            LEFT JOIN users u ON
                u.id = oi.user_id
            WHERE
                oi.delivery_boy_id = $id
            GROUP BY
                o.id
            ORDER BY
                o.id
            DESC
            LIMIT $offset , $limit
    ";
    }

    $db->sql($sql);
    $res = $db->getResult();
    $rows = array();
    $tempRow = array();
    $i = 0;

    foreach ($res as $row) {
        $tempRow['order_id'] = $row['order_id'];
        $tempRow['user_id'] = $row['user_id'];
        $tempRow['user_name'] = $row['user_name'];
        $tempRow['mobile'] = $row['mobile'];
        $tempRow['number_article'] = $row['number_article'];
        $tempRow['otp'] = $row['otp'];
        $tempRow['total'] = $row['total'];
        $tempRow['payment_method'] = $row['payment_method'];
        $tempRow['active_status'] = $row['active_status'];
        $tempRow['date_added'] = date('d-m-Y', strtotime($row['date_added']));
        $tempRow['delivery_time'] = $row['delivery_time'];
        $tempRow['delivery_charge'] = $row['delivery_charge'];
        $tempRow['delivery_rank'] = $row['delivery_rank'];
        $tempRow['latitude'] = $row['latitude'];
        $tempRow['longitude'] = $row['longitude'];

        $rows[] = $tempRow;
        $i++;
    }


    if (!empty($res)) {
        $orders['error'] = false;
        $orders['total'] = $total;
        $orders['data'] = $rows;
        print_r(json_encode($orders));
    } else {
        $orders['error'] = false;
        $orders['total'] = 0;
        $orders['data'] = array();
        print_r(json_encode($orders));
        return false;
    }
}

/* 
---------------------------------------------------------------------------------------------------------
*/

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
                            $response['message'] = "La commande a été déjà livrée.";
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
                $sql = "SELECT oi.id as order_items, oi.is_available,oi.sub_total,o.total,o.delivery_charge,o.final_total,o.payment_method,oi.delivery_boy_id,o.service_charge, s.id as seller_id, s.commission FROM orders o join order_items oi on oi.order_id=o.id join seller s on s.id = oi.seller_id WHERE o.id=" . $res[0]['id'];
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
                        $amount = $res_boy[$i]['sub_total'] - ($res_boy[$i]['sub_total'] / 100 * $res_boy[$i]['commission']);
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
                 //Send to admin notification mail
            $subject = $response['message'];
            $to = "info@koumishop.com";
            $message = "Salut, cher(è) gestionnaire la commande dont l'ID est ".$res[0]['id']. ". A été livrée avec succès merci!";
            send_email($to, $subject, $message);
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
                    switch ($postStatus) {
                        case 'received':
                            $responseStatus = "Votre commande a été reçue";
                            break;
                        case 'shipped':
                            $responseStatus = "Votre commande est en cours de livraison";
                            break;
                        case 'processed':
                            $responseStatus = "Votre commande est en cours de traitement";
                            break;
                        case 'delivered':
                            $responseStatus = "Votre commande est livrée";
                            break;
                        case 'awaiting_payment':
                            $responseStatus = "Votre commande est en attente de paiement.";
                            break;
                        default:
                            $responseStatus = "Votre commande est :  " . $res[0]['active_status'] . ".";
                            break;
                    }
                    $user_data = $function->get_data($columns = ['name', 'email', 'mobile', 'country_code'], 'id=' . $user_id, 'users');
                    $to = $user_data[0]['email'];
                    $mobile = $user_data[0]['mobile'];
                    $country_code = $user_data[0]['country_code'];
                    $subject = "Votre commande a été " . ucwords($responseStatus);
                    $message = "Bonjour, cher(è) " . ucwords($user_data[0]['name']) . ",  Voici la nouvelle mise à jour de votre commande pour l'ID de commande : #" . $id . " " . ucwords($responseStatus) . ". Veuillez en prendre note.";
                    $message .= "Merci d'utiliser nos services ! Vous recevrez des mises à jour sur votre commande par e-mail !";


                    // $function->send_order_update_notification($user_id, "Votre commande a été " . ucwords($postStatus), $message, 'order', $id);
                    $function->send_order_update_notification($user_id, ucwords($responseStatus), $message, 'order', $id);
                    send_email($to, $subject, $message);
                    $message = "Bonjour, cher(è) " . ucwords($user_data[0]['name']) . ",  Voici la nouvelle mise à jour de votre commande pour l'ID de commande : #" . $id . "  " . ucwords($responseStatus) . ". Veuillez en prendre note.";
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
}
