<?php
//admin/ip_blacklist.php
require_once __DIR__."/auth.php";
if(!isAdmin()) die("权限不足");
$db = getDB();
$iplist = $db->query("SELECT * FROM ".DB_PREFIX."ip_black ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$themeColor = $db->query("SELECT cfg_value FROM ".DB_PREFIX."system_config WHERE cfg_key='theme_color'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>IP黑名单管理</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root{--main-color:<?php echo htmlSafe($themeColor);?>;}
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f4f4f5;padding:22px}
        .wrap{max-width:950px;margin:0 auto}
        .back{margin-bottom:16px}
        .back a{text-decoration:none;color:#444}
        .card{background:#fff;padding:24px;border-radius:8px}
        .add-item{display:flex;gap:10px;margin-bottom:20px}
        #ip_input{flex:1;padding:11px;border:1px solid #ddd;border-radius:5px}
        .btn-add{padding:11px 20px;background:var(--main-color);color:#fff;border:none;border-radius:5px;cursor:pointer}
        table{width:100%;border-collapse:collapse;margin-top:15px}
        th,td{border:1px solid #eee;padding:12px;text-align:left}
        th{background:#f7f7f7}
        .btn-del{background:#c83c3c;color:#fff;padding:6px 11px;border-radius:4px;border:none;cursor:pointer}
    </style>
</head>
<body>
<div class="wrap">
    <div class="back">
        <a href="index.php">← 返回后台首页</a>
    </div>
    <div class="card">
        <h2>IP黑名单管理（封禁/解封IP）</h2>
        <div class="add-item" style="margin-top:15px">
            <input id="ip_input" placeholder="输入需要封禁的IP地址，例如：192.168.1.1">
            <button class="btn-add" onclick="banIp()">添加封禁IP</button>
        </div>
        <table>
            <tr>
                <th>ID</th>
                <th>封禁IP地址</th>
                <th>添加时间</th>
                <th>操作</th>
            </tr>
            <?php foreach ($iplist as $iprow):?>
            <tr>
                <td><?php echo $iprow['id']?></td>
                <td><?php echo htmlSafe($iprow['ip'])?></td>
                <td><?php echo $iprow['add_time']?></td>
                <td>
                    <button class="btn-del" onclick="unBanIp(<?php echo $iprow['id']?>)">解除封禁</button>
                </td>
            </tr>
            <?php endforeach;?>
        </table>
    </div>
</div>
<script>
function banIp(){
    let ip = $("#ip_input").val().trim();
    if(ip === ""){
        alert("请输入IP地址");
        return;
    }
    $.post("api/api_ban_ip.php",{act:"add",ip:ip},res=>{
        alert(res.msg);
        location.reload();
    },"json")
}
function unBanIp(id){
    if(!confirm("确定解除该IP封禁？")) return;
    $.post("api/api_ban_ip.php",{act:"del",id:id},res=>{
        alert(res.msg);
        location.reload();
    },"json")
}
</script>
</body>
</html>

