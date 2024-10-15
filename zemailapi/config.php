<?php
$info=array(
    'auth'=>'',//api秘钥，设置好后，你的api地址就是：https://域名/zemailapi/?auth=n你得秘钥地址
    'Host' =>'smtp.qq.com', // SMTP 服务地址,QQ邮箱为：smtp.qq.com
    'Username' =>'xxxx@qq.com',// SMTP 用户名一般就是邮箱地址
    'Password'=>'',// SMTP 密码
    'SMTPSecure' =>'ssl',// SMTP 加密类型 'ssl' or 'tls'.
    'Port' =>'465', // SMTP 端口 默认25 SSL为465 TLS为587
    'from'=>'xxxx@qq.com',//发件邮箱地址
    'fromName'=>'昵称',//发件人昵称
    );
?>