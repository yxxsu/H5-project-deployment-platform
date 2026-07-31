<?php
//dashboard.php
require __DIR__."/config.php";
// 必须开启session
if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF Token 生成（会话唯一）
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// 安全转义工具函数（替换不可靠的htmlSafe）
function escapeHtml(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function escapeJs(string $str): string {
    return json_encode($str, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
function escapeUrl(string $str): string {
    return urlencode($str);
}
// CSS颜色白名单校验
function safeCssColor(string $color): string {
    if (preg_match('/^#[0-9a-fA-F]{6}$|^[a-zA-Z0-9_-]+$/', $color)) {
        return escapeHtml($color);
    }
    return '#66cdaa'; // 默认安全色
}

// 登录校验
if(!isset($_SESSION['uid'])){
    header("Location: login.php");
    exit;
}
$uid = $_SESSION['uid'];
$db = getDB();

// 读取系统配置（无注入风险，常量前缀）
$smax = $db->query("SELECT cfg_value FROM ".DB_PREFIX."system_config WHERE cfg_key='max_space_mb'")->fetchColumn();
$themeColorRaw = $db->query("SELECT cfg_value FROM ".DB_PREFIX."system_config WHERE cfg_key='theme_color'")->fetchColumn();
$themeColor = safeCssColor($themeColorRaw);

$usedSize = getDirSize(getUserSpace($uid));
$isAdmin = isAdmin();
// 修复：超大数字替换为PHP最大整数，避免溢出
$limitMb = $isAdmin ? PHP_INT_MAX : (int)$smax;

// 获取已部署项目列表（预处理语句安全）
$listStmt = $db->prepare("SELECT * FROM ".DB_PREFIX."shortlink WHERE uid=? ORDER BY id DESC");
$listStmt->execute([$uid]);
$projectList = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// 安全协议拼接URL（修复HTTP_HOST污染）
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$serverHost = escapeHtml($_SERVER['SERVER_NAME']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>项目部署控制台</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root{
    --main-color:<?php echo htmlSafe($themeColor);?>;
    --main-light:#66cdaa;
    --main-glass: rgba(102, 205, 170, 0.25);
    --main-glass-solid: rgba(102, 205, 170, 0.85);
    --bg-color:#f5f5f5;
    --card-bg:rgba(255,255,255,0.65);
    --glass-border:rgba(255,255,255,0.4);
    --text-color:#222;
    --border-color:#e5e5e5;
    --input-bg:rgba(255,255,255,0.55);
    --table-header:rgba(247,247,247,0.6);
    --storage-bg:rgba(232, 245, 233,0.6);
    --storage-border:#c8e6c9;
    --shadow: 0 4px 16px rgba(0,0,0,0.07);
    --danger-color:#ef5350;
    --danger-glass:rgba(239, 83, 80, 0.25);
}
[data-theme="dark"]{
    --main-color:#66cdaa;
    --main-light:#66cdaa;
    --main-glass: rgba(102, 205, 170, 0.22);
    --main-glass-solid: rgba(102, 205, 170, 0.82);
    --bg-color:#121418;
    --card-bg:rgba(30, 34, 41, 0.55);
    --glass-border:rgba(255,255,255,0.08);
    --text-color:#e8e8e8;
    --border-color:#333842;
    --input-bg:rgba(44, 48, 56,0.45);
    --table-header:rgba(44, 48, 56,0.5);
    --storage-bg:rgba(26, 60, 42,0.45);
    --storage-border:#2c5c42;
    --shadow: 0 4px 16px rgba(0,0,0,0.25);
    --danger-color:#f47174;
    --danger-glass:rgba(244, 113, 116, 0.22);
}
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui;transition: background 0.35s ease, color 0.35s ease, border-color 0.35s ease;}
body{
    background: var(--bg-color);
    padding:16px;
    color: var(--text-color);
    min-height:100vh;
    scrollbar-width: thin;
    scrollbar-color: var(--main-color) transparent;
}
::-webkit-scrollbar{width:6px;height:6px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--main-color);border-radius:99px;opacity:0.6;}
::-webkit-scrollbar-thumb:hover{opacity:1;}

.wrap{max-width:1100px;margin:0 auto}
.header{
    background: var(--card-bg);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border:1px solid var(--glass-border);
    padding:16px 22px;
    border-radius:12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
    box-shadow: var(--shadow);
}
.header h1{font-size:20px;color:var(--text-color)}
.header a{
    padding:10px 16px;
    background: var(--main-glass-solid);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    color:#fff;
    text-decoration:none;
    border-radius:10px;
    border: 1px solid rgba(255,255,255,0.25);
    transition: transform 0.2s ease, filter 0.2s ease, background 0.3s;
    display:inline-block;
    box-shadow: 0 2px 10px rgba(102, 205, 170, 0.2);
}
.header a:hover{
    background: var(--main-color);
    filter: brightness(1.1);
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(102, 205, 170, 0.3);
}
.header a.btn-danger{
    background: var(--danger-color);
    box-shadow: 0 2px 10px var(--danger-glass);
}
.header a.btn-danger:hover{
    background: var(--danger-color);
    filter: brightness(1.15);
}

.card{
    background: var(--card-bg);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border:1px solid var(--glass-border);
    padding:20px;
    border-radius:14px;
    margin-bottom:18px;
    box-shadow: var(--shadow);
    transition: transform 0.24s ease, box-shadow 0.24s ease;
}
.card:hover{
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.09);
}
.title{font-size:17px;margin-bottom:14px;font-weight:bold;color:var(--text-color)}
.storage-info{
    padding:12px;
    background:var(--storage-bg);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-radius:8px;
    margin-bottom:15px;
    border:1px solid var(--storage-border);
}
textarea{
    width:100%;
    min-height:180px;
    padding:12px;
    border:1px solid var(--glass-border);
    border-radius:8px;
    font-family:monospace;font-size:14px;
    resize:vertical;
    background:var(--input-bg);
    backdrop-filter: blur(6px);
    color:var(--text-color);
    outline:none;
    transition: border 0.3s, box-shadow 0.3s;
}
textarea:focus{
    border-color:var(--main-color);
    box-shadow: 0 0 0 3px var(--main-glass);
}
textarea::placeholder,
input::placeholder{
    color: var(--text-color);
    opacity:0.45;
}
.row{display:flex;gap:14px;margin:12px 0}

button{
    padding:10px 20px;
    background: var(--main-glass-solid);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color:#ffffff;
    border: 1px solid rgba(255,255,255,0.28);
    border-radius:10px;
    cursor:pointer;
    transition: transform 0.2s ease, box-shadow 0.25s ease, background 0.3s;
    box-shadow: 0 3px 12px rgba(102, 205, 170, 0.18);
    font-size:14px;
    outline: none;
}
button:hover{
    background: var(--main-color);
    filter: brightness(1.08);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(102, 205, 170, 0.32);
}
button:active{
    transform: translateY(0px);
    box-shadow: 0 2px 8px rgba(102, 205, 170, 0.2);
}
button:disabled{
    opacity:0.6;
    cursor:not-allowed;
    transform:none !important;
    filter:none !important;
    box-shadow:none !important;
}
button.btn-danger{
    background: var(--danger-color);
    box-shadow:0 3px 12px var(--danger-glass);
}
button.btn-danger:hover{
    background: var(--danger-color);
    filter:brightness(1.1);
}
button.btn-sm{
    padding:5px 8px;
    font-size:12px;
    margin:2px;
}

input[type=text]{
    padding:10px;
    background:var(--input-bg);
    backdrop-filter: blur(6px);
    border:1px solid var(--glass-border);
    border-radius:8px;
    color:var(--text-color);
    width:100%;
    outline:none;
    transition: border 0.3s, box-shadow 0.3s;
}
input:focus{
    border-color:var(--main-color);
    box-shadow: 0 0 0 3px var(--main-glass);
}

.table-wrap{
    overflow-x:auto;
    margin-top:12px;
    border-radius:8px;
}
table{width:100%;border-collapse:collapse;min-width:820px;}
th,td{
    border:1px solid var(--glass-border);
    padding:11px;text-align:left;font-size:14px;
    color:var(--text-color);
    background: transparent;
}
th{background:var(--table-header);backdrop-filter: blur(4px);}
tr:hover td{
    background: rgba(102, 205, 170, 0.08);
}
.link-text a{
    color:var(--main-color);
    text-decoration:none;
    word-break:break-all;
}
.link-text a:hover{
    text-decoration:underline;
}

.file-input-hidden{
    display:none !important;
}
.file-select-row{
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin:8px 0;
}
.file-name-tip{
    color:var(--text-color);
    font-size:14px;
    max-width: 70%;
    white-space: nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.theme-switch-wrap{
    display:flex;
    align-items:center;
    gap:10px;
    margin-right:12px;
}
.switch {
    position: relative;
    display: inline-block;
    width: 48px;
    height: 24px;
}
.switch input {opacity: 0;width: 0;height: 0;}
.slider {
    position: absolute;
    cursor: pointer;
    top: 0;left: 0;right: 0;bottom: 0;
    background-color: #ccc;
    transition: .35s;
    border-radius: 24px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 18px;width: 18px;
    left:3px;bottom:3px;
    background-color: white;
    transition: .35s;
    border-radius: 50%;
}
input:checked + .slider {
    background-color: var(--main-color);
}
input:checked + .slider:before {
    transform: translateX(24px);
}

.empty-tip{
    text-align:center;
    padding:30px 10px;
    opacity:0.65;
}

.toast-box{
    position:fixed;
    z-index:9999;
    left:50%;
    top:24px;
    transform:translateX(-50%);
    padding:12px 22px;
    border-radius:10px;
    background:var(--card-bg);
    backdrop-filter: blur(10px);
    border:1px solid var(--glass-border);
    box-shadow:var(--shadow);
    color:var(--text-color);
    animation:toastIn 0.25s ease;
}
@keyframes toastIn{
    from{opacity:0;transform:translateX(-50%) translateY(-10px);}
    to{opacity:1;transform:translateX(-50%) translateY(0);}
}

/* 弹窗遮罩 */
.modal-mask{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.45);
    backdrop-filter: blur(4px);
    z-index:9990;
    display:flex;
    align-items:center;
    justify-content:center;
}
.modal-box{
    width:min(540px,92vw);
    max-height:85vh;
    overflow:auto;
    background:var(--card-bg);
    backdrop-filter: blur(14px);
    border:1px solid var(--glass-border);
    border-radius:14px;
    padding:20px;
    box-shadow:var(--shadow);
}
.modal-title{
    font-weight:bold;
    font-size:16px;
    margin-bottom:16px;
}
.modal-buttons{
    display:flex;
    gap:10px;
    justify-content:flex-end;
    margin-top:18px;
}
.file-list-item{
    padding:6px 8px;
    border-radius:6px;
    cursor:pointer;
}
.file-list-item:hover{
    background:var(--main-glass);
}

@media(max-width:768px){
    body{padding:12px;}
    .header{flex-direction:column;gap:12px;align-items:flex-start;}
    .header>div{width:100%;display:flex;flex-wrap:wrap;gap:8px;}
    .file-select-row{
        flex-direction:column;
        align-items:flex-start;
    }
    .file-name-tip{max-width:100%}
}
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1>H5项目部署控制台</h1>
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;">
            <div class="theme-switch-wrap">
                <i class="fa-solid fa-sun"></i>
                <label class="switch">
                    <input type="checkbox" id="themeToggle">
                    <span class="slider"></span>
                </label>
                <i class="fa-solid fa-moon"></i>
            </div>

            <?php if($isAdmin):?>
                <a href="./admin/index.php">进入管理后台</a>
            <?php endif;?>
            <a href="javascript:logout()">退出登录</a>
        </div>
    </div>
    <div class="card">
        <div class="storage-info">
            <i class="fa fa-hdd"></i> 存储空间：已使用 <?php echo $usedSize;?> MB / 限额 <?php echo $limitMb;?> MB
        </div>
        <div class="title">1.单文件部署 HTML / CSS / JS（代码编辑）</div>
        <div>
            <label>文件名（例如 index.html / style.css）</label>
            <input type="text" id="filename" placeholder="index.html" style="margin:8px 0;">
        </div>
        <div>
            <label>代码内容</label>
            <textarea id="code"></textarea>
        </div>
        <div class="row">
            <button onclick="saveFile()">保存并部署文件</button>
        </div>
    </div>

    <div class="card">
        <div class="title">2.上传单个文件部署（html/js/css/json/png等）</div>
        <div class="file-select-row">
            <input type="file" id="singlefile" class="file-input-hidden">
            <button onclick="$('#singlefile').click()">
                <i class="fas fa-folder-open"></i> 选择文件
            </button>
            <span class="file-name-tip" id="singleFileName">未选择文件</span>
        </div>
        <button onclick="uploadSingleFile()" style="margin-top:10px">上传单文件部署</button>
    </div>

    <div class="card">
        <div class="title">3.上传ZIP整套项目压缩包部署（根目录必须包含index.html）</div>
        <div class="file-select-row">
            <input type="file" id="zipfile" accept=".zip" class="file-input-hidden">
            <button onclick="$('#zipfile').click()">
                <i class="fas fa-file-archive"></i> 选择Zip压缩包
            </button>
            <span class="file-name-tip" id="zipFileName">未选择文件</span>
        </div>
        <button onclick="uploadZip()" style="margin-top:10px">上传压缩包部署</button>
    </div>
    <div class="card">
        <div class="title">已部署项目列表</div>
        <div class="table-wrap">
        <table>
            <tr>
                <th>ID</th>
                <th>访问链接</th>
                <th>项目目录</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
            <?php if(empty($projectList)): ?>
                <tr>
                    <td colspan="5" class="empty-tip">暂无部署项目，快去上传部署项目吧</td>
                </tr>
            <?php else: ?>
            <?php foreach ($projectList as $p):
                $linkUrl = "./api/link.php?url=".htmlSafe($p['rand_key']);
                $fullUrl = $_SERVER['HTTP_HOST'].$linkUrl;
            ?>
            <tr>
                <td><?php echo $p['id']?></td>
                <td class="link-text">
                    <a target="_blank" href="<?php echo $linkUrl;?>"><?php echo $fullUrl; ?></a>
                </td>
                <td><?php echo htmlSafe($p['project_path'])?></td>
                <td><?php echo $p['create_time']?></td>
                <td>
                    <button class="btn-sm" onclick="window.open('<?php echo $linkUrl;?>')"><i class="fas fa-external-link-alt"></i>预览</button>
                    <button class="btn-sm" onclick="copyUrl('<?php echo $fullUrl;?>')"><i class="fas fa-copy"></i>复制链接</button>
                    <button class="btn-sm" onclick="openRename(<?php echo $p['id'];?>,'<?php echo htmlSafe($p['project_path']) ?>')"><i class="fas fa-pen"></i>重命名</button>
                    <button class="btn-sm" onclick="openFileManager(<?php echo $p['id'];?>,'<?php echo htmlSafe($p['project_path']) ?>')"><i class="fas fa-folder"></i>文件管理</button>
                    <button class="btn-sm btn-danger" onclick="delProject(<?php echo $p['id'];?>,'<?php echo htmlSafe($p['project_path']) ?>')"><i class="fas fa-trash"></i>删除</button>
                </td>
            </tr>
            <?php endforeach;?>
            <?php endif; ?>
        </table>
        </div>
    </div>
</div>

<!-- 弹窗容器 -->
<div id="modalContainer"></div>

<script>
function toast(msg){
    $(".toast-box").remove();
    let tpl = `<div class="toast-box">${msg}</div>`;
    $("body").append(tpl);
    setTimeout(()=>{
        $(".toast-box").fadeOut(200,()=>$(this).remove());
    },2500);
}

const htmlDom = document.documentElement;
const toggleBtn = document.getElementById('themeToggle');
let saveTheme = localStorage.getItem('console_theme');
if(saveTheme === 'dark'){
    htmlDom.setAttribute('data-theme','dark');
    toggleBtn.checked = true;
}
toggleBtn.onchange = function(){
    if(this.checked){
        htmlDom.setAttribute('data-theme','dark');
        localStorage.setItem('console_theme','dark');
    }else{
        htmlDom.removeAttribute('data-theme');
        localStorage.setItem('console_theme','light');
    }
}

$("#singlefile").on("change",function(){
    let name = this.files.length>0 ? this.files[0].name : "未选择文件";
    $("#singleFileName").text(name);
})
$("#zipfile").on("change",function(){
    let name = this.files.length>0 ? this.files[0].name : "未选择文件";
    $("#zipFileName").text(name);
})

//复制链接
async function copyUrl(text){
    try{
        await navigator.clipboard.writeText(text);
        toast("链接已复制！");
    }catch(e){
        toast("复制失败，请手动复制");
    }
}

//打开重命名弹窗
function openRename(pid,oldPath){
    let modal = `
    <div class="modal-mask" onclick="closeModal(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <div class="modal-title">重命名项目目录</div>
            <input type="text" id="newDirName" value="${oldPath}" placeholder="目录名称，禁止中文/特殊符号">
            <div class="modal-buttons">
                <button type="button" onclick="closeModal()">取消</button>
                <button onclick="submitRename(${pid},'${oldPath}')">确认修改</button>
            </div>
        </div>
    </div>`;
    $("#modalContainer").html(modal);
}

//提交重命名
function submitRename(pid,oldPath){
    let newPath = $("#newDirName").val().trim();
    if(!newPath) return toast("目录名不能为空");
    $.post("api/api_rename_project.php",{id:pid,old_path:oldPath,new_path:newPath},res=>{
        if(res.code===1){
            toast("重命名成功");
            closeModal();
            setTimeout(()=>location.reload(),800);
        }else{
            toast(res.msg);
        }
    },"json");
}

//打开文件管理器
function openFileManager(pid,projectPath){
    $.post("api/api_file_list.php",{id:pid,path:projectPath},res=>{
        if(res.code!==1){
            return toast(res.msg);
        }
        let html = "";
        res.data.forEach(item=>{
            let icon = item.is_dir ? '<i class="fas fa-folder"></i>' : '<i class="fas fa-file"></i>';
            html += `<div class="file-list-item">${icon} ${item.name}</div>`;
        });
        let modal = `
        <div class="modal-mask" onclick="closeModal(event)">
            <div class="modal-box" onclick="event.stopPropagation()">
                <div class="modal-title">项目文件列表 - ${projectPath}</div>
                <div style="max-height:400px;overflow:auto;">${html}</div>
                <div class="modal-buttons">
                    <button onclick="closeModal()">关闭</button>
                </div>
            </div>
        </div>`;
        $("#modalContainer").html(modal);
    },"json");
}

//关闭弹窗
function closeModal(e){
    if(e && e.target !== e.currentTarget) return;
    $("#modalContainer").empty();
}

//删除项目
function delProject(pid,path){
    if(!confirm("⚠️警告：删除会物理删除服务器项目目录，无法恢复！确定删除？")){
        return;
    }
    $.post("api/api_del_project.php",{id:pid,path:path},res=>{
        if(res.code===1){
            toast("删除成功！");
            setTimeout(()=>location.reload(),800);
        }else{
            toast(res.msg);
        }
    },"json");
}

function logout(){
    if(confirm("确认退出登录？")){
        $.post("api/api_logout.php",{},()=>{location.href="login.php"})
    }
}

function saveFile(){
    let fn = $("#filename").val().trim();
    let cd = $("#code").val();
    if(!fn){toast("请填写文件名！");return;}
    $.post("api/api_upload.php",{filename:fn,code:cd},res=>{
        if(res.code===1){
            toast("保存成功！正在生成访问链接");
            $.post("api/api_gen_link.php",{path:res.data.path},linkRes=>{
                toast("部署成功！访问链接："+linkRes.data.url);
                setTimeout(()=>location.reload(),1200);
            })
        }else{
            toast(res.msg);
        }
    },"json")
}
function uploadSingleFile(){
    let fdata = new FormData();
    let file = $("#singlefile")[0].files[0];
    if(!file){toast("请选择要上传的单个文件");return;}
    fdata.append("single",file);
    $.ajax({
        url:"api/api_single_upload.php",
        type:"POST",
        data:fdata,
        processData:false,
        contentType:false,
        dataType:"json",
        success(res){
            if(res.code===1){
                toast("单文件上传部署成功！链接："+res.data.url);
                setTimeout(()=>location.reload(),1200);
            }else{
                toast(res.msg);
            }
        }
    })
}
function uploadZip(){
    let fdata = new FormData();
    let file = $("#zipfile")[0].files[0];
    if(!file){toast("请选择zip压缩包");return;}
    fdata.append("zip",file);
    $.ajax({
        url:"api/api_zip_deploy.php",
        type:"POST",
        data:fdata,
        processData:false,
        contentType:false,
        dataType:"json",
        success(res){
            if(res.code===1){
                toast("压缩包部署成功！链接："+res.data.url);
                setTimeout(()=>location.reload(),1200);
            }else{
                toast(res.msg);
            }
        }
    })
}
</script>
</body>
</html>
