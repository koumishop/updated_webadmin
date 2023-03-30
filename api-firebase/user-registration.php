<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
header("Content-Type: application/json");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Access-Control-Allow-Origin: *');


include('../includes/crud.php');
include('../includes/custom-functions.php');
include('verify-token.php');
$fn = new custom_functions();
$db = new Database();
$db->connect();
$settings = $fn->get_settings('system_timezone', true);
$app_name = $settings['app_name'];
include 'send-email.php';

$config = $fn->get_configurations();
$time_slot_config = $fn->time_slot_config();
if (isset($config['system_timezone']) && isset($config['system_timezone_gmt'])) {
    date_default_timezone_set($config['system_timezone']);
    $db->sql("SET `time_zone` = '" . $config['system_timezone_gmt'] . "'");
} else {
    date_default_timezone_set('Asia/Kolkata');
    $db->sql("SET `time_zone` = '+05:30'");
}
include('../includes/variables.php');

/* 
-------------------------------------------
APIs for Multi Vendor
-------------------------------------------
1. verify_user
2. edit_profile
3. change_password
4. forgot_password_mobile
5. register_device
6. register
7. upload_profile
8. delete_profile

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
}
// Verification de l'utilisateur
if ((isset($_POST['type'])) && ($_POST['type'] == 'verify-user')) {
    /*
    1. verify_user
        accesskey:90336
        type:verify-user
        mobile:1234567890
        web:1 {optional}
    */

    if (empty($_POST['mobile'])) {
        $response['error'] = true;
        $response['message'] = "Le numéro de téléphone doit être rempli !";
        print_r(json_encode($response));
        return false;
    }

    $mobile = $db->escapeString($fn->xss_clean($_POST['mobile']));
    $web = (isset($_POST['web']) && !empty($_POST['web'])) ? $db->escapeString($fn->xss_clean($_POST['web'])) : "";


    $sql = 'select id from users where mobile =' . $mobile;
    $db->sql($sql);
    $res = $db->getResult();
    $num_rows = $db->numRows($res);
    if ($num_rows > 0) {
        if($web == "1"){
            $response["error"]   = true;
            $response["message"] = "Ce numéro de téléphone est déjà enregistré. Veuillez vous connecter !";
            $response["id"]   = $res[0]['id'];
        }else{
            $response["error"]   = false;
            $response["message"] = "Ce numéro de téléphone est déjà enregistré. Veuillez vous connecter !";
            $response["id"]   = $res[0]['id'];
        }
       
    } else if ($num_rows == 0) {
        if($web == "1"){
            $response["error"]   = false;
            $response["message"] = "Prêt à envoyer la demande de l'OTP !";
        }else{
            $response["error"]   = true;
            $response["message"] = "Prêt à envoyer la demande de l'OTP !";
        }
    }
    print_r(json_encode($response));
    return false;
}
// Faire la mise à jour de l'utilisatur
if (isset($_POST['type']) && $_POST['type'] != '' && $_POST['type'] == 'edit-profile') {
    /*
    2. edit_profile
        accesskey:90336
        type:edit-profile
        user_id:178
        name:Jaydeep
        email:admin@gmail.com
        mobile:1234567890
        profile:file        // {optional}
    */

    if (empty($_POST['user_id']) || empty($_POST['name']) || empty($_POST['email']) || empty($_POST['mobile'])) {
        $response['error'] = true;
        $response['message'] = "Passez tous les champs !";
        print_r(json_encode($response));
        return false;
    }

    $id     = $db->escapeString($fn->xss_clean($_POST['user_id']));
    $name   = $db->escapeString($fn->xss_clean($_POST['name']));
    $email  = $db->escapeString($fn->xss_clean($_POST['email']));
    $mobile = $db->escapeString($fn->xss_clean($_POST['mobile']));

    $sql = 'select * from users where id =' . $id;
    $db->sql($sql);
    $res = $db->getResult();

    if (!empty($res)) {

        if (isset($_FILES['profile']) && !empty($_FILES['profile']) && $_FILES['profile']['error'] == 0 && $_FILES['profile']['size'] > 0) {
            if (!empty($res[0]['profile'])) {
                $old_image = $res[0]['profile'];
                if ($old_image != 'default_user_profile.png' && !empty($old_image)) {
                    unlink('../upload/profile/' . $old_image);
                }
            }

            $profile = $db->escapeString($fn->xss_clean($_FILES['profile']['name']));
            $extension = pathinfo($_FILES["profile"]["name"])['extension'];
            $result = $fn->validate_image($_FILES["profile"]);
            if (!$result) {
                $response["error"]   = true;
                $response["message"] = "Le type d'image doit être jpg, jpeg, gif, ou png !";
                print_r(json_encode($response));
                return false;
            }
            $filename = microtime(true) . '.' . strtolower($extension);
            $full_path = '../upload/profile/' . "" . $filename;
            if (!move_uploaded_file($_FILES["profile"]["tmp_name"], $full_path)) {
                $response["error"]   = true;
                $response["message"] = "Répertoire non valide pour charger le profil !";
                print_r(json_encode($response));
                return false;
            }
            $sql = "UPDATE users SET `profile`='" . $filename . "' WHERE `id`=" . $id;
            $db->sql($sql);
        }

        $sql = 'UPDATE `users` SET `name`="' . $name . '",`email`="' . $email . '",`mobile`="' . $mobile . '" WHERE `id`=' . $id;
        $db->sql($sql);

        $response["error"]   = false;
        $response["message"] = "Le profil a été mis à jour avec succès.";
    }
    print_r(json_encode($response));
    return false;
}

