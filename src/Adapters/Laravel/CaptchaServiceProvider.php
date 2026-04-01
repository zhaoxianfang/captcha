<?php

declare(strict_types=1);

namespace zxf\Captcha\Adapters\Laravel;

use Illuminate\Support\ServiceProvider;
use zxf\Captcha\Captcha;

/**
 * Laravel 服务提供者
 */
class CaptchaServiceProvider extends ServiceProvider
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/captcha.php', 'captcha');

        $this->app->singleton(Captcha::class, function ($app) {
            return new Captcha($app['config']->get('captcha', []));
        });

        $this->app->alias(Captcha::class, 'captcha');
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
        // 发布配置
        $this->publishes([
            __DIR__ . '/../../../config/captcha.php' => config_path('captcha.php'),
        ], 'captcha-config');

        // 发布资源
        $this->publishes([
            __DIR__ . '/../../../resources/assets' => public_path('vendor/zxf-captcha'),
        ], 'captcha-assets');

        // 注册路由
        $this->registerRoutes();
    }

    /**
     * 注册路由
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $config = $this->app['config']->get('captcha.routes', []);

        if (!($config['enabled'] ?? true)) {
            return;
        }

        $router = $this->app['router'];
        $prefix = $config['prefix'] ?? 'zxf-captcha';
        $middleware = $config['middleware'] ?? [];

        $router->group([
            'prefix' => $prefix,
            'middleware' => $middleware,
            'namespace' => 'zxf\Captcha\Adapters\Laravel',
        ], function ($router) use ($config) {
            $imagePath = $config['image_path'] ?? 'image';
            $verifyPath = $config['verify_path'] ?? 'verify';
            $assetPath = $config['asset_path'] ?? 'assets';

            // 验证码图片
            $router->get($imagePath, [CaptchaController::class, 'image'])
                ->name('captcha.image');

            // 验证
            $router->post($verifyPath, [CaptchaController::class, 'verify'])
                ->name('captcha.verify');
            $router->get($verifyPath, [CaptchaController::class, 'verify'])
                ->name('captcha.verify.get');

            // 资源文件
            $router->get($assetPath . '/captcha.css', [CaptchaController::class, 'css'])
                ->name('captcha.css');
            $router->get($assetPath . '/captcha.js', [CaptchaController::class, 'js'])
                ->name('captcha.js');
            $router->get($assetPath . '/img/{filename}', [CaptchaController::class, 'img'])
                ->name('captcha.img');
        });
    }
}
