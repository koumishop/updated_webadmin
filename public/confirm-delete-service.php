<div id="content" class="container col-md-12">
    <?php
    include_once('includes/custom-functions.php');
    $fn = new custom_functions;

    if (isset($_POST['btnDelete'])) {
        if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
            echo '<label class="alert alert-danger">This operation is not allowed in demo panel!.</label>';
            return false;
        }

        if (isset($_GET['id']) && !empty($_GET['id'])) {
            $ID = $db->escapeString($fn->xss_clean($_GET['id']));
        } else { ?>
            <script>
                alert("Something went wrong, No data available.");
                window.location.href = "services.php";
            </script>
    <?php
        }

        $sql_query = "SELECT image FROM services WHERE id =" . $ID;
        $db->sql($sql_query);
        $res = $db->getResult();
        unlink($res[0]['image']);
        $sql_query = "DELETE FROM services WHERE id =" . $ID;
        $db->sql($sql_query);
        $delete_service_result = $db->getResult();
        if (!empty($delete_service_result)) {
            $delete_service_result = 0;
        } else {
            $delete_service_result = 1;
        }

        if($delete_service_result){
            header("location: services.php");
        }
    }

    if (isset($_POST['btnNo'])) {
        header("location: services.php");
    }
    if (isset($_POST['btncancel'])) {
        header("location: services.php");
    }

    ?>
    <h1>Confirm Action</h1>
    <?php
    if ($permissions['services']['delete'] == 1) { ?>
        <hr />
        <form method="post">
            <p>Are you sure want to delete this Service?</p>
            <input type="submit" class="btn btn-primary" value="Delete" name="btnDelete" />
            <input type="submit" class="btn btn-danger" value="Cancel" name="btnNo" />
        </form>
        <div class="separator"> </div>
    <?php } else { ?>
        <div class="alert alert-danger topmargin-sm">You have no permission to delete services.</div>
        <form method="post">
            <input type="submit" class="btn btn-danger" value="Back" name="btncancel" />
        </form>
    <?php } ?>
</div>

<?php $db->disconnect(); ?>