<?php
session_start();

// TOTP配置
$totp_secret = '你的TOTP密钥'; // 例如: JBSWY3DPEHPK3PXP
$totp_tolerance = 1; // 时间容差（允许前后1个时间窗口）

// 检查TOTP验证状态
$is_verified = false;
if (isset($_SESSION['totp_verified']) && $_SESSION['totp_verified'] === true) {
    // 检查会话是否过期（30分钟）
    if (isset($_SESSION['totp_verified_time']) && 
        (time() - $_SESSION['totp_verified_time']) < 1800) {
        $is_verified = true;
    } else {
        // 会话过期，需要重新验证
        unset($_SESSION['totp_verified']);
        unset($_SESSION['totp_verified_time']);
    }
}

// 处理TOTP验证
if (isset($_POST['totp_code']) && !$is_verified) {
    $code = $_POST['totp_code'];
    if (verify_totp($code, $totp_secret, $totp_tolerance)) {
        $_SESSION['totp_verified'] = true;
        $_SESSION['totp_verified_time'] = time();
        $is_verified = true;
    } else {
        $totp_error = "验证码错误，请重试";
    }
}

// 退出登录
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// TOTP验证函数
function verify_totp($code, $secret, $tolerance = 1) {
    $time_window = floor(time() / 30);
    
    for ($i = -$tolerance; $i <= $tolerance; $i++) {
        $expected_code = generate_totp($secret, $time_window + $i);
        if (hash_equals($expected_code, $code)) {
            return true;
        }
    }
    
    return false;
}

