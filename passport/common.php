<?php
if (!defined('__DIR__')) {
    define('__DIR__', dirname(__FILE__));
}

define('__TYPECHO_ADMIN__', true);
define('__ADMIN_DIR__', __TYPECHO_ROOT_DIR__ . __TYPECHO_ADMIN_DIR__);

/** 初始化组件 */
\Widget\Init::alloc();

\Widget\Options::alloc()->to($options);
\Widget\User::alloc()->to($user);
\Widget\Security::alloc()->to($security);
\Widget\Menu::alloc()->to($menu);


$suffixVersion= $options->version;
