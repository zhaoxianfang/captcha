<?php

/**
 * zxf/captcha 配置文件
 *
 * 本配置文件适用于所有支持的框架，框架会自动加载此配置
 * 
 * @package zxf\Captcha
 * @author zhaoxianfang
 * @license MIT
 */

return [
    /**
     * 验证码图片宽度（像素）
     */
    'bg_width' => 240,

    /**
     * 验证码图片高度（像素）
     */
    'bg_height' => 150,

    /**
     * 滑块宽度（像素）
     */
    'mark_width' => 50,

    /**
     * 滑块高度（像素）
     */
    'mark_height' => 50,

    /**
     * 容错像素值（越大用户体验越好，但安全性降低）
     * 建议值：2-5
     */
    'fault_tolerance' => 3,

    /**
     * 最大错误次数（超过后需要重新刷新验证码）
     */
    'max_error_count' => 10,

    /**
     * 验证码有效期（秒）
     */
    'ttl' => 300,

    /**
     * 背景图片路径（绝对路径或相对于资源目录的路径）
     * 如果为空，将使用默认背景图
     * 
     * 示例：
     * 'background_images' => [
     *     '/path/to/bg1.png',
     *     '/path/to/bg2.png',
     * ],
     */
    'background_images' => [],

    /**
     * 滑块图片配置
     * 如果不需要自定义，保持空数组即可使用默认图片
     */
    'slide_images' => [
        // 透明滑块图片（用于背景缺口）
        // 'transparent' => '/path/to/mark.png',
        // 深色滑块图片（用于滑动块）
        // 'dark' => '/path/to/mark2.png',
        // 图标组图片
        // 'icon' => '/path/to/icon.png',
    ],

    /**
     * 存储配置
     */
    'storage' => [
        // 存储驱动：session, array, custom
        'driver' => 'session',

        // Session 配置
        'session_key' => 'zxf_captcha',

        // 自定义存储类（用于 custom 驱动）
        'custom_class' => null,
    ],

    /**
     * Cookie 配置
     * 用于存储验证码标识
     */
    'cookie' => [
        // Cookie 名称
        'name' => 'zxf_captcha_key',
        
        // 过期时间（0 表示浏览器关闭时过期）
        'expire' => 0,
        
        // 路径
        'path' => '/',
        
        // 域名
        'domain' => '',
        
        // 仅 HTTPS
        'secure' => false,
        
        // HTTP Only
        'httponly' => true,
        
        // SameSite 属性 (None, Lax, Strict)
        'samesite' => 'Lax',
    ],

    /**
     * 路由配置
     */
    'routes' => [
        // 是否启用内置路由
        'enabled' => true,

        // 路由前缀
        'prefix' => 'zxf-captcha',

        // 路由中间件（Laravel/ThinkPHP）
        'middleware' => [],

        // 获取验证码图片路由
        'image_path' => 'image',

        // 验证路由
        'verify_path' => 'verify',

        // 资源路由
        'asset_path' => 'assets',
    ],

    /**
     * 输出配置
     */
    'output' => [
        // 默认输出格式：webp, png
        // webp 压缩率更高，但部分浏览器不支持
        'format' => 'webp',

        // WebP 质量 (0-100)
        'webp_quality' => 40,

        // PNG 压缩级别 (0-9)
        'png_quality' => 7,
    ],

    /**
     * 安全配置
     */
    'security' => [
        // 启用 IP 频率限制
        'frequency_limit_enabled' => true,

        // 最小请求间隔（秒）
        'min_request_interval' => 1,

        // 启用 IP 每日最大请求数限制
        'daily_limit_enabled' => false,

        // 单个 IP 每日最大请求数
        'daily_limit' => 1000,
    ],
];
