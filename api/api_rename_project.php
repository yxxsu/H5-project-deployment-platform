<?php
require __DIR__."/../config.php";
header("Content-Type:application/json;charset=utf-8");
if(!isset($_SESSION['uid'])){
    echo json_encode(['code'=>0,'msg'=>'未登录']);
    exit;
}
$uid = $_SESSION['uid'];
$id = intval($_POST['id']??0);
$oldPath = trim($_POST['old_path']??'');
$newPath = trim($_POST['new_path']??'');

//简单路径过滤，禁止非法字符
if(preg_match('/[\/\\:*?"<>|]/',$newPath)){
    echo json_encode(['code'=>0,'msg'=>'目录名称不能包含 / \ : * ? " < > |']);
    exit;
}
if($id<=0 || empty($oldPath) || empty($newPath)){
    echo json_encode(['code'=>0,'msg'=>'参数不全']);
    exit;
}
if($oldPath === $newPath){
    echo json_encode(['code'=>0,'msg'=>'新目录名不能和原来一致']);
    exit;
}

$db = getDB();
//越权校验
$check = $db->prepare("SELECT id FROM ".DB_PREFIX."shortlink WHERE id=? AND uid=?");
$check->execute([$id,$uid]);
if(!$check->fetch()){
    echo json_encode(['code'=>0,'msg'=>'无权操作该项目']);
    exit;
}

$userRoot = getUserSpace($uid);
$oldFull = $userRoot.DIRECTORY_SEPARATOR.$oldPath;
$newFull = $userRoot.DIRECTORY_SEPARATOR.$newPath;

if(!is_dir($oldFull)){
    echo json_encode(['code'=>0,'msg'=>'原项目文件夹不存在']);
    exit;
}
if(is_dir($newFull)){
    echo json_encode(['code'=>0,'msg'=>'同名目录已存在']);
    exit;
}

//物理重命名文件夹
if(!rename($oldFull,$newFull)){
    echo json_encode(['code'=>0,'msg'=>'服务器目录重命名失败，请检查权限']);
    exit;
}
//更新数据库
$upd = $db->prepare("UPDATE ".DB_PREFIX."shortlink SET project_path=? WHERE id=? AND uid=?");
$upd->execute([$newPath,$id,$uid]);

echo json_encode(['code'=>1,'msg'=>'成功']);
exit;
