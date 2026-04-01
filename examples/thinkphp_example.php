<?php

/**
 * ThinkPHP 使用示例
 */

// ========================================
// 1. 控制器中使用
// ========================================

namespace app\controller;

use think\Request;
use think\facade\View;
use zxf\Captcha\Captcha;

class Login
{
    /**
     * 登录页面
     */
    public function index()
    {
        return View::fetch('login');
    }

    /**
     * 登录处理
     */
    public function doLogin(Request $request)
    {
        // 获取验证码实例
        $captcha = app('captcha');
        
        // 验证滑块
        $verifyResult = $captcha->verify($request->param('tn_r'));
        
        if (!$verifyResult['success']) {
            return json([
                'code' => 400,
                'message' => $verifyResult['message']
            ]);
        }

        // 验证用户名密码...
        $username = $request->param('username');
        $password = $request->param('password');
        
        // 登录逻辑...
        
        return json([
            'code' => 200,
            'message' => '登录成功'
        ]);
    }
}

// ========================================
// 2. 模板文件 (view/login/index.html)
// ========================================

/*
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>登录 - ThinkPHP</title>
    <link rel="stylesheet" href="{:url('captcha.css')}">
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 360px;
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #666;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
        }
        .captcha-wrap {
            margin: 25px 0;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        .submit-btn:hover {
            background: #0056b3;
        }
        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .tips {
            text-align: center;
            color: #999;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>用户登录</h2>
        
        <form id="loginForm">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" placeholder="请输入用户名" required>
            </div>
            
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" placeholder="请输入密码" required>
            </div>
            
            <div class="captcha-wrap">
                <div class="tncode"></div>
                <input type="hidden" name="tn_r" id="tn_r">
            </div>
            
            <button type="submit" class="submit-btn" id="submitBtn">登录</button>
        </form>
        
        <div class="tips">ThinkPHP + zxf Captcha 演示</div>
    </div>
    
    <script src="{:url('captcha.js')}"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        var captchaVerified = false;
        
        // 初始化验证码
        zxfCaptcha.init({
            handleDom: '.tncode',
            getImgUrl: "{:url('captcha.image')}",
            checkUrl: "{:url('captcha.verify')}"
        }).onSuccess(function() {
            captchaVerified = true;
            document.getElementById('tn_r').value = zxfCaptcha._mark_offset;
            document.getElementById('submitBtn').disabled = false;
        }).onFail(function() {
            captchaVerified = false;
            document.getElementById('submitBtn').disabled = true;
        });
        
        // 初始禁用提交按钮
        document.getElementById('submitBtn').disabled = true;
        
        // 表单提交
        $('#loginForm').on('submit', function(e) {
            e.preventDefault();
            
            if (!captchaVerified) {
                alert('请先完成验证码验证');
                return false;
            }
            
            $.ajax({
                url: "{:url('doLogin')}",
                type: 'POST',
                data: $(this).serialize(),
                success: function(res) {
                    if (res.code === 200) {
                        alert('登录成功！');
                        // window.location.href = '/index';
                    } else {
                        alert(res.message);
                        // 刷新验证码
                        zxfCaptcha.refresh();
                        captchaVerified = false;
                        document.getElementById('submitBtn').disabled = true;
                    }
                },
                error: function(xhr) {
                    var res = xhr.responseJSON;
                    alert(res ? res.message : '请求失败');
                }
            });
        });
    </script>
</body>
</html>
*/

// ========================================
// 3. 路由配置 (route/app.php)
// ========================================

/*
use think\facade\Route;

// 验证码路由已自动注册

// 登录路由
Route::get('login', 'Login/index');
Route::post('login/doLogin', 'Login/doLogin');
*/

// ========================================
// 4. 配置文件 (config/captcha.php)
// ========================================

/*
<?php
return [
    'bg_width' => 240,
    'bg_height' => 150,
    'fault_tolerance' => 3,
    'max_error_count' => 10,
    
    'storage' => [
        'driver' => 'session',
        'session_key' => 'zxf_captcha',
    ],
    
    'routes' => [
        'enabled' => true,
        'prefix' => 'zxf-captcha',
        'middleware' => [],
    ],
    
    'security' => [
        'frequency_limit_enabled' => true,
        'min_request_interval' => 1,
    ],
];
*/