if (isset($_POST['type']) && $_POST['type'] != '' && $_POST['type'] == 'change-password') {
    /* 
    3.change_password
        accesskey:90336
        type:change-password
        user_id:5
        password:12345678
    */

    if (empty($_POST['user_id']) || empty($_POST['password'])) {
        $response['error'] = true;
        $response['message'] = "Passez tous les champs !";
        print_r(json_encode($response));
        return false;
    }
    $id       = $db->escapeString($fn->xss_clean($_POST['user_id']));
    $password = $db->escapeString($fn->xss_clean($_POST['password']));
    $password = md5($password);

    $mobile = $fn->get_data($columns = ['mobile'], 'id = "' . $id . '"', 'users');
    $mobile = $mobile[0]['mobile'];
    if($mobile=='9876543210' && defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION==0){
        $response["error"]   = true;
        $response["message"] = "Le mot de passe du compte démo n'a pas pu être modifié !";
        print_r(json_encode($response));
        return false;
    }

    $sql = 'UPDATE `users` SET `password`="' . $password . '" WHERE `id`=' . $id;
    if ($db->sql($sql)) {
        $response["error"]   = false;
        $response["message"] = "Le mot de passe a été mis à jour avec succès";
    } else {
        $response["error"]   = true;
        $response["message"] = "Quelque chose s'est mal passé ! Essayez à nouveau !";
    }
    print_r(json_encode($response));
    return false;
}

if (isset($_POST['type']) && $_POST['type'] != '' && $_POST['type'] == 'forgot-password-mobile') {
    /* 
    4.forgot_password_mobile
        accesskey:90336
        type:forgot-password-mobile
        mobile:1234567890
        password:12345678
    */

    if (empty($_POST['mobile']) || empty($_POST['password'])) {
        $response['error'] = true;
        $response['message'] = "Passez tous les champs !";
        print_r(json_encode($response));
        return false;
    }

    $mobile  = $db->escapeString($fn->xss_clean($_POST['mobile']));
    $password = $db->escapeString($fn->xss_clean($_POST['password']));

    $encrypted_password = md5($password);
    $sql = "select `id`,`name`,`country_code` from `users` where `mobile`='" . $mobile . "'";
    $db->sql($sql);
    $result = $db->getResult();

    if ($db->numRows($result) > 0) {
        $country_code = $result[0]['country_code'];
        $message = 'Votre mot de passe pour ' . $app_name . ' est réinitialisé. Veuillez vous connecter en utilisant un nouveau mot de passe : ' . $password . '.';
        $sql = 'UPDATE `users` SET `password`="' . $encrypted_password . '" WHERE `mobile`="' . $mobile . '"';
        if ($db->sql($sql)) {
            $response["error"]   = false;
            $response["message"] = "Le mot de passe a été envoyé avec succès ! Veuillez vous connecter via l'OTP envoyé à votre numéro de téléphone mobile !";
        }
    } else {
        $response["error"]   = true;
        $response["message"] = "Le numéro de mobile n'existe pas ! Veuillez vous enregistrer";
    }
    print_r(json_encode($response));
    return false;
}

