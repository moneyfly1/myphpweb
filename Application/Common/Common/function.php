<?php
header("Content-type:text/html;charset=utf-8");
function loadEnv($envFile = null) {
    if ($envFile === null) {
        // 使用更可靠的方法计算项目根目录
        $envFile = dirname(dirname(dirname(__DIR__))) . '/.env';
    }
    if (!file_exists($envFile)) {
        return false;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'\'');
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
    return true;
}

function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        $value = isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }
    if (is_string($value)) {
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }
    }
    return $value;
}

loadEnv();
function p($data) {
    $str = '<pre style="display: block;padding: 9.5px;margin: 44px 0 0 0;font-size: 13px;line-height: 1.42857;color: #333;word-break: break-all;word-wrap: break-word;background-color: #F5F5F5;border: 1px solid #CCC;border-radius: 4px;">';
    if (is_bool($data)) {
        $show_data = $data ? 'true' : 'false';
    } elseif (is_null($data)) {
        $show_data = 'null';
    } else {
        $show_data = print_r($data, true);
    }
    $str .= $show_data;
    $str .= '</pre>';
    echo $str;
}

/**
 * app 图片上传
 * @return string 上传后的图片名
 */
function app_upload_image($path,$maxSize=52428800){
    ini_set('max_execution_time', '0');
    // 去除两边的/
    $path=trim($path,'.');
    $path=trim($path,'/');
    $config=array(
        'rootPath'  =>'./',         //文件上传保存的根路径
        'savePath'  =>'./'.$path.'/',   
        'exts'      => array('jpg', 'gif', 'png', 'jpeg','bmp'),
        'maxSize'   => $maxSize,
        'autoSub'   => true,
        );
    $upload = new \Think\Upload($config);// 实例化上传类
    $info = $upload->upload();
    if($info) {
        foreach ($info as $k => $v) {
            $data[]=trim($v['savepath'],'.').$v['savename'];
        }
        return $data;
    }
}

/**
 * 实例化阿里云oos
 * @return object 实例化得到的对象
 */
function new_oss(){
    vendor('Alioss.autoload');
    $config=array(
        'KEY_ID' => env('ALIOSS_KEY_ID'),
        'KEY_SECRET' => env('ALIOSS_KEY_SECRET'),
        'END_POINT' => env('ALIOSS_END_POINT'),
        'BUCKET' => env('ALIOSS_BUCKET')
    );
    $oss=new \OSS\OssClient($config['KEY_ID'],$config['KEY_SECRET'],$config['END_POINT']);
    return $oss;
}

/**
 * 上传文件到oss并删除本地文件
 * @param  string $path 文件路径
 * @return bollear      是否上传
 */
function oss_upload($path){
    // 获取bucket名称
    $bucket=env('ALIOSS_BUCKET');
    // 先统一去除左侧的.或者/ 再添加./
    $oss_path=ltrim($path,'./');
    $path='./'.$oss_path;
    if (file_exists($path)) {
        // 实例化oss类
        $oss=new_oss();
        // 上传到oss    
        $oss->uploadFile($bucket,$oss_path,$path);
        // 如需上传到oss后 自动删除本地的文件 则删除下面的注释 
        // unlink($path);
        return true;
    }
    return false;
}

/**
 * 删除oss上指定文件
 * @param  string $object 文件路径 例如删除 /Public/README.md文件  传Public/README.md 即可
 */
function oss_delet_object($object){
    // 实例化oss类
    $oss=new_oss();
    // 获取bucket名称
    $bucket=env('ALIOSS_BUCKET');
    $test=$oss->deleteObject($bucket,$object);
}

/**
 * app 视频上传
 * @return string 上传后的视频名
 */
function app_upload_video($path,$maxSize=52428800){
    ini_set('max_execution_time', '0');
    // 去除两边的/
    $path=trim($path,'.');
    $path=trim($path,'/');
    $config=array(
        'rootPath'  =>'./',         //文件上传保存的根路径
        'savePath'  =>'./'.$path.'/',   
        'exts'      => array('mp4','avi','3gp','rmvb','gif','wmv','mkv','mpg','vob','mov','flv','swf','mp3','ape','wma','aac','mmf','amr','m4a','m4r','ogg','wav','wavpack'),
        'maxSize'   => $maxSize,
        'autoSub'   => true,
        );
    $upload = new \Think\Upload($config);// 实例化上传类
    $info = $upload->upload();
    if($info) {
        foreach ($info as $k => $v) {
            $data[]=trim($v['savepath'],'.').$v['savename'];
        }
        return $data;
    }
}

/**
 * 返回文件格式
 * @param  string $str 文件名
 * @return string      文件格式
 */
function file_format($str){
    // 取文件后缀名
    $str=strtolower(pathinfo($str, PATHINFO_EXTENSION));
    // 图片格式
    $image=array('webp','jpg','png','ico','bmp','gif','tif','pcx','tga','bmp','pxc','tiff','jpeg','exif','fpx','svg','psd','cdr','pcd','dxf','ufo','eps','ai','hdri');
    // 视频格式
    $video=array('mp4','avi','3gp','rmvb','gif','wmv','mkv','mpg','vob','mov','flv','swf','mp3','ape','wma','aac','mmf','amr','m4a','m4r','ogg','wav','wavpack');
    // 压缩格式
    $zip=array('rar','zip','tar','cab','uue','jar','iso','z','7-zip','ace','lzh','arj','gzip','bz2','tz');
    // 文档格式
    $text=array('exe','doc','ppt','xls','wps','txt','lrc','wfs','torrent','html','htm','java','js','css','less','php','pdf','pps','host','box','docx','word','perfect','dot','dsf','efe','ini','json','lnk','log','msi','ost','pcs','tmp','xlsb');
    // 匹配不同的结果
    switch ($str) {
        case in_array($str, $image):
            return 'image';
            break;
        case in_array($str, $video):
            return 'video';
            break;
        case in_array($str, $zip):
            return 'zip';
            break;
        case in_array($str, $text):
            return 'text';
            break;
        default:
            return 'image';
            break;
    }
}

/**
 * 发送友盟推送消息
 * @param  integer  $uid   用户id
 * @param  string   $title 推送的标题
 * @return boolear         是否成功
 */
/**
 * 返回用户id
 * @return integer 用户id
 */
function get_uid(){
    return $_SESSION['user']['id'];
}

/**
 * 返回iso、Android、ajax的json格式数据
 * @param  array  $data           需要发送到前端的数据
 * @param  string  $error_message 成功或者错误的提示语
 * @param  integer $error_code    状态码： 0：成功  1：失败
 * @return string                 json格式的数据
 */
function ajax_return($data='',$error_message='成功',$error_code=1){
    $all_data=array(
        'error_code'=>$error_code,
        'error_message'=>$error_message,
        );
    if ($data!=='') {
        $all_data['data']=$data;
        // app 禁止使用和为了统一字段做的判断
        $reserved_words=array('id','title','price','product_title','product_id','product_category','product_number');
        foreach ($reserved_words as $k => $v) {
            if (array_key_exists($v, $data)) {
                echo 'app不允许使用【'.$v.'】这个键名 —— 此提示是function.php 中的ajax_return函数返回的';
                die;
            }
        }
    }
    // 如果是ajax或者app访问；则返回json数据 pc访问直接p出来
    echo json_encode($all_data);
    exit(0);
}

/**
 * 获取完整网络连接
 * @param  string $path 文件路径
 * @return string       http连接
 */
function get_url($path){
    // 如果是空；返回空
    if (empty($path)) {
        return '';
    }
    // 如果已经有http直接返回
    if (strpos($path, 'http://')!==false) {
        return $path;
    }
    // 判断是否使用了oss
    $alioss=array(
        'KEY_ID' => env('ALIOSS_KEY_ID'),
        'KEY_SECRET' => env('ALIOSS_KEY_SECRET'),
        'END_POINT' => env('ALIOSS_END_POINT'),
        'BUCKET' => env('ALIOSS_BUCKET')
    );
    if (empty($alioss['KEY_ID'])) {
        return 'http://'.$_SERVER['HTTP_HOST'].$path;
    }else{
        return 'http://'.$alioss['BUCKET'].'.'.$alioss['END_POINT'].$path;
    }
}

/**
 * 检测是否登录
 * @return boolean 是否登录
 */
function check_login(){
    if (!empty($_SESSION['user']['id'])){
        return true;
    }else{
        return false;
    }
}

/**
 * 检测前台是否登录
 * @return boolean 是否登录
 */
function check_user_login(){
    if (!empty($_SESSION['users']['id'])){
        return true;
    }else{
        return false;
    }
}

/**
 * 根据配置项获取对应的key和secret
 * @return array key和secret
 */
/**
 * 删除指定的标签和内容
 * @param array $tags 需要删除的标签数组
 * @param string $str 数据源
 * @param string $content 是否删除标签内的内容 0保留内容 1不保留内容
 * @return string
 */
function strip_html_tags($tags,$str,$content=0){
    if($content){
        $html=array();
        foreach ($tags as $tag) {
            $html[]='/(<'.$tag.'.*?>[\s|\S]*?<\/'.$tag.'>)/';
        }
        $data=preg_replace($html,'',$str);
    }else{
        $html=array();
        foreach ($tags as $tag) {
            $html[]="/(<(?:\/".$tag."|".$tag.")[^>]*>)/i";
        }
        $data=preg_replace($html, '', $str);
    }
    return $data;
}

/**
 * 传递ueditor生成的内容获取其中图片的路径
 * @param  string $str 含有图片链接的字符串
 * @return array       匹配的图片数组
 */
function get_ueditor_image_path($str){
    $preg='/\/Upload\/image\/u(m)?editor\/\d*\/\d*\.[jpg|jpeg|png|bmp]*/i';
    preg_match_all($preg, $str,$data);
    return current($data);
}

/**
 * 字符串截取，支持中文和其他编码
 * @param string $str 需要转换的字符串
 * @param string $start 开始位置
 * @param string $length 截取长度
 * @param string $suffix 截断显示字符
 * @param string $charset 编码格式
 * @return string
 */
function re_substr($str, $start, $length, $suffix=true, $charset="utf-8") {
    if(function_exists("mb_substr"))
        $slice = mb_substr($str, $start, $length, $charset);
    elseif(function_exists('iconv_substr')) {
        $slice = iconv_substr($str,$start,$length,$charset);
    }else{
        $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk']  = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("",array_slice($match[0], $start, $length));
    }
    $omit=mb_strlen($str) >=$length ? '...' : '';
    return $suffix ? $slice.$omit : $slice;
}

// 设置验证码
function show_verify($config=''){
    if($config==''){
        $config=array(
            'codeSet'=>'1234567890',
            'fontSize'=>30,
            'useCurve'=>false,
            'imageH'=>60,
            'imageW'=>240,
            'length'=>4,
            'fontttf'=>'4.ttf',
            );
    }
    $verify=new \Think\Verify($config);
    return $verify->entry();
}

// 检测验证码
function check_verify($code){
    $verify=new \Think\Verify();
    return $verify->check($code);
}

/**
 * 取得根域名
 * @param type $domain 域名
 * @return string 返回根域名
 */
function get_url_to_domain($domain) {
    $re_domain = '';
    $domain_postfix_cn_array = array("com", "net", "org", "gov", "edu", "com.cn", "cn");
    $array_domain = explode(".", $domain);
    $array_num = count($array_domain) - 1;
    if ($array_domain[$array_num] == 'cn') {
        if (in_array($array_domain[$array_num - 1], $domain_postfix_cn_array)) {
            $re_domain = $array_domain[$array_num - 2] . "." . $array_domain[$array_num - 1] . "." . $array_domain[$array_num];
        } else {
            $re_domain = $array_domain[$array_num - 1] . "." . $array_domain[$array_num];
        }
    } else {
        $re_domain = $array_domain[$array_num - 1] . "." . $array_domain[$array_num];
    }
    return $re_domain;
}

/**
 * 按符号截取字符串的指定部分
 * @param string $str 需要截取的字符串
 * @param string $sign 需要截取的符号
 * @param int $number 如是正数以0为起点从左向右截  负数则从右向左截
 * @return string 返回截取的内容
 */
