<?php
/**
 * MSC Shipping Packages Handler - VERSÃO ATUALIZADA
 * Gerencia packages separados por CODLOCAL com visual uniformizado
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('MSC_Shipping_Packages_Handler')) {

class MSC_Shipping_Packages_Handler {

    // Novo design do Step 2: só 2 opções de frete, com nome fixo (nunca o
    // nome da transportadora). Preço e prazo sempre vêm do método real de
    // frete cotado (ex.: AllPost), nunca de um valor inventado.
    const LABEL_ECONOMY = 'Entrega Econômica';
    const LABEL_EXPRESS = 'Entrega Expressa';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_filter('woocommerce_cart_shipping_packages', array($this, 'create_separate_packages'), 10, 1);
    }
    
    /**
     * Criar packages separados por CODLOCAL
     */
    public function create_separate_packages($packages) {
        
        if (!$this->should_separate_packages()) {
            return $packages;
        }
        
        $groups = $this->get_cart_groups_by_codlocal();
        
        if (count($groups) <= 1) {
            return $packages;
        }
        
        error_log('[MSC] Criando packages separados para ' . count($groups) . ' CODLOCAL diferentes');
        
        return $this->build_packages_from_groups($groups);
    }
    
    private function should_separate_packages() {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return false;
        }
        
        if (!function_exists('msc_cart_has_multiple_codlocal')) {
            return false;
        }
        
        return msc_cart_has_multiple_codlocal();
    }
    
    private function get_cart_groups_by_codlocal() {
        if (function_exists('msc_group_cart_by_codlocal')) {
            return msc_group_cart_by_codlocal();
        }
        
        return $this->create_basic_groups();
    }
    
    private function create_basic_groups() {
        $groups = array();
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $codlocal = $this->detect_product_codlocal($product_id);
            
            if (!isset($groups[$codlocal])) {
                $groups[$codlocal] = array(
                    'codlocal' => $codlocal,
                    'name' => $codlocal === 'default' ? 'Principal' : "Local {$codlocal}",
                    'items' => array(),
                    'item_count' => 0,
                    'total_value' => 0.0
                );
            }
            
            $groups[$codlocal]['items'][] = array(
                'key' => $cart_item_key,
                'cart_item' => $cart_item,
                'product_id' => $product_id,
                'quantity' => $cart_item['quantity']
            );
            
            $groups[$codlocal]['item_count'] += $cart_item['quantity'];
            $groups[$codlocal]['total_value'] += (float) $cart_item['line_total'];
        }
        
        return $groups;
    }
    
    private function detect_product_codlocal($product_id) {
        if (function_exists('msc_get_product_codlocal_cached')) {
            return msc_get_product_codlocal_cached($product_id);
        }
        
        if (function_exists('woo_importador_get_codlocal')) {
            $sku = get_post_meta($product_id, '_sku', true);
            if ($sku) {
                try {
                    $codlocal = woo_importador_get_codlocal($sku);
                    if ($codlocal && $codlocal !== 'default') {
                        return $codlocal;
                    }
                } catch (Exception $e) {
                    error_log('[MSC] Erro ao buscar CODLOCAL: ' . $e->getMessage());
                }
            }
        }
        
        $codlocal_meta = get_post_meta($product_id, '_codlocal', true);
        if ($codlocal_meta) {
            return $codlocal_meta;
        }
        
        return 'default';
    }
    
    private function build_packages_from_groups($groups) {
        $new_packages = array();
        $package_index = 0;
        $customer = WC()->customer;
        
        $destination = array(
            'country'   => $customer->get_shipping_country(),
            'state'     => $customer->get_shipping_state(),
            'postcode'  => $customer->get_shipping_postcode(),
            'city'      => $customer->get_shipping_city(),
            'address'   => $customer->get_shipping_address(),
            'address_1' => $customer->get_shipping_address_1(),
            'address_2' => $customer->get_shipping_address_2(),
        );
        
        foreach ($groups as $codlocal => $group) {
            
            $package_contents = array();
            $package_contents_cost = 0;
            
            foreach ($group['items'] as $item) {
                $cart_item_key = $item['key'];
                $cart_item = WC()->cart->get_cart()[$cart_item_key];
                
                $package_contents[$cart_item_key] = $cart_item;
                $package_contents_cost += (float) $cart_item['line_total'];
            }
            
            // Nomes personalizados
            $location_names = array(
                '201' => 'Curitiba',
                '401' => 'Espírito Santo',
                'default' => 'Principal'
            );
            
            $display_name = isset($location_names[$codlocal]) 
                ? $location_names[$codlocal] 
                : $group['name'];
            
            $new_packages[] = array(
                'contents'        => $package_contents,
                'contents_cost'   => $package_contents_cost,
                'applied_coupons' => WC()->cart->get_applied_coupons(),
                'user'            => array('ID' => get_current_user_id()),
                'destination'     => $destination,
                'ship_via'        => array(),
                'package_name'    => $display_name,
                'codlocal'        => $codlocal,
                'package_index'   => $package_index,
            );
            
            $package_index++;
        }
        
        error_log('[MSC] Criados ' . count($new_packages) . ' packages separados');
        
        return $new_packages;
    }
    
    /**
     * Renderizar métodos de frete por package
     */
    public function render_shipping_methods() {
        WC()->cart->calculate_totals();
        $packages = WC()->shipping() ? WC()->shipping()->get_packages() : array();
        
        if (empty($packages)) {
            echo '<p class="msc-notice">' . esc_html__('Informe o endereço completo para calcular o frete.', 'multistep-checkout') . '</p>';
            return;
        }
        
        $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
        
        foreach ($packages as $i => $package) {
            $package_name = isset($package['package_name']) ? $package['package_name'] : sprintf(__('Envio %d', 'multistep-checkout'), $i + 1);
            $codlocal = isset($package['codlocal']) ? $package['codlocal'] : 'default';
            $available_methods = isset($package['rates']) ? $package['rates'] : array();
            $chosen_method = isset($chosen_methods[$i]) ? $chosen_methods[$i] : '';
            
            $package_value = isset($package['contents_cost']) ? $package['contents_cost'] : 0;
            $item_count = count($package['contents'] ?? array());
            
            $this->render_single_package($i, $package_name, $codlocal, $available_methods, $chosen_method, $package_value, $item_count);
        }
    }
    
    /**
     * Renderizar package individual com visual uniformizado
     *
     * Público: também é chamado pelo MSC_Ajax_Handler_V4 ao reconstruir o
     * fragmento .msc-shipping-methods-container, para que a carga inicial
     * e o refresh via AJAX produzam exatamente o mesmo HTML.
     */
    public function render_single_package($index, $name, $codlocal, $methods, $chosen, $value, $count) {
        ?>
        <div class="msc-shipping-group" data-codlocal="<?php echo esc_attr($codlocal); ?>" data-package="<?php echo esc_attr($index); ?>">
            
            <!-- Cabeçalho do grupo (igual ao Step 1) -->
            <div class="msc-shipping-group-header">
                <div class="msc-codlocal-info">
                    <span class="msc-codlocal-icon">📦</span>
                    <div class="msc-codlocal-details">
                        <h3 class="msc-codlocal-name">
                            <?php echo esc_html($name); ?>
                            <span class="msc-codlocal-code"><?php echo esc_html($codlocal); ?></span>
                        </h3>
                        <p class="msc-codlocal-meta">
                            <?php 
                            printf(
                                esc_html__('%d %s', 'multistep-checkout'),
                                $count,
                                _n('item', 'itens', $count, 'multistep-checkout')
                            );
                            ?>
                            <span class="msc-codlocal-separator">•</span>
                            <?php echo wc_price($value); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Métodos de frete -->
            <div class="msc-shipping-group-methods">
                <?php
                $display_methods = $this->get_display_methods($methods);
                $cheapest_id = ($display_methods) ? array_key_first($display_methods) : null;
                ?>
                <?php if (!empty($display_methods)) : ?>
                    <ul class="msc-shipping-methods-list">
                        <?php foreach ($display_methods as $method_id => $display) : ?>
                            <?php $is_selected = $chosen ? ($method_id === $chosen) : ($method_id === $cheapest_id); ?>
                            <li class="msc-shipping-method-item <?php echo $is_selected ? 'is-selected' : ''; ?>">
                                <label class="msc-shipping-method-label">
                                    <input type="radio"
                                           name="shipping_method[<?php echo esc_attr($index); ?>]"
                                           value="<?php echo esc_attr($method_id); ?>"
                                           class="msc-shipping-method-input"
                                           data-index="<?php echo esc_attr($index); ?>"
                                           data-codlocal="<?php echo esc_attr($codlocal); ?>"
                                           <?php checked($is_selected, true); ?>>
                                    <span class="msc-method-radio" aria-hidden="true"></span>

                                    <div class="msc-method-details">
                                        <span class="msc-method-name"><?php echo esc_html($display['name'] . ' - ' . $display['eta']); ?></span>
                                        <span class="msc-method-time">
                                            <?php echo esc_html($display['eta']); ?>
                                        </span>
                                    </div>

                                    <!-- Valor do frete -->
                                    <span class="msc-method-cost">
                                        <?php
                                        if ($display['cost'] > 0) {
                                            echo wc_price($display['cost']);
                                        } else {
                                            echo '<span style="color:#00a650; font-weight:700;">Gratuito</span>';
                                        }
                                        ?>
                                    </span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p class="msc-no-shipping-methods">
                        <?php esc_html_e('Nenhum método disponível para este local. Verifique o endereço.', 'multistep-checkout'); ?>
                    </p>
                <?php endif; ?>
            </div>

        </div>
        <?php
    }

    /**
     * Reduz os métodos de frete brutos do WooCommerce às no máximo 2 opções
     * do novo design do Step 2. Preço e prazo sempre vêm da cotação real do
     * método (ex.: metadado `delivery_time` da AllPost) — nunca inventados.
     *
     * "Entrega Econômica" (mais barata) e "Entrega Expressa" (mais cara) só
     * são exibidas como par quando isso é coerente com os dados reais: a
     * opção mais cara precisa ter prazo estritamente menor que a mais
     * barata (senão não faz sentido cobrar mais pelo "mesmo" prazo). Se essa
     * condição não se confirma — ou se só existe 1 método cotado — mostra
     * apenas a melhor opção (a mais barata), sempre com nome e prazo reais.
     *
     * Público: usado também pelo resumo do Step 4, para que o rótulo exibido
     * lá bata com o que o cliente viu e selecionou no Step 2.
     *
     * @return array<string,array{id:string,cost:float,name:string,eta:string}> indexado por rate ID
     */
    public function get_display_methods($methods) {
        if (empty($methods)) {
            return array();
        }

        $sorted = array_values($methods);
        usort($sorted, function ($a, $b) {
            return $a->get_cost() <=> $b->get_cost();
        });

        $cheapest = $sorted[0];
        $cheapest_eta_days = $this->extract_delivery_days($cheapest);

        if (count($sorted) > 1) {
            $priciest = $sorted[count($sorted) - 1];
            $priciest_eta_days = $this->extract_delivery_days($priciest);

            $is_coherent_pair = $cheapest_eta_days !== null
                && $priciest_eta_days !== null
                && $priciest_eta_days < $cheapest_eta_days;

            if ($is_coherent_pair) {
                return array(
                    $cheapest->get_id() => array(
                        'id'   => $cheapest->get_id(),
                        'cost' => (float) $cheapest->get_cost(),
                        'name' => self::LABEL_ECONOMY,
                        'eta'  => $this->format_delivery_days($cheapest_eta_days),
                    ),
                    $priciest->get_id() => array(
                        'id'   => $priciest->get_id(),
                        'cost' => (float) $priciest->get_cost(),
                        'name' => self::LABEL_EXPRESS,
                        'eta'  => $this->format_delivery_days($priciest_eta_days),
                    ),
                );
            }
        }

        // Sem par coerente (ou só há 1 método cotado): mostra apenas a
        // melhor opção real disponível, a mais barata.
        return array(
            $cheapest->get_id() => array(
                'id'   => $cheapest->get_id(),
                'cost' => (float) $cheapest->get_cost(),
                'name' => self::LABEL_ECONOMY,
                'eta'  => $cheapest_eta_days !== null ? $this->format_delivery_days($cheapest_eta_days) : $this->extract_delivery_text($cheapest),
            ),
        );
    }

    /**
     * Prazo em dias úteis de um método, a partir do metadado `delivery_time`
     * usado pela AllPost (e por convenção por outros métodos deste projeto).
     * Retorna null quando o método não informa prazo (ex.: frete de
     * contingência "A consultar"), para que o chamador saiba que não pode
     * comparar/validar esse valor.
     */
    private function extract_delivery_days($method) {
        $meta_data = $method->get_meta_data();

        if (is_array($meta_data) && isset($meta_data['delivery_time']) && is_numeric($meta_data['delivery_time']) && $meta_data['delivery_time'] > 0) {
            return (int) $meta_data['delivery_time'];
        }

        if (is_array($meta_data)) {
            foreach ($meta_data as $meta_value) {
                if (preg_match('/(\d+)\s*(dia|day|útil|util|corrido)/i', (string) $meta_value, $matches)) {
                    return (int) $matches[1];
                }
            }
        }

        // Alguns métodos (ex.: AllPost) embutem o prazo só no próprio label,
        // como "Bauer - (Normal) - 5 dias úteis".
        if (preg_match('/(\d+)\s*dias?\s*úteis?/i', $method->get_label(), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Texto de prazo pronto para exibição quando não há dias numéricos
     * extraíveis (ex.: "A consultar", "Frete grátis" no meta `delivery_time`
     * do frete de contingência/grátis da AllPost).
     */
    private function extract_delivery_text($method) {
        $meta_data = $method->get_meta_data();

        if (is_array($meta_data) && isset($meta_data['delivery_time']) && is_string($meta_data['delivery_time']) && $meta_data['delivery_time'] !== '') {
            return $meta_data['delivery_time'];
        }

        return __('Prazo a confirmar', 'multistep-checkout');
    }

    private function format_delivery_days($days) {
        return sprintf(_n('%d dia útil', '%d dias úteis', $days, 'multistep-checkout'), $days);
    }
}

} // end class

// Inicializar
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        MSC_Shipping_Packages_Handler::instance();
    }
}, 25);
