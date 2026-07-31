<?php
//admin/auth.php 后台统一鉴权文件
//【强制要求】本文件保存格式：UTF-8 无BOM

// 定义config完整路径
$configFile = __DIR__ . "/../config.php";

// 检测配置文件是否存在
if (!file_exists($configFile)) {
    http_response_code(403);
    // 输出前不再执行任何header/session操作，直接渲染页面
    echo '<!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <title>系统未初始化</title>
        <style>
            body{font-family:system-ui;background:#f3f4f6;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
            .box{background:#fff;padding:32px;border-radius:10px;text-align:center;box-shadow:0 2px 12px #00000015}
            .tip{font-size:18px;color:#dc2626;margin-bottom:20px}
            a{display:inline-block;padding:10px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px}
        </style>
    </head>
    <body>
        <div class="box">
            <div class="tip">⚠ 系统尚未安装，请前往安装程序</div>
            <a href="../install.php">前往 install.php 初始化系统</a>
        </div>
    </body>
    </html>';
    exit;
}

// 载入配置文件
require_once $configFile;

// 安全响应头（增加CSP加固）
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline';");

// 管理员权限校验：增加函数存在性判断，防止致命错误
if (!function_exists('isAdmin') || !isAdmin()) {
    http_response_code(403);
    die("⚠ 权限不足，禁止访问");
}

//统一生成CSRF Token
if(empty($_SESSION['admin_csrf'])){
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$adminCsrf = $_SESSION['admin_csrf'];

