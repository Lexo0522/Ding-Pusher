jQuery(document).ready(function($) {
    'use strict';
    
    // 测试消息
    $('#dtpwp-test-message').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        button.text('发送中...').prop('disabled', true);
        
        $.ajax({
            url: dtpwp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'dtpwp_test_message',
                nonce: dtpwp_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('测试消息发送失败，请检查网络连接。');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // 添加关键词
    $('.dtpwp-add-keyword').on('click', function() {
        var container = $('#dtpwp-keyword-list');
        var newItem = $('<div class="keyword-item">');
        newItem.html('<input type="text" name="dtpwp_dingtalk_settings[security_keyword][]" value="" class="regular-text" /> <button type="button" class="button button-link-delete dtpwp-remove-keyword">删除</button>');
        container.append(newItem);
        
        // 绑定删除事件
        newItem.find('.dtpwp-remove-keyword').on('click', function() {
            $(this).parent().remove();
        });
    });
    
    // 删除关键词
    $(document).on('click', '.dtpwp-remove-keyword', function() {
        $(this).parent().remove();
    });
    
    // 添加IP
    $('.dtpwp-add-ip').on('click', function() {
        var container = $('#dtpwp-ip-list');
        var newItem = $('<div class="ip-item">');
        newItem.html('<input type="text" name="dtpwp_dingtalk_settings[security_ip_whitelist][]" value="" class="regular-text" /> <button type="button" class="button button-link-delete dtpwp-remove-ip">删除</button>');
        container.append(newItem);
        
        // 绑定删除事件
        newItem.find('.dtpwp-remove-ip').on('click', function() {
            $(this).parent().remove();
        });
    });
    
    // 删除IP
    $(document).on('click', '.dtpwp-remove-ip', function() {
        $(this).parent().remove();
    });
    
    // 取消标记已推送
    $(document).on('click', '.dtpwp-mark-as-not-sent', function() {
        var button = $(this);
        var postId = button.data('post-id');
        
        if (confirm('确定要取消标记这篇文章吗？')) {
            button.text('处理中...').prop('disabled', true);
            
            $.ajax({
                url: dtpwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dtpwp_mark_as_sent',
                    nonce: dtpwp_ajax.nonce,
                    post_id: postId,
                    mark_as: 'not_sent'
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut('slow', function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('操作失败，请重试。');
                },
                complete: function() {
                    button.text('取消标记').prop('disabled', false);
                }
            });
        }
    });
    
    // 清理所有记录
    $('#dtpwp-clear-records').on('click', function() {
        if (confirm('确定要清理所有推送记录吗？此操作不可恢复。')) {
            var button = $(this);
            var originalText = button.text();
            
            button.text('清理中...').prop('disabled', true);
            
            $.ajax({
                url: dtpwp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dtpwp_clear_sent_records',
                    nonce: dtpwp_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('清理失败，请重试。');
                },
                complete: function() {
                    button.text(originalText).prop('disabled', false);
                }
            });
        }
    });
    
    // 安全类型切换
    $('#dtpwp-security-type').on('change', function() {
        var securityType = $(this).val();
        
        // 显示/隐藏相关设置
        $('#dtpwp-security-keyword, #dtpwp-security-secret, #dtpwp-security-ip-whitelist').closest('tr').hide();
        
        if (securityType === 'keyword') {
            $('#dtpwp-security-keyword').closest('tr').show();
        } else if (securityType === 'secret') {
            $('#dtpwp-security-secret').closest('tr').show();
        } else if (securityType === 'ip_whitelist') {
            $('#dtpwp-security-ip-whitelist').closest('tr').show();
        }
    }).trigger('change');
    
    // 消息类型切换，自动适配默认模板
    var messageTypeSelect = $('select[name="dtpwp_dingtalk_settings[message_type]"]');
    var postTemplateTextarea = $('textarea[name="dtpwp_dingtalk_settings[post_template]"]');
    var userTemplateTextarea = $('textarea[name="dtpwp_dingtalk_settings[user_template]"]');
    
    // 默认模板配置
    var defaultTemplates = {
        text: {
            post: '【新文章】\n标题：{title}\n作者：{author}\n链接：{link}\n分类：{category}\n发布时间：{date}',
            user: '【新用户注册】\n用户名：{username}\n邮箱：{email}\n注册时间：{register_time}'
        },
        link: {
            post: '【新文章】\n标题：{title}\n作者：{author}\n链接：{link}',
            user: '【新用户注册】\n用户名：{username}\n邮箱：{email}\n注册时间：{register_time}'
        },
        markdown: {
            post: '# 新文章发布\n\n## {title}\n\n**作者：** {author}\n**分类：** {category}\n**发布时间：** {date}\n\n[点击查看完整文章]({link})',
            user: '# 新用户注册\n\n## 基本信息\n\n**用户名：** {username}\n**邮箱：** {email}\n**注册时间：** {register_time}'
        }
    };
    
    // 保存初始模板，用于判断用户是否修改过
    var initialPostTemplate = postTemplateTextarea.val();
    var initialUserTemplate = userTemplateTextarea.val();
    
    // 消息类型切换事件
    messageTypeSelect.on('change', function() {
        var messageType = $(this).val();
        
        // 检查用户是否修改过模板
        var isPostTemplateModified = (postTemplateTextarea.val() !== initialPostTemplate);
        var isUserTemplateModified = (userTemplateTextarea.val() !== initialUserTemplate);
        
        // 如果用户没有修改过模板，或者模板为空，则使用默认模板
        if (!isPostTemplateModified || postTemplateTextarea.val().trim() === '') {
            postTemplateTextarea.val(defaultTemplates[messageType].post);
            initialPostTemplate = defaultTemplates[messageType].post;
        }
        
        if (!isUserTemplateModified || userTemplateTextarea.val().trim() === '') {
            userTemplateTextarea.val(defaultTemplates[messageType].user);
            initialUserTemplate = defaultTemplates[messageType].user;
        }
    });
    
    // 初始加载时根据当前消息类型设置默认模板
    messageTypeSelect.trigger('change');
});
