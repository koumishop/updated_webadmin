<?php
header('Access-Control-Allow-Origin: *');
header("Content-Type: application/json");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
include '../includes/crud.php';
require_once '../includes/functions.php';
include_once('../includes/custom-functions.php');
$fn = new custom_functions;
include_once('../includes/variables.php');
include_once('verify-token.php');
$db = new Database();
$db->connect();
$config = $fn->get_configurations();
$time_slot_config = $fn->time_slot_config();
if (isset($config['system_timezone']) && isset($config['system_timezone_gmt'])) {
    date_default_timezone_set($config['system_timezone']);
    $db->sql("SET `time_zone` = '" . $config['system_timezone_gmt'] . "'");
} else {
    date_default_timezone_set('Asia/Kolkata');
    $db->sql("SET `time_zone` = '+05:30'");
}

if (!verify_token()) {
    return false;
}


if (!isset($_POST['accesskey'])  || trim($_POST['accesskey']) != $access_key) {
    $response['error'] = true;
    $response['message'] = "No Accsess key found!";
    print_r(json_encode($response));
    return false;
}

if (isset($_POST['all_service']) && $_POST['all_service'] == 1) {
    $sql_query = "SELECT * FROM `services` s";
    $db->sql($sql_query);
    $result = $db->getResult();


    $where = "";
    $response = array();

    foreach ($result as $row) {

        $tempRow['id'] = $row['id'];
        $tempRow['name'] = $row['name'];
        $tempRow['illustration'] = DOMAIN_URL . $row['image'];
        $tempRow['service_description'] = $row['description'];
        $tempRow['is_available'] = $row['is_available'];
        $rows[] = $tempRow;
    }

    if ($db->numRows($result) > 0) {
        $response['error']     = false;
        $response['message']   = "Service retrieved successfully!";
        $response['data'] = $rows;
    } else {
        $response['error']     = true;
        $response['message']   = "Service data does not exists!";
        $response['data'] = array();
    }

    print_r(json_encode($response));
    return false;
}

if (isset($_POST['get_seller_by_service']) && $_POST['get_seller_by_service'] == 1) {
    /* 
    1.get_seller_data_by_service
        accesskey:90336
        get_seller_data:1
        service_id:1
    */

    $where = "";
    $response = array();
    if (isset($_POST['service_id']) && $_POST['service_id'] != "" && is_numeric($_POST['service_id'])) {
        $id = $db->escapeString($fn->xss_clean($_POST['service_id']));
        $where .=  " AND s.id= $id ";
    }

    $sql_query = "SELECT s.id service_id,s.name service_name, seller.* FROM `services` s INNER JOIN seller ON seller.service_id = s.id WHERE seller.status = 1 $where";
    $db->sql($sql_query);
    $result = $db->getResult();

    $rows = array();
    $tempRow = array();
    $service_id = "";
    $service_name = "";

    foreach ($result as $row) {
        $seller_address = $fn->get_seller_address($row['id']);

        $service_id = $row['service_id'];
        $service_name = $row['service_name'];

        $tempRow['id'] = $row['id'];
        $tempRow['name'] = $row['name'];
        $tempRow['store_name'] = $row['store_name'];
        $tempRow['slug'] = $row['slug'];
        $tempRow['email'] = $row['email'];
        $tempRow['mobile'] = $row['mobile'];
        $tempRow['balance'] = strval(ceil($row['balance']));
        $tempRow['store_url'] = $row['store_url'];
        $tempRow['store_description'] = $row['store_description'];
        $tempRow['street'] = $row['street'];
        $tempRow['pincode_id'] = $row['pincode_id'];
        $tempRow['state'] = $row['state'];
        $tempRow['categories'] = $row['categories'];
        $tempRow['account_number'] = $row['account_number'];
        $tempRow['bank_ifsc_code'] = $row['bank_ifsc_code'];
        $tempRow['bank_name'] = $row['bank_name'];
        $tempRow['account_name'] = $row['account_name'];
        $tempRow['logo'] = DOMAIN_URL . 'upload/seller/' . $row['logo'];
        $tempRow['national_identity_card'] = DOMAIN_URL . 'upload/seller/' . $row['national_identity_card'];
        $tempRow['address_proof'] = DOMAIN_URL . 'upload/seller/' . $row['address_proof'];
        $tempRow['pan_number'] = !empty($row['pan_number']) ? $row['pan_number'] : "";
        $tempRow['tax_name'] = !empty($row['tax_name']) ? $row['tax_name'] : "";
        $tempRow['tax_number'] = !empty($row['tax_number']) ? $row['tax_number'] : "";
        $tempRow['categories'] = !empty($row['categories']) ? $row['categories'] : "";
        $tempRow['longitude'] = (!empty($row['longitude']))  ? $row['longitude'] : "";
        $tempRow['latitude'] = !empty($row['latitude'])  ? $row['latitude'] : "";
        $tempRow['seller_address'] = $seller_address;
        $rows[] = $tempRow;
    }

    if ($db->numRows($result) > 0) {
        $response['error']     = false;
        $response['message']   = "Seller retrieved successfully!";
        $response['service_id']     = $service_id;
        $response['service_name']     = $service_name;
        $response['message']   = "Seller retrieved successfully!";
        $response['data'] = $rows;
    } else {
        $response['error']     = true;
        $response['message']   = "Seller data does not exists!";
        $response['data'] = array();
    }

    print_r(json_encode($response));
    return false;
}