/*  示例
    $str='123/456/789';
    cut_str($str,'/',0);  返回 123
    cut_str($str,'/',-1);  返回 789
    cut_str($str,'/',-2);  返回 456
    具体参考 http://www.baijunyao.com/index.php/Home/Index/article/aid/18
*/
function cut_str($str,$sign,$number){
    $array=explode($sign, $str);
    $length=count($array);
    if($number<0){
        $new_array=array_reverse($array);
        $abs_number=abs($number);
        if($abs_number>$length){
            return 'error';
        }else{
            return $new_array[$abs_number-1];
        }
    }else{
        if($number>=$length){
            return 'error';
        }else{
            return $array[$number];
        }
    }
}

/**
 * 发送邮件
 * @param  string $address 需要发送的邮箱地址 发送给多个地址需要写成数组形式
 * @param  string $subject 标题
 * @param  string $content 内容
 * @return boolean       是否成功
 */
function send_email($address,$subject,$content){
    $email_smtp=env('EMAIL_SMTP') ?: env('MAIL_HOST');
    $email_username=env('EMAIL_USERNAME') ?: env('MAIL_USER');
    $email_password=env('EMAIL_PASSWORD') ?: env('MAIL_PASS');
    $email_from_name=env('EMAIL_FROM_NAME');
    $email_smtp_secure=env('EMAIL_SMTP_SECURE') ?: env('MAIL_SECURE');
    $email_port=env('EMAIL_PORT') ?: env('MAIL_PORT');
    if(empty($email_smtp) || empty($email_username) || empty($email_password) || empty($email_from_name)){
        return array("error"=>1,"message"=>'邮箱配置不完整');
    }
    require_once './ThinkPHP/Library/Org/Nx/class.phpmailer.php';
    require_once './ThinkPHP/Library/Org/Nx/class.smtp.php';
    $phpmailer=new \Phpmailer();
    // 设置PHPMailer使用SMTP服务器发送Email
    $phpmailer->IsSMTP();
    // 设置设置smtp_secure
    $phpmailer->SMTPSecure=$email_smtp_secure;
    // 设置port
    $phpmailer->Port=$email_port;
    // 设置为html格式
    $phpmailer->IsHTML(true);
    // 设置邮件的字符编码'
    $phpmailer->CharSet='UTF-8';
    // 设置SMTP服务器。
    $phpmailer->Host=$email_smtp;
    // 设置为"需要验证"
    $phpmailer->SMTPAuth=true;
    // 设置用户名
    $phpmailer->Username=$email_username;
    // 设置密码
    $phpmailer->Password=$email_password;
    // 设置邮件头的From字段。
    $phpmailer->From=$email_username;
    // 设置发件人名字
    $phpmailer->FromName=$email_from_name;
    // 添加收件人地址，可以多次使用来添加多个收件人
    if(is_array($address)){
        foreach($address as $addressv){
            $phpmailer->AddAddress($addressv);
        }
    }else{
        $phpmailer->AddAddress($address);
    }
    // 设置邮件标题
    $phpmailer->Subject=$subject;
    // 设置邮件正文
    $phpmailer->Body=$content;
    // 发送邮件。
    if(!$phpmailer->Send()) {
        $phpmailererror=$phpmailer->ErrorInfo;
        return array("error"=>1,"message"=>$phpmailererror);
    }else{
        return array("error"=>0);
    }
}

/**
 * 获取一定范围内的随机数字
 * 跟rand()函数的区别是 位数不足补零 例如
 * rand(1,9999)可能会得到 465
 * rand_number(1,9999)可能会得到 0465  保证是4位的
 * @param integer $min 最小值
 * @param integer $max 最大值
 * @return string
 */
function rand_number ($min=1, $max=9999) {
    return sprintf("%0".strlen($max)."d", mt_rand($min,$max));
}

/**
 * 生成一定数量的随机数，并且不重复
 * @param integer $number 数量
 * @param string $len 长度
 * @param string $type 字串类型
 * 0 字母 1 数字 其它 混合
 * @return string
 */
function build_count_rand ($number,$length=4,$mode=1) {
    if($mode==1 && $length<strlen($number) ) {
        //不足以生成一定数量的不重复数字
        return false;
    }
    $rand   =  array();
    for($i=0; $i<$number; $i++) {
        $rand[] = rand_string($length,$mode);
    }
    $unqiue = array_unique($rand);
    if(count($unqiue)==count($rand)) {
        return $rand;
    }
    $count   = count($rand)-count($unqiue);
    for($i=0; $i<$count*3; $i++) {
        $rand[] = rand_string($length,$mode);
    }
    $rand = array_slice(array_unique ($rand),0,$number);
    return $rand;
}

/**
 * 生成不重复的随机数
 * @param  int $start  需要生成的数字开始范围
 * @param  int $end 结束范围
 * @param  int $length 需要生成的随机数个数
 * @return array       生成的随机数
 */
function get_rand_number($start=1,$end=10,$length=4){
    $connt=0;
    $temp=array();
    while($connt<$length){
        $temp[]=rand($start,$end);
        $data=array_unique($temp);
        $connt=count($data);
    }
    sort($data);
    return $data;
}

/**
 * 实例化page类
 * @param  integer  $count 总数
 * @param  integer  $limit 每页数量
 * @return subject       page类
 */
function new_page($count,$limit=10){
    return new \Org\Nx\Page($count,$limit);
}

/**
 * 获取分页数据
 * @param  subject  $model  model对象
 * @param  array    $map    where条件
 * @param  string   $order  排序规则
 * @param  integer  $limit  每页数量
 * @return array            分页数据
 */
function get_page_data($model,$map,$order='',$limit=10){
    $count=$model
        ->where($map)
        ->count();
    $page=new_page($count,$limit);
    // 获取分页数据
    $list=$model
            ->where($map)
            ->order($order)
            ->limit($page->firstRow.','.$page->listRows)
            ->select();
    $data=array(
        'data'=>$list,
        'page'=>$page->show()
        );
    return $data;
}

/**
 * 处理post上传的文件；并返回路径
 * @param  string $path    字符串 保存文件路径示例： /Upload/image/
 * @param  string $format  文件格式限制
 * @param  string $maxSize 允许的上传文件最大值 52428800
 * @return array           返回ajax的json格式数据
 */
function post_upload($path='file',$format='empty',$maxSize='52428800'){
    ini_set('max_execution_time', '0');
    // 去除两边的/
    $path=trim($path,'/');
    // 添加Upload根目录
    $path=strtolower(substr($path, 0,6))==='upload' ? ucfirst($path) : 'Upload/'.$path;
    // 上传文件类型控制
    $ext_arr= array(
            'image' => array('gif', 'jpg', 'jpeg', 'png', 'bmp'),
            'photo' => array('jpg', 'jpeg', 'png'),
            'flash' => array('swf', 'flv'),
            'media' => array('swf', 'flv', 'mp3', 'wav', 'wma', 'wmv', 'mid', 'avi', 'mpg', 'asf', 'rm', 'rmvb'),
            'file' => array('doc', 'docx', 'xls', 'xlsx', 'ppt', 'htm', 'html', 'txt', 'zip', 'rar', 'gz', 'bz2','pdf')
        );
    if(!empty($_FILES)){
        // 上传文件配置
        $config=array(
                'maxSize'   =>  $maxSize,       //   上传文件最大为50M
                'rootPath'  =>  './',           //文件上传保存的根路径
                'savePath'  =>  './'.$path.'/',         //文件上传的保存路径（相对于根路径）
                'saveName'  =>  array('uniqid',''),     //上传文件的保存规则，支持数组和字符串方式定义
                'autoSub'   =>  true,                   //  自动使用子目录保存上传文件 默认为true
                'exts'    =>    isset($ext_arr[$format])?$ext_arr[$format]:'',
            );
        // 实例化上传
        $upload=new \Think\Upload($config);
        // 调用上传方法
        $info=$upload->upload();
        $data=array();
        if(!$info){
            // 返回错误信息
            $error=$upload->getError();
            $data['error_info']=$error;
            return $data;
        }else{
            // 返回成功信息
            foreach($info as $file){
                $data['name']=trim($file['savepath'].$file['savename'],'.');
                return $data;
            }               
        }
    }
}

/**
 * 上传文件类型控制   此方法仅限ajax上传使用
 * @param  string   $path    字符串 保存文件路径示例： /Upload/image/
 * @param  string   $format  文件格式限制
 * @param  integer  $maxSize 允许的上传文件最大值 52428800
 * @return booler       返回ajax的json格式数据
 */
function upload($path='file',$format='empty',$maxSize='52428800'){
    ini_set('max_execution_time', '0');
    // 去除两边的/
    $path=trim($path,'/');
    // 添加Upload根目录
    $path=strtolower(substr($path, 0,6))==='upload' ? ucfirst($path) : 'Upload/'.$path;
    // 上传文件类型控制
    $ext_arr= array(
            'image' => array('gif', 'jpg', 'jpeg', 'png', 'bmp'),
            'photo' => array('jpg', 'jpeg', 'png'),
            'flash' => array('swf', 'flv'),
            'media' => array('swf', 'flv', 'mp3', 'wav', 'wma', 'wmv', 'mid', 'avi', 'mpg', 'asf', 'rm', 'rmvb'),
            'file' => array('doc', 'docx', 'xls', 'xlsx', 'ppt', 'htm', 'html', 'txt', 'zip', 'rar', 'gz', 'bz2','pdf')
        );
    if(!empty($_FILES)){
        // 上传文件配置
        $config=array(
                'maxSize'   =>  $maxSize,       //   上传文件最大为50M
                'rootPath'  =>  './',           //文件上传保存的根路径
                'savePath'  =>  './'.$path.'/',         //文件上传的保存路径（相对于根路径）
                'saveName'  =>  array('uniqid',''),     //上传文件的保存规则，支持数组和字符串方式定义
                'autoSub'   =>  true,                   //  自动使用子目录保存上传文件 默认为true
                'exts'    =>    isset($ext_arr[$format])?$ext_arr[$format]:'',
            );
        // 实例化上传
        $upload=new \Think\Upload($config);
        // 调用上传方法
        $info=$upload->upload();
        $data=array();
        if(!$info){
            // 返回错误信息
            $error=$upload->getError();
            $data['error_info']=$error;
            echo json_encode($data);
        }else{
            // 返回成功信息
            foreach($info as $file){
                $data['name']=trim($file['savepath'].$file['savename'],'.');
                echo json_encode($data);
            }               
        }
    }
}

/**
 * 使用curl获取远程数据
 * @param  string $url url连接
 * @return string      获取到的数据
 */
function curl_get_contents($url){
    $ch=curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);                //设置访问的url地址
    // curl_setopt($ch,CURLOPT_HEADER,1);               //是否显示头部信息
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);               //设置超时
    curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);   //用户访问代理 User-Agent
    curl_setopt($ch, CURLOPT_REFERER,$_SERVER['HTTP_HOST']);        //设置 referer
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);          //跟踪301
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);        //返回结果
    $r=curl_exec($ch);
    curl_close($ch);
    return $r;
}

/**
 * 将路径转换加密
 * @param  string $file_path 路径
 * @return string            转换后的路径
 */
function path_encode($file_path){
    return rawurlencode(base64_encode($file_path));
}

/**
 * 将路径解密
 * @param  string $file_path 加密后的字符串
 * @return string            解密后的路径
 */
function path_decode($file_path){
    return base64_decode(rawurldecode($file_path));
}

/**
 * 根据文件后缀的不同返回不同的结果
 * @param  string $str 需要判断的文件名或者文件的id
 * @return integer     1:图片  2：视频  3：压缩文件  4：文档  5：其他
 */
function file_category($str){
    // 取文件后缀名
    $str=strtolower(pathinfo($str, PATHINFO_EXTENSION));
    // 图片格式
    $images=array('webp','jpg','png','ico','bmp','gif','tif','pcx','tga','bmp','pxc','tiff','jpeg','exif','fpx','svg','psd','cdr','pcd','dxf','ufo','eps','ai','hdri');
    // 视频格式
    $video=array('mp4','avi','3gp','rmvb','gif','wmv','mkv','mpg','vob','mov','flv','swf','mp3','ape','wma','aac','mmf','amr','m4a','m4r','ogg','wav','wavpack');
    // 压缩格式
    $zip=array('rar','zip','tar','cab','uue','jar','iso','z','7-zip','ace','lzh','arj','gzip','bz2','tz');
    // 文档格式
    $document=array('exe','doc','ppt','xls','wps','txt','lrc','wfs','torrent','html','htm','java','js','css','less','php','pdf','pps','host','box','docx','word','perfect','dot','dsf','efe','ini','json','lnk','log','msi','ost','pcs','tmp','xlsb');
    // 匹配不同的结果
    switch ($str) {
        case in_array($str, $images):
            return 1;
            break;
        case in_array($str, $video):
            return 2;
            break;
        case in_array($str, $zip):
            return 3;
            break;
        case in_array($str, $document):
            return 4;
            break;
        default:
            return 5;
            break;
    }
}

