<?php
//兼容低版本PHP 补充str_contains函数
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $haystack, $needle) !== false;
    }
}

// CSRF工具函数
function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_reg'])) {
        $_SESSION['csrf_reg'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_reg'];
}
function checkCsrfToken(string $inputToken): bool
{
    if (empty($_SESSION['csrf_reg'])) return false;
    $ret = hash_equals($_SESSION['csrf_reg'], $inputToken);
    // 验证成功销毁token（一次性token，提升安全）
    if ($ret) unset($_SESSION['csrf_reg']);
    return $ret;
}

require __DIR__."/config.php";
$msg = "";
$isSuccess = false;

if($_SERVER['REQUEST_METHOD'] === "POST"){
    // 1. CSRF防护 最先校验
    if(!checkCsrfToken(trim($_POST['csrf_token'] ?? ''))){
        $msg = "表单验证失败，请刷新页面重试";
    }else{
        $uname = trim($_POST['username'] ?? '');
        $pwd = trim($_POST['password'] ?? '');

        // 2. 输入白名单校验，禁止控制字符、空字节
        if(!preg_match('/^[a-zA-Z0-9_\u4e00-\u9fa5]{3,32}$/u', $uname)){
            $msg = "账号3-32位，仅允许字母、数字、下划线、中文";
        }
        // bcrypt 72字节限制，UTF8中文占用多字节，限制最大64字符
        elseif(strlen($pwd) < 4 || mb_strlen($pwd) > 64){
            $msg = "密码长度4~64位";
        }else{
            try {
                $db = getDB();
                $db->beginTransaction(); //开启事务，保证数据库与目录原子性

                // 移除【先查询】，直接INSERT，依靠数据库UNIQUE约束防止竞态条件
                $pw_hash = password_hash($pwd, PASSWORD_DEFAULT);
                $ins = $db->prepare("INSERT INTO ".DB_PREFIX."users(username,password)VALUES(?,?)");
                $ins->execute([$uname,$pw_hash]);
                $new_uid = (int)$db->lastInsertId();
                $userSpace = getUserSpace($new_uid);

                // 检测目录创建结果，失败回滚事务
                if(!mkdir($userSpace,0755,true)){
                    throw new Exception("用户空间目录创建失败");
                }
                $db->commit();
                // 注册成功使用302跳转，不使用前端JS跳转
                header('Location: login.php?notice=regok');
                exit;

            }catch(PDOException $e){
                $db->rollBack();
                // 捕获唯一约束冲突 23000 = duplicate entry
                if($e->getCode() === '23000'){
                    // 【账号枚举防护】统一模糊提示，不直接告知账号占用
                    $msg = "注册信息异常，请更换信息重试";
                }else{
                    // 数据库异常不对外暴露详情，生产环境建议写入日志
                    $msg = "服务器繁忙，请稍后再试";
                    // error_log("注册数据库异常：".$e->getMessage());
                }
            }catch(Exception $e){
                $db->rollBack();
                $msg = "创建用户目录失败，请联系管理员";
                // error_log("目录创建异常：".$e->getMessage());
            }
        }
    }
}
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>注册账号</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:system-ui}
        body{background:#f7f7f7;display:flex;align-items:center;justify-content:center;height:100vh}
        .reg-card{width:420px;background:#fff;padding:32px;border-radius:10px;box-shadow:0 3px 14px #00000017}
        h2{text-align:center;margin-bottom:26px;color:#333}
        .item{margin-bottom:16px}
        label{display:block;margin-bottom:5px;color:#555}
        input{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:5px;font-size:15px}
        .btn-reg{width:100%;padding:12px;border:none;background:#d47829;color:#fff;font-size:16px;border-radius:5px;margin-top:8px;cursor:pointer}
        .btn-reg:hover{background:#b76620}
        .tip{margin-top:16px;text-align:center}
        .tip a{color:#d47829;text-decoration:none}
        .msg{padding:10px;background:#e8f8e8;color:#247024;margin-bottom:14px;border-radius:4px;text-align:center}
        .err{padding:10px;background:#ffe8e8;color:#bb3333;margin-bottom:14px;border-radius:4px;text-align:center}
    </style>
</head>
<body>
<div class="reg-card">
    <h2>账号注册</h2>
    <?php if($msg !== ""): ?>
        <!-- 修复XSS风险：全部统一htmlspecialchars转义，不再区分成功/失败 -->
        <div class="err"><?= htmlspecialchars($msg,ENT_QUOTES) ?></div>
    <?php endif; ?>
    <form method="post">
        <!-- CSRF隐藏令牌 -->
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken,ENT_QUOTES) ?>">
        <div class="item">
            <label>设置账号（3-32位，字母/数字/下划线/中文）</label>
            <input name="username" required autocomplete="off">
        </div>
        <div class="item">
            <label>设置密码（4~64位）</label>
            <input type="password" name="password" required autocomplete="off">
        </div>
        <button class="btn-reg">注册</button>
    </form>
    <div class="tip">
        已有账号？<a href="login.php">返回登录</a>
    </div>
</div>
</body>
</html>
