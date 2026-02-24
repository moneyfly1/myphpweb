<?php
return array(
    'LOG_RECORD'            => true,   // 开启日志记录
    'LOG_TYPE'              => 'File', // 日志记录类型为文件
    'LOG_PATH'              => './Application/Runtime/Logs/', // 日志存储路径
    'LOG_FILE_SIZE'         => 2097152, // 日志文件大小限制
    'LOG_EXCEPTION_RECORD'  => true,    // 是否记录异常信息日志
    'LOG_LEVEL'             => 'EMERG,ALERT,CRIT,ERR,WARN,NOTICE,INFO,DEBUG,SQL', // 允许记录的日志级别
);
