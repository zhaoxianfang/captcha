<?php

/**
 * zxf/captcha - 辅助函数
 *
 * @package     zxf\Captcha
 * @author      zhaoxianfang <zhaoxianfang@163.com>
 * @license     MIT
 */

use zxf\Captcha\Captcha;

if (!function_exists('xf_captcha')) {
    /**
     * 获取验证码实例或生成验证码图片
     *
     * 用法：
     * 1. xf_captcha() - 获取 Captcha 实例
     * 2. xf_captcha(['/path/to/bg1.png', '/path/to/bg2.png']) - 使用自定义背景图生成验证码
     * 3. xf_captcha(['fault_tolerance' => 5]) - 使用自定义配置
     *
     * @param array $configOrBgImages 配置数组或背景图片数组
     *
     * @return Captcha
     */
    function xf_captcha(array $configOrBgImages = []): Captcha
    {
        // 如果数组为空，直接返回新的 Captcha 实例
        if (empty($configOrBgImages)) {
            return new Captcha([]);
        }

        // 获取第一个元素的值
        $firstValue = reset($configOrBgImages);
        $firstKey = key($configOrBgImages);

        // 如果参数包含文件路径（数字键且值为字符串路径），则视为背景图片数组
        if (is_int($firstKey) && is_string($firstValue) && file_exists($firstValue)) {
            $config = [];
            if (function_exists('config')) {
                $config = config('xf_captcha', []);
            }
            $captcha = new Captcha($config);
            $captcha->make($configOrBgImages);
            return $captcha;
        }

        // 否则视为配置数组
        return new Captcha($configOrBgImages);
    }
}

if (!function_exists('xf_captcha_check')) {
    /**
     * 验证验证码
     *
     * @param string|int  $offset      用户滑动的偏移量（滑动验证码使用）
     * @param string|null $token       验证令牌（双重验证模式使用）
     * @param array       $clickPoints 用户点击的坐标点（点击验证码使用）
     *
     * @return bool 验证是否通过
     */
    function xf_captcha_check(string|int $offset = '', ?string $token = null, array $clickPoints = []): bool
    {
        $config = [];
        if (function_exists('config')) {
            $config = config('xf_captcha', []);
        }

        $captcha = new Captcha($config);
        $result = $captcha->verify($offset ?: null, $token, $clickPoints);
        return $result['success'];
    }
}

if (!function_exists('xf_captcha_refresh')) {
    /**
     * 刷新验证码（清除验证状态）
     *
     * @return void
     */
    function xf_captcha_refresh(): void
    {
        $config = [];
        if (function_exists('config')) {
            $config = config('xf_captcha', []);
        }

        $captcha = new Captcha($config);
        $captcha->refresh();
    }
}

if (!function_exists('xf_captcha_is_checked')) {
    /**
     * 检查验证码是否已通过验证
     *
     * @return bool
     */
    function xf_captcha_is_checked(): bool
    {
        $config = [];
        if (function_exists('config')) {
            $config = config('xf_captcha', []);
        }

        $captcha = new Captcha($config);
        return $captcha->isChecked();
    }
}

if (!function_exists('xf_captcha_html')) {
    /**
     * 获取验证码 HTML 代码
     *
     * @param string $selector 触发验证码的 CSS 选择器
     * @param array  $options  额外配置选项
     *
     * @return string HTML 代码
     */
    function xf_captcha_html(string $selector = '.xf-captcha', array $options = []): string
    {
        $config = [];
        if (function_exists('config')) {
            $config = config('xf_captcha', []);
        }

        $frontend = array_merge(
            $config['frontend'] ?? [],
            $options,
            ['handleDom' => $selector]
        );

        // 生成 JavaScript 配置
        $routePrefix = $config['route_prefix'] ?? 'xf_captcha';
        $jsConfig = json_encode([
            'handleDom' => $selector,
            'dataUrl' => $frontend['data_url'] ?? '/' . $routePrefix . '/data',
            'imageUrl' => $frontend['image_url'] ?? '/' . $routePrefix . '/image',
            'checkUrl' => $frontend['check_url'] ?? '/' . $routePrefix . '/check',
            'placeholder' => $frontend['placeholder'] ?? '点击按钮进行验证',
            'slideText' => $frontend['slide_text'] ?? '拖动左边滑块完成上方拼图',
            'clickText' => $frontend['click_text'] ?? '请按照顺序点击图片中的文字',
            'successText' => $frontend['success_text'] ?? '✓ 验证成功',
            'failText' => $frontend['fail_text'] ?? '验证失败，请重试',
            'showClose' => $frontend['show_close'] ?? true,
            'showRefresh' => $frontend['show_refresh'] ?? true,
            'showRipple' => $frontend['show_ripple'] ?? true,
        ]);

        return <<<HTML
<div class="xf-captcha" id="xf-captcha-{$selector}"></div>
<link rel="stylesheet" href="/{$routePrefix}/css">
<script src="/{$routePrefix}/js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        xfCaptcha.init({$jsConfig});
    });
</script>
HTML;
    }
}

if (!function_exists('xf_captcha_script')) {
    /**
     * 获取验证码初始化脚本（不包含资源引用）
     *
     * @param string $selector 触发验证码的 CSS 选择器
     * @param array  $options  额外配置选项
     *
     * @return string JavaScript 代码
     */
    function xf_captcha_script(string $selector = '.xf-captcha', array $options = []): string
    {
        $config = [];
        if (function_exists('config')) {
            $config = config('xf_captcha', []);
        }

        $frontend = array_merge(
            $config['frontend'] ?? [],
            $options,
            ['handleDom' => $selector]
        );

        $routePrefix = $config['route_prefix'] ?? 'xf_captcha';
        $jsConfig = json_encode([
            'handleDom' => $selector,
            'dataUrl' => $frontend['data_url'] ?? '/' . $routePrefix . '/data',
            'imageUrl' => $frontend['image_url'] ?? '/' . $routePrefix . '/image',
            'checkUrl' => $frontend['check_url'] ?? '/' . $routePrefix . '/check',
            'placeholder' => $frontend['placeholder'] ?? '点击按钮进行验证',
            'slideText' => $frontend['slide_text'] ?? '拖动左边滑块完成上方拼图',
            'clickText' => $frontend['click_text'] ?? '请按照顺序点击图片中的文字',
            'successText' => $frontend['success_text'] ?? '✓ 验证成功',
            'failText' => $frontend['fail_text'] ?? '验证失败，请重试',
            'showClose' => $frontend['show_close'] ?? true,
            'showRefresh' => $frontend['show_refresh'] ?? true,
            'showRipple' => $frontend['show_ripple'] ?? true,
        ]);

        return <<<JS
<script>
    document.addEventListener('DOMContentLoaded', function() {
        xfCaptcha.init({$jsConfig});
    });
</script>
JS;
    }
}
