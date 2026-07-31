<?php
// index.php
// 开启输出缓冲，防止config.php空白输出导致header报错
ob_start();

if (!file_exists(__DIR__ . "/config.php")) {
    header("Location: install.php");
    ob_end_clean();
    exit;
}

require __DIR__ . "/config.php";

// ✅ 会话必须最优先启动
session_start();
// 会话安全加固：重新生成session id防止会话固定攻击
session_regenerate_id(true);

// ✅ 增加基础函数校验，防止config损坏报错
if (!function_exists('getDB') || !defined('DB_PREFIX')) {
    ob_end_clean();
    exit('配置文件损坏，请重新运行安装程序 <a href="install.php">前往安装</a>');
}

// ✅ 安全校验表前缀：只允许字母数字下划线，防止注入
if (!preg_match('/^[a-zA-Z0-9_]+$/', DB_PREFIX)) {
    ob_end_clean();
    exit('非法数据表前缀，请重新安装');
}

$db = getDB();
$uid = $_SESSION['uid'] ?? 0;

// ✅ 强制uid转为整型，过滤非数字会话ID
$uid = (int)$uid;

// 未登录跳转登录页
if ($uid <= 0) {
    header("Location: login.php");
    ob_end_clean();
    exit;
}

// 查询用户封禁状态（参数化查询无SQL注入风险）
$stmt = $db->prepare("SELECT is_ban FROM " . DB_PREFIX . "users WHERE id = ?");
$stmt->execute([$uid]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ 容错：用户不存在 OR 账号被封禁（强转整型判断，兼容PDO字符串返回值）
if (!$userInfo || (int)$userInfo['is_ban'] === 1) {
    session_unset();   // 清空当前会话变量
    session_destroy(); // 销毁服务端会话
    // ✅ 清除客户端session cookie，彻底失效旧会话
    setcookie(session_name(), '', time() - 3600, '/');
    http_response_code(403); // 返回403禁止访问状态码
    ob_end_clean();

    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>账号已封禁</title>
    <style>
        :root {
            --mint-50:  #f0fdfa;
            --mint-100: #ccfbef;
            --mint-200: #99f6e0;
            --mint-300: #5eeac8;
            --mint-400: #2dd4aa;
            --mint-500: #14b88e;
            --mint-600: #0d9670;
            --mint-700: #10785b;
            --mint-800: #125f4a;
            --mint-900: #134e3e;

            --gray-50:  #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;

            --bg:       var(--mint-50);
            --card-bg:  #ffffff;
            --primary:  var(--mint-500);
            --primary-hover: var(--mint-600);
            --text-main:    var(--gray-800);
            --text-sub:     var(--gray-500);
            --border:       var(--gray-200);
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 24px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.04);
            --shadow-lg: 0 12px 48px rgba(0,0,0,0.08);
            --radius: 20px;
            --radius-sm: 10px;
            --radius-full: 999px;
            --transition: 0.25s cubic-bezier(.4,0,.2,1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC",
                         "Hiragino Sans GB", "Microsoft YaHei", "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            position: relative;
        }

        /* ── 背景装饰 ── */
        .bg-decoration {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.18;
            animation: float 8s ease-in-out infinite alternate;
        }

        .bg-orb:nth-child(1) {
            width: 360px; height: 360px;
            background: var(--mint-300);
            top: -10%; left: -5%;
            animation-delay: 0s;
        }

        .bg-orb:nth-child(2) {
            width: 280px; height: 280px;
            background: var(--mint-200);
            bottom: -8%; right: -6%;
            animation-delay: -3s;
        }

        .bg-orb:nth-child(3) {
            width: 200px; height: 200px;
            background: var(--mint-400);
            top: 50%; left: 60%;
            animation-delay: -6s;
        }

        @keyframes float {
            0%   { transform: translate(0, 0) scale(1); }
            100% { transform: translate(20px, -30px) scale(1.12); }
        }

        /* ── 主卡片 ── */
        .card {
            position: relative;
            z-index: 1;
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            max-width: 480px;
            width: 92%;
            padding: 48px 40px 40px;
            text-align: center;
            animation: cardIn 0.55s cubic-bezier(.16,1,.3,1) both;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(0.96); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ── 图标区 ── */
        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--mint-100), var(--mint-200));
            margin-bottom: 28px;
            animation: iconPulse 2.6s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(20,184,142,0.25); }
            50%      { box-shadow: 0 0 0 16px rgba(20,184,142,0); }
        }

        .icon-wrapper svg {
            width: 40px;
            height: 40px;
            color: var(--mint-600);
        }

        /* ── 标题 ── */
        .title {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 12px;
            letter-spacing: -0.3px;
        }

        /* ── 响应式 ── */
        @media (max-width: 520px) {
            .card {
                padding: 36px 24px 32px;
            }

            .title { font-size: 22px; }

            .bg-orb:nth-child(1) { width: 220px; height: 220px; }
            .bg-orb:nth-child(2) { width: 180px; height: 180px; }
            .bg-orb:nth-child(3) { width: 120px; height: 120px; }
        }
    </style>
</head>
<body>

    <!-- 背景装饰 -->
    <div class="bg-decoration">
        <div class="bg-orb"></div>
        <div class="bg-orb"></div>
        <div class="bg-orb"></div>
    </div>

    <!-- 主卡片 -->
    <main class="card">

        <!-- 图标 -->
        <div class="icon-wrapper">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round">
                <!-- 盾牌 + 感叹号 -->
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                <line x1="12" y1="8" x2="12" y2="13"/>
                <circle cx="12" cy="16.5" r="0.6" fill="currentColor" stroke="none"/>
            </svg>
        </div>

        <!-- 标题 -->
        <h1 class="title">账号已被封禁</h1>
        <p>你的账号已限制访问，如需申诉请联系管理员，若配置异常请 <a href="install.php">重新安装</a></p>

    </main>

</body>
</html>';
    exit;
}

ob_end_clean();
// ✅ Location标准格式：冒号后增加空格
header("Location: dashboard.php");
exit;
?>
