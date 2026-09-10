<?php
add_action('init', function() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    // Разрешаем локальные домены Vite
    $allowed_origins = [
        'http://localhost:5173', 
        'https://localhost:5173', 
        'http://127.0.0.1:5173'
    ];

    if (in_array($origin, $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, Nonce, Cart-Token, X-WC-Store-API-Nonce, X-WP-Nonce');
        
        // ВАЖНО: Экспонируем заголовки в нижнем регистре, так как Axios/браузер приводит их к lower-case
        header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages, Link, cart-token, Cart-Token, nonce, Nonce, x-wc-store-api-nonce');
    }

    // ОБЯЗАТЕЛЬНО: Отдаем статус 200 и заголовки для preflight (OPTIONS) запросов
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        status_header(200);
        exit;
    }
});

// Отключаем проверку nonce для Store API (это безопасно, если вы используете Cart-Token) [[36]]
add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

// ========================================
// API PROXY: скрываем ключи от браузера
// ========================================

add_action('rest_api_init', function() {
    register_rest_route('headless/v1', '/products', array(
        'methods'  => 'GET',
        'callback' => 'my_headless_products',
        'permission_callback' => '__return_true',
    ));
});

// ========================================
// API PROXY: скрываем ключи от браузера
// ========================================

add_action('rest_api_init', function() {
    // Товары
    register_rest_route('headless/v1', '/products', array(
        'methods'  => 'GET',
        'callback' => 'my_headless_products',
        'permission_callback' => '__return_true',
    ));
    
    // Категории
    register_rest_route('headless/v1', '/products/categories', array(
        'methods'  => 'GET',
        'callback' => 'my_headless_categories',
        'permission_callback' => '__return_true',
    ));
    
    // Теги
    register_rest_route('headless/v1', '/products/tags', array(
        'methods'  => 'GET',
        'callback' => 'my_headless_tags',
        'permission_callback' => '__return_true',
    ));
});

// Универсальная функция для запросов к WooCommerce
function my_headless_wc_request($endpoint, $params = array()) {
    $key = 'ck_ad0327fa26d26a6c5c835d1bacfecf6903284ff3';
    $secret = 'cs_e5a5deb2b05c187d02ea9c684dbd73df1a6e13ad';
    
    $url = get_site_url() . "/wp-json/wc/v3{$endpoint}";
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Basic ' . base64_encode("$key:$secret")
        ),
        'timeout' => 30,
        'sslverify' => false,
    ));
    
    if (is_wp_error($response)) {
        return new WP_Error('api_fail', $response->get_error_message(), array('status' => 500));
    }
    
    return array(
        'data' => json_decode(wp_remote_retrieve_body($response)),
        'headers' => wp_remote_retrieve_headers($response),
    );
}

function my_headless_products($request) {
    $params = $request->get_query_params();
    $result = my_headless_wc_request('/products', $params);
    
    if (is_wp_error($result)) {
        return $result;
    }
    
    $response = rest_ensure_response($result['data']);
    if (isset($result['headers']['x-wp-total'])) {
        $response->header('X-WP-Total', $result['headers']['x-wp-total']);
        $response->header('X-WP-TotalPages', $result['headers']['x-wp-totalpages']);
    }
    return $response;
}

function my_headless_categories($request) {
    $params = $request->get_query_params();
    $result = my_headless_wc_request('/products/categories', $params);
    return is_wp_error($result) ? $result : $result['data'];
}

function my_headless_tags($request) {
    $params = $request->get_query_params();
    $result = my_headless_wc_request('/products/tags', $params);
    return is_wp_error($result) ? $result : $result['data'];
}