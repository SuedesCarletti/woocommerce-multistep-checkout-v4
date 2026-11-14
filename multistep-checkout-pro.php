<?php
/**
 * Plugin Name: Multistep Checkout Pro v4
 * Plugin URI: https://github.com/SuedesCarletti/woocommerce-multistep-checkout-v4
 * GitHub Plugin URI: SuedesCarletti/woocommerce-multistep-checkout-v4
 * Description: Checkout multistep OTIMIZADO - Funciona nativamente com WooCommerce
 * Version: 4.1.2
 * Author: Suedes Carletti
 * License: Todos os direitos reservados / All rights reserved
 * Text Domain: multistep-checkout
 */

if (!defined('ABSPATH')) exit;

// Verificar WooCommerce
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Multistep Checkout Pro:</strong> WooCommerce é necessário.</p></div>';
    });
    return;
}

// Constantes
// Suba este valor a cada mudança visual relevante em assets/css|js — é o
// que faz o navegador/CDN buscar uma cópia nova do CSS/JS em vez de
// continuar servindo a versão em cache (ficou parado em 4.0.2 durante
// todo o redesign visual, o que pode ter deixado telas com a
// marcação PHP nova mas o CSS antigo em cache).
define('MSC_VERSION', '4.2.4');
define('MSC_PLUGIN_FILE', __FILE__);
define('MSC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MSC_PLUGIN_URL', plugin_dir_url(__FILE__));

class MultistepCheckoutPro_V4 {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once MSC_PLUGIN_PATH . 'includes/msc-codlocal-functions.php';
        require_once MSC_PLUGIN_PATH . 'includes/class-msc-shipping-packages-handler.php';
        require_once MSC_PLUGIN_PATH . 'includes/class-msc-ajax-handler-v4.php';
        require_once MSC_PLUGIN_PATH . 'includes/msc-fields-helper.php';
    }
    
    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('wc_get_template', array($this, 'override_checkout_template'), 10, 5);
        add_action('wp_enqueue_scripts', array($this, 'force_wc_checkout_script'), 999);
    }
    
    public function force_wc_checkout_script() {
        if (!is_checkout() || is_wc_endpoint_url('order-received')) {
            return;
        }
        wp_enqueue_script('wc-checkout');
        wp_enqueue_script('wc-cart-fragments');
        wp_enqueue_script('woocommerce');
    }
    
    public function enqueue_assets() {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_style(
            'msc-inter-font',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
            array(),
            null
        );

        wp_enqueue_style(
            'msc-checkout-v4',
            MSC_PLUGIN_URL . 'assets/css/multistep-checkout-v4.css',
            array('msc-inter-font'),
            MSC_VERSION
        );

        if (is_wc_endpoint_url('order-received')) {
            // Página de confirmação (Step 5): só o CSS acima é necessário
            // para os tokens/estilos do msc-thankyou.php. O JS do
            // multistep e o wc-checkout forçado (force_wc_checkout_script)
            // continuam restritos ao formulário de checkout em si.
            return;
        }

        wp_enqueue_script(
            'msc-checkout-v4',
            MSC_PLUGIN_URL . 'assets/js/multistep-checkout-v4.js',
            array('jquery', 'wc-checkout'),
            MSC_VERSION,
            true
        );

        wp_enqueue_script(
            'pagarme-step4-sync',
            MSC_PLUGIN_URL . 'assets/js/payment-step4-sync-fix.js',
            array('jquery', 'msc-checkout-v4'),
            MSC_VERSION,
            true
        );
        
        wp_localize_script('msc-checkout-v4', 'msc_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('msc_nonce'),
            'i18n' => array(
                'loading' => __('Carregando...', 'multistep-checkout'),
                'error' => __('Erro ao processar', 'multistep-checkout'),
                'select_shipping' => __('Selecione uma forma de envio', 'multistep-checkout'),
                'fill_address' => __('Preencha o endereço de entrega', 'multistep-checkout'),
            ),
        ));
    }

    public function override_checkout_template($template, $template_name, $args, $template_path, $default_path) {
        if ($template_name === 'checkout/form-checkout.php') {
            $msc_template = MSC_PLUGIN_PATH . 'templates/checkout/multistep-form-v4.php';
            if (file_exists($msc_template)) {
                return $msc_template;
            }
        }
        if ($template_name === 'checkout/thankyou.php') {
            $msc_template = MSC_PLUGIN_PATH . 'templates/checkout/msc-thankyou.php';
            if (file_exists($msc_template)) {
                return $msc_template;
            }
        }
        return $template;
    }
}