/**
 * 组合缩略图
 * @param  string  $file_path  原图path
 * @param  integer $size       比例
 * @return string              缩略图
 */
function get_min_image_path($file_path,$width=170,$height=170){
    $min_path=str_replace('.', '_'.$width.'_'.$height.'.', trim($file_path,'.'));
    $min_path=OSS_URL.$min_path;
    return $min_path;
} 
/**
 * 不区分大小写的in_array()
 * @param  string $str   检测的字符
 * @param  array  $array 数组
 * @return boolear       是否in_array
 */
function in_iarray($str,$array){
    $str=strtolower($str);
    $array=array_map('strtolower', $array);
    if (in_array($str, $array)) {
        return true;
    }
    return false;
}

/**
 * 传入时间戳,计算距离现在的时间
 * @param  number $time 时间戳
 * @return string     返回多少以前
 */
function word_time($time) {
    $time = (int) substr($time, 0, 10);
    $int = time() - $time;
    $str = '';
    if ($int <= 2){
        $str = sprintf('刚刚', $int);
    }elseif ($int < 60){
        $str = sprintf('%d秒前', $int);
    }elseif ($int < 3600){
        $str = sprintf('%d分钟前', floor($int / 60));
    }elseif ($int < 86400){
        $str = sprintf('%d小时前', floor($int / 3600));
    }elseif ($int < 1728000){
        $str = sprintf('%d天前', floor($int / 86400));
    }else{
        $str = date('Y-m-d H:i:s', $time);
    }
    return $str;
}

/**
 * 生成缩略图
 * @param  string  $image_path 原图path
 * @param  integer $width      缩略图的宽
 * @param  integer $height     缩略图的高
 * @return string             缩略图path
 */
function crop_image($image_path,$width=170,$height=170){
    $image_path=trim($image_path,'.');
    $min_path='.'.str_replace('.', '_'.$width.'_'.$height.'.', $image_path);
    $image = new \Think\Image();
    $image->open($image_path);
    // 生成一个居中裁剪为$width*$height的缩略图并保存
    $image->thumb($width, $height,\Think\Image::IMAGE_THUMB_CENTER)->save($min_path);
    oss_upload($min_path);
    return $min_path;
}

/**
 * 上传文件类型控制 此方法仅限ajax上传使用
 * @param  string   $path    字符串 保存文件路径示例： /Upload/image/
 * @param  string   $format  文件格式限制
 * @param  integer  $maxSize 允许的上传文件最大值 52428800
 * @return booler   返回ajax的json格式数据
 */
function ajax_upload($path='file',$format='empty',$maxSize='52428800'){
    ini_set('max_execution_time', '0');
    // 去除两边的/
    $path=trim($path,'/');
    // 添加Upload根目录
    $path=strtolower(substr($path, 0,6))==='upload' ? ucfirst($path) : 'Upload/'.$path;
    // 上传文件类型控制
    $ext_arr= array(
            'image' => array('gif', 'jpg', 'jpeg', 'png', 'bmp'),
            'photo' => array('jpg', 'jpeg', 'png'),
            'flash' => array('swf', 'flv'),
            'media' => array('swf', 'flv', 'mp3', 'wav', 'wma', 'wmv', 'mid', 'avi', 'mpg', 'asf', 'rm', 'rmvb'),
            'file' => array('doc', 'docx', 'xls', 'xlsx', 'ppt', 'htm', 'html', 'txt', 'zip', 'rar', 'gz', 'bz2','pdf')
        );
    if(!empty($_FILES)){
        // 上传文件配置
        $config=array(
                'maxSize'   =>  $maxSize,               // 上传文件最大为50M
                'rootPath'  =>  './',                   // 文件上传保存的根路径
                'savePath'  =>  './'.$path.'/',         // 文件上传的保存路径（相对于根路径）
                'saveName'  =>  array('uniqid',''),     // 上传文件的保存规则，支持数组和字符串方式定义
                'autoSub'   =>  true,                   // 自动使用子目录保存上传文件 默认为true
                'exts'      =>    isset($ext_arr[$format])?$ext_arr[$format]:'',
            );
        // p($_FILES);
        // 实例化上传
        $upload=new \Think\Upload($config);
        // 调用上传方法
        $info=$upload->upload();
        // p($info);
        $data=array();
        if(!$info){
            // 返回错误信息
            $error=$upload->getError();
            $data['error_info']=$error;
            echo json_encode($data);
        }else{
            // 返回成功信息
            foreach($info as $file){
                $data['name']=trim($file['savepath'].$file['savename'],'.');
                // p($data);
                echo json_encode($data);
            }               
        }
    }
}

/**
 * 检测webuploader上传是否成功
 * @param  string $file_path post中的字段
 * @return boolear           是否成功
 */
function upload_success($file_path){
    // 为兼容传进来的有数组；先转成json
    $file_path=json_encode($file_path);
    // 如果有undefined说明上传失败
    if (strpos($file_path, 'undefined') !== false) {
        return false;
    }
    // 如果没有.符号说明上传失败
    if (strpos($file_path, '.') === false) {
        return false;
    }
    // 否则上传成功则返回true
    return true;
}

/**
 * 把用户输入的文本转义（主要针对特殊符号和emoji表情）
 */
function emoji_encode($str){
    if(!is_string($str))return $str;
    if(!$str || $str=='undefined')return '';
    $text = json_encode($str); //暴露出unicode
    $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i",function($str){
        return addslashes($str[0]);
    },$text); //将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。
    return json_decode($text);
}

/**
 * 检测是否是手机访问
 */
function is_mobile(){
    $useragent=isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $useragent_commentsblock=preg_match('|\(.*?\)|',$useragent,$matches)>0?$matches[0]:'';
    function _is_mobile($substrs,$text){
        foreach($substrs as $substr)
            if(false!==strpos($text,$substr)){
                return true;
            }
            return false;
    }
    $mobile_os_list=array('Google Wireless Transcoder','Windows CE','WindowsCE','Symbian','Android','armv6l','armv5','Mobile','CentOS','mowser','AvantGo','Opera Mobi','J2ME/MIDP','Smartphone','Go.Web','Palm','iPAQ');
    $mobile_token_list=array('Profile/MIDP','Configuration/CLDC-','160×160','176×220','240×240','240×320','320×240','UP.Browser','UP.Link','SymbianOS','PalmOS','PocketPC','SonyEricsson','Nokia','BlackBerry','Vodafone','BenQ','Novarra-Vision','Iris','NetFront','HTC_','Xda_','SAMSUNG-SGH','Wapaka','DoCoMo','iPhone','iPod');
    $found_mobile=_is_mobile($mobile_os_list,$useragent_commentsblock) ||
              _is_mobile($mobile_token_list,$useragent);
    if ($found_mobile){
        return true;
    }else{
        return false;
    }
}

/**
 * 将utf-16的emoji表情转为utf8文字形
 * @param  string $str 需要转的字符串
 * @return string      转完成后的字符串
 */
function escape_sequence_decode($str) {
    $regex = '/\\\u([dD][89abAB][\da-fA-F]{2})\\\u([dD][c-fC-F][\da-fA-F]{2})|\\\u([\da-fA-F]{4})/sx';
    return preg_replace_callback($regex, function($matches) {
        if (isset($matches[3])) {
            $cp = hexdec($matches[3]);
        } else {
            $lead = hexdec($matches[1]);
            $trail = hexdec($matches[2]);
            $cp = ($lead << 10) + $trail + 0x10000 - (0xD800 << 10) - 0xDC00;
        }
        if ($cp > 0xD7FF && 0xE000 > $cp) {
            $cp = 0xFFFD;
        }
        if ($cp < 0x80) {
            return chr($cp);
        } else if ($cp < 0xA0) {
            return chr(0xC0 | $cp >> 6).chr(0x80 | $cp & 0x3F);
        }
        $result =  html_entity_decode('&#'.$cp.';');
        return $result;
    }, $str);
}

/**
 * 获取当前访问的设备类型
 * @return integer 1：其他  2：iOS  3：Android
 */
function get_device_type(){
    //全部变成小写字母
    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $type = 1;
    //分别进行判断
    if(strpos($agent, 'iphone')!==false || strpos($agent, 'ipad')!==false){
        $type = 2;
    } 
    if(strpos($agent, 'android')!==false){
        $type = 3;
    }
    return $type;
}

/**
 * 生成pdf
 * @param  string $html      需要生成的内容
 */
function pdf($html='<h1 style="color:red">hello word</h1>'){
    vendor('Tcpdf.tcpdf');
    $pdf = new \Tcpdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    // 设置打印模式
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Nicola Asuni');
    $pdf->SetTitle('TCPDF Example 001');
    $pdf->SetSubject('TCPDF Tutorial');
    $pdf->SetKeywords('TCPDF, PDF, example, test, guide');
    // 是否显示页眉
    $pdf->setPrintHeader(false);
    // 设置页眉显示的内容
    $pdf->SetHeaderData('logo.png', 60, 'baijunyao.com', '白俊遥博客', array(0,64,255), array(0,64,128));
    // 设置页眉字体
    $pdf->setHeaderFont(Array('dejavusans', '', '12'));
    // 页眉距离顶部的距离
    $pdf->SetHeaderMargin('5');
    // 是否显示页脚
    $pdf->setPrintFooter(true);
    // 设置页脚显示的内容
    $pdf->setFooterData(array(0,64,0), array(0,64,128));
    // 设置页脚的字体
    $pdf->setFooterFont(Array('dejavusans', '', '10'));
    // 设置页脚距离底部的距离
    $pdf->SetFooterMargin('10');
    // 设置默认等宽字体
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    // 设置行高
    $pdf->setCellHeightRatio(1);
    // 设置左、上、右的间距
    $pdf->SetMargins('10', '10', '10');
    // 设置是否自动分页  距离底部多少距离时分页
    $pdf->SetAutoPageBreak(TRUE, '15');
    // 设置图像比例因子
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
        require_once(dirname(__FILE__).'/lang/eng.php');
        $pdf->setLanguageArray($l);
    }
    $pdf->setFontSubsetting(true);
    $pdf->AddPage();
    // 设置字体
    $pdf->SetFont('stsongstdlight', '', 14, '', true);
    $pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
    $pdf->Output('example_001.pdf', 'I');
}

/**
 * 生成二维码
 * @param  string  $url  url连接
 * @param  integer $size 尺寸 纯数字
 */
function qrcode($url,$size=4){
    Vendor('Phpqrcode.phpqrcode');
    QRcode::png($url,false,QR_ECLEVEL_L,$size,2,false,0xFFFFFF,0x000000);
}

/**
 * 数组转xls格式的excel文件
 * @param  array  $data      需要生成excel文件的数组
 * @param  string $filename  生成的excel文件名
 *      示例数据：
        $data = array(
            array(NULL, 2010, 2011, 2012),
            array('Q1',   12,   15,   21),
            array('Q2',   56,   73,   86),
            array('Q3',   52,   61,   69),
            array('Q4',   30,   32,    0),
           );
 */
/**
 * 跳向支付宝付款
 * @param  array $order 订单数据 必须包含 out_trade_no(订单号)、price(订单金额)、subject(商品名称标题)
 */
