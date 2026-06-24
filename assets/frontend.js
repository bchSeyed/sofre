jQuery(document).ready(function($) {
    
    var isRestaurantOpen = true;
    
    // بررسی وضعیت رستوران
    $.post(bq_ajax.ajax_url, {
        action: 'bq_check_restaurant_status',
        nonce: bq_ajax.nonce
    }, function(response) {
        if (response.success) {
            isRestaurantOpen = response.data.is_open;
            if (isRestaurantOpen) {
                loadMenu();
            } else {
                $('#bq-menu-content').html(
                    '<div class="bq-loading" style="padding:40px;">' +
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
        $.post(bq_ajax.ajax_url, {
            action: 'bq_get_categories',
            nonce: bq_ajax.nonce
        }, function(response) {
            if (response.success && response.data.length > 0) {
                renderCategories(response.data);
                renderMenuContent(response.data);
                updateCartBar();
            } else {
                $('#bq-menu-content').html('<div class="bq-loading"><p style="font-size:18px;">🍽️ منوی رستوران خالی است</p><p>هنوز هیچ آیتمی به منو اضافه نشده است.</p></div>');
            }
        });
    }

    function renderCategories(categories) {
        var html = '';
        categories.forEach(function(cat, index) {
            var activeClass = index === 0 ? 'active' : '';
            html += '<button class="bq-cat-btn ' + activeClass + '" data-target="cat-' + cat.id + '">' + cat.name + '</button>';
        });
        $('.bq-categories-scroll').html(html);

        $('.bq-cat-btn').on('click', function() {
            $('.bq-cat-btn').removeClass('active');
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
            html += '<div class="bq-category-section" id="cat-' + cat.id + '">';
            html += '<div class="bq-section-header">';
            html += '<div class="bq-cat-icon">' + getCategoryIcon(cat.name) + '</div>';
            html += '<h2>' + cat.name + '</h2>';
            html += '</div>';

            cat.items.forEach(function(item) {
                var priceFormatted = formatPrice(item.price);
                var imageUrl = item.image || 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22300%22 height=%22300%22 viewBox=%220 0 300 300%22%3E%3Crect width=%22300%22 height=%22300%22 fill=%22%2326211f%22/%3E%3Ctext x=%22150%22 y=%22140%22 font-family=%22Tahoma%22 font-size=%2260%22 fill=%22%23ad8b4c%22 text-anchor=%22middle%22%3E%F0%9F%8D%BD%EF%B8%8F%3C/text%3E%3Ctext x=%22150%22 y=%22185%22 font-family=%22Tahoma%22 font-size=%2214%22 fill=%22%23888%22 text-anchor=%22middle%22%3E%D8%A8%D8%AF%D9%88%D9%86%20%D8%AA%D8%B5%D9%88%DB%8C%D8%B1%3C/text%3E%3C/svg%3E';
                var disabledClass = !isRestaurantOpen ? ' bq-add-btn-disabled' : '';
                var disabledAttr = !isRestaurantOpen ? ' disabled="disabled"' : '';
                
                html += '<div class="bq-menu-item">';
                html += '<div class="bq-item-image"><img src="' + imageUrl + '" alt="' + item.name + '" loading="lazy"></div>';
                html += '<div class="bq-item-info">';
                html += '<h3 class="bq-item-name">' + item.name + '</h3>';
                if (item.description) {
                    html += '<p class="bq-item-desc">' + item.description + '</p>';
                }
                html += '<div class="bq-item-bottom">';
                html += '<span class="bq-item-price">' + priceFormatted + ' <small>تومان</small></span>';
                if (isRestaurantOpen && item.in_stock) {
                    html += '<a href="' + item.permalink + '?add-to-cart=' + item.id + '" class="bq-add-btn" data-product-id="' + item.id + '">افزودن +</a>';
                } else if (!isRestaurantOpen) {
                    html += '<span class="bq-add-btn bq-add-btn-disabled">رستوران بسته</span>';
                } else {
                    html += '<span class="bq-add-btn bq-add-btn-disabled">ناموجود</span>';
                }
                html += '</div>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div>';
        });

        $('#bq-menu-content').html(html);

        $('.bq-add-btn').on('click', function(e) {
            if ($(this).hasClass('bq-add-btn-disabled')) {
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
                    $('#bq-cart-bar').slideDown();
                } else {
                    $('#bq-cart-bar').slideUp();
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