jQuery(document).ready(function($) {
    
    var isRestaurantOpen = true;
    
    // بررسی وضعیت رستوران
    $.post(ryns_ajax.ajax_url, {
        action: 'ryns_check_restaurant_status',
        nonce: ryns_ajax.nonce
    }, function(response) {
        if (response.success) {
            isRestaurantOpen = response.data.is_open;
            if (isRestaurantOpen) {
                loadMenu();
            } else {
                $('#ryns-menu-content').html(
                    '<div class="ryns-loading" style="padding:40px;">' +
                    '<div style="font-size:64px;margin-bottom:20px;">🔴</div>' +
                    '<h2 style="color:#f44336;">رستوران بسته است</h2>' +
                    '<p style="opacity:0.7;">در حال حاضر امکان سفارش‌گیری وجود ندارد</p>' +
                    '<p style="font-size:13px;opacity:0.5;">' + (response.data.status_text || '') + '</p>' +
                    '</div>'
                );
            }
        } else {
            loadMenu();
        }
    }).fail(function() {
        loadMenu();
    });

    function loadMenu() {
        $.post(ryns_ajax.ajax_url, {
            action: 'ryns_get_categories',
            nonce: ryns_ajax.nonce
        }, function(response) {
            if (response.success && response.data.length > 0) {
                renderCategories(response.data);
                renderMenuContent(response.data);
                updateCartBar();
            } else {
                $('#ryns-menu-content').html('<div class="ryns-loading"><p style="font-size:18px;">🍽️ منوی رستوران خالی است</p><p>هنوز هیچ آیتمی به منو اضافه نشده است.</p></div>');
            }
        });
    }

    function renderCategories(categories) {
        var html = '';
        categories.forEach(function(cat, index) {
            var activeClass = index === 0 ? 'active' : '';
            html += '<button class="ryns-cat-btn ' + activeClass + '" data-target="cat-' + cat.id + '">' + cat.name + '</button>';
        });
        $('.ryns-categories-scroll').html(html);

        $('.ryns-cat-btn').on('click', function() {
            $('.ryns-cat-btn').removeClass('active');
            $(this).addClass('active');
            var target = $(this).data('target');
            $('html, body').animate({
                scrollTop: $('#' + target).offset().top - 70
            }, 400);
        });
    }

    function renderMenuContent(categories) {
        var html = '';
        categories.forEach(function(cat) {
            html += '<div class="ryns-category-section" id="cat-' + cat.id + '">';
            html += '<div class="ryns-section-header">';
            html += '<div class="ryns-cat-icon">' + getCategoryIcon(cat.name) + '</div>';
            html += '<h2>' + cat.name + '</h2>';
            html += '</div>';

            cat.items.forEach(function(item) {
                var priceFormatted = formatPrice(item.price);
                var imageUrl = item.image || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22300%22 viewBox=%220 0 300 300%22%3E%3Crect width=%22300%22 height=%22300%22 fill=%22%2326211f%22/%3E%3Ctext x=%22150%22 y=%22140%22 font-family=%22Tahoma%22 font-size=%2260%22 fill=%22%23ad8b4c%22 text-anchor=%22middle%22%3E%F0%9F%8D%BD%EF%B8%8F%3C/text%3E%3Ctext x=%22150%22 y=%22185%22 font-family=%22Tahoma%22 font-size=%2214%22 fill=%22%23888%22 text-anchor=%22middle%22%3E%D8%A8%D8%AF%D9%88%D9%86%20%D8%AA%D8%B5%D9%88%DB%8C%D8%B1%3C/text%3E%3C/svg%3E';
                var disabledClass = !isRestaurantOpen ? ' ryns-add-btn-disabled' : '';
                var disabledAttr = !isRestaurantOpen ? ' disabled="disabled"' : '';
                
                html += '<div class="ryns-menu-item">';
                html += '<div class="ryns-item-image"><img src="' + imageUrl + '" alt="' + item.name + '" loading="lazy"></div>';
                html += '<div class="ryns-item-info">';
                html += '<h3 class="ryns-item-name">' + item.name + '</h3>';
                if (item.description) {
                    html += '<p class="ryns-item-desc">' + item.description + '</p>';
                }
                html += '<div class="ryns-item-bottom">';
                html += '<span class="ryns-item-price">' + priceFormatted + ' <small>تومان</small></span>';
                if (isRestaurantOpen && item.in_stock) {
                    html += '<a href="' + item.permalink + '?add-to-cart=' + item.id + '" class="ryns-add-btn" data-product-id="' + item.id + '">افزودن +</a>';
                } else if (!isRestaurantOpen) {
                    html += '<span class="ryns-add-btn ryns-add-btn-disabled">رستوران بسته</span>';
                } else {
                    html += '<span class="ryns-add-btn ryns-add-btn-disabled">ناموجود</span>';
                }
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div>';
        });

        $('#ryns-menu-content').html(html);

        $('.ryns-add-btn').on('click', function(e) {
            if ($(this).hasClass('ryns-add-btn-disabled')) {
                e.preventDefault();
                return;
            }
            setTimeout(function() {
                updateCartBar();
            }, 1000);
        });
    }

    function updateCartBar() {
        try {
            var cartCount = 0;
            $('body').trigger('wc_fragment_refresh');

            setTimeout(function() {
                var countEl = $('.cart-contents .count, .cart-total .count');
                if (countEl.length) {
                    cartCount = parseInt(countEl.first().text()) || 0;
                }
                if (cartCount > 0) {
                    $('#ryns-cart-bar').slideDown();
                } else {
                    $('#ryns-cart-bar').slideUp();
                }
            }, 500);
        } catch(e) {}
    }

    function getCategoryIcon(name) {
        var icons = {
            'پیش': '🥗', 'سالاد': '🥗', 'ساندویچ': '🥪', 'برگر': '🍔',
            'پیتزا': '🍕', 'ماکارونی': '🍝', 'سوپ': '🍜', 'نوشیدنی': '🥤',
            'دسر': '🍰', 'صبحانه': '🥞', 'ایرانی': '🍚', 'کباب': '🥩',
            'غذا': '🍛', 'فست': '🌭', 'پیش غذا': '🥗', 'مخصوص': '⭐',
        };
        for (var key in icons) {
            if (name.includes(key)) return icons[key];
        }
        return '🍽️';
    }

    function formatPrice(price) {
        price = parseFloat(price);
        if (isNaN(price)) return '0';
        return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
});