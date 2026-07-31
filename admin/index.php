<?php
//admin/index.php
require_once __DIR__."/auth.php"; // 只引入鉴权文件，不要再引入config！

// ========== 安全响应头 最先执行 ==========
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:;");

// CSRF Token直接使用auth.php定义好的 $adminCsrf
$csrfToken = $adminCsrf;

// ========== 数据库读取主题色 + 安全校验 ==========
$db = getDB();
$stmt = $db->query("SELECT cfg_value FROM ".DB_PREFIX."system_config WHERE cfg_key='theme_color'");
$themeColor = $stmt->fetchColumn();

// 默认主题色 + 严格白名单正则：仅允许6位十六进制颜色 #xxxxxx
$defaultColor = '#1890ff';
if(!is_string($themeColor) || !preg_match('/^#[0-9a-fA-F]{6}$/', $themeColor)){
    $themeColor = $defaultColor;
}

// 直接使用校验完成的颜色，不再调用htmlSafe（html转义不适用于CSS上下文）
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统管理后台</title>
    <!-- jQuery 增加SRI完整性校验 -->
    <script 
        src="https://code.jquery.com/jquery-3.6.0.min.js"
        integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4="
        crossorigin="anonymous">
    </script>
    <!-- FontAwesome SRI完整性校验 -->
    <link 
        rel="stylesheet" 
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
        integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg=="
        crossorigin="anonymous"
    >
    <style>
        :root{
            --main-color:<?php echo $themeColor;?>;
            --glass-bg: rgba(255, 255, 255, 0.65);
            --glass-border: rgba(255, 255, 255, 0.25);
            --shadow-light: 0 4px 16px rgba(0,0,0,0.06);
            --shadow-hover: 0 6px 22px rgba(0,0,0,0.09);
            --radius-md: 10px;
            --radius-lg: 12px;
            --text-dark: #2d3748;
            --text-gray: #4a5568;
        }
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family: system-ui, -apple-system, sans-serif;
        }
        body{
            /* 扁平化柔和渐变背景，衬托玻璃效果 */
            background: linear-gradient(135deg, #f7f8fa 0%, #eef2f7 100%);
            padding:24px 16px;
            min-height: 100vh;
        }
        .wrap{
            max-width:1040px;
            margin:0 auto;
        }

        /* 液态玻璃卡片通用样式 */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-light);
            transition: all 0.24s ease;
        }
        .glass-card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }

        .header{
            padding:20px 28px;
            margin-bottom:22px;
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .header h2 {
            color: var(--text-dark);
            font-weight: 600;
            font-size: 1.35rem;
        }
        .header a {
            padding: 9px 16px;
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--main-color);
            border: 1px solid rgba(var(--main-color), 0.2);
            transition: 0.2s;
        }
        .header a:hover {
            background: rgba(var(--main-color), 0.08);
        }

        .menu {
            padding:28px;
            margin-bottom:18px;
        }
        .menu>a{
            display:inline-flex;
            align-items: center;
            gap: 8px;
            padding:12px 20px;
            background:var(--main-color);
            color:#fff;
            text-decoration:none;
            border-radius: var(--radius-md);
            margin:0 8px 10px 0;
            border: 1px solid rgba(255,255,255,0.15);
            box-shadow: 0 2px 8px rgba(var(--main-color), 0.2);
            transition: all 0.2s ease;
        }
        .menu>a:hover{
            filter: brightness(1.08);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(var(--main-color), 0.25);
        }

        .card{
            padding:28px;
            margin-bottom:16px;
        }
        .card h3 {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 14px;
            font-size: 1.15rem;
        }
        .card ul {
            line-height:2.3;
            color:var(--text-gray);
            padding-left: 6px;
        }
        .card li {
            list-style: none;
            position: relative;
            padding-left: 22px;
        }
        .card li::before {
            content: "✅";
            position: absolute;
            left: 0;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header glass-card">
        <h2>系统管理后台</h2>
        <a href="../dashboard.php">返回用户面板</a>
    </div>

    <div class="card menu glass-card">
        <a href="setting.php"><i class="fa fa-cog"></i>系统设置(存储限额、主题色)</a>
        <a href="user_manage.php"><i class="fa fa-users"></i>用户管理（封禁/解封）</a>
        <a href="ip_blacklist.php"><i class="fa fa-ban"></i>IP封禁管理</a>
    </div>

    <div class="card glass-card">
        <h3>平台说明</h3>
        <ul>
            <li>普通用户最大空间后台设置，管理员无容量限制</li>
            <li>支持单文件HTML/CSS/JS部署，支持ZIP整套项目部署</li>
            <li>自动生成随机字符串访问短链接</li>
            <li>防护XSS、SQL注入、IP黑名单拦截</li>
            <li>支持账号封禁解封，IP封禁解封</li>
        </ul>
    </div>

    <!-- CSRF隐藏域示例，子页面表单携带此token -->
    <input type="hidden" id="admin_csrf" value="<?=htmlSafe($csrfToken)?>">
</div>
</body>
</html>

