<?php
// 设置响应头为 XML
header('Content-Type: application/xml; charset=utf-8');

// 读取 articles.json 文件
$jsonFile = 'articles.json';
if (!file_exists($jsonFile)) {
    die('articles.json 文件不存在！');
}

$articles = json_decode(file_get_contents($jsonFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON 解析错误：' . json_last_error_msg());
}

// 按 create_time 降序排序
usort($articles, function($a, $b) {
    return $b['create_time'] - $a['create_time'];
});

// 生成 RSS
echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>AIListings 文章列表</title>
        <link>https://mp.weixin.qq.com/mp/appmsgalbum?__biz=Mzk1NzU3MTMxMg==&amp;action=getalbum&amp;album_id=3954694936062066693</link>
        <description>AIListings 公众号文章列表（共计 <?php echo count($articles); ?> 篇文章）</description>
        <language>zh-CN</language>
        <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
        <atom:link href="<?php echo 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>" rel="self" type="application/rss+xml" />
        
        <?php foreach ($articles as $article): ?>
        <item>
            <title><![CDATA[<?php echo htmlspecialchars($article['title']); ?>]]></title>
            <link><?php echo htmlspecialchars($article['url']); ?></link>
            <guid><?php echo htmlspecialchars($article['msgid']); ?></guid>
            <pubDate><?php echo date('r', $article['create_time']); ?></pubDate>
            <description><![CDATA[<?php echo htmlspecialchars($article['title']); ?>]]></description>
            <imageLink><?php echo 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/wx.png'; ?></imageLink>
        </item>
        <?php endforeach; ?>
    </channel>
</rss> 