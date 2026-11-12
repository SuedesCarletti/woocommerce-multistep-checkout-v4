<?php
/**
 * MSC AJAX Handler v4.7 E ROBUSTA
 * Arquivo: includes/class-msc-ajax-handler-v4.php
 *
 * CORREÇÃO v4.7:
 * - A função `get_msc_shipping_methods_html` foi reescrita para ser mais resiliente.
 * - Ela não depende mais estritamente de `package_details`, evitando o problema da "tela em branco".
 * - Garante que os métodos de frete sejam sempre exibidos durante a atualização AJAX,
 * usando um fallback para o nome do local se o nome customizado não for encontrado.
 */

if (!defined('ABSPATH')) exit;

class MSC_Ajax_Handler_V4 {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('wp_ajax_msc_update_cart_item', array($this, 'ajax_update_cart_item'));
        add_action('wp_ajax_nopriv_msc_update_cart_item', array($this, 'ajax_update_cart_item'));
        add_filter('woocommerce_update_order_review_fragments', array($this, 'add_shipping_fragments'));
    }

    public function ajax_update_cart_item() {
        // Validações básicas
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'msc_nonce')) {
            wp_send_json_error(array('message' => 'Nonce inválido'), 403);
        }
        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(array('message' => 'WooCommerce não disponível'), 500);
        }

        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 0;

        if (empty($cart_item_key)) {
            wp_send_json_error(array('message' => 'Chave do item inválida'), 400);
        }

        // Atualiza a quantidade ou remove o item do carrinho
        $updated = $quantity > 0 ? WC()->cart->set_quantity($cart_item_key, $quantity) : WC()->cart->remove_cart_item($cart_item_key);

        if (!$updated) {
            wp_send_json_error(array('message' => 'Não foi possível atualizar o item'), 500);
        }

        // ANTES: delegava para `WC_AJAX::get_refreshed_fragments()`, que aplica o
        // filtro 'woocommerce_add_to_cart_fragments' (mini-carrinho) — não o
        // 'woocommerce_update_order_review_fragments' que `add_shipping_fragments()`
        // escuta. Ou seja, nosso filtro nunca era chamado e a resposta não trazia
        // nenhum dos campos que o JS (updateCartQuantity) usa para corrigir o
        // subtotal do item e os totais na hora (item_subtotal, cart_subtotal,
        // cart_total, etc.) — só ficavam corretos quando o cliente avançava de
        // step e disparava o update_checkout "de verdade" do WooCommerce.
        // Aqui montamos a resposta diretamente com os dados já recalculados.
        $fragments = $this->add_shipping_fragments(array());

        $data = array(
            'cart_subtotal'      => WC()->cart->get_cart_subtotal(),
            'cart_total'         => WC()->cart->get_total(),
            'shipping_total_raw' => (float) WC()->cart->get_shipping_total(),
            'discount_total_raw' => (float) WC()->cart->get_discount_total(),
            'fragments'          => $fragments,
        );

        if ($quantity > 0 && isset(WC()->cart->cart_contents[$cart_item_key])) {
            $cart_item = WC()->cart->cart_contents[$cart_item_key];
            $data['item_subtotal'] = WC()->cart->get_product_subtotal($cart_item['data'], $cart_item['quantity']);
        }

        if (function_exists('msc_group_cart_by_codlocal')) {
            $codlocal_totals = array();
            foreach (msc_group_cart_by_codlocal() as $codlocal => $group) {
                $codlocal_totals[$codlocal] = $group['total_value'];
            }
            $data['codlocal_totals'] = $codlocal_totals;
        }

        wp_send_json_success($data);
    }

    public function add_shipping_fragments($fragments) {
        // Força o WooCommerce a recalcular tudo com base no carrinho atualizado
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        // Gera e adiciona nossos fragmentos de HTML customizados à resposta AJAX
        $fragments['.msc-shipping-methods-container'] = $this->get_msc_shipping_methods_html();
        $fragments['.msc-review-shipping-methods'] = $this->get_msc_review_shipping_html();

        return $fragments;
    }

    /**
     * Renderiza o HTML para a seção de métodos de envio (Passo 2).
     *
     * Delega para MSC_Shipping_Packages_Handler::render_single_package(), a
     * mesma função usada na carga inicial da página, para que o refresh via
     * AJAX produza exatamente o mesmo HTML (antes disso, esta função tinha
     * sua própria renderização simplificada, causando um visual diferente
     * após qualquer atualização de frete).
     */
    private function get_msc_shipping_methods_html() {
        ob_start();
        ?>
        <div class="msc-shipping-methods-container">
            <h3 class="msc-section-title">
                <span class="msc-section-icon">📮</span>
                Forma de Entrega
            </h3>
            <?php
            if (WC()->cart->needs_shipping() && WC()->cart->show_shipping() && class_exists('MSC_Shipping_Packages_Handler')) {
                $packages = WC()->shipping->get_packages();
                $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
                $handler = MSC_Shipping_Packages_Handler::instance();

                foreach ($packages as $i => $package) {
                    $package_name = isset($package['package_name']) ? $package['package_name'] : sprintf(__('Envio %d', 'multistep-checkout'), $i + 1);
                    $codlocal = isset($package['codlocal']) ? $package['codlocal'] : 'default';
                    $available_methods = isset($package['rates']) ? $package['rates'] : array();
                    $chosen_method = isset($chosen_methods[$i]) ? $chosen_methods[$i] : '';
                    $package_value = isset($package['contents_cost']) ? $package['contents_cost'] : 0;
                    $item_count = count($package['contents'] ?? array());

                    $handler->render_single_package($i, $package_name, $codlocal, $available_methods, $chosen_method, $package_value, $item_count);
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza o HTML para a seção de revisão de envio (Passo 4).
     */
    private function get_msc_review_shipping_html() {
        ob_start();
        ?>
        <div class="msc-review-shipping-methods">
            <?php
            if (WC()->cart->needs_shipping()) {
                $packages = WC()->shipping->get_packages();
                $chosen_methods = WC()->session->get('chosen_shipping_methods');
                $shipping_handler = class_exists('MSC_Shipping_Packages_Handler') ? MSC_Shipping_Packages_Handler::instance() : null;

                foreach ($packages as $i => $package) {
                    if (isset($chosen_methods[$i], $package['rates'][$chosen_methods[$i]])) {
                        $method = $package['rates'][$chosen_methods[$i]];
                        $display_methods = $shipping_handler ? $shipping_handler->get_display_methods($package['rates']) : array();
                        $display = isset($display_methods[$chosen_methods[$i]]) ? $display_methods[$chosen_methods[$i]] : null;

                        // Fallback para o nome do local
                        $package_details = $package['package_details'] ?? null;
                        $location_name = $package_details['name'] ?? __('Entrega', 'woocommerce');

                        $label = $display ? ($display['name'] . ' - ' . $display['eta']) : $method->get_label();
                        $cost = $display ? $display['cost'] : $method->cost;
                        $cost_html = ($cost > 0) ? wc_price($cost) : __('Frete Grátis', 'woocommerce');
                        ?>
                        <div class="msc-review-shipping-item" data-package-index="<?php echo esc_attr($i); ?>">
                            <span class="msc-review-shipping-package"><?php echo esc_html($location_name); ?>:</span>
                            <strong class="msc-review-shipping-label"><?php echo wp_kses_post($label); ?></strong>
                            <span class="msc-review-shipping-cost"><?php echo wp_kses_post($cost_html); ?></span>
                        </div>
                        <?php
                    }
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Inicializa a classe
MSC_Ajax_Handler_V4::instance();
