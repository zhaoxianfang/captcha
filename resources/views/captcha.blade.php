{{--
    xfCaptcha - Laravel Blade 模板
    
    使用方法：
    @include('xf-captcha::captcha', ['selector' => '.xf-captcha'])
--}}

<div class="xf-captcha" {{ $attributes ?? '' }}></div>

@push('styles')
<link rel="stylesheet" href="{{ route('xf-captcha.css') }}">
@endpush

@push('scripts')
<script src="{{ route('xf-captcha.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        xfCaptcha.init({
            handleDom: '{{ $selector ?? ".xf-captcha" }}',
            getImgUrl: '{{ route('xf-captcha.image') }}',
            checkUrl: '{{ route('xf-captcha.check') }}',
            placeholder: '{{ $placeholder ?? config('xf_captcha.frontend.placeholder', '点击按钮进行验证') }}',
            slideText: '{{ $slideText ?? config('xf_captcha.frontend.slide_text', '拖动左边滑块完成上方拼图') }}',
            successText: '{{ $successText ?? config('xf_captcha.frontend.success_text', '✓ 验证成功') }}',
            failText: '{{ $failText ?? config('xf_captcha.frontend.fail_text', '验证失败，请重试') }}',
            showClose: {{ $showClose ?? 'true' }},
            showRefresh: {{ $showRefresh ?? 'true' }},
            showRipple: {{ $showRipple ?? 'true' }}
        })
        @if(isset($onSuccess))
        .onSuccess({{ $onSuccess }})
        @endif
        @if(isset($onFail))
        .onFail({{ $onFail }})
        @endif
        @if(isset($onClose))
        .onClose({{ $onClose }})
        @endif
        ;
    });
</script>
@endpush
