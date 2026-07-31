<?php
//api/link.php
require __DIR__."/../config.php";

// 修正变量名混淆：传入参数是短链密钥key，不是url
$shortKey = isset($_GET['url']) ? trim($_GET['url']) : "";
if(empty($shortKey)){
    http_response_code(404);
    echo "404 访问链接不存在";
    exit;
}

$db = getDB();
$st = $db->prepare("SELECT * FROM ".DB_PREFIX."shortlink WHERE rand_key=? LIMIT 1");
$st->execute([$shortKey]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if(!$row){
    http_response_code(404);
    echo "404，无效部署链接";
    exit;
}

$userRoot = getUserSpace($row['uid']);
// 防御路径遍历：清除 .. 、反斜杠、去除开头斜杠
$projectPath = str_replace(['..', '\\'], ['', '/'], $row['project_path']);
$projectPath = ltrim($projectPath, '/');

$targetPath = $userRoot . DIRECTORY_SEPARATOR . $projectPath;
$realTarget = realpath($targetPath);
$realUserRoot = realpath($userRoot);

// 安全边界校验：文件必须存在 且 文件绝对路径必须属于用户目录内
if ($realTarget === false || $realUserRoot === false || strpos($realTarget, $realUserRoot) !== 0) {
    http_response_code(403);
    echo "403 禁止访问，非法路径";
    exit;
}

// 阻断PHP流包装器、网络协议，防止SSRF、php://filter读取源码
$scheme = parse_url($realTarget, PHP_URL_SCHEME);
if ($scheme !== null) {
    http_response_code(403);
    exit;
}

if(!file_exists($realTarget) || !is_file($realTarget)){
    http_response_code(404);
    echo "文件已被删除";
    exit;
}

// 完善MIME类型映射
$ext = strtolower(pathinfo($realTarget, PATHINFO_EXTENSION));
$mimeMap = [
    'css'   => 'text/css',
    'js'    => 'application/javascript',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'svg'   => 'image/svg+xml',
    'html'  => 'text/html',
    'htm'   => 'text/html',
    'json'  => 'application/json',
    'txt'   => 'text/plain',
    'ico'   => 'image/x-icon',
    'webp'  => 'image/webp'
];
$mime = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';

// 安全响应头
header("X-Content-Type-Options: nosniff"); // 阻止浏览器MIME嗅探
header("Content-Type: ".$mime.";charset=utf-8");
header("Cache-Control: public, max-age=3600");

// 输出文件内容
readfile($realTarget);
exit;
?>