function alipay($order){
    vendor('Alipay.AlipaySubmit','','.class.php');
    // 从数据库获取支付宝配置
    $alipayConfig = D('paysite')->where([
        'pay_type' => 'zfb',
        'status' => 1
    ])->find();
    
    if (!$alipayConfig) {
        return false;
    }
    
    // 构建配置数组 - 适配老版本支付宝SDK
    $config=array(
        'partner' => $alipayConfig['app_id'], // 使用app_id作为partner
        'key' => $alipayConfig['merchant_private_key'], // 商户私钥
        'sign_type' => 'RSA', // 签名类型
        'input_charset' => 'utf-8',
        'return_url' => $alipayConfig['return_url'],
        'notify_url' => $alipayConfig['notify_url'],
        'seller_email' => 'admin@example.com', // 默认邮箱
        'show_url' => 'https://' . $_SERVER['HTTP_HOST'] // 商品展示网址
    );
    $data=array(
        "_input_charset" => $config['input_charset'], // 编码格式
        "logistics_fee" => "0.00", // 物流费用
        "logistics_payment" => "SELLER_PAY", // 物流支付方式SELLER_PAY（卖家承担运费）、BUYER_PAY（买家承担运费）
        "logistics_type" => "EXPRESS", // 物流类型EXPRESS（快递）、POST（平邮）、EMS（EMS）
        "notify_url" => $config['notify_url'], // 异步接收支付状态通知的链接
        "out_trade_no" => $order['out_trade_no'], // 订单号
        "partner" => $config['partner'], // partner 从支付宝商户版个人中心获取
        "payment_type" => "1", // 支付类型对应请求时的 payment_type 参数,原样返回。固定设置为1即可
        "price" => $order['price'], // 订单价格单位为元
        // "price" => 0.01, // // 调价用于测试
        "quantity" => "1", // price、quantity 能代替 total_fee。 即存在 total_fee,就不能存在 price 和 quantity;存在 price、quantity, 就不能存在 total_fee。 （没绕明白；好吧；那无视这个参数即可）
        "receive_address" => '1', // 收货人地址 即时到账方式无视此参数即可
        "receive_mobile" => '1', // 收货人手机号码 即时到账方式无视即可
        "receive_name" => '1', // 收货人姓名 即时到账方式无视即可
        "receive_zip" => '1', // 收货人邮编 即时到账方式无视即可
        "return_url" => $config['return_url'], // 页面跳转 同步通知 页面路径 支付宝处理完请求后,当前页面自 动跳转到商户网站里指定页面的 http 路径。
        "seller_email" => $config['seller_email'], // email 从支付宝商户版个人中心获取
        "service" => "create_direct_pay_by_user", // 接口名称 固定设置为create_direct_pay_by_user
        "show_url" => $config['show_url'], // 商品展示网址,收银台页面上,商品展示的超链接。
        "subject" => $order['subject'] // 商品名称商品的标题/交易标题/订单标 题/订单关键字等
    );
    $alipay=new \AlipaySubmit($config);
    $new=$alipay->buildRequestPara($data);
    $go_pay=$alipay->buildRequestForm($new, 'get','支付');
    echo $go_pay;
}

/**
 * geetest检测验证码
 */
function geetest_chcek_verify($data){
    $geetest_id=env('GEETEST_ID');
    $geetest_key=env('GEETEST_KEY');
    $geetest=new \Org\Xb\Geetest($geetest_id,$geetest_key);
    $user_id=$_SESSION['geetest']['user_id'];
    if ($_SESSION['geetest']['gtserver']==1) {
        $result=$geetest->success_validate($data['geetest_challenge'], $data['geetest_validate'], $data['geetest_seccode'], $user_id);
        if ($result) {
            return true;
        } else{
            return false;
        }
    }else{
        if ($geetest->fail_validate($data['geetest_challenge'],$data['geetest_validate'],$data['geetest_seccode'])) {
            return true;
        }else{
            return false;
        }
    }
}

/**
 * 加密解密字符串
 * @param $string 明文 或 密文
 * @param $operation DECODE表示解密,其它表示加密
 * @param $key 密钥
 * @param $expiry 密文有效期
 * @author nish
 */
