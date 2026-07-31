<?php
// admin/api/api_ban_ip.php
require __DIR__ . "/../../config.php";
header("Content-Type: application/json; charset=utf-8");

// 1. 权限校验
if (!isAdmin()) {
    echo json_encode(["code" => 0, "msg" => "无操作权限"]);
    exit;
}

// 2. 限制请求方法
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["code" => 0, "msg" => "请求方式错误"]);
    exit;
}

// 3. CSRF 校验
$csrfToken = $_POST["csrf_token"] ?? "";
if (empty($csrfToken) || !hash_equals($_SESSION["csrf_token"] ?? "", $csrfToken)) {
    echo json_encode(["code" => 0, "msg" => "CSRF 校验失败"]);
    exit;
}

$act = trim($_POST["act"] ?? "");
$db = getDB();

try {
    if ($act === "add") {
        $ip = trim($_POST["ip"] ?? "");

        if (empty($ip)) {
            echo json_encode(["code" => 0, "msg" => "IP不能为空"]);
            exit;
        }

        // 4. IP 格式校验
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            echo json_encode(["code" => 0, "msg" => "IP格式不正确"]);
            exit;
        }

        // 5. 使用 INSERT IGNORE 防止并发重复插入（需配合唯一索引）
        $stmt = $db->prepare("INSERT IGNORE INTO " . DB_PREFIX . "ip_black (ip) VALUES (?)");
        $stmt->execute([$ip]);

        if ($stmt->rowCount() > 0) {
            logAdminAction("ban_ip_add", "封禁IP: {$ip}");
            echo json_encode(["code" => 1, "msg" => "IP封禁添加成功"]);
        } else {
            echo json_encode(["code" => 0, "msg" => "该IP已经在封禁列表中"]);
        }
        exit;

    } elseif ($act === "del") {
        $id = intval($_POST["id"] ?? 0);

        if ($id <= 0) {
            echo json_encode(["code" => 0, "msg" => "参数错误"]);
            exit;
        }

        // 6. 删除前确认记录存在
        $check = $db->prepare("SELECT ip FROM " . DB_PREFIX . "ip_black WHERE id = ?");
        $check->execute([$id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(["code" => 0, "msg" => "该封禁记录不存在"]);
            exit;
        }

        $del = $db->prepare("DELETE FROM " . DB_PREFIX . "ip_black WHERE id = ?");
        $del->execute([$id]);

        // 7. 审计日志
        logAdminAction("ban_ip_del", "解封IP: {$row['ip']} (id={$id})");
        echo json_encode(["code" => 1, "msg" => "IP已解除封禁"]);
        exit;

    } else {
        echo json_encode(["code" => 0, "msg" => "未知操作"]);
        exit;
    }
} catch (PDOException $e) {
    // 生产环境不要直接把 SQL 错误暴露给前端
    error_log("api_ban_ip error: " . $e->getMessage());
    echo json_encode(["code" => 0, "msg" => "操作失败，请稍后再试"]);
    exit;
}

