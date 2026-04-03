<?php

/**
 * zxf/captcha - Laravel Facade
 *
 * @package     zxf\Captcha\Laravel\Facades
 * @author      zhaoxianfang <zhaoxianfang@163.com>
 * @license     MIT
 */

declare(strict_types=1);

namespace zxf\Captcha\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Laravel Captcha Facade
 *
 * 提供静态方式访问验证码服务
 *
 * @method static \zxf\Captcha\Captcha getFacadeRoot()
 * @method static void make(array $bgImages = [])
 * @method static string makeRaw(array $bgImages = [])
 * @method static bool check(string|int $offset = '')
 * @method static bool isChecked()
 * @method static void refresh()
 * @method static mixed getConfig(string $key, mixed $default = null)
 * @method static \zxf\Captcha\Captcha setConfig(string $key, mixed $value)
 * @method static \zxf\Captcha\Captcha setConfigs(array $config)
 *
 * @see \zxf\Captcha\Captcha
 *
 * @author zhaoxianfang
 * @since  2.0.0
 */
class Captcha extends Facade
{
    /**
     * 获取组件的注册名称
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'xfCaptcha';
    }
}
