<?php
//install.php
error_reporting(E_ALL ^ E_DEPRECATED);
session_start();
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$err = '';
$success = false;
if(file_exists(__DIR__.'/config.php')){
    echo "<script>alert('系统已经安装完成！请勿重复运行安装程序');location.href='index.php'</script>";
    exit;
}

//第二步表单提交处理（新增！！）
if($_SERVER['REQUEST_METHOD'] === 'POST' && $step ==2){
    $_SESSION['db_host'] = $_POST['db_host'] ?? '';
    $_SESSION['db_name'] = $_POST['db_name'] ?? '';
    $_SESSION['db_user'] = $_POST['db_user'] ?? '';
    $_SESSION['db_pwd'] = $_POST['db_pwd'] ?? '';
    $_SESSION['db_pre']  = trim($_POST['db_pre'] ?? 'deploy_');
    //跳转到第三步页面
    header("Location: install.php?step=3");
    exit;
}

//第三步安装处理
if($_SERVER['REQUEST_METHOD'] === 'POST' && $step ==3){
    $db_host = $_SESSION['db_host'] ?? '';
    $db_name = $_SESSION['db_name'] ?? '';
    $db_user = $_SESSION['db_user'] ?? '';
    $db_pwd = $_SESSION['db_pwd'] ?? '';
    $db_pre  = $_SESSION['db_pre'] ?? 'deploy_';

    $admin_user = trim($_POST['adminuser']);
    $admin_pwd  = trim($_POST['adminpwd']);
    $max_size = intval($_POST['max_space']);
    $theme_color = trim($_POST['theme_color'] ?? '#c97028');
    try{
        $pdo = new PDO("mysql:host={$db_host};port=3306;dbname={$db_name};charset=utf8mb4",$db_user,$db_pwd,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
        ]);
        //建数据表
        $sqls = [
"CREATE TABLE IF NOT EXISTS {$db_pre}users(
id INT PRIMARY KEY AUTO_INCREMENT,
username VARCHAR(80) NOT NULL UNIQUE,
password VARCHAR(255) NOT NULL,
is_ban TINYINT DEFAULT 0,
create_time DATETIME DEFAULT CURRENT_TIMESTAMP
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

"CREATE TABLE IF NOT EXISTS {$db_pre}shortlink(
id INT PRIMARY KEY AUTO_INCREMENT,
uid INT NOT NULL,
rand_key VARCHAR(64) NOT NULL UNIQUE,
project_path VARCHAR(255) NOT NULL,
create_time DATETIME DEFAULT CURRENT_TIMESTAMP
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

"CREATE TABLE IF NOT EXISTS {$db_pre}ip_black(
id INT PRIMARY KEY AUTO_INCREMENT,
ip VARCHAR(60) NOT NULL UNIQUE,
add_time DATETIME DEFAULT CURRENT_TIMESTAMP
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

"CREATE TABLE IF NOT EXISTS {$db_pre}system_config(
id INT PRIMARY KEY AUTO_INCREMENT,
cfg_key VARCHAR(64) NOT NULL UNIQUE,
cfg_value TEXT
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
        ];
        foreach ($sqls as $sql){
            $pdo->exec($sql);
        }
        //写入系统配置
        $stmt = $pdo->prepare("INSERT INTO {$db_pre}system_config(cfg_key,cfg_value)VALUES(?,?)");
        $stmt->execute(['max_space_mb',$max_size]);
        $stmt->execute(['theme_color',$theme_color]);
        //创建管理员账号 ID=1
        $hash_pwd = password_hash($admin_pwd,PASSWORD_DEFAULT);
        $stmt2 = $pdo->prepare("INSERT INTO {$db_pre}users(id,username,password)VALUES(1,?,?) ON DUPLICATE KEY UPDATE username=?,password=?");
        $stmt2->execute([$admin_user,$hash_pwd,$admin_user,$hash_pwd]);
        //创建space文件夹
        if(!is_dir(__DIR__.'/space')) mkdir(__DIR__.'/space',0755,true);
        if(!is_dir(__DIR__.'/space/1')) mkdir(__DIR__.'/space/1',0755,true);
        //生成config.php配置文件
        $config_text = '<?php
//自动生成配置文件请勿手动修改
define("DB_HOST","'.$db_host.'");
define("DB_NAME","'.$db_name.'");
define("DB_USER","'.$db_user.'");
define("DB_PASS","'.$db_pwd.'");
define("DB_PREFIX","'.$db_pre.'");
define("ROOT_PATH",__DIR__);
session_start();
function getDB(){
    try{
        $dbh = new PDO("mysql:host=".DB_HOST.";port=3306;dbname=".DB_NAME.";charset=utf8mb4",DB_USER,DB_PASS,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        return $dbh;
    }catch(Exception $e){
        die("数据库连接失败:".$e->getMessage());
    }
}
//XSS输出过滤
function htmlSafe($str){
    return htmlspecialchars($str,ENT_QUOTES,"UTF-8");
}
//获取用户文件夹路径
function getUserSpace($uid){
    return ROOT_PATH."/space/".$uid."/";
}
//计算文件夹占用空间MB
function getDirSize($dir){
    $size =0;
    if(!is_dir($dir)) return 0;
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $f){
        if($f->isFile()) $size += $f->getSize();
    }
    return round($size / 1024 /1024,2);
}
//随机短链字符串
function getRandStr($len=10){
    $chars="1234567890abcdefghijklmnopqrstuvwxyz";
    $str="";
    for($i=0;$i<$len;$i++){
        $str .= $chars[rand(0,strlen($chars)-1)];
    }
    return $str;
}
//校验是否管理员
function isAdmin(){
    if(!isset($_SESSION["uid"])) return false;
    return $_SESSION["uid"] == 1;
}
//IP封禁检测
function checkIpBan(){
    $ip = $_SERVER["REMOTE_ADDR"];
    $db = getDB();
    $st = $db->prepare("SELECT id FROM ".DB_PREFIX."ip_black WHERE ip=?");
    $st->execute([$ip]);
    if($st->rowCount()>0){
        die("IP已被封禁，禁止访问");
    }
}
checkIpBan();
?>';
        file_put_contents(__DIR__."/config.php",$config_text);
        //安装完成清除session
        unset($_SESSION['db_host'],$_SESSION['db_name'],$_SESSION['db_user'],$_SESSION['db_pwd'],$_SESSION['db_pre']);
        $success=true;
    }catch(Exception $e){
        $err = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装向导</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --main-color: #48c9b0;
            --main-dark: #39b39c;
            --bg-light: #f7fbfb;
            --glass-bg: rgba(255, 255, 255, 0.65);
            --glass-blur: blur(12px);
            --shadow-normal: 0 8px 30px rgba(72, 201, 176, 0.12);
        }
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft Yahei", system-ui, -apple-system, sans-serif;
        }
        body{
            background: var(--bg-light);
            padding: 48px 20px;
            min-height: 100vh;
            transition: background 0.3s;
        }
        body.texture-bg{
            background: linear-gradient(135deg,#f7fbfb 0%,#eaf7f4 100%);
        }

        /* 主容器 毛玻璃基础样式 */
        .install-box{
            max-width: 680px;
            margin: 0 auto;
            padding: 40px;
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        .install-box.glass-mode{
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            -webkit-backdrop-filter: var(--glass-blur);
            border: 1px solid rgba(255,255,255,0.8);
        }
        .install-box.no-glass{
            background: #ffffff;
        }
        .install-box.shadow-on{
            box-shadow: var(--shadow-normal);
        }
        .install-box.shadow-off{
            box-shadow: none;
        }

        .step-title{
            font-size: 26px;
            color: #2d3436;
            margin-bottom: 32px;
            text-align: center;
            font-weight: 600;
        }
        .step-bar{
            display: flex;
            margin-bottom: 36px;
            gap: 8px;
        }
        .step-item{
            flex: 1;
            text-align: center;
            padding: 12px 0;
            border-bottom: 3px solid #e4ecec;
            color: #869494;
            transition: all 0.3s ease;
            font-size: 15px;
        }
        .step-item.active{
            border-color: var(--main-color);
            color: var(--main-color);
            font-weight: 600;
        }
        .form-item{
            margin-bottom: 22px;
        }
        label{
            display: block;
            margin-bottom: 8px;
            color: #3c4a4a;
            font-weight: 500;
        }
        input{
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid #dde8e8;
            border-radius: 10px;
            font-size: 15px;
            background-color: rgba(252, 254, 254, 0.75);
            transition: 0.25s ease;
            outline: none;
        }
        input:focus{
            border-color: var(--main-color);
            box-shadow: 0 0 0 3px rgba(72, 201, 176, 0.15);
        }
        .btn{
            width: 100%;
            padding: 14px;
            border: none;
            background: var(--main-color);
            color: #ffffff;
            font-size: 16px;
            font-weight: 500;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 12px;
            transition: all 0.28s ease;
        }
        .btn.anim-enable:hover{
            background: var(--main-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(72, 201, 176, 0.25);
        }
        .btn.anim-disable:hover{
            background: var(--main-dark);
            transform: none;
            box-shadow: none;
        }
        .btn:active{
            transform: translateY(0px);
        }
        .error{
            padding: 14px 16px;
            background: #fef2f2;
            color: #dc3545;
            border-radius: 10px;
            margin-bottom: 18px;
            border-left: 4px solid #f87171;
        }
        .ok{
            padding: 30px 20px;
            background: rgba(240, 252, 249, 0.7);
            color: #107c69;
            border-radius: 12px;
            text-align: center;
            border:1px solid #cbf0e8;
        }
        .ok h3{
            margin:10px 0;
            font-size:22px;
        }
        .ok p{
            margin:6px 0;
        }
        h4{
            color:#2d3436;
            font-size:17px;
            margin-bottom:16px;
        }
        .env-list p{
            padding:8px 0;
            font-size:15px;
            color:#444;
        }

        /* ===== 设置悬浮按钮 ===== */
        .setting-float{
            position: fixed;
            right: 24px;
            bottom: 24px;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--main-color);
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:20px;
            cursor:pointer;
            box-shadow: 0 4px 14px rgba(72,201,176,0.3);
            z-index:998;
            transition:0.2s;
        }
        .setting-float:hover{
            background:var(--main-dark);
            transform:scale(1.05);
        }

        /* ===== 弹窗遮罩 ===== */
        .modal-mask{
            position:fixed;
            inset:0;
            background:rgba(0,0,0,0.35);
            display:none;
            align-items:center;
            justify-content:center;
            z-index:999;
            padding:16px;
        }
        .modal-wrap{
            width:100%;
            max-width:420px;
            border-radius:16px;
            padding:28px;
            background:rgba(255,255,255,0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border:1px solid rgba(255,255,255,0.9);
        }
        .modal-title{
            font-size:20px;
            font-weight:600;
            margin-bottom:24px;
            color:#2d3436;
            display:flex;
            justify-content:space-between;
            align-items:center;
        }
        .modal-close{
            cursor:pointer;
            font-size:22px;
            color:#888;
        }
        .switch-item{
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:14px 0;
            border-bottom:1px solid #e8efef;
        }
        .switch-item:last-child{
            border-bottom:none;
        }
        .switch-name{
            color:#333;
        }
        /* 自定义开关 */
        .switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left:3px;
            bottom:3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: var(--main-color);
        }
        input:checked + .slider:before {
            transform: translateX(22px);
        }
    </style>
</head>
<body class="">
<div class="install-box" id="mainBox">
    <h2 class="step-title">H5项目部署平台 - 安装向导</h2>
    <div class="step-bar">
        <div class="step-item <?php if($step==1)echo 'active'?>">1.环境检测</div>
        <div class="step-item <?php if($step==2)echo 'active'?>">2.数据库配置</div>
        <div class="step-item <?php if($step==3)echo 'active'?>">3.创建管理员</div>
    </div>
    <?php if($step ==1): ?>
    <div>
        <h4>环境检测清单</h4>
        <div class="env-list" style="margin:20px 0">
            <p><i class="fa fa-check" style="color:#279e37"></i> PHP版本 >=7.4 : <?php echo phpversion();?></p>
            <p><i class="fa fa-check" style="color:#279e37"></i> PDO_MYSQL扩展: <?php echo extension_loaded('pdo_mysql')?"已开启":"<span style='color:red'>未开启(必须开启)</span>" ?></p>
            <p><i class="fa fa-check" style="color:#279e37"></i> ZIP扩展: <?php echo extension_loaded('zip')?"已开启":"<span style='color:red'>未开启(压缩部署需要)</span>" ?></p>
            <p><i class="fa fa-check" style="color:#279e37"></i> space目录可写: <?php echo is_writable(__DIR__)?"✅可写入":"❌目录没有写入权限" ?></p>
        </div>
        <a href="install.php?step=2"><button class="btn" id="mainBtn">下一步</button></a>
    </div>
    <?php elseif($step ==2):?>
    <form method="post">
        <div class="form-item">
            <label>数据库地址</label>
            <input name="db_host" value="127.0.0.1" required>
        </div>
        <div class="form-item">
            <label>数据库名称</label>
            <input name="db_name" placeholder="提前建好数据库" required>
        </div>
        <div class="form-item">
            <label>数据库账号</label>
            <input name="db_user" required>
        </div>
        <div class="form-item">
            <label>数据库密码</label>
            <input name="db_pwd">
        </div>
        <div class="form-item">
            <label>数据表前缀</label>
            <input name="db_pre" value="deploy_">
        </div>
        <button class="btn" id="mainBtn">下一步</button>
    </form>
    <?php elseif($step ==3):?>
        <?php if($success):?>
            <div class="ok">
                <i class="fa fa-check-circle" style="font-size:36px;color:#48c9b0"></i>
                <h3>🎉安装成功！</h3>
                <p>配置文件 config.php 已生成</p>
                <p>管理员ID=1，可登录/admin进入后台管理</p>
                <a href="index.php"><button class="btn" id="mainBtn">进入系统首页</button></a>
            </div>
        <?php else: ?>
            <?php if(!empty($err)) echo '<div class="error">'.$err.'</div>'?>
            <form method="post">
                <div class="form-item">
                    <label>管理员账号</label>
                    <input name="adminuser" required>
                </div>
                <div class="form-item">
                    <label>管理员密码</label>
                    <input type="password" name="adminpwd" required>
                </div>
                <div class="form-item">
                    <label>普通用户存储空间限额(MB)</label>
                    <input type="number" name="max_space" value="100" required>
                </div>
                <div class="form-item">
                    <label>系统主题色(十六进制，禁止蓝/紫色)</label>
                    <input type="color" name="theme_color" value="#48c9b0">
                </div>
                <button class="btn" id="mainBtn">确认安装</button>
            </form>
        <?php endif;?>
    <?php endif;?>
</div>

<!-- 悬浮设置按钮 -->
<div class="setting-float">
    <i class="fas fa-cog"></i>
</div>

<!-- 设置弹窗 -->
<div class="modal-mask">
    <div class="modal-wrap">
        <div class="modal-title">
            界面效果设置
            <span class="modal-close"><i class="fas fa-times"></i></span>
        </div>
        <div class="switch-item">
            <span class="switch-name">毛玻璃磨砂效果</span>
            <label class="switch">
                <input type="checkbox" data-key="glass">
                <span class="slider"></span>
            </label>
        </div>
        <div class="switch-item">
            <span class="switch-name">按钮悬浮动画</span>
            <label class="switch">
                <input type="checkbox" data-key="animation">
                <span class="slider"></span>
            </label>
        </div>
        <div class="switch-item">
            <span class="switch-name">柔和阴影效果</span>
            <label class="switch">
                <input type="checkbox" data-key="shadow">
                <span class="slider"></span>
            </label>
        </div>
        <div class="switch-item">
            <span class="switch-name">渐变纹理背景</span>
            <label class="switch">
                <input type="checkbox" data-key="texture">
                <span class="slider"></span>
            </label>
        </div>
    </div>
</div>

<script>
$(function(){
    const $mask = $(".modal-mask");
    const $box = $("#mainBox");
    const $btn = $(".btn");
    const $body = $("body");

    // 默认配置
    let config = {
        glass:true,
        animation:true,
        shadow:true,
        texture:true
    };
    // 读取本地存储
    let local = localStorage.getItem("install_ui_config");
    if(local){
        config = JSON.parse(local);
    }

    // 应用设置
    function applySetting(){
        // 毛玻璃
        if(config.glass){
            $box.addClass("glass-mode").removeClass("no-glass");
        }else{
            $box.addClass("no-glass").removeClass("glass-mode");
        }
        // 动画
        if(config.animation){
            $btn.addClass("anim-enable").removeClass("anim-disable");
        }else{
            $btn.addClass("anim-disable").removeClass("anim-enable");
        }
        // 阴影
        if(config.shadow){
            $box.addClass("shadow-on").removeClass("shadow-off");
        }else{
            $box.addClass("shadow-off").removeClass("shadow-on");
        }
        // 纹理背景
        if(config.texture){
            $body.addClass("texture-bg");
        }else{
            $body.removeClass("texture-bg");
        }
        // 更新复选框状态
        $('input[data-key]').each(function(){
            let key = $(this).data("key");
            $(this).prop("checked", config[key]);
        })
    }
    applySetting();

    // 打开弹窗
    $(".setting-float").click(function(){
        $mask.css("display","flex");
    })
    // 关闭弹窗
    $(".modal-close, .modal-mask").click(function(e){
        if($(e.target).is(".modal-mask,.modal-close")){
            $mask.hide();
        }
    })

    // 切换开关事件
    $('input[data-key]').on("change",function(){
        let key = $(this).data("key");
        config[key] = $(this).prop("checked");
        localStorage.setItem("install_ui_config", JSON.stringify(config));
        applySetting();
    })
})
</script>
</body>
</html>
