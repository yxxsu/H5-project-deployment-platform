<?php
//api/api_gen_link.php
require __DIR__."/../config.php";
header("Content-Type:application/json");
if(!isset($_SESSION['uid'])){
    echo json_encode(["code"=>0,"msg"=>"未登录"]);exit;
}
$uid = $_SESSION['uid'];
$path = isset($_POST['path']) ? trim($_POST['path']) : "";
if(empty($path)){
    echo json_encode(["code"=>0,"msg"=>"项目路径不能为空"]);exit;
}
$db = getDB();
//生成不重复随机串
do{
    $randStr = getRandStr(32);
    $ck = $db->prepare("SELECT id FROM ".DB_PREFIX."shortlink WHERE rand_key=?");
    $ck->execute([$randStr]);
}while($ck->rowCount()>0);
$ins = $db->prepare("INSERT INTO ".DB_PREFIX."shortlink(uid,rand_key,project_path)VALUES(?,?,?)");
$ins->execute([$uid,$randStr,$path]);
$host = $_SERVER['HTTP_HOST'];
$fullUrl = "http://".$host."/api/link.php?url=".$randStr;
echo json_encode(["code"=>1,"data"=>["key"=>$randStr,"url"=>$fullUrl]]);
exit;
?>