if (isset($_POST['type']) && $_POST['type'] != '' && $_POST['type'] == 'register-device') {
    /* 
    5.register_device
        accesskey:90336
        type:register-device
        user_id:122
        token:123fghjf687657fre78fg57gf8re7
    */

    if (empty($_POST['user_id']) || empty($_POST['token'])) {
        $response['error'] = true;
        $response['message'] = "Passez tous les champs !";
        print_r(json_encode($response));
        return false;
    }

    $user_id  = $db->escapeString($fn->xss_clean($_POST['user_id']));
    $token  = $db->escapeString($fn->xss_clean($_POST['token']));

    $sql = "select `id` from `users` where `id`='" . $user_id . "'";
    $db->sql($sql);
    $result = $db->getResult();
    if ($db->numRows($result) > 0) {
        $sql = 'UPDATE `users` SET `fcm_id`="' . $token . '" WHERE `id`="' . $user_id . '"';
        if ($db->sql($sql)) {
            $response["error"]   = false;
            $response["message"] = "Le dispositif a été mis à jour avec succès";
        }
    } else {
        $response["error"]   = true;
        $response["message"] = "L'utilisateur n'existe pas.";
    }
    print_r(json_encode($response));
    return false;
}
//Enregistrer un utilisateur
try{
    if ((isset($_POST['type'])) && ($_POST['type'] == 'register')) {
        /* 
        6.register
            accesskey:90336
            type:register
            name:Jaydeep Goswami
            email:admin@gmail.com
            mobile:9876543210
            password:12345678
            sex: M
            date :1990
            friends_code:value //{optional}
            profile:FILE        // {optional}
            country_code:91  // {optional}
        */

        $name = $db->escapeString($fn->xss_clean($_POST['name']));
        $email = $db->escapeString($fn->xss_clean($_POST['email']));
        $mobile = $db->escapeString($fn->xss_clean($_POST['mobile']));
        $password = md5($db->escapeString($fn->xss_clean($_POST['password'])));
        $sex = $db->escapeString($fn->xss_clean($_POST['sex']));
        $date_of_birth= $db->escapeString($fn->xss_clean($_POST['date_of_birth']));
        $fcm_id = (isset($_POST['fcm_id'])) ? $db->escapeString($fn->xss_clean($_POST['fcm_id'])) : "";
        $country_code = (isset($_POST['country_code'])) ? $db->escapeString($fn->xss_clean($_POST['country_code'])) : "91";
        $status     = 1;
        $chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $referral_code  = "";

        
        // $response["error"]   = true;
        // $response["message"] =  $date_of_birth;
        // echo json_encode($response);
        // return false;
        
        //if (empty($name) && empty($email) && empty($mobile) && empty($password)&& empty($sex)&& empty($date_of_birth)) {
            if (!empty($name) && !empty($email) && !empty($mobile) && !empty($password)) { //&& !empty($sex)&& !empty($date_of_birth)
            for ($i = 0; $i < 10; $i++) {
                $referral_code .= $chars[mt_rand(0, strlen($chars) - 1)];
            }
            // code existant if (isset($_POST['friends_code']) && $_POST['friends_code'] != '') {
            
            // une fonctionnalité qui obsolete :invitation augmentation de credit 

            // if (isset($_POST['friends_code']) && $_POST['friends_code'] != '') {
            //     $friend_code = $db->escapeString($fn->xss_clean($_POST['friends_code']));
            //     $friend_code ="7P3WK9XBPP";
            //     $sql = "SELECT id FROM users WHERE referral_code='" . $friend_code . "'";
            //     $db->sql($sql);
            //     $result = $db->getResult();
            //     $num_rows = $db->numRows($result);

            //     $response["error"]   = true;
            //     $response["message"] = $num_rows;
            //     echo json_encode($response);
            //         return false;
            //     if ($num_rows > 0) {
            //         $friends_code = $db->escapeString($fn->xss_clean($_POST['friends_code']));
            //     } else {
            //         $response["error"]   = true;
            //         $response["message"] = "Code d'amis non valide !";
            //      //   $response["message"] = $referral_code;
            //         echo json_encode($response);
            //         return false;
            //     }
            // } else {
            //     $friends_code = '';
            // }
            
        // Verification de mobile
            if (!empty($mobile)) {
                $sql = "select mobile from users where mobile='" . $mobile . "'";
                $db->sql($sql);
                $res = $db->getResult();
                $num_rows = $db->numRows($res);
                if ($num_rows > 0) {
                    $response["error"]   = true;
                    $response["message"] = "Ce mobile  $mobile est déjà enregistré. Veuillez vous connecter !";
                    print_r(json_encode($response));
                    return false;
                } else if ($num_rows == 0) {
                    if (isset($_FILES['profile']) && !empty($_FILES['profile']) && $_FILES['profile']['error'] == 0 && $_FILES['profile']['size'] > 0) {
                        $profile = $db->escapeString($fn->xss_clean($_FILES['profile']['name']));
                        if (!is_dir('../../upload/profile/')) {
                            mkdir('../../upload/profile/', 0777, true);
                        }
                        $extension = pathinfo($_FILES["profile"]["name"])['extension'];
                        $result = $fn->validate_image($_FILES["profile"]);

                        if (!$result) {
                            $response["error"]   = true;
                            $response["message"] = "Le type d'image doit être jpg, jpeg, gif, ou png !";
                            print_r(json_encode($response));
                            return false;
                        }

                        $filename = microtime(true) . '.' . strtolower($extension);
                        $full_path = '../upload/profile/' . "" . $filename;
                        if (!move_uploaded_file($_FILES["profile"]["tmp_name"], $full_path)) {
                            $response["error"]   = true;
                            $response["message"] = "Répertoire non valide pour charger le profil !";
                            print_r(json_encode($response));
                            return false;
                        }
                    } else {
                        $filename = 'default_user_profile.png';
                        $full_path = 'upload/profile/' . "" . $filename;
                    }
                    //user is not registered, insert the data to the database  
                    $sql = "INSERT INTO users(`name`,`email`, `mobile`,`password`,`fcm_id`,`profile`,`referral_code`,`friends_code`,`status`,`country_code`,`date_of_birth`,`sex`)VALUES('$name','$email','$mobile','$password','$fcm_id','$filename','$referral_code','$friends_code','1','$country_code','$date_of_birth','$sex')";
                    $db->sql($sql);
                    $res = $db->getResult();
                    $usr_id = $fn->get_data($columns = ['id'], 'mobile = "' . $mobile . '"', 'users');

                    $sql = "DELETE FROM devices where fcm_id = '$fcm_id' ";
                    $db->sql($sql);
                    $res = $db->getResult();

                    $sql_query = "SELECT * FROM `users` WHERE `mobile` = '" . $mobile . "' AND `password` ='" . $password . "'";
                    $db->sql($sql_query);
                    $result = $db->getResult();
                    if ($db->numRows($result) > 0) {
                        $response["error"]   = false;
                        $response["message"] = "L'utilisateur s'est enregistré avec succès";
                        $response['password']  = $result[0]['password'];
                        foreach ($result as $row) {
                            $response['error']     = false;
                            $response['user_id'] = $row['id'];
                            $response['name'] = $row['name'];
                            $response['email'] = $row['email'];
                            $response['profile'] = DOMAIN_URL . 'upload/profile/' . "" . $row['profile'];
                            $response['mobile'] = $row['mobile'];
                            $response['balance'] = $row['balance'];
                            $response['country_code'] = $row['country_code'];
                            $response['referral_code'] = $row['referral_code'];
                            $response['friends_code'] = $row['friends_code'];
                            $response['fcm_id'] = $row['fcm_id'];
                            $response['status'] = $row['status'];
                            $response['sex'] = $row['sex'];
                            $response['date_of_birth'] = $row['date_of_birth'];
                            $response['created_at'] = $row['created_at'];
                        }
                    }
                }
            }
        } else {
            $response["error"]   = true;
            $response["message"] = "Veuillez passer tous les champs ou le nom de paramatre  s'il vous plait";
        }
        print_r(json_encode($response));
        return false;
    }
}catch(Exception $exception){
    $response["error"]   = true;
    $response["message"] = $exception->getMessage();
    print_r(json_encode($response));
    return false;
}

