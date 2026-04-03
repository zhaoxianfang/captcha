# xfCaptcha - 高性能滑动验证码 PHP 扩展包

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

高性能、安全、易用的滑动验证码 PHP 扩展包，支持 Laravel、ThinkPHP 等主流 PHP 框架，也可在原生 PHP 中使用。

![滑动验证码演示](demo.png)

## ✨ 特性

- 🚀 **高性能**: 优化的图像处理算法，响应迅速
- 🔒 **高安全性**: 支持双重验证模式，容错机制、错误次数限制、防暴力破解
- 🎨 **美观界面**: 现代化 UI 设计，支持浅色/深色主题
- 📱 **响应式**: 完美适配各种屏幕尺寸，移动端优化
- 🔧 **易集成**: 支持 Laravel 11+、ThinkPHP 8+ 等主流框架
- 🌍 **兼容性**: 支持 PHP 8.2+，不依赖任何第三方包
- ⚡ **轻量化**: 无外部依赖，安装即用
- 🛡️ **防重放**: Token 一次性使用，防止重放攻击
- 🔄 **易重置**: 提供 reset() 接口，表单失败后快速重置

## 📋 环境要求

- PHP >= 8.2
- GD 扩展
- Laravel 11+ 或 ThinkPHP 8+（可选）

## 📦 安装

通过 Composer 安装：

```bash
composer require zxf/captcha
```

## 🚀 快速开始

### Laravel 中使用（支持 Laravel 11+）

#### 1. 安装服务提供者（Laravel 11+ 会自动发现）

**注意：本包需要 Laravel 11.0 或更高版本。**

对于需要手动注册的情况，在 `bootstrap/providers.php` 中注册服务提供者：

```php
return [
    // ...
    zxf\Captcha\Laravel\CaptchaServiceProvider::class,
];
```

#### 2. 发布配置文件

```bash
php artisan vendor:publish --tag=xf-captcha-config
```

#### 3. 在 Blade 模板中使用

```blade
<!DOCTYPE html>
<html>
<head>
    <title>验证码演示</title>
</head>
<body>
    <form method="POST" action="/login" id="loginForm">
        @csrf
        
        <!-- 使用 Blade 组件 -->
        @include('xf-captcha::captcha', [
            'selector' => '.xf-captcha',
            'placeholder' => '点击完成验证',
            'inputName' => 'xf_captcha',
        ])
        
        <div class="xf-captcha"></div>
        
        <button type="submit">提交</button>
    </form>
    
    <script>
        // 表单提交示例
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            fetch('/login', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('登录成功！');
                    window.location.href = '/dashboard';
                } else {
                    alert('登录失败：' + data.message);
                    // 重置验证码
                    xfCaptcha.reset();
                }
            });
        });
    </script>
</body>
</html>
```

#### 4. 后端验证

```php
use Illuminate\Http\Request;

public function login(Request $request)
{
    // 验证请求
    $validated = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'xf_captcha' => 'required|xfCaptcha', // 验证验证码
    ], [
        'xf_captcha.required' => '请完成滑动验证',
        'xf_captcha.xf_captcha' => '验证失败，请重新验证',
    ]);
    
    // 登录逻辑...
}
```

### ThinkPHP 中使用（支持 ThinkPHP 8+）

#### 1. 配置

在 `config/service.php` 中添加：

```php
return [
    // ...
    'services' => [
        zxf\Captcha\ThinkPHP\CaptchaService::class,
    ],
];
```

#### 2. 发布配置

```bash
php think vendor:publish zxf/captcha
```

#### 3. 在模板中使用

