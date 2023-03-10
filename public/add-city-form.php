<?php
include_once('includes/functions.php');
include_once('includes/custom-functions.php');
$fn = new custom_functions;
?>
<?php
$sql_query = "SELECT id, pincode FROM pincodes ORDER BY id ASC";
$db->sql($sql_query);
$res_city = $db->getResult();
if (isset($_POST['btnAdd'])) {
	if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
		echo '<label class="alert alert-danger">This operation is not allowed in demo panel!.</label>';
		return false;
	}
	if ($permissions['locations']['create'] == 1) {
		$city_name = $db->escapeString($fn->xss_clean($_POST['city_name']));
		$pincode_ID = $db->escapeString($fn->xss_clean($_POST['pincode_ID']));
		$delivery_charges = $db->escapeString($fn->xss_clean($_POST['delivery_charges']));
		$minimum_free_delivery_order_amount = $db->escapeString($fn->xss_clean($_POST['minimum_free_delivery_order_amount']));

		// create array variable to handle error
		$error = array();

		if (empty($city_name)) {
			$error['city_name'] = " <span class='label label-danger'>Required!</span>";
		}
		if (empty($pincode_ID)) {
			$error['pincode_ID'] = " <span class='label label-danger'>Required!</span>";
		}

		// if ($delivery_charges == 0) {
		// 	$error['delivery_charges'] = " <span class='label label-danger'>Not null</span>";
		// }
		// if ($minimum_free_delivery_order_amount == 0) {
		// 	$error['$minimum_free_delivery_order_amount'] = " <span class='label label-danger'>Not null</span>";
		// }

		$sql_query = "SELECT * FROM pincodes WHERE id=" . $pincode_ID;
		$db->sql($sql_query);
		$res_pincodes = $db->getResult();
		$TOTAL = $db->numRows($res_pincodes);

		if ($TOTAL != 0) {
			if (!empty($city_name) && !empty($pincode_ID) && !empty($delivery_charges) && !empty($minimum_free_delivery_order_amount)) {
				$sql_query = "INSERT INTO cities (name, pincode_id,delivery_charges,minimum_free_delivery_order_amount) VALUES('$city_name', '$pincode_ID','$delivery_charges','$minimum_free_delivery_order_amount')";
				$db->sql($sql_query);
				$result = $db->getResult();
				if (!empty($result)) {
					$result = 0;
				} else {
					$result = 1;
				}

				if ($result == 1) {
					$error['add_city'] = "<section class='content-header'>
												<span class='label label-success'>City Added Successfully</span>
												<h4><small><a  href='city.php'><i class='fa fa-angle-double-left'></i>&nbsp;&nbsp;&nbsp;Back to Cities</a></small></h4>
												</section>";
				} else {
					$error['add_city'] = " <span class='label label-danger'>Failed add city</span>";
				}
			}
		} else {
			$error['add_city'] = "<section class='content-header'><span class='label label-danger'>Pincode not found</span></section>";
		}
	} else {
		$error['add_city'] = "<section class='content-header'><span class='label label-danger'>You have no permission to create city</span></section>";
	}
}

if (isset($_POST['btnCancel'])) {
	header("location:city-table.php");
}

?>
<section class="content-header">
	<h1>Add city</h1>
	<?php echo isset($error['add_city']) ? $error['add_city'] : ''; ?>
	<ol class="breadcrumb">
		<li><a href="home.php"><i class="fa fa-home"></i> Home</a></li>
	</ol>
	<hr />
</section>
<section class="content">
	<div class="row">
		<div class="col-md-6">
			<?php if ($permissions['locations']['create'] == 0) { ?>
				<div class="alert alert-danger">You have no permission to create city</div>
			<?php } ?>
			<!-- general form elements -->
			<div class="box box-primary">
				<div class="box-header with-border">
					<h3 class="box-title">Add City</h3>
				</div><!-- /.box-header -->
				<!-- form start -->
				<form method="post" id="city_form" enctype="multipart/form-data">
					<div class="box-body">
						<div class="form-group">
							<label for="exampleInputEmail1">City Name</label><?php echo isset($error['city_name']) ? $error['city_name'] : ''; ?>
							<input type="text" class="form-control" name="city_name">
						</div>
						<!-- <div class="form-group">
							<label for="exampleInputEmail1">pincode_ID</label><?php echo isset($error['pincode_ID']) ? $error['pincode_ID'] : ''; ?>
							<input type="text" class="form-control" name="pincode_ID">
						</div> -->
						<select name="pincode_ID" id="pincode_ID" class="form-control" required>
							<option value="">Select Your pincode_ID</option>
							<?php
							if ($permissions['locations']['read'] == 1) {
								foreach ($res_city as $row) { ?>
									<option value="<?php echo $row['id']; ?>"> <?php echo $row['pincode']; ?></option>
							<?php }
							} ?>
						</select>
						<div class="form-group">
							<label for="exampleInputEmail1">Delivery Charges</label><?php echo isset($error['delivery_charges']) ? $error['delivery_charges'] : ''; ?>
							<input type="number" step="any" min="0" class="form-control" name="delivery_charges" id="delivery_charges" />
						</div>
						<div class="form-group">
							<label for="exampleInputEmail1">Minimum Free Delivery Order Amount</label><?php echo isset($error['minimum_free_delivery_order_amount']) ? $error['minimum_free_delivery_order_amount'] : ''; ?>
							<input type="number" step="any" min="0" class="form-control" name="minimum_free_delivery_order_amount" id="minimum_free_delivery_order_amount" required />
						</div>
					</div><!-- /.box-body -->

					<div class="box-footer">
						<button type="submit" class="btn btn-primary" name="btnAdd">Add</button>
						<input type="reset" class="btn-warning btn" value="Clear" />
					</div>
				</form>
			</div><!-- /.box -->
		</div>
	</div>
</section>

<div class="separator"> </div>

<?php $db->disconnect(); ?>
<script>
	$('#city_form').validate({
		debug: false,
		rules: {
			city_name: "required",
			pincode_ID: "required",
			minimum_free_delivery_order_amount: {
				required: "true",
				number: "true"
			},
			delivery_charges: {
				required: "true",
				number: "true",
			},
		}
	});
</script>