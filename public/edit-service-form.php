<?php
include_once('includes/functions.php');
$function = new functions;
include_once('includes/custom-functions.php');
$fn = new custom_functions;
?>
<?php
if (isset($_GET['id'])) {
	$ID = $db->escapeString($fn->xss_clean($_GET['id']));
} else {
	return false;
	exit(0);
}
$category_data = array();

$sql_query = "SELECT image FROM services WHERE id =" . $ID;
$db->sql($sql_query);
$res = $db->getResult();
if (isset($_POST['btnEdit'])) {
	if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
		echo '<label class="alert alert-danger">This operation is not allowed in demo panel!.</label>';
		return false;
	}
	if ($permissions['services']['update'] == 1) {

		$name = $db->escapeString($fn->xss_clean($_POST['name']));
		$description = $db->escapeString($fn->xss_clean($_POST['description']));

		$menu_image = $db->escapeString($fn->xss_clean($_FILES['image']['name']));
		$image_error = $db->escapeString($fn->xss_clean($_FILES['image']['error']));
		$image_type = $db->escapeString($fn->xss_clean($_FILES['image']['type']));
		$is_available_allowed = (isset($_POST['is_available_allowed']) && $_POST['is_available_allowed'] != '') ? 1 : 0;
		$error = array();

		if (empty($name)) {
			$error['name'] = " <span class='label label-danger'>Required!</span>";
		}

		// get image file extension
		error_reporting(E_ERROR | E_PARSE);
		$extension = end(explode(".", $_FILES["image"]["name"]));

		if (!empty($menu_image)) {
			$result = $fn->validate_image($_FILES["image"]);
			if (!$result) {
				$error['image'] = " <span class='label label-danger'>Image type must jpg, jpeg, gif, or png!</span>";
			}
		}

		if (!empty($name)) {

			if (!empty($menu_image)) {

				$string = '0123456789';
				$file = preg_replace("/\s+/", "_", $_FILES['image']['name']);
				$function = new functions;
				$image = $function->get_random_string($string, 4) . "-" . date("Y-m-d") . "." . $extension;

				// delete previous image
				$delete = unlink($res[0]['image']);

				// upload new image
				$upload = move_uploaded_file($_FILES['image']['tmp_name'], 'upload/service/' . $image);
				$upload_image = 'upload/service/' . $image;
				$sql_query = "UPDATE services SET name = ' $name',  description = '$description',image = '$upload_image', is_available='$is_available_allowed' WHERE id =  $ID";
				if ($db->sql($sql_query)) {
					$db->sql($sql_query);
					$update_result = $db->getResult();
				}
			} else {
				$sql_query = "UPDATE services SET name = '" . $name . "',  description = '" . $description . "', image = '" . $res[0]['image'] . "',  is_available = '" . $is_available_allowed . "' WHERE id =" . $ID;
				$db->sql($sql_query);
				$update_result = $db->getResult();
			}

			if (!empty($update_result)) {
				$update_result = 0;
			} else {
				$update_result = 1;
			}

			// check update result
			if ($update_result == 1) {
				$error['update_services'] = " <section class='content-header'><span class='label label-success'>Services updated Successfully</span></section>";
			} else {
				$error['update_services'] = " <span class='label label-danger'>Failed update Services</span>";
			}
		}
	} else {
		$error['check_permission'] = " <section class='content-header'><span class='label label-danger'>You have no permission to update Services</span></section>";
	}
}

// create array variable to store previous data
$data = array();

$sql_query = "SELECT * FROM services WHERE id =" . $ID;
$db->sql($sql_query);
$res = $db->getResult();

if (isset($_POST['btnCancel'])) { ?>
	<script>
		window.location.href = "services.php";
	</script>
<?php } ?>
<section class="content-header">
	<h1>
		Edit Services<small><a href='services.php'><i class='fa fa-angle-double-left'></i>&nbsp;&nbsp;&nbsp;Back to Services</a></small></h1>
	<small><?php echo isset($error['update_services']) ? $error['update_services'] : ''; ?></small>
	<ol class="breadcrumb">
		<li><a href="home.php"><i class="fa fa-home"></i> Home</a></li>
	</ol>
</section>
<section class="content">

	<div class="row">
		<div class="col-md-6">
			<?php if ($permissions['services']['update'] == 0) { ?>
				<div class="alert alert-danger topmargin-sm">You have no permission to update Services.</div>
			<?php } ?>
			<!-- general form elements -->
			<div class="box box-primary">
				<div class="box-header with-border">
					<h3 class="box-title">Edit Services</h3>
				</div><!-- /.box-header -->
				<!-- form start -->
				<form id="edit_service_form" method="post" enctype="multipart/form-data">
					<div class="box-body">
						<div class="form-group">
							<label for="name">Services Name</label><?php echo isset($error['name']) ? $error['name'] : ''; ?>
							<input type="text" class="form-control" id="name" name="name" value="<?php echo $res[0]['name']; ?>">
						</div>
						<div class="form-group">
							<label for="description">Services description</label><?php echo isset($error['description']) ? $error['description'] : ''; ?>
							<input type="text" class="form-control" id="description" name="description" value="<?php echo $res[0]['description']; ?>">
						</div>
						<div class="form-group">
							<label for="image">Image&nbsp;&nbsp;&nbsp;*Please choose square image of larger than 350px*350px & smaller than 550px*550px.</label><?php echo isset($error['image']) ? $error['image'] : ''; ?>
							<input type="file" name="image" id="image" title="Please choose square image of larger than 350px*350px & smaller than 550px*550px." value="<img src='<?php echo $res[0]['image']; ?>'/>">
							<p class="help-block"><img src="<?php echo $res[0]['image']; ?>" width="280" height="190" /></p>
						</div>

						<div class="form-group">
							<label for="">Is available ? :</label><br>
							<input type="checkbox" id="is_available_button" class="js-switch" name="is_available_allowed" <?= isset($res[0]['is_available']) && $res[0]['is_available'] == 1 ? 'checked' : '' ?>>
							<input type="hidden" id="is_available_status" value="<?= isset($res[0]['is_available']) && $res[0]['is_available'] == 1 ? 1 : 0 ?>">
						</div>
					</div><!-- /.box-body -->

					<div class="box-footer">
						<button type="submit" class="btn btn-primary" name="btnEdit">Update</button>
						<button type="submit" class="btn btn-danger" name="btnCancel">Cancel</button>
					</div>
				</form>
			</div><!-- /.box -->
		</div>
	</div>
</section>

<div class="separator"> </div>
<?php $db->disconnect(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.17.0/jquery.validate.min.js"></script>
<script>
	$('#edit_service_form').validate({
		rules: {
			name: "required",
		}
	});
</script>
<script>
</script>