<?php
// 检测配置文件
if(!file_exists(__DIR__."/config.php")){
    die("系统配置文件 config.php 缺失，请先完成安装！");
}
require __DIR__."/config.php";

// 内置HTML转义函数，避免htmlSafe未定义致命错误
if(!function_exists('htmlSafe')){
    function htmlSafe(string $str): string
    {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

// CSRF Token生成
if(empty($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$msg = "";
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    // CSRF校验
    $post_token = $_POST['csrf_token'] ?? '';
    if(!hash_equals($_SESSION['csrf_token'], $post_token)){
        $msg = "非法请求，请刷新页面重试";
    }else{
        $user = trim($_POST['username'] ?? '');
        $pwd = $_POST['password'] ?? ''; // 密码禁止trim，保留首尾空格

        // ======================
        // 【预留位置】可添加：IP限流、验证码校验、暴力破解拦截
        // ======================

        $db = getDB();
        $stmt = $db->prepare("SELECT id,password,is_ban FROM ".DB_PREFIX."users WHERE username=?");
        $stmt->execute([$user]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // 统一校验逻辑：账号存在 + 密码正确 + 未封禁
        $loginSuccess = false;
        if($row){
            if(!$row['is_ban'] && password_verify($pwd,$row['password'])){
                $loginSuccess = true;
            }
        }

        if($loginSuccess){
            // 防御会话固定攻击
            session_regenerate_id(true);
            $_SESSION['uid'] = $row['id'];

            // ======================
            // 【预留】登录成功日志写入
            // ======================
            header("Location:index.php");
            exit;
        }else{
            // 统一错误信息，杜绝用户名枚举、封禁账号探测
            $msg="账号或密码错误";
            // ======================
            // 【预留】登录失败日志写入
            // ======================
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>登录</title>
    <!-- CDN增加SRI完整性校验，防止资源被劫持 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"
            integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4="
            crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
          integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQEVbX3KhFXSQx1Uw=="
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:system-ui}
        body{background:#f7f7f7;display:flex;align-items:center;justify-content:center;height:100vh}
        .login-card{width:420px;background:#fff;padding:32px;border-radius:10px;box-shadow:0 3px 14px #00000017}
        h2{text-align:center;margin-bottom:26px;color:#333}
        .item{margin-bottom:16px}
        label{display:block;margin-bottom:5px;color:#555}
        input{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:5px;font-size:15px}
        .btn-login{width:100%;padding:12px;border:none;background:#d47829;color:#fff;font-size:16px;border-radius:5px;margin-top:8px;cursor:pointer}
        .btn-login:hover{background:#b76620}
        .tip{margin-top:16px;text-align:center}
        .tip a{color:#d47829;text-decoration:none}
        .err{padding:10px;background:#ffe8e8;color:#bb3333;margin-bottom:14px;border-radius:4px;text-align:center}
    </style>
</head>
<body>
<div class="login-card">
    <h2>平台登录</h2>
    <?php if($msg!="") echo "<div class='err'>".htmlSafe($msg)."</div>";?>
    <form method="post">
        <!-- CSRF隐藏令牌 -->
        <input type="hidden" name="csrf_token" value="<?=htmlSafe($csrf_token)?>">
        <div class="item">
            <label>账号</label>
            <input name="username" required autocomplete="username">
        </div>
        <div class="item">
            <label>密码</label>
            <input type="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn-login">登录</button>
    </form>
    <div class="tip">
        没有账号？<a href="register.php">立即注册</a>
    </div>
</div>
</body>
</html>