```html
<!DOCTYPE html>
<html>
<head>
    <title>验证码演示</title>
    <link rel="stylesheet" href="/xf_captcha/css">
</head>
<body>
    <form method="POST" action="/login" id="loginForm">
        <div class="xf-captcha"></div>
        <input type="hidden" name="xf_captcha_token" id="xf_captcha_token">
        <button type="submit">提交</button>
    </form>
    
    <script src="/xf_captcha/js"></script>
    <script>
        xfCaptcha.init({
            handleDom: '.xf-captcha',
            getImgUrl: '/xf_captcha/image',
            checkUrl: '/xf_captcha/check',
            inputName: 'xf_captcha_token'
        });
        
        // 表单提交
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            fetch('/login', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 200) {
                    alert('登录成功！');
                } else {
                    alert('登录失败：' + data.msg);
                    xfCaptcha.reset(); // 重置验证码
                }
            });
        });
    </script>
</body>
</html>
```

#### 4. 后端验证

```php
use zxf\Captcha\Captcha;

public function login()
{
    $data = input('post.');
    
    // 验证验证码
    $captcha = app('xfCaptcha');
    $result = $captcha->verify(null, $data['xf_captcha_token'] ?? '');
    
    if (!$result['success']) {
        return json(['code' => 400, 'msg' => $result['message']]);
    }
    
    // 登录逻辑...
}
```

### 原生 PHP 中使用

```php
<?php
require_once 'vendor/autoload.php';

use zxf\Captcha\Captcha;

session_start();

// 创建验证码实例
$captcha = new Captcha([
    'verify_mode' => Captcha::VERIFY_DUAL, // 双重验证模式
]);

// 生成验证码图片
if ($_GET['action'] === 'image') {
    $captcha->make();
    exit;
}

// 验证
if ($_GET['action'] === 'check') {
    $result = $captcha->verify($_GET['captcha_r'] ?? null, $_GET['xf_captcha_token'] ?? null);
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>验证码演示</title>
    <link rel="stylesheet" href="/path/to/captcha.css">
</head>
<body>
    <form method="POST" action="/submit" id="myForm">
        <div class="xf-captcha"></div>
        <input type="hidden" name="xf_captcha_token" id="xf_captcha_token">
        <button type="submit">提交</button>
    </form>
    
    <script src="/path/to/captcha.js"></script>
    <script>
        xfCaptcha.init({
            handleDom: '.xf-captcha',
            getImgUrl: '?action=image',
            checkUrl: '?action=check',
            inputName: 'xf_captcha_token'
        });
        
        // 表单提交
        document.getElementById('myForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            fetch('/submit', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('提交成功！');
                } else {
                    alert('提交失败：' + data.message);
                    xfCaptcha.reset(); // 重置验证码
                }
            });
        });
    </script>
</body>
</html>
```

## ⚙️ 配置说明

### 完整配置示例