if (isset($_POST['get_category_by_service']) && $_POST['get_category_by_service'] == 1) {

    $where = "";
    if (isset($_POST['slug']) && !empty($_POST['slug'])) {
        $slug = $db->escapeString($fn->xss_clean($_GET['slug']));
        $where = " AND slug = '$slug' ";
    }
    if (isset($_POST['service_id']) && $_POST['service_id'] != "" && is_numeric($_POST['service_id'])) {
        $id = $db->escapeString($fn->xss_clean($_POST['service_id']));
        $where .=  " AND s.id= $id ";
    }
    $sql_query = "SELECT c.* FROM category c INNER JOIN services s ON s.id = c.service_id where status = 1 " . $where . " ORDER BY row_order ASC";
    $db->sql($sql_query);
    $res = $db->getResult();

    if (!empty($res)) {
        for ($i = 0; $i < count($res); $i++) {
            $res[$i]['image'] = (!empty($res[$i]['image'])) ? DOMAIN_URL . '' . $res[$i]['image'] : '';
            $res[$i]['web_image'] = (!empty($res[$i]['web_image'])) ? DOMAIN_URL . '' . $res[$i]['web_image'] : '';
        }
        $tmp = [];
        foreach ($res as $r) {
            $r['childs'] = [];

            $db->sql("SELECT * FROM subcategory WHERE category_id = '" . $r['id'] . "' ORDER BY id DESC");
            $childs = $db->getResult();
            if (!empty($childs)) {
                for ($i = 0; $i < count($childs); $i++) {
                    $childs[$i]['image'] = (!empty($childs[$i]['image'])) ? DOMAIN_URL . '' . $childs[$i]['image'] : '';
                    $r['childs'][$childs[$i]['slug']] = (array)$childs[$i];
                }
            }
            $tmp[] = $r;
        }
        $res = $tmp;

        $data = $fn->get_settings('categories_settings', true);
        $response['style'] =  $data['cat_style'];
        $response['visible_count'] = $data['max_visible_categories'];
        $response['column_count'] = ($data['cat_style'] == "style_2") ? 0 : $data['max_col_in_single_row'];
        $response['error'] = false;
        $response['message'] = "Categories retrived successfully";
        $response['data'] = $res;
    } else {
        $response['error'] = true;
        $response['message'] = "No data found!";
    }
    print_r(json_encode($response));
    return false;
}

if (isset($_POST['get_product_by_subcategory_to_category']) && $_POST['get_product_by_subcategory_to_category'] == 1) {
    $category_id = (isset($_POST['category_id']) && !empty($_POST['category_id'])) ? $db->escapeString($fn->xss_clean($_POST['category_id'])) : "";

    if (empty($category_id)) {
        $response['error'] = true;
        $response['message'] = "Please send category id and service id";
        print_r(json_encode($response));
        return false;
    }
    $where = "";
    if (isset($_POST['category_id']) && !empty($_POST['category_id']) && is_numeric($_POST['category_id'])) {
        $where .=  !empty($where) ? " AND `category_id`=" . $category_id  : " WHERE `category_id`=" . $category_id;
    }

    $sql = "SELECT id, name FROM subcategory $where ";
    $db->sql($sql);
    $subCat = $db->getResult();
    $rows = $data = $tempRow = array();
    $i = 0;
    $j = 0;

    foreach ($subCat as $row) {
        $sql = "SELECT p.*,s.name as seller_name,s.status as seller_status,(SELECT MIN(pv.price) FROM product_variant pv WHERE pv.product_id=p.id) as price FROM `products` p  INNER JOIN seller s ON s.id = p.seller_id WHERE p.subcategory_id = " . $row['id'] . " ORDER BY id DESC ";
        $db->sql($sql);
        $res[$i]['subcategory_name'] = $row['name'];
        $res[$i]['subcategory_id'] = $row['id'];
        $products = array();
        $res[$i]['products'] = $db->getResult();
        $i++;
        /*
        foreach ($res[$i]['subcategory'] as $res) {
            $tempRow['id'] = $res['id'];
            $tempRow['seller_name'] = $res['seller_name'];
            $tempRow['tax_id'] = $res['tax_id'];
            $tempRow['row_order'] = $res['row_order'];
            $tempRow['name'] = $res['name'];
            $tempRow['slug'] = $res['slug'];
            $tempRow['category_id'] = $res['category_id'];
            $tempRow['subcategory_id'] = $res['subcategory_id'];
            $tempRow['indicator'] = $res['indicator'];
            $tempRow['manufacturer'] = $res['manufacturer'];
            $tempRow['made_in'] = $res['made_in'];
            $tempRow['return_status'] = $res['return_status'];
            $tempRow['cancelable_status'] = $res['cancelable_status'];
            $tempRow['till_status'] = $res['till_status'];
            $tempRow['seller_status'] = $res['seller_status'];
            $tempRow['date_added'] = $res['date_added'];
            $tempRow['price'] = $res['price'];
            $tempRow['date_added'] = $res['date_added'];
            $tempRow['type'] = $res['type'];
            $tempRow['is_approved'] = $res['is_approved'];
            $tempRow['return_days'] = $res['return_days'];
            $tempRow['image'] = (!empty($res['image'])) ? DOMAIN_URL . '' . $res['image'] : '';
            $products = $tempRow;
        }
        */
    }

    if (!empty($res)) {
        // for ($i = 0; $i < count($res); $i++) {
        //     $res[$i]['image'] = (!empty($res[$i]['image'])) ? DOMAIN_URL . '' . $res[$i]['image'] : '';
        // }
        $response['error'] = false;
        $response['message'] = "Sub Categories retrieved successfully";
        // $response['total'] = $total[0]['total'];
        $response['data'] = array_values($res);
    } else {
        $response['error'] = true;
        $response['message'] = "No data found!";
        // $response['total'] = $total[0]['total'];
        $response['data'] = array();
    }
    print_r(json_encode($res));
    return false;
}

if (isset($_POST['get_product_by_subcategory']) && $_POST['get_product_by_subcategory'] == 1) {
    $subcategory_id = (isset($_POST['subcategory_id']) && !empty($_POST['subcategory_id'])) ? $db->escapeString($fn->xss_clean($_POST['subcategory_id'])) : "";

    $where = "";
    if (isset($_POST['subcategory_id']) && !empty($_POST['subcategory_id']) && is_numeric($_POST['subcategory_id'])) {
        $where .=  !empty($where) ? " AND `subcategory_id`=" . $subcategory_id  : " WHERE `subcategory_id`=" . $subcategory_id;
    }

    $sql = "SELECT COUNT(id) as total FROM products $where ";
    $db->sql($sql);
    $total = $db->getResult();

    $sql = "SELECT s.*, p.* FROM subcategory s INNER JOIN products p ON p.subcategory_id =s.id  $where ORDER BY p.id DESC ";
    $db->sql($sql);
    $res = $db->getResult();
    if (!empty($res)) {
        for ($i = 0; $i < count($res); $i++) {
            $res[$i]['image'] = (!empty($res[$i]['image'])) ? DOMAIN_URL . '' . $res[$i]['image'] : '';
        }
        $response['error'] = false;
        $response['message'] = "Products retrieved successfully";
        $response['total'] = $total[0]['total'];
        $response['data'] = $res;
    } else {
        $response['error'] = true;
        $response['message'] = "No products available.";
        $response['total'] = $total[0]['total'];
        $response['data'] = array();
    }
    print_r(json_encode($response));
    return false;
}
