<?php
//api_del_project.php
require __DIR__."/../config.php";
header("Content-Type:application/json;charset=utf-8");
//登录校验
if(!isset($_SESSION['uid'])){
    echo json_encode(['code'=>0,'msg'=>'未登录']);
    exit;
}
$uid = $_SESSION['uid'];
$id = intval($_POST['id'] ?? 0);
$projectPath = trim($_POST['path'] ?? '');
if($id<=0 || empty($projectPath)){
    echo json_encode(['code'=>0,'msg'=>'参数错误']);
    exit;
}
$db = getDB();
//校验：这条记录属于当前登录用户，防止越权删除
$chk = $db->prepare("SELECT id FROM ".DB_PREFIX."shortlink WHERE id=? AND uid=?");
$chk->execute([$id,$uid]);
$row = $chk->fetch();
if(!$row){
    echo json_encode(['code'=>0,'msg'=>'无权删除该项目']);
    exit;
}

//物理删除目录函数
function rmDirRecursive($dir) {
    if (!is_dir($dir)) return true;
    $files = array_diff(scandir($dir), ['.','..']);
    foreach ($files as $file) {
        $fullPath = $dir.DIRECTORY_SEPARATOR.$file;
        is_dir($fullPath) ? rmDirRecursive($fullPath) : unlink($fullPath);
    }
    return rmdir($dir);
}

$fullPath = getUserSpace($uid).DIRECTORY_SEPARATOR.$projectPath;
//删除文件夹
if(is_dir($fullPath)){
    rmDirRecursive($fullPath);
}
//删除数据库记录
$delStmt = $db->prepare("DELETE FROM ".DB_PREFIX."shortlink WHERE id=? AND uid=?");
$delStmt->execute([$id,$uid]);

echo json_encode(['code'=>1,'msg'=>'删除成功']);
exit;
