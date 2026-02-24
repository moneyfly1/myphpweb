<?php
return array(

//*************************************数据库设置*************************************
    'DB_TYPE'               =>  env('DB_TYPE', 'mysqli'),                 // 数据库类型
    'DB_HOST'               =>  env('DB_HOST', 'localhost'),     // 服务器地址
    'DB_NAME'               =>  env('DB_NAME', 'database'),     // 数据库名
    'DB_USER'               =>  env('DB_USER', 'username'),     // 用户名
    'DB_PWD'                =>  env('DB_PASSWORD', ''),      // 密码
    'DB_PORT'               =>  env('DB_PORT', '3306'),     // 端口
    'DB_PREFIX'             =>  env('DB_PREFIX', ''),   // 数据库表前缀
);
