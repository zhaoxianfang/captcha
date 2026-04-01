/**
 * zxf Captcha - 滑动验证码 JavaScript
 * @package zxf/captcha
 * @version 2.0.0
 * @author zhaoxianfang
 * @license MIT
 */

(function (window, document) {
    "use strict";

    /**
     * Cookie 操作工具
     */
    var CookieUtil = {
        /**
         * 获取 Cookie
         * @param {string} name
         * @returns {string|null}
         */
        get: function(name) {
            var value = "; " + document.cookie;
            var parts = value.split("; " + name + "=");
            if (parts.length === 2) {
                return decodeURIComponent(parts.pop().split(";").shift());
            }
            return null;
        },

        /**
         * 设置 Cookie
         * @param {string} name
         * @param {string} value
         * @param {object} options
         */
        set: function(name, value, options) {
            options = options || {};
            var cookieString = name + "=" + encodeURIComponent(value);
            
            if (options.expires) {
                if (typeof options.expires === 'number') {
                    var days = options.expires;
                    var t = options.expires = new Date();
                    t.setTime(t.getTime() + days * 24 * 60 * 60 * 1000);
                }
                cookieString += "; expires=" + options.expires.toUTCString();
            }
            
            if (options.path) cookieString += "; path=" + options.path;
            if (options.domain) cookieString += "; domain=" + options.domain;
            if (options.secure) cookieString += "; secure";
            if (options.sameSite) cookieString += "; samesite=" + options.sameSite;
            
            document.cookie = cookieString;
        },

        /**
         * 删除 Cookie
         * @param {string} name
         */
        remove: function(name) {
            this.set(name, '', { expires: -1, path: '/' });
        }
    };

    /**
     * 工具函数：检查元素是否有某个类名
     */
    function hasClass(elem, cls) {
        cls = cls || "";
        if (cls.replace(/\s/g, "").length === 0) {
            return false;
        }
        return new RegExp(" " + cls + "").test("" + elem.className + "");
    }

    /**
     * 工具函数：添加类名
     */
    function addClass(elements, cName) {
        if (!hasClass(elements, cName)) {
            elements.className += " " + cName;
        }
    }

    /**
     * 工具函数：移除类名
     */
    function removeClass(elements, cName) {
        if (hasClass(elements, cName)) {
            elements.className = elements.className.replace(new RegExp("(\\s|^)" + cName + "(\\s|$)"), " ");
        }
    }

    /**
     * 工具函数：插入 HTML
     */
    function appendHTML(o, html) {
        let divTemp = document.createElement("div");
        let nodes = null;
        let fragment = document.createDocumentFragment();
        divTemp.innerHTML = html;
        nodes = divTemp.childNodes;
        for (let i = 0, length = nodes.length; i < length; i++) {
            fragment.appendChild(nodes[i].cloneNode(true));
        }
        o.appendChild(fragment);
        nodes = null;
        fragment = null;
    }

    /**
     * AJAX 类
     */
    var Ajax = function () {};

    Ajax.prototype = {
        request: function (method, url, callback, postVars) {
            let xhr = this.createXhrObject()();
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) {
                    return;
                }
                if (xhr.status === 200) {
                    callback.success(xhr.responseText, xhr.responseXML);
                } else {
                    callback.failure(xhr, xhr.status);
                }
            };
            if (method !== "POST" && postVars) {
                url += "?" + this.JSONStringify(postVars);
                postVars = null;
            }
            xhr.open(method, url, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(postVars);
        },
        createXhrObject: function () {
            let methods = [
                function () { return new XMLHttpRequest(); },
                function () { return new ActiveXObject("Msxml2.XMLHTTP"); },
                function () { return new ActiveXObject("Microsoft.XMLHTTP"); }
            ];
            for (let i = 0, len = methods.length; i < len; i++) {
                try {
                    return methods[i];
                } catch (e) {
                    continue;
                }
            }
            throw new Error("ajax created failure");
        },
        JSONStringify: function (obj) {
            return JSON.stringify(obj).replace(/"|{|}/g, "")
                .replace(/b:b/g, "=")
                .replace(/b,b/g, "&");
        }
    };

    /**
     * 验证码主对象
     */
    var tncode = {
        _obj: null,
        _tncode: null,
        _img: null,
        _img_loaded: false,
        _is_draw_bg: false,
        _is_moving: false,
        _block_start_x: 0,
        _block_start_y: 0,
        _doing: false,
        _mark_w: 50,
        _mark_h: 50,
        _mark_offset: 0,
        _img_w: 240,
        _img_h: 150,
        _result: false,
        _err_c: 0,
        _onSuccess: null,
        _onFail: null,
        _options: {},
        _captchaKey: null,

        /**
         * Cookie 名称
         */
        COOKIE_NAME: 'zxf_captcha_key',

        /**
         * 绑定事件
         */
        _bind: function (elm, evType, fn) {
            if (!elm) return;
            if (elm.addEventListener) {
                elm.addEventListener(evType, fn);
                return true;
            } else if (elm.attachEvent) {
                return elm.attachEvent("on" + evType, fn);
            }
        },

        /**
         * 获取验证偏移量（供外部使用）
         * @returns {number}
         */
        getOffset: function() {
            return Math.round(this._mark_offset);
        },

        /**
         * 滑块开始移动
         */
        _block_start_move: function (e) {
            if (tncode._doing || !tncode._img_loaded) {
                return;
            }
            e.preventDefault();
            let theEvent = e || window.event;
            if (theEvent.touches) {
                theEvent = theEvent.touches[0];
            }
            let obj = document.getElementsByClassName("slide_block_text")[0];
            if (obj) obj.style.display = "none";
            tncode._draw_bg();
            tncode._block_start_x = theEvent.clientX;
            tncode._block_start_y = theEvent.clientY;
            tncode._doing = true;
            tncode._is_moving = true;
        },

        /**
         * 滑块移动中
         */
        _block_on_move: function (e) {
            if (!tncode._doing) return true;
            if (!tncode._is_moving) return true;
            e.preventDefault();
            let theEvent = e || window.event;
            if (theEvent.touches) {
                theEvent = theEvent.touches[0];
            }
            tncode._is_moving = true;
            let offset = theEvent.clientX - tncode._block_start_x;
            if (offset < 0) {
                offset = 0;
            }
            let max_off = tncode._img_w - tncode._mark_w;
            if (offset > max_off) {
                offset = max_off;
            }
            let obj = document.getElementsByClassName("slide_block")[0];
            if (obj) obj.style.cssText = "transform: translate(" + offset + "px, 0px)";
            tncode._mark_offset = offset / max_off * (tncode._img_w - tncode._mark_w);
            tncode._draw_bg();
            tncode._draw_mark();
        },

        /**
         * 滑块移动结束
         */
        _block_on_end: function (e) {
            if (!tncode._doing) return true;
            e.preventDefault();
            let theEvent = e || window.event;
            if (theEvent.touches) {
                theEvent = theEvent.touches[0];
            }
            tncode._is_moving = false;
            tncode._send_result();
        },

        /**
         * 发送验证结果
         */
        _send_result: function () {
            let haddle = {
                success: tncode._send_result_success,
                failure: tncode._send_result_failure
            };
            tncode._result = false;
            let re = new Ajax();
            let url = tncode._options.checkUrl;
            var offset = Math.round(tncode._mark_offset);
            
            // 添加时间戳防止缓存
            var timestamp = new Date().getTime();
            if (url.indexOf('?') > -1) {
                url += '&tn_r=' + offset + '&_t=' + timestamp;
            } else {
                url += '?tn_r=' + offset + '&_t=' + timestamp;
            }
            
            re.request("get", url, haddle);
        },

        /**
         * 验证成功回调
         */
        _send_result_success: function (responseText) {
            tncode._doing = false;
            var tnHandleDom = document.querySelector(tncode._options.handleDom);

            // 尝试解析 JSON
            var responseData = responseText;
            try {
                if (typeof responseText === "string" && responseText.indexOf('{') === 0) {
                    responseData = JSON.parse(responseText);
                }
            } catch (err) {
                responseData = responseText;
            }

            // 判断成功（兼容旧版 'ok' 字符串和新的 JSON 格式）
            var isSuccess = (responseText === "ok") || 
                            (responseData && responseData.success === true);

            if (isSuccess) {
                tncode._result = true;
                if (tnHandleDom) {
                    tnHandleDom.innerHTML = "✓ 验证成功";
                    tnHandleDom.style.borderColor = "#24C628";
                    tnHandleDom.style.color = "#24C628";
                }
                tncode._showmsg("✓ 验证成功", 1);
                let hgroup = document.getElementsByClassName("hgroup")[0];
                if (hgroup) hgroup.style.display = "block";
                
                // 延迟隐藏弹窗
                setTimeout(tncode.hide, 1500);
                
                if (tncode._onSuccess) {
                    tncode._onSuccess(responseData);
                }
                if (tnHandleDom && tnHandleDom.classList.contains("tn_ripple")) {
                    tnHandleDom.classList.remove("tn_ripple");
                }
            } else {
                let err_msg = (responseData && responseData.message) ? responseData.message : "验证失败";
                
                let obj = document.getElementById("tncode_div");
                if (obj) {
                    addClass(obj, "dd");
                    setTimeout(function () {
                        removeClass(obj, "dd");
                    }, 200);
                }
                tncode._result = false;
                tncode._showmsg(err_msg);
                tncode._err_c++;
                
                // 延迟刷新
                setTimeout(function() { tncode.refresh(); }, 500);
                
                if (tncode._onFail) {
                    tncode._onFail(responseData);
                }
                if (tnHandleDom && !tnHandleDom.classList.contains("tn_ripple")) {
                    tnHandleDom.classList.add("tn_ripple");
                }
            }
        },

        /**
         * 验证失败回调
         */
        _send_result_failure: function (xhr) {
            tncode._doing = false;
            tncode._result = false;

            let errorRes = xhr.responseText;
            let err_msg = "验证失败";
            try {
                if (typeof (errorRes) == "string") {
                    let json = JSON.parse(errorRes);
                    err_msg = json.message || err_msg;
                }
            } catch (err) {}

            let obj = document.getElementById("tncode_div");
            if (obj) {
                addClass(obj, "dd");
                setTimeout(function () {
                    removeClass(obj, "dd");
                }, 200);
            }
            tncode._showmsg(err_msg);
            var tnHandleDom = document.querySelector(tncode._options.handleDom);
            if (tnHandleDom && !tnHandleDom.classList.contains("tn_ripple")) {
                tnHandleDom.classList.add("tn_ripple");
            }
        },

        /**
         * 绘制完整背景
         */
        _draw_fullbg: function () {
            let canvas_bg = document.getElementsByClassName("tncode_canvas_bg")[0];
            if (!canvas_bg) return;
            let ctx_bg = canvas_bg.getContext("2d");
            ctx_bg.clearRect(0, 0, canvas_bg.width, canvas_bg.height);
            ctx_bg.drawImage(tncode._img, 0, tncode._img_h * 2, tncode._img_w, tncode._img_h, 0, 0, tncode._img_w, tncode._img_h);
        },

        /**
         * 绘制背景
         */
        _draw_bg: function () {
            if (tncode._is_draw_bg) {
                return;
            }
            tncode._is_draw_bg = true;
            let canvas_bg = document.getElementsByClassName("tncode_canvas_bg")[0];
            if (!canvas_bg) return;
            let ctx_bg = canvas_bg.getContext("2d");
            ctx_bg.drawImage(tncode._img, 0, 0, tncode._img_w, tncode._img_h, 0, 0, tncode._img_w, tncode._img_h);
        },

        /**
         * 绘制滑块标记
         */
        _draw_mark: function () {
            var canvas_mark = document.getElementsByClassName("tncode_canvas_mark")[0];
            if (!canvas_mark) return;
            var ctx_mark = canvas_mark.getContext("2d");
            ctx_mark.clearRect(0, 0, canvas_mark.width, canvas_mark.height);
            ctx_mark.drawImage(tncode._img, 0, tncode._img_h, tncode._mark_w, tncode._img_h, tncode._mark_offset, 0, tncode._mark_w, tncode._img_h);
            var imageData = ctx_mark.getImageData(0, 0, tncode._img_w, tncode._img_h);
            var data = imageData.data;
            var x = tncode._img_h, y = tncode._img_w;
            for (let j = 0; j < x; j++) {
                let ii = 1, k1 = -1;
                for (let k = 0; k < y && k >= 0 && k > k1;) {
                    let i = (j * y + k) * 4;
                    k += ii;
                    let r = data[i], g = data[i + 1], b = data[i + 2];
                    if (r + g + b < 200) {
                        data[i + 3] = 0;
                    } else {
                        let arr_pix = [1, -5];
                        let arr_op = [250, 0];
                        for (let i = 1; i < arr_pix[0] - arr_pix[1]; i++) {
                            let iiii = arr_pix[0] - 1 * i;
                            let op = parseInt(arr_op[0] - (arr_op[0] - arr_op[1]) / (arr_pix[0] - arr_pix[1]) * i);
                            let iii = (j * y + k + iiii * ii) * 4;
                            data[iii + 3] = op;
                        }
                        if (ii === -1) {
                            break;
                        }
                        k1 = k;
                        k = y - 1;
                        ii = -1;
                    }
                }
            }
            ctx_mark.putImageData(imageData, 0, 0);
        },

        /**
         * 重置滑块
         */
        _reset: function () {
            tncode._mark_offset = 0;
            tncode._is_draw_bg = false;
            tncode._draw_bg();
            tncode._draw_mark();
            let obj = document.getElementsByClassName("slide_block")[0];
            if (obj) obj.style.cssText = "transform: translate(0px, 0px)";
        },

        /**
         * 显示验证码
         */
        show: function () {
            var tnHandleDom = document.querySelector(tncode._options.handleDom);
            if (tnHandleDom && !tnHandleDom.classList.contains("tn_ripple")) {
                tnHandleDom.classList.add("tn_ripple");
            }

            let obj = document.getElementsByClassName("hgroup")[0];
            if (obj) obj.style.display = "none";
            tncode.refresh();
            tncode._tncode = this;
            let bg = document.getElementById("tncode_div_bg");
            let div = document.getElementById("tncode_div");
            if (bg) bg.style.display = "block";
            if (div) div.style.display = "block";
        },

        /**
         * 隐藏验证码
         */
        hide: function () {
            let bg = document.getElementById("tncode_div_bg");
            let div = document.getElementById("tncode_div");
            if (bg) bg.style.display = "none";
            if (div) div.style.display = "none";
        },

        /**
         * 显示消息
         */
        _showmsg: function (msg, status) {
            let obj;
            if (!status) {
                status = 0;
                obj = document.getElementsByClassName("tncode_msg_error")[0];
            } else {
                obj = document.getElementsByClassName("tncode_msg_ok")[0];
            }
            if (obj) obj.innerHTML = msg;

            function setOpacity(ele, opacity) {
                if (ele.style.opacity !== undefined) {
                    ele.style.opacity = opacity / 100;
                } else {
                    ele.style.filter = "alpha(opacity=" + opacity + ")";
                }
            }

            function fadeout(ele, opacity, speed) {
                if (!ele) return;
                let v = ele.style.filter.replace("alpha(opacity=", "").replace(")", "") || ele.style.opacity || 100;
                v < 1 && (v = v * 100);
                let count = speed / 1000;
                let avg = (100 - opacity) / count;
                let timer = null;
                timer = setInterval(function () {
                    if (v - avg > opacity) {
                        v -= avg;
                        setOpacity(ele, v);
                    } else {
                        setOpacity(ele, 0);
                        if (status === 0) {
                            tncode._reset();
                        }
                        clearInterval(timer);
                    }
                }, 100);
            }

            function fadein(ele, opacity, speed) {
                if (!ele) return;
                let v = ele.style.filter.replace("alpha(opacity=", "").replace(")", "") || ele.style.opacity;
                v < 1 && (v = v * 100);
                let count = speed / 1000;
                let avg = count < 2 ? (opacity / count) : (opacity / count - 1);
                let timer = null;
                timer = setInterval(function () {
                    if (v < opacity) {
                        v += avg;
                        setOpacity(ele, v);
                    } else {
                        clearInterval(timer);
                        setTimeout(function () {
                            fadeout(obj, 0, 6000);
                        }, 1000);
                    }
                }, 100);
            }

            fadein(obj, 80, 4000);
        },

        /**
         * 生成 HTML
         */
        _html: function () {
            let d = document.getElementById("tncode_div_bg");
            if (d) return;
            let html = '<div class="tncode_div_bg" id="tncode_div_bg"></div>' +
                '<div class="tncode_div" id="tncode_div">' +
                '<div class="loading">加载中...</div>' +
                '<canvas class="tncode_canvas_bg" width="240" height="150"></canvas>' +
                '<canvas class="tncode_canvas_mark" width="240" height="150"></canvas>' +
                '<div class="hgroup"></div>' +
                '<div class="tncode_msg_error"></div>' +
                '<div class="tncode_msg_ok"></div>' +
                '<div class="slide">' +
                '<div class="slide_block"></div>' +
                '<div class="slide_block_text">拖动左边滑块完成上方拼图</div>' +
                '</div>' +
                '<div class="tools">' +
                '<div class="tncode_close" title="关闭"></div>' +
                '<div class="tncode_refresh" title="刷新"></div>' +
                '<div class="tncode_tips"></div>' +
                '</div>' +
                '</div>';
            let bo = document.getElementsByTagName("body")[0];
            appendHTML(bo, html);
        },

        /**
         * 刷新验证码
         */
        refresh: function () {
            // 检测 WebP 支持
            let isSupportWebp = false;
            try {
                let canvas = document.createElement("canvas");
                if (canvas.toDataURL("image/webp").indexOf("data:image/webp") === 0) {
                    isSupportWebp = true;
                }
            } catch (e) {
                isSupportWebp = false;
            }
            
            tncode._err_c = 0;
            tncode._is_draw_bg = false;
            tncode._result = false;
            tncode._img_loaded = false;
            
            let obj = document.getElementsByClassName("tncode_canvas_bg")[0];
            if (obj) obj.style.display = "none";
            obj = document.getElementsByClassName("tncode_canvas_mark")[0];
            if (obj) obj.style.display = "none";
            
            tncode._img = new Image();
            tncode._img.crossOrigin = "Anonymous";
            
            let img_url = tncode._options.getImgUrl;
            // 添加随机参数防止缓存
            img_url += (img_url.indexOf('?') > -1 ? '&' : '?') + 't=' + Math.random();
            if (!isSupportWebp) {
                img_url += '&nowebp=1';
            }
            
            tncode._img.src = img_url;
            
            // 加载超时处理
            let loadTimeout = setTimeout(function() {
                if (!tncode._img_loaded) {
                    tncode._showmsg("图片加载超时，请重试");
                }
            }, 10000);
            
            tncode._img.onload = function () {
                clearTimeout(loadTimeout);
                tncode._draw_fullbg();
                let canvas_mark = document.getElementsByClassName("tncode_canvas_mark")[0];
                if (canvas_mark) {
                    let ctx_mark = canvas_mark.getContext("2d");
                    ctx_mark.clearRect(0, 0, canvas_mark.width, canvas_mark.height);
                }
                tncode._img_loaded = true;
                obj = document.getElementsByClassName("tncode_canvas_bg")[0];
                if (obj) obj.style.display = "";
                obj = document.getElementsByClassName("tncode_canvas_mark")[0];
                if (obj) obj.style.display = "";
            };
            
            tncode._img.onerror = function() {
                clearTimeout(loadTimeout);
                tncode._showmsg("图片加载失败，请刷新重试");
            };
            
            let obj1 = document.getElementsByClassName("slide_block")[0];
            if (obj1) obj1.style.cssText = "transform: translate(0px, 0px)";
            obj1 = document.getElementsByClassName("slide_block_text")[0];
            if (obj1) obj1.style.display = "block";
            
            // 重置状态
            tncode._mark_offset = 0;
            tncode._doing = false;
            tncode._is_moving = false;
        },

        /**
         * 初始化
         */
        init: function (options) {
            options = options || {};
            tncode._options = Object.assign({}, {
                handleDom: ".tncode",
                getImgUrl: "/zxf-captcha/image",
                checkUrl: "/zxf-captcha/verify"
            }, options);

            let _this = this;
            if (!tncode._img) {
                tncode._html();
                
                let slideBlock = document.getElementsByClassName("slide_block")[0];
                if (slideBlock) {
                    tncode._bind(slideBlock, "mousedown", _this._block_start_move);
                    tncode._bind(slideBlock, "touchstart", _this._block_start_move);
                }
                
                tncode._bind(document, "mousemove", _this._block_on_move);
                tncode._bind(document, "touchmove", _this._block_on_move);
                tncode._bind(document, "mouseup", _this._block_on_end);
                tncode._bind(document, "touchend", _this._block_on_end);

                let closeBtn = document.getElementsByClassName("tncode_close")[0];
                if (closeBtn) {
                    tncode._bind(closeBtn, "touchstart", _this.hide);
                    tncode._bind(closeBtn, "click", _this.hide);
                }

                let refreshBtn = document.getElementsByClassName("tncode_refresh")[0];
                if (refreshBtn) {
                    tncode._bind(refreshBtn, "touchstart", _this.refresh);
                    tncode._bind(refreshBtn, "click", _this.refresh);
                }

                // 背景点击关闭
                let bgDiv = document.getElementById("tncode_div_bg");
                if (bgDiv) {
                    tncode._bind(bgDiv, "click", _this.hide);
                }

                let objs = document.querySelectorAll(tncode._options.handleDom);
                for (let i = 0; i < objs.length; i++) {
                    let o = objs[i];
                    o.innerHTML = "点击按钮进行验证";
                    tncode._bind(o, "touchstart", function(e) {
                        e.preventDefault();
                        _this.show();
                    });
                    tncode._bind(o, "click", function(e) {
                        e.preventDefault();
                        _this.show();
                    });
                    o.classList.add("tn_ripple");
                }
            }
            return tncode;
        },

        /**
         * 获取结果
         * @returns {boolean}
         */
        result: function () {
            return tncode._result;
        },

        /**
         * 设置成功回调
         * @param {function} fn
         * @returns {tncode}
         */
        onSuccess: function (fn) {
            tncode._onSuccess = fn;
            return tncode;
        },

        /**
         * 设置失败回调
         * @param {function} fn
         * @returns {tncode}
         */
        onFail: function (fn) {
            tncode._onFail = fn;
            return tncode;
        },

        /**
         * 重置验证码状态
         */
        reset: function() {
            tncode._result = false;
            tncode._err_c = 0;
            tncode._mark_offset = 0;
            tncode._reset();
            
            var tnHandleDom = document.querySelector(tncode._options.handleDom);
            if (tnHandleDom) {
                tnHandleDom.innerHTML = "点击按钮进行验证";
                tnHandleDom.style.borderColor = "#ccc";
                tnHandleDom.style.color = "";
                tnHandleDom.classList.add("tn_ripple");
            }
        }
    };

    // 暴露到全局
    window.zxfCaptcha = tncode;
    // 兼容旧版本
    window.$TN = tncode;

})(window, document);
