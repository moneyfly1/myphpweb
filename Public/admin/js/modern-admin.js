/**
 * 现代化后台管理系统 JavaScript 增强功能
 * 提供移动端优化和用户体验增强
 */

(function($) {
    'use strict';
    
    // 全局配置
    var ModernAdmin = {
        // 移动端断点
        mobileBreakpoint: 768,
        
        // 初始化
        init: function() {
            this.initMobileMenu();
            this.initSidebarCollapse();
            this.initTableResponsive();
            this.initFormEnhancements();
            this.initTooltips();
            this.initConfirmDialogs();
            this.initLoadingStates();
            this.initScrollToTop();
            this.initThemeToggle();
            this.initPageAnimations();
            this.initCardEffects();
            this.initSmoothScrolling();
            this.initProgressBars();
        },
        
        // 移动端菜单处理
        initMobileMenu: function() {
            var self = this;
            
            // 创建遮罩层
            if (!$('.sidebar-overlay').length) {
                $('body').append('<div class="sidebar-overlay"></div>');
            }
            
            // 菜单切换
            $(document).on('click', '#menu-toggler', function(e) {
                e.preventDefault();
                self.toggleMobileMenu();
            });
            
            // 点击遮罩层关闭菜单
            $(document).on('click', '.sidebar-overlay', function() {
                self.closeMobileMenu();
            });
            
            // 窗口大小改变时处理
            $(window).on('resize', function() {
                if ($(window).width() > self.mobileBreakpoint) {
                    self.closeMobileMenu();
                }
            });
            
            // ESC键关闭菜单
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) { // ESC
                    self.closeMobileMenu();
                }
            });
        },
        
        // 切换移动端菜单
        toggleMobileMenu: function() {
            $('#sidebar').toggleClass('mobile-show');
            $('.sidebar-overlay').toggle();
            $('body').toggleClass('sidebar-open');
        },
        
        // 关闭移动端菜单
        closeMobileMenu: function() {
            $('#sidebar').removeClass('mobile-show');
            $('.sidebar-overlay').hide();
            $('body').removeClass('sidebar-open');
        },
        
        // 侧边栏折叠
        initSidebarCollapse: function() {
            $(document).on('click', '#sidebar-collapse', function(e) {
                e.preventDefault();
                $('#sidebar').toggleClass('menu-min');
                
                // 保存状态到localStorage
                var isCollapsed = $('#sidebar').hasClass('menu-min');
                localStorage.setItem('sidebar-collapsed', isCollapsed);
            });
            
            // 恢复侧边栏状态
            var isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
            if (isCollapsed) {
                $('#sidebar').addClass('menu-min');
            }
        },
        
        // 表格响应式处理
        initTableResponsive: function() {
            // 为所有表格添加响应式包装
            $('.table').each(function() {
                if (!$(this).parent().hasClass('table-responsive')) {
                    $(this).wrap('<div class="table-responsive"></div>');
                }
            });
            
            // 移动端表格优化
            if ($(window).width() <= this.mobileBreakpoint) {
                this.optimizeTablesForMobile();
            }
        },
        
        // 移动端表格优化
        optimizeTablesForMobile: function() {
            $('.table').each(function() {
                var $table = $(this);
                var headers = [];
                
                // 获取表头
                $table.find('thead th').each(function() {
                    headers.push($(this).text());
                });
                
                // 为每个单元格添加标签
                $table.find('tbody tr').each(function() {
                    $(this).find('td').each(function(index) {
                        var headerText = headers[index];
                        if (headerText && !$(this).attr('data-label')) {
                            $(this).attr('data-label', headerText);
                        }
                    });
                });
            });
        },
        
        // 表单增强
        initFormEnhancements: function() {
            // 搜索表单美化
            $('form').each(function() {
                if ($(this).find('input[name="search"]').length > 0) {
                    $(this).addClass('search-form');
                }
            });
            
            // 输入框焦点效果
            $('input, textarea, select').on('focus', function() {
                $(this).closest('.form-group').addClass('focused');
            }).on('blur', function() {
                $(this).closest('.form-group').removeClass('focused');
            });
            
            // 文件上传美化
            $('input[type="file"]').each(function() {
                var $input = $(this);
                var $wrapper = $('<div class="file-upload-wrapper"></div>');
                var $button = $('<button type="button" class="btn btn-primary"><i class="fa fa-upload"></i> 选择文件</button>');
                var $filename = $('<span class="filename">未选择文件</span>');
                
                $input.wrap($wrapper);
                $input.after($button).after($filename);
                
                $button.on('click', function() {
                    $input.click();
                });
                
                $input.on('change', function() {
                    var filename = this.files[0] ? this.files[0].name : '未选择文件';
                    $filename.text(filename);
                });
            });
        },
        
        // 工具提示
        initTooltips: function() {
            // 为带有title属性的元素添加工具提示
            $('[title]').each(function() {
                var $this = $(this);
                var title = $this.attr('title');
                
                if (title && !$this.hasClass('no-tooltip')) {
                    $this.removeAttr('title');
                    $this.attr('data-tooltip', title);
                    
                    $this.on('mouseenter', function(e) {
                        var tooltip = $('<div class="custom-tooltip">' + title + '</div>');
                        $('body').append(tooltip);
                        
                        var offset = $this.offset();
                        var tooltipWidth = tooltip.outerWidth();
                        var tooltipHeight = tooltip.outerHeight();
                        var windowWidth = $(window).width();
                        var windowHeight = $(window).height();
                        var scrollTop = $(window).scrollTop();
                        
                        var left = offset.left + ($this.outerWidth() - tooltipWidth) / 2;
                        var top = offset.top - tooltipHeight - 10;
                        
                        // 边界检查
                        if (left < 10) left = 10;
                        if (left + tooltipWidth > windowWidth - 10) left = windowWidth - tooltipWidth - 10;
                        if (top < scrollTop + 10) top = offset.top + $this.outerHeight() + 10;
                        
                        tooltip.css({ top: top, left: left });
                        tooltip.addClass('tooltip-show');
                    }).on('mouseleave', function() {
                        $('.custom-tooltip').removeClass('tooltip-show').fadeOut(200, function() {
                            $(this).remove();
                        });
                    });
                }
            });
        },
        
        // 页面动画效果
        initPageAnimations: function() {
            // 页面加载动画
            $(window).on('load', function() {
                $('.main-content').addClass('page-loaded');
            });
            
            // 元素进入视口动画
            this.initScrollAnimations();
        },
        
        // 滚动动画
        initScrollAnimations: function() {
            var self = this;
            
            function checkScroll() {
                $('.card, .table-responsive, .alert').each(function() {
                    var $element = $(this);
                    var elementTop = $element.offset().top;
                    var elementBottom = elementTop + $element.outerHeight();
                    var viewportTop = $(window).scrollTop();
                    var viewportBottom = viewportTop + $(window).height();
                    
                    if (elementBottom > viewportTop && elementTop < viewportBottom) {
                        $element.addClass('animate-in');
                    }
                });
            }
            
            // 简单的节流函数
             var throttleTimer = null;
             $(window).on('scroll', function() {
                 if (throttleTimer) return;
                 throttleTimer = setTimeout(function() {
                     checkScroll();
                     throttleTimer = null;
                 }, 100);
             });
             checkScroll(); // 初始检查
        },
        
        // 卡片效果
        initCardEffects: function() {
            // 卡片悬停效果
            $('.card').on('mouseenter', function() {
                $(this).addClass('card-hover');
            }).on('mouseleave', function() {
                $(this).removeClass('card-hover');
            });
            
            // 统计卡片数字动画
            $('.stat-number').each(function() {
                var $this = $(this);
                var finalValue = parseInt($this.text().replace(/[^0-9]/g, ''));
                
                if (finalValue > 0) {
                    $this.text('0');
                    $({ value: 0 }).animate({ value: finalValue }, {
                         duration: 2000,
                         step: function() {
                             $this.text(Math.floor(this.value).toLocaleString());
                         },
                         complete: function() {
                             $this.text(finalValue.toLocaleString());
                         }
                     });
                }
            });
        },
        
        // 平滑滚动
        initSmoothScrolling: function() {
            // 锚点平滑滚动
            $('a[href^="#"]').on('click', function(e) {
                var target = $(this.getAttribute('href'));
                if (target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                         scrollTop: target.offset().top - 80
                     }, 800);
                }
            });
        },
        
        // 进度条动画
        initProgressBars: function() {
            $('.progress-bar').each(function() {
                var $bar = $(this);
                var width = $bar.data('width') || $bar.attr('style').match(/width:\s*(\d+)%/);
                
                if (width) {
                    var targetWidth = typeof width === 'string' ? width[1] + '%' : width + '%';
                    $bar.css('width', '0%');
                    
                    setTimeout(function() {
                         $bar.animate({ width: targetWidth }, 1500);
                     }, 500);
                }
            });
        },
        
        // 确认对话框
        initConfirmDialogs: function() {
            $(document).on('click', 'a[onclick*="confirm"], button[onclick*="confirm"]', function(e) {
                e.preventDefault();
                
                var $this = $(this);
                var href = $this.attr('href');
                var onclick = $this.attr('onclick');
                
                // 提取确认消息
                var message = '确定要执行此操作吗？';
                if (onclick) {
                    var match = onclick.match(/confirm\(['"](.*?)['"]\)/);
                    if (match) {
                        message = match[1];
                    }
                }
                
                // 创建现代化确认对话框
                var modal = $(`
                    <div class="modern-confirm-modal">
                        <div class="modern-confirm-content">
                            <div class="modern-confirm-header">
                                <i class="fa fa-question-circle"></i>
                                <h4>确认操作</h4>
                            </div>
                            <div class="modern-confirm-body">
                                <p>${message}</p>
                            </div>
                            <div class="modern-confirm-footer">
                                <button class="btn btn-secondary cancel-btn">取消</button>
                                <button class="btn btn-danger confirm-btn">确定</button>
                            </div>
                        </div>
                    </div>
                `);
                
                $('body').append(modal);
                modal.fadeIn(200);
                
                // 确认按钮
                modal.find('.confirm-btn').on('click', function() {
                    if (href) {
                        window.location.href = href;
                    } else if (onclick) {
                        // 移除confirm调用并执行
                        var cleanOnclick = onclick.replace(/return\s+confirm\([^)]+\);?/g, '');
                        eval(cleanOnclick);
                    }
                    modal.fadeOut(200, function() {
                        $(this).remove();
                    });
                });
                
                // 取消按钮
                modal.find('.cancel-btn').on('click', function() {
                    modal.fadeOut(200, function() {
                        $(this).remove();
                    });
                });
                
                // 点击背景关闭
                modal.on('click', function(e) {
                    if (e.target === this) {
                        modal.fadeOut(200, function() {
                            $(this).remove();
                        });
                    }
                });
            });
        },
        
        // 加载状态
        initLoadingStates: function() {
            // 表单提交加载状态
            $('form').on('submit', function() {
                var $form = $(this);
                var $submitBtn = $form.find('input[type="submit"], button[type="submit"]');
                
                if ($submitBtn.length) {
                    var originalText = $submitBtn.val() || $submitBtn.text();
                    $submitBtn.prop('disabled', true);
                    
                    if ($submitBtn.is('input')) {
                        $submitBtn.val('处理中...');
                    } else {
                        $submitBtn.html('<span class="loading"></span> 处理中...');
                    }
                    
                    // 5秒后恢复按钮状态（防止卡死）
                    setTimeout(function() {
                        $submitBtn.prop('disabled', false);
                        if ($submitBtn.is('input')) {
                            $submitBtn.val(originalText);
                        } else {
                            $submitBtn.text(originalText);
                        }
                    }, 5000);
                }
            });
            
            // 链接点击加载状态
            $('a[href*="/Admin/"]').on('click', function() {
                var $this = $(this);
                if (!$this.hasClass('no-loading') && !$this.attr('onclick')) {
                    $this.addClass('loading-link');
                    $this.append(' <span class="loading"></span>');
                }
            });
        },
        
        // 回到顶部
        initScrollToTop: function() {
            // 创建回到顶部按钮
            var $scrollTop = $('<div class="scroll-to-top"><i class="fa fa-arrow-up"></i></div>');
            $('body').append($scrollTop);
            
            // 滚动监听
            $(window).on('scroll', function() {
                if ($(this).scrollTop() > 300) {
                    $scrollTop.addClass('show');
                } else {
                    $scrollTop.removeClass('show');
                }
            });
            
            // 点击回到顶部
            $scrollTop.on('click', function() {
                $('html, body').animate({ scrollTop: 0 }, 500);
            });
        },
        
        // 主题切换
        initThemeToggle: function() {
            // 检测系统主题偏好
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                $('body').addClass('dark-theme');
            }
            
            // 监听系统主题变化
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                    if (e.matches) {
                        $('body').addClass('dark-theme');
                    } else {
                        $('body').removeClass('dark-theme');
                    }
                });
            }
        },
        
        // 显示通知
        showNotification: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 3000;
            
            var notification = $(`
                <div class="modern-notification modern-notification-${type}">
                    <i class="fa fa-${this.getNotificationIcon(type)}"></i>
                    <span>${message}</span>
                    <button class="close-notification"><i class="fa fa-times"></i></button>
                </div>
            `);
            
            $('body').append(notification);
            
            // 显示动画
            setTimeout(function() {
                notification.addClass('show');
            }, 100);
            
            // 自动隐藏
            setTimeout(function() {
                notification.removeClass('show');
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, duration);
            
            // 手动关闭
            notification.find('.close-notification').on('click', function() {
                notification.removeClass('show');
                setTimeout(function() {
                    notification.remove();
                }, 300);
            });
        },
        
        // 获取通知图标
        getNotificationIcon: function(type) {
            var icons = {
                'success': 'check-circle',
                'error': 'exclamation-circle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            return icons[type] || 'info-circle';
        }
    };
    
    // 页面加载完成后初始化
    $(document).ready(function() {
        ModernAdmin.init();
    });
    
    // 暴露到全局
    window.ModernAdmin = ModernAdmin;
    
})(jQuery);

// 添加必要的CSS样式
var additionalCSS = `
<style>
/* 自定义工具提示 */
.custom-tooltip {
    position: absolute;
    background: #333;
    color: white;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 12px;
    z-index: 9999;
    white-space: nowrap;
    display: none;
}

.custom-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border: 5px solid transparent;
    border-top-color: #333;
}

/* 现代化确认对话框 */
.modern-confirm-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}

.modern-confirm-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    max-width: 400px;
    width: 90%;
    overflow: hidden;
}

.modern-confirm-header {
    padding: 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modern-confirm-header i {
    color: #f59e0b;
    font-size: 24px;
}

.modern-confirm-header h4 {
    margin: 0;
    font-weight: 600;
}

.modern-confirm-body {
    padding: 20px;
}

.modern-confirm-footer {
    padding: 20px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* 文件上传美化 */
.file-upload-wrapper {
    position: relative;
    display: inline-block;
}

.file-upload-wrapper input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.file-upload-wrapper .filename {
    margin-left: 12px;
    color: #64748b;
    font-style: italic;
}

/* 加载链接 */
.loading-link {
    pointer-events: none;
    opacity: 0.7;
}

/* 回到顶部按钮 */
.scroll-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    transform: translateY(100px);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 1000;
}

.scroll-to-top.show {
    transform: translateY(0);
    opacity: 1;
}

.scroll-to-top:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

/* 现代化通知 */
.modern-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 9999;
    transform: translateX(400px);
    opacity: 0;
    transition: all 0.3s ease;
    max-width: 350px;
}

.modern-notification.show {
    transform: translateX(0);
    opacity: 1;
}

.modern-notification-success {
    border-left: 4px solid #10b981;
}

.modern-notification-error {
    border-left: 4px solid #ef4444;
}

.modern-notification-warning {
    border-left: 4px solid #f59e0b;
}

.modern-notification-info {
    border-left: 4px solid #06b6d4;
}

.modern-notification i {
    font-size: 18px;
}

.modern-notification-success i {
    color: #10b981;
}

.modern-notification-error i {
    color: #ef4444;
}

.modern-notification-warning i {
    color: #f59e0b;
}

.modern-notification-info i {
    color: #06b6d4;
}

.close-notification {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 4px;
    margin-left: auto;
}

.close-notification:hover {
    color: #1e293b;
}

/* 移动端表格优化 */
@media (max-width: 768px) {
    .table-responsive .table {
        border: none;
    }
    
    .table-responsive .table thead {
        display: none;
    }
    
    .table-responsive .table tbody tr {
        display: block;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin-bottom: 12px;
        padding: 12px;
        background: white;
    }
    
    .table-responsive .table tbody td {
        display: block;
        border: none;
        padding: 8px 0;
        text-align: left;
    }
    
    .table-responsive .table tbody td:before {
        content: attr(data-label) ': ';
        font-weight: 600;
        color: #374151;
        display: inline-block;
        width: 100px;
    }
}

/* 侧边栏移动端遮罩 */
@media (max-width: 768px) {
    body.sidebar-open {
        overflow: hidden;
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1010;
        display: none;
    }
}
</style>
`;

// 将CSS添加到页面
$(document).ready(function() {
    $('head').append(additionalCSS);
});