add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        MultistepCheckoutPro_V4::get_instance();
    }
}, 20);

add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    $product_id = $values['product_id'];
    $codlocal = get_post_meta($product_id, '_codlocal', true);
    
    if ($codlocal) {
        $item->add_meta_data('_codlocal', $codlocal, true);
        $item->add_meta_data('_codlocal_name', msc_get_codlocal_name($codlocal), true);
    }
}, 10, 4);

add_action('woocommerce_checkout_create_order', function($order, $data) {
    if (function_exists('msc_cart_has_multiple_codlocal') && msc_cart_has_multiple_codlocal()) {
        $groups = msc_group_cart_by_codlocal();
        
        $order->add_meta_data('_msc_multiple_codlocal', 'yes', true);
        $order->add_meta_data('_msc_codlocal_groups', $groups, true);
        
        $summary = array();
        foreach ($groups as $codlocal => $group) {
            $summary[] = sprintf(
                '%s: %d %s (R$ %.2f)',
                $group['name'],
                $group['item_count'],
                _n('item', 'itens', $group['item_count'], 'multistep-checkout'),
                $group['total_value']
            );
        }
        $order->add_meta_data('_msc_codlocal_summary', implode(' | ', $summary), true);
    }
}, 10, 2);

add_action('woocommerce_checkout_create_order', function($order, $data) {
    $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
    if (!empty($chosen_methods)) {
        $order->add_meta_data('_msc_shipping_methods', $chosen_methods, true);
    }
}, 10, 2);

/**
 * Na confirmação do pedido (e em "Meus pedidos"/e-mails) o cliente deve ver
 * apenas "Entrega Econômica" ou "Entrega Expressa", nunca o nome da
 * transportadora. O nome real é preservado no meta `_msc_carrier_title`
 * do item de frete (visível para o admin).
 */
add_action('woocommerce_checkout_create_order_shipping_item', function($item, $package_key, $package, $order) {
    if (!class_exists('MSC_Shipping_Packages_Handler') || empty($package['rates'])) {
        return;
    }

    $rate_id = $item->get_method_id() . ':' . $item->get_instance_id();
    $display = MSC_Shipping_Packages_Handler::instance()->get_display_methods($package['rates']);

    if (isset($display[$rate_id])) {
        $item->add_meta_data('_msc_carrier_title', $item->get_method_title(), true);
        $item->set_method_title($display[$rate_id]['name']);
    }
}, 10, 4);

add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    if ($order->get_meta('_msc_multiple_codlocal') === 'yes') {
        echo '<div class="order_data_column">';
        echo '<h3>' . __('Múltiplos Estoques', 'multistep-checkout') . '</h3>';
        
        $summary = $order->get_meta('_msc_codlocal_summary');
        if ($summary) {
            echo '<p><strong>Resumo:</strong><br>' . esc_html($summary) . '</p>';
        }
        
        $groups = $order->get_meta('_msc_codlocal_groups');
        if ($groups) {
            echo '<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">';
            echo '<thead><tr><th>Local</th><th>Itens</th><th>Valor</th></tr></thead><tbody>';
            foreach ($groups as $codlocal => $group) {
                echo '<tr>';
                echo '<td>' . esc_html($group['name']) . ' (' . esc_html($codlocal) . ')</td>';
                echo '<td>' . intval($group['item_count']) . '</td>';
                echo '<td>R$ ' . number_format($group['total_value'], 2, ',', '.') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}, 10, 1);

add_action('woocommerce_before_calculate_totals', 'msc_auto_select_shipping_method', 10);
function msc_auto_select_shipping_method($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!is_checkout()) return;
    
    WC()->cart->calculate_shipping();
    $packages = WC()->shipping()->get_packages();
    $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
    
    foreach ($packages as $package_key => $package) {
        if (!isset($chosen_methods[$package_key]) || empty($chosen_methods[$package_key])) {
            $available_methods = $package['rates'];
            if (!empty($available_methods)) {
                $first_method = reset($available_methods);
                $chosen_methods[$package_key] = $first_method->get_id();
            }
        }
    }
    
    WC()->session->set('chosen_shipping_methods', $chosen_methods);
}

