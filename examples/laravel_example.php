<?php

/**
 * Laravel 使用示例
 */

// ========================================
// 1. 控制器中使用
// ========================================

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use zxf\Captcha\Captcha;

class LoginController extends Controller
{
    /**
     * 登录页面
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * 登录处理
     */
    public function login(Request $request, Captcha $captcha)
    {
        // 验证滑块
        $verifyResult = $captcha->verify($request->input('tn_r'));
        
        if (!$verifyResult['success']) {
            return back()->withErrors([
                'captcha' => $verifyResult['message']
            ]);
        }

        // 验证用户名密码...
        $credentials = $request->only('email', 'password');
        
        if (auth()->attempt($credentials)) {
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => '邮箱或密码错误'
        ]);
    }
}

// ========================================
// 2. Blade 模板
// ========================================

/*
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>登录</title>
    <link rel="stylesheet" href="{{ route('captcha.css') }}">
    <style>
        .login-form {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="login-form">
        <h2>登录</h2>
        
        @if($errors->has('captcha'))
            <div style="color: red; margin-bottom: 10px;">
                {{ $errors->first('captcha') }}
            </div>
        @endif
        
        <form method="POST" action="{{ route('login') }}">
            @csrf
            
            <div class="form-group">
                <label>邮箱</label>
                <input type="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label>验证码</label>
                <div class="tncode"></div>
                <input type="hidden" name="tn_r" id="tn_r">
            </div>
            
            <button type="submit">登录</button>
        </form>
    </div>
    
    <script src="{{ route('captcha.js') }}"></script>
    <script>
        zxfCaptcha.init({
            handleDom: '.tncode',
            getImgUrl: '{{ route('captcha.image') }}',
            checkUrl: '{{ route('captcha.verify') }}'
        }).onSuccess(function() {
            // 设置隐藏字段，提交验证偏移量
            document.getElementById('tn_r').value = zxfCaptcha._mark_offset;
        });
    </script>
</body>
</html>
*/

// ========================================
// 3. 路由配置
// ========================================

/*
// routes/web.php

use App\Http\Controllers\LoginController;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
*/

// ========================================
// 4. 中间件中使用（全局验证）
// ========================================

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use zxf\Captcha\Captcha;

class VerifyCaptcha
{
    protected Captcha $captcha;
    
    public function __construct(Captcha $captcha)
    {
        $this->captcha = $captcha;
    }
    
    public function handle(Request $request, Closure $next)
    {
        // 排除验证码相关路由
        if ($request->is('zxf-captcha/*')) {
            return $next($request);
        }
        
        // 只在 POST 请求时验证
        if ($request->isMethod('post')) {
            $result = $this->captcha->verify($request->input('tn_r'));
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        }
        
        return $next($request);
    }
}