```php
<?php
return [
    /*
    |--------------------------------------------------------------------------
    | 滑块图片路径
    |--------------------------------------------------------------------------
    |
    | 自定义滑块图片，留空则使用默认图片
    |
    */
    'slide_dark_img' => '',
    'slide_transparent_img' => '',
    
    /*
    |--------------------------------------------------------------------------
    | 背景图片配置
    |--------------------------------------------------------------------------
    |
    | 可以配置背景图片目录或具体图片路径数组
    |
    */
    'bg_images_dir' => '',
    'bg_images' => [],
    
    /*
    |--------------------------------------------------------------------------
    | 容错像素值
    |--------------------------------------------------------------------------
    |
    | 滑动位置允许的误差范围（像素）
 |
    */
    'fault_tolerance' => 3,
    
    /*
    |--------------------------------------------------------------------------
    | 最大错误次数
    |--------------------------------------------------------------------------
    |
    | 超过此次数后需要刷新验证码
    |
    */
    'max_error_count' => 10,
    
    /*
    |--------------------------------------------------------------------------
    | 图片尺寸
    |--------------------------------------------------------------------------
    */
    'bg_width' => 240,
    'bg_height' => 150,
    'mark_width' => 50,
    'mark_height' => 50,
    
    /*
    |--------------------------------------------------------------------------
    | 输出格式
    |--------------------------------------------------------------------------
    */
    'output_format' => 'webp', // 'webp' 或 'png'
    'webp_quality' => 40,
    'png_quality' => 7,
    
    /*
    |--------------------------------------------------------------------------
    | Session 前缀
    |--------------------------------------------------------------------------
    */
    'session_prefix' => 'xf_captcha',
    
    /*
    |--------------------------------------------------------------------------
    | 资源路由配置
    |--------------------------------------------------------------------------
    */
    'route_prefix' => 'xf_captcha',
    
    /*
    |--------------------------------------------------------------------------
    | 验证模式
    |--------------------------------------------------------------------------
    |
    | frontend_only - 仅前端验证（不安全，仅测试）
    | backend_only  - 仅后端验证
    | dual          - 双重验证（推荐，最安全）
    |
    */
    'verify_mode' => 'dual',
    
    /*
    |--------------------------------------------------------------------------
    | Token 过期时间（秒）
    |--------------------------------------------------------------------------
    */
    'token_expire' => 300,
    
    /*
    |--------------------------------------------------------------------------
    | 前端配置
    |--------------------------------------------------------------------------
    */
    'frontend' => [
        'theme' => 'auto', // 'light' | 'dark' | 'auto'
        'input_name' => 'xf_captcha_token',
        'auto_insert_input' => true,
        'placeholder' => '点击按钮进行验证',
        'slide_text' => '拖动左边滑块完成上方拼图',
        'success_text' => '✓ 验证成功',
        'fail_text' => '验证失败，请重试',
        'show_close' => true,
        'show_refresh' => true,
        'show_ripple' => true,
    ],
];
```

### 验证模式说明

#### 1. frontend_only - 仅前端验证（不安全）

仅在前端进行滑动验证，不发送请求到后端。此模式仅用于测试，**不推荐在生产环境使用**。

#### 2. backend_only - 仅后端验证

传统的验证模式，用户滑动后前端发送位置到后端验证。验证通过后返回成功状态，并立即销毁 session 数据。

#### 3. dual - 双重验证（推荐）

最安全的验证模式，流程如下：

1. **首次验证**：用户滑动滑块，前端发送位置到后端
2. **生成 Token**：后端验证位置正确后，生成一次性 Token 返回
3. **存储 Token**：前端将 Token 存入隐藏输入框
4. **二次验证**：表单提交时，后端验证 Token 有效性
5. **销毁 Token**：Token 一次性使用，验证后立即销毁

**安全特性**：
- Token 一次性使用，防止重放攻击
- Token 有过期时间（默认5分钟）
- 使用 hash_equals 防止时序攻击
- 首次验证后 session 数据保留，二次验证后销毁

## 🔌 JavaScript API

### 初始化

```javascript
xfCaptcha.init({
    handleDom: '.xf-captcha',       // 触发元素选择器
    getImgUrl: '/xf_captcha/image', // 图片接口地址
    checkUrl: '/xf_captcha/check',  // 验证接口地址
    placeholder: '点击按钮进行验证', // 按钮占位文字
    slideText: '拖动左边滑块完成上方拼图', // 滑动提示
    successText: '✓ 验证成功',      // 成功提示
    failText: '验证失败，请重试',    // 失败提示
    showClose: true,                // 显示关闭按钮
    showRefresh: true,              // 显示刷新按钮
    showRipple: true,               // 显示水波纹效果
    theme: 'auto',                  // 主题: 'light' | 'dark' | 'auto'
    inputName: 'xf_captcha_token',  // 隐藏输入框 name
    autoInsertInput: true,          // 自动插入隐藏输入框
});
```

### 方法

