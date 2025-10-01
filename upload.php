<?php
// 设置UTF-8编码
header('Content-Type: application/json; charset=utf-8');

// 配置FTP信息 - 请根据你的实际情况修改
$ftp_config = array(
    'host' => '你的FTP主机地址',
    'user' => '你的FTP用户名',
    'pass' => '你的FTP密码',
    'dir'  => '你的FTP图片目录',
    'url'  => '你的网站域名'
);

// 允许的文件类型
$allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp');

// 检查是否有文件上传
if (!isset($_FILES['image'])) {
    echo json_encode(array('success' => false, 'error' => '没有文件上传'), JSON_UNESCAPED_UNICODE);
    exit;
}

$file = $_FILES['image'];

// 检查上传错误
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = array(
        1 => '上传的文件超过了php.ini中upload_max_filesize指令限制的值',
        2 => '上传文件的大小超过了HTML表单中MAX_FILE_SIZE指令指定的值',
        3 => '文件只有部分被上传',
        4 => '没有文件被上传',
        6 => '找不到临时文件夹',
        7 => '文件写入失败'
    );
    
    $error_msg = isset($error_messages[$file['error']]) ? 
                 $error_messages[$file['error']] : '未知上传错误';
    
    echo json_encode(array('success' => false, 'error' => $error_msg), JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查文件类型
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(array('success' => false, 'error' => '不支持的文件类型: ' . $file['type']), JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查文件大小（限制为5MB）
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(array('success' => false, 'error' => '文件大小超过5MB限制'), JSON_UNESCAPED_UNICODE);
    exit;
}

// 生成唯一文件名（避免中文乱码）
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'img_' . date('Ymd_His') . '_' . uniqid() . '.' . strtolower($file_extension);
$local_file = $file['tmp_name'];
$remote_file = $ftp_config['dir'] . $filename;

// 连接FTP服务器
$conn_id = @ftp_connect($ftp_config['host']);

if (!$conn_id) {
    echo json_encode(array('success' => false, 'error' => '无法连接到FTP服务器'), JSON_UNESCAPED_UNICODE);
    exit;
}

// 登录FTP
if (!@ftp_login($conn_id, $ftp_config['user'], $ftp_config['pass'])) {
    echo json_encode(array('success' => false, 'error' => 'FTP登录失败，请检查用户名和密码'), JSON_UNESCAPED_UNICODE);
    ftp_close($conn_id);
    exit;
}

// 开启被动模式
ftp_pasv($conn_id, true);

// 检查并创建目录（如果不存在）
$dirs = explode('/', trim($ftp_config['dir'], '/'));
$current_dir = '';
foreach ($dirs as $dir) {
    $current_dir .= '/' . $dir;
    if (!@ftp_chdir($conn_id, $current_dir)) {
        if (!@ftp_mkdir($conn_id, $current_dir)) {
            echo json_encode(array('success' => false, 'error' => '无法创建目录: ' . $current_dir), JSON_UNESCAPED_UNICODE);
            ftp_close($conn_id);
            exit;
        }
        ftp_chdir($conn_id, $current_dir);
    }
}

// 上传文件
if (ftp_put($conn_id, $remote_file, $local_file, FTP_BINARY)) {
    // 重要修改：生成的链接只包含域名和文件名，不包含上传目录
    $image_url = $ftp_config['url'] . '/' . $filename;
    echo json_encode(array('success' => true, 'url' => $image_url), JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(array('success' => false, 'error' => '文件上传到FTP失败'), JSON_UNESCAPED_UNICODE);
}

// 关闭FTP连接
ftp_close($conn_id);
?>