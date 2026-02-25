<?php
return array(
	//'配置项'=>'配置值'
	'URL_MODEL'          => '2',    //URL模式
	'URL_ROUTER_ON'         =>  true,   // 是否开启URL路由
	'URL_ROUTE_RULES'       =>  array(
		// ':a/:b/:c'    => 'Index/index',
		'createShortUrl' => 'Index/createShortUrl',
		'test' => 'Index/test',
		'test1' => 'Index/test1',
		'login' => 'User/login',
		'reg' => 'User/reg',
		'verify' => 'User/verify',
		'active' => 'User/active',
		'outlogin' => 'User/outlogin',
		'reseturl' => 'Index/resetUrl',
		'resetpass' => 'User/resetpass',
		'respass' => 'User/respass',
		'getpass' => 'User/getpass',
		'sendVerifyCode' => 'User/sendVerifyCode',
		'sendResetCode' => 'User/sendResetCode',
		'tc' => 'Order/tc',
		'pay' => 'Order/pay',
		'notify' => 'Order/notify',
		'return' => 'Order/payReturn',
		'qx' => 'Order/qx',
		'checkDingyue' => 'User/checkDingyue',
		'checkDingyues' => 'User/checkDingyues',
		'send' => 'Email/send', // 明确定义邮件发送路由
		'Help' => 'Help/index', // 帮助文档页面路由
		'Node' => 'Node/index', // 节点页面路由
		'getDeviceList' => 'Index/getDeviceList', // 获取设备列表路由
		'removeDevice' => 'Index/removeDevice', // 移除设备路由
		'cleanOldDevices' => 'Index/cleanOldDevices', // 清理旧设备路由
		'welcome' => 'Index/welcome', // 统一 layout 示例页
		':short' 	=> 'Index/short',
	)
);