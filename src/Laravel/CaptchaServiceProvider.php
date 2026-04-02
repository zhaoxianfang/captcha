<?php

/**
 * zxf/captcha - Laravel 服务提供者
 *
 * @package     zxf\Captcha\Laravel
 * @author      zhaoxianfang <zhaoxianfang@163.com>
 * @license     MIT
 */

declare(strict_types=1);

namespace zxf\Captcha\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Route;
use zxf\Captcha\Captcha;
use zxf\Captcha\Http\CaptchaController;

/**
 * Laravel 验证码服务提供者
 *
 * 该类为 Laravel 框架提供验证码服务的注册、配置加载和验证器扩展
 *
 * @author zhaoxianfang
 * @since  2.0.0
 */
class CaptchaServiceProvider extends ServiceProvider
{
    /**
     * 服务提供者是否延迟加载
     *
     * @var bool
     */
    protected bool $defer = false;

    /**
     * 启动服务
     *
     * 注册路由、验证器扩展等
     *
     * @return void
     */
    public function boot(): void
    {
        // 计算包根目录
        $packageRoot = dirname(__DIR__, 2);

        // 发布配置文件
        $configSource = $packageRoot . '/config/xf_captcha.php';
        $configTarget = config_path('xf_captcha.php');

        if (file_exists($configSource)) {
            $this->publishes([$configSource => $configTarget], 'xf-captcha-config');
        }

        // 发布资源文件
        $assetsSource = $packageRoot . '/resources/assets';
        $assetsTarget = public_path('vendor/xf-captcha');

        if (is_dir($assetsSource)) {
            $this->publishes([$assetsSource => $assetsTarget], 'xf-captcha-assets');
        }

        // 合并配置
        if (file_exists($configSource)) {
            $this->mergeConfigFrom($configSource, 'xf_captcha');
        }

        // 注册路由
        $this->registerRoutes();

        // 注册验证器扩展
        $this->registerValidator();
    }

    /**
     * 注册服务
     *
     * 将 Captcha 类绑定到服务容器
     *
     * @return void
     */
    public function register(): void
    {
        // 单例绑定 Captcha 类
        $this->app->singleton('xfCaptcha', function ($app) {
            $config = $app['config']->get('xf_captcha', []);
            return new Captcha($config);
        });

        // 别名绑定，便于通过 Captcha 类名解析
        $this->app->alias('xfCaptcha', Captcha::class);
    }

    /**
     * 注册验证码路由
     *
     * 注册用于获取验证码图片、验证结果、静态资源的路由
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $config = $this->app['config']->get('xf_captcha', []);
        $prefix = $config['route_prefix'] ?? 'captcha';
        $middleware = $config['route_middleware'] ?? ['web'];

        Route::group([
            'prefix' => $prefix,
            'middleware' => $middleware,
        ], function () {
            // 获取验证码图片
            Route::get('image', [CaptchaController::class, 'image'])->name('xf-captcha.image');

            // 验证验证码
            Route::post('check', [CaptchaController::class, 'check'])->name('xf-captcha.check');
            Route::get('check', [CaptchaController::class, 'check']);

            // 静态资源
            Route::get('js', [CaptchaController::class, 'js'])->name('xf-captcha.js');
            Route::get('css', [CaptchaController::class, 'css'])->name('xf-captcha.css');
            Route::get('icon', [CaptchaController::class, 'icon'])->name('xf-captcha.icon');
        });
    }

    /**
     * 注册验证器扩展
     *
     * 扩展 Laravel 验证器，支持 xfCaptcha 验证规则
     *
     * @return void
     */
    protected function registerValidator(): void
    {
        // 注册 xfCaptcha 验证规则
        Validator::extend('xfCaptcha', function ($attribute, $value, $parameters, $validator) {
            $captcha = app('xfCaptcha');
            return $captcha->check($value);
        }, '滑动验证码验证失败');

        // 设置自定义验证消息
        Validator::replacer('xfCaptcha', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, '滑动验证码验证失败');
        });

        // 兼容小写写法
        Validator::extend('xfcaptcha', function ($attribute, $value, $parameters, $validator) {
            $captcha = app('xfCaptcha');
            return $captcha->check($value);
        }, '滑动验证码验证失败');
    }

    /**
     * 获取提供者所提供的服务
     *
     * @return array
     */
    public function provides(): array
    {
        return ['xfCaptcha', Captcha::class];
    }
}
