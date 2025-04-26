<?php
// 1. 使用 CURL 获取网页内容（避免被屏蔽）
function fetchWeixinUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 忽略 HTTPS 证书验证（仅测试用）
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

$url = "https://mp.weixin.qq.com/mp/appmsgalbum?__biz=Mzk1NzU3MTMxMg==&action=getalbum&album_id=3954694936062066693";
$html = fetchWeixinUrl($url);

if (!$html) {
    die("无法获取网页内容，请检查 URL 或网络连接！");
}

// 2. 提取 window.cgiData 数据
preg_match('/window\.cgiData\s*=\s*({[\s\S]*?})\s*;/', $html, $matches);

if (!isset($matches[1])) {
    die("未找到 window.cgiData 数据！可能是页面结构已变化。");
}

// 提取 articleList 部分
preg_match('/articleList\s*:\s*\[([\s\S]*?)\]\s*,/', $matches[1], $articleListMatches);

if (!isset($articleListMatches[1])) {
    die("未找到 articleList 数据！");
}

// 3. 预处理 articleList 字符串到有效 JSON
$articleListStr = '[' . $articleListMatches[1] . ']';

// 移除所有制表符和多余的空格
$articleListStr = preg_replace('/\t+/', '', $articleListStr);
$articleListStr = preg_replace('/\s+/', ' ', $articleListStr);

// 替换所有单引号为双引号
$articleListStr = str_replace("'", '"', $articleListStr);

// 处理特殊运算符和值
$articleListStr = str_replace(' || ""', '', $articleListStr); // 移除 || "" 运算符
$articleListStr = str_replace('"" || ', '', $articleListStr); // 移除 "" || 运算符
$articleListStr = str_replace(' * 1', '', $articleListStr); // 移除 * 1 运算符
$articleListStr = str_replace(' ? "" * 1 : -1', ': -1', $articleListStr); // 处理三元运算符
$articleListStr = str_replace(' ? "" : -1', ': -1', $articleListStr); // 处理另一种形式的三元运算符

// 处理空值和特殊值
$articleListStr = str_replace(': ""', ': null', $articleListStr); // 将空字符串转换为 null
$articleListStr = str_replace(': "0"', ': 0', $articleListStr); // 将字符串 "0" 转换为数字 0
$articleListStr = str_replace(': "1"', ': 1', $articleListStr); // 将字符串 "1" 转换为数字 1
$articleListStr = str_replace(': "2"', ': 2', $articleListStr); // 将字符串 "2" 转换为数字 2

// 确保所有键都是双引号包裹的
$articleListStr = preg_replace('/([{,])\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $articleListStr);

// 处理空对象和数组
$articleListStr = str_replace(': {}', ':{}', $articleListStr);
$articleListStr = str_replace(': []', ':[]', $articleListStr);

// 修复缺失的逗号 - 使用新的方法
$articleListStr = preg_replace('/"([^"]+)":\s*([^,}\]]+)(?=\s*"[^"]+":)/', '"$1": $2,', $articleListStr);
$articleListStr = preg_replace('/"([^"]+)":\s*([^,}\]]+)(?=\s*})/', '"$1": $2', $articleListStr);

// 修复 articleList 数组中的格式错误
$articleListStr = preg_replace('/,\s*{/', ',{', $articleListStr); // 修复逗号后的空格
$articleListStr = preg_replace('/}\s*,/', '},', $articleListStr); // 修复右括号后的逗号
$articleListStr = preg_replace('/{\s*,/', '{', $articleListStr); // 修复左括号后的逗号
$articleListStr = preg_replace('/,\s*}/', '}', $articleListStr); // 修复逗号后的右括号

// 修复空对象的格式
$articleListStr = str_replace('{ }', '{}', $articleListStr);
$articleListStr = str_replace('{  }', '{}', $articleListStr);

// 修复 articleList 数组中的格式
$articleListStr = preg_replace('/\[\s*{/', '[{', $articleListStr);
$articleListStr = preg_replace('/}\s*\]/', '}]', $articleListStr);

// 修复三元运算符的另一种形式
$articleListStr = preg_replace('/\s*\?\s*""\s*:\s*-1/', ': -1', $articleListStr);

echo "<pre>";
echo "Debug: 预处理后的 articleList 数据:\n";
echo htmlspecialchars($articleListStr) . "\n\n";

// 4. 解析 JSON
$articles = json_decode($articleListStr, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    // 输出更详细的错误信息
    echo "JSON 解析失败！错误: " . json_last_error_msg() . "\n";
    echo "错误位置: " . json_last_error() . "\n";
    
    // 输出预处理后的 JSON 的前 100 个字符
    echo "\n预处理后的 JSON 前 100 个字符:\n";
    echo substr($articleListStr, 0, 100) . "...\n";
    
    // 检查 JSON 字符串中的引号
    $singleQuotes = substr_count($articleListStr, "'");
    $doubleQuotes = substr_count($articleListStr, '"');
    echo "\n引号统计:\n";
    echo "单引号数量: " . $singleQuotes . "\n";
    echo "双引号数量: " . $doubleQuotes . "\n";
    
    // 检查 JSON 字符串中的特殊字符
    echo "\n特殊字符检查:\n";
    echo "反斜杠数量: " . substr_count($articleListStr, "\\") . "\n";
    echo "未转义的双引号数量: " . substr_count($articleListStr, '"') . "\n";
    
    // 输出 JSON 字符串中的前几个键值对
    echo "\n前几个键值对:\n";
    $pairs = explode(',', $articleListStr);
    for ($i = 0; $i < min(5, count($pairs)); $i++) {
        echo $pairs[$i] . "\n";
    }
    echo "</pre>";
    die();
}

// 5. 显示调试信息
echo "Debug: 成功解析出 " . count($articles) . " 篇文章\n";

// 准备要写入的数据
$newArticlesData = [];
foreach ($articles as $article) {
    $newArticlesData[] = [
        'title' => $article['title'] ?? '',
        'url' => $article['url'] ?? '',
        'create_time' => $article['create_time'] ?? '',
        'msgid' => $article['msgid'] ?? ''
    ];
}

// 读取现有数据（如果存在）
$existingArticles = [];
$jsonFile = 'articles.json';
if (file_exists($jsonFile)) {
    $existingData = file_get_contents($jsonFile);
    $existingArticles = json_decode($existingData, true) ?? [];
}

// 合并数据，确保不重复
$allArticles = $existingArticles;
foreach ($newArticlesData as $newArticle) {
    // 检查是否已存在相同 msgid 的文章
    $exists = false;
    foreach ($allArticles as $existingArticle) {
        if ($existingArticle['msgid'] === $newArticle['msgid']) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $allArticles[] = $newArticle;
    }
}

// 按 create_time 降序排序
usort($allArticles, function($a, $b) {
    return $b['create_time'] - $a['create_time'];
});

// 写入 JSON 文件
$jsonData = json_encode($allArticles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
file_put_contents($jsonFile, $jsonData);

echo "Debug: 数据已成功写入 articles.json 文件\n";
echo "Debug: 写入的数据内容:\n";
echo htmlspecialchars($jsonData) . "\n";
echo "</pre>";
?>