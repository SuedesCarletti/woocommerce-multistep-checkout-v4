<?php
/**
 * MSC CODLOCAL Functions (SEM DEBUG VISUAL)
 * Funções para múltiplos CODLOCAL com cache e performance superior
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cache Manager para CODLOCAL - Sistema rápido
 */
class MSC_CODLOCAL_Cache {
    
    private static $memory_cache = array();
    private static $cache_timeout = 3600; // 1 hora para dados de produto
    private static $cart_cache_timeout = 300; // 5 min para dados de carrinho
    
    /**
     * Get com cache duplo (memory + transient)
     */
    public static function get($key, $timeout = null) {
        $timeout = $timeout ?: self::$cache_timeout;
        
        // Memory cache primeiro (mais rápido)
        if (isset(self::$memory_cache[$key])) {
            $data = self::$memory_cache[$key];
            if (time() < $data['expires']) {
                return $data['value'];
            }
            unset(self::$memory_cache[$key]);
        }
        
        // Transient cache
        $cached = get_transient("msc_codlocal_{$key}");
        if ($cached !== false) {
            self::$memory_cache[$key] = array(
                'value' => $cached,
                'expires' => time() + $timeout
            );
            return $cached;
        }
        
        return false;
    }
    
    /**
     * Set com cache duplo
     */
    public static function set($key, $value, $timeout = null) {
        $timeout = $timeout ?: self::$cache_timeout;
        
        // Memory cache
        self::$memory_cache[$key] = array(
            'value' => $value,
            'expires' => time() + $timeout
        );
        
        // Transient cache
        set_transient("msc_codlocal_{$key}", $value, $timeout);
    }
    
    /**
     * Clear cache por padrão
     */
    public static function clear($pattern = '') {
        if ($pattern) {
            // Limpar por padrão
            foreach (self::$memory_cache as $key => $data) {
                if (strpos($key, $pattern) !== false) {
                    unset(self::$memory_cache[$key]);
                    delete_transient("msc_codlocal_{$key}");
                }
            }
        } else {
            // Limpar tudo
            self::$memory_cache = array();
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_msc_codlocal_%' OR option_name LIKE '_transient_timeout_msc_codlocal_%'");
        }
    }
}

/**
 * VERIFICAR se carrinho tem múltiplos CODLOCAL - SUPER OTIMIZADO
 */
function msc_cart_has_multiple_codlocal() {
    if ( ! function_exists('WC') || ! WC()->cart ) {
        return false;
    }
    
    // Cache baseado no hash do carrinho
    $cart_items = array_keys(WC()->cart->get_cart());
    $cart_hash = md5(serialize($cart_items));
    $cache_key = "has_multiple_{$cart_hash}";
    
    $cached = MSC_CODLOCAL_Cache::get($cache_key, 300);
    if ($cached !== false) {
        return $cached;
    }
    
    $codlocals = array();
    
    // Verificação com early exit para performance
    foreach ($cart_items as $cart_item_key) {
        $cart_item = WC()->cart->get_cart()[$cart_item_key];
        $product_id = $cart_item['product_id'];
        $codlocal = msc_get_product_codlocal_cached($product_id);
        
        if (!in_array($codlocal, $codlocals)) {
            $codlocals[] = $codlocal;
            
            // Early exit - se já tem mais de 1, não precisa continuar
            if (count($codlocals) > 1) {
                MSC_CODLOCAL_Cache::set($cache_key, true, 300);
                return true;
            }
        }
    }
    
    $has_multiple = count($codlocals) > 1;
    MSC_CODLOCAL_Cache::set($cache_key, $has_multiple, 300);
    
    return $has_multiple;
}

/**
 * GET PRODUCT CODLOCAL - Cache ultra agressivo
 */
function msc_get_product_codlocal_cached($product_id) {
    $cache_key = "product_codlocal_{$product_id}";
    
    $cached = MSC_CODLOCAL_Cache::get($cache_key, 3600); // 1 hora
    if ($cached !== false) {
        return $cached;
    }
    
    $codlocal = 'default';
    
    // Só chamar Sankhya se função existir
    if (function_exists('woo_importador_get_codlocal')) {
        $sku = get_post_meta($product_id, '_sku', true);
        if ($sku) {
            try {
                $sankhya_codlocal = woo_importador_get_codlocal($sku);
                if ($sankhya_codlocal && $sankhya_codlocal !== 'default') {
                    $codlocal = $sankhya_codlocal;
                }
            } catch (Exception $e) {
                // Log erro mas não quebrar
                error_log('[MSC CODLOCAL] Erro ao buscar CODLOCAL para SKU ' . $sku . ': ' . $e->getMessage());
            }
        }
    }
    
    // Cache longo pois CODLOCAL não muda frequentemente
    MSC_CODLOCAL_Cache::set($cache_key, $codlocal, 3600);
    
    return $codlocal;
}

/**
 * AGRUPAR carrinho por CODLOCAL - Otimizado com cache
 */
