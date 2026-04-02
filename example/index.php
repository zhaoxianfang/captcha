<?php

/**
 * xfCaptcha - 使用示例
 *
 * 这是一个原生 PHP 使用示例，演示如何集成滑动验证码
 */

require_once __DIR__ . '/../vendor/autoload.php';

use zxf\Captcha\Captcha;
use zxf\Captcha\Generic\Adapter;

// 创建适配器并处理请求
$adapter = new Adapter([
    'route_prefix' => 'captcha',
]);

// 检查是否是验证码相关请求
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($uri, '/captcha/') !== false) {
    $adapter->handle();
    exit;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $captcha = new Captcha();
    $isValid = $captcha->check($_POST['xf_captcha'] ?? '');

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $isValid,
        'message' => $isValid ? '验证成功！' : '验证失败，请重试',
    ]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xfCaptcha 滑动验证码示例</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 400px;
            width: 90%;
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
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>xfCaptcha 演示</h1>
        <p>请完成下方滑动验证码验证</p>

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
                getImgUrl: '/captcha/image',
                checkUrl: '/captcha/check',
                placeholder: '点击完成验证',
                slideText: '拖动滑块完成拼图',
                successText: '✓ 验证成功',
                failText: '验证失败，请重试'
            }).onSuccess(function() {
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
                    showResult('请先完成滑动验证', false);
                    return;
                }

                // 发送验证请求到服务器
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'xf_captcha=verified'
                })
                .then(response => response.json())
                .then(data => {
                    showResult(data.message, data.success);
                    if (data.success) {
                        // 3秒后刷新验证码
                        setTimeout(function() {
                            xfCaptcha.refresh();
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
