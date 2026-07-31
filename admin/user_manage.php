<?php
//admin/user_manage.php
require_once __DIR__."/auth.php";
if(!isAdmin()) die("权限不足");
$db = getDB();

// 生成CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$userList = $db->query("SELECT * FROM ".DB_PREFIX."users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$themeColor = $db->query("SELECT cfg_value FROM ".DB_PREFIX."system_config WHERE cfg_key='theme_color'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>用户管理</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root{--main-color:<?php echo htmlSafe($themeColor);?>;}
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f4f4f5;padding:22px}
        .wrap{max-width:950px;margin:0 auto}
        .back{margin-bottom:16px}
        .back a{text-decoration:none;color:#444}
        .card{background:#fff;padding:24px;border-radius:8px}
        table{width:100%;border-collapse:collapse;margin-top:15px}
        th,td{border:1px solid #eee;padding:12px;text-align:left}
        th{background:#f7f7f7}
        .ban{background:#c83c3c;color:#fff;padding:6px 11px;border-radius:4px;border:none;cursor:pointer}
        .unban{background:#2c9947;color:#fff;padding:6px 11px;border-radius:4px;border:none;cursor:pointer}
    </style>
</head>
<body>
<div class="wrap">
    <div class="back">
        <a href="index.php">← 返回后台首页</a>
    </div>
    <div class="card">
        <h2>用户管理列表（支持封禁/解封账号）</h2>
        <table>
            <tr>
                <th>UID</th>
                <th>账号名称</th>
                <th>账号状态</th>
                <th>注册时间</th>
                <th>操作</th>
            </tr>
            <?php foreach ($userList as $u):?>
            <tr>
                <td><?php echo $u['id']?></td>
                <td><?php echo htmlSafe($u['username'])?></td>
                <td><?php echo $u['is_ban']==1?"<span style='color:#c52c2c'>已封禁</span>":"正常";?></td>
                <td><?php echo $u['create_time']?></td>
                <td>
                    <?php if($u['id'] !=1):?>
                        <form method="post" action="api/api_ban_user.php" style="display:inline;">
                            <input type="hidden" name="uid" value="<?php echo $u['id'];?>">
                            <input type="hidden" name="op" value="<?php echo $u['is_ban']==0?1:0;?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken;?>">
                            <?php if($u['is_ban'] ==0):?>
                                <button type="submit" class="ban">封禁账号</button>
                            <?php else:?>
                                <button type="submit" class="unban">解封账号</button>
                            <?php endif;?>
                        </form>
                    <?php else:?>
                        <span>超级管理员不可封禁</span>
                    <?php endif;?>
                </td>
            </tr>
            <?php endforeach;?>
        </table>
    </div>
</div>
</body>
</html>