```javascript
// 获取验证结果
const isVerified = xfCaptcha.result();

// 获取验证令牌（双重验证模式）
const token = xfCaptcha.getToken();

// 动态切换主题
xfCaptcha.setTheme('dark');

// 刷新验证码
xfCaptcha.refresh();

// 显示/隐藏验证码弹窗
xfCaptcha.show();
xfCaptcha.hide();

// 重置验证码状态（表单提交失败后使用）
xfCaptcha.reset();
```

### 事件回调

```javascript
xfCaptcha.init({
    // ... 配置
})
.onSuccess(function(token) {
    console.log('验证成功，Token:', token);
    // 可以在这里添加自定义逻辑
})
.onFail(function() {
    console.log('验证失败');
    // 验证失败后的处理
})
.onClose(function() {
    console.log('验证码弹窗关闭');
    // 弹窗关闭后的处理
});
```

### 表单提交示例

```javascript
document.getElementById('myForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // 检查是否已完成验证
    if (!xfCaptcha.result()) {
        alert('请先完成滑动验证');
        return;
    }
    
    fetch('/submit', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('提交成功！');
            // 重置表单
            this.reset();
            // 重置验证码
            xfCaptcha.reset();
        } else {
            alert('提交失败：' + data.message);
            // 表单提交失败，重置验证码
            xfCaptcha.reset();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('网络错误，请重试');
        xfCaptcha.reset();
    });
});
```

## 🛡️ 安全建议

1. **使用双重验证模式**：生产环境请务必使用 `dual` 验证模式
2. **启用 HTTPS**：防止 Token 被中间人窃取
3. **限制错误次数**：合理设置 `max_error_count` 防止暴力破解
4. **自定义背景图**：使用自己的背景图片，增加识别难度
5. **调整容错值**：根据安全需求调整 `fault_tolerance`
6. **及时重置**：表单提交失败后及时调用 `xfCaptcha.reset()`
7. **Token 过期时间**：根据业务需求调整 `token_expire`

## 🔧 常见问题

### Q: 验证码图片不显示？

A: 请检查：
1. GD 扩展是否安装：`php -m | grep gd`
2. 背景图片目录是否存在且可读
3. 路由配置是否正确
4. 浏览器控制台是否有错误信息

### Q: 验证总是失败？

A: 请检查：
1. Session 是否正常启动
2. 容错值是否设置过小（建议 3-5 像素）
3. 浏览器是否支持 WebP（可强制使用 PNG）
4. 服务器时间是否准确（影响 Token 过期判断）

### Q: 移动端滑动卡顿？

A: 已针对移动端优化，如仍有问题请检查：
1. 是否有其他 JavaScript 冲突
2. 页面是否有大量重绘
3. 是否使用了 `touch-action: none` 样式

### Q: Token 验证失败？

A: 请检查：
1. Token 是否在有效期内（默认5分钟）
2. Token 是否已被使用过
3. Session 是否正常
4. 前端是否正确保存了 Token

### Q: 如何自定义样式？

A: 可以通过覆盖 CSS 变量来自定义样式：

```css
:root {
    --xf-captcha-bg: #ffffff;
    --xf-captcha-text: #303133;
    --xf-captcha-border: #dcdfe6;
    --xf-captcha-msg-error-bg: #ff4d4f;
    --xf-captcha-msg-ok-bg: #52c41a;
}
```

### Q: 如何禁用关闭和刷新按钮？

A: 在初始化时设置：

```javascript
xfCaptcha.init({
    showClose: false,
    showRefresh: false,
});
```

## 📄 许可证

MIT License

## 👨‍💻 作者

zhaoxianfang <zhaoxianfang@163.com>

## 📝 更新日志

### v2.0.0
- 重构验证逻辑，支持三种验证模式
- 新增双重验证模式，提高安全性
- 优化移动端兼容性
- 支持主题切换（浅色/深色/自动）
- 新增 `reset()` 接口
- 移除中间件配置
- 修复已知问题

### v1.0.0
- 初始版本发布
- 支持 Laravel 和 ThinkPHP
- 基础滑动验证功能
