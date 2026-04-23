<?php

/**
 * xfCaptcha - 使用示例
 *
 * 这是原生 PHP 使用示例，演示如何集成滑动验证码和点击验证码
 */

require_once __DIR__ . '/../vendor/autoload.php';

use zxf\Captcha\Captcha;
use zxf\Captcha\Http\CaptchaController;

// 创建验证码控制器
$controller = new CaptchaController();

// 获取请求路径
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

// 处理验证码相关请求
if (strpos($uri, '/captcha/') !== false) {
    $action = basename($uri);
    switch ($action) {
        case 'data':
            $result = $controller->data();
            if ($result !== null) {
                echo $result;
            }
            exit;
        case 'image':
            $result = $controller->image();
            if ($result !== null) {
                echo $result;
            }
            exit;
        case 'check':
            $result = $controller->check();
            if ($result !== null) {
                echo $result;
            }
            exit;
        case 'js':
            $result = $controller->js();
            if ($result !== null) {
                echo $result;
            }
            exit;
        case 'css':
            $result = $controller->css();
            if ($result !== null) {
                echo $result;
            }
            exit;
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $captcha = new Captcha();
    $result = $captcha->verify(
        $_POST['captcha_r'] ?? null,
        $_POST['xf_captcha_token'] ?? null,
        json_decode($_POST['click_points'] ?? '[]', true)
    );

    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xfCaptcha 滑动验证码 & 点击验证码示例</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 450px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }
        p {
            color: #666;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .config-info {
            background: #f5f7fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
            font-size: 13px;
            color: #606266;
        }
        .config-info strong {
            color: #409eff;
        }
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 100%;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            display: none;
        }
        .result.success {
            background: #e1f3d8;
            color: #67c23a;
            display: block;
        }
        .result.error {
            background: #fde2e2;
            color: #f56c6c;
            display: block;
        }
        .feature-list {
            text-align: left;
            margin: 20px 0;
            padding-left: 20px;
        }
        .feature-list li {
            margin: 8px 0;
            color: #606266;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>xfCaptcha 演示</h1>
        <p>支持滑动验证码和点击验证码，自动随机切换</p>

        <div class="config-info">
            <strong>当前配置：</strong><br>
            验证码类型：both（随机切换滑动/点击）<br>
            验证模式：dual（双重验证）
        </div>

        <ul class="feature-list">
            <li>✓ 滑动验证码：拖动滑块完成拼图</li>
            <li>✓ 点击验证码：按顺序点击指定文字</li>
            <li>✓ 支持双重验证（前端+后端）</li>
            <li>✓ 可配置的字符数量和容错范围</li>
        </ul>

        <form id="demo-form">
            <div class="form-group">
                <div class="xf-captcha"></div>
            </div>

            <button type="submit" class="submit-btn" disabled id="submit-btn">
                提交验证
            </button>
        </form>

        <div id="result" class="result"></div>
    </div>

    <link rel="stylesheet" href="/captcha/css">
    <script src="/captcha/js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化验证码
            xfCaptcha.init({
                handleDom: '.xf-captcha',
                dataUrl: '/captcha/data',
                checkUrl: '/captcha/check',
                placeholder: '点击完成验证',
                slideText: '拖动左边滑块完成上方拼图',
                clickText: '请按照顺序点击图片中的文字',
                successText: '✓ 验证成功',
                failText: '验证失败，请重试'
            }).onSuccess(function(token) {
                // 验证成功启用提交按钮
                document.getElementById('submit-btn').disabled = false;
            }).onFail(function() {
                // 验证失败禁用提交按钮
                document.getElementById('submit-btn').disabled = true;
            });

            // 处理表单提交
            document.getElementById('demo-form').addEventListener('submit', function(e) {
                e.preventDefault();

                if (!xfCaptcha.result()) {
                    showResult('请先完成验证码验证', false);
                    return;
                }

                // 获取验证令牌
                const token = xfCaptcha.getToken();

                // 发送验证请求到服务器
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'xf_captcha_token=' + encodeURIComponent(token)
                })
                .then(response => response.json())
                .then(data => {
                    showResult(data.message, data.success);
                    if (data.success) {
                        setTimeout(function() {
                            xfCaptcha.reset();
                            document.getElementById('submit-btn').disabled = true;
                        }, 3000);
                    }
                })
                .catch(error => {
                    showResult('请求失败: ' + error.message, false);
                });
            });

            function showResult(message, isSuccess) {
                const resultDiv = document.getElementById('result');
                resultDiv.textContent = message;
                resultDiv.className = 'result ' + (isSuccess ? 'success' : 'error');
            }
        });
    </script>
</body>
</html>
