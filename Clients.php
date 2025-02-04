<?php
require_once('../config.php');
Class client extends DBConnection {
	private $settings;
	public function __construct(){
		global $_settings;
		$this->settings = $_settings;
		parent::__construct();
	}
	public function __destruct(){
		parent::__destruct();
	}
	public function save_client(){
		if(empty($_POST['password']))
			unset($_POST['password']);
		else
			$_POST['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
		extract($_POST);
		$user_field = ['firstname', 'middlename', 'lastname', 'username', 'password', 'type'];
		$oid = $id;
		$data = '';
		$chk = $this->conn->query("SELECT * FROM `client_tb` where username ='{$username}' ".($id>0? " and id!= '{$id}' " : ""))->num_rows;
		if($chk > 0){
			return 3;
			exit;
		}
		foreach($_POST as $k => $v){
			if(in_array($k,$user_field) && !is_array($_POST[$k])){
				if(!empty($data)) $data .=" , ";
				$v = $this->conn->real_escape_string($v);
				$data .= " {$k} = '{$v}' ";
			}
		}
		
		if(empty($id)){
			$sql = "INSERT INTO client_tb set {$data}";
		}else{
			$sql = "UPDATE client_tb set $data where id = {$id}";
		}
		$save = $this->conn->query($sql);
		if($save){
			$uid = empty($id) ? $this->conn->insert_id : $id;
			if(isset($_FILES['image']) && $_FILES['image']['tmp_name'] != ''){
				if(!is_dir(base_app."uploads/users"))
					mkdir(base_app."uploads/users");
				$fname = 'uploads/users/avatar-'.$uid.'.png';
				$dir_path =base_app. $fname;
				$upload = $_FILES['image']['tmp_name'];
				$type = mime_content_type($upload);
				$allowed = array('image/png','image/jpeg');
				if(!in_array($type,$allowed)){
					$resp['msg'].=" But Image failed to upload due to invalid file type.";
				}else{
					list($width, $height) = getimagesize($upload);
					$new_width = $width; 
					$new_height = $height; 
					if($width > 200)
					$new_width = 200; 
					
					if($height > 200)
					$new_height = 200; 
			
					$t_image = imagecreatetruecolor($new_width, $new_height);
					imagealphablending( $t_image, false );
					imagesavealpha( $t_image, true );
					$gdImg = ($type == 'image/png')? imagecreatefrompng($upload) : imagecreatefromjpeg($upload);
					imagecopyresampled($t_image, $gdImg, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
					if($gdImg){
							if(is_file($dir_path))
							unlink($dir_path);
							$uploaded_img = imagepng($t_image,$dir_path);
							imagedestroy($gdImg);
							imagedestroy($t_image);
					}else{
					$resp['msg'].=" But Image failed to upload due to unkown reason.";
					}
				}
				if(isset($uploaded_img)){
					$this->conn->query("UPDATE client_tb set `avatar` = CONCAT('{$fname}','?v=',unix_timestamp(CURRENT_TIMESTAMP)) where id = '{$uid}' ");
					if($uid == $this->settings->userdata('id')){
							$this->settings->set_userdata('avatar',$fname);
					}
				}
			}
			$data = "";
			foreach($_POST as $k => $v){
				if(!in_array($k,array_merge(['id'], $user_field)) && !is_array($_POST[$k])){
					if(!empty($data)) $data .=", ";
					$v = $this->conn->real_escape_string($v);
					$data .= "('{$uid}','{$k}', '{$v}')";
				}
			}
			if(!empty($data)){
				$this->conn->query("DELETE FROM `seller_meta` where user_id = '{$uid}' ");
				$sql2 = "INSERT INTO `seller_meta` (`user_id`, `meta_field`, `meta_value`) VALUES {$data} ";
				$save2  = $this->conn->query($sql2);
				if(!$save2){
					if(empty($id))
						$this->conn->query("DELETE FROM `client_tb` where id = '{$uid}'");
						$resp['status'] = 'failed';
						$resp['msg'] = 'An error occurred';
						$resp['error'] = $this->conn->error;
						$resp['sql'] = $sql2;
						return json_encode($resp);
						exit;
				}
			}
			if(empty($id))
				$this->settings->set_flashdata('success','Your Account has been created successfully.');
			else
				$this->settings->set_flashdata('success','Your Account has been updated successfully.');
			$resp['status'] = 'success';
			if($this->settings->userdata('id') == $uid){
				foreach($_POST as $k => $v){
					if(!in_array($k,['id']) && !is_array($_POST[$k])){
						$this->settings->set_userdata($k,$v);
					}
				}
			}
		}else{
			$resp['status'] = 'failed';
			$resp['msg'] = 'Saving account failed.';
			$resp['error'] = $this->conn->error;
		}
		
		
		return  json_encode($resp);
	}
	public function delete_client(){
		extract($_POST);
		$avatar = $this->conn->query("SELECT avatar FROM client_tb where id = '{$id}'")->fetch_array()['avatar'];
		$qry = $this->conn->query("DELETE FROM client_tb where id = $id");
		if($qry){
			$this->settings->set_flashdata('success','User Details successfully deleted.');
			$avatar = explode("?", $avatar)[0];
			if(is_file(base_app.$avatar))
				unlink(base_app.$avatar);
			$resp['status'] = 'success';
		}else{
			$resp['status'] = 'failed';
		}
		return json_encode($resp);
	}
}

$client_tb = new client();
$action = !isset($_GET['f']) ? 'none' : strtolower($_GET['f']);
switch ($action) {
	case 'save_user':
		echo $client_tb->save_client();
	break;
	case 'delete_user':
		echo $client_tb->delete_client();
	break;
	default:
		// echo $sysset->index();
		break;
}