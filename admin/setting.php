<?php
//admin/setting.php
require_once __DIR__."/auth.php";
if(!isAdmin()) die("权限不足");
$db = getDB();
$maxMb = $db->query("SELECT cfg_value FROM ".DB_PREFIX."system_config WHERE cfg_key='max_space_mb'")->fetchColumn();
$tColor = $db->query("SELECT cfg_value FROM ".DB_PREFIX."system_config WHERE cfg_key='theme_color'")->fetchColumn();
$msg = "";
if($_SERVER['REQUEST_METHOD']=="POST"){
    $newMax = intval($_POST['maxmb']);
    $newColor = trim($_POST['themecolor']);
    $upd = $db->prepare("UPDATE ".DB_PREFIX."system_config SET cfg_value=? WHERE cfg_key='max_space_mb'");
    $upd->execute([$newMax]);
    $upd2 = $db->prepare("UPDATE ".DB_PREFIX."system_config SET cfg_value=? WHERE cfg_key='theme_color'");
    $upd2->execute([$newColor]);
    $msg="✅配置保存成功";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>系统设置</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root{--main-color:<?php echo htmlSafe($tColor);?>;}
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f4f4f5;padding:22px}
        .wrap{max-width:700px;margin:0 auto}
        .back{margin-bottom:16px}
        .back a{text-decoration:none;color:#444}
        .card{background:#fff;padding:28px;border-radius:8px}
        .item{margin-bottom:20px}
        label{display:block;margin-bottom:7px;color:#444}
        input{width:100%;padding:11px;border:1px solid #ddd;border-radius:5px}
        button{padding:11px 22px;background:var(--main-color);border:none;color:#fff;border-radius:5px;cursor:pointer}
        .ok-tip{padding:10px;background:#e8f8e8;color:#277727;border-radius:5px;margin-bottom:15px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="back">
        <a href="index.php">← 返回后台首页</a>
    </div>
    <div class="card">
        <h2>系统参数设置</h2>
        <?php if($msg) echo "<div class='ok-tip'>{$msg}</div>"?>
        <form method="post">
            <div class="item">
                <label>普通用户存储空间上限(MB)</label>
                <input type="number" name="maxmb" value="<?php echo htmlSafe($maxMb)?>">
            </div>
            <div class="item">
                <label>全局主题颜色（禁止蓝色、紫色）</label>
                <input type="color" name="themecolor" value="<?php echo htmlSafe($tColor)?>">
            </div>
            <button type="submit">保存配置</button>
        </form>
    </div>
</div>
</body>
</html>
