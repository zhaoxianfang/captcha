<?php

/**
 * 原生 PHP 使用示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use zxf\Captcha\Captcha;
use zxf\Captcha\Storage\SessionStorage;

// 启动 Session
session_start();

// 路由处理
$path = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($path, PHP_URL_PATH);

// 去掉开头的 /
$path = ltrim($path, '/');

switch ($path) {
    case '':
    case 'index':
        // 显示首页
        showIndex();
        break;
        
    case 'captcha/image':
        // 输出验证码图片
        outputCaptchaImage();
        break;
        
    case 'captcha/verify':
        // 验证
        verifyCaptcha();
        break;
        
    case 'captcha/css':
        // 输出 CSS
        outputAsset('css/captcha.css', 'text/css');
        break;
        
    case 'captcha/js':
        // 输出 JS
        outputAsset('js/captcha.js', 'application/javascript');
        break;
        
    case 'submit':
        // 提交表单
        handleSubmit();
        break;
        
    default:
        http_response_code(404);
        echo '404 Not Found';
}

/**
 * 显示首页
 */
function showIndex(): void
{
    $baseUrl = getBaseUrl();
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>滑动验证码示例</title>
    <link rel="stylesheet" href="{$baseUrl}/captcha/css">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .result.success {
            background: #d4edda;
            color: #155724;
        }
        .result.error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <h1>滑动验证码示例</h1>
    
    <form id="demoForm" method="POST" action="{$baseUrl}/submit">
        <div class="form-group">
            <label>用户名</label>
            <input type="text" name="username" placeholder="请输入用户名">
        </div>
        
        <div class="form-group">
            <label>验证码</label>
            <div class="tncode"></div>
            <input type="hidden" name="tn_r" id="tn_r">
        </div>
        
        <button type="submit">提交</button>
    </form>
    
    <script src="{$baseUrl}/captcha/js"></script>
    <script>
        zxfCaptcha.init({
            handleDom: '.tncode',
            getImgUrl: '{$baseUrl}/captcha/image',
            checkUrl: '{$baseUrl}/captcha/verify'
        }).onSuccess(function() {
            console.log('验证成功');
        }).onFail(function() {
            console.log('验证失败');
        });
        
        // 表单提交前验证
        document.getElementById('demoForm').addEventListener('submit', function(e) {
            if (!zxfCaptcha.result()) {
                e.preventDefault();
                alert('请先完成验证码验证');
                return false;
            }
            document.getElementById('tn_r').value = zxfCaptcha._mark_offset;
        });
    </script>
</body>
</html>
HTML;
}

/**
 * 输出验证码图片
 */
function outputCaptchaImage(): void
{
    $captcha = new Captcha([
        'storage' => [
            'driver' => 'session',
            'session_key' => 'zxf_captcha',
        ]
    ]);
    
    $result = $captcha->generate();
    
    $mimeType = $result['format'] === 'webp' ? 'image/webp' : 'image/png';
    
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    
    echo $result['image'];
}

/**
 * 验证验证码
 */
function verifyCaptcha(): void
{
    header('Content-Type: application/json');
    
    $offset = $_GET['tn_r'] ?? null;
    
    if ($offset === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => '缺少验证参数'
        ]);
        return;
    }
    
    $captcha = new Captcha([
        'storage' => [
            'driver' => 'session',
            'session_key' => 'zxf_captcha',
        ]
    ]);
    
    $result = $captcha->verify($offset);
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'ok']);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
}

/**
 * 输出资源文件
 */
function outputAsset(string $path, string $mimeType): void
{
    $fullPath = __DIR__ . '/../resources/assets/' . $path;
    
    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo 'Not Found';
        return;
    }
    
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    
    readfile($fullPath);
}

/**
 * 处理表单提交
 */
function handleSubmit(): void
{
    header('Content-Type: application/json');
    
    $username = $_POST['username'] ?? '';
    $offset = $_POST['tn_r'] ?? null;
    
    // 验证验证码
    $captcha = new Captcha([
        'storage' => [
            'driver' => 'session',
            'session_key' => 'zxf_captcha',
        ]
    ]);
    
    $result = $captcha->verify($offset);
    
    if (!$result['success']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message']
        ]);
        return;
    }
    
    // 这里进行实际的业务逻辑处理
    echo json_encode([
        'success' => true,
        'message' => '提交成功',
        'data' => [
            'username' => $username
        ]
    ]);
}

/**
 * 获取基础 URL
 */
function getBaseUrl(): string
{
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script);
    
    return $scheme . '://' . $host . ($dir === '/' ? '' : $dir);
}
