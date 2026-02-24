"use strict";function xbGoTo(e,o,n){var e=arguments[0]?arguments[0]:"/",o=arguments[1]?arguments[1]:0,n=arguments[2]?arguments[2]:"";o=1e3*o+1,""!=n&&xbAlert(n),setTimeout(function(){location.href=e},o)}function xbRefresh(e,o){var e=arguments[0]?arguments[0]:0,o=!!arguments[1]&&arguments[1];e=1e3*e+1,setTimeout(function(){o?location.reload(!0):(console.log(o),location.reload(!1))},e)}function xbCheckLogin(){var e=!1;return $.ajaxSetup({async:!1}),$.get(xbCheckLoginUrl,function(o){0==o.error_code&&(e=!0)},"json"),e}function xbNeedLogin(e){xbCheckLogin()?xbGoTo(e):(xbAlert("您需要登录"),xbSetCookie("thisUrl",e),xbShowLogin())}function xbNeedConfirm(e,o){var o=arguments[1]?arguments[1]:"确认操作";confirm(o)&&(location.href=e)}function xbGetForm(e){var o=$(e).serializeArray(),n={};return $.each(o,function(e,o){n[o.name]=o.value}),n}function xbSetCookie(e,o,n){if(n){var t=new Date;t.setTime(t.getTime()+24*n*60*60*1e3);var i="; expires="+t.toGMTString()}else var i="";document.cookie=e+"="+o+i+"; path=/"}function xbGetCookie(e){for(var o=e+"=",n=document.cookie.split(";"),t=0;t<n.length;t++){for(var i=n[t];" "==i.charAt(0);)i=i.substring(1,i.length);if(0==i.indexOf(o))return i.substring(o.length,i.length)}return null}function xbDeleteCookie(e){xbSetCookie(e,"",-1)}

// AJAX Form Handler
$(document).ready(function() {
    // Handle AJAX form submissions
    $(document).on('submit', '.ajax-form', function(e) {
        e.preventDefault();
        var form = $(this);
        var url = form.attr('action');
        var method = form.attr('method') || 'POST';
        var data = form.serialize();

        $.ajax({
            url: url,
            type: method,
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.status == 1) {
                    // Success
                    if (typeof layer !== 'undefined') {
                        layer.msg(response.msg || '操作成功', {icon: 1, time: 1500}, function() {
                            if (response.url) {
                                window.location.href = response.url;
                            }
                        });
                    } else {
                        alert(response.msg || '操作成功');
                        if (response.url) {
                            window.location.href = response.url;
                        }
                    }
                } else {
                    // Error
                    if (typeof layer !== 'undefined') {
                        layer.msg(response.msg || '操作失败', {icon: 2, time: 2000});
                    } else {
                        alert(response.msg || '操作失败');
                    }
                }
            },
            error: function(xhr, status, error) {
                if (typeof layer !== 'undefined') {
                    layer.msg('请求失败，请重试', {icon: 2, time: 2000});
                } else {
                    alert('请求失败，请重试');
                }
            }
        });
    });

    // Handle AJAX delete links
    $(document).on('click', '.ajax-del', function(e) {
        e.preventDefault();
        var link = $(this);
        var url = link.attr('href');
        var confirmMsg = link.data('confirm') || '确定要删除吗？';

        if (typeof layer !== 'undefined') {
            layer.confirm(confirmMsg, {icon: 3, title: '提示'}, function(index) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        layer.close(index);
                        if (response.status == 1) {
                            layer.msg(response.msg || '删除成功', {icon: 1, time: 1500}, function() {
                                if (response.url) {
                                    window.location.href = response.url;
                                } else {
                                    window.location.reload();
                                }
                            });
                        } else {
                            layer.msg(response.msg || '删除失败', {icon: 2, time: 2000});
                        }
                    },
                    error: function() {
                        layer.close(index);
                        layer.msg('请求失败，请重试', {icon: 2, time: 2000});
                    }
                });
            });
        } else {
            if (confirm(confirmMsg)) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status == 1) {
                            alert(response.msg || '删除成功');
                            if (response.url) {
                                window.location.href = response.url;
                            } else {
                                window.location.reload();
                            }
                        } else {
                            alert(response.msg || '删除失败');
                        }
                    },
                    error: function() {
                        alert('请求失败，请重试');
                    }
                });
            }
        }
    });

    // Handle AJAX action links (for other operations like reset, toggle, etc.)
    $(document).on('click', '.ajax-action', function(e) {
        e.preventDefault();
        var link = $(this);
        var url = link.attr('href');
        var confirmMsg = link.data('confirm');

        var doAction = function() {
            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status == 1) {
                        if (typeof layer !== 'undefined') {
                            layer.msg(response.msg || '操作成功', {icon: 1, time: 1500}, function() {
                                if (response.url) {
                                    window.location.href = response.url;
                                } else {
                                    window.location.reload();
                                }
                            });
                        } else {
                            alert(response.msg || '操作成功');
                            if (response.url) {
                                window.location.href = response.url;
                            } else {
                                window.location.reload();
                            }
                        }
                    } else {
                        if (typeof layer !== 'undefined') {
                            layer.msg(response.msg || '操作失败', {icon: 2, time: 2000});
                        } else {
                            alert(response.msg || '操作失败');
                        }
                    }
                },
                error: function() {
                    if (typeof layer !== 'undefined') {
                        layer.msg('请求失败，请重试', {icon: 2, time: 2000});
                    } else {
                        alert('请求失败，请重试');
                    }
                }
            });
        };

        if (confirmMsg) {
            if (typeof layer !== 'undefined') {
                layer.confirm(confirmMsg, {icon: 3, title: '提示'}, function(index) {
                    layer.close(index);
                    doAction();
                });
            } else {
                if (confirm(confirmMsg)) {
                    doAction();
                }
            }
        } else {
            doAction();
        }
    });
});