# xfCaptcha - 高性能滑动验证码 PHP 扩展包

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

高性能、安全、易用的滑动验证码 PHP 扩展包，支持 Laravel、ThinkPHP 等主流 PHP 框架，也可在原生 PHP 中使用。

![滑动验证码演示](demo.png)

## ✨ 特性

- 🚀 **高性能**: 优化的图像处理算法，响应迅速
- 🔒 **高安全性**: 容错机制、错误次数限制、防暴力破解
- 🎨 **美观界面**: 现代化 UI 设计，支持深色模式
- 📱 **响应式**: 完美适配各种屏幕尺寸
- 🔧 **易集成**: 支持 Laravel、ThinkPHP 等主流框架
- 🌍 **兼容性**: 支持 PHP 8.2+，不依赖任何第三方包
- ⚡ **轻量化**: 无外部依赖，安装即用

## 📦 安装

通过 Composer 安装：

```bash
composer require zxf/captcha
```

## 🚀 快速开始

### Laravel 中使用

#### 1. 安装服务提供者（Laravel 5.5+ 会自动发现）

对于 Laravel 5.5 以下版本，在 `config/app.php` 中注册服务提供者：

```php
'providers' => [
    // ...
    zxf\Captcha\Laravel\CaptchaServiceProvider::class,
],

'aliases' => [
    // ...
    'xfCaptcha' => zxf\Captcha\Laravel\Facades\Captcha::class,
],
```

#### 2. 发布配置文件

```bash
php artisan vendor:publish --tag=xf-captcha-config
```

**注意**：如果提示 `No publishable resources for tag [xf-captcha-config]`，请尝试：
```bash
# 清除缓存后重试
php artisan cache:clear
php artisan config:clear
composer dump-autoload
php artisan vendor:publish --tag=xf-captcha-config
```

#### 3. 发布资源文件（可选）

```bash
php artisan vendor:publish --tag=xf-captcha-assets
```

#### 4. 在 Blade 模板中使用

```blade
<!DOCTYPE html>
<html>
<head>
    <title>验证码演示</title>
</head>
<body>
    <!-- 方式1：使用辅助函数 -->
    {!! xf_captcha_html('.xf-captcha') !!}

    <!-- 方式2：手动引入 -->
    <div class="xf-captcha"></div>
    <link rel="stylesheet" href="{{ route('xf-captcha.css') }}">
    <script src="{{ route('xf-captcha.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            xfCaptcha.init({
                handleDom: '.xf-captcha',
                getImgUrl: '{{ route('xf-captcha.image') }}',
                checkUrl: '{{ route('xf-captcha.check') }}'
            });
        });
    </script>
</body>
</html>
```

#### 5. 后端验证（三种方式）

**方式一：使用 Request 验证器（推荐）**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function store(Request $request)
    {
        // 验证请求数据，包括滑动验证码
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'xf_captcha' => 'required|xfCaptcha',  // 滑动验证码验证
        ], [
            'xf_captcha.required' => '请完成滑动验证',
            'xf_captcha.xfCaptcha' => '滑动验证码验证失败',
        ]);

        // 验证通过，继续处理登录逻辑
        // ...
    }
}
```

**方式二：使用 Validator 门面**

```php
use Illuminate\Support\Facades\Validator;

public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required',
        'xf_captcha' => 'required|xfCaptcha',
    ]);

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput();
    }

    // 验证通过
}
```

**方式三：使用辅助函数**

```php
public function login(Request $request)
{
    // 验证滑动验证码
    if (!xf_captcha_check($request->input('xf_captcha'))) {
        return response()->json([
            'success' => false,
            'message' => '滑动验证码验证失败'
        ], 400);
    }

    // 验证通过，继续处理
}
```

**方式四：使用 Facade**

```php
use xfCaptcha;

public function login(Request $request)
{
    // 验证滑动验证码
    if (!xfCaptcha::check($request->input('xf_captcha'))) {
        return response()->json([
            'success' => false,
            'message' => '滑动验证码验证失败'
        ], 400);
    }

    // 验证通过，继续处理
}
```

### ThinkPHP 中使用

#### 1. 安装服务（ThinkPHP 6+）

在 `config/service.php` 中添加：

```php
return [
    'bind' => [
        'xfCaptcha' => zxf\Captcha\ThinkPHP\CaptchaService::class,
    ],
];
```

或在 `app/provider.php` 中：

```php
return [
    'xfCaptcha' => zxf\Captcha\ThinkPHP\CaptchaService::class,
];
```

#### 2. 在模板中使用

```html
<!DOCTYPE html>
<html>
<head>
    <title>验证码演示</title>
</head>
<body>
    <div class="xf-captcha"></div>
    <link rel="stylesheet" href="/captcha/css">
    <script src="/captcha/js"></script>
    <script>
        xfCaptcha.init({
            handleDom: '.xf-captcha',
            getImgUrl: '/captcha/image',
            checkUrl: '/captcha/check'
        });
    </script>
</body>
</html>
```

#### 3. 后端验证

```php
<?php

namespace app\controller;

use think\Request;
use think\exception\ValidateException;

