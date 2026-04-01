<?php
/*
 * tncode 1.2
 * https://gitee.com/zhaoxianfang/tncode
 */
error_reporting(0);
require_once dirname(__FILE__) . '/TnCode.php';

$tn = new TnCode([
    'tool_icon_img'         => dirname(__FILE__) . '/img/icon.png', // 前端使用的图标组图片
    'slide_transparent_img' => dirname(__FILE__) . '/img/mark_01.png', // 透明滑块图片
    'slide_dark_img'        => dirname(__FILE__) . '/img/mark_02.png', // 黑色滑块图片
]);
$tn->make(
    [
//    dirname(__FILE__) . '/bg/1.png',
//    dirname(__FILE__) . '/bg/2.png',
//    dirname(__FILE__) . '/bg/3.png'
]
);
