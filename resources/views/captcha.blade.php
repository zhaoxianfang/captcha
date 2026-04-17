{{--
    xfCaptcha - Laravel Blade 模板

    使用方法：
    @include('xf-captcha::captcha', ['selector' => '.xf-captcha'])

    完整参数示例：
    @include('xf-captcha::captcha', [
        'selector' => '.xf-captcha',
        'theme' => 'auto',
        'inputName' => 'xf_captcha_token',
        'placeholder' => '点击按钮进行验证',
        'width' => '100%',
        'height' => '40px',
        'borderRadius' => '4px',
        'onSuccess' => 'function(token) { console.log("验证成功", token); }',
        'onFail' => 'function() { console.log("验证失败"); }',
        'onClose' => 'function() { console.log("关闭"); }'
    ])
--}}

@php
    $elementId = 'xf-captcha-' . uniqid();
    $finalSelector = $selector ?? '.' . $elementId;
    $finalShowClose = var_export($showClose ?? config('xf_captcha.frontend.show_close', true), true);
    $finalShowRefresh = var_export($showRefresh ?? config('xf_captcha.frontend.show_refresh', true), true);
    $finalShowRipple = var_export($showRipple ?? config('xf_captcha.frontend.show_ripple', true), true);
    $finalTheme = $theme ?? config('xf_captcha.frontend.theme', 'auto');
    $finalInputName = $inputName ?? config('xf_captcha.frontend.input_name', 'xf_captcha_token');
    $finalAutoInsertInput = var_export($autoInsertInput ?? config('xf_captcha.frontend.auto_insert_input', true), true);

    $inlineStyle = '';
    if (!empty($width)) $inlineStyle .= 'width:' . $width . ';';
    if (!empty($height)) $inlineStyle .= 'height:' . $height . ';';
    if (!empty($borderRadius)) $inlineStyle .= 'border-radius:' . $borderRadius . ';';
@endphp

<div class="xf-captcha {{ $elementId }}" {!! $attributes ?? '' !!} {!! $inlineStyle ? 'style="' . $inlineStyle . '"' : '' !!}></div>

<link rel="stylesheet" href="{{ route('xf-captcha.css') }}">
<script src="{{ route('xf-captcha.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var captcha = xfCaptcha.init({
            handleDom: '.{{ $elementId }}',
            getImgUrl: '{{ route('xf-captcha.image') }}',
            checkUrl: '{{ route('xf-captcha.check') }}',
            placeholder: '{{ $placeholder ?? config('xf_captcha.frontend.placeholder', '点击按钮进行验证') }}',
            slideText: '{{ $slideText ?? config('xf_captcha.frontend.slide_text', '拖动左边滑块完成上方拼图') }}',
            successText: '{{ $successText ?? config('xf_captcha.frontend.success_text', '✓ 验证成功') }}',
            failText: '{{ $failText ?? config('xf_captcha.frontend.fail_text', '验证失败，请重试') }}',
            showClose: {{ $finalShowClose }},
            showRefresh: {{ $finalShowRefresh }},
            showRipple: {{ $finalShowRipple }},
            theme: '{{ $finalTheme }}',
            inputName: '{{ $finalInputName }}',
            autoInsertInput: {{ $finalAutoInsertInput }}
        });
        @if(isset($onSuccess) && trim($onSuccess) !== '')
        captcha.onSuccess({!! $onSuccess !!});
        @endif
        @if(isset($onFail) && trim($onFail) !== '')
        captcha.onFail({!! $onFail !!});
        @endif
        @if(isset($onClose) && trim($onClose) !== '')
        captcha.onClose({!! $onClose !!});
        @endif
    });
</script>