class User
{
    public function login(Request $request)
    {
        // 方式一：使用验证器
        try {
            validate([
                'username' => 'require',
                'password' => 'require',
                'xf_captcha' => 'require|xfCaptcha',
            ], [
                'xf_captcha.require' => '请完成滑动验证',
                'xf_captcha.xfCaptcha' => '滑动验证码验证失败',
            ])->check($request->post());
        } catch (ValidateException $e) {
            return json(['error' => $e->getMessage()], 400);
        }

        // 方式二：使用辅助函数
        if (!xf_captcha_check($request->post('xf_captcha'))) {
            return json(['error' => '滑动验证码验证失败'], 400);
        }

        // 验证通过
        return json(['message' => '登录成功']);
    }
}
```

### 原生 PHP 中使用

```php
<?php

require_once 'vendor/autoload.php';

use zxf\Captcha\Captcha;

// 创建验证码实例
$captcha = new Captcha();

// 生成验证码图片（直接输出到浏览器）
// 在需要输出验证码图片的页面调用：
// $captcha->make();

// 验证用户输入
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isValid = $captcha->check($_POST['xf_captcha'] ?? '');

    if ($isValid) {
        echo json_encode(['success' => true, 'message' => '验证成功']);
    } else {
        echo json_encode(['success' => false, 'message' => '验证失败']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>验证码演示</title>
</head>
<body>
    <div class="xf-captcha"></div>
    <link rel="stylesheet" href="/captcha/css">
    <script src="/captcha/js"></script>
    <script>
        xfCaptcha.init({
            handleDom: '.xf-captcha',
            getImgUrl: '/captcha/image',
            checkUrl: '/captcha/check'
        });
    </script>
</body>
</html>
```

## ⚙️ 配置说明

配置文件位于 `config/xf_captcha.php`，所有配置项都有详细的中文注释：

```php
return [
    // 容错像素值（值越大越宽松）
    'fault_tolerance' => 3,

    // 最大错误次数
    'max_error_count' => 10,

    // 背景图片尺寸
    'bg_width' => 240,
    'bg_height' => 150,

    // 滑块尺寸
    'mark_width' => 50,
    'mark_height' => 50,

    // 图片输出格式
    'output_format' => 'webp',  // 或 'png'

    // WebP 质量（0-100）
    'webp_quality' => 40,

    // PNG 压缩级别（0-9）
    'png_quality' => 7,

    // Session 前缀
    'session_prefix' => 'xf_captcha',

    // 路由配置
    'route_prefix' => 'captcha',
    'route_middleware' => ['web'],

    // 前端配置
    'frontend' => [
        'handle_dom' => '.xf-captcha',
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

## 📚 API 文档

### JavaScript API

```javascript
// 初始化
xfCaptcha.init({
    handleDom: '.xf-captcha',      // 触发按钮选择器
    getImgUrl: '/captcha/image',   // 获取图片接口
    checkUrl: '/captcha/check',    // 验证接口
    placeholder: '点击按钮进行验证',
    slideText: '拖动左边滑块完成上方拼图',
    successText: '✓ 验证成功',
    failText: '验证失败，请重试',
    showClose: true,               // 显示关闭按钮
    showRefresh: true,             // 显示刷新按钮
    showRipple: true               // 显示水波纹效果
});

// 设置回调
xfCaptcha
    .onSuccess(function() {
        console.log('验证成功');
    })
    .onFail(function() {
        console.log('验证失败');
    })
    .onClose(function() {
        console.log('弹窗关闭');
    });

// 手动操作
xfCaptcha.show();      // 显示验证码
xfCaptcha.hide();      // 隐藏验证码
xfCaptcha.refresh();   // 刷新验证码
xfCaptcha.result();    // 获取验证结果（true/false）
```

### PHP API

```php
use zxf\Captcha\Captcha;

// 创建实例（使用默认配置）
$captcha = new Captcha();

// 创建实例（使用自定义配置）
$captcha = new Captcha([
    'fault_tolerance' => 5,
    'bg_images' => ['/path/to/bg1.png', '/path/to/bg2.png'],
]);

// 生成验证码图片并输出到浏览器
$captcha->make();

// 生成验证码图片并返回二进制数据
$imageData = $captcha->makeRaw();

// 验证用户输入
$isValid = $captcha->check($_POST['xf_captcha']);

// 检查是否已验证通过
$isChecked = $captcha->isChecked();

// 刷新验证码（清除验证状态）
$captcha->refresh();

// 获取/设置配置
$value = $captcha->getConfig('fault_tolerance');
$captcha->setConfig('fault_tolerance', 5);
$captcha->setConfigs(['fault_tolerance' => 5, 'max_error_count' => 5]);
```

### 辅助函数

```php
// 获取验证码实例
$captcha = xf_captcha();

// 使用自定义背景图生成验证码
$captcha = xf_captcha(['/path/to/bg1.png', '/path/to/bg2.png']);

// 验证滑动验证码
$isValid = xf_captcha_check($_POST['xf_captcha']);

// 检查是否已验证通过
$isChecked = xf_captcha_is_checked();

// 刷新验证码
xf_captcha_refresh();

// 生成 HTML 代码
$html = xf_captcha_html('.xf-captcha', [
    'placeholder' => '点击验证',
]);

// 生成初始化脚本
$script = xf_captcha_script('.xf-captcha');
```

## 🎨 自定义样式

你可以通过覆盖 CSS 来自定义验证码样式：

```css
/* 自定义触发按钮 */
.xf-captcha {
    width: 300px;
    height: 50px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 25px;
}

/* 自定义弹窗 */
.captcha_div {
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

/* 自定义滑块 */
.captcha_slide_block {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
```

## 🖼️ 自定义背景图和滑块

### 替换背景图

将背景图片放入 `resources/assets/images/bg/` 目录即可自动识别使用。

### 替换滑块图片

准备两张 PNG 图片：
- `mark_01.png` - 透明滑块（用于背景缺口）
- `mark_02.png` - 黑色滑块（用于拖动滑块）

替换 `resources/assets/images/` 目录下的对应文件。

### 配置自定义路径

```php
// config/xf_captcha.php
return [
    'slide_transparent_img' => '/custom/path/to/mark_01.png',
    'slide_dark_img' => '/custom/path/to/mark_02.png',
    'bg_images' => [
        '/custom/path/to/bg1.png',
        '/custom/path/to/bg2.png',
    ],
];
```

## 🔒 安全建议

1. **调整容错值**: 根据安全需求调整 `fault_tolerance`，值越小越难破解
2. **限制错误次数**: 设置合适的 `max_error_count` 防止暴力破解
3. **使用 HTTPS**: 生产环境务必使用 HTTPS
4. **配合其他验证**: 建议配合 CSRF 保护使用
5. **定期更换背景图**: 增加背景图数量可提高安全性

## 🐛 常见问题

### 验证码图片不显示

1. 检查 GD 扩展是否安装：`php -m | grep gd`
2. 检查背景图片路径是否正确
3. 检查是否有读写权限
4. 检查是否有输出缓冲问题，确保在输出图片前没有输出任何内容

### 验证总是失败

1. 检查 Session 是否正常开启
2. 检查 `fault_tolerance` 配置是否过小
3. 检查前端传递的参数名是否为 `captcha_r`
4. 检查浏览器 Cookie 是否启用（Session 依赖 Cookie）

### Laravel 中路由找不到

1. 执行 `php artisan route:clear` 清除路由缓存
2. 检查服务提供者是否正确注册
3. 执行 `php artisan cache:clear` 清除应用缓存
4. 如果提示 `Failed to open stream: No such file or directory`，请执行：
   ```bash
   composer dump-autoload
   rm -rf vendor/zxf/captcha
   composer install
   ```

### Laravel 中发布配置失败

如果遇到 `Can't locate path` 错误，请手动复制配置文件：

```bash
cp vendor/zxf/captcha/config/xf_captcha.php config/xf_captcha.php
```

### 类找不到错误

如果遇到 `Class 'zxf\Captcha\...' not found` 错误：

1. 执行 `composer dump-autoload`
2. 检查 `composer.json` 中是否有自动加载冲突
3. 删除 `vendor` 目录重新安装：`rm -rf vendor && composer install`

### 跨域问题

如果是跨域请求，请配置 CORS：

```php
// Laravel 示例
return response()->json($data)->header('Access-Control-Allow-Origin', '*');
```

### 图片输出乱码或损坏

1. 确保在调用 `$captcha->make()` 之前没有任何输出（包括空格和 BOM 头）
2. 检查是否有其他代码设置了响应头
3. 尝试使用 `ob_clean()` 清理输出缓冲区

### Session 问题

如果遇到 Session 相关错误：

1. 确保在调用验证码之前没有输出内容
2. 检查 `session.save_path` 是否有写权限
3. 如果使用 Redis 等 Session 驱动，确保连接正常

## 🔧 快速故障排除

### 如果遇到 `Failed to open stream: No such file or directory`

这通常是因为 Composer 自动加载缓存问题，请按以下步骤解决：

```bash
# 1. 清除 Composer 自动加载缓存
composer dump-autoload

# 2. 清除 Laravel 缓存（如果在 Laravel 中使用）
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 3. 如果问题仍然存在，删除并重新安装包
rm -rf vendor/zxf/captcha
composer install

# 4. 确保命名空间正确
# 检查 vendor/composer/autoload_psr4.php 中是否有：
# 'zxf\\Captcha\\' => array($vendorDir . '/zxf/captcha/src')
```

### 如果遇到 `include(.../Adapters/...): failed to open stream`

这是旧版本残留缓存问题，请执行：

```bash
# 1. 完全删除 vendor 目录
rm -rf vendor composer.lock

# 2. 重新安装依赖
composer install

# 3. 如果仍然报错，检查是否有自定义的 PSR-4 映射冲突
# 编辑 composer.json 确保没有重复的命名空间映射
```

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

## 🔗 相关链接

- [GitHub 仓库](https://github.com/zhaoxianfang/captcha)
- [Packagist](https://packagist.org/packages/zxf/captcha)
- [作者主页](https://weisifang.com)

---

Made with ❤️ by [zhaoxianfang](https://weisifang.com)
