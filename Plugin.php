<?php

namespace TypechoPlugin\CommentNotifier;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Layout;
use Widget\Options;
use Widget\Base\Comments;
use Typecho\Db;
use Typecho\Date;
use Utils\Helper;
use Widget\Feedback;
use Widget\Service;
use Widget\Comments\Edit;

/**
 * typecho 评论通过时发送邮件提醒,要求typecho1.2.0及以上
 * 
 * @package CommentNotifier
 * @author 泽泽社长
 * @version 1.6.3
 * @link https://github.com/jrotty/CommentNotifier
 */

class Plugin implements PluginInterface
{

    /** @var string 控制菜单链接 */
    public static $panel = 'CommentNotifier/console.php';

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return string
     */
    public static function activate()
    {
        Feedback::pluginHandle()->finishComment = __CLASS__ . '::refinishComment'; // 前台提交评论完成接口
        Edit::pluginHandle()->finishComment = __CLASS__ . '::refinishComment'; // 后台操作评论完成接口
        Service::pluginHandle()->send = __CLASS__ . '::send';//异步接口
        
        Edit::pluginHandle()->mark = __CLASS__ . '::mark'; // 后台标记评论状态完成接口
        Helper::addPanel(1, self::$panel, '评论邮件提醒外观', '评论邮件提醒主题列表', 'administrator');
        Helper::addRoute("zemail","/zemail","CommentNotifier_Action",'action');
        return _t('请配置邮箱SMTP选项!');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     */
    public static function deactivate()
    {
        Helper::removePanel(1, self::$panel);
        Helper::removeRoute("zemail");
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Form $form 配置面板
     * @return void
     */
    public static function config(Form $form)
    {
        ?>
        <style>
            .aliyun,.smtp{display:none;}
        </style>
        <script>
window.onload = function () {
if($("#tuisongtype :radio:checked").val()=='aliyun')
{
        $('.aliyun').show(); 
        $('.smtp').hide(); 
        }else{
        $('.aliyun').hide(); 
        $('.smtp').show();  
}
$('#tuisongtype input').click(function(){
if($("#tuisongtype :radio:checked").val()=='aliyun')
{
        $('.aliyun').show(); 
        $('.smtp').hide(); 
        }else{
        $('.aliyun').hide(); 
        $('.smtp').show();  
}
     });
}
        </script>
        <?php
        // 记录log
        $log = new Form\Element\Checkbox('log', array('ok' => _t('记录日志')), [], _t('记录日志'), _t('启用后将当前目录生成一个log.txt 注:目录需有写入权限'));
        $form->addInput($log->multiMode());
        
        $yibu = new Form\Element\Radio('yibu', array('0' => _t('不启用'), '1' => _t('启用'),), '0', _t('异步提交'), _t('异步回调优点就是减小对博客评论提交速度的影响'));
        $form->addInput($yibu);

        // 发信方式
        $tuisongtype = new Form\Element\Radio('tuisongtype', array('smtp' => _t('SMTP'), 'aliyun' => _t('阿里云推送')), 'smtp', _t('邮件推送方式'));
        $form->addInput($tuisongtype);
        $tuisongtype->setAttribute('id', 'tuisongtype');

        $stmplayout = new Layout();
        $stmplayout->html(_t('<h3>邮件SMTP服务配置:</h3>'));
        $form->addItem($stmplayout);
        // SMTP服务地址
        $STMPHost = new Form\Element\Text('STMPHost', NULL, 'smtp.qq.com', _t('SMTP服务器地址'), _t('如:smtp.163.com,smtp.gmail.com,smtp.exmail.qq.com,smtp.sohu.com,smtp.sina.com'));
        $form->addInput($STMPHost);

        // SMTP用户名
        $SMTPUserName = new Form\Element\Text('SMTPUserName', NULL, NULL, _t('SMTP登录用户'), _t('SMTP登录用户名，一般为邮箱地址'));
        $form->addInput($SMTPUserName);

        // 发件邮箱
        $from = new Form\Element\Text('from', NULL, NULL, _t('SMTP邮箱地址'), _t('请填写用于发送邮件的邮箱，一般与SMTP登录用户名一致'));
        $form->addInput($from);

        // SMTP密码
        $description = _t('一般为邮箱登录密码, 有特殊如: QQ邮箱有独立的SMTP密码. 可参考: ');
        $description .= '<a href="https://service.mail.qq.com/cgi-bin/help?subtype=1&&no=1001256&&id=28" target="_blank">QQ邮箱</a> ';
        $description .= '<a href="https://mailhelp.aliyun.com/freemail/detail.vm?knoId=6521875" target="_blank">阿里邮箱</a> ';
        $description .= '<a href="https://support.office.com/zh-cn/article/outlook-com-%E7%9A%84-pop%E3%80%81imap-%E5%92%8C-smtp-%E8%AE%BE%E7%BD%AE-d088b986-291d-42b8-9564-9c414e2aa040?ui=zh-CN&rs=zh-CN&ad=CN" target="_blank">Outlook邮箱</a> ';
        $description .= '<a href="http://help.sina.com.cn/comquestiondetail/view/160/" target="_blank">新浪邮箱</a> ';
        $SMTPPassword = new Form\Element\Text('SMTPPassword', NULL, NULL, _t('SMTP登录密码'), $description);
        $form->addInput($SMTPPassword);

        // 服务器安全模式
        $SMTPSecure = new Form\Element\Radio('SMTPSecure', array('' => _t('无安全加密'), 'ssl' => _t('SSL加密'), 'tls' => _t('TLS加密')), '', _t('SMTP加密模式'));
        $form->addInput($SMTPSecure);

        // SMTP server port
        $SMTPPort = new Form\Element\Text('SMTPPort', NULL, '25', _t('SMTP服务端口'), _t('默认25 SSL为465 TLS为587'));
        $form->addInput($SMTPPort);
        $stmplayout->setAttribute('class', 'typecho-option smtp');
        $STMPHost->setAttribute('class', 'typecho-option smtp');
        $SMTPUserName->setAttribute('class', 'typecho-option smtp');
        $from->setAttribute('class', 'typecho-option smtp');
        $SMTPPassword->setAttribute('class', 'typecho-option smtp');
        $SMTPSecure->setAttribute('class', 'typecho-option smtp');
        $SMTPPort->setAttribute('class', 'typecho-option smtp');



        // 阿里云推送区块
        $ali_section = new Layout();
        // 区块标题
        $ali_section->html('<h2>阿里云推送邮件发送设置</h2>');
        $form->addItem($ali_section);
        // 发件邮箱
        $ali_from = new Form\Element\Text('ali_from', NULL, NULL, _t('阿里云邮箱地址'), _t('请填写用于发送邮件的邮箱'));
        $form->addInput($ali_from);
        // 地域选择
        $ali_region = new Form\Element\Select('ali_region', array('hangzhou' => _t('华东1(杭州)'), 'singapore' => _t('亚太东南1(新加坡)'), 'sydney' => _t('亚太东南2(悉尼)')), NULL, _t('DM接入区域'), _t('请选择您的邮件推送所在服务器区域，请务必选择正确'));
        $form->addInput($ali_region);
        // AccessKey ID
        $ali_accesskey_id = new Form\Element\Text('ali_accesskey_id', NULL, NULL, _t('AccessKey ID'), _t('请填入在阿里云生成的AccessKey ID'));
        $form->addInput($ali_accesskey_id);
        // Access Key Secret
        $ali_accesskey_secret = new Form\Element\Text('ali_accesskey_secret', NULL, NULL, _t('Access Key Secret'), _t('请填入在阿里云生成的Access Key Secret'));
        $form->addInput($ali_accesskey_secret);
        $ali_section->setAttribute('class', 'typecho-option aliyun');
        $ali_region->setAttribute('class', 'typecho-option aliyun');
        $ali_from->setAttribute('class', 'typecho-option aliyun');
        $ali_accesskey_id->setAttribute('class', 'typecho-option aliyun');
        $ali_accesskey_secret->setAttribute('class', 'typecho-option aliyun');


        $layout = new Layout();
        $layout->html(_t('<h3>邮件信息配置:</h3>'));
        $form->addItem($layout);

        // 发件人姓名
        $fromName = new Form\Element\Text('fromName', NULL, NULL, _t('发件人姓名'), _t('发件人姓名'));
        $form->addInput($fromName->addRule('required', _t('发件人姓名必填!')));

        // 收件邮箱
        $adminfrom = new Form\Element\Text('adminfrom', NULL, NULL, _t('站长收件邮箱'), _t('遇到待审核评论或文章作者邮箱为空时，评论提醒会发送到此邮箱地址！'));
        $form->addInput($adminfrom->addRule('required', _t('收件邮箱必填!')));
        
        
        $zznotice = new Form\Element\Radio('zznotice', array('0' => _t('通知'), '1' => _t('不通知'),), '0', _t('是否通知站长'), _t('因为站长可能有其他接受评论通知的方式，不想在重复接受邮件通知可选择不通知'));
        $form->addInput($zznotice);
        
        // 表情重载函数
        $biaoqing = new Form\Element\Text('biaoqing', NULL, NULL, _t('表情重载'), _t('请填写您博客主题评论表情函数名，如：parseBiaoQing（我的Plain,Sinner,Dinner,Store主题），Mirages::parseBiaoqing（Mirages主题），（此项非必填项具体函数名请咨询主题作者，填写后邮件提醒将支持显示表情，更换主题后请同步更换此项内容或者删除此项内容）'));
        $form->addInput($biaoqing);
        
        // 模板
        $template = new Form\Element\Text('template', NULL, 'default', _t('邮件模板选择'), _t('该项请不要在插件设置里填写，请到邮件模板列表页面选择模板启动！'));
        $template->setAttribute('class', 'hidden');
        $form->addInput($template);        
        
        $t = new Form\Element\Text(
            'auth',
            null,
            \Typecho\Common::randString(32),
            _t('* 接口保护'),
            _t('加盐保护 API 接口不被滥用，自动生成禁止自行设置。')
        );
        $t->setAttribute('class', 'hidden');
        $form->addInput($t);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Form $form
     * @return void
     */
    public static function personalConfig(Form $form)
    {
    }

    /**
     * 插件实现方法
     *
     * @access public
     * @return void
     */
    public static function render()
    {
    }


    /**
     * @param $comment
     * @return array
     * @throws Typecho_Db_Exception
     * 获取上级评论人
     */
    public static function getParent($comment): array
    {
        $recipients = [];
        $parent = Helper::widgetById('comments', $comment->parent);
        $recipients = [
                'name' => $parent->author,
                'mail' => $parent->mail,
                ];
        return $recipients;
    }

    /**
     * @param $comment
     * @return array
     * @throws Typecho_Db_Exception
     * 获取文章作者邮箱
     */
    public static function getAuthor($comment): array
    {
        $plugin = Options::alloc()->plugin('CommentNotifier');
        $recipients = [];
        $db = Db::get();
        $ae = $db->fetchRow($db->select()->from('table.users')->where('table.users.uid=?', $comment->ownerId));
        if (empty($ae['mail'])) {
            $ae['screenName'] = $plugin->fromName;
            $ae['mail'] = $plugin->adminfrom;
        }
        $recipients = [
            'name' => $ae['screenName'],
            'mail' => $ae['mail'],
        ];
        // 查询
        return $recipients;
    }

    /**
     * @param $comment
     * @param Widget_Comments_Edit $edit
     * @param $status
     * @throws Typecho_Db_Exception
     * @throws Typecho_Plugin_Exception
     * 在后台标记评论状态时的回调
     */
    public static function mark($comment, $edit, $status)
    {
        $recipients = [];
        $plugin = Options::alloc()->plugin('CommentNotifier');
        $from = $plugin->adminfrom; // 站长邮箱
        // 在后台标记评论状态为[approved 审核通过]时, 发信给上级评论人或作者
        if ($status == 'approved') {
            $type = 0;
            // 如果有上级
            if ($edit->parent > 0) {
                $recipients[] = self::getParent($edit);//获取上级评论信息
                $type = 1;
            } else {
                $recipients[] = self::getAuthor($edit);//获取作者信息
            }

            // 如果自己回复自己的评论, 不做任何操作
            if ($recipients[0]['mail'] == $edit->mail) {
                return;
            }
            // 如果上级是博主, 不做任何操作
            if ($recipients[0]['mail'] == $from) {
                return;
            }
            //邮箱为空时就不发邮件
            if (empty($recipients[0]['mail'])) {
                return;
            }

            self::sendMail($edit, $recipients, $type);
        }
    }


    /**
     * @param Widget_Comments_Edit|Widget_Feedback $comment
     * @throws Typecho_Db_Exception
     * @throws Typecho_Plugin_Exception
     * 评论/回复时的回调
     */
    public static function refinishComment($comment)
    {
        $plugin = Options::alloc()->plugin('CommentNotifier');
        $from = $plugin->adminfrom; // 站长邮箱
        $fromName = $plugin->fromName; // 发件人
        $recipients = [];
        // 审核通过
        if ($comment->status == 'approved') {
            $type = 0;//0为无父级评论
            // 不需要发信给博主
            if ($comment->authorId != $comment->ownerId && $comment->mail != $from) {
                $recipients[] = self::getAuthor($comment);//收到新评论后发送给文章作者
            }
            // 如果有上级
            if ($comment->parent) {
                $type = 1;//1为有父级评论
                // 查询上级评论人
                $parent = self::getParent($comment);//获取上级评论者邮箱
                // 如果上级是博主和自己回复自己, 不需要发信
                if ($parent['mail'] != $from && $parent['mail'] != $comment->mail) {
                    $recipients[] = $parent;
                }
            }
            self::sendMail($comment, $recipients, $type);
        } else {
            // 如果所有评论必须经过审核, 通知博主审核评论
            $recipients[] = ['name' => $fromName, 'mail' => $from];
            self::sendMail($comment, $recipients, 2);//2为待审核评论
        }
    }

    /**
     * @param Widget_Comments_Edit|Widget_Feedback $comment
     * @param array $recipients
     * @param $type
     */
    private static function sendMail($comment, array $recipients, $type)
    {
        if (empty($recipients)) return; // 没有收信人
            // 获取系统配置选项
            $options = Options::alloc();
            $plugin = $options->plugin('CommentNotifier');
            if ($type == 1) {
                $Subject = '你在[' . $comment->title . ']的评论有了新的回复';
            } elseif ($type == 2) {
                $Subject = '文章《' . $comment->title . '》有条待审评论';
            } else {
                $Subject = '你的《' . $comment->title . '》文章有了新的评论';
            }
            foreach ($recipients as $recipient) {
            $param['to']=$recipient['mail']; // 收件地址
            $param['fromName']=$plugin->fromName; // 收件人名称
            $param['subject']=$Subject; // 邮件标题
            $param['html']=self::mailBody($comment, $options, $type); // 邮件内容
            self::resendMail($param);
        }
    }

public static function resendMail($param)
    {
        // 获取系统配置选项
        $options = Options::alloc();
        $plugin = $options->plugin('CommentNotifier');
        if($plugin->zznotice==1&&$param['to']==$plugin->adminfrom){return;}//不通知站长邮箱
        
        if($plugin->yibu==1){
        Helper::requestService('send', $param);
        }else{
        self::send($param);
        }
    }
public static function send($param){
     // 获取系统配置选项
    $options = Options::alloc();
    $plugin = $options->plugin('CommentNotifier');
    
    
    if($plugin->tuisongtype=='aliyun'){
        self::aliyun($param);
    }else{
        self::zemail($param);
    }
    
}
   
    
public static function zemail($param)
    {   // 获取系统配置选项
        $options = Options::alloc();
        // 获取插件配置
        $plugin = $options->plugin('CommentNotifier');
        
        //api地址
        $rewrite='';if(Helper::options()->rewrite==0){$rewrite='index.php/';}
        $apiurl=Helper::options()->siteUrl.$rewrite.'zemail';
        
        $param['auth']=$plugin->auth;//密钥
        // 初始化Curl
        $ch = curl_init();
        // 设置为POST请求
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        // 请求地址
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        // 开启非阻塞模式
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        // 返回数据
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        // 提交参数
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        // 关闭ssl验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        // 执行请求
        $result = curl_exec($ch);
        // 获取错误代码
        $errno = curl_errno($ch);
        // 获取错误信息
        $error = curl_error($ch);
        // 获取返回状态码
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // 关闭请求
        curl_close($ch);
        // 成功标识
        $flag = TRUE;
        // 如果开启了Debug
        if ($plugin->log) {
            // 记录时间
            $log = '[Zemail] ' . date('Y-m-d H:i:s') . ': ' . PHP_EOL;
            // 如果失败
            if ( $errno ) {
                // 设置失败
                $flag = FALSE;
                $log .= _t('邮件发送失败, 错误代码：' . $errno . '，错误提示: ' . $error . PHP_EOL);
            }
            // 如果失败
            if ( 400 <= $httpCode ) {
                // 设置失败
                $flag = FALSE;
                // 尝试转换json
                if ( $json = json_decode($result) ) {
                    $log .= _t('邮件发送失败，错误代码：' . $json->Code . '，错误提示：' . $json->Message . PHP_EOL);
                } else {
                    $log .= _t('邮件发送失败, 请求返回HTTP Code：' . $httpCode . PHP_EOL);
                }
            }
            // 记录返回值
            $log .= _t('邮件发送返回数据：' . serialize($result) . PHP_EOL);
            // 输出分隔
            $log .= '-------------------------------------------' . PHP_EOL;
            // 写入文件
            file_put_contents(dirname(__FILE__) . '/log.txt', "\n".$log."\n", FILE_APPEND);
        }
        // 返回结果
        return $flag;
    }


    /**
     * 阿里云邮件发送
     *
     * @static
     * @access public
     *
     * @param array $param 公共参数
     *
     * @return bool|string
     * @throws Typecho_Plugin_Exception
     */
    public static function aliyun($param)
    {   // 获取系统配置选项
        $options = Options::alloc();
        // 获取插件配置
        $plugin = $options->plugin('CommentNotifier');
        // 判断当前请求区域
        switch ( $plugin->ali_region ) {
            case 'hangzhou': // 杭州
                // API地址
                $param['api'] = 'https://dm.aliyuncs.com/';
                // API版本号
                $param['version'] = '2015-11-23';
                // 机房信息
                $param['region'] = 'cn-hangzhou';
                break;
            case 'singapore': // 新加坡
                // API地址
                $param['api'] = 'https://dm.ap-southeast-1.aliyuncs.com/';
                 // API版本号
                $param['version'] = '2017-06-22';
                 // 机房信息
                $param['region'] = 'ap-southeast-1';
                break;
            case 'sydney': // 悉尼
                // API地址
                $param['api'] = 'https://dm.ap-southeast-2.aliyuncs.com/';
                // API版本号
                $param['version'] = '2017-06-22';
                // 机房信息
                $param['region'] = 'ap-southeast-2';
                break;
            }
        // 重新组合为阿里云所使用的参数
        $data = array(
            'Action' => 'SingleSendMail', // 操作接口名
            'AccountName' => $plugin->ali_from, // 发件地址
            'ReplyToAddress' => "true", // 回信地址
            'AddressType' => 1, // 地址类型
            'ToAddress' => $param['to'], // 收件地址
            'FromAlias' => $param['fromName'], // 发件人名称
            'Subject' => $param['subject'], // 邮件标题
            'HtmlBody' => $param['html'], // 邮件内容
            'Format' => 'JSON', // 返回JSON
            'Version' => $param['version'], // API版本号
            'AccessKeyId' => $plugin->ali_accesskey_id, // Access Key ID
            'SignatureMethod' => 'HMAC-SHA1', // 签名方式
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'), // 请求时间
            'SignatureVersion' => '1.0', // 签名算法版本
            'SignatureNonce' => md5(time()), // 唯一随机数
            'RegionId' => $param['region'] // 机房信息
        );
        // 请求签名
        $data['Signature'] = self::sign($data, $plugin->ali_accesskey_secret);
        // 初始化Curl
        $ch = curl_init();
        // 设置为POST请求
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        // 请求地址
        curl_setopt($ch, CURLOPT_URL, $param['api']);
        // 开启非阻塞模式
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        // 返回数据
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        // 提交参数
        curl_setopt($ch, CURLOPT_POSTFIELDS, self::getPostHttpBody($data));
        // 关闭ssl验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        // 执行请求
        $result = curl_exec($ch);
        // 获取错误代码
        $errno = curl_errno($ch);
        // 获取错误信息
        $error = curl_error($ch);
        // 获取返回状态码
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // 关闭请求
        curl_close($ch);
        // 成功标识
        $flag = TRUE;
        // 如果开启了Debug
        if ($plugin->log) {
            // 记录时间
            $log = '[Aliyun] ' . date('Y-m-d H:i:s') . ': ' . PHP_EOL;
            // 如果失败
            if ( $errno ) {
                // 设置失败
                $flag = FALSE;
                $log .= _t('邮件发送失败, 错误代码：' . $errno . '，错误提示: ' . $error . PHP_EOL);
            }
            // 如果失败
            if ( 400 <= $httpCode ) {
                // 设置失败
                $flag = FALSE;
                // 尝试转换json
                if ( $json = json_decode($result) ) {
                    $log .= _t('邮件发送失败，错误代码：' . $json->Code . '，错误提示：' . $json->Message . PHP_EOL);
                } else {
                    $log .= _t('邮件发送失败, 请求返回HTTP Code：' . $httpCode . PHP_EOL);
                }
            }
            // 记录返回值
            $log .= _t('邮件发送返回数据：' . serialize($result) . PHP_EOL);
            // 输出分隔
            $log .= '-------------------------------------------' . PHP_EOL;
            // 写入文件
            file_put_contents(dirname(__FILE__) . '/log.txt', "\n".$log."\n", FILE_APPEND);
        }
        // 返回结果
        return $flag;
    }
    

    /**
     * @param $comment
     * @param $options
     * @param $type
     * @return string
     * 很朴素的邮件风格
     */
    private static function mailBody($comment, $options, $type): string
    {
        $plugin = Options::alloc()->plugin('CommentNotifier');
        $commentAt = new Date($comment->created);
        $commentAt = $commentAt->format('Y-m-d H:i:s');
        $commentText = $comment->content;
        $html = 'owner';
        if ($type == 1) {
            $html = 'guest';
        } elseif ($type == 2) {
            $html = 'notice';
        }
        $Pmail = '';
        $Pname = '';
        $Ptext = '';
        $Pmd5 = '';
        if ($comment->parent) {
            $parent = Helper::widgetById('comments', $comment->parent);
            $Pname = $parent->author;
            $Ptext = $parent->content;
            $Pmail = $parent->mail;
            $Pmd5 = md5($parent->mail);
        }
        
        $post=Helper::widgetById('Contents', $comment->cid);
        
        if($plugin->biaoqing&&is_callable($plugin->biaoqing)){//表情函数重载
        $parseBiaoQing = $plugin->biaoqing;
        $commentText = $parseBiaoQing($commentText);
        $Ptext = $parseBiaoQing($Ptext);
        }
        
        $style='style="display: inline-block;vertical-align: bottom;margin: 0;" width="30"';//限制表情尺寸
        
        $commentText=str_replace('class="biaoqing',$style.' class="biaoqing',$commentText);
        $Ptext=str_replace('class="biaoqing',$style.' class="biaoqing',$Ptext);
        
        
        $content = self::getTemplate($html);
        
        $content=preg_replace('#<\?php#', '<!--', $content);
        $content=preg_replace('#\?>#', '-->', $content);
        
        $template = Options::alloc()->plugin('CommentNotifier')->template;
        $status = array(
            "approved" => '通过',
            "waiting" => '待审',
            "spam" => '垃圾',
        );
        $search = array(
            '{title}',//文章标题
            '{PostAuthor}',//文章作者昵称
            '{time}',//评论发出时间
            '{commentText}',//评论内容
            '{author}',//评论人昵称
            '{mail}',//评论者邮箱
            '{md5}',//评论者邮箱
            '{ip}',//评论者ip
            '{permalink}',//评论楼层链接
            '{siteUrl}',//网站地址
            '{siteTitle}',//网站标题
            '{Pname}',//父级评论昵称
            '{Ptext}',//父级评论内容
            '{Pmail}',//父级评论邮箱
            '{Pmd5}',//父级评论邮箱md5
            '{url}',//当前模板文件夹路径
            '{manageurl}',//后台管理评论的入口链接
            '{status}', //评论状态
        );
        $replace = array(
            $comment->title,
            $post->author->screenName,
            $commentAt,
            $commentText,
            $comment->author,
            $comment->mail,
            md5($comment->mail),
            $comment->ip,
            $comment->permalink,
            $options->siteUrl,
            $options->title,
            $Pname,
            $Ptext,
            $Pmail,
            $Pmd5,
            Options::alloc()->pluginUrl . '/CommentNotifier/template/' . $template,
            Options::alloc()->adminUrl . '/manage-comments.php',
            $status[$comment->status]
        );

        return str_replace($search, $replace, $content);
    }

    /**
     * 获取评论模板
     *
     * @param template owner 为博主 guest 为访客
     * @return false|string
     */
    private static function getTemplate($template = 'owner')
    {
        $template .= '.html';
        $templateDir = self::configStr('template', 'default');
        $filePath = dirname(__FILE__) . '/template/' . $templateDir . '/' . $template;

        if (!file_exists($filePath)) {//如果模板文件缺失就调用根目录下的default文件夹中用于垫底的模板
            $filePath = dirname(__FILE__) . 'template/default/' . $template;
        }

        return file_get_contents($filePath);
    }

    /**
     * 从 Widget_Options 对象获取 Typecho 选项值（文本型）
     * @param string $key 选项 Key
     * @param mixed $default 默认值
     * @param string $method 测空值方法
     * @return string
     */
    public static function configStr(string $key, $default = '', string $method = 'empty'): string
    {
        $value = Helper::options()->plugin('CommentNotifier')->$key;
        if ($method === 'empty') {
            return empty($value) ? $default : $value;
        } else {
            return call_user_func($method, $value) ? $default : $value;
        }

    }
    
  /**
     * 阿里云签名
     *
     * @static
     * @access private
     *
     * @param array  $param        签名参数
     * @param string $accesssecret 秘钥
     *
     * @return string
     */
    private static function sign($param, $accesssecret)
    {
        // 参数排序
        ksort($param);
        // 组合基础
        $stringToSign = 'POST&' . self::percentEncode('/') . '&';
        // 临时变量
        $tmp = '';
        // 循环参数列表
        foreach ( $param as $k => $v ) {
            // 组合参数
            $tmp .= '&' . self::percentEncode($k) . '=' . self::percentEncode($v);
        }
        // 去除最后一个&
        $tmp = trim($tmp, '&');
        // 组合签名参数
        $stringToSign = $stringToSign . self::percentEncode($tmp);
        // 数据签名
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accesssecret . '&', TRUE));
        // 返回签名
        return $signature;
    }
    
    /**
     * 阿里云签名编码转换
     *
     * @static
     * @access private
     *
     * @param string $val 要转换的编码
     *
     * @return string|string[]|null
     */
    private static function percentEncode($val)
    {
        // URL编码
        $res = urlencode($val);
        // 加号转换为%20
        $res = preg_replace('/\+/', '%20', $res);
        // 星号转换为%2A
        $res = preg_replace('/\*/', '%2A', $res);
        // %7E转换为~
        $res = preg_replace('/%7E/', '~', $res);
        return $res;
    }
    
    /**
     * 阿里云请求参数组合
     *
     * @static
     * @access private
     *
     * @param array $param 发送参数
     *
     * @return bool|string
     */
    private static function getPostHttpBody($param)
    {
        // 空字符串
        $str = "";
        // 循环参数
        foreach ( $param as $k => $v ) {
            // 组合参数
            $str .= $k . '=' . urlencode($v) . '&';
        }
        // 去除第一个&
        return substr($str, 0, -1);
    }
}
