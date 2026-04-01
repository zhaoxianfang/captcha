# zxf Captcha - PHP 滑动验证码扩展包

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/badge/packagist-zxf/captcha-orange.svg)](https://packagist.org/packages/zxf/captcha)

一个通用的、高性能的 PHP 滑动验证码扩展包，支持 Laravel、ThinkPHP、Yii 等主流 PHP 框架，也可以在任何原生 PHP 项目中使用。

![滑动验证码演示](./demo.png)

## 目录

- [特性](#特性)
- [安装](#安装)
- [快速开始](#快速开始)
- [框架集成](#框架集成)
- [配置说明](#配置说明)
- [API 文档](#api-文档)
- [自定义存储](#自定义存储)
- [安全建议](#安全建议)
- [性能优化](#性能优化)
- [常见问题](#常见问题)
- [更新日志](#更新日志)

## 特性

- **PHP 8.2+** 完全类型化实现，现代 PHP 特性
- **零依赖** 无需安装任何额外扩展包（仅需 PHP GD 扩展）
- **多框架支持** Laravel、ThinkPHP、Yii、原生 PHP 通用
- **内置存储接口** 支持 Session、Array、自定义存储（如 Redis）
- **资源路由** 自动提供 CSS/JS/图片资源路由
- **高安全性**
  - Cookie + Session 双重验证机制
  - 频率限制（IP 级别）
  - 容错验证机制
  - 错误次数限制
  - 验证码有效期控制
- **高性能**
  - WebP 格式支持（自动检测浏览器兼容性）
  - 智能缓存（CSS/JS 资源）
  - 优化的图片处理算法
- **易于定制**
  - 自定义背景图
  - 自定义滑块样式
  - 灵活的配置选项
- **完善的文档和示例**

## 安装

### 环境要求

- PHP >= 8.2
- GD 扩展（`ext-gd`）

### 通过 Composer 安装

```bash
composer require zxf/captcha
```

### 验证安装

```php
<?php
require_once 'vendor/autoload.php';

use zxf\Captcha\CaptchaHelper;

// 检查系统兼容性
$info = CaptchaHelper::getSystemInfo();
print_r($info);
```

## 快速开始

### 1. 原生 PHP 使用

```php
<?php
require_once 'vendor/autoload.php';

use zxf\Captcha\Captcha;

// 创建验证码实例
$captcha = new Captcha();

// 生成验证码（通常在单独的接口中调用）
$result = $captcha->generate();

// 输出图片
header('Content-Type: ' . ($result['format'] === 'webp' ? 'image/webp' : 'image/png'));
echo $result['image'];

// 验证（接收前端传来的偏移量）
$result = $captcha->verify($_GET['tn_r'] ?? 0);
if ($result['success']) {
    echo '验证通过';
} else {
    echo '验证失败：' . $result['message'];
}
```

### 2. 前端使用

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>验证码示例</title>
    <!-- 引入验证码 CSS -->
    <link rel="stylesheet" href="/zxf-captcha/assets/captcha.css">
    <style>
        .container { max-width: 400px; margin: 50px auto; }
        .tncode { margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>滑动验证码演示</h2>
        
        <!-- 验证码容器 -->
        <div class="tncode"></div>
        
        <form id="myForm">
            <input type="hidden" name="captcha_offset" id="captcha_offset">
            <button type="submit">提交</button>
        </form>
    </div>

    <!-- 引入验证码 JS -->
    <script src="/zxf-captcha/assets/captcha.js"></script>
    <script>
        // 初始化验证码
        zxfCaptcha.init({
            // 触发按钮选择器
            handleDom: '.tncode',
            // 获取验证码图片接口
            getImgUrl: '/zxf-captcha/image',
            // 验证接口
            checkUrl: '/zxf-captcha/verify'
        }).onSuccess(function(response) {
            // 验证成功回调
            console.log('验证成功', response);
            // 可以将偏移量存入表单
            document.getElementById('captcha_offset').value = zxfCaptcha.getOffset();
        }).onFail(function(response) {
            // 验证失败回调
            console.log('验证失败', response);
        });

        // 表单提交
        document.getElementById('myForm').addEventListener('submit', function(e) {
            if (!zxfCaptcha.result()) {
                e.preventDefault();
                alert('请先完成验证码验证');
                return false;
            }
            // 继续提交...
        });
    </script>
</body>
</html>
```

## 框架集成

### Laravel 集成

#### 1. 发布配置

```bash
# 发布配置文件
php artisan vendor:publish --provider="zxf\Captcha\Adapters\Laravel\CaptchaServiceProvider" --tag="captcha-config"

# 发布资源文件（可选，如果不发布则使用内置路由）
php artisan vendor:publish --provider="zxf\Captcha\Adapters\Laravel\CaptchaServiceProvider" --tag="captcha-assets"
```

#### 2. 路由配置

服务提供者会自动注册路由，无需手动配置。访问以下路由：

- `GET /zxf-captcha/image` - 获取验证码图片
- `GET/POST /zxf-captcha/verify` - 验证
- `GET /zxf-captcha/assets/captcha.css` - CSS 资源
- `GET /zxf-captcha/assets/captcha.js` - JS 资源

#### 3. Blade 模板使用

```blade
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="{{ route('captcha.css') }}">
</head>
<body>
    <div class="tncode"></div>

    <script src="{{ route('captcha.js') }}"></script>
    <script>
        zxfCaptcha.init({
            handleDom: '.tncode',
            getImgUrl: '{{ route('captcha.image') }}',
            checkUrl: '{{ route('captcha.verify') }}'
        });
    </script>
</body>
</html>
```

#### 4. 控制器中验证

```php
<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use zxf\Captcha\Captcha;

class LoginController extends Controller
{
    public function login(Request $request, Captcha $captcha)
    {
        // 验证验证码
        $result = $captcha->verify($request->input('tn_r'));
        
        if (!$result['success']) {
            return back()->withErrors([
                'captcha' => $result['message']
            ]);
        }
        
        // 继续处理登录逻辑...
    }
}
```

#### 5. 使用门面

```php
use zxf\Captcha\Adapters\Laravel\CaptchaFacade as Captcha;

// 生成验证码
$result = Captcha::generate();

// 验证
$result = Captcha::verify($request->input('tn_r'));
```

### ThinkPHP 集成

#### 1. 注册服务

在 `config/service.php` 中添加：

```php
return [
    'services' => [
        \zxf\Captcha\Adapters\ThinkPHP\CaptchaService::class,
    ],
];
```

复制配置文件：

```bash
cp vendor/zxf/captcha/config/captcha.php config/captcha.php
```

#### 2. 模板中使用

```html
<link rel="stylesheet" href="{:url('captcha.css')}">

<div class="tncode"></div>

<script src="{:url('captcha.js')}"></script>
<script>
    zxfCaptcha.init({
        handleDom: '.tncode',
        getImgUrl: "{:url('captcha.image')}",
        checkUrl: "{:url('captcha.verify')}"
    });
</script>
```

### Yii 集成

#### 1. 配置模块

在 `config/web.php` 中添加：

```php
return [
    'modules' => [
        'captcha' => [
            'class' => 'zxf\Captcha\Adapters\Yii\CaptchaModule',
            'config' => [
                // 可选配置
            ],
        ],
    ],
];
```

#### 2. 访问路由

- `/captcha/captcha/image` - 获取验证码图片
- `/captcha/captcha/verify` - 验证

## 配置说明

### 完整配置参考

```php
<?php
return [
    // === 图片尺寸配置 ===
    'bg_width' => 240,          // 背景宽度（像素）
    'bg_height' => 150,         // 背景高度（像素）
    'mark_width' => 50,         // 滑块宽度（像素）
    'mark_height' => 50,        // 滑块高度（像素）
    
    // === 验证配置 ===
    'fault_tolerance' => 3,     // 容错像素值（越大越容易通过）
    'max_error_count' => 10,    // 最大错误次数
    'ttl' => 300,               // 验证码有效期（秒）
    
    // === 自定义图片 ===
    'background_images' => [     // 自定义背景图路径列表
        // '/path/to/bg1.png',
        // '/path/to/bg2.png',
    ],
    'slide_images' => [          // 滑块图片配置
        // 'transparent' => '/path/to/mark.png',
        // 'dark' => '/path/to/mark2.png',
        // 'icon' => '/path/to/icon.png',
    ],
    
    // === 存储配置 ===
    'storage' => [
        'driver' => 'session',   // 驱动：session, array, custom
        'session_key' => 'zxf_captcha',
        'custom_class' => null,  // 自定义存储类（driver=custom时）
    ],
    
    // === Cookie 配置 ===
    'cookie' => [
        'name' => 'zxf_captcha_key',
        'expire' => 0,           // 0 表示浏览器关闭时过期
        'path' => '/',
        'domain' => '',
        'secure' => false,       // 生产环境建议 true
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    
    // === 路由配置 ===
    'routes' => [
        'enabled' => true,
        'prefix' => 'zxf-captcha',
        'middleware' => [],
        'image_path' => 'image',
        'verify_path' => 'verify',
        'asset_path' => 'assets',
    ],
    
    // === 输出配置 ===
    'output' => [
        'format' => 'webp',      // webp 或 png
        'webp_quality' => 40,    // WebP 质量 (0-100)
        'png_quality' => 7,      // PNG 压缩级别 (0-9)
    ],
    
    // === 安全配置 ===
    'security' => [
        'frequency_limit_enabled' => true,   // 启用频率限制
        'min_request_interval' => 1,         // 最小请求间隔（秒）
        'daily_limit_enabled' => false,      // 启用每日限制
        'daily_limit' => 1000,               // 每日最大请求数
    ],
];
```

## API 文档

### PHP API

#### Captcha 类

##### 构造函数

```php
public function __construct(
    array $config = [], 
    ?StorageInterface $storage = null
)
```

**参数：**
- `$config` - 配置数组（可选）
- `$storage` - 自定义存储实例（可选）

**示例：**
```php
use zxf\Captcha\Captcha;

// 使用默认配置
$captcha = new Captcha();

// 使用自定义配置
$captcha = new Captcha([
    'fault_tolerance' => 5,
    'max_error_count' => 5,
]);
```

##### generate() - 生成验证码

```php
public function generate(array $bgImages = []): array
```

**参数：**
- `$bgImages` - 自定义背景图片路径列表（可选）

**返回值：**
```php
[
    'image' => '二进制图片数据',
    'format' => 'webp',        // 或 'png'
    'width' => 240,
    'height' => 150,
    'key' => '验证码唯一标识',
]
```

**示例：**
```php
$result = $captcha->generate();

// 输出图片
header('Content-Type: image/' . $result['format']);
echo $result['image'];
```

##### verify() - 验证偏移量

```php
public function verify(int|float|string $offset, ?string $key = null): array
```

**参数：**
- `$offset` - 用户输入的偏移量（从 cookie 自动读取 key）
- `$key` - 存储键名（可选，通常不需要手动传入）

**返回值：**
```php
// 成功
[
    'success' => true,
    'message' => '验证成功',
    'code' => 'success',
]

// 失败
[
    'success' => false,
    'message' => '验证失败，请重试',
    'code' => 'failed',              // 或 'expired', 'too_many_errors'
    'remaining_attempts' => 5,       // 剩余尝试次数
]
```

**示例：**
```php
$offset = $_POST['tn_r'] ?? 0;
$result = $captcha->verify($offset);

if ($result['success']) {
    // 验证通过
} else {
    // 验证失败
    echo $result['message'];
}
```

##### isVerified() - 检查是否已验证

```php
public function isVerified(?string $key = null): bool
```

**示例：**
```php
if ($captcha->isVerified()) {
    // 已通过验证
}
```

##### clear() - 清除验证码数据

```php
public function clear(?string $key = null): void
```

##### setKeyToCookie() - 设置 key 到 Cookie

```php
public function setKeyToCookie(string $key): void
```

**说明：**
通常在 `generate()` 后自动调用，无需手动调用。

##### clearCookie() - 清除 Cookie

```php
public function clearCookie(): void
```

**说明：**
通常在验证成功后自动清除。

#### CaptchaHelper 辅助类

```php
use zxf\Captcha\CaptchaHelper;

// 快速生成验证码
$result = CaptchaHelper::generate();

// 快速验证
$result = CaptchaHelper::verify($offset);

// 获取资源路径
$cssPath = CaptchaHelper::asset('css/captcha.css');

// 检查系统信息
$info = CaptchaHelper::getSystemInfo();
// 返回：['php_version' => '8.2.x', 'gd_enabled' => true, ...]
```

### JavaScript API

#### zxfCaptcha.init() - 初始化

```javascript
zxfCaptcha.init(options)
```

**参数：**
- `options` - 配置对象

**Options：**
| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `handleDom` | string | '.tncode' | 触发按钮选择器 |
| `getImgUrl` | string | '/zxf-captcha/image' | 获取图片接口 |
| `checkUrl` | string | '/zxf-captcha/verify' | 验证接口 |

**返回值：**
返回 zxfCaptcha 对象，支持链式调用。

**示例：**
```javascript
zxfCaptcha.init({
    handleDom: '.my-captcha',
    getImgUrl: '/api/captcha/image',
    checkUrl: '/api/captcha/verify'
});
```

#### zxfCaptcha.result() - 获取验证结果

```javascript
var isVerified = zxfCaptcha.result(); // true 或 false
```

#### zxfCaptcha.getOffset() - 获取偏移量

```javascript
var offset = zxfCaptcha.getOffset(); // 整数偏移量
```

**说明：**
验证成功后可以通过此方法获取偏移量，用于提交到后端二次验证。

#### zxfCaptcha.refresh() - 刷新验证码

```javascript
zxfCaptcha.refresh();
```

#### zxfCaptcha.show() - 显示验证码弹窗

```javascript
zxfCaptcha.show();
```

#### zxfCaptcha.hide() - 隐藏验证码弹窗

```javascript
zxfCaptcha.hide();
```

#### zxfCaptcha.reset() - 重置验证码状态

```javascript
zxfCaptcha.reset();
```

#### zxfCaptcha.onSuccess() - 成功回调

```javascript
zxfCaptcha.onSuccess(function(response) {
    console.log('验证成功', response);
    // response 包含服务器返回的数据
});
```

#### zxfCaptcha.onFail() - 失败回调

```javascript
zxfCaptcha.onFail(function(response) {
    console.log('验证失败', response);
});
```

## 自定义存储

### 实现 Redis 存储

```php
<?php
use zxf\Captcha\Contracts\StorageInterface;

class RedisStorage implements StorageInterface
{
    private $redis;
    private string $prefix;
    
    public function __construct($redis, string $prefix = 'captcha:')
    {
        $this->redis = $redis;
        $this->prefix = $prefix;
    }
    
    public function set(string $key, mixed $value, int $ttl = 300): bool
    {
        return $this->redis->setex(
            $this->makeKey($key), 
            $ttl, 
            serialize($value)
        );
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->makeKey($key));
        return $value !== false ? unserialize($value) : $default;
    }
    
    public function delete(string $key): bool
    {
        return $this->redis->del($this->makeKey($key)) > 0;
    }
    
    public function has(string $key): bool
    {
        return $this->redis->exists($this->makeKey($key));
    }
    
    public function makeKey(string $key): string
    {
        return $this->prefix . $key;
    }
}

// 使用自定义存储
$captcha = new Captcha([
    'storage' => [
        'driver' => 'custom',
        'custom_class' => RedisStorage::class,
    ]
], new RedisStorage($redis));
```

## 安全建议

### 1. 生产环境配置

```php
// config/captcha.php
return [
    'cookie' => [
        'secure' => true,        // 仅 HTTPS
        'httponly' => true,      // 禁止 JavaScript 读取
        'samesite' => 'Strict',  // 防止 CSRF
    ],
    
    'security' => [
        'frequency_limit_enabled' => true,
        'min_request_interval' => 1,
        'daily_limit_enabled' => true,
        'daily_limit' => 1000,
    ],
];
```

### 2. 服务端二次验证

前端验证通过后，必须在服务端再次验证：

```php
public function submit(Request $request, Captcha $captcha)
{
    // 必须服务端再次验证
    $result = $captcha->verify($request->input('tn_r'));
    
    if (!$result['success']) {
        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }
    
    // 继续处理业务逻辑...
}
```

### 3. HTTPS 强制

生产环境必须使用 HTTPS：

```php
// Laravel 中间件示例
public function handle($request, Closure $next)
{
    if (!$request->secure()) {
        return redirect()->secure($request->getRequestUri());
    }
    return $next($request);
}
```

### 4. 验证码使用一次后失效

验证成功后立即清除：

```php
if ($result['success']) {
    $captcha->clear();
    // 处理业务...
}
```

## 性能优化

### 1. 启用 WebP

WebP 格式比 PNG 体积小 30-50%：

```php
'output' => [
    'format' => 'webp',
    'webp_quality' => 40,  // 平衡质量和大小
],
```

### 2. 资源 CDN

将 CSS/JS 文件放到 CDN：

```html
<link rel="stylesheet" href="https://cdn.example.com/captcha.css">
<script src="https://cdn.example.com/captcha.js"></script>
<script>
zxfCaptcha.init({
    getImgUrl: '/zxf-captcha/image',  // 图片接口仍需走原服务器
    checkUrl: '/zxf-captcha/verify'
});
</script>
```

### 3. Redis 存储

高并发场景使用 Redis：

```php
$captcha = new Captcha([
    'storage' => [
        'driver' => 'custom',
        'custom_class' => RedisStorage::class,
    ]
], $redisStorage);
```

## 常见问题

### Q: 验证码图片无法显示？

**A:** 检查以下几点：
1. GD 扩展是否安装：`php -m | grep gd`
2. 资源目录权限：`chmod -R 755 resources/`
3. 路由是否正确注册

### Q: 验证总是失败？

**A:** 可能原因：
1. Cookie 被禁用（检查浏览器设置）
2. 验证码已过期（默认 5 分钟）
3. Session 未正确启动

### Q: 如何自定义背景图？

**A:**
```php
$captcha = new Captcha([
    'background_images' => [
        '/path/to/your/bg1.png',
        '/path/to/your/bg2.png',
    ]
]);
```

背景图尺寸建议：240x150 像素

### Q: 前后端分离项目如何使用？

**A:**
后端提供 API：
```php
// 生成验证码
Route::get('/api/captcha', function (Captcha $captcha) {
    $result = $captcha->generate();
    $captcha->setKeyToCookie($result['key']);
    
    return response($result['image'])
        ->header('Content-Type', 'image/' . $result['format']);
});
```

前端：
```javascript
zxfCaptcha.init({
    getImgUrl: 'https://api.example.com/captcha',
    checkUrl: 'https://api.example.com/captcha/verify'
});
```

注意：跨域时需要配置 CORS 和 SameSite=None。

### Q: 移动端适配问题？

**A:** 验证码已内置移动端触摸事件支持，无需额外配置。如果遇到问题，检查 viewport 设置：

```html
<meta name="viewport" content="width=device-width, initial-scale=1.0">
```

## 更新日志

### v2.0.0 (2026-04-01)

- 完全重写为 PHP 8.2+ 扩展包
- 新增多框架支持（Laravel、ThinkPHP、Yii）
- 新增 Cookie + Session 双重验证机制
- 新增频率限制功能
- 新增自定义存储接口
- 优化前端 JS 代码

## License

MIT License - 详见 [LICENSE](LICENSE) 文件

## 作者

- 作者：zhaoxianfang
- 邮箱：zhaoxianfang@163.com
- 网站：https://weisifang.com
- GitHub：https://github.com/zhaoxianfang/captcha

## 贡献

欢迎提交 Issue 和 Pull Request！

## 致谢

感谢使用 zxf Captcha！