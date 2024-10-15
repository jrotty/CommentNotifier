<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require dirname(__FILE__) . '/PHPMailer/PHPMailer.php';
require dirname(__FILE__) . '/PHPMailer/SMTP.php';
require dirname(__FILE__) . '/PHPMailer/Exception.php';

include('config.php');

        if(!isset($_REQUEST['auth'])||$_REQUEST['auth']!=$info['auth']){
            echo '密钥不正确';
        }else{
        try {
            // Server settings
            $mail = new PHPMailer(false);
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            $mail->isSMTP();
            $mail->Host = $info['Host']; // SMTP 服务地址
            $mail->SMTPAuth = true; // 开启认证
            $mail->Username = $info['Username']; // SMTP 用户名
            $mail->Password = $info['Password']; // SMTP 密码
            $mail->SMTPSecure = $info['SMTPSecure']; // SMTP 加密类型 'ssl' or 'tls'.
            $mail->Port = $info['Port']; // SMTP 端口

            $mail->setFrom($info['from'], $info['fromName']);
            $mail->addAddress($_REQUEST['to'], $_REQUEST['fromName']); // 收件人
            
            $mail->Subject =$_REQUEST['subject'];

            $mail->isHTML(); // 邮件为HTML格式
            // 邮件内容
            $content = $_REQUEST['html'];
            $mail->Body = $content;
            $mail->send();


                $at = date('Y-m-d H:i:s');
                if ($mail->isError()) {
                    $data = $at . ' ' . $mail->ErrorInfo; // 记录发信失败的日志
                } else { // 记录发信成功的日志
                    $data = PHP_EOL . $at . ' 发送成功!! ';
                    $data .= ' 发件人:' . $info['fromName'];
                    $data .= ' 发件邮箱:' . $info['from'];
                    $data .= ' 接收邮箱:' . $_REQUEST['to'];
                    $data .= ' 接收人:' . $_REQUEST['fromName'] . PHP_EOL;
                }
                echo $data;

        } catch (Exception $e) {
            $str = "\nerror time: " . date('Y-m-d H:i:s') . "\n";
            echo $str.$e."\n";
        }
    }


?>