<?php

declare(strict_types=1);

namespace zxf\Captcha\Exceptions;

use Exception;

/**
 * 验证码异常类
 */
class CaptchaException extends Exception
{
    /**
     * 错误码定义
     */
    public const ERROR_INVALID_CONFIG = 1001;
    public const ERROR_IMAGE_GENERATE_FAILED = 1002;
    public const ERROR_STORAGE_FAILED = 1003;
    public const ERROR_VALIDATION_FAILED = 1004;
    public const ERROR_RATE_LIMIT = 1005;
    public const ERROR_INVALID_OFFSET = 1006;
    public const ERROR_SESSION_NOT_STARTED = 1007;
    public const ERROR_UNSUPPORTED_DRIVER = 1008;

    /**
     * 快速创建配置错误异常
     *
     * @param string $message
     * @return static
     */
    public static function invalidConfig(string $message): self
    {
        return new self($message, self::ERROR_INVALID_CONFIG);
    }

    /**
     * 快速创建图片生成失败异常
     *
     * @param string $message
     * @return static
     */
    public static function imageGenerateFailed(string $message): self
    {
        return new self($message, self::ERROR_IMAGE_GENERATE_FAILED);
    }

    /**
     * 快速创建验证失败异常
     *
     * @param string $message
     * @return static
     */
    public static function validationFailed(string $message = '验证失败'): self
    {
        return new self($message, self::ERROR_VALIDATION_FAILED);
    }

    /**
     * 快速创建频率限制异常
     *
     * @param string $message
     * @return static
     */
    public static function rateLimit(string $message = '请求过于频繁'): self
    {
        return new self($message, self::ERROR_RATE_LIMIT);
    }
}