if ((isset($_POST['type'])) && ($_POST['type'] == 'upload_profile')) {
    /* 
    7.upload_profile
        accesskey:90336
        type:upload_profile
        user_id:4
        profile:FILE        // {optional}
    */

    if (!isset($_POST['user_id']) && empty($_POST['user_id'])) {
        $response["error"]   = true;
        $response["message"] = "L'identifiant de l'utilisateur est manquant.";
        print_r(json_encode($response));
        return false;
    }
    $id = $db->escapeString($fn->xss_clean($_POST['user_id']));
    $sql = 'select * from users where id =' . $id;
    $db->sql($sql);
    $res = $db->getResult();

    if (!empty($res)) {
        if (isset($_FILES['profile']) && !empty($_FILES['profile']) && $_FILES['profile']['error'] == 0 && $_FILES['profile']['size'] > 0) {

            if (!is_dir('../upload/profile/')) {
                mkdir('../upload/profile/', 0777, true);
            }
            if (!empty($res[0]['profile'])) {
                $old_image = $res[0]['profile'];
                if ($old_image != 'default_user_profile.png' && !empty($old_image)) {
                    unlink('../upload/profile/' . $old_image);
                }
            }
            $profile = $db->escapeString($fn->xss_clean($_FILES['profile']['name']));
            $extension = pathinfo($_FILES["profile"]["name"])['extension'];
            $result = $fn->validate_image($_FILES["profile"]);
            if (!$result) {
                $response["error"]   = true;
                $response["message"] = "Le type d'image doit être jpg, jpeg, gif, ou png !";
                print_r(json_encode($response));
                return false;
            }
            $filename = microtime(true) . '.' . strtolower($extension);
            $full_path = '../upload/profile/' . "" . $filename;
            if (!move_uploaded_file($_FILES["profile"]["tmp_name"], $full_path)) {
                $response["error"]   = true;
                $response["message"] = "Répertoire non valide pour charger le profil !";
                print_r(json_encode($response));
                return false;
            }
            $sql = "UPDATE users SET `profile`='" . $filename . "' WHERE `id`=" . $id;
            if ($db->sql($sql)) {
                $profile = $fn->get_data($columns = ['profile'], 'id = "' . $id . '"', 'users');
                $profile_url = DOMAIN_URL . 'upload/profile/' . "" . $profile[0]['profile'];
                $response["error"]   = false;
                $response["profile"]   = $profile_url;
                $response["message"] = "Le profil a été mis à jour avec succès.";
            } else {
                $response["error"]   = true;
                $response["message"] = "Le profil n'est pas mis à jour.";
            }
        } else {
            $response["error"]   = true;
            $response["message"] = "Le paramètre de profil est manquant.";
        }
    } else {
        $response["error"]   = true;
        $response["message"] = "L'utilisateur n'existe pas.";
    }
    print_r(json_encode($response));
    return false;
}