add_filter('woocommerce_shipping_chosen_method', 'msc_ensure_chosen_method', 10, 3);
function msc_ensure_chosen_method($default, $available_methods, $chosen_method) {
    if (empty($chosen_method) && !empty($available_methods)) {
        $first_method = reset($available_methods);
        return $first_method->get_id();
    }
    return $chosen_method;
}

add_filter('woocommerce_ship_to_different_address_checked', '__return_false');

add_action('wp_footer', 'msc_force_shipping_selection_step2', 999);
function msc_force_shipping_selection_step2() {
    if (!is_checkout()) return;
    ?>
    <script>
    jQuery(document).ready(function($){
        function forceShippingSelection() {
            $('input[name^="shipping_method"]').each(function(){
                var name = $(this).attr('name');
                var $group = $('input[name="' + name + '"]');
                if ($group.filter(':checked').length === 0) {
                    var $first = $group.first();
                    $first.prop('checked', true).attr('checked', 'checked');
                    $first.closest('li').addClass('is-selected');
                    $first.trigger('change');
                }
            });
        }
        
        $(document).on('click', '.msc-step[data-step="2"] .woocommerce-shipping-methods li', function(e){
            if ($(e.target).is('input')) return;
            var $radio = $(this).find('input[type="radio"]');
            if ($radio.length) {
                var name = $radio.attr('name');
                $('input[name="' + name + '"]').prop('checked', false).removeAttr('checked');
                $('input[name="' + name + '"]').closest('li').removeClass('is-selected');
                $radio.prop('checked', true).attr('checked', 'checked');
                $(this).addClass('is-selected');
                $radio.trigger('change');
            }
        });
        
        $(document.body).on('msc:enterStep:2', function(){
            setTimeout(forceShippingSelection, 300);
        });
        
        $(document.body).on('updated_checkout', function(){
            if ($('.msc-step[data-step="2"]').hasClass('active')) {
                setTimeout(forceShippingSelection, 200);
            }
        });
        
        setTimeout(function(){
            if ($('.msc-step[data-step="2"]').hasClass('active')) {
                forceShippingSelection();
            }
        }, 500);
        
        setInterval(function(){
            if ($('.msc-step[data-step="2"]').hasClass('active')) {
                $('input[name^="shipping_method"]').each(function(){
                    var $group = $('input[name="' + this.name + '"]');
                    if ($group.filter(':checked').length === 0) {
                        forceShippingSelection();
                    }
                });
            }
        }, 2000);
    });
    </script>
    <?php
}

add_action('wp_footer', 'msc_debug_shipping_session', 1000);
function msc_debug_shipping_session() {
    if (!is_checkout() || !defined('WP_DEBUG') || !WP_DEBUG) return;
    $chosen = WC()->session->get('chosen_shipping_methods', array());
    echo '<!-- MSC Debug - Chosen Shipping Methods: ' . print_r($chosen, true) . ' -->';
}

add_action('woocommerce_before_checkout_form', 'msc_force_calculate_totals', 5);
function msc_force_calculate_totals() {
    if (!is_checkout()) return;
    WC()->cart->calculate_shipping();
    WC()->cart->calculate_totals();
}

add_action('wp_ajax_woocommerce_update_order_review', 'msc_force_recalculate_before_update', 1);
add_action('wp_ajax_nopriv_woocommerce_update_order_review', 'msc_force_recalculate_before_update', 1);
function msc_force_recalculate_before_update() {
    if (!is_checkout()) return;
    if (WC()->session) {
        WC()->session->set('shipping_for_package_0', null);
        WC()->session->set('shipping_for_package_1', null);
        WC()->session->set('previous_shipping_methods', null);
    }
    if (WC()->cart) {
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }
    if (WC()->shipping) {
        $packages = WC()->cart->get_shipping_packages();
        if (!empty($packages)) {
            WC()->shipping->calculate_shipping($packages);
        }
    }
    if (WC()->session) {
        WC()->session->save_data();
    }
}

