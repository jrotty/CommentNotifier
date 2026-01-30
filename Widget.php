<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
use Widget\Options;
/**
 * 找回密码类
 *
 * @package Passport
 * @copyright Copyright (c) 2016 小否先生 (https://github.com/mhcyong)
 * @license GNU General Public License 2.0
 */
class CommentNotifier_Widget extends Typecho_Widget
{
    /**
     * 配置表
     *
     * @access private
     * @var Typecho_Options
     */
    private $options;

    /**
     * 提示框组件
     *
     * @access private
     * @var Widget_Notice
     */
    private $notice;

    /**
     * 构造函数
     *
     * @access public
     * @param mixed $request request对象
     * @param mixed $response response对象
     * @param mixed $params 参数列表
     */
    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);

        $this->notice = parent::widget('Widget_Notice');
        $this->options = parent::widget('Widget_Options');
    }

    /**
     * execute function.
     *
     * @access public
     * @return void
     */
    public function execute(){}

    /**
     * 找回密码
     *
     * @access public
     * @return void
     */


    public function doForgot()
    {
        $plugin = Options::alloc()->plugin('CommentNotifier');
        if(!in_array('passport', $plugin->tool)){$this->response->redirect($this->options->siteUrl);}
        require_once 'passport/forgot.php';
        if($user->hasLogin()){$this->notice->set(_t('当前账号处于登录状态，请在后台直接重置密码即可！'), 'error');exit;}

        if ($this->request->isPost()) {
            /* 验证表单 */
            if ($error = $this->forgotForm()->validate()) {
                $this->notice->set($error, 'error');
                return false;
            }

            $db = Typecho_Db::get();
            $userRow = $db->fetchRow($db->select()->from('table.users')->where('mail = ?', $this->request->mail));

            if (empty($userRow)) {
                // 返回没有该用户
                $this->notice->set(_t('该邮箱还没有注册'), 'error');
                return false;
            }
             
            /* 生成重置密码地址 */
            $hashString = $userRow['name'] . $userRow['mail'] . $userRow['password'];
            $hashValidate = Typecho_Common::hash($hashString);
            $token = base64_encode($userRow['uid'] . '.' . $hashValidate . '.' . $this->options->gmtTime);
            $url = Typecho_Common::url('/password/reset?token=' . $token, $this->options->index);


            /* 发送重置密码地址 */
            $param['to'] = $userRow['mail']; // 收件地址
            $param['fromName'] = $userRow['name']; // 收件人名称
            $param['subject'] = '密码重置' . date('Y-m-d H:i:s');// 邮件标题
            $param['html'] = '<p>' . $userRow['name'] . ' 您好，您申请了重置登录密码。</p>'
                . '<br><p>请在 1 小时内点击此链接以完成重置 <a href="' . $url . '">' . $url . '</a></p>'
                . '<br><p>如非本人操作请忽略本条消息！</p>';    // 邮件内容
            CommentNotifier_Plugin::send($param);
            $this->response->redirect($this->options->siteUrl.'password/forgot?type=ok');
        }
    }

    /**
     * 重置密码
     *
     * @access public
     * @return void
     */
    public function doReset()
    {
        $plugin = Options::alloc()->plugin('CommentNotifier');
        if(!in_array('passport', $plugin->tool)){$this->response->redirect($this->options->siteUrl);}
        /* 验证token */
        $token = $this->request->filter('strip_tags', 'trim', 'xss')->token;
        list($uid, $hashValidate, $timeStamp) = explode('.', base64_decode($token));
        $currentTimeStamp = $this->options->gmtTime;

        /* 检查链接时效 */
        if (($currentTimeStamp - $timeStamp) > 3600) {
            // 链接失效, 返回登录页
            $this->notice->set(_t('该链接已失效, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
        }

        $db = Typecho_Db::get();
        $userRow = $db->fetchRow($db->select()->from('table.users')->where('uid = ?', $uid));

        $hashString = $userRow['name'] . $userRow['mail'] . $userRow['password'];
        $hashValidate = Typecho_Common::hashValidate($hashString, $hashValidate);

        if (!$hashValidate) {
            // token错误, 返回登录页
            $this->notice->set(_t('该链接已失效, 请重新获取'), 'notice');
            $this->response->redirect($this->options->loginUrl);
        }

        require_once 'passport/reset.php';

        /* 重置密码 */
        if ($this->request->isPost()) {
            /* 验证表单 */
            if ($error = $this->resetForm()->validate()) {
                $this->notice->set($error, 'error');
                return false;
            }

            $hasher = new PasswordHash(8, true);
            $password = $hasher->HashPassword($this->request->password);

            $update = $db->query($db->update('table.users')
                ->rows(array('password' => $password))
                ->where('uid = ?', $userRow['uid']));

            if (!$update) {
                $this->notice->set(_t('重置密码失败'), 'error');
            }

            $this->notice->set(_t('重置密码成功'), 'success');
            $this->response->redirect($this->options->loginUrl);
        }
    }

    /**
     * 生成找回密码表单
     *
     * @access public
     * @return Typecho_Widget_Helper_Form
     */
    public function forgotForm() {
        $form = new Typecho_Widget_Helper_Form(NULL, Typecho_Widget_Helper_Form::POST_METHOD);

        $mail = new Typecho_Widget_Helper_Form_Element_Text('mail',
            NULL,
            NULL,
            _t('邮箱'),
            _t('账号对应的邮箱地址'));
        $form->addInput($mail);

        /** 用户动作 */
        $do = new Typecho_Widget_Helper_Form_Element_Hidden('do', NULL, 'mail');
        $form->addInput($do);

        /** 提交按钮 */
        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('提交'));
        $submit->input->setAttribute('class', 'w-100 btn primary');
        $form->addItem($submit);

        $mail->addRule('required', _t('必须填写电子邮箱'));
        $mail->addRule('email', _t('电子邮箱格式错误'));

        return $form;
    }

    /**
     * 生成重置密码表单
     *
     * @access public
     * @return Typecho_Widget_Helper_Form
     */
    public function resetForm() {
        $form = new Typecho_Widget_Helper_Form(NULL, Typecho_Widget_Helper_Form::POST_METHOD);

        /** 新密码 */
        $password = new Typecho_Widget_Helper_Form_Element_Password('password',
            NULL,
            NULL,
            _t('新密码'),
            _t('建议使用特殊字符与字母、数字的混编样式,以增加系统安全性.'));
        $password->input->setAttribute('class', 'w-100');
        $form->addInput($password);

        /** 新密码确认 */
        $confirm = new Typecho_Widget_Helper_Form_Element_Password('confirm',
            NULL,
            NULL,
            _t('密码确认'),
            _t('请确认你的密码, 与上面输入的密码保持一致.'));
        $confirm->input->setAttribute('class', 'w-100');
        $form->addInput($confirm);

        /** 用户动作 */
        $do = new Typecho_Widget_Helper_Form_Element_Hidden('do', NULL, 'password');
        $form->addInput($do);

        /** 提交按钮 */
        $submit = new Typecho_Widget_Helper_Form_Element_Submit('submit', NULL, _t('更新密码'));
        $submit->input->setAttribute('class', 'w-100 btn primary');
        $form->addItem($submit);

        $password->addRule('required', _t('必须填写密码'));
        $password->addRule('minLength', _t('为了保证账户安全, 请输入至少六位的密码'), 6);
        $confirm->addRule('confirm', _t('两次输入的密码不一致'), 'password');

        return $form;
    }
}