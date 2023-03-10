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

if (!isset($_POST['promotion'])  || trim($_POST['promotion']) !=1) {
    $response['error'] = true;
    $response['message'] = "Veuillez vérifier le parametre!";
    print_r(json_encode($response));
    return false;
}
try { 
if (isset($_POST['promotion']) && $_POST['promotion'] == 1) {
    $sql_query = "SELECT * FROM `promotion` s";
    $db->sql($sql_query);
    $result = $db->getResult();

    $response = array();

    foreach ($result as $row) {

        $tempRow['id'] = $row['id'];
        $tempRow['illustration'] = DOMAIN_URL . $row['image'];
        $tempRow['service_description'] = $row['description'];
        $tempRow['is_available'] = $row['is_available'];
        $rows[] = $tempRow;
    }

    if ($db->numRows($result) > 0) {
        $response['error']     = false;
        $response['message']   = "Promotion retrieved successfully!";
        $response['data'] = $rows;
    } else {
        $response['error']     = true;
        $response['message']   = "Promotion data does not exists!";
        $response['data'] = array();
    }

    print_r(json_encode($response));
    return false;
}else{
    $response['error']     = true;
    $response['message']   = "Veuillez vérifier le parametre!";
    return false;
   
}
 }catch(Exception $exception){
     $response['error']     = true;
     $response['message']   = $exception->getMessage();
     print_r(json_encode($response));
 }
 /* 
    1.get_promotion 
        accesskey:90336
        promotion:1
    */
//           
// if (isset($_POST['promotion']) && $_POST['promotion'] == 1) {
//     $where = "";
//     $response = array();

//     $sql_query = "SELECT * FROM `promotion limit 1`";
//     $db->sql($sql_query);
//     $result = $db->getResult();

//     if ($db->numRows($result) > 0) {
//         $response['error']     = false;
//         $response['message']   = "rrr";
        
//     } else {
//         $response['error']     = true;
//         $response['message']   = "Seller data does not exists!";
//         $response['data'] = array();
//     }

//     print_r(json_encode($response));
//     return false;
// }