add_action('woocommerce_review_order_after_order_total', 'msc_add_update_timestamp');
function msc_add_update_timestamp() {
    $timestamp = time();
    $cart_total = WC()->cart ? WC()->cart->get_total() : '0';
    $shipping_total = WC()->cart ? (WC()->cart->get_shipping_total() + WC()->cart->get_shipping_tax()) : '0';
    echo '<!-- MSC Update: ' . $timestamp . ' | Cart Total: ' . $cart_total . ' | Shipping: ' . $shipping_total . ' -->';
    echo '<input type="hidden" id="msc-last-update" value="' . esc_attr($timestamp) . '" data-cart-total="' . esc_attr($cart_total) . '" data-shipping="' . esc_attr($shipping_total) . '">';
}

add_action('wp_footer', 'msc_force_pagarme_update', 999);
function msc_force_pagarme_update() {
    if (!is_checkout()) return;
    ?>
    <script>
    jQuery(document).ready(function($){
        var lastTotal = $('.msc-total-value').text();
        function checkTotalChanged() {
            var currentTotal = $('.msc-total-value').text();
            if (currentTotal !== lastTotal && currentTotal !== '') {
                lastTotal = currentTotal;
                if (typeof window.wc_pagarme_update_checkout === 'function') {
                    setTimeout(function() { window.wc_pagarme_update_checkout(); }, 500);
                }
                $(document.body).trigger('update_checkout');
            }
        }
        setInterval(checkTotalChanged, 2000);
        $(document.body).on('updated_cart_totals updated_checkout updated_shipping_method', function(){
            setTimeout(checkTotalChanged, 500);
        });
    });
    </script>
    <?php
}

// Modificar a URL do carrinho e do checkout globalmente
add_filter('woocommerce_get_cart_url', 'msc_custom_cart_url');
function msc_custom_cart_url($url) {
    $checkout_page_id = wc_get_page_id('checkout');
    if ($checkout_page_id) {
        return add_query_arg('step', '1', get_permalink($checkout_page_id));
    }
    return $url;
}

add_filter('woocommerce_get_checkout_url', 'msc_custom_checkout_url');
function msc_custom_checkout_url($url) {
    if (strpos($url, 'step=2') === false && strpos($url, 'step=1') === false) {
        return add_query_arg('step', '2', $url);
    }
    return $url;
}

// Forçar o redirect do mini-cart via JS (caso o tema tenha as URLs fixas no HTML) e pular para o passo 2 se requisitado
add_action('wp_footer', 'msc_custom_minicart_scripts', 100);
function msc_custom_minicart_scripts() {
    $checkout_url_step1 = function_exists('wc_get_checkout_url') ? add_query_arg('step', '1', wc_get_checkout_url()) : '';
    $checkout_url_step2 = function_exists('wc_get_checkout_url') ? add_query_arg('step', '2', wc_get_checkout_url()) : '';
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Redireciona botões do mini carrinho para os passos corretos
        var step1Url = '<?php echo esc_url($checkout_url_step1); ?>';
        var step2Url = '<?php echo esc_url($checkout_url_step2); ?>';
        
        if (step1Url) {
            $('a.view-cart, a.button.view-cart').attr('href', step1Url);
        }
        if (step2Url) {
            $('a.checkout-button, a.button.checkout, a.checkout-button').attr('href', step2Url);
        }
        
        <?php if (is_checkout() && !is_wc_endpoint_url('order-received') && isset($_GET['step'])) : ?>
            <?php $step = intval($_GET['step']); ?>
            <?php if ($step === 2) : ?>
                // Aguarda o plugin carregar e avança para o Step 2
                setTimeout(function() {
                    $('.msc-btn-next[data-next-step="2"]').trigger('click');
                }, 500);
                // Fallback para garantir a execução caso algo atrase
                setTimeout(function() {
                    if ($('.msc-step[data-step="1"]').hasClass('active')) {
                        $('.msc-btn-next[data-next-step="2"]').trigger('click');
                    }
                }, 1200);
            <?php endif; ?>
        <?php endif; ?>
    });
    </script>
    <?php
}
