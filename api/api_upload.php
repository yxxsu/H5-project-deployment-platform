<?php
//api/api/api_upload.php
require __DIR__."/../config.php";
header("Content-Type:application/json");
if(!isset($_SESSION['uid'])){
    echo json_encode(["code"=>0,"msg"=>"未登录"]);exit;
}
$uid = $_SESSION['uid'];
$db = getDB();
$maxMb = isAdmin() ? 999999 : $db->query("SELECT cfg_value FROM ".DB_PREFIX."system_config WHERE cfg_key='max_space_mb'")->fetchColumn();
$nowSize = getDirSize(getUserSpace($uid));
if($nowSize>=$maxMb){
    echo json_encode(["code"=>0,"msg"=>"存储空间已满，无法上传"]);exit;
}
$filename = isset($_POST['filename']) ? trim($_POST['filename']) : "";
$code = isset($_POST['code']) ? $_POST['code'] : "";
if(empty($filename)){
    echo json_encode(["code"=>0,"msg"=>"文件名不能为空"]);exit;
}
$saveDir = getUserSpace($uid);
if(!is_dir($saveDir)) mkdir($saveDir,0755,true);
$savePath = $saveDir.$filename;
file_put_contents($savePath,$code);
$relativePath = $filename;
echo json_encode(["code"=>1,"msg"=>"保存成功","data"=>["path"=>$relativePath]]);
exit;
?>
