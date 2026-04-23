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
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use zxf\Captcha\Captcha;
use zxf\Captcha\Http\CaptchaController;

/**
 * Laravel 验证码服务提供者
 *
 * 该类为 Laravel 11+ 框架提供验证码服务的注册、配置加载和验证器扩展
 *
 * @author zhaoxianfang
 * @since  2.0.0
 */
class CaptchaServiceProvider extends ServiceProvider
{
    /**
     * 启动服务
     *
     * 注册路由、验证器扩展等
     *
     * @return void
     */
    public function boot(): void
    {
        $packageRoot = dirname(__DIR__, 2);

        // 发布配置文件
        $configSource = $packageRoot . '/config/xf_captcha.php';
        if (file_exists($configSource)) {
            $this->publishes([$configSource => config_path('xf_captcha.php')], 'xf-captcha-config');
            $this->mergeConfigFrom($configSource, 'xf_captcha');
        }

        // 发布资源文件
        $assetsSource = $packageRoot . '/resources/assets';
        if (is_dir($assetsSource)) {
            $this->publishes([$assetsSource => public_path('vendor/xf-captcha')], 'xf-captcha-assets');
        }

        // 发布视图文件
        $viewsSource = $packageRoot . '/resources/views';
        if (is_dir($viewsSource)) {
            $this->publishes([$viewsSource => resource_path('views/vendor/xf-captcha')], 'xf-captcha-views');
            $this->loadViewsFrom($viewsSource, 'xf-captcha');
        }

        // 注册路由
        $this->registerRoutes();

        // 注册验证器扩展
        $this->registerValidator();
    }

    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('xfCaptcha', function ($app) {
            $config = $app['config']->get('xf_captcha', []);
            return new Captcha($config);
        });

        $this->app->alias('xfCaptcha', Captcha::class);
    }

    /**
     * 注册验证码路由（非闭包形式）
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $config = $this->app['config']->get('xf_captcha', []);
        $prefix = $config['route_prefix'] ?? 'xf_captcha';

        Route::prefix($prefix)->controller(CaptchaController::class)->group(function () {
            // 验证码数据接口（支持滑动和点击验证码）
            Route::get('/data', 'data')->name('xf-captcha.data');

            // 验证码图片（向后兼容）
            Route::get('/image', 'image')->name('xf-captcha.image');

            // 验证接口
            Route::match(['get', 'post'], '/check', 'check')->name('xf-captcha.check');

            // JS 文件
            Route::get('/js', 'js')->name('xf-captcha.js');

            // CSS 文件
            Route::get('/css', 'css')->name('xf-captcha.css');

            // 图标文件
            Route::get('/icon', 'icon')->name('xf-captcha.icon');
        });
    }

    /**
     * 注册验证器扩展
     *
     * @return void
     */
    protected function registerValidator(): void
    {
        $extend = function ($attribute, $value, $parameters, $validator) {
            $captcha = app('xfCaptcha');
            $verifyMode = $captcha->getConfig('verify_mode', Captcha::VERIFY_DUAL);

            // 双重验证模式下，表单提交必须提供有效的 token，防止通过 captcha_r 绕过二次验证
            if ($verifyMode === Captcha::VERIFY_DUAL && empty($value)) {
                return false;
            }

            $result = $captcha->verify(null, $value);
            return $result['success'];
        };

        Validator::extend('xfCaptcha', $extend, '验证码验证失败');
        Validator::extend('xfcaptcha', $extend, '验证码验证失败');
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
