<?php

declare(strict_types=1);

namespace zxf\Captcha\Adapters\ThinkPHP;

use think\facade\Route;
use zxf\Captcha\Captcha;

/**
 * ThinkPHP 验证码服务
 */
class CaptchaService extends \think\Service
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind('captcha', function () {
            return new Captcha($this->getConfig());
        });
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerRoutes();
    }

    /**
     * 注册路由
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $config = $this->getConfig();
        $routeConfig = $config['routes'] ?? [];

        if (!($routeConfig['enabled'] ?? true)) {
            return;
        }

        $prefix = $routeConfig['prefix'] ?? 'zxf-captcha';
        $imagePath = $routeConfig['image_path'] ?? 'image';
        $verifyPath = $routeConfig['verify_path'] ?? 'verify';
        $assetPath = $routeConfig['asset_path'] ?? 'assets';

        // 验证码图片
        Route::get($prefix . '/' . $imagePath, function () {
            return (new CaptchaController())->image();
        })->name('captcha.image');

        // 验证
        Route::post($prefix . '/' . $verifyPath, function () {
            return (new CaptchaController())->verify();
        })->name('captcha.verify');
        Route::get($prefix . '/' . $verifyPath, function () {
            return (new CaptchaController())->verify();
        })->name('captcha.verify.get');

        // 资源文件
        Route::get($prefix . '/' . $assetPath . '/captcha.css', function () {
            return (new CaptchaController())->css();
        })->name('captcha.css');

        Route::get($prefix . '/' . $assetPath . '/captcha.js', function () {
            return (new CaptchaController())->js();
        })->name('captcha.js');

        Route::get($prefix . '/' . $assetPath . '/img/:filename', function ($filename) {
            return (new CaptchaController())->img($filename);
        })->name('captcha.img');
    }

    /**
     * 获取配置
     *
     * @return array
     */
    protected function getConfig(): array
    {
        return config('captcha', []);
    }
}
