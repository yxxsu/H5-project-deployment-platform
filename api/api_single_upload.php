<?php
//api/api_single_upload.php
require __DIR__."/../config.php";
header("Content-Type:application/json;charset=utf-8");
if(!isset($_SESSION['uid'])){
    exit(json_encode(["code"=>0,"msg"=>"未登录"]));
}
$uid = $_SESSION['uid'];
$db = getDB();
$isAdmin = isAdmin();
$userDir = getUserSpace($uid);

if(!isset($_FILES['single'])){
    exit(json_encode(["code"=>0,"msg"=>"未接收到文件"]));
}
$file = $_FILES['single'];
if($file['error']!==0){
    exit(json_encode(["code"=>0,"msg"=>"文件上传失败"]));
}
//检测存储空间
$smax = $isAdmin?999999:$db->query("SELECT cfg_value FROM ".DB_PREFIX."system_config WHERE cfg_key='max_space_mb'")->fetchColumn();
$used = getDirSize($userDir);
$fileMb = round($file['size']/1024/1024,3);
if(($used+$fileMb)>$smax){
    exit(json_encode(["code"=>0,"msg"=>"存储空间不足"]));
}
$saveName = $file['name'];
$savePath = $userDir."/".$saveName;
if(!move_uploaded_file($file['tmp_name'],$savePath)){
    exit(json_encode(["code"=>0,"msg"=>"文件写入失败，请检查目录权限"]));
}
//生成短链
$gen = $db->prepare("INSERT INTO ".DB_PREFIX."shortlink(uid,project_path,create_time,rand_key)VALUES(?,?,?,?)");
$randKey = getRandStr(32);
$time = date("Y-m-d H:i:s");
$gen->execute([$uid,$saveName,$time,$randKey]);
$url = $_SERVER['HTTP_HOST']."/api/link.php?url=".$randKey;
echo json_encode(["code"=>1,"data"=>["path"=>$saveName,"url"=>$url]]);
