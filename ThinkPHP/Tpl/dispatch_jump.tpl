<?php
    if(C('LAYOUT_ON')) {
        echo '{__NOLAYOUT__}';
    }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo isset($message) ? '操作成功' : '操作提示'; ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#f5f7fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Hiragino Sans GB','Microsoft YaHei',sans-serif;color:#333;display:flex;justify-content:center;align-items:center;min-height:100vh}
.msg-box{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);padding:40px;text-align:center;max-width:400px;width:90%;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.icon{font-size:48px;margin-bottom:16px}
.icon.ok{color:#52c41a}
.icon.fail{color:#ff4d4f}
.msg-text{font-size:16px;line-height:1.6;margin-bottom:20px;color:#333}
.msg-link{display:inline-block;padding:8px 24px;background:#1677ff;color:#fff;text-decoration:none;border-radius:6px;font-size:14px;transition:opacity .2s}
.msg-link:hover{opacity:.85}
.msg-link.err{background:#ff4d4f}
</style>
</head>
<body>
<div class="msg-box">
<?php if(isset($message)) {?>
<div class="icon ok">&#10004;</div>
<p class="msg-text"><?php echo($message); ?></p>
<a class="msg-link" id="href" href="<?php echo($jumpUrl); ?>">立即跳转</a>
<?php }else{?>
<div class="icon fail">&#10008;</div>
<p class="msg-text"><?php echo($error); ?></p>
<a class="msg-link err" id="href" href="<?php echo($jumpUrl); ?>">返回</a>
<?php }?>
</div>
<script>
(function(){
    var href = document.getElementById('href').href;
    var wait = <?php echo($waitSecond); ?>;
    if(wait <= 1) {
        location.href = href;
    } else {
        setTimeout(function(){ location.href = href; }, 800);
    }
})();
</script>
</body>
</html>
