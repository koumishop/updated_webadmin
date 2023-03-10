<?php
session_start();
include '../includes/crud.php';
include_once('../includes/variables.php');
include_once('../includes/custom-functions.php');


header("Content-Type: application/json");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
//header("Content-Type: multipart/form-data");
header('Access-Control-Allow-Origin: *');
// date_default_timezone_set('Asia/Kolkata');

$fn = new custom_functions;
include_once('verify-token.php');
$db = new Database();
$db->connect();
$response = array();

$config = $fn->get_configurations();
$time_slot_config = $fn->time_slot_config();
if (isset($config['system_timezone']) && isset($config['system_timezone_gmt'])) {
    date_default_timezone_set($config['system_timezone']);
    $db->sql("SET `time_zone` = '" . $config['system_timezone_gmt'] . "'");
} else {
    date_default_timezone_set('Asia/Kolkata');
    $db->sql("SET `time_zone` = '+05:30'");
}

if (!isset($_POST['accesskey'])) {
    $response['error'] = true;
    $response['message'] = "Access key is invalid or not passed!";
    print_r(json_encode($response));
    return false;
}
$accesskey = $db->escapeString($fn->xss_clean_array($_POST['accesskey']));
if ($access_key != $accesskey) {
    $response['error'] = true;
    $response['message'] = "invalid accesskey!";
    print_r(json_encode($response));
    return false;
}

if (!verify_token()) {
    return false;
}
/*
1.add_to_cart
    accesskey:90336
    add_to_cart:1
    user_id:3
    product_id:1
    product_variant_id:4
    qty:2
*/
if ((isset($_POST['add_to_cart'])) && ($_POST['add_to_cart'] == 1)) {
    $user_id = (isset($_POST['user_id']) && !empty($_POST['user_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['user_id'])) : "";
    $product_id = (isset($_POST['product_id']) && !empty($_POST['product_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['product_id'])) : "";
    $product_variant_id  = (isset($_POST['product_variant_id']) && !empty($_POST['product_variant_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['product_variant_id'])) : "";
    $qty = (isset($_POST['qty']) && !empty($_POST['qty'])) ? $db->escapeString($fn->xss_clean_array($_POST['qty'])) : "";
    if (!empty($user_id) && !empty($product_id)) {
        if (!empty($product_variant_id)) {
            if ($fn->is_item_available($product_id, $product_variant_id)) {
                $sql = "select serve_for,stock from product_variant where id = " . $product_variant_id;
                $db->sql($sql);
                $stock = $db->getResult();
                if ($stock[0]['stock'] > 0 && $stock[0]['serve_for'] == 'Available') {
                    // $total_allowed_quantity = $fn->get_data($columns = ['total_allowed_quantity'], "id='" . $product_id . "'", 'products');
                    // if (isset($total_allowed_quantity[0]['total_allowed_quantity']) && !empty($total_allowed_quantity[0]['total_allowed_quantity'])) {
                    // }
                    if ($fn->is_item_available_in_user_cart($user_id, $product_variant_id)) {

                        /* if item found in user's cart update it */
                        if (empty($qty) || $qty == 0) {
                            $sql = "DELETE FROM cart WHERE user_id = $user_id AND product_variant_id = $product_variant_id";
                            if ($db->sql($sql)) {
                                $response['error'] = false;
                                $response['message'] = 'Item removed from users cart due to 0 quantity';
                            } else {
                                $response['error'] = true;
                                $response['message'] = 'Something went wrong please try again!';
                            }
                            print_r(json_encode($response));
                            return false;
                        }
                        // check for total allowed quantity
                        $total_quantity = $fn->get_data($columns = ['sum(qty) as total'], "product_id='" . $product_id . "' and user_id='" . $user_id . "' and save_for_later='0'", 'cart');
                        if (isset($total_quantity[0]['total']) && !empty($total_quantity[0]['total'])) {
                            $total_allowed_quantity = $fn->get_data($columns = ['total_allowed_quantity'], "id='" . $product_id . "'", 'products');
                            if (isset($total_allowed_quantity[0]['total_allowed_quantity']) && !empty($total_allowed_quantity[0]['total_allowed_quantity'])) {
                                $total_quantity = $total_quantity[0]['total'];
                                $temp = $fn->get_data($columns = ['qty'], "product_variant_id='" . $product_variant_id . "' and user_id='" . $user_id . "'", 'cart');
                                $total_quantity = $total_quantity - $temp[0]['qty'];
                                $total_quantity = $total_quantity + $qty;
                                if ($total_quantity > $total_allowed_quantity[0]['total_allowed_quantity']) {
                                    $response['error'] = true;
                                    $response['message'] = 'Total allowed quantity for this product is ' . $total_allowed_quantity[0]['total_allowed_quantity'] . '!';
                                    print_r(json_encode($response));
                                    return false;
                                }
                            }
                        }
                        $data = array(
                            'qty' => $qty,
                            'save_for_later' => 0
                        );
                        if ($db->update('cart', $data, 'user_id=' . $user_id . ' AND product_variant_id=' . $product_variant_id)) {
                            $response['error'] = false;
                            $response['message'] = 'Item added in users cart successfully';
                        } else {
                            $response['error'] = true;
                            $response['message'] = 'Something went wrong please try again!';
                        }
                    } else {

                        /* Check user status */
                        $sql = "select status from users where id = " . $user_id;
                        $db->sql($sql);
                        $result = $db->getResult();
                        if (isset($result[0]['status']) && $result[0]['status'] == 1) {
                            $total_allowed_quantity = $fn->get_data($columns = ['total_allowed_quantity'], "id='" . $product_id . "'", 'products');
                            if (isset($total_allowed_quantity[0]['total_allowed_quantity']) && !empty($total_allowed_quantity[0]['total_allowed_quantity'])) {
                                if ($qty > $total_allowed_quantity[0]['total_allowed_quantity']) {
                                    $response['error'] = true;
                                    $response['message'] = 'Total allowed quantity for this product is ' . $total_allowed_quantity[0]['total_allowed_quantity'] . '!';
                                    print_r(json_encode($response));
                                    return false;
                                }
                            }
                            /* if item not found in user's cart add it */
                            $data = array(
                                'user_id' => $user_id,
                                'product_id' => $product_id,
                                'product_variant_id' => $product_variant_id,
                                'qty' => $qty
                            );
                            if ($db->insert('cart', $data)) {
                                $response['error'] = false;
                                $response['message'] = 'Item added to users cart successfully';
                            } else {
                                $response['error'] = true;
                                $response['message'] = 'Something went wrong please try again!';
                            }
                        } else {
                            $response['error'] = true;
                            $response['message'] = 'Not allowed to add to cart as your account is de-activated!';
                        }
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = 'Opps stock is not available!';
                }
            } else {
                $response['error'] = true;
                $response['message'] = 'No such item available!';
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'Please choose atleast one item!';
        }
    } else {
        $response['error'] = true;
        $response['message'] = 'Please pass all the fields!';
    }

    print_r(json_encode($response));
    return false;
}

/*
    2.add_multiple_items
        accesskey:90336
        add_multiple_items OR save_for_later_items:1
        user_id:3
        product_variant_id:203,198,202
        qty:1,2,1
    */
if (((isset($_POST['add_multiple_items'])) && ($_POST['add_multiple_items'] == 1)) || ((isset($_POST['save_for_later_items'])) && ($_POST['save_for_later_items'] == 1))) {

    $user_id = (isset($_POST['user_id']) && !empty($_POST['user_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['user_id'])) : "";
    $product_variant_id  = (isset($_POST['product_variant_id']) && !empty($_POST['product_variant_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['product_variant_id'])) : "";
    $qty = (isset($_POST['qty']) && !empty($_POST['qty'])) ? $db->escapeString($fn->xss_clean_array($_POST['qty'])) : "";
    $empty_qty = $is_variant =  $is_product = false;
    $empty_qty_1 = false;
    $item_exists = false;
    $item_exists_1 = false;
    $item_exists_2 = false;

    $sql = "SELECT * FROM users where id = $user_id";
    $db->sql($sql);
    $res1 = $db->getResult();
    if ($res1[0]['status'] == 1) {
        if (!empty($user_id)) {
            if (!empty($product_variant_id)) {
                $product_variant_id = explode(",", $product_variant_id);
                $qty = explode(",", $qty);
                for ($i = 0; $i < count($product_variant_id); $i++) {
                    if ((isset($_POST['add_multiple_items'])) && ($_POST['add_multiple_items'] == 1)) {
                        if ($fn->get_product_id_by_variant_id($product_variant_id[$i])) {
                            $product_id = $fn->get_product_id_by_variant_id($product_variant_id[$i]);
                            if ($fn->is_item_available($product_id, $product_variant_id[$i])) {
                                if ($fn->is_item_available_in_save_for_later($user_id, $product_variant_id[$i])) {
                                    $data = array(
                                        'save_for_later' => 0
                                    );
                                    $db->update('cart', $data, 'user_id=' . $user_id . ' AND product_variant_id=' . $product_variant_id[$i]);
                                }
                                if ($fn->is_item_available_in_user_cart($user_id, $product_variant_id[$i])) {
                                    $item_exists = true;
                                    if (empty($qty[$i]) || $qty[$i] == 0) {
                                        $empty_qty = true;
                                        $sql = "DELETE FROM cart WHERE user_id = $user_id AND product_variant_id = $product_variant_id[$i]";
                                        $db->sql($sql);
                                    } else {
                                        $data = array(
                                            'qty' => $qty[$i]
                                        );
                                        $db->update('cart', $data, 'user_id=' . $user_id . ' AND product_variant_id=' . $product_variant_id[$i]);
                                    }
                                } else {
                                    if (!empty($qty[$i]) && $qty[$i] != 0) {
                                        $data = array(
                                            'user_id' => $user_id,
                                            'product_id' => $product_id,
                                            'product_variant_id' => $product_variant_id[$i],
                                            'qty' => $qty[$i]
                                        );
                                        $db->insert('cart', $data);
                                    } else {
                                        $empty_qty_1 = true;
                                    }
                                }
                            } else {
                                $is_variant = true;
                            }
                        } else {
                            $is_product = true;
                        }
                    } else if ((isset($_POST['save_for_later_items'])) && ($_POST['save_for_later_items'] == 1)) {
                        if ($fn->is_item_available_in_user_cart($user_id, $product_variant_id[$i])) {
                            $item_exists_1 = true;
                            $data = array(
                                'save_for_later' => 1
                            );
                            $db->update('cart', $data, 'user_id=' . $user_id . ' AND product_variant_id=' . $product_variant_id[$i]);
                        } else {
                            $item_exists_2 = true;
                        }
                    }
                }
                $response['error'] = false;
                $response['message'] = $item_exists == true ? 'Cart Updated successfully!' : 'Cart Added Successfully';
                $response['message'] .= $item_exists_1 == true ? 'Item add to save for later!' : '';
                $response['message'] .= $item_exists_2 == true ? 'Item not add into cart!' : '';
                $response['message'] .= $empty_qty == true ? 'Some items removed due to 0 quantity' : '';
                $response['message'] .= $empty_qty_1 == true ? 'Some items not added due to 0 quantity' : '';
                $response['message'] .= $is_variant == true ? 'Some items not present in product list now' : '';
                $response['message'] .= $is_product == true ? 'Some items not present in product list now' : '';
            } else {
                $response['error'] = true;
                $response['message'] = 'Please choose atleast one item!';
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'Please pass all the fields!';
        }
    } else {
        $response['error'] = true;
        $response['message'] = 'Your Account is De-active ask on Customer Support!';
    }
    print_r(json_encode($response));
    return false;
}

/*
3.remove_from_cart
    accesskey:90336
    remove_from_cart:1
    user_id:3
    product_variant_id:4 {optional}
*/
if ((isset($_POST['remove_from_cart'])) && ($_POST['remove_from_cart'] == 1)) {
    $user_id  = (isset($_POST['user_id']) && !empty($_POST['user_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['user_id'])) : "";
    $product_variant_id = (isset($_POST['product_variant_id']) && !empty($_POST['product_variant_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['product_variant_id'])) : "";
    if (!empty($user_id)) {
        if ($fn->is_item_available_in_user_cart($user_id, $product_variant_id)) {
            /* if item found in user's cart remove it */
            $sql = "DELETE FROM cart WHERE user_id=" . $user_id . " and save_for_later=0";
            $sql .= !empty($product_variant_id) ? " AND product_variant_id=" . $product_variant_id : "";
            if ($db->sql($sql) && !empty($product_variant_id)) {
                $response['error'] = false;
                $response['message'] = 'Item removed from users cart successfully';
            } elseif ($db->sql($sql) && empty($product_variant_id)) {
                $response['error'] = false;
                $response['message'] = 'All items removed from users cart successfully';
            } else {
                $response['error'] = true;
                $response['message'] = 'Something went wrong please try again!';
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'Item not found in users cart!';
        }
    } else {
        $response['error'] = true;
        $response['message'] = 'Please pass all the fields!';
    }

    print_r(json_encode($response));
    return false;
}

/*
    4.get_user_cart
        accesskey:90336
        get_user_cart:1
        user_id:3
    */
if ((isset($_POST['get_user_cart'])) && ($_POST['get_user_cart'] == 1)) {
    $ready_to_add = false;
    $pincode_id = "";
    $user_id  = (isset($_POST['user_id']) && !empty($_POST['user_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['user_id'])) : "";
    $address_id  = (isset($_POST['address_id']) && !empty($_POST['address_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['address_id'])) : "";
    $passed_pincode_id  = (isset($_POST['pincode_id']) && !empty($_POST['pincode_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['pincode_id'])) : "";
    if ($address_id != "") {
        $pincodes = $fn->get_data($column = ['pincode_id'], "id=" . $address_id, "user_addresses");
        if (empty($pincodes)) {
            $response['error'] = true;
            $response['message'] = 'Address not available for delivary check. First set the address.';
            print_r(json_encode($response));
            return false;
        }
        $pincode_id = $pincodes[0]['pincode_id'];
    }
    if ($passed_pincode_id != "") {
        $pincode_id = $passed_pincode_id;
    }
    if (!empty($user_id)) {
        if ($fn->is_item_available_in_user_cart($user_id)) {
            $i = 0;
            $j = 0;
            $x = 0;
            $total_amount = 0;

            $sql = "SELECT count(id) as total from cart where save_for_later = 0 AND user_id=" . $user_id;
            $db->sql($sql);
            $total = $db->getResult();
            $sql = "select * from cart where save_for_later = 0 AND user_id=" . $user_id . " ORDER BY date_created DESC ";
            $db->sql($sql);
            $res = $db->getResult();
            $sql = "select qty,product_variant_id from cart where user_id=" . $user_id;
            $db->sql($sql);
            $res_1 = $db->getResult();
            foreach ($res_1 as $row_1) {
                $sql = "select price,discounted_price from product_variant where id=" . $row_1['product_variant_id'];
                $db->sql($sql);
                $result_1 = $db->getResult();
                foreach ($result_1 as $result_2) {
                    $price = $result_2['discounted_price'] == 0 ? $result_2['price'] * $row_1['qty'] : $result_2['discounted_price'] * $row_1['qty'];
                }
                $total_amount += $price;
            }
            // print_r($res);
            foreach ($res as $row) {
                $sql = "select pv.*,p.name,p.pincodes,p.type as d_type,p.cod_allowed,p.slug,p.image,p.other_images,t.percentage as tax_percentage,t.title as tax_title,pv.measurement,(select short_code from unit u where u.id=pv.measurement_unit_id) as unit from product_variant pv left join products p on p.id=pv.product_id left join taxes t on t.id=p.tax_id  where pv.id=" . $row['product_variant_id'] . " GROUP BY pv.id";
                $db->sql($sql);
                $res[$i]['item'] = $db->getResult();
                // print_r($res[$i]['item']);
                for ($k = 0; $k < count($res[$i]['item']); $k++) {
                    // echo "test";
                    if (!empty($pincode_id)) {
                        $pincodes = ($res[$i]['item'][$k]['d_type'] == "all") ? "" : $res[$i]['item'][$k]['pincodes'];
                        // print_r($pincodes);
                        $pincodes = explode(',',$pincodes);
                        if ($res[$i]['item'][$k]['d_type'] == "all") {
                            $res[$i]['item'][$k]['is_item_deliverable'] = true;
                        } else if ($res[$i]['item'][$k]['d_type'] == "included") {
                            if (in_array($pincode_id,$pincodes)) {
                                $res[$i]['item'][$k]['is_item_deliverable']  = true;
                            } else {
                                $res[$i]['item'][$k]['is_item_deliverable']  = false;
                            }
                        } else if ($res[$i]['item'][$k]['d_type'] == "excluded") {

                            if (in_array($pincode_id,$pincodes)) {
                                $res[$i]['item'][$k]['is_item_deliverable']  = false;
                            } else {
                                $res[$i]['item'][$k]['is_item_deliverable']  = true;
                            }
                        }
                    } else {
                        $res[$i]['item'][$k]['is_item_deliverable'] = false;
                    }

                    $res[$i]['item'][$k]['other_images'] = json_decode($res[$i]['item'][$k]['other_images']);
                    $res[$i]['item'][$k]['other_images'] = empty($res[$i]['item'][$k]['other_images']) ? array() : $res[$i]['item'][$k]['other_images'];
                    $res[$i]['item'][$k]['tax_percentage'] = empty($res[$i]['item'][$k]['tax_percentage']) ? "0" : $res[$i]['item'][$k]['tax_percentage'];
                    // $res[$i]['item'][$k]['is_cod_allowed'] = empty($res[$i]['item'][$k]['is_cod_allowed']) ? "0" : $res[$i]['item'][$k]['is_cod_allowed'];
                    $res[$i]['item'][$k]['tax_title'] = empty($res[$i]['item'][$k]['tax_title']) ? "" : $res[$i]['item'][$k]['tax_title'];
                    if ($res[$i]['item'][$k]['stock'] <= 0 || $res[$i]['item'][$k]['serve_for'] == 'Sold Out') {
                        $res[$i]['item'][$k]['isAvailable'] = false;
                        $ready_to_add = true;
                    } else {
                        $res[$i]['item'][$k]['isAvailable'] = true;
                    }
                    for ($l = 0; $l < count($res[$i]['item'][$k]['other_images']); $l++) {
                        $other_images = DOMAIN_URL . $res[$i]['item'][$k]['other_images'][$l];
                        $res[$i]['item'][$k]['other_images'][$l] = $other_images;
                    }
                }
                for ($j = 0; $j < count($res[$i]['item']); $j++) {
                    $res[$i]['item'][$j]['image'] = !empty($res[$i]['item'][$j]['image']) ? DOMAIN_URL . $res[$i]['item'][$j]['image'] : "";
                    $res[$i]['item'][$j]['size_chart'] = !empty($res[$i]['item'][$j]['size_chart']) ? DOMAIN_URL . $res[$i]['item'][$j]['size_chart'] : "";
                }
                $i++;
            }

            $sql = "select * from cart where save_for_later = 1 AND user_id=" . $user_id . " ORDER BY date_created DESC ";
            $db->sql($sql);
            $result = $db->getResult();

            $sql = "select qty,product_variant_id from cart where save_for_later = 1 AND user_id=" . $user_id;
            $db->sql($sql);
            $res1 = $db->getResult();

            foreach ($res1 as $row1) {
                $sql = "select price,discounted_price from product_variant where id=" . $row1['product_variant_id'];
                $db->sql($sql);
                $result1 = $db->getResult();
                foreach ($result1 as $result2) {
                    $price = $result2['discounted_price'] == 0 ? $result2['price'] * $row_1['qty'] : $result2['discounted_price'] * $row1['qty'];
                }
                $total_amount += $price;
            }

            foreach ($result as $rows) {
                $sql = "select pv.*,p.name,p.type as d_type,p.cod_allowed,p.slug,p.image,p.other_images,t.percentage as tax_percentage,t.title as tax_title,pv.measurement,(select short_code from unit u where u.id=pv.measurement_unit_id) as unit from product_variant pv left join products p on p.id=pv.product_id left join taxes t on t.id=p.tax_id where pv.id=" . $rows['product_variant_id'] . " GROUP BY pv.id";
                $db->sql($sql);
                $result[$x]['item'] = $db->getResult();

                for ($z = 0; $z < count($result[$x]['item']); $z++) {
                    // if (!empty($pincode_id)) {
                    //     $pincodes = ($res[$i]['item'][$k]['d_type'] == "all") ? "" : $res[$i]['item'][$k]['pincodes'];
                    //     if ($res[$i]['item'][$k]['d_type'] == "all") {
                    //         $res[$i]['item'][$k]['is_item_deliverable'] = false;
                    //     } else if ($res[$i]['item'][$k]['d_type'] == "included") {
                    //         if (strpos($pincodes, $pincode_id) !== false) {
                    //             $res[$i]['item'][$k]['is_item_deliverable']  = false;
                    //         } else {
                    //             $res[$i]['item'][$k]['is_item_deliverable']  = true;
                    //         }
                    //     } else if ($res[$i]['item'][$k]['d_type'] == "excluded") {

                    //         if (strpos($pincodes, $pincode_id) !== false) {
                    //             $res[$i]['item'][$k]['is_item_deliverable']  = true;
                    //         } else {
                    //             $res[$i]['item'][$k]['is_item_deliverable']  = false;
                    //         }
                    //     }
                    // } else {
                    //     $res[$i]['item'][$k]['is_item_deliverable'] = false;
                    // }
                    $result[$x]['item'][$z]['is_item_deliverable'] = '';
                    $result[$x]['item'][$z]['other_images'] = json_decode($result[$x]['item'][$z]['other_images']);
                    $result[$x]['item'][$z]['other_images'] = empty($result[$x]['item'][$z]['other_images']) ? array() : $result[$x]['item'][$z]['other_images'];
                    $result[$x]['item'][$z]['tax_percentage'] = empty($result[$x]['item'][$z]['tax_percentage']) ? "0" : $result[$x]['item'][$z]['tax_percentage'];
                    // $result[$x]['item'][$z]['is_cod_allowed'] = empty($result[$x]['item'][$z]['cod_allowed']) ? "0" : $result[$x]['item'][$z]['cod_allowed'];
                    $result[$x]['item'][$z]['tax_title'] = empty($result[$x]['item'][$z]['tax_title']) ? "" : $result[$x]['item'][$z]['tax_title'];

                    if ($result[$x]['item'][$z]['stock'] <= 0 || $result[$x]['item'][$z]['serve_for'] == 'Sold Out') {
                        $result[$x]['item'][$z]['isAvailable'] = false;
                        $ready_to_add = true;
                    } else {
                        $result[$x]['item'][$z]['isAvailable'] = true;
                    }

                    for ($y = 0; $y < count($result[$x]['item'][$z]['other_images']); $y++) {
                        $other_images = DOMAIN_URL . $result[$x]['item'][$z]['other_images'][$y];
                        $result[$x]['item'][$z]['other_images'][$y] = $other_images;
                    }
                }
                for ($j = 0; $j < count($result[$x]['item']); $j++) {
                    $result[$x]['item'][$j]['image'] = !empty($result[$x]['item'][$j]['image']) ? DOMAIN_URL . $result[$x]['item'][$j]['image'] : "";
                }
                $x++;
            }

            if (!empty($res) || !empty($result)) {
                $response['error'] = false;
                $response['total'] = $total[0]['total'];
                $response['ready_to_cart'] = $ready_to_add;
                $response['total_amount'] = number_format($total_amount, 2, '.', '');
                $response['message'] = 'Cart Data Retrived Successfully!';
                $response['data'] = array_values($res);
                $response['save_for_later'] = array_values($result);
            } else {
                $response['error'] = true;
                $response['message'] = "No item(s) found in users cart!";
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'No item(s) found in user cart!';
        }
    } else {
        $response['error'] = true;
        $response['message'] = 'Please pass all the fields!';
    }
    print_r(json_encode($response));
    return false;
}

/*
5.add_to_save_for_later
    accesskey:90336
    add_to_save_for_later:1
    user_id:3
    product_id:1
    product_variant_id:4
    qty:2
*/
if ((isset($_POST['add_to_save_for_later'])) && ($_POST['add_to_save_for_later'] == 1)) {
    $user_id = (isset($_POST['user_id']) && !empty($_POST['user_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['user_id'])) : "";
    $product_id = (isset($_POST['product_id']) && !empty($_POST['product_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['product_id'])) : "";
    $product_variant_id  = (isset($_POST['product_variant_id']) && !empty($_POST['product_variant_id'])) ? $db->escapeString($fn->xss_clean_array($_POST['product_variant_id'])) : "";
    $qty = (isset($_POST['qty']) && !empty($_POST['qty'])) ? $db->escapeString($fn->xss_clean_array($_POST['qty'])) : "";
    if (!empty($user_id) && !empty($product_id)) {
        if (!empty($product_variant_id)) {
            if ($fn->is_item_available($product_id, $product_variant_id)) {
                if ($fn->is_item_available_in_user_cart($user_id, $product_variant_id)) {
                    /* if item found in user's cart update it */
                    if (empty($qty) || $qty == 0) {
                        $sql = "DELETE FROM cart WHERE user_id = $user_id AND product_variant_id = $product_variant_id";
                        if ($db->sql($sql)) {
                            $response['error'] = false;
                            $response['message'] = 'Item removed users cart due to 0 quantity';
                        } else {
                            $response['error'] = true;
                            $response['message'] = 'Something went wrong please try again!';
                        }
                        print_r(json_encode($response));
                        return false;
                    }
                    $data = array(
                        'qty' => $qty,
                        'save_for_later' => 1
                    );
                    if ($db->update('cart', $data, 'user_id=' . $user_id . ' AND product_variant_id=' . $product_variant_id)) {
                        $response['error'] = false;
                        $response['message'] = 'Item added to save for later successfully';
                    } else {
                        $response['error'] = true;
                        $response['message'] = 'Something went wrong please try again!';
                    }
                } else {

                    /* if item not found in user's cart add it */
                    $data = array(
                        'user_id' => $user_id,
                        'product_id' => $product_id,
                        'product_variant_id' => $product_variant_id,
                        'qty' => $qty,
                        'save_for_later' => 1
                    );
                    if ($db->insert('cart', $data)) {
                        $response['error'] = false;
                        $response['message'] = 'Item added to save for later successfully';
                    } else {
                        $response['error'] = true;
                        $response['message'] = 'Something went wrong please try again!';
                    }
                }
            } else {
                $response['error'] = true;
                $response['message'] = 'No such item available!';
            }
        } else {
            $response['error'] = true;
            $response['message'] = 'Please choose atleast one item!';
        }
    } else {
        $response['error'] = true;
        $response['message'] = 'Please pass all the fields!';
    }

    print_r(json_encode($response));
    return false;
}
