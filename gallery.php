<?php
// 配置FTP信息 - 与upload.php相同
$ftp_config = array(
    'host' => '你的FTP主机地址',
    'user' => '你的FTP用户名', 
    'pass' => '你的FTP密码',
    'dir'  => '你的FTP图片目录',
    'url'  => '你的网站域名'
);

// 连接FTP服务器获取图片列表
$conn_id = @ftp_connect($ftp_config['host']);

if ($conn_id && @ftp_login($conn_id, $ftp_config['user'], $ftp_config['pass'])) {
    ftp_pasv($conn_id, true);
    
    // 获取文件列表
    $files = @ftp_nlist($conn_id, $ftp_config['dir']);
    
    if ($files) {
        // 过滤出图片文件
        $image_files = array();
        foreach ($files as $file) {
            $filename = basename($file);
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'))) {
                // 重要修改：生成的链接只包含域名和文件名，不包含上传目录
                $image_files[] = array(
                    'name' => $filename,
                    'url' => $ftp_config['url'] . '/' . $filename
                );
            }
        }
        
        // 如果没有图片，显示空状态
        if (empty($image_files)) {
            echo '<div class="empty-gallery">暂无图片，请上传第一张图片</div>';
        } else {
            // 显示图片卡片
            foreach ($image_files as $image) {
                echo '<div class="image-card">';
                echo '<img src="' . $image['url'] . '" alt="' . $image['name'] . '" onerror="this.src=\'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgZmlsbD0iIzIyMiIvPjx0ZXh0IHg9IjEwMCIgeT0iNzUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iI2NjYyIgdGV4dC1hbmNob3I9Im1pZGRsZSI+5Zu+54mH5paH5Lu2PC90ZXh0Pjwvc3ZnPg==\'">';
                echo '<div class="image-info">';
                echo '<div class="image-name">' . $image['name'] . '</div>';
                echo '<button class="card-btn" onclick="copyToClipboard(\'' . $image['url'] . '\')">复制链接</button>';
                echo '</div>';
                echo '</div>';
            }
        }
    } else {
        echo '<div class="empty-gallery">无法获取图片列表或目录为空</div>';
    }
    
    ftp_close($conn_id);
} else {
    echo '<div class="empty-gallery">无法连接到FTP服务器，请检查配置</div>';
}
?>