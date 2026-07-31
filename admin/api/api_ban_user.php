<?php
//admin/api/api_ban_user.php

ob_start();

require_once __DIR__."/../auth.php";
require_once __DIR__."/../../config.php";

header("Content-Type:text/html; charset=utf-8");

// 统一返回函数（改为HTML弹窗跳转）
function jsonReturn(int $code, string $msg): void
{
    ob_clean();
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>操作提示</title></head><body>';
    echo '<script>alert("'.addslashes($msg).'");window.location.href="../user_manage.php";</script>';
    echo '</body></html>';
    exit;
}

// 1. 限制请求方法必须为POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonReturn(0, "请求方式错误，请使用POST提交");
}

// 2. CSRF Token 校验（防CSRF攻击）
$clientCsrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $clientCsrf)) {
    jsonReturn(0, "安全令牌验证失败，请求非法");
}
// 验证后销毁一次性token（防止重复利用）
unset($_SESSION['csrf_token']);

// 3. 管理员权限校验
if (!isAdmin()) {
    jsonReturn(0, "无管理员操作权限");
}

// 4. 获取并清洗参数
$uid = intval($_POST['uid'] ?? 0);
$op = intval($_POST['op'] ?? -1);

// 参数基础校验
if ($uid <= 0) {
    jsonReturn(0, "用户ID参数非法");
}
// op白名单：只能0解封 / 1封禁
if (!in_array($op, [0, 1], true)) {
    jsonReturn(0, "操作类型错误，仅支持0解封、1封禁");
}

// 禁止操作超级管理员uid=1
if ($uid === 1) {
    jsonReturn(0, "禁止封禁超级管理员账号");
}

$db = getDB();
// 开启PDO异常模式（如果config.php没配置）
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // 前置查询：判断目标用户是否存在
    $checkStmt = $db->prepare("SELECT id,is_ban FROM ".DB_PREFIX."users WHERE id = ? LIMIT 1");
    $checkStmt->execute([$uid]);
    $targetUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (empty($targetUser)) {
        jsonReturn(0, "目标用户不存在");
    }

    // 如果状态已经一致，无需更新
    if ((int)$targetUser['is_ban'] === $op) {
        $statusText = $op === 1 ? "该用户已是封禁状态" : "该用户已是解封状态";
        jsonReturn(0, $statusText);
    }

    // 执行更新
    $updateStmt = $db->prepare("UPDATE ".DB_PREFIX."users SET is_ban = ? WHERE id = ?");
    $updateStmt->execute([$op, $uid]);
    $affectedRows = $updateStmt->rowCount();

    if ($affectedRows <= 0) {
        jsonReturn(0, "数据更新失败，未修改任何数据");
    }

    // 操作日志记录（审计用）
    $adminUid = $_SESSION['admin_uid'] ?? 0;
    $logContent = date('Y-m-d H:i:s') . " | 管理员{$adminUid} | " . ($op===1?"封禁":"解封") . " | 用户ID:{$uid}" . PHP_EOL;
    $logDir = __DIR__."/../../logs";
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir."/ban_operation.log", $logContent, FILE_APPEND | LOCK_EX);

    $tip = $op == 1 ? "账号封禁成功" : "账号解封成功";
    jsonReturn(1, $tip);

} catch (PDOException $e) {
    // 数据库异常日志（不对外暴露详细报错）
    error_log("封禁接口数据库异常: " . $e->getMessage());
    jsonReturn(0, "数据库操作异常，请稍后重试");
}
?>

