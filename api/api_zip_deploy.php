<?php
//api/api_zip_deploy.php
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
    echo json_encode(["code"=>0,"msg"=>"存储空间已满"]);exit;
}
if(!isset($_FILES['zip'])){
    echo json_encode(["code"=>0,"msg"=>"未收到压缩包文件"]);exit;
}
$zipFile = $_FILES['zip']['tmp_name'];
$zipName = time()."_".rand(1000,9999);
$outPath = getUserSpace($uid).$zipName."/";
if(!is_dir($outPath)) mkdir($outPath,0755,true);
$zip = new ZipArchive();
if ($zip->open($zipFile) === TRUE) {
    $zip->extractTo($outPath);
    $zip->close();
}else{
    echo json_encode(["code"=>0,"msg"=>"压缩包解压失败，请检查zip文件"]);exit;
}
$indexFile = $zipName."/index.html";
//生成访问链接
do{
    $randStr = getRandStr(32);
    $ck = $db->prepare("SELECT id FROM ".DB_PREFIX."shortlink WHERE rand_key=?");
    $ck->execute([$randStr]);
}while($ck->rowCount()>0);
$ins = $db->prepare("INSERT INTO ".DB_PREFIX."shortlink(uid,rand_key,project_path)VALUES(?,?,?)");
$ins->execute([$uid,$randStr,$indexFile]);
$host = $_SERVER['HTTP_HOST'];
$fullUrl = "http://".$host."/api/link.php?url=".$randStr;
echo json_encode(["code"=>1,"msg"=>"压缩包部署成功","data"=>["url"=>$fullUrl,"path"=>$indexFile]]);
exit;
?>