// 生成TOTP代码
function generate_totp($secret, $time_window) {
    // 将密钥解码为二进制
    $secret = base32_decode($secret);
    
    // 将时间窗口打包为64位大端序字节
    $time = pack('N*', 0) . pack('N*', $time_window);
    
    // 使用HMAC-SHA1生成哈希
    $hash = hash_hmac('sha1', $time, $secret, true);
    
    // 动态截断
    $offset = ord($hash[19]) & 0xF;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % pow(10, 6);
    
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

// Base32解码函数
function base32_decode($secret) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper($secret);
    $binary = '';
    
    for ($i = 0; $i < strlen($secret); $i++) {
        $char = $secret[$i];
        if ($char === '=') break;
        
        $value = strpos($alphabet, $char);
        if ($value === false) continue;
        
        $binary .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
    }
    
    // 将二进制字符串转换为字节
    $bytes = '';
    for ($i = 0; $i < strlen($binary); $i += 8) {
        $byte = substr($binary, $i, 8);
        if (strlen($byte) < 8) break;
        $bytes .= chr(bindec($byte));
    }
    
    return $bytes;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>私人图床系统 - 安全访问</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            background-color: #000000;
            color: #ffffff;
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #111111;
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(255, 255, 255, 0.05);
            padding: 30px;
            border: 1px solid #333;
        }
        
        header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #333;
            padding-bottom: 20px;
            position: relative;
        }
        
        h1 {
            color: #ffffff;
            margin-bottom: 10px;
            font-size: 2.5rem;
            font-weight: 300;
            letter-spacing: 2px;
        }
        
        .description {
            color: #aaaaaa;
            font-size: 16px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .logout-btn {
            position: absolute;
            top: 0;
            right: 0;
            background: #333;
            color: white;
            border: 1px solid #555;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #444;
        }
        
        /* TOTP验证界面样式 */
        .totp-container {
            max-width: 400px;
            margin: 50px auto;
            text-align: center;
        }
        
        .totp-icon {
            font-size: 64px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .totp-form {
            background: #0a0a0a;
            border-radius: 8px;
            padding: 30px;
            border: 1px solid #333;
        }
        
        .totp-input {
            width: 100%;
            padding: 15px;
            margin: 15px 0;
            background: #000;
            border: 1px solid #444;
            border-radius: 5px;
            color: #fff;
            font-size: 18px;
            text-align: center;
            letter-spacing: 5px;
        }
        
        .totp-btn {
            background: #222;
            color: white;
            border: 1px solid #444;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            transition: background 0.3s;
        }
        
        .totp-btn:hover {
            background: #333;
        }
        
        .totp-error {
            color: #ff5555;
            margin-top: 15px;
            padding: 10px;
            background: #1a0a0a;
            border-radius: 5px;
            border: 1px solid #553333;
        }
        
        .upload-section {
            background: #0a0a0a;
            border: 2px dashed #444;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            margin-bottom: 30px;
            transition: all 0.3s;
        }
        
        .upload-section.dragover {
            border-color: #ffffff;
            background: #1a1a1a;
        }
        
        .upload-icon {
            font-size: 48px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .upload-text {
            margin-bottom: 20px;
            color: #aaaaaa;
            font-size: 18px;
        }
        
        .file-input {
            display: none;
        }
        
        .upload-btn {
            background: #222;
            color: white;
            border: 1px solid #444;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .upload-btn:hover {
            background: #333;
            border-color: #666;
        }
        
        .progress-container {
            width: 100%;
            background: #222;
            border-radius: 5px;
            margin: 20px 0;
            display: none;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 10px;
            background: #fff;
            border-radius: 5px;
            width: 0%;
            transition: width 0.3s;
        }
        
        .result-section {
            display: none;
            margin-top: 30px;
            padding: 20px;
            background: #0a0a0a;
            border-radius: 8px;
            border: 1px solid #333;
        }
        
        .url-container {
            display: flex;
            margin-bottom: 15px;
        }
        
        .url-input {
            flex: 1;
            padding: 12px;
            border: 1px solid #444;
            border-radius: 5px 0 0 5px;
            font-size: 14px;
            background: #000;
            color: #fff;
        }
        
        .copy-btn {
            background: #333;
            color: white;
            border: 1px solid #555;
            padding: 12px 15px;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .copy-btn:hover {
            background: #444;
        }
        
        .preview-container {
            text-align: center;
            margin-top: 20px;
        }
        
        .preview-img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 5px;
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.1);
            border: 1px solid #333;
        }
        
        .gallery-section {
            margin-top: 40px;
        }
        
        .gallery-title {
            margin-bottom: 20px;
            color: #fff;
            font-size: 1.8rem;
            font-weight: 300;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
        }
        
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        
        .image-card {
            background: #0a0a0a;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #333;
        }
        
        .image-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(255, 255, 255, 0.1);
        }
        
        .image-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
            filter: grayscale(30%);
            transition: filter 0.3s;
        }
        
        .image-card:hover img {
            filter: grayscale(0%);
        }
        
        .image-info {
            padding: 15px;
        }
        
        .image-name {
            font-size: 14px;
            margin-bottom: 10px;
            color: #ccc;
            word-break: break-all;
        }
        
        .card-btn {
            background: #222;
            color: white;
            border: 1px solid #444;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            width: 100%;
            transition: background 0.3s;
        }
        
        .card-btn:hover {
            background: #333;
        }
        
        .error-message {
            color: #ff5555;
            margin-top: 10px;
            display: none;
            padding: 10px;
            background: #1a0a0a;
            border-radius: 5px;
            border: 1px solid #553333;
        }
        
        .success-message {
            color: #55ff55;
            margin-top: 10px;
            display: none;
            padding: 10px;
            background: #0a1a0a;
            border-radius: 5px;
            border: 1px solid #335533;
        }
        
        .empty-gallery {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .image-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .logout-btn {
                position: relative;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$is_verified): ?>
        <!-- TOTP验证界面 -->
        <div class="totp-container">
            <div class="totp-icon">🔒</div>
            <h2>图床系统安全访问</h2>
            <p style="color: #aaa; margin-bottom: 20px;">请输入您的TOTP验证码</p>
            
            <form class="totp-form" method="POST">
                <input type="text" name="totp_code" class="totp-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autocomplete="off">
                <button type="submit" class="totp-btn">验证</button>
                
                <?php if (isset($totp_error)): ?>
                <div class="totp-error"><?php echo $totp_error; ?></div>
                <?php endif; ?>
            </form>
            
            <p style="color: #666; margin-top: 20px; font-size: 14px;">
                使用您的身份验证器应用获取6位验证码
            </p>
        </div>
        
        <?php else: ?>
        <!-- 图床系统界面 -->
        <header>
            <h1>私人图床</h1>
            <p class="description">上传图片到存储空间，获取可直接使用的链接</p>
            <a href="?logout=1" class="logout-btn">退出登录</a>
        </header>
        
        <section class="upload-section" id="uploadArea">
            <div class="upload-icon">📤</div>
            <p class="upload-text">拖放图片到此处或点击选择文件</p>
            <input type="file" id="fileInput" class="file-input" accept="image/*" multiple>
            <button class="upload-btn" id="selectBtn">选择图片</button>
        </section>
        
        <div class="progress-container" id="progressContainer">
            <div class="progress-bar" id="progressBar"></div>
        </div>
        
        <div class="error-message" id="errorMessage"></div>
        <div class="success-message" id="successMessage"></div>
        
        <section class="result-section" id="resultSection">
            <h3>上传成功！</h3>
            <p>图片链接：</p>
            <div class="url-container">
                <input type="text" id="imageUrl" class="url-input" readonly>
                <button class="copy-btn" id="copyBtn">复制</button>
            </div>
            
            <div class="preview-container">
                <p>图片预览：</p>
                <img id="previewImg" class="preview-img" src="" alt="预览">
            </div>
        </section>
        
        <section class="gallery-section">
            <h2 class="gallery-title">图片库</h2>
            <div class="image-grid" id="imageGrid">
                <?php
                // 加载图片库
                include 'gallery.php';
                ?>
            </div>
        </section>
        
        <script>
            // DOM元素
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('fileInput');
            const selectBtn = document.getElementById('selectBtn');
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = document.getElementById('progressBar');
            const resultSection = document.getElementById('resultSection');
            const imageUrl = document.getElementById('imageUrl');
            const copyBtn = document.getElementById('copyBtn');
            const previewImg = document.getElementById('previewImg');
            const errorMessage = document.getElementById('errorMessage');
            const successMessage = document.getElementById('successMessage');
            const imageGrid = document.getElementById('imageGrid');

            // 初始化
            document.addEventListener('DOMContentLoaded', function() {
                // 选择文件按钮点击事件
                selectBtn.addEventListener('click', () => {
                    fileInput.click();
                });
                
                // 文件选择变化事件
                fileInput.addEventListener('change', handleFileSelect);
                
                // 拖放事件
                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('dragover');
                });
                
                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('dragover');
                });
                
                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('dragover');
                    
                    if (e.dataTransfer.files.length) {
                        handleFiles(e.dataTransfer.files);
                    }
                });
                
                // 复制链接按钮事件
                copyBtn.addEventListener('click', copyImageUrl);
            });

            // 处理文件选择
            function handleFileSelect(e) {
                if (e.target.files.length) {
                    handleFiles(e.target.files);
                }
            }
            
            // 处理文件上传
            function handleFiles(files) {
                // 只处理第一个文件（可扩展为多文件上传）
                const file = files[0];
                
                // 验证文件类型
                if (!file.type.match('image.*')) {
                    showError('请选择图片文件！');
                    return;
                }
                
                // 验证文件大小（限制为5MB）
                if (file.size > 5 * 1024 * 1024) {
                    showError('图片大小不能超过5MB！');
                    return;
                }
                
                // 显示上传进度
                progressContainer.style.display = 'block';
                progressBar.style.width = '0%';
                
                // 创建FormData对象
                const formData = new FormData();
                formData.append('image', file);
                
                // 发送AJAX请求到PHP后端
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                    }
                });
                
                xhr.addEventListener('load', () => {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            if (response.success) {
                                showSuccess('图片上传成功！');
                                displayResult(response.url, file);
                                
                                // 刷新页面以更新图片库
                                setTimeout(() => {
                                    location.reload();
                                }, 1500);
                            } else {
                                showError('上传失败：' + response.error);
                            }
                        } catch (e) {
                            showError('服务器响应异常：' + xhr.responseText);
                        }
                    } else {
                        showError('上传失败，HTTP状态码：' + xhr.status);
                    }
                    
                    progressContainer.style.display = 'none';
                });
                
                xhr.addEventListener('error', () => {
                    showError('上传过程中发生网络错误');
                    progressContainer.style.display = 'none';
                });
                
                xhr.open('POST', 'upload.php');
                xhr.send(formData);
            }
            
            // 显示上传结果
            function displayResult(url, file) {
                imageUrl.value = url;
                
                // 创建预览
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                };
                reader.readAsDataURL(file);
                
                resultSection.style.display = 'block';
                
                // 滚动到结果区域
                resultSection.scrollIntoView({ behavior: 'smooth' });
            }
            
            // 复制图片链接
            function copyImageUrl() {
                imageUrl.select();
                document.execCommand('copy');
                
                // 显示复制成功提示
                const originalText = copyBtn.textContent;
                copyBtn.textContent = '已复制！';
                
                setTimeout(() => {
                    copyBtn.textContent = originalText;
                }, 2000);
            }
            
            // 复制到剪贴板（用于图片库中的复制按钮）
            function copyToClipboard(text) {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                // 显示提示
                showSuccess('链接已复制到剪贴板');
            }
            
            // 显示错误信息
            function showError(message) {
                errorMessage.textContent = message;
                errorMessage.style.display = 'block';
                successMessage.style.display = 'none';
                
                // 5秒后隐藏错误信息
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 5000);
            }
            
            // 显示成功信息
            function showSuccess(message) {
                successMessage.textContent = message;
                successMessage.style.display = 'block';
                errorMessage.style.display = 'none';
                
                // 3秒后隐藏成功信息
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 3000);
            }
        </script>
        <?php endif; ?>
    </div>
</body>
</html>