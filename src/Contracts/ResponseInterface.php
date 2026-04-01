<?php

declare(strict_types=1);

namespace zxf\Captcha\Contracts;

/**
 * 响应接口
 *
 * 兼容不同框架的响应处理
 */
interface ResponseInterface
{
    /**
     * 输出图片响应
     *
     * @param string $imageData 图片二进制数据
     * @param string $format 图片格式
     * @return mixed
     */
    public function image(string $imageData, string $format): mixed;

    /**
     * 输出 JSON 响应
     *
     * @param array $data 响应数据
     * @param int $statusCode HTTP 状态码
     * @return mixed
     */
    public function json(array $data, int $statusCode = 200): mixed;

    /**
     * 输出资源文件
     *
     * @param string $content 文件内容
     * @param string $mimeType MIME 类型
     * @param int $maxAge 缓存时间（秒）
     * @return mixed
     */
    public function asset(string $content, string $mimeType, int $maxAge = 86400): mixed;
}
