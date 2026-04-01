<?php

declare(strict_types=1);

namespace zxf\Captcha\Adapters\Yii;

use Yii;
use yii\base\Module;

/**
 * Yii 验证码模块
 */
class CaptchaModule extends Module
{
    /**
     * 控制器命名空间
     */
    public $controllerNamespace = 'zxf\Captcha\Adapters\Yii';

    /**
     * 配置
     */
    public array $config = [];

    /**
     * 初始化
     */
    public function init(): void
    {
        parent::init();

        // 合并配置
        $this->config = array_merge(
            require __DIR__ . '/../../../config/captcha.php',
            $this->config
        );

        // 注册组件
        Yii::$app->set('zxfCaptcha', [
            'class' => 'zxf\Captcha\Captcha',
        ]);
    }
}