function authcode($string, $operation = 'DECODE', $key = 'icondoit', $expiry = 0)
{
    // 过滤特殊字符
    if ($operation == 'DECODE') {
        $string = str_replace('-a-', '+', $string);
        $string = str_replace('-b-', '&', $string);
        $string = str_replace('-c-', '/', $string);
    }
    // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
    $ckey_length = 4;
    // 密匙
    $key = md5($key ? $key : 'nish');
    // 密匙a会参与加解密
    $keya = md5(substr($key, 0, 16));
    // 密匙b会用来做数据完整性验证
    $keyb = md5(substr($key, 16, 16));
    // 密匙c用于变化生成的密文
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';
    // 参与运算的密匙
    $cryptkey   = $keya . md5($keya . $keyc);
    $key_length = strlen($cryptkey);
    // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，解密时会通过这个密匙验证数据完整性
    // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
    $string        = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
    $string_length = strlen($string);
    $result        = '';
    $box           = range(0, 255);
    $rndkey        = array();
    // 产生密匙簿
    for ($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }
    // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
    for ($j = $i = 0; $i < 256; $i++) {
        $j       = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp     = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }
    // 核心加解密部分
    for ($a = $j = $i = 0; $i < $string_length; $i++) {
        $a       = ($a + 1) % 256;
        $j       = ($j + $box[$a]) % 256;
        $tmp     = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        // 从密匙簿得出密匙进行异或，再转成字符
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }
    if ($operation == 'DECODE') {
        // substr($result, 0, 10) == 0 验证数据有效性
        // substr($result, 0, 10) - time() > 0 验证数据有效性
        // substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16) 验证数据完整性
        // 验证数据有效性，请看未加密明文的格式
        if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
        // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
        $ustr = $keyc . str_replace('=', '', base64_encode($result));
        // 过滤特殊字符
        $ustr = str_replace('+', '-a-', $ustr);
        $ustr = str_replace('&', '-b-', $ustr);
        $ustr = str_replace('/', '-c-', $ustr);
        return $ustr;
    }
}

    function randomkeys($length){ 
        $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';
        $key = '';
        for($i=0;$i<$length;$i++){ 
            $key .= $pattern[mt_rand(0,61)]; //生成php随机数
        } 
        return $key; 
    }
    // 生成更安全的随机字符串（仅包含数字和字母）
    function generate_secure_random($length = 16) {
        $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';
        $key = '';
        $pattern_length = strlen($pattern) - 1;
        // 使用更安全的随机数生成器
        if (function_exists('random_bytes')) {
            try {
                $random_bytes = random_bytes($length);
                for ($i = 0; $i < $length; $i++) {
                    $key .= $pattern[ord($random_bytes[$i]) % $pattern_length];
                }
                return $key;
            } catch (Exception $e) {
                // 如果random_bytes失败，使用备用方法
            }
        }
        // 备用方法：使用mt_rand
        for($i = 0; $i < $length; $i++){ 
            $key .= $pattern[mt_rand(0, $pattern_length)];
        } 
        return $key; 
    }
    // 安全的密码哈希函数
    function secure_password_hash($password) {
        // 使用PHP内置的password_hash函数，默认使用BCRYPT算法
        return password_hash($password, PASSWORD_DEFAULT);
    }
    // 验证密码函数，支持旧的MD5和新的哈希
    function verify_password($password, $hash) {
        // 首先尝试新的password_verify
        if (password_verify($password, $hash)) {
            return true;
        }
        // 如果新验证失败，检查是否是旧的MD5哈希（32位长度）
        if (strlen($hash) === 32 && $hash === md5($password)) {
            return true;
        }
        return false;
    }
    // 检查密码是否需要重新哈希（用于升级旧密码）
    if (!function_exists('password_needs_rehash')) {
        function password_needs_rehash($hash, $algo = PASSWORD_DEFAULT, $options = array()) {
            // 如果是32位长度，说明是MD5，需要重新哈希
            if (strlen($hash) === 32) {
                return true;
            }
            // 对于其他情况，假设不需要重新哈希（兼容旧版本PHP）
            return false;
        }
    }
    // 自定义函数：检查密码是否需要重新哈希（用于升级旧密码）
    function check_password_needs_rehash($hash) {
        return password_needs_rehash($hash, PASSWORD_DEFAULT, ['cost' => 12]);
    }
    /**
     * 发送订阅地址邮件
     * @param string $email 邮箱地址
     * @param string $username 用户名
     * @param string $mobileUrl 通用订阅地址
     * @param string $clashUrl Clash专用地址
     * @param int $expireTime 到期时间戳
     * @param bool $useQueue 是否使用邮件队列，默认true
     * @return bool 发送结果
     */
    function send_subscription_email($email, $username, $mobileUrl, $clashUrl, $expireTime = null, $useQueue = true) {
        try {
            // 引入邮件模板类
            require_once dirname(__FILE__) . '/EmailTemplate.class.php';
            // 生成邮件内容
            $emailContent = EmailTemplate::getSubscriptionTemplate($username, $mobileUrl, $clashUrl, $expireTime);
            // 发送邮件
            return send_mail($email, '订阅地址通知', $emailContent, $useQueue, 'subscription');
        } catch (Exception $e) {
            error_log('发送订阅邮件失败: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * 发送激活邮件
     * @param string $email 邮箱地址
     * @param string $username 用户名
     * @param string $activationLink 激活链接
     * @param bool $useQueue 是否使用邮件队列，默认true
     * @return bool 发送结果
     */
    function send_activation_email($email, $username, $activationLink, $useQueue = true) {
        try {
            // 引入邮件模板类
            require_once dirname(__FILE__) . '/EmailTemplate.class.php';
            // 生成邮件内容
            $emailContent = EmailTemplate::getActivationTemplate($username, $activationLink);
            // 发送邮件
            return send_mail($email, '账户激活', $emailContent, $useQueue, 'activation');
        } catch (Exception $e) {
            error_log('发送激活邮件失败: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * 发送到期提醒邮件
     * @param string $email 邮箱地址
     * @param string $username 用户名
     * @param int $expireTime 到期时间戳
     * @param bool $isExpired 是否已到期
     * @param bool $useQueue 是否使用邮件队列，默认true
     * @return bool 发送结果
     */
    function send_expiration_email($email, $username, $expireTime, $isExpired = false, $useQueue = true) {
        try {
            // 引入邮件模板类
            require_once dirname(__FILE__) . '/EmailTemplate.class.php';
            // 生成邮件内容
            $emailContent = EmailTemplate::getExpirationTemplate($username, $expireTime, $isExpired);
            // 发送邮件
            $subject = $isExpired ? '订阅已到期' : '订阅即将到期';
            $emailType = $isExpired ? 'expired' : 'expiring';
            return send_mail($email, $subject, $emailContent, $useQueue, $emailType);
        } catch (Exception $e) {
            error_log('发送到期提醒邮件失败: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * 发送订单通知邮件
     * @param array $config 配置信息
     * @param string $orderNo 订单号
     * @param string $planName 套餐名称
     * @param float $price 价格
     * @param string $duration 时长
     * @param string $status 状态
     * @param bool $useQueue 是否使用邮件队列，默认true
     * @param bool $isAdmin 是否为管理员邮件，默认false
     * @return bool 发送结果
     */
    function send_order_email($config, $orderNo, $planName, $price, $duration, $status = '已支付', $useQueue = true, $isAdmin = false) {
        try {
            // 引入邮件模板类
            require_once dirname(__FILE__) . '/EmailTemplate.class.php';
            // 获取用户信息
            $username = isset($config['username']) ? $config['username'] : '';
            $mobileUrl = '';
            $clashUrl = '';
            $expireDate = '';
            // 如果有用户名，获取订阅信息
            if ($username && function_exists('M')) {
                $subscription = M('ShortDingyue')->where(['qq' => $username])->find();
                if ($subscription) {
                    // 生成两个订阅地址
                    $mobileUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/' . $subscription['mobileshorturl'];
                    $clashUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/' . $subscription['clashshorturl'];
                    // 获取到期时间
                    if ($subscription['endtime'] > 0) {
                        $expireDate = date('Y年m月d日 H:i:s', $subscription['endtime']);
                    }
                }
            }
            // 生成邮件内容
            $emailContent = EmailTemplate::getOrderTemplate($orderNo, $planName, $price, $duration, $status, $username, $mobileUrl, $clashUrl, $expireDate, $isAdmin);
            // 获取用户邮箱（从配置或其他地方获取）
            $email = isset($config['email']) ? $config['email'] : $config['qq'] . '@qq.com';
            // 发送邮件
            $subject = $isAdmin ? '新订单通知' : '订单支付成功通知';
            return send_mail($email, $subject, $emailContent, $useQueue, 'order');
        } catch (Exception $e) {
            error_log('发送订单邮件失败: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * 发送密码重置邮件
     * @param string $email 邮箱地址
     * @param string $username 用户名
     * @param string $resetLink 重置链接
     * @param bool $useQueue 是否使用邮件队列，默认true
     * @return bool 发送结果
     */
    function send_password_reset_email($email, $username, $resetLink, $useQueue = true) {
        try {
            // 引入邮件模板类
            require_once dirname(__FILE__) . '/EmailTemplate.class.php';
            // 生成邮件内容
            $emailContent = EmailTemplate::getPasswordResetTemplate($username, $resetLink);
            // 发送邮件
            return send_mail($email, '密码重置', $emailContent, $useQueue, 'password_reset');
        } catch (Exception $e) {
            error_log('发送密码重置邮件失败: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * 通用邮件发送函数
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $body 邮件内容（HTML格式）
     * @param bool $useQueue 是否使用邮件队列，默认true
     * @param string $emailType 邮件类型
     * @param int $priority 优先级（1-5，1最高）
     * @return bool 发送结果
     */
    function send_mail($to, $subject, $body, $useQueue = true, $emailType = 'general', $priority = 3) {
        if ($useQueue) {
            // Log::record("Queuing email to {$to}, Subject: {$subject}", Log::INFO);
            // 引入邮件队列类
            require_once dirname(__FILE__) . '/EmailQueue.class.php';
            $emailQueue = new EmailQueue();
            return $emailQueue->addToQueue($to, $subject, $body, $emailType, $priority);
        }
        // 直接发送模式（原有逻辑）
        $result = send_mail_direct($to, $subject, $body);
        // 记录发送结果
        $logFile = dirname(dirname(__DIR__)) . '/Runtime/Logs/email_queue.log';
        file_put_contents($logFile, "[send_mail] 直接发送结果: " . ($result ? '成功' : '失败') . " - To: {$to}, Subject: {$subject}\n", FILE_APPEND);
        return $result;
    }
    /**
     * 直接发送邮件函数（不使用队列）
     * @param string $to 收件人邮箱
     * @param string $subject 邮件主题
     * @param string $body 邮件内容（HTML格式）
     * @return bool 发送结果
     */
    function send_mail_direct($to, $subject, $body) {
        try {
            // 日志文件路径
            $logFile = dirname(dirname(__DIR__)) . '/Runtime/Logs/email_queue.log';
            // 优先从.env文件读取邮件配置，如果没有则从config.php读取
                $smtpHost = env('EMAIL_SMTP') ?: env('MAIL_HOST');
    $smtpPort = env('EMAIL_PORT') ?: env('MAIL_PORT');
    $smtpUser = env('EMAIL_USERNAME') ?: env('MAIL_USER');
    $smtpPass = env('EMAIL_PASSWORD') ?: env('MAIL_PASS');
    $smtpSecure = env('EMAIL_SMTP_SECURE') ?: env('MAIL_SECURE');
    $fromName = env('EMAIL_FROM_NAME') ?: '订阅服务';
            // 记录SMTP配置信息
            file_put_contents($logFile, "[send_mail_direct] start\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] SMTP Host: " . var_export($smtpHost, true) . "\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] SMTP User: " . var_export($smtpUser, true) . "\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] SMTP Pass: " . var_export($smtpPass, true) . "\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] SMTP Port: " . var_export($smtpPort, true) . "\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] SMTP Secure: " . var_export($smtpSecure, true) . "\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] From Name: " . var_export($fromName, true) . "\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] To: " . var_export($to, true) . "\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] Subject: " . var_export($subject, true) . "\n", FILE_APPEND);
            // 检查必要配置
            if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
                file_put_contents($logFile, "[send_mail_direct] 邮件配置不完整\n", FILE_APPEND);
                error_log('邮件配置不完整，请检查SMTP配置');
                return false;
            }
            // 使用PHPMailer发送邮件
            file_put_contents($logFile, "[send_mail_direct] 尝试加载PHPMailer\n", FILE_APPEND);
            // 使用最可靠的方式构建 Vendor 路径
            $vendor_path_prefix = dirname(dirname(dirname(__DIR__))) . '/ThinkPHP/Library/Vendor/';
            $exception_path = $vendor_path_prefix . 'PHPMailer/Exception.php';
            file_put_contents($logFile, "[send_mail_direct] Checking for Exception.php at: " . $exception_path . "\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] Exception.php exists: " . (file_exists($exception_path) ? 'Yes' : 'No') . "\n", FILE_APPEND);
            require_once $exception_path;
            file_put_contents($logFile, "[send_mail_direct] Loaded Exception.php\n", FILE_APPEND);
            $phpmailer_path = $vendor_path_prefix . 'PHPMailer/PHPMailer.php';
            file_put_contents($logFile, "[send_mail_direct] Checking for PHPMailer.php at: " . $phpmailer_path . "\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] PHPMailer.php exists: " . (file_exists($phpmailer_path) ? 'Yes' : 'No') . "\n", FILE_APPEND);
            require_once $phpmailer_path;
            file_put_contents($logFile, "[send_mail_direct] Loaded PHPMailer.php\n", FILE_APPEND);
            $smtp_path = $vendor_path_prefix . 'PHPMailer/SMTP.php';
            file_put_contents($logFile, "[send_mail_direct] Checking for SMTP.php at: " . $smtp_path . "\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] SMTP.php exists: " . (file_exists($smtp_path) ? 'Yes' : 'No') . "\n", FILE_APPEND);
            require_once $smtp_path;
            file_put_contents($logFile, "[send_mail_direct] Loaded SMTP.php\n", FILE_APPEND);
            file_put_contents($logFile, "[send_mail_direct] All PHPMailer files loaded. Creating instance.\n", FILE_APPEND);
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            file_put_contents($logFile, "[send_mail_direct] PHPMailer instance created.\n", FILE_APPEND);
            // SMTP配置
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->Port = intval($smtpPort);
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->SMTPSecure = $smtpSecure;
            $mail->CharSet = 'UTF-8';
            // 发件人和收件人
            $mail->setFrom($smtpUser, $fromName);
            $mail->addAddress($to);
            // 邮件内容
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            // 发送邮件
            try {
                $result = $mail->send();
                file_put_contents($logFile, "[send_mail_direct] send result: " . var_export($result, true) . "\n", FILE_APPEND);
                if ($result) {
                    file_put_contents($logFile, "[send_mail_direct] 邮件发送成功: $to\n", FILE_APPEND);
                } else {
                    file_put_contents($logFile, "[send_mail_direct] 邮件发送失败: " . $mail->ErrorInfo . "\n", FILE_APPEND);
                }
                return $result;
            } catch (\Exception $e) {
                file_put_contents($logFile, "[send_mail_direct] PHPMailer异常: " . $e->getMessage() . "\n", FILE_APPEND);
                error_log('邮件发送异常: ' . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            $logFile = dirname(dirname(__DIR__)) . '/Runtime/Logs/email_queue.log';
            file_put_contents($logFile, "[send_mail_direct] 全局异常: " . $e->getMessage() . "\n", FILE_APPEND);
            error_log('邮件发送异常: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * 记录操作日志
     * @param string $action 操作类型
     * @param string $message 日志消息
     * @param string $operator 操作者
     * @return bool
     */
    function write_action_log($action, $message, $operator = '') {
        try {
            $logData = [
                'action' => $action,
                'message' => $message,
                'operator' => $operator,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'create_time' => time()
            ];
                    // 如果存在action_log表，则记录到数据库
        if (function_exists('M') && M('action_log', '', true)) {
            M('action_log')->add($logData);
        }
            // 同时记录到文件日志
            $logMessage = sprintf(
                "[%s] Action: %s, Message: %s, Operator: %s, IP: %s\n",
                date('Y-m-d H:i:s'),
                $action,
                $message,
                $operator,
                $logData['ip']
            );
            $logFile = dirname(dirname(__DIR__)) . '/Runtime/Logs/action.log';
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            return true;
        } catch (Exception $e) {
            error_log('write_action_log error: ' . $e->getMessage());
            return false;
        }
    }
    /**
 * 获取iOS版本名称
 * @param float $darwinVersion Darwin版本号
 * @return string iOS版本名称
 */
function get_ios_version_name($darwinVersion) {
    // Darwin版本到iOS版本的精确映射（修正版）
    $versionMap = [
        25.0 => 'iOS18.0',
        24.0 => 'iOS18.0',
        23.0 => 'iOS17.0',
        22.0 => 'iOS16.0',
        21.0 => 'iOS15.0',
        20.0 => 'iOS14.0',
        19.0 => 'iOS13.0',
        18.0 => 'iOS12.0',
        17.0 => 'iOS11.0',
        16.0 => 'iOS10.0',
        15.0 => 'iOS9.0',
        14.0 => 'iOS8.0',
        13.0 => 'iOS7.0',
        12.0 => 'iOS6.0',
        11.0 => 'iOS5.0',
        10.0 => 'iOS4.0',
        9.0 => 'iOS3.0',
        8.0 => 'iOS2.0'
    ];
    // 更精确的版本匹配（修正版）
    if ($darwinVersion >= 25.0) {
        return 'iOS19.0';
    } elseif ($darwinVersion >= 24.6) {
        return 'iOS18.6';
    } elseif ($darwinVersion >= 24.5) {
        return 'iOS18.5';
    } elseif ($darwinVersion >= 24.0) {
        return 'iOS18.0';
    } elseif ($darwinVersion >= 23.6) {
        return 'iOS17.6';
    } elseif ($darwinVersion >= 23.5) {
        return 'iOS17.5';
    } elseif ($darwinVersion >= 23.0) {
        return 'iOS17.0';
    } elseif ($darwinVersion >= 22.6) {
        return 'iOS16.6';
    } elseif ($darwinVersion >= 22.5) {
        return 'iOS16.5';
    } elseif ($darwinVersion >= 22.4) {
        return 'iOS16.4';
    } elseif ($darwinVersion >= 22.3) {
        return 'iOS16.3';
    } elseif ($darwinVersion >= 22.2) {
        return 'iOS16.2';
    } elseif ($darwinVersion >= 22.1) {
        return 'iOS16.1';
    } elseif ($darwinVersion >= 22.0) {
        return 'iOS16.0';
    } elseif ($darwinVersion >= 21.6) {
        return 'iOS15.6';
    } elseif ($darwinVersion >= 21.5) {
        return 'iOS15.5';
    } elseif ($darwinVersion >= 21.4) {
        return 'iOS15.4';
    } elseif ($darwinVersion >= 21.3) {
        return 'iOS15.3';
    } elseif ($darwinVersion >= 21.2) {
        return 'iOS15.2';
    } elseif ($darwinVersion >= 21.1) {
        return 'iOS15.1';
    } elseif ($darwinVersion >= 21.0) {
        return 'iOS15.0';
    } elseif ($darwinVersion >= 20.0) {
        return 'iOS14.0';
    } elseif ($darwinVersion >= 19.0) {
        return 'iOS13.0';
    } elseif ($darwinVersion >= 18.0) {
        return 'iOS12.0';
    } elseif ($darwinVersion >= 17.0) {
        return 'iOS11.0';
    } elseif ($darwinVersion >= 16.0) {
        return 'iOS10.0';
    } elseif ($darwinVersion >= 15.0) {
        return 'iOS9.0';
    } elseif ($darwinVersion >= 14.0) {
        return 'iOS8.0';
    } elseif ($darwinVersion >= 13.0) {
        return 'iOS7.0';
    } elseif ($darwinVersion >= 12.0) {
        return 'iOS6.0';
    } elseif ($darwinVersion >= 11.0) {
        return 'iOS5.0';
    } elseif ($darwinVersion >= 10.0) {
        return 'iOS4.0';
    } elseif ($darwinVersion >= 9.0) {
        return 'iOS3.0';
    } elseif ($darwinVersion >= 8.0) {
        return 'iOS2.0';
    }
    return 'iOS_Unknown';
}

/**
 * 获取Android版本名称
 * @param string $androidVersion Android版本号
 * @return string Android版本名称
 */
function get_android_version_name($androidVersion) {
    $versionMap = [
        '14' => 'Android14',
        '13' => 'Android13',
        '12' => 'Android12',
        '11' => 'Android11',
        '10' => 'Android10',
        '9' => 'Android9',
        '8' => 'Android8',
        '7' => 'Android7',
        '6' => 'Android6',
        '5' => 'Android5',
        '4' => 'Android4'
    ];
    return $versionMap[$androidVersion] ?? "Android{$androidVersion}";
}

/**
 * 获取Windows版本名称
 * @param string $windowsVersion Windows版本号
 * @return string Windows版本名称
 */
function get_windows_version_name($windowsVersion) {
    $versionMap = [
        '10.0' => 'Windows10',
        '6.3' => 'Windows8.1',
        '6.2' => 'Windows8',
        '6.1' => 'Windows7',
        '6.0' => 'WindowsVista',
        '5.2' => 'WindowsXP',
        '5.1' => 'WindowsXP',
        '5.0' => 'Windows2000'
    ];
    return $versionMap[$windowsVersion] ?? "Windows{$windowsVersion}";
}

/**
 * 获取macOS版本名称
 * @param string $macVersion macOS版本号
 * @return string macOS版本名称
 */
function get_macos_version_name($macVersion) {
    $versionMap = [
        '14' => 'macOS14_Sonoma',
        '13' => 'macOS13_Ventura',
        '12' => 'macOS12_Monterey',
        '11' => 'macOS11_BigSur',
        '10.15' => 'macOS10.15_Catalina',
        '10.14' => 'macOS10.14_Mojave',
        '10.13' => 'macOS10.13_HighSierra',
        '10.12' => 'macOS10.12_Sierra',
        '10.11' => 'macOS10.11_ElCapitan',
        '10.10' => 'macOS10.10_Yosemite'
    ];
    return $versionMap[$macVersion] ?? "macOS{$macVersion}";
}

/**
 * 获取iOS设备名称
 * @param string $deviceIdentifier 设备标识符 (如 "iPhone17,2")
 * @return string 设备名称
 */
function get_ios_device_name($deviceIdentifier) {
    // iOS设备标识符到设备名称的映射
    $deviceMap = [
        // iPhone 17系列
        'iPhone18,1' => 'iPhone17',
        'iPhone18,2' => 'iPhone17Pro',
        'iPhone18,3' => 'iPhone17ProMax',
        // iPhone 16系列
        'iPhone17,1' => 'iPhone16',
        'iPhone17,2' => 'iPhone16Pro',
        'iPhone17,3' => 'iPhone16ProMax',
        // iPhone 15系列
        'iPhone16,1' => 'iPhone15Pro',
        'iPhone16,2' => 'iPhone15ProMax',
        'iPhone15,4' => 'iPhone15',
        'iPhone15,5' => 'iPhone15Plus',
        // iPhone 14系列
        'iPhone14,7' => 'iPhone14',
        'iPhone14,8' => 'iPhone14Plus',
        'iPhone15,2' => 'iPhone14Pro',
        'iPhone15,3' => 'iPhone14ProMax',
        // iPhone 13系列
        'iPhone14,2' => 'iPhone13Pro',
        'iPhone14,3' => 'iPhone13ProMax',
        'iPhone14,4' => 'iPhone13mini',
        'iPhone14,5' => 'iPhone13',
        'iPhone14,6' => 'iPhoneSE3',
        // iPhone 12系列
        'iPhone13,1' => 'iPhone12mini',
        'iPhone13,2' => 'iPhone12',
        'iPhone13,3' => 'iPhone12Pro',
        'iPhone13,4' => 'iPhone12ProMax',
        // iPhone 11系列
        'iPhone12,1' => 'iPhone11',
        'iPhone12,3' => 'iPhone11Pro',
        'iPhone12,5' => 'iPhone11ProMax',
        // iPhone XS系列
        'iPhone11,2' => 'iPhoneXS',
        'iPhone11,4' => 'iPhoneXSMax',
        'iPhone11,6' => 'iPhoneXSMax',
        'iPhone11,8' => 'iPhoneXR',
        // iPhone X
        'iPhone10,3' => 'iPhoneX',
        'iPhone10,6' => 'iPhoneX',
        // iPhone 8系列
        'iPhone10,1' => 'iPhone8',
        'iPhone10,4' => 'iPhone8',
        'iPhone10,2' => 'iPhone8Plus',
        'iPhone10,5' => 'iPhone8Plus',
        // iPhone 7系列
        'iPhone9,1' => 'iPhone7',
        'iPhone9,3' => 'iPhone7',
        'iPhone9,2' => 'iPhone7Plus',
        'iPhone9,4' => 'iPhone7Plus',
        // iPhone 6系列
        'iPhone8,1' => 'iPhone6s',
        'iPhone8,2' => 'iPhone6sPlus',
        'iPhone8,4' => 'iPhoneSE',
        // iPhone 6系列
        'iPhone7,1' => 'iPhone6Plus',
        'iPhone7,2' => 'iPhone6',
        // iPhone 5系列
        'iPhone6,1' => 'iPhone5s',
        'iPhone6,2' => 'iPhone5s',
        'iPhone5,1' => 'iPhone5',
        'iPhone5,2' => 'iPhone5',
        'iPhone5,3' => 'iPhone5c',
        'iPhone5,4' => 'iPhone5c',
        // iPhone 4系列
        'iPhone4,1' => 'iPhone4s',
        'iPhone3,1' => 'iPhone4',
        'iPhone3,2' => 'iPhone4',
        'iPhone3,3' => 'iPhone4',
        // iPhone 3系列
        'iPhone2,1' => 'iPhone3GS',
        'iPhone1,1' => 'iPhone',
        'iPhone1,2' => 'iPhone3G',
        // iPad Pro系列
        'iPad8,1' => 'iPadPro11_1',
        'iPad8,2' => 'iPadPro11_1',
        'iPad8,3' => 'iPadPro11_1',
        'iPad8,4' => 'iPadPro11_1',
        'iPad8,5' => 'iPadPro12.9_4',
        'iPad8,6' => 'iPadPro12.9_4',
        'iPad8,7' => 'iPadPro12.9_4',
        'iPad8,8' => 'iPadPro12.9_4',
        'iPad8,9' => 'iPadPro11_2',
        'iPad8,10' => 'iPadPro11_2',
        'iPad8,11' => 'iPadPro12.9_5',
        'iPad8,12' => 'iPadPro12.9_5',
        // iPad Air系列
        'iPad13,1' => 'iPadAir4',
        'iPad13,2' => 'iPadAir4',
        'iPad13,4' => 'iPadAir5',
        'iPad13,5' => 'iPadAir5',
        'iPad13,6' => 'iPadAir5',
        'iPad13,7' => 'iPadAir5',
        // iPad系列
        'iPad11,1' => 'iPad7',
        'iPad11,2' => 'iPad7',
        'iPad11,3' => 'iPadAir3',
        'iPad11,4' => 'iPadAir3',
        'iPad11,6' => 'iPad8',
        'iPad11,7' => 'iPad8',
        'iPad12,1' => 'iPad9',
        'iPad12,2' => 'iPad9',
        // iPad mini系列
        'iPad14,1' => 'iPadmini6',
        'iPad14,2' => 'iPadmini6',
        // iPod系列
        'iPod9,1' => 'iPodTouch7',
        'iPod7,1' => 'iPodTouch6',
        'iPod5,1' => 'iPodTouch5',
        'iPod4,1' => 'iPodTouch4',
        'iPod3,1' => 'iPodTouch3',
        'iPod2,1' => 'iPodTouch2',
        'iPod1,1' => 'iPodTouch1',
    ];
    return $deviceMap[$deviceIdentifier] ?? $deviceIdentifier;
}

/**
 * 获取Android设备名称
 * @param string $ua User-Agent字符串
 * @return string 设备名称
 */
function get_android_device_name($ua) {
    // 华为设备识别
    if (preg_match('/HUAWEI|HONOR/i', $ua)) {
        if (preg_match('/HUAWEI\s+([^;\s]+)/i', $ua, $matches)) {
            return 'Huawei_' . trim($matches[1]);
        }
        if (preg_match('/HONOR\s+([^;\s]+)/i', $ua, $matches)) {
            return 'Honor_' . trim($matches[1]);
        }
        return 'Huawei_Device';
    }
    // 小米设备识别
    if (preg_match('/Xiaomi|Redmi|POCO/i', $ua)) {
        if (preg_match('/(Xiaomi|Redmi|POCO)\s+([^;\s]+)/i', $ua, $matches)) {
            return $matches[1] . '_' . trim($matches[2]);
        }
        return 'Xiaomi_Device';
    }
    // OPPO设备识别
    if (preg_match('/OPPO/i', $ua)) {
        if (preg_match('/OPPO\s+([^;\s]+)/i', $ua, $matches)) {
            return 'OPPO_' . trim($matches[1]);
        }
        return 'OPPO_Device';
    }
    // vivo设备识别
    if (preg_match('/vivo/i', $ua)) {
        if (preg_match('/vivo\s+([^;\s]+)/i', $ua, $matches)) {
            return 'vivo_' . trim($matches[1]);
        }
        return 'vivo_Device';
    }
    // 三星设备识别
    if (preg_match('/Samsung|SM-/i', $ua)) {
        if (preg_match('/SM-([A-Z0-9]+)/i', $ua, $matches)) {
            return 'Samsung_SM' . $matches[1];
        }
        if (preg_match('/Samsung\s+([^;\s]+)/i', $ua, $matches)) {
            return 'Samsung_' . trim($matches[1]);
        }
        return 'Samsung_Device';
    }
    // 一加设备识别
    if (preg_match('/OnePlus/i', $ua)) {
        if (preg_match('/OnePlus\s+([^;\s]+)/i', $ua, $matches)) {
            return 'OnePlus_' . trim($matches[1]);
        }
        return 'OnePlus_Device';
    }
    // 魅族设备识别
    if (preg_match('/Meizu/i', $ua)) {
        if (preg_match('/Meizu\s+([^;\s]+)/i', $ua, $matches)) {
            return 'Meizu_' . trim($matches[1]);
        }
        return 'Meizu_Device';
    }
    // 联想设备识别
    if (preg_match('/Lenovo/i', $ua)) {
        if (preg_match('/Lenovo\s+([^;\s]+)/i', $ua, $matches)) {
            return 'Lenovo_' . trim($matches[1]);
        }
        return 'Lenovo_Device';
    }
    // 通用Android设备
    if (preg_match('/Android/i', $ua)) {
        return 'Android_Device';
    }
    return 'Unknown_Device';
}

/**
 * 获取电脑设备名称
 * @param string $ua User-Agent字符串
 * @return string 设备名称
 */
function get_computer_device_name($ua) {
    // Windows设备识别
    if (preg_match('/Windows NT/i', $ua)) {
        if (preg_match('/Windows NT (\d+\.\d+)/i', $ua, $matches)) {
            $windowsVersion = get_windows_version_name($matches[1]);
            return "PC_{$windowsVersion}";
        }
        return 'PC_Windows';
    }
    // macOS设备识别
    if (preg_match('/Macintosh/i', $ua)) {
        if (preg_match('/Mac OS X (\d+_\d+)/i', $ua, $matches)) {
            $macVersion = str_replace('_', '.', $matches[1]);
            $macOSVersion = get_macos_version_name($macVersion);
            return "Mac_{$macOSVersion}";
        }
        return 'Mac_macOS';
    }
    // Linux设备识别
    if (preg_match('/Linux/i', $ua)) {
        if (preg_match('/Ubuntu/i', $ua)) {
            return 'PC_Ubuntu';
        }
        if (preg_match('/CentOS/i', $ua)) {
            return 'PC_CentOS';
        }
        if (preg_match('/Debian/i', $ua)) {
            return 'PC_Debian';
        }
        return 'PC_Linux';
    }
    return 'PC_Unknown';
}

/**
 * 解析和标准化User-Agent字符串
 * @param string $ua 原始User-Agent字符串
 * @return string 标准化后的设备标识
 */
function parse_and_normalize_ua($ua) {
    if (empty($ua) || $ua === null) {
        return 'Unknown';
    }
    $ua = trim($ua);
    // 浏览器UA直接返回原始UA（不进行标准化）
    if (preg_match('/Mozilla|Chrome|Safari|Edge|Trident|Firefox|MSIE/i', $ua)) {
        return $ua;
    }
    // 苹果设备优化识别（仅针对特定客户端软件）
    if (preg_match('/Shadowrocket|Clash|V2ray|Quantumult|Surge|Loon/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        
        // 处理自定义格式：Shadowrocket_iPhone17,2_iOS17.0（需要转换设备标识符）
        if (preg_match('/^Shadowrocket_(iPhone\d+,\d+)_(iOS\d+\.\d+)$/i', $ua, $customMatches)) {
            $deviceIdentifier = $customMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
            $systemVersion = $customMatches[2];
            if (!empty($deviceModel)) {
                return $deviceModel . '_' . $systemVersion;
            }
        }
        
        // 处理自定义格式：Shadowrocket_iPhone12ProMax_iOS17.0
        if (preg_match('/^Shadowrocket_(iPhone[^_]+)_(iOS\d+\.\d+)$/i', $ua, $customMatches)) {
            return $customMatches[1] . '_' . $customMatches[2];
        }
        
        // iOS设备识别（iPhone/iPad/iPod）
        if (preg_match('/iPhone(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPhone' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        } elseif (preg_match('/iPad(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPad' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        } elseif (preg_match('/iPod(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPod' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        } elseif (preg_match('/Macintosh/i', $ua)) {
            // macOS设备识别
            $deviceModel = 'Mac';
            // 提取macOS版本
            if (preg_match('/Mac OS X (\d+_\d+)/i', $ua, $macMatches)) {
                $macVersion = str_replace('_', '.', $macMatches[1]);
                $systemVersion = get_macos_version_name($macVersion);
            } elseif (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
                $darwinVersion = floatval($darwinMatches[1]);
                $systemVersion = get_macos_version_name($darwinVersion);
            }
        }
        // 提取iOS版本
        if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches) && !preg_match('/Macintosh/i', $ua)) {
            $darwinVersion = floatval($darwinMatches[1]);
            $systemVersion = get_ios_version_name($darwinVersion);
        }
        // 构建优化的苹果设备标识
        if (!empty($deviceModel)) {
            $normalizedUA = $deviceModel;
            if (!empty($systemVersion)) {
                $normalizedUA .= "_{$systemVersion}";
            }
            return $normalizedUA;
        }
    }
    // 1. 代理客户端识别（优先级最高）
    // Shadowrocket设备识别（包括标准格式和自定义格式）
    if (preg_match('/Shadowrocket/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        
        // 处理自定义格式：Shadowrocket_iPhone17,2_iOS17.0（需要转换设备标识符）- 优先级更高
        if (preg_match('/^Shadowrocket_(iPhone\d+,\d+)_(iOS\d+\.\d+)$/i', $ua, $customMatches)) {
            $deviceIdentifier = $customMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
            $systemVersion = $customMatches[2];
            if (!empty($deviceModel)) {
                return $deviceModel . '_' . $systemVersion;
            }
        }
        
        // 处理自定义格式：Shadowrocket_iPhone12ProMax_iOS17.0
        if (preg_match('/^Shadowrocket_(iPhone[^_]+)_(iOS\d+\.\d+)$/i', $ua, $customMatches)) {
            return $customMatches[1] . '_' . $customMatches[2];
        }
        
        // 标准Shadowrocket格式识别
        if (preg_match('/Shadowrocket\/(\d+)/i', $ua, $matches)) {
            $version = $matches[1];
            // iOS设备识别
            if (preg_match('/iPhone(\d+,\d+)/i', $ua, $deviceMatches)) {
                $deviceIdentifier = 'iPhone' . $deviceMatches[1];
                $deviceModel = get_ios_device_name($deviceIdentifier);
            } elseif (preg_match('/iPad(\d+,\d+)/i', $ua, $deviceMatches)) {
                $deviceIdentifier = 'iPad' . $deviceMatches[1];
                $deviceModel = get_ios_device_name($deviceIdentifier);
            } elseif (preg_match('/iPod(\d+,\d+)/i', $ua, $deviceMatches)) {
                $deviceIdentifier = 'iPod' . $deviceMatches[1];
                $deviceModel = get_ios_device_name($deviceIdentifier);
            } elseif (preg_match('/Macintosh/i', $ua)) {
                $deviceModel = get_computer_device_name($ua);
            }
            // 提取系统版本
            if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
                $darwinVersion = floatval($darwinMatches[1]);
                $systemVersion = get_ios_version_name($darwinVersion);
            }
            // 构建标准化的设备标识
            if (!empty($deviceModel)) {
                $normalizedUA = $deviceModel;
                if (!empty($systemVersion)) {
                    $normalizedUA .= "_{$systemVersion}";
                }
                return $normalizedUA;
            } else {
                // 如果没有找到具体设备型号，返回Shadowrocket标识
                $normalizedUA = 'Shadowrocket';
                if (!empty($systemVersion)) {
                    $normalizedUA .= "_{$systemVersion}";
                }
                return $normalizedUA;
            }
        }
    }
    // Clash设备识别 - 修复安卓设备被错误识别为Windows的问题
    // 优先检查安卓设备，避免被误识别为Windows
    if (preg_match('/ClashMetaForAndroid|ClashforAndroid/i', $ua)) {
        $clashType = '';
        $deviceModel = '';
        $systemVersion = '';
        
        if (preg_match('/ClashMetaForAndroid/i', $ua)) {
            $clashType = 'Android_Meta';
        } elseif (preg_match('/ClashforAndroid/i', $ua)) {
            $clashType = 'Android';
        }
        
        $deviceModel = get_android_device_name($ua);
        // 提取Android版本
        if (preg_match('/Android (\d+)/i', $ua, $androidMatches)) {
            $systemVersion = get_android_version_name($androidMatches[1]);
        }
        
        $normalizedUA = "Clash_{$clashType}";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    
    // 检查Windows Clash客户端
    if (preg_match('/ClashforWindows/i', $ua)) {
        // 提取版本号
        $version = '';
        if (preg_match('/ClashforWindows\/([^\/\s]+)/i', $ua, $matches)) {
            $version = $matches[1];
        }
        
        // 构建标准化UA
        $normalizedUA = "Clash_Windows_PC_电脑端";
        if (!empty($version)) {
            $normalizedUA .= "_v{$version}";
        }
        return $normalizedUA;
    }
    
    // 优先检查FlClash客户端
    if (preg_match('/FlClash/i', $ua)) {
        // 提取FlClash版本号
        $version = '';
        if (preg_match('/FlClash\/v([^\/\s]+)/i', $ua, $matches)) {
            $version = $matches[1];
        }
        
        // 提取平台信息
        $platform = '';
        if (preg_match('/Platform\/([^\/\s]+)/i', $ua, $matches)) {
            $platform = strtolower($matches[1]);
        }
        
        // 构建FlClash标准化UA
        $normalizedUA = 'FlClash';
        if (!empty($version)) {
            $normalizedUA .= "/v{$version}";
        }
        if (!empty($platform)) {
            $normalizedUA .= "_{$platform}_pc";
        }
        return $normalizedUA;
    }
    
    // 检查其他Clash客户端（如Clash Meta等）
    if (preg_match('/Clash(?:Meta)?\/([^\/\s]+)/i', $ua, $matches)) {
        $clashType = '';
        $deviceModel = '';
        $systemVersion = '';
        
        // 根据UA内容判断设备类型
        if (preg_match('/Android/i', $ua)) {
            $clashType = 'Android';
            $deviceModel = get_android_device_name($ua);
            if (preg_match('/Android (\d+)/i', $ua, $androidMatches)) {
                $systemVersion = get_android_version_name($androidMatches[1]);
            }
        } elseif (preg_match('/Windows|NT/i', $ua)) {
            $clashType = 'Windows';
            $deviceModel = get_computer_device_name($ua);
        } elseif (preg_match('/Macintosh|Darwin/i', $ua)) {
            $clashType = 'Mac';
            $deviceModel = get_computer_device_name($ua);
        } else {
            // 默认情况下，如果无法确定设备类型，不进行标准化处理
            return $ua;
        }
        
        $normalizedUA = "Clash_{$clashType}";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // Clash Meta 简单识别（处理 clash.meta 和 clash.meta/version 格式）
    if (preg_match('/^clash\.meta(?:\/([^\/\s]+))?$/i', $ua, $matches)) {
        $version = isset($matches[1]) ? $matches[1] : '';
        $deviceModel = 'PC'; // 默认认为是PC客户端
        $systemVersion = '';
        // 检测操作系统
        if (preg_match('/Macintosh|Darwin/i', $ua)) {
            $deviceModel = 'Mac';
            if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
                $darwinVersion = floatval($darwinMatches[1]);
                $systemVersion = get_macos_version_name($darwinVersion);
            }
        } elseif (preg_match('/Windows NT|windows/i', $ua)) {
            $deviceModel = 'PC';
            if (preg_match('/Windows NT (\d+\.\d+)/i', $ua, $windowsMatches)) {
                $systemVersion = get_windows_version_name($windowsMatches[1]);
            }
        } elseif (preg_match('/Linux/i', $ua)) {
            $deviceModel = 'PC';
            if (preg_match('/Ubuntu/i', $ua)) {
                $systemVersion = 'Ubuntu';
            } elseif (preg_match('/CentOS/i', $ua)) {
                $systemVersion = 'CentOS';
            } else {
                $systemVersion = 'Linux';
            }
        }
        $normalizedUA = "ClashMeta";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        if (!empty($version)) {
            $normalizedUA .= "_{$version}";
        }
        return $normalizedUA;
    }
    // V2ray设备识别
    if (preg_match('/V2rayU|V2rayNG|V2rayX|v2rayN|^V2ray(?!-core)/i', $ua, $matches)) {
        $v2rayType = '';
        $deviceModel = '';
        $systemVersion = '';
        if (preg_match('/V2rayU/i', $ua)) {
            $v2rayType = 'Mac';
            $deviceModel = get_computer_device_name($ua);
        } elseif (preg_match('/V2rayNG/i', $ua)) {
            $v2rayType = 'Android';
            $deviceModel = get_android_device_name($ua);
            if (preg_match('/Android (\d+)/i', $ua, $androidMatches)) {
                $systemVersion = get_android_version_name($androidMatches[1]);
            }
        } elseif (preg_match('/V2rayX/i', $ua)) {
            $v2rayType = 'Mac_Old';
            $deviceModel = get_computer_device_name($ua);
        } elseif (preg_match('/v2rayN/i', $ua)) {
            // 提取v2rayN版本号
            $version = '';
            if (preg_match('/v2rayN\/([^\s]+)/i', $ua, $versionMatches)) {
                $version = $versionMatches[1];
            }
            $v2rayType = 'Windows';
            $deviceModel = 'PC'; // 固定为PC，避免Unknown
            
            // 构建标准化UA：v2rayN/版本号_Windows_PC
            if (!empty($version)) {
                $normalizedUA = "v2rayN/{$version}_{$v2rayType}_{$deviceModel}";
            } else {
                $normalizedUA = "v2rayN_{$v2rayType}_{$deviceModel}";
            }
            return $normalizedUA;
        }
        $normalizedUA = "V2ray_{$v2rayType}";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // Quantumult设备识别
    if (preg_match('/Quantumult/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        if (preg_match('/iPhone(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPhone' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        } elseif (preg_match('/iPad(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPad' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        }
        if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
            $darwinVersion = floatval($darwinMatches[1]);
            $systemVersion = get_ios_version_name($darwinVersion);
        }
        $normalizedUA = "Quantumult";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // Surge设备识别
    if (preg_match('/Surge/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        if (preg_match('/iPhone(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPhone' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        } elseif (preg_match('/iPad(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPad' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        } elseif (preg_match('/Macintosh/i', $ua)) {
            $deviceModel = get_computer_device_name($ua);
        }
        if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
            $darwinVersion = floatval($darwinMatches[1]);
            $systemVersion = get_ios_version_name($darwinVersion);
        }
        $normalizedUA = "Surge";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // Loon设备识别
    if (preg_match('/Loon/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        if (preg_match('/iPhone(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPhone' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        } elseif (preg_match('/iPad(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPad' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        }
        if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
            $darwinVersion = floatval($darwinMatches[1]);
            $systemVersion = get_ios_version_name($darwinVersion);
        }
        $normalizedUA = "Loon";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // Potatso设备识别
    if (preg_match('/Potatso/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        if (preg_match('/iPhone(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPhone' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        } elseif (preg_match('/iPad(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPad' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        }
        if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
            $darwinVersion = floatval($darwinMatches[1]);
            $systemVersion = get_ios_version_name($darwinVersion);
        }
        $normalizedUA = "Potatso";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // Mihomo Party 设备识别（优先识别）
    if (preg_match('/mihomo\.party|mihomo\/v\d+\.\d+\.\d+|mihomo.*party/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        // Mihomo Party 通常是电脑客户端，默认设置为PC
        $deviceModel = 'PC';
        // 检测操作系统
        if (preg_match('/Macintosh|Darwin/i', $ua)) {
            $deviceModel = 'Mac';
            if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
                $darwinVersion = floatval($darwinMatches[1]);
                $systemVersion = get_macos_version_name($darwinVersion);
            }
        } elseif (preg_match('/Windows NT|windows/i', $ua)) {
            $deviceModel = 'PC';
            if (preg_match('/Windows NT (\d+\.\d+)/i', $ua, $windowsMatches)) {
                $systemVersion = get_windows_version_name($windowsMatches[1]);
            }
        } elseif (preg_match('/Linux/i', $ua)) {
            $deviceModel = 'PC';
            if (preg_match('/Ubuntu/i', $ua)) {
                $systemVersion = 'Ubuntu';
            } elseif (preg_match('/CentOS/i', $ua)) {
                $systemVersion = 'CentOS';
            } else {
                $systemVersion = 'Linux';
            }
        }
        $normalizedUA = "MihomoParty";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // Sparkle设备识别（优先识别）
    if (preg_match('/Sparkle|sparkle|^sparkle/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        // 检测操作系统
        if (preg_match('/Macintosh|Darwin/i', $ua)) {
            $deviceModel = 'Mac';
            if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
                $darwinVersion = floatval($darwinMatches[1]);
                $systemVersion = get_macos_version_name($darwinVersion);
            }
        } elseif (preg_match('/Windows NT|windows/i', $ua)) {
            $deviceModel = 'PC';
            if (preg_match('/Windows NT (\d+\.\d+)/i', $ua, $windowsMatches)) {
                $systemVersion = get_windows_version_name($windowsMatches[1]);
            }
        } elseif (preg_match('/Linux/i', $ua)) {
            $deviceModel = 'PC';
            if (preg_match('/Ubuntu/i', $ua)) {
                $systemVersion = 'Ubuntu';
            } elseif (preg_match('/CentOS/i', $ua)) {
                $systemVersion = 'CentOS';
            } else {
                $systemVersion = 'Linux';
            }
        }
        $normalizedUA = "Sparkle";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // 通用Mihomo识别（不包含party）
    if (preg_match('/Mihomo|mihomo/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        // 检测操作系统
        if (preg_match('/Macintosh|Darwin/i', $ua)) {
            $deviceModel = 'Mac';
            if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
                $darwinVersion = floatval($darwinMatches[1]);
                $systemVersion = get_macos_version_name($darwinVersion);
            }
        } elseif (preg_match('/Windows NT/i', $ua)) {
            $deviceModel = 'PC';
            if (preg_match('/Windows NT (\d+\.\d+)/i', $ua, $windowsMatches)) {
                $systemVersion = get_windows_version_name($windowsMatches[1]);
            }
        } elseif (preg_match('/Linux/i', $ua)) {
            $deviceModel = 'PC';
            if (preg_match('/Ubuntu/i', $ua)) {
                $systemVersion = 'Ubuntu';
            } elseif (preg_match('/CentOS/i', $ua)) {
                $systemVersion = 'CentOS';
            } else {
                $systemVersion = 'Linux';
            }
        }
        $normalizedUA = "Mihomo";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // 通用HTTP客户端识别（可能是各种代理客户端）
    if (preg_match('/Go-http-client|curl|wget|HTTP-Client|clash-verge|HiddifyNext|FlClash|sing-box|v2ray-core|shadowsocks/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        // 检查是否是macOS系统
        if (preg_match('/Macintosh|Darwin/i', $ua)) {
            $deviceModel = 'Mac';
            if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
                $darwinVersion = floatval($darwinMatches[1]);
                $systemVersion = get_macos_version_name($darwinVersion);
            }
        }
        // 检查是否是Windows系统
        elseif (preg_match('/Windows NT|windows/i', $ua)) {
            $deviceModel = 'PC';
            if (preg_match('/Windows NT (\d+\.\d+)/i', $ua, $windowsMatches)) {
                $systemVersion = get_windows_version_name($windowsMatches[1]);
            }
        }
        // 检查是否是Linux系统
        elseif (preg_match('/Linux/i', $ua)) {
            $deviceModel = 'PC';
            if (preg_match('/Ubuntu/i', $ua)) {
                $systemVersion = 'Ubuntu';
            } elseif (preg_match('/CentOS/i', $ua)) {
                $systemVersion = 'CentOS';
            } else {
                $systemVersion = 'Linux';
            }
        }
        // 根据具体客户端类型返回不同的标识
        if (preg_match('/FlClash/i', $ua)) {
            // 提取FlClash版本号
            $version = '';
            if (preg_match('/FlClash\/v([^\/\s]+)/i', $ua, $matches)) {
                $version = $matches[1];
            }
            
            // 提取平台信息
            $platform = '';
            if (preg_match('/Platform\/([^\/\s]+)/i', $ua, $matches)) {
                $platform = strtolower($matches[1]);
            }
            
            // 构建FlClash标准化UA
            $clientType = 'FlClash';
            if (!empty($version)) {
                $clientType .= "/v{$version}";
            }
            if (!empty($platform)) {
                $clientType .= "_{$platform}_pc";
            }
        } elseif (preg_match('/clash-verge/i', $ua)) {
            $clientType = 'ClashVerge';
        } elseif (preg_match('/HiddifyNext/i', $ua)) {
            $clientType = 'HiddifyNext';
        } elseif (preg_match('/sing-box/i', $ua)) {
            $clientType = 'SingBox';
        } elseif (preg_match('/v2ray-core/i', $ua)) {
            $clientType = 'V2rayCore';
        } elseif (preg_match('/shadowsocks/i', $ua)) {
            $clientType = 'Shadowsocks';
        } else {
            $clientType = 'HTTPClient';
        }
        
        // 对于FlClash，直接返回已经构建好的标准化UA
        if (preg_match('/FlClash/i', $ua)) {
            return $clientType;
        }
        
        $normalizedUA = $clientType;
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // 通用macOS系统识别（处理非常简单的UA）
    if (preg_match('/Macintosh|Darwin/i', $ua) && strlen($ua) < 50) {
        // 如果UA很短且包含macOS标识，可能是Sparkle
        $deviceModel = 'Mac';
        $systemVersion = '';
        if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
            $darwinVersion = floatval($darwinMatches[1]);
            $systemVersion = get_macos_version_name($darwinVersion);
        }
        return "Sparkle_{$deviceModel}" . (!empty($systemVersion) ? "_{$systemVersion}" : '');
    }
    // 2. 移动设备识别
    // Android设备识别
    if (preg_match('/Android/i', $ua)) {
        $deviceModel = get_android_device_name($ua);
        $systemVersion = '';
        if (preg_match('/Android (\d+)/i', $ua, $androidMatches)) {
            $systemVersion = get_android_version_name($androidMatches[1]);
        }
        $normalizedUA = $deviceModel;
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // iOS设备识别
    if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
        $deviceModel = '';
        $systemVersion = '';
        if (preg_match('/iPhone(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPhone' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        } elseif (preg_match('/iPad(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPad' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        } elseif (preg_match('/iPod(\d+,\d+)/i', $ua, $deviceMatches)) {
            $deviceIdentifier = 'iPod' . $deviceMatches[1];
            $deviceModel = get_ios_device_name($deviceIdentifier);
        }
        if (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
            $darwinVersion = floatval($darwinMatches[1]);
            $systemVersion = get_ios_version_name($darwinVersion);
        }
        $normalizedUA = $deviceModel ?: 'iOS_Device';
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // 3. 电脑设备识别
    // Windows设备识别
    if (preg_match('/Windows NT/i', $ua)) {
        $deviceModel = get_computer_device_name($ua);
        $systemVersion = '';
        if (preg_match('/Windows NT (\d+\.\d+)/i', $ua, $windowsMatches)) {
            $systemVersion = get_windows_version_name($windowsMatches[1]);
        }
        $normalizedUA = $deviceModel;
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // macOS设备识别
    if (preg_match('/Macintosh|Darwin/i', $ua)) {
        $deviceModel = get_computer_device_name($ua);
        $systemVersion = '';
        if (preg_match('/Mac OS X (\d+_\d+)/i', $ua, $macMatches)) {
            $macVersion = str_replace('_', '.', $macMatches[1]);
            $systemVersion = get_macos_version_name($macVersion);
        } elseif (preg_match('/Darwin\/(\d+\.\d+)/i', $ua, $darwinMatches)) {
            $darwinVersion = floatval($darwinMatches[1]);
            $systemVersion = get_macos_version_name($darwinVersion);
        }
        // 检查是否是Sparkle相关的请求
        if (preg_match('/Go-http-client|curl|wget|HTTP-Client/i', $ua)) {
            $normalizedUA = "Sparkle_{$deviceModel}";
        } else {
                            // 对于macOS系统，直接识别为macOS设备，不根据URL判断
                $normalizedUA = $deviceModel;
        }
        if (!empty($systemVersion)) {
            $normalizedUA .= "_{$systemVersion}";
        }
        return $normalizedUA;
    }
    // Linux设备识别
    if (preg_match('/Linux/i', $ua)) {
        $deviceModel = get_computer_device_name($ua);
        return $deviceModel;
    }
    // 4. 浏览器识别
    if (preg_match('/Mozilla|Chrome|Safari|Edge|Trident|Firefox|MSIE/i', $ua)) {
        $browser = '';
        if (preg_match('/Chrome/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/Firefox/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Edge/i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/MSIE|Trident/i', $ua)) {
            $browser = 'IE';
        } else {
            $browser = 'Browser';
        }
        $deviceModel = '';
        if (preg_match('/Windows NT/i', $ua)) {
            $deviceModel = get_computer_device_name($ua);
        } elseif (preg_match('/Macintosh/i', $ua)) {
            $deviceModel = get_computer_device_name($ua);
        } elseif (preg_match('/Android/i', $ua)) {
            $deviceModel = get_android_device_name($ua);
        } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $deviceModel = 'iOS_Device';
        }
        // 特殊处理：如果是从订阅链接访问的浏览器UA，可能是Sparkle
        // 检查是否是macOS系统且访问的是订阅相关URL
        if (preg_match('/Macintosh/i', $ua) && isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            // 如果访问的是订阅相关的URL，可能是Sparkle使用浏览器引擎
            if (preg_match('/\/u\/|subscription|clash|yaml|json/i', $requestUri)) {
                $systemVersion = '';
                if (preg_match('/Mac OS X (\d+_\d+)/i', $ua, $macMatches)) {
                    $macVersion = str_replace('_', '.', $macMatches[1]);
                    $systemVersion = get_macos_version_name($macVersion);
                }
                return "Sparkle_Mac" . (!empty($systemVersion) ? "_{$systemVersion}" : '');
            }
        }
        $normalizedUA = "Browser_{$browser}";
        if (!empty($deviceModel)) {
            $normalizedUA .= "_{$deviceModel}";
        }
        return $normalizedUA;
    }
    return 'Unknown';
}

    /**
     * 生成设备指纹（用于设备识别）
     * @param string $ua User-Agent字符串
     * @param array $additionalHeaders 额外的HTTP头信息
     * @return string 设备指纹
     */
    function generate_device_fingerprint($ua, $additionalHeaders = []) {
        $normalizedUA = parse_and_normalize_ua($ua);
        // 收集其他可用于设备识别的信息
        $fingerprintComponents = [
            $normalizedUA,
            $additionalHeaders['accept'] ?? '',
            $additionalHeaders['accept_language'] ?? '',
            $additionalHeaders['accept_encoding'] ?? '',
            // 可以添加更多组件，如屏幕分辨率、时区等
        ];
        // 过滤空值并生成指纹
        $fingerprintComponents = array_filter($fingerprintComponents);
        return md5(implode('|', $fingerprintComponents));
    }
    /**
     * 生成稳定的设备指纹（解决电脑设备重复UA问题）
     * @param string $ua User-Agent字符串
     * @param array $additionalHeaders 额外的HTTP头信息
     * @param string $qq 用户QQ号（用于区分不同用户）
     * @return string 稳定的设备指纹
     */
    function generate_stable_device_fingerprint($ua, $additionalHeaders = [], $qq = '') {
        $normalizedUA = parse_and_normalize_ua($ua);
        // 对于电脑设备，添加更多稳定标识符
        $isComputer = false;
        if (preg_match('/Windows NT|Macintosh|Linux/i', $ua)) {
            $isComputer = true;
        }
        // 特殊处理：某些客户端即使没有系统标识，也是电脑客户端
        if (preg_match('/mihomo\.party|Sparkle|clash-verge|HiddifyNext|FlClash/i', $ua)) {
            $isComputer = true;
        }
        if ($isComputer) {
            // 电脑设备使用更稳定的指纹生成方式
            $fingerprintComponents = [
                'user' => $qq, // 添加用户标识
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '', // 保留IP地址
                // 移除HTTP头信息，因为它们可能因软件而异
                // 只保留最稳定的标识符
            ];
            // 对于电脑设备，使用更长的哈希来减少冲突
            $fingerprintString = implode('|', $fingerprintComponents);
            return hash('sha256', $fingerprintString);
        } else {
            // 移动设备使用原有的指纹生成方式
            return generate_device_fingerprint($ua, $additionalHeaders);
        }
    }