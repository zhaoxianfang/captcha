<?php

declare(strict_types=1);

namespace zxf\Captcha\Adapters\Laravel;

use Illuminate\Support\Facades\Facade;
use zxf\Captcha\Captcha;

/**
 * Laravel 门面
 *
 * @method static array generate(array $bgImages = [])
 * @method static array verify(int|float|string $offset, ?string $key = null)
 * @method static bool isVerified(?string $key = null)
 * @method static void clear(?string $key = null)
 * @method static array getConfig()
 *
 * @see Captcha
 */
class CaptchaFacade extends Facade
{
    /**
     * 获取组件注册名称
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'captcha';
    }
}
