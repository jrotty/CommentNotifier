<?php
if(!empty($_GET['theme'])&&!empty($_GET['file'])){
$theme=$_GET['theme'];
$file=$_GET['file'];
}
    /**
     * 获取评论模板
     *
     * @param template owner 为博主 guest 为访客
     * @return false|string
     */
function getTemplate($templateDir = 'default',$template = 'owner.html')
    {
        $filePath = dirname(__FILE__) . '/' . $templateDir . '/' . $template;

        if (!file_exists($filePath)) {//如果模板文件缺失就调用根目录下的default文件夹中用于垫底的模板
            $filePath = dirname(__FILE__) . '/default/' . $template;
        }

$content=file_get_contents($filePath);
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
            'CommentNotifier邮件提醒插件',
            '泽泽社长',
            date('Y-m-d H:i:s'),
            '这个插件真好用！',
            '念',
            'bssf@qq.com',
            '138be792998aef019362d52276290752',
            '192.168.1.1',
            'https://github.com/jrotty/CommentNotifier',
            'https://blog.zezeshe.com',
            '泽泽社',
            'Jochen',
            '这个插件真好用!',
            'zezeshe@foxmail.com',
            '076176b67617855818a00f3e5f963262',
            './' . $templateDir.'/',
            'https://blog.zezeshe.com',
            '通过'
        );

        return str_replace($search, $replace, $content);
    }
?>
<html lang="zh-CN">
<head> 
<meta charset="UTF-8">
<meta name="renderer" content="webkit">
<meta name="viewport" content="width=device-width,user-scalable=no,viewport-fit=cover,initial-scale=1, maximum-scale=1">
<title>预览<?php echo $file; ?></title>
</head>
<body>
    
<?php echo getTemplate($theme,$file); ?>
    
</body>
</html>