function msc_group_cart_by_codlocal() {
    if ( ! function_exists('WC') || ! WC()->cart ) {
        return array();
    }
    
    $cart_items = WC()->cart->get_cart();
    $cart_hash = md5(serialize(array_keys($cart_items)));
    $cache_key = "cart_groups_{$cart_hash}";
    
    $cached = MSC_CODLOCAL_Cache::get($cache_key, 300);
    if ($cached !== false) {
        return $cached;
    }
    
    $groups = array();
    
    foreach ($cart_items as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $product = $cart_item['data'];
        $codlocal = msc_get_product_codlocal_cached($product_id);
        
        // Nome do local otimizado
        if (!isset($groups[$codlocal])) {
            $location_name = $codlocal === 'default' ? 'Principal' : "Local {$codlocal}";
            
            $groups[$codlocal] = array(
                'codlocal' => $codlocal,
                'name' => $location_name,
                'items' => array(),
                'item_count' => 0,
                'total_value' => 0.0
            );
        }
        
        // Adicionar item
        $line_total = (float) $cart_item['line_total'];
        
        $groups[$codlocal]['items'][] = array(
            'key' => $cart_item_key,
            'cart_item' => $cart_item,
            'data' => $product,
            'product_id' => $product_id,
            'quantity' => $cart_item['quantity']
        );
        
        $groups[$codlocal]['item_count'] += $cart_item['quantity'];
        $groups[$codlocal]['total_value'] += $line_total;
    }
    
    MSC_CODLOCAL_Cache::set($cache_key, $groups, 300);
    
    return $groups;
}

/**
 * GET AVAILABLE STOCK - Otimizado com cache pesado
 */
function msc_get_available_stock($product_id) {
    $cache_key = "stock_{$product_id}";
    
    // Cache de estoque por menos tempo (5min) pois muda mais
    $cached = MSC_CODLOCAL_Cache::get($cache_key, 300);
    if ($cached !== false) {
        return $cached;
    }
    
    $available_stock = 0;
    
    $sku = get_post_meta($product_id, '_sku', true);
    
    if ($sku && function_exists('woo_importador_get_locais_estoque')) {
        try {
            $dados = woo_importador_get_locais_estoque($sku);
            
            if ($dados && isset($dados['locais']) && is_array($dados['locais'])) {
                // Calcular estoque disponível por local
                foreach ($dados['locais'] as $local) {
                    $estoque_local = (int) ($local['ESTOQUE'] ?? 0);
                    $reservado_local = (int) ($local['RESERVADO'] ?? 0);
                    $available_stock += max(0, $estoque_local - $reservado_local);
                }
            } elseif (isset($dados['estoque_total'])) {
                $available_stock = max(0, (int) $dados['estoque_total']);
            }
            
        } catch (Exception $e) {
            error_log('[MSC CODLOCAL] Erro ao buscar estoque para SKU ' . $sku . ': ' . $e->getMessage());
            
            // Fallback para estoque WooCommerce
            $product = wc_get_product($product_id);
            $available_stock = $product ? (int) $product->get_stock_quantity() : 0;
        }
    } else {
        // Fallback para estoque WooCommerce
        $product = wc_get_product($product_id);
        $available_stock = $product ? (int) $product->get_stock_quantity() : 0;
    }
    
    $available_stock = max(0, $available_stock); // Nunca negativo
    
    // Cache por 5 minutos
    MSC_CODLOCAL_Cache::set($cache_key, $available_stock, 300);
    
    return $available_stock;
}

/**
 * FORMAT STOCK DISPLAY - Otimizado e consistente
 */
function msc_format_stock_display($product_id, $current_qty = 1) {
    $available = msc_get_available_stock($product_id);
    
    if ($available <= 0) {
        return '<span class="msc-stock-warning">Indisponível</span>';
    }
    
    if ($current_qty > $available) {
        return '<span class="msc-stock-error">Qtd. indisponível (máx: ' . $available . ')</span>';
    }
    
    if ($available <= 3) {
        return '<span class="msc-stock-critical">Últimas ' . $available . ' unidades</span>';
    } elseif ($available <= 10) {
        return '<span class="msc-stock-low">Restam ' . $available . '</span>';
    } elseif ($available >= 50) {
        return '<span class="msc-stock-good">Disponível</span>';
    } else {
        return '<span class="msc-stock-normal">' . $available . ' disponível</span>';
    }
}

/**
 * GET SHIPPING BY CODLOCAL - Para integração com frete
 */
function msc_get_shipping_by_codlocal() {
    $groups = msc_group_cart_by_codlocal();
    $shipping_groups = array();
    
    if (empty($groups)) {
        return $shipping_groups;
    }
    
    foreach ($groups as $codlocal => $group) {
        $shipping_groups[$codlocal] = array(
            'codlocal' => $codlocal,
            'name' => $group['name'],
            'item_count' => $group['item_count'],
            'total_value' => $group['total_value'],
            'shipping_cost' => 0, // Será calculado pelo sistema de frete
            'methods' => array()
        );
    }
    
    return $shipping_groups;
}

/**
 * VALIDATE STOCK ON ADD - Validação otimizada
 */
function msc_validate_stock_on_add($passed, $product_id, $quantity, $variation_id = null, $variations = null) {
    $available = msc_get_available_stock($product_id);
    
    if ($available <= 0) {
        wc_add_notice(__('Produto indisponível no momento.', 'multistep-checkout'), 'error');
        return false;
    }
    
    if ($quantity > $available) {
        wc_add_notice(
            sprintf(__('Apenas %d unidades disponíveis.', 'multistep-checkout'), $available), 
            'error'
        );
        return false;
    }
    
    return $passed;
}

/**
 * Obter nome amigável do CODLOCAL
 */
function msc_get_codlocal_display_name($codlocal) {
    $names = array(
        '201' => 'Curitiba',
        '401' => 'Espírito Santo',
        'default' => 'Principal'
    );
    
    return isset($names[$codlocal]) ? $names[$codlocal] : "Local {$codlocal}";
}

/**
 * CLEAR CACHE ON CART UPDATE - Limpar cache quando necessário
 */
function msc_clear_codlocal_cache_on_update() {
    MSC_CODLOCAL_Cache::clear('cart_');
    MSC_CODLOCAL_Cache::clear('has_multiple_');
}

/**
 * DEBUG FUNCTIONS - REMOVIDAS DO FRONTEND
 * 
 * Para debug, use o console do navegador (F12) e os logs do servidor.
 * Logs aparecem em: wp-content/debug.log (se WP_DEBUG_LOG estiver ativo)
 * 
 * Exemplo de uso:
 * error_log('[MSC DEBUG] ' . print_r($data, true));
 */

// Funções de debug comentadas - não aparecem mais visualmente na tela
/*
function msc_debug_cart_groups() {
    // Debug desativado no frontend
    return;
}

function msc_debug_cache_stats() {
    // Debug desativado no frontend
    return;
}
*/

/**
 * HOOKS DE INTEGRAÇÃO
 */
add_action('init', function() {
    // Hooks apenas se Sankhya estiver disponível
    if (function_exists('woo_importador_get_codlocal')) {
        
        // Validação de estoque
        add_filter('woocommerce_add_to_cart_validation', 'msc_validate_stock_on_add', 10, 5);
        
        // Limpar cache quando carrinho atualizar
        add_action('woocommerce_cart_updated', 'msc_clear_codlocal_cache_on_update');
        add_action('woocommerce_checkout_update_order_review', 'msc_clear_codlocal_cache_on_update');
        
        // Limpar cache quando produtos forem atualizados
        add_action('woocommerce_update_product', function($product_id) {
            MSC_CODLOCAL_Cache::clear("product_codlocal_{$product_id}");
            MSC_CODLOCAL_Cache::clear("stock_{$product_id}");
        });
    }
});

/**
 * AJAX Endpoint para debug (desenvolvimento - apenas dados, sem HTML)
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_ajax_msc_debug_codlocal', function() {
        check_ajax_referer('msc_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Não autorizado'), 403);
        }
        
        $action = sanitize_text_field($_POST['debug_action'] ?? 'stats');
        
        switch ($action) {
            case 'clear_cache':
                MSC_CODLOCAL_Cache::clear();
                wp_send_json_success(array('message' => 'Cache limpo'));
                break;
                
            case 'stats':
                $stats = array(
                    'memory_cache_count' => count(MSC_CODLOCAL_Cache::$memory_cache),
                    'has_multiple' => msc_cart_has_multiple_codlocal(),
                    'groups_count' => count(msc_group_cart_by_codlocal()),
                );
                wp_send_json_success($stats);
                break;
                
            default:
                wp_send_json_error(array('message' => 'Ação inválida'));
        }
    });
    
    add_action('wp_ajax_nopriv_msc_debug_codlocal', function() {
        wp_send_json_error(array('message' => 'Não autorizado'));
    });
}

/**
 * CLEANUP na desativação do plugin
 */
register_deactivation_hook(MSC_PLUGIN_FILE, function() {
    MSC_CODLOCAL_Cache::clear();
});

/**
 * WP-CLI Commands (se disponível) - Para debug via linha de comando
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('msc codlocal-cache', function($args, $assoc_args) {
        $action = $args[0] ?? 'stats';
        
        switch ($action) {
            case 'clear':
                MSC_CODLOCAL_Cache::clear();
                WP_CLI::success('Cache CODLOCAL limpo');
                break;
                
            case 'stats':
                $memory_count = count(MSC_CODLOCAL_Cache::$memory_cache);
                global $wpdb;
                $transient_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_msc_codlocal_%'");
                
                WP_CLI::line("Memory cache: {$memory_count} entries");
                WP_CLI::line("Transient cache: {$transient_count} entries");
                WP_CLI::line("Has multiple CODLOCAL: " . (msc_cart_has_multiple_codlocal() ? 'Yes' : 'No'));
                break;
                
            default:
                WP_CLI::error('Ação inválida. Use: clear ou stats');
        }
    });
}
