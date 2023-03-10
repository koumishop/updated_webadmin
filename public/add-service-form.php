<?php
include_once('includes/functions.php');
$function = new functions;
include_once('includes/custom-functions.php');
$fn = new custom_functions;
?>
<?php
if (isset($_POST['btnAdd'])) {
	if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
		echo '<label class="alert alert-danger">This operation is not allowed in demo panel!.</label>';
		return false;
	}
	if ($permissions['services']['create'] == 1) {
		$name = $db->escapeString($fn->xss_clean($_POST['name']));
		$description = $db->escapeString($fn->xss_clean($_POST['description']));
		
		$is_available = (isset($_POST['is_available_allowed']) && $_POST['is_available_allowed'] != '') ? 1 : 0;

		// get image info
		$menu_image = $db->escapeString($fn->xss_clean($_FILES['image']['name']));
		$image_error = $db->escapeString($fn->xss_clean($_FILES['image']['error']));
		$image_type = $db->escapeString($fn->xss_clean($_FILES['image']['type']));

		// create array variable to handle error
		$error = array();

		if (empty($name)) {
			$error['name'] = " <span class='label label-danger'>Required!</span>";
		}

		// common image file extensions
		$allowedExts = array("gif", "jpeg", "jpg", "png");

		// get image file extension
		error_reporting(E_ERROR | E_PARSE);
		$extension = end(explode(".", $db->escapeString($fn->xss_clean($_FILES["image"]["name"]))));

		if ($image_error > 0) {
			$error['image'] = " <span class='label label-danger'>Not Uploaded!!</span>";
		} else {
			$result = $fn->validate_image($_FILES["image"]);
			if (!$result) {
				$error['image'] = " <span class='label label-danger'>Image type must jpg, jpeg, gif, or png!</span>";
			}
		}
		if (!empty($name) && empty($error['image']) || !empty($description) || !empty($is_available)) {
			// create random image file name
			$string = '0123456789';
			$file = preg_replace("/\s+/", "_", $db->escapeString($fn->xss_clean($_FILES['image']['name'])));

			$menu_image = $function->get_random_string($string, 4) . "-" . date("Y-m-d") . "." . $extension;

			// upload new image
			$upload = move_uploaded_file($_FILES['image']['tmp_name'], 'upload/service/' . $menu_image);

			// insert new data to menu table
			$upload_image = 'upload/service/' . $menu_image;
			$sql_query = "INSERT INTO services (name, description, image, is_available) VALUES('$name', '$description', '$upload_image', '$is_available')";


			// Execute query
			$db->sql($sql_query);
			// store result 
			$result = $db->getResult();
			if (!empty($result)) {
				$result = 0;
			} else {
				$result = 1;
			}
			if ($result == 1) {
				$error['add_service'] = " <section class='content-header'><span class='label label-success'>Service Added Successfully</span></section>";
			} else {
				$error['add_service'] = " <span class='label label-danger'>Failed add service</span>";
			}
		}
	} else {
		$error['check_permission'] = " <section class='content-header'><span class='label label-danger'>You have no permission to create service</span></section>";
	}
}

?>
<section class="content-header">
	<h1>Add Service<small><a href='services.php'> <i class='fa fa-angle-double-left'></i>&nbsp;&nbsp;&nbsp;Back to Services</a></small></h1>
	<?php echo isset($error['add_service']) ? $error['add_service'] : ''; ?>
	<ol class="breadcrumb">
		<li><a href="home.php"><i class="fa fa-home"></i> Home</a></li>
	</ol>
	<hr />
</section>
<section class="content">
	<div class="row">
		<div class="col-md-6">
			<?php if ($permissions['categories']['create'] == 0) { ?>
				<div class="alert alert-danger">You have no permission to create service.</div>
			<?php } ?>
			<!-- general form elements -->
			<div class="box box-primary">
				<div class="box-header with-border">
					<h3 class="box-title">Add Service</h3>

				</div><!-- /.box-header -->
				<!-- form start -->
				<form method="post" enctype="multipart/form-data">
					<div class="box-body">
						<div class="form-group">
							<label for="service_name">Service Name</label><?php echo isset($error['service_name']) ? $error['service_name'] : ''; ?>
							<input type="text" class="form-control" id="service_name" name="name" required>
						</div>
						<div class="form-group">
							<label for="service_description">Service Description</label><?php echo isset($error['service_description']) ? $error['service_description'] : ''; ?>
							<textarea type="text" class="form-control" id="service_description" name="description" placeholder="Une description du service"></textarea>
						</div>

						<div class="form-group">
							<label for="service_image">Image&nbsp;&nbsp;&nbsp;*Please choose square image of larger than 350px*350px & smaller than 550px*550px.</label><?php echo isset($error['image']) ? $error['image'] : ''; ?>
							<input type="file" name="image" id="service_image" required />
						</div>
						<div class="form-group">
							<label for="">Is available ? :</label><br>
							<input type="checkbox" id="is_available_button" name="is_available_allowed" class="js-switch" >
						</div>
					</div>
					<!-- /.box-body -->

					<div class="box-footer">
						<button type="submit" class="btn btn-primary" name="btnAdd">Add</button>
						<input type="reset" class="btn-warning btn" value="Clear" />

					</div>

				</form>

			</div><!-- /.box -->
			<?php echo isset($error['check_permission']) ? $error['check_permission'] : ''; ?>
		</div>
	</div>
</section>

<div class="separator"> </div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.17.0/jquery.validate.min.js"></script>


<?php $db->disconnect(); ?>