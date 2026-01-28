<?php
$theme = empty($_GET['theme']) ? '' : $_GET['theme'];
$file = empty($_GET['file']) ? '' : $_GET['file'];
//获取评论模板
function getTemplate($templateDir = 'default',$template = 'owner.html')
    {
        $filePath = dirname(__FILE__) . '/' . $templateDir . '/' . $template;

        if (!file_exists($filePath)) {//如果模板文件缺失就调用根目录下的default文件夹中用于垫底的模板
             return '预览的主题文件夹或主题文件不存在';
        }

$content=file_get_contents($filePath);
$content=preg_replace('#<\?php#', '<!--', $content);
$content=preg_replace('#\?>#', '-->', $content);
$demouser = array(
    array('name' => '月宅', 'md5' => 'bf413cdf4570464b971cb6e0f0a0437a'),
    array('name' => '念', 'md5' => '138be792998aef019362d52276290752'),
    array('name' => 'Jochen', 'md5' => '076176b67617855818a00f3e5f963262'),
    array('name' => '吃猫的鱼', 'md5' => 'e2909ce0b9d612c601733aea588c0097'),
    array('name' => '清酒', 'md5' => '60dfa69a58fae040fb0feb753bef1535'),
    array('name' => '凡涛', 'md5' => 'b8f7b5a08bcba93c2c3eff7b1c5a7c32'),
);

// 随机选择两个不同的数组元素
$index1 = rand(0, count($demouser) - 1);
$index2 = array_rand(array_diff_key($demouser, [$index1 => '']));

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
            '{Ptime}',//父级评论时间
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
            $demouser[$index1]['name'],
            '10000@qq.com',
            $demouser[$index1]['md5'],
            '172.0.0.1',
            'https://github.com/jrotty/CommentNotifier',
            'https://typecho.fans',
            '泽泽社',
            $demouser[$index2]['name'],
            '这个插件真好用!',
            'zezeshe@foxmail.com',
            date('Y-m-d H:i:s'),
            $demouser[$index2]['md5'],
            './' . $templateDir.'/',
            'https://typecho.fans',
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
<title>预览<?php echo $file; ?> - <?php echo $theme; ?>主题</title>
</head>
<body>
<?php echo getTemplate($theme,$file);?>
</body>
</html>
