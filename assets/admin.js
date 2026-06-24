jQuery(document).ready(function($) {
    
    var lastOrderId = 0;
    var pollingInterval = null;

    // ============ 1. رستوران باز/بسته کردن ============
    
    $('#bq-toggle-restaurant').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true);
        
        $.post(bq_ajax.ajax_url, {
            action: 'bq_toggle_restaurant',
            nonce: bq_ajax.nonce
        }, function(response) {
            if (response.success) {
                var isOpen = response.data.status === 'yes';
                btn.removeClass('open closed').addClass(isOpen ? 'open' : 'closed');
                btn.find('.bq-toggle-text').text(isOpen ? 'باز' : 'بسته');
                btn.data('current', isOpen ? 'open' : 'closed');

                var banner = $('#bq-status-banner');
                if (banner.length) {
                    banner.removeClass('bq-status-open bq-status-closed')
                          .addClass(isOpen ? 'bq-status-open' : 'bq-status-closed');
                    banner.find('.bq-status-icon').html(isOpen ? '🟢' : '🔴');
                    banner.find('.bq-status-msg').text(
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

        var firstRow = $('.bq-order-row').first();
        if (firstRow.length) {
            lastOrderId = parseInt(firstRow.data('order-id')) || 0;
        }

        pollingInterval = setInterval(function() {
            checkNewOrders();
        }, 15000);
    }

    function checkNewOrders() {
        $.post(bq_ajax.ajax_url, {
            action: 'bq_check_new_orders',
            last_order_id: lastOrderId,
            nonce: bq_ajax.nonce
        }, function(response) {
            if (response.success && response.data.new_count > 0) {
                playNotificationSound();
                
                var badge = $('#bq-live-badge');
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
    
    $('.bq-order-status').on('change', function() {
        var select = $(this);
        var orderId = select.data('order-id');
        var status = select.val();
        
        if (!status) return;
        
        select.prop('disabled', true);
        
        $.post(bq_ajax.ajax_url, {
            action: 'bq_update_order_status',
            order_id: orderId,
            status: status,
            nonce: bq_ajax.nonce
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
    
    $(document).on('click', '.bq-view-order', function() {
        var orderId = $(this).data('order-id');
        showOrderDetail(orderId);
    });

    function showOrderDetail(orderId) {
        var modal = $('#bq-order-modal');
        modal.show();
        $('#bq-modal-order-number').text('#' + orderId);
        $('#bq-modal-body').html(
            '<div class="bq-loading" style="text-align:center;padding:40px;">' +
            '<div class="bq-spinner"></div><p>در حال بارگذاری...</p></div>'
        );

        $.post(bq_ajax.ajax_url, {
            action: 'bq_get_order_detail',
            order_id: orderId,
            nonce: bq_ajax.nonce
        }, function(response) {
            if (response.success) {
                renderOrderDetail(response.data);
            } else {
                $('#bq-modal-body').html('<p style="color:red;text-align:center;padding:40px;">خطا در دریافت اطلاعات</p>');
            }
        });
    }

    function renderOrderDetail(data) {
        var html = '';
        
        html += '<div class="bq-order-section">';
        html += '<h3>👤 اطلاعات مشتری</h3>';
        html += '<table class="bq-detail-table">';
        html += '<tr><th>نام:</th><td>' + data.customer + '</td></tr>';
        html += '<tr><th>تلفن:</th><td>' + data.phone + '</td></tr>';
        html += '<tr><th>ایمیل:</th><td>' + data.email + '</td></tr>';
        html += '<tr><th>آدرس:</th><td>' + data.address + '</td></tr>';
        html += '</table></div>';

        html += '<div class="bq-order-section">';
        html += '<h3>🛒 آیتم‌های سفارش (' + data.items_count + ')</h3>';
        html += '<table class="bq-detail-table">';
        html += '<thead><tr><th>محصول</th><th>تعداد</th><th>قیمت</th></tr></thead>';
        html += '<tbody>' + data.items_html + '</tbody>';
        html += '</table></div>';

        html += '<div class="bq-order-section">';
        html += '<h3>💰 مبالغ</h3>';
        html += '<table class="bq-detail-table">';
        html += '<tr><th>جمع جزء:</th><td>' + data.subtotal + '</td></tr>';
        html += '<tr><th>هزینه ارسال:</th><td>' + data.shipping_total + '</td></tr>';
        html += '<tr><th><strong>جمع کل:</strong></th><td><strong>' + data.total + '</strong></td></tr>';
        html += '</table></div>';

        html += '<div class="bq-order-section">';
        html += '<h3>📋 اطلاعات تکمیلی</h3>';
        html += '<table class="bq-detail-table">';
        html += '<tr><th>تاریخ:</th><td>' + data.date + '</td></tr>';
        html += '<tr><th>روش پرداخت:</th><td>' + data.payment_method + '</td></tr>';
        html += '<tr><th>وضعیت:</th><td>' + data.status + '</td></tr>';
        if (data.notes) {
            html += '<tr><th>یادداشت:</th><td>' + data.notes + '</td></tr>';
        }
        html += '</table></div>';

        html += '<div style="text-align:center;margin-top:20px;">';
        html += '<a href="' + bq_ajax.ajax_url.replace('admin-ajax.php', 'post.php?post=' + data.id + '&action=edit') + '" class="button button-primary" target="_blank">مشاهده در ووکامرس</a>';
        html += '</div>';

        $('#bq-modal-body').html(html);
    }

    $(document).on('click', '.bq-modal-close, .bq-modal-overlay', function() {
        $('#bq-order-modal').hide();
    });

    // ============ 6. آپلود لوگو ============
    
    var bqFileFrame = null;

    $('#bq-upload-logo').on('click', function(e) {
        e.preventDefault();

        if (bqFileFrame) {
            bqFileFrame.open();
            return;
        }

        bqFileFrame = wp.media({
            title: 'انتخاب لوگوی رستوران',
            button: { text: 'انتخاب لوگو' },
            multiple: false,
            library: { type: 'image' }
        });

        bqFileFrame.on('select', function() {
            var attachment = bqFileFrame.state().get('selection').first().toJSON();
            $('#bq_restaurant_logo').val(attachment.url);
            $('#bq-logo-preview').html('<img src="' + attachment.url + '" style="max-width:150px;max-height:80px;">');
            $('#bq-remove-logo').show();
        });

        bqFileFrame.open();
    });

    $('#bq-remove-logo').on('click', function() {
        $('#bq_restaurant_logo').val('');
        $('#bq-logo-preview').html('<span class="bq-logo-placeholder">لوگو انتخاب نشده</span>');
        $(this).hide();
    });

    // ============ 7. شروع Polling ============
    
    if ($('.bq-dashboard').length || $('.bq-orders-wrap').length) {
        setTimeout(function() { startOrderPolling(); }, 2000);
    }
});