<?php
include_once('includes/variables.php');
include_once('includes/crud.php');
include_once('includes/custom-functions.php');
$function = new custom_functions();
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $ID = $db->escapeString($function->xss_clean($_GET['id']));
} else { ?>
    <script>
        alert("Something went wrong, No data available.");
        window.location.href = "orders.php";
    </script>
<?php
}
if (!isset($_SESSION['seller_id']) && !isset($_SESSION['seller_name'])) {
    header("location:index.php");
} else {
    $seller_id = $_SESSION['seller_id'];
}
$currency = $function->get_settings('currency');

// create array variable to handle error
$update_order_permission = $permissions['orders']['update'];
$allowed = ALLOW_MODIFICATION;
$seller_name = "";

$error = array();
?>
<section class="content-header">
    <h1>Order Detail</h1>
    <?php echo isset($error['update_data']) ? $error['update_data'] : ''; ?>
    <ol class="breadcrumb">
        <li><a href="home.php"><i class="fa fa-home"></i> Home</a></li>
    </ol>
</section>
<?php
// echo $sql = "SELECT oi.*,o.final_total as payable_total,oi.id as order_item_id,p.*,v.product_id, v.measurement,o.*,o.total as order_total,o.wallet_balance,oi.active_status as oi_active_status,u.email,u.name as uname,u.country_code,p.name as pname,(SELECT short_code FROM unit un where un.id=v.measurement_unit_id)as mesurement_unit_name 
//         FROM `order_items` oi
//         JOIN users u ON u.id=oi.user_id
//         JOIN product_variant v ON oi.product_variant_id=v.id
//         JOIN products p ON p.id=v.product_id
//         JOIN orders o ON o.id=oi.order_id
//     WHERE o.id=" . $ID;
$sql = "SELECT oi.*,o.final_total as payable_total,oi.id as order_item_id,v.product_id,v.measurement_unit_id,p.cancelable_status,o.*,o.total as order_total,o.wallet_balance,oi.active_status as oi_active_status,u.email,u.name as uname,u.country_code FROM `order_items` oi JOIN users u ON u.id=oi.user_id LEFT JOIN product_variant v ON oi.product_variant_id=v.id LEFT JOIN products p ON p.id=v.product_id JOIN orders o ON o.id=oi.order_id WHERE o.id=$ID and oi.seller_id=" . $seller_id;
$db->sql($sql);
$res = $db->getResult();
$items = [];
if (isset($res[0]) && !empty($res[0])) {
    foreach ($res as $row) {
        $data = array($row['product_id'], $row['product_variant_id'], $row['product_name'], $row['variant_name'], $row['measurement_unit_id'], $row['quantity'], $row['discounted_price'], $row['price'], $row['oi_active_status'], $row['cancelable_status'], $row['order_item_id'], $row['sub_total'], $row['tax_amount'], $row['tax_percentage'], $row['seller_id'], $row['delivery_boy_id'], $row['user_id']);
        array_push($items, $data);
    }
?>
    <style>
        @media (min-width: 992px) {
            .col-md-3 {
                width: 20% !important;
            }
        }
    </style>

    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Order Detail</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <table class="table table-bordered">
                            <tr>
                                <input type="hidden" name="hidden" id="order_id" value="<?php echo $res[0]['id']; ?>">
                                <th style="width: 10px">ID</th>
                                <td><?php echo $res[0]['id']; ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Name</th>
                                <td><?php echo $res[0]['uname']; ?></td>
                            </tr>
                            <?php
                            $str_to_replace = '*******';
                            ?>
                            <th style="width: 10px">Email</th>
                            <td><?= defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 || !($function->get_seller_permission($_SESSION['seller_id'], 'customer_privacy')) ? $str_to_replace . substr($res[0]['email'], 7) : $res[0]['email']; ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Contact</th>
                                <td><?= defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 || !($function->get_seller_permission($_SESSION['seller_id'], 'customer_privacy')) ? $str_to_replace . substr($res[0]['mobile'], 7) : $res[0]['mobile']; ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">O. Note</th>
                                <td><?php echo $res[0]['order_note']; ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Area</th>
                                <?php
                                if (!empty($res[0]['area_id'])) {
                                    $area_id = $res[0]['area_id'];
                                    $sql = "SELECT * FROM `area` WHERE id =$area_id";
                                    $db->sql($sql);
                                    $res_areas = $db->getResult();
                                } else {
                                    $res_areas = array();
                                }
                                ?>
                                <td><?= (!empty($res_areas)) ? $res_areas[0]['name'] : "" ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Pincode</th>
                                <?php
                                $pincode_id = $res[0]['pincode_id'];
                                $sql = "SELECT * FROM `pincodes` WHERE id =$pincode_id";
                                $db->sql($sql);
                                $res_pincodes = $db->getResult();
                                ?>
                                <td><?= (!empty($res_pincodes)) ? $res_pincodes[0]['pincode'] : "" ?></td>
                            </tr>
                            <?php
                            if ($function->get_seller_permission($seller_id, 'view_order_otp')) {
                            ?>
                                <tr>
                                    <th style="width: 10px">OTP</th>
                                    <td><?= (isset($res[0]['otp']) && !empty($res[0]['otp'])) ? $res[0]['otp'] : "-" ?></td>
                                </tr>
                            <?php } ?>
                            <?php
                            // $sql = "SELECT id,name FROM delivery_boys WHERE status=1";
                            $sql = "SELECT id,name,pincode_id,is_available FROM delivery_boys WHERE status=1 and FIND_IN_SET($pincode_id, pincode_id) ";
                            $db->sql($sql);
                            $result = $db->getResult();
                            ?>
                            <tr>
                                <th>Items</th>
                                <td>
                                    <form id="update_form">
                                        <input type="hidden" name="update_order_items" value="1">
                                        <input type="hidden" name="accesskey" value="90336">
                                        <div class="container-fluid">

                                            <div class="row">
                                                <div class="col-md-12  mb-5">
                                                    <lable class="badge badge-primary">Select status, delivery boy and square box of item which you want to update</lable>
                                                </div>
                                                <div class="col-md-12  mb-5">
                                                    <div id="save_result"></div>
                                                </div>
                                                <div class="col-md-4">
                                                    <select name="status" class="form-control status">
                                                        <option value=''>Select Status</option>
                                                        <option value="awaiting_payment">Awaiting Payment</option>
                                                        <option value="received">Received</option>
                                                        <option value="processed">Processed</option>
                                                        <option value="shipped">Shipped</option>
                                                        <option value="delivered">Delivered</option>
                                                        <option value="cancelled">Cancel</option>
                                                        <option value="returned">Returned</option>
                                                    </select>
                                                </div>
                                                <?php
                                                if ($function->get_seller_permission($seller_id, 'assign_delivery_boy')) { ?>
                                                    <div class="col-md-4">
                                                        <select name='delivery_boy_id' class='form-control deliver_by' required>
                                                            <option value=''>Select Delivery Boy</option>
                                                            <?php if (isset($result[0]) && !empty($result[0])) {
                                                                foreach ($result as $row1) {
                                                                    $pending_orders = $function->rows_count('order_items', 'distinct(order_id)', 'delivery_boy_id=' . $row1['id'] . ' and active_status != "cancelled" and active_status != "returned" and active_status != "delivered"');
                                                                    $disabled = $row1['is_available'] == 0 ? 'disabled' : '';
                                                                    if ($item[15] == $row1['id']) { ?>
                                                                        <option value='<?= $row1['id'] ?>'><?= $row1['name'] . ' - ' .  $pending_orders ?> - Pending Orders</option>
                                                                    <?php } else { ?>
                                                                        <option value='<?= $row1['id'] ?>' <?= $disabled ?>><?= $row1['name'] . ' - ' .  $pending_orders ?> - Pending Orders</option>
                                                            <?php }
                                                                }
                                                            } ?>
                                                        </select>
                                                    </div>
                                                <?php } ?>
                                                <div class="col-md-4">
                                                    <a href="#" title='update' id="submit_btn" class="btn btn-primary col-sm-12 col-md-12 update_order_items">Bulk Update</a>
                                                </div>
                                            </div>

                                            <?php $total = 0;
                                            foreach ($items as $item) {
                                            ?>
                                                <div class="card col-md-3">
                                                    <div class="card-body">
                                                        <?php if ($item[8] == 'received') {
                                                            $active_status = '<label class="label label-primary">Received</label>';
                                                        }
                                                        if ($item[8] == 'processed') {
                                                            $active_status = '<label class="label label-info">Processed</label>';
                                                        }
                                                        if ($item[8] == 'shipped') {
                                                            $active_status = '<label class="label label-warning">Shipped</label>';
                                                        }
                                                        if ($item[8] == 'delivered') {
                                                            $active_status = '<label class="label label-success">Delivered</label>';
                                                        }
                                                        if ($item[8] == 'returned') {
                                                            $active_status = '<label class="label label-danger">Returned</label>';
                                                        }
                                                        if ($item[8] == 'cancelled') {
                                                            $active_status = '<label class="label label-danger">Cancelled</label>';
                                                        }
                                                        if ($item[8] == 'awaiting_payment') {
                                                            $active_status = '<label class="label label-secondary">Awaiting Payment</label>';
                                                        }
                                                        $array[] = $item[8];
                                                        if (!empty($item[14])) {
                                                            $s_id = $item[14];
                                                            $db->sql("SET NAMES 'utf8'");
                                                            $sql = "SELECT `name` FROM `seller` where id= $s_id ORDER BY id DESC";
                                                            $db->sql($sql);
                                                            $seller_name = $db->getResult();
                                                            $seller_name = (!empty($seller_name)) ? $seller_name[0]['name'] : "Not Assigned";
                                                            $sql = "SELECT `name` FROM `delivery_boys` where id= " . $item[15] . "";
                                                            $db->sql($sql);
                                                            $delivery_boy_name = $db->getResult();
                                                            $delivery_boy_name = isset($delivery_boy_name[0]['name']) && (!empty($delivery_boy_name[0]['name'])) ?  $delivery_boy_name[0]['name'] : "Not assigned";
                                                            $sql = "SELECT `name` FROM `users` where id= " . $item[16] . "";
                                                            $db->sql($sql);
                                                            $user_name = $db->getResult();
                                                            $user_name = isset($user_name[0]['name']) && (!empty($user_name[0]['name'])) ?  $user_name[0]['name'] : "";
                                                        }
                                                        $view_product = "";
                                                        $is_product = $function->get_data($column = ['id'], 'id=' . $item[1], 'product_variant');
                                                        $is_product = isset($is_product[0]) && !empty($is_product[0]) ? 1 : 0;
                                                        if ($is_product == 1) {
                                                            $view_product = " <a href='" . DOMAIN_URL . "view-product-variants.php?id=" . $item[0] . "' class='btn btn-success btn-xs' title='View Product'><i class='fa fa-eye'></i></a>";
                                                        }
                                                        // echo $view_product;
                                                        $total += $subtotal = ($item[6] != 0 && $item[6] < $item[7]) ? ($item[6] * $item[5]) : ($item[7] * $item[5]);
                                                        echo "<br><input type='checkbox' name='order_items[]' value=" . $item[10] . ">" . "</br>";
                                                        echo  "</br>" . $active_status . "<br><br><b>Order Item Id : </b>" . $item[10] . "<br><b>D.boy : </b>" . $delivery_boy_name . "</br><b>Product Id : </b>" . $item[0] . $view_product . "</br>";
                                                        if (!empty($seller_name)) {
                                                            echo " <b>Seller : </b>" . $seller_name . "</br>";
                                                        }
                                                        if (!empty($user_name)) {
                                                            echo " <b>User Name : </b>" . $user_name . "</br>";
                                                        }
                                                        echo "<b>Variant Id : </b>" . $item[1] . "</br>";
                                                        echo " <b>Name : </b>" . $item[2] . "(" . $item[3] . ")</br>";
                                                        echo " <b>Quantity : </b>" . $item[5] . "</br>";
                                                        echo " <b>Price(" . $currency . ") : </b>" . $item[7] . "</br>";
                                                        echo " <b>Discounted Price(" . $currency . ") : </b>" . $item[6] . "</br>";
                                                        echo " <b>Tax Amount(" . $currency . ") : </b>" . $item[12] . "</br>";
                                                        echo " <b>Tax Percentage(%) : </b>" . $item[13] . "</br>";
                                                        echo " <b>Subtotal(" . $currency . ") : </b>" . $item[11] . "  ";
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php } ?><br>
                                            <?php if (count($items) > 6) { ?>
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="col-md-4">
                                                            <select name="status_bottom" class="form-control status">
                                                                <option value=''>Select Status</option>
                                                                <option value="awaiting_payment">Awaiting Payment</option>
                                                                <option value="received">Received</option>
                                                                <option value="processed">Processed</option>
                                                                <option value="shipped">Shipped</option>
                                                                <option value="delivered">Delivered</option>
                                                                <option value="cancelled">Cancel</option>
                                                                <option value="returned">Returned</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <select name='delivery_boy_id_bottom' class='form-control deliver_by' required>
                                                                <option value=''>Select Delivery Boy</option>
                                                                <?php foreach ($result as $row1) {
                                                                    $pending_orders = $function->rows_count('order_items', 'distinct(order_id)', 'delivery_boy_id=' . $row1['id'] . ' and active_status != "cancelled" and active_status != "returned"');
                                                                    $disabled = $row1['is_available'] == 0 ? 'disabled' : '';
                                                                    if ($item[15] == $row1['id']) { ?>
                                                                        <option value='<?= $row1['id'] ?>'><?= $row1['name'] . ' - ' .  $pending_orders ?> - Pending Orders</option>
                                                                    <?php } else { ?>
                                                                        <option value='<?= $row1['id'] ?>' <?= $disabled ?>><?= $row1['name'] . ' - ' .  $pending_orders ?> - Pending Orders</option>
                                                                <?php }
                                                                } ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <a href="#" title='update' id="submit_btn_bottom" class="btn btn-primary col-sm-12 col-md-12 update_order_items_bottom">Bulk Update</a>
                                                        </div>
                                                    </div>

                                                </div>
                                            <?php } ?>

                                            <!-- <div class="col-md-4"> -->

                                            <div class="mt-5" id="save_result_bottom"></div>
                                            <!-- </div> -->
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Total (<?= $settings['currency'] ?>)</th>
                                <td><?php echo $total; ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">D.Charge (<?= $settings['currency'] ?>)</th>
                                <td><?php echo $res[0]['delivery_charge']; ?></td>

                            </tr>

                            <?php if ($res[0]['discount'] > 0) {
                                $discounted_amount = $res[0]['total'] * $res[0]['discount'] / 100; /*  */
                                $final_total = $res[0]['total'] - $discounted_amount;
                                $discount_in_rupees = $res[0]['total'] - $final_total;
                                $discount_in_rupees = $discount_in_rupees;
                            } else {
                                $discount_in_rupees = 0;
                            } ?>
                            <tr>
                                <th style="width: 10px">Disc. <?= $settings['currency'] ?>(%)</th>
                                <td><?php echo  $discount_in_rupees . '(' . round($res[0]['discount'], 2) . '%)'; ?></td>
                            </tr>

                            <tr>
                                <th style="width: 10px">Promo Disc. (<?= $settings['currency'] ?>)</th>
                                <td><?php echo $res[0]['promo_discount']; ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Wallet Used</th>
                                <td><?php echo $res[0]['wallet_balance']; ?></td>
                            </tr>
                            <input type="hidden" name="total_amount" id="total_amount" value="<?php echo $res[0]['payable_total']; ?>">
                            <?php $final_total = $total + $res[0]['delivery_charge'] - $discount_in_rupees - $res[0]['promo_discount'] - $res[0]['wallet_balance']; ?>
                            <tr>
                                <th style="width: 10px">Payable Total(<?= $settings['currency'] ?>)</th>
                                <td><input type="number" class="form-control" id="final_total" name="final_total" value="<?= $final_total ?>" disabled></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Payment Method</th>
                                <td><?php echo $res[0]['payment_method']; ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Promo Code</th>
                                <td><?= (!empty($res[0]['promo_code']) || $res[0]['promo_code'] != null) ? $res[0]['promo_code'] : ""; ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Address</th>
                                <td><?php echo $res[0]['address']; ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Order Date</th>
                                <td><?php echo date('d-m-Y h:i:s A', strtotime($row['date_added'])); ?></td>
                            </tr>
                            <tr>
                                <th style="width: 10px">Delivery Time</th>
                                <td><?php echo $res[0]['delivery_time']; ?></td>
                            </tr>
                        </table>
                        <div class="box-footer clearfix">
                            <?php
                            $check_array = array("awaiting_payment", "cancelled", "returned");
                            $result1 = array_diff($array, $check_array);
                            if (!empty($result1)) { ?>
                                <button class="btn btn-primary pull-right" onclick="myfunction()"><i class="fa fa-download"></i>Generate Invoice</button>
                            <?php } else { ?>
                                <button class="btn btn-primary disabled pull-right"><i class="fa fa-download"></i> Generate Invoice</button>
                            <?php } ?>
                        </div>
                    </div>



                </div>
                <!-- /.box -->
            </div>

        </div>
    </section>
<?php } else { ?>
    <div class="alert alert-danger">Something went wrong</div>
<?php } ?>
<script>
    var allowed = '<?= $allowed; ?>';
    var delivery_by = "";
    $(".deliver_by").change(function(e) {
        delivery_by = $(this).val();
    });
    var status = "";
    $(".status").change(function(e) {
        status = $(this).val();
    });
    $(document).on('click', '.update_order_item_status', function(e) {
        e.preventDefault();
        if (allowed == 0) {
            alert('Sorry! This operation is not allowed in demo panel!.');
            window.location.reload();
            return false;
        }
        var update_permission = '<?= $update_order_permission; ?>';
        if (update_permission == 0) {
            alert('Sorry! you have no permission to update orders.');
            window.location.reload();
            return false;
        }
        var status1 = status;
        var id = $('#order_id').val();
        var item_id = $(this).data('value1');
        var delivery_by1 = delivery_by;
        // alert("STATUS : " + status1 + " DELIVER: " + delivery_by + " ITEM ID: " + item_id);
        var dataString = 'update_order_status=1&order_id=' + id + '&status=' + status1 + '&order_item_id=' + item_id + '&delivery_boy_id=' + delivery_by + '&ajaxCall=1';
        if (confirm("Are you sure? you want to change the order item status")) {
            $.ajax({
                url: "../api-firebase/order-process.php",
                type: "POST",
                data: dataString,
                dataType: "json",
                beforeSend: function() {
                    $('#submit_btn').html('Please wait...').attr('disabled', true);
                },
                success: function(data) {
                    if (data.error == true) {
                        alert(data.message);
                        location.reload(true);
                    } else {
                        alert(data.message);
                        location.reload(true);
                    }
                    $('#status option:selected').attr('disabled', false);
                }
            });
        }
    });

    function myfunction() {
        window.location.href = 'invoice.php?id=<?php echo $res[0]['id']; ?>';
    }
</script>

<script>
    $('.update_order_items').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure? want to update.')) {
            var data = $('#update_form').serialize();
            $.ajax({
                type: 'POST',
                url: "../api-firebase/order-process.php",
                data: data,
                beforeSend: function() {
                    $('#submit_btn').html('Please wait..').attr('disabled', true);
                },
                cache: false,
                processData: false,
                dataType: "json",
                success: function(result) {
                    $('#save_result').html(result.message);
                    $('#save_result').show().delay(3000).fadeOut();
                    $('#submit_btn').html('Bulk Update').attr('disabled', false);
                    if (result.error == false) {
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    }
                }
            });
        }

    });
</script>
<script>
    $('.update_order_items_bottom').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure? want to update.')) {
            var data = $('#update_form').serialize();
            $.ajax({
                type: 'POST',
                url: 'api-firebase/order-process.php',
                data: data,
                beforeSend: function() {
                    $('#submit_btn_bottom').html('Please wait..').attr('disabled', true);
                },
                cache: false,
                processData: false,
                dataType: "json",
                success: function(result) {
                    $('#save_result_bottom').html(result.message);
                    $('#save_result_bottom').show().delay(3000).fadeOut();
                    $('#submit_btn_bottom').html('Bulk Update').attr('disabled', false);
                    if (result.error == false) {
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    }
                }
            });
        }


    });
</script>
<?php $db->disconnect(); ?>