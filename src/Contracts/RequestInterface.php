<?php

declare(strict_types=1);

namespace zxf\Captcha\Contracts;

/**
 * 请求接口
 *
 * 兼容不同框架的请求处理
 */
interface RequestInterface
{
    /**
     * 获取请求参数
     *
     * @param string $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed;

    /**
     * 获取客户端 IP 地址
     *
     * @return string
     */
    public function getClientIp(): string;

    /**
     * 获取请求方法
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * 检查是否为 AJAX 请求
     *
     * @return bool
     */
    public function isAjax(): bool;

    /**
     * 获取请求头
     *
     * @param string $key 头名称
     * @param mixed $default 默认值
     * @return mixed
     */
    public function header(string $key, mixed $default = null): mixed;
}