// Faire la suppression de l'utilisatur
if (isset($_POST['type']) && $_POST['type'] != '' && $_POST['type'] == 'delete-profile') {
    /*
    8. delete_profile
        accesskey:90336
        type:delete-profile
        user_id:178
        email:admin@gmail.com
        mobile:1234567890
    */

    if (empty($_POST['user_id']) || empty($_POST['email']) || empty($_POST['mobile'])) {
        $response['error'] = true;
        $response['message'] = "Passez tous les champs !";
        print_r(json_encode($response));
        return false;
    }

    $id     = $db->escapeString($fn->xss_clean($_POST['user_id']));
    $email  = $db->escapeString($fn->xss_clean($_POST['email']));
    $mobile = $db->escapeString($fn->xss_clean($_POST['mobile']));

    $sql = 'select * from users where id =' . $id;
    $db->sql($sql);
    $res = $db->getResult();
    
    if (!empty($res)) {
        
/*        $sql = 'DELETE users WHERE id = ' . $id;
        $db->sql($sql);
        
        $res = $db->getResult();
        $response["message"] = $res;*/
        if ($db->delete('users', 'id=' . $id)) {
            $response["error"]   = false;
            $response["message"] = "Le profil a été supprimer avec succès.";
        }else {
            $response["error"]   = true;
            $response["message"] = "Le profil n'a pas été supprimer.";
        }
    }
    print_r(json_encode($response));
    return false;
}


$response["error"]   = true;
$response["message"] = "Veuillez vérifier le parametre type, s'il vous plait";
print_r(json_encode($response));
return false;

