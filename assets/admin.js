jQuery(document).ready(function($) {
    
    var lastOrderId = 0;
    var pollingInterval = null;

    // ============ 1. رستوران باز/بسته کردن ============
    
    $('#ryns-toggle-restaurant').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true);
        
        $.post(ryns_ajax.ajax_url, {
            action: 'ryns_toggle_restaurant',
            nonce: ryns_ajax.nonce
        }, function(response) {
            if (response.success) {
                var isOpen = response.data.status === 'yes';
                btn.removeClass('open closed').addClass(isOpen ? 'open' : 'closed');
                btn.find('.ryns-toggle-text').text(isOpen ? 'باز' : 'بسته');
                btn.data('current', isOpen ? 'open' : 'closed');

                var banner = $('#ryns-status-banner');
                if (banner.length) {
                    banner.removeClass('ryns-status-open ryns-status-closed')
                          .addClass(isOpen ? 'ryns-status-open' : 'ryns-status-closed');
                    banner.find('.ryns-status-icon').html(isOpen ? '🟢' : '🔴');
                    banner.find('.ryns-status-msg').text(
                        isOpen ? 'رستوران باز است و سفارش پذیرفته می‌شود' : 'رستوران بسته است - سفارشات پذیرفته نمی‌شوند'
                    );
                }

                location.reload();
            }
            btn.prop('disabled', false);
        });
    });

    // ============ 2. نظارت لایو سفارشات (Polling) ============
    
    function startOrderPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
        }

        var firstRow = $('.ryns-order-row').first();
        if (firstRow.length) {
            lastOrderId = parseInt(firstRow.data('order-id')) || 0;
        }

        pollingInterval = setInterval(function() {
            checkNewOrders();
        }, 15000);
    }

    function checkNewOrders() {
        $.post(ryns_ajax.ajax_url, {
            action: 'ryns_check_new_orders',
            last_order_id: lastOrderId,
            nonce: ryns_ajax.nonce
        }, function(response) {
            if (response.success && response.data.new_count > 0) {
                playNotificationSound();
                
                var badge = $('#ryns-live-badge');
                if (badge.length) {
                    badge.text('🔔 ' + response.data.new_count + ' سفارش جدید').show();
                    setTimeout(function() { badge.fadeOut(); }, 5000);
                }

                location.reload();
                lastOrderId = response.data.last_order_id;
            }
        });
    }

    // ============ 3. صدای اعلان سفارش جدید ============
    
    function playNotificationSound() {
        try {
            if (window.AudioContext || window.webkitAudioContext) {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.value = 800;
                gain.gain.value = 0.3;
                osc.start();
                setTimeout(function() { osc.stop(); }, 200);
            }
        } catch(e) {
            console.log('sound not supported');
        }
    }

    // ============ 4. تغییر وضعیت سفارش ============
    
    $('.ryns-order-status').on('change', function() {
        var select = $(this);
        var orderId = select.data('order-id');
        var status = select.val();
        
        if (!status) return;
        
        select.prop('disabled', true);
        
        $.post(ryns_ajax.ajax_url, {
            action: 'ryns_update_order_status',
            order_id: orderId,
            status: status,
            nonce: ryns_ajax.nonce
        }, function(response) {
            if (response.success) {
                setTimeout(function() { location.reload(); }, 500);
            } else {
                alert(response.data.message || 'خطا در بروزرسانی وضعیت');
                select.prop('disabled', false);
            }
        }).fail(function() {
            alert('خطا در ارتباط با سرور');
            select.prop('disabled', false);
        });
    });

    // ============ 5. مودال جزئیات سفارش ============
    
    $(document).on('click', '.ryns-view-order', function() {
        var orderId = $(this).data('order-id');
        showOrderDetail(orderId);
    });

    function showOrderDetail(orderId) {
        var modal = $('#ryns-order-modal');
        modal.show();
        $('#ryns-modal-order-number').text('#' + orderId);
        $('#ryns-modal-body').html(
            '<div class="ryns-loading" style="text-align:center;padding:40px;">' +
            '<div class="ryns-spinner"></div><p>در حال بارگذاری...</p></div>'
        );

        $.post(ryns_ajax.ajax_url, {
            action: 'ryns_get_order_detail',
            order_id: orderId,
            nonce: ryns_ajax.nonce
        }, function(response) {
            if (response.success) {
                renderOrderDetail(response.data);
            } else {
                $('#ryns-modal-body').html('<p style="color:red;text-align:center;padding:40px;">خطا در دریافت اطلاعات</p>');
            }
        });
    }

    function renderOrderDetail(data) {
        var html = '';
        
        html += '<div class="ryns-order-section">';
        html += '<h3>👤 اطلاعات مشتری</h3>';
        html += '<table class="ryns-detail-table">';
        html += '<tr><th>نام:</th><td>' + data.customer + '</td></tr>';
        html += '<tr><th>تلفن:</th><td>' + data.phone + '</td></tr>';
        html += '<tr><th>ایمیل:</th><td>' + data.email + '</td></tr>';
        html += '<tr><th>آدرس:</th><td>' + data.address + '</td></tr>';
        html += '</table></div>';

        html += '<div class="ryns-order-section">';
        html += '<h3>🛒 آیتم‌های سفارش (' + data.items_count + ')</h3>';
        html += '<table class="ryns-detail-table">';
        html += '<thead><tr><th>محصول</th><th>تعداد</th><th>قیمت</th></tr></thead>';
        html += '<tbody>' + data.items_html + '</tbody>';
        html += '</table></div>';

        html += '<div class="ryns-order-section">';
        html += '<h3>💰 مبالغ</h3>';
        html += '<table class="ryns-detail-table">';
        html += '<tr><th>جمع جزء:</th><td>' + data.subtotal + '</td></tr>';
        html += '<tr><th>هزینه ارسال:</th><td>' + data.shipping_total + '</td></tr>';
        html += '<tr><th><strong>جمع کل:</strong></th><td><strong>' + data.total + '</strong></td></tr>';
        html += '</table></div>';

        html += '<div class="ryns-order-section">';
        html += '<h3>📋 اطلاعات تکمیلی</h3>';
        html += '<table class="ryns-detail-table">';
        html += '<tr><th>تاریخ:</th><td>' + data.date + '</td></tr>';
        html += '<tr><th>روش پرداخت:</th><td>' + data.payment_method + '</td></tr>';
        html += '<tr><th>وضعیت:</th><td>' + data.status + '</td></tr>';
        if (data.notes) {
            html += '<tr><th>یادداشت:</th><td>' + data.notes + '</td></tr>';
        }
        html += '</table></div>';

        html += '<div style="text-align:center;margin-top:20px;">';
        html += '<a href="' + ryns_ajax.ajax_url.replace('admin-ajax.php', 'post.php?post=' + data.id + '&action=edit') + '" class="button button-primary" target="_blank">مشاهده در ووکامرس</a>';
        html += '</div>';

        $('#ryns-modal-body').html(html);
    }

    $(document).on('click', '.ryns-modal-close, .ryns-modal-overlay', function() {
        $('#ryns-order-modal').hide();
    });

    // ============ 6. آپلود لوگو ============
    
    var rynsFileFrame = null;

    $('#ryns-upload-logo').on('click', function(e) {
        e.preventDefault();

        if (rynsFileFrame) {
            rynsFileFrame.open();
            return;
        }

        rynsFileFrame = wp.media({
            title: 'انتخاب لوگوی رستوران',
            button: { text: 'انتخاب لوگو' },
            multiple: false,
            library: { type: 'image' }
        });

        rynsFileFrame.on('select', function() {
            var attachment = rynsFileFrame.state().get('selection').first().toJSON();
            $('#ryns_restaurant_logo').val(attachment.url);
            $('#ryns-logo-preview').html('<img src="' + attachment.url + '" style="max-width:150px;max-height:80px;">');
            $('#ryns-remove-logo').show();
        });

        rynsFileFrame.open();
    });

    $('#ryns-remove-logo').on('click', function() {
        $('#ryns_restaurant_logo').val('');
        $('#ryns-logo-preview').html('<span class="ryns-logo-placeholder">لوگو انتخاب نشده</span>');
        $(this).hide();
    });

    // ============ 7. شروع Polling ============
    
    if ($('.ryns-dashboard').length || $('.ryns-orders-wrap').length) {
        setTimeout(function() { startOrderPolling(); }, 2000);
    }
});