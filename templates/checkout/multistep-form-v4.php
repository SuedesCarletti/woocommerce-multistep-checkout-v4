<?php
/**
 * Template: Multistep Checkout v4 - VERSÃO COMPLETA CORRIGIDA
 * Arquivo: templates/checkout/multistep-form-v4.php
 * 
 * CORREÇÕES APLICADAS:
 * - Step 3 renderiza métodos de pagamento diretamente
 * - Review table oculta sem duplicar métodos de pagamento
 * - Sincronização de valores de frete entre steps
 * - Atualização dinmica de valores no Step 4
 */

if (!defined('ABSPATH')) exit;

$checkout = WC()->checkout();
$cart = WC()->cart;

do_action('woocommerce_before_checkout_form', $checkout);
?>

<div class="msc-checkout-wrapper" data-current-step="1">

  <!-- Barra de Progresso -->
  <div class="msc-progress-container">
    <div class="msc-progress-steps">
      <div class="msc-progress-step active" data-step="1">
        <div class="msc-step-circle">1</div>
        <div class="msc-step-label"><?php esc_html_e('Carrinho', 'multistep-checkout'); ?></div>
      </div>
      <div class="msc-progress-step" data-step="2">
        <div class="msc-step-circle">2</div>
        <div class="msc-step-label"><?php esc_html_e('Envio', 'multistep-checkout'); ?></div>
      </div>
      <div class="msc-progress-step" data-step="3">
        <div class="msc-step-circle">3</div>
        <div class="msc-step-label"><?php esc_html_e('Pagamento', 'multistep-checkout'); ?></div>
      </div>
      <div class="msc-progress-step" data-step="4">
        <div class="msc-step-circle">4</div>
        <div class="msc-step-label"><?php esc_html_e('Revisão', 'multistep-checkout'); ?></div>
      </div>
    </div>
    <div class="msc-progress-bar">
      <div class="msc-progress-bar-inner" style="width: 25%;"></div>
    </div>
  </div>

  <!-- Formulário -->
  <form name="checkout" 
        method="post" 
        class="checkout woocommerce-checkout msc-form" 
        action="<?php echo esc_url(wc_get_checkout_url()); ?>" 
        enctype="multipart/form-data">

    <div class="msc-checkout-content">
      
      <div class="msc-main-panel">

        <!-- ==========================================
            STEP 1: CARRINHO - COM AGRUPAMENTO POR CODLOCAL
        ========================================== -->
        <?php
        // Obter grupos de produtos por CODLOCAL
        $cart_groups = array();
        $has_multiple_codlocal = false;

        if (function_exists('msc_group_cart_by_codlocal')) {
            $cart_groups = msc_group_cart_by_codlocal();
            $has_multiple_codlocal = count($cart_groups) > 1;
        }

        // Fallback: se não tiver agrupamento, criar grupo único
        if (empty($cart_groups)) {
            $cart_groups = array(
                'default' => array(
                    'codlocal' => 'default',
                    'name' => 'Principal',
                    'items' => array(),
                    'item_count' => 0,
                    'total_value' => 0.0
                )
            );
            
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                $product_id = $cart_item['product_id'];
                $cart_groups['default']['items'][] = array(
                    'key' => $cart_item_key,
                    'cart_item' => $cart_item,
                    'data' => $cart_item['data'],
                    'product_id' => $product_id,
                    'quantity' => $cart_item['quantity']
                );
            }
        }
        ?>

        <div class="msc-step msc-step-cart active" data-step="1">
            <div class="msc-step-header">
                <h2 class="msc-step-title"><?php esc_html_e('Carrinho', 'multistep-checkout'); ?></h2>
                <p class="msc-step-subtitle">
                    <?php 
                    if ($has_multiple_codlocal) {
                        printf(
                            esc_html__('Seus produtos estão em %d locais de estoque diferentes', 'multistep-checkout'),
                            count($cart_groups)
                        );
                    } else {
                        esc_html_e('Revise os produtos e quantidades', 'multistep-checkout');
                    }
                    ?>
                </p>
            </div>

            <div class="msc-step-content">
                
                <?php foreach ($cart_groups as $codlocal => $group) : ?>
                    
                    <!-- Bloco de CODLOCAL -->
                    <div class="msc-codlocal-group" data-codlocal="<?php echo esc_attr($codlocal); ?>">
                        
                        <?php if ($has_multiple_codlocal) : ?>
                            <!-- Cabeçalho do grupo (apenas se houver múltiplos) -->
                            <div class="msc-codlocal-header">
                                <div class="msc-codlocal-info">
                                    <span class="msc-codlocal-icon">📍</span>
                                    <div class="msc-codlocal-details">
                                        <h3 class="msc-codlocal-name">
                                            <?php 
                                            $display_name = function_exists('msc_get_codlocal_display_name') 
                                                ? msc_get_codlocal_display_name($codlocal) 
                                                : $group['name']; 
                                            echo esc_html($display_name);
                                            ?>
                                            <span class="msc-codlocal-code"><?php echo esc_html($codlocal); ?></span>
                                        </h3>
                                        <p class="msc-codlocal-meta">
                                            <?php 
                                            printf(
                                                esc_html__('%d %s', 'multistep-checkout'),
                                                $group['item_count'],
                                                _n('item', 'itens', $group['item_count'], 'multistep-checkout')
                                            );
                                            ?>
                                            <span class="msc-codlocal-separator">•</span>
                                            <?php echo wc_price($group['total_value']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Produtos do grupo -->
                        <div class="msc-codlocal-items">
                            <?php
                            foreach ($group['items'] as $item) {
                                $cart_item_key = $item['key'];
                                $cart_item = $item['cart_item'];
                                $_product = $item['data'];
                                $product_id = $item['product_id'];
                                
                                if (!$_product || !$_product->exists()) continue;
                                
                                $product_name = $_product->get_name();
                                $product_price = WC()->cart->get_product_price($_product);
                                $thumbnail = $_product->get_image('woocommerce_thumbnail');
                                $item_subtotal = WC()->cart->get_product_subtotal($_product, $cart_item['quantity']);

                                $product_category = '';
                                $product_cat_terms = get_the_terms($product_id, 'product_cat');
                                if ($product_cat_terms && !is_wp_error($product_cat_terms)) {
                                    $product_category = $product_cat_terms[0]->name;
                                }
                                
                                // Estoque
                                $stock_qty = $_product->get_stock_quantity();
                                $max_qty = $stock_qty ?: 999;
                                
                                // Info de estoque
                                $stock_class = 'in-stock';
                                $stock_text = $stock_qty ? sprintf(__('%d em estoque', 'multistep-checkout'), $stock_qty) : __('Disponível', 'multistep-checkout');
                                $stock_icon = '✓';
                                
                                if (!$_product->is_in_stock()) {
                                    $stock_class = 'out-of-stock';
                                    $stock_text = __('Indisponível', 'multistep-checkout');
                                    $stock_icon = '✕';
                                } elseif ($stock_qty && $stock_qty <= 5) {
                                    $stock_class = 'low-stock';
                                    $stock_text = sprintf(__('Apenas %d restantes', 'multistep-checkout'), $stock_qty);
                                    $stock_icon = '⚠';
                                }
                                ?>
                                
                                <div class="msc-cart-item" data-cart-key="<?php echo esc_attr($cart_item_key); ?>">
                                    
                                    <!-- Imagem -->
                                    <div class="msc-item-image">
                                        <?php echo $thumbnail; ?>
                                    </div>
                                    
                                    <!-- Detalhes -->
                                    <div class="msc-item-details">
                                        <?php if ($product_category) : ?>
                                            <p class="msc-item-category"><?php echo esc_html($product_category); ?></p>
                                        <?php endif; ?>
                                        <h4 class="msc-item-name"><?php echo wp_kses_post($product_name); ?></h4>
                                        <p class="msc-item-price"><?php echo $product_price; ?></p>
                                    </div>
                                    
                                    <!-- Quantidade com botões +/- -->
                                    <div class="msc-item-quantity">
                                        <button type="button" class="msc-qty-btn msc-qty-minus" data-action="minus">−</button>
                                        <input type="number" 
                                            class="qty" 
                                            name="cart[<?php echo esc_attr($cart_item_key); ?>][qty]" 
                                            value="<?php echo esc_attr($cart_item['quantity']); ?>" 
                                            min="1" 
                                            max="<?php echo esc_attr($max_qty); ?>" 
                                            step="1" 
                                            data-original="<?php echo esc_attr($cart_item['quantity']); ?>"
                                            inputmode="numeric"
                                            pattern="[0-9]*">
                                        <button type="button" class="msc-qty-btn msc-qty-plus" data-action="plus">+</button>
                                    </div>
                                    
                                    <!-- Subtotal -->
                                    <div class="msc-item-subtotal">
                                        <?php echo $item_subtotal; ?>
                                    </div>
                                    
                                    <!-- Botão Remover -->
                                    <button type="button" 
                                            class="msc-remove-item" 
                                            data-cart-key="<?php echo esc_attr($cart_item_key); ?>" 
                                            title="<?php esc_attr_e('Remover', 'multistep-checkout'); ?>">×</button>
                                    
                                    <!-- Info de Estoque -->
                                    <div class="msc-item-stock-info <?php echo esc_attr($stock_class); ?>">
                                        <span class="stock-icon"><?php echo $stock_icon; ?></span>
                                        <span class="stock-text"><?php echo esc_html($stock_text); ?></span>
                                    </div>
                                    
                                </div>
                                
                            <?php } // end foreach items ?>
                        </div>
                        
                        <?php if ($has_multiple_codlocal) : ?>
                            <!-- Rodapé do grupo com subtotal -->
                            <div class="msc-codlocal-footer">
                                <span class="msc-codlocal-subtotal-label">
                                    <?php esc_html_e('Subtotal deste local:', 'multistep-checkout'); ?>
                                </span>
                                <span class="msc-codlocal-subtotal-value">
                                    <?php echo wc_price($group['total_value']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                    
                <?php endforeach; ?>
                
                <!-- Campo de Cupom -->
                <div class="msc-coupon-wrapper">
                    <h3 class="msc-coupon-title">
                        <span class="msc-coupon-icon">🎫</span>
                        <?php esc_html_e('Cupom de desconto', 'multistep-checkout'); ?>
                    </h3>
                    
                    <?php
                    $applied_coupons = $cart->get_applied_coupons();
                    if (!empty($applied_coupons)) {
                        echo '<div class="msc-applied-coupons">';
                        foreach ($applied_coupons as $coupon_code) {
                            echo '<div class="msc-applied-coupon">';
                            echo '<span class="msc-coupon-code">' . esc_html($coupon_code) . '</span>';
                            echo '<button type="button" class="msc-remove-coupon" data-coupon="' . esc_attr($coupon_code) . '">×</button>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    ?>
                    
                    <div class="msc-coupon-form">
                        <input type="text"
                            class="msc-coupon-input"
                            placeholder="<?php esc_attr_e('Código do cupom', 'multistep-checkout'); ?>"
                            value="">
                        <button type="button" class="msc-apply-coupon">
                            <?php esc_html_e('Aplicar', 'multistep-checkout'); ?>
                        </button>
                    </div>
                </div>
                
            </div>

            <div class="msc-step-footer">
                <button type="button" class="msc-btn msc-btn-next" data-next-step="2">
                    <?php esc_html_e('Continuar para Envio', 'multistep-checkout'); ?> →
                </button>
            </div>
        </div>

        <!-- ==========================================
            STEP 2: ENVIO
        ========================================== -->
        <div class="msc-step msc-step-shipping" data-step="2" style="display: none;">
            <div class="msc-step-header">
                <h2 class="msc-step-title"><?php esc_html_e('Envio e Cobrança', 'multistep-checkout'); ?></h2>
                <p class="msc-step-subtitle"><?php esc_html_e('Informe os dados para cálculo do frete', 'multistep-checkout'); ?></p>
            </div>

            <div class="msc-step-content">

                <?php
                // Separa os campos de billing em "contato" (email/telefone/CPF)
                // e "endereço" (nome, logradouro, bairro, cidade, UF...) para o
                // tratamento visual de display/edição do design. A classificação
                // é por substring da chave, não por uma lista fixa de campos, para
                // continuar funcionando mesmo se um plugin de campos brasileiros
                // (CPF/CNPJ) usar uma chave diferente de "billing_cpf".
                $billing_fields = $checkout->get_checkout_fields('billing');
                $msc_contact_fields = array();
                $msc_address_fields = array();
                foreach ($billing_fields as $key => $field) {
                    if (preg_match('/email|phone|cpf|cnpj|document/i', $key)) {
                        $msc_contact_fields[$key] = $field;
                    } else {
                        $msc_address_fields[$key] = $field;
                    }
                }

                $msc_contact_has_value = false;
                foreach ($msc_contact_fields as $key => $field) {
                    if ($checkout->get_value($key)) {
                        $msc_contact_has_value = true;
                        break;
                    }
                }
                $msc_contact_mode = $msc_contact_has_value ? 'display' : 'edit';

                // Mesmo critério do bloco Contato: qualquer um dos campos que
                // identificam um endereço já salvo é suficiente para abrir no
                // card resumido (os campos que faltarem aparecem como "—" no
                // resumo). Exigir *todos* os campos (ex.: nome E CEP) deixava
                // o card travado em "edit" para clientes com endereço parcial.
                $msc_address_identifying_fields = array('billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_postcode');
                $msc_address_has_value = false;
                foreach ($msc_address_identifying_fields as $key) {
                    if ($checkout->get_value($key)) {
                        $msc_address_has_value = true;
                        break;
                    }
                }
                $msc_address_mode = $msc_address_has_value ? 'display' : 'edit';
                ?>

                <!-- Contato -->
                <div class="msc-contact-block" data-mode="<?php echo esc_attr($msc_contact_mode); ?>">
                    <h3 class="msc-section-title">
                        <span class="msc-section-icon">👤</span>
                        <?php esc_html_e('Contato', 'multistep-checkout'); ?>
                    </h3>

                    <div class="msc-contact-display">
                        <?php foreach ($msc_contact_fields as $key => $field) : ?>
                            <div class="msc-contact-row">
                                <span class="msc-contact-label"><?php echo esc_html(wp_strip_all_tags($field['label'] ?? '')); ?></span>
                                <span class="msc-contact-value" data-display-field="<?php echo esc_attr($key); ?>"><?php echo esc_html($checkout->get_value($key) ?: '—'); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <button type="button" class="msc-inline-edit-btn msc-contact-edit-btn">✏️ <?php esc_html_e('Alterar', 'multistep-checkout'); ?></button>
                    </div>

                    <div class="msc-contact-edit">
                        <div class="msc-fields-grid msc-fields-grid-3col">
                            <?php foreach ($msc_contact_fields as $key => $field) : ?>
                                <?php woocommerce_form_field($key, $field, $checkout->get_value($key)); ?>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="msc-btn msc-inline-save-btn msc-contact-save-btn"><?php esc_html_e('Salvar', 'multistep-checkout'); ?></button>
                    </div>
                </div>

                <!-- Endereço -->
                <div class="msc-address-block" data-mode="<?php echo esc_attr($msc_address_mode); ?>">
                    <h3 class="msc-section-title">
                        <span class="msc-section-icon">📍</span>
                        <?php esc_html_e('Endereço', 'multistep-checkout'); ?>
                    </h3>

                    <div class="msc-address-display">
                        <div class="msc-address-card is-selected">
                            <span class="msc-address-badge"><?php esc_html_e('PADRÃO', 'multistep-checkout'); ?></span>
                            <div class="msc-address-card-body">
                                <strong data-display-field="msc_address_name">
                                    <?php echo esc_html(trim($checkout->get_value('billing_first_name') . ' ' . $checkout->get_value('billing_last_name')) ?: '—'); ?>
                                </strong>
                                <p data-display-field="msc_address_line">
                                    <?php
                                    $address_line = trim($checkout->get_value('billing_address_1') . ' ' . $checkout->get_value('billing_address_2'));
                                    $neighborhood = $checkout->get_value('billing_neighborhood');
                                    echo esc_html($address_line ?: '—');
                                    if ($neighborhood) {
                                        echo esc_html(' - ' . $neighborhood);
                                    }
                                    ?>
                                </p>
                                <p data-display-field="msc_address_city_state">
                                    <?php
                                    $city = $checkout->get_value('billing_city');
                                    $state = $checkout->get_value('billing_state');
                                    $postcode = $checkout->get_value('billing_postcode');
                                    echo esc_html(trim($city . ($state ? ' - ' . $state : '')) ?: '—');
                                    if ($postcode) {
                                        echo esc_html(', ' . $postcode);
                                    }
                                    ?>
                                </p>
                            </div>
                            <button type="button" class="msc-inline-edit-btn msc-address-edit-btn">✏️ <?php esc_html_e('Alterar', 'multistep-checkout'); ?></button>
                        </div>
                    </div>

                    <div class="msc-address-edit">
                        <div class="msc-fields-grid">
                            <?php foreach ($msc_address_fields as $key => $field) : ?>
                                <?php woocommerce_form_field($key, $field, $checkout->get_value($key)); ?>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="msc-btn msc-inline-save-btn msc-address-save-btn"><?php esc_html_e('Salvar', 'multistep-checkout'); ?></button>
                    </div>
                </div>

                <!-- Toggle: Entregar em endereço diferente -->
                <div class="msc-ship-to-different msc-ship-to-different-dashed">
                    <label class="msc-checkbox-label">
                        <input type="checkbox"
                            id="ship-to-different-address-checkbox"
                            name="ship_to_different_address"
                            value="1"
                            <?php checked(apply_filters('woocommerce_ship_to_different_address_checked', 'shipping' === get_option('woocommerce_ship_to_destination') ? 1 : 0), 1); ?>>
                        <span class="msc-checkbox-text">+ <?php esc_html_e('Cadastrar novo endereço de entrega', 'multistep-checkout'); ?></span>
                    </label>
                </div>

                <!-- Campos de Entrega (colapsáveis) -->
                <div class="msc-shipping-section shipping_address" style="<?php echo apply_filters('woocommerce_ship_to_different_address_checked', 'shipping' === get_option('woocommerce_ship_to_destination') ? 1 : 0) ? '' : 'display:none;'; ?>">
                    <h3 class="msc-section-title">
                        <span class="msc-section-icon">🚚</span>
                        <?php esc_html_e('Endereço de Entrega', 'multistep-checkout'); ?>
                    </h3>

                    <div class="msc-fields-grid">
                        <!-- Nome do destinatário: campo único visível que sincroniza, via JS,
                             com os shipping_first_name/shipping_last_name reais (mantidos no
                             DOM, só ocultos) para não mudar os dados submetidos ao WooCommerce. -->
                        <p class="form-row form-row-wide msc-shipping-fullname-field">
                            <label for="msc_shipping_full_name" class="required_field"><?php esc_html_e('Nome do destinatário', 'multistep-checkout'); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
                            <span class="woocommerce-input-wrapper">
                                <input type="text" class="input-text" id="msc_shipping_full_name" placeholder="<?php esc_attr_e('Nome completo', 'multistep-checkout'); ?>" autocomplete="name">
                            </span>
                        </p>
                        <?php
                        $shipping_fields = $checkout->get_checkout_fields('shipping');
                        foreach ($shipping_fields as $key => $field) {
                            woocommerce_form_field($key, $field, $checkout->get_value($key));
                        }
                        ?>
                    </div>
                </div>

                <!-- Métodos de Frete por CODLOCAL -->
                <div class="msc-shipping-methods-container">
                    <h3 class="msc-section-title">
                        <span class="msc-section-icon">📮</span>
                        <?php esc_html_e('Forma de Entrega', 'multistep-checkout'); ?>
                    </h3>

                    <?php
                    // Renderizar métodos de frete usando o handler customizado
                    if (class_exists('MSC_Shipping_Packages_Handler')) {
                        $handler = MSC_Shipping_Packages_Handler::instance();
                        $handler->render_shipping_methods();
                    } else {
                        // Fallback: usar método nativo do WooCommerce
                        woocommerce_order_review();
                    }
                    ?>
                </div>

            </div>

            <div class="msc-step-footer">
                <button type="button" class="msc-btn msc-btn-back" data-prev-step="1">
                    ← <?php esc_html_e('Voltar', 'multistep-checkout'); ?>
                </button>
                <button type="button" class="msc-btn msc-btn-next" data-next-step="3">
                    <?php esc_html_e('Continuar para Pagamento', 'multistep-checkout'); ?> →
                </button>
            </div>
        </div>

        <!-- ==========================================
             STEP 3: PAGAMENTO
        ========================================== -->
        <div class="msc-step msc-step-payment" data-step="3" style="display: none;">
            <div class="msc-step-header">
                <h2 class="msc-step-title"><?php esc_html_e('Pagamento', 'multistep-checkout'); ?></h2>
                <p class="msc-step-subtitle"><?php esc_html_e('Escolha como deseja pagar', 'multistep-checkout'); ?></p>
            </div>

            <div class="msc-step-content">
                
                <?php if (WC()->cart->needs_payment()) : ?>
                    <!-- IMPORTANTE: NÃO usar a classe "woocommerce-checkout-payment" aqui.
                         O wc-checkout.js nativo (forçado via force_wc_checkout_script) usa
                         essa classe como chave de fragment no AJAX de update_checkout e
                         substitui o elemento pelo checkout/payment.php padrão do WooCommerce
                         — que inclui SEU PRÓPRIO #place_order e #terms. Isso cria um par
                         duplicado (oculto, dentro do Step 3) que fica antes do nosso no DOM,
                         fazendo `$('#terms')`/`$('#place_order')` sempre resolverem para o
                         elemento errado e travando o botão do Step 4 mesmo com os termos
                         marcados. -->
                    <div id="payment">
                        <?php
                        // Renderizar métodos de pagamento diretamente
                        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
                        $chosen_gateway = WC()->session->get('chosen_payment_method');

                        // Com um único gateway ativo (caso comum: só a Rede), a lista
                        // genérica de métodos do WC (rádio + título) é suprimida via CSS
                        // e o payment_fields() do próprio gateway (as abas pixel-perfect
                        // Cartão/PIX/Boleto) passa a ser o conteúdo principal do Step 3.
                        // Com mais de um gateway habilitado, cai para a lista genérica.
                        $msc_single_gateway = count($available_gateways) === 1;
                        ?>
                        <ul class="wc_payment_methods payment_methods methods<?php echo $msc_single_gateway ? ' msc-payment-methods-single' : ''; ?>">
                            <?php
                            if (!empty($available_gateways)) {
                                foreach ($available_gateways as $gateway) {
                                    ?>
                                    <li class="wc_payment_method payment_method_<?php echo esc_attr($gateway->id); ?>">
                                        <input 
                                            id="payment_method_<?php echo esc_attr($gateway->id); ?>" 
                                            type="radio" 
                                            class="input-radio" 
                                            name="payment_method" 
                                            value="<?php echo esc_attr($gateway->id); ?>" 
                                            <?php checked($gateway->chosen, true); ?>
                                            data-order_button_text="<?php echo esc_attr($gateway->order_button_text); ?>"
                                        />

                                        <label for="payment_method_<?php echo esc_attr($gateway->id); ?>">
                                            <?php echo wp_kses_post($gateway->get_title()); ?>
                                            <?php echo wp_kses_post($gateway->get_icon()); ?>
                                        </label>
                                        
                                        <?php if ($gateway->has_fields() || $gateway->get_description()) : ?>
                                            <div class="payment_box payment_method_<?php echo esc_attr($gateway->id); ?>" 
                                                 <?php if (!$gateway->chosen) : ?>style="display:none;"<?php endif; ?>>
                                                <?php $gateway->payment_fields(); ?>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                    <?php
                                }
                            } else {
                                echo '<li class="woocommerce-notice woocommerce-notice--info">';
                                echo esc_html__('Desculpe, não há métodos de pagamento disponíveis.', 'woocommerce');
                                echo '</li>';
                            }
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>

            </div>

            <div class="msc-step-footer">
                <button type="button" class="msc-btn msc-btn-back" data-prev-step="2">
                    ← <?php esc_html_e('Voltar', 'multistep-checkout'); ?>
                </button>
                <button type="button" class="msc-btn msc-btn-next" data-next-step="4">
                    <?php esc_html_e('Revisar Pedido', 'multistep-checkout'); ?> →
                </button>
            </div>
        </div>

        <!-- ==========================================
            STEP 4: REVISÃO
        ========================================== -->
        <div class="msc-step msc-step-review" data-step="4" style="display: none;">
            <div class="msc-step-header">
                <h2 class="msc-step-title"><?php esc_html_e('Revisão do Pedido', 'multistep-checkout'); ?></h2>
                <p class="msc-step-subtitle"><?php esc_html_e('Confira todos os dados antes de finalizar', 'multistep-checkout'); ?></p>
            </div>

            <div class="msc-step-content">
                
                <!-- SEÇÃO 1: PRODUTOS -->
                <div class="msc-review-section msc-review-products">
                    <div class="msc-review-section-header">
                        <h3 class="msc-review-section-title">
                            <span class="msc-review-icon">📦</span>
                            <?php esc_html_e('Seus Produtos', 'multistep-checkout'); ?>
                        </h3>
                        <button type="button" class="msc-edit-btn" data-edit-step="1">
                            ✏️ <?php esc_html_e('Editar', 'multistep-checkout'); ?>
                        </button>
                    </div>
                    
                    <div class="msc-review-section-content">
                        <?php
                        // Obter grupos por CODLOCAL
                        $cart_groups = array();
                        if (function_exists('msc_group_cart_by_codlocal')) {
                            $cart_groups = msc_group_cart_by_codlocal();
                        }
                        
                        $has_multiple = count($cart_groups) > 1;
                        
                        if (empty($cart_groups)) {
                            // Fallback: lista simples
                            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                                $_product = $cart_item['data'];
                                if (!$_product || !$_product->exists()) continue;
                                ?>
                                <div class="msc-review-product-item">
                                    <div class="msc-review-product-image">
                                        <?php echo $_product->get_image('thumbnail'); ?>
                                    </div>
                                    <div class="msc-review-product-details">
                                        <h4 class="msc-review-product-name"><?php echo $_product->get_name(); ?></h4>
                                        <p class="msc-review-product-meta">
                                            <?php 
                                            printf(
                                                __('Quantidade: %s', 'multistep-checkout'),
                                                '<strong>' . $cart_item['quantity'] . '</strong>'
                                            );
                                            ?>
                                        </p>
                                    </div>
                                    <div class="msc-review-product-price">
                                        <?php echo WC()->cart->get_product_subtotal($_product, $cart_item['quantity']); ?>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            // Com agrupamento por CODLOCAL
                            foreach ($cart_groups as $codlocal => $group) {
                                if ($has_multiple) {
                                    // Mostrar cabeçalho do grupo apenas se houver múltiplos
                                    $location_names = array(
                                        '201' => 'Curitiba',
                                        '401' => 'Espírito Santo',
                                        'default' => 'Principal'
                                    );
                                    $display_name = isset($location_names[$codlocal]) ? $location_names[$codlocal] : $group['name'];
                                    ?>
                                    <div class="msc-review-codlocal-header">
                                        <span class="msc-review-codlocal-icon">📍</span>
                                        <strong><?php echo esc_html($display_name); ?></strong>
                                        <span class="msc-review-codlocal-badge"><?php echo esc_html($codlocal); ?></span>
                                    </div>
                                    <?php
                                }
                                
                                foreach ($group['items'] as $item) {
                                    $_product = $item['data'];
                                    $cart_item = $item['cart_item'];
                                    ?>
                                    <div class="msc-review-product-item">
                                        <div class="msc-review-product-image">
                                            <?php echo $_product->get_image('thumbnail'); ?>
                                        </div>
                                        <div class="msc-review-product-details">
                                            <h4 class="msc-review-product-name"><?php echo $_product->get_name(); ?></h4>
                                            <p class="msc-review-product-meta">
                                                <?php 
                                                printf(
                                                    __('Quantidade: %s', 'multistep-checkout'),
                                                    '<strong>' . $cart_item['quantity'] . '</strong>'
                                                );
                                                ?>
                                            </p>
                                        </div>
                                        <div class="msc-review-product-price">
                                            <?php echo WC()->cart->get_product_subtotal($_product, $cart_item['quantity']); ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- SEÇÃO 2: ENTREGA -->
                <div class="msc-review-section msc-review-shipping">
                    <div class="msc-review-section-header">
                        <h3 class="msc-review-section-title">
                            <span class="msc-review-icon">🚚</span>
                            <?php esc_html_e('Entrega', 'multistep-checkout'); ?>
                        </h3>
                        <button type="button" class="msc-edit-btn" data-edit-step="2">
                            ✏️ <?php esc_html_e('Editar', 'multistep-checkout'); ?>
                        </button>
                    </div>
                    
                    <div class="msc-review-section-content">
                        
                        <!-- Endereço de Entrega -->
                        <div class="msc-review-address">
                            <h4 class="msc-review-subsection-title">
                                <?php esc_html_e('Endereço de Entrega', 'multistep-checkout'); ?>
                            </h4>
                            <div class="msc-review-address-text">
                                <?php
                                $customer = WC()->customer;
                                $address_parts = array();
                                
                                if ($customer->get_shipping_address_1()) {
                                    $address_parts[] = $customer->get_shipping_address_1();
                                }
                                if ($customer->get_shipping_address_2()) {
                                    $address_parts[] = $customer->get_shipping_address_2();
                                }
                                if ($customer->get_shipping_city()) {
                                    $city_state = $customer->get_shipping_city();
                                    if ($customer->get_shipping_state()) {
                                        $city_state .= ' - ' . $customer->get_shipping_state();
                                    }
                                    $address_parts[] = $city_state;
                                }
                                if ($customer->get_shipping_postcode()) {
                                    $address_parts[] = 'CEP: ' . $customer->get_shipping_postcode();
                                }
                                
                                if (!empty($address_parts)) {
                                    echo '<p>' . implode('<br>', array_map('esc_html', $address_parts)) . '</p>';
                                } else {
                                    echo '<p class="msc-review-empty">' . esc_html__('Endereço não informado', 'multistep-checkout') . '</p>';
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Método de Frete -->
                        <div class="msc-review-shipping-method">
                            <h4 class="msc-review-subsection-title">
                                <?php esc_html_e('Método de Envio', 'multistep-checkout'); ?>
                            </h4>
                            <div class="msc-review-shipping-methods">
                                <?php
                                $packages = WC()->shipping()->get_packages();
                                $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
                                $shipping_handler = class_exists('MSC_Shipping_Packages_Handler') ? MSC_Shipping_Packages_Handler::instance() : null;

                                if (!empty($packages)) {
                                    foreach ($packages as $i => $package) {
                                        $chosen_method_id = isset($chosen_methods[$i]) ? $chosen_methods[$i] : '';
                                        $available_methods = isset($package['rates']) ? $package['rates'] : array();
                                        $package_name = isset($package['package_name']) ? $package['package_name'] : '';
                                        $display_methods = $shipping_handler ? $shipping_handler->get_display_methods($available_methods) : array();

                                        if ($chosen_method_id && isset($available_methods[$chosen_method_id])) {
                                            $method = $available_methods[$chosen_method_id];
                                            $display = isset($display_methods[$chosen_method_id]) ? $display_methods[$chosen_method_id] : null;
                                            $label = $display ? ($display['name'] . ' - ' . $display['eta']) : $method->get_label();
                                            $cost = $display ? $display['cost'] : $method->get_cost();
                                            ?>
                                            <div class="msc-review-shipping-item" data-package-index="<?php echo esc_attr($i); ?>">
                                                <?php if (!empty($package_name) && count($packages) > 1) : ?>
                                                    <span class="msc-review-shipping-package"><?php echo esc_html($package_name); ?>:</span>
                                                <?php endif; ?>
                                                <strong class="msc-review-shipping-label"><?php echo esc_html($label); ?></strong>
                                                <span class="msc-review-shipping-cost">
                                                    <?php
                                                    if ($cost > 0) {
                                                        echo wc_price($cost);
                                                    } else {
                                                        echo '<span style="color:#00a650;">Gratuito</span>';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                            <?php
                                        }
                                    }
                                } else {
                                    echo '<p class="msc-review-empty">' . esc_html__('Método de envio não selecionado', 'multistep-checkout') . '</p>';
                                }
                                ?>
                            </div>
                        </div>
                        
                    </div>
                </div>

                <!-- SEÇÃO 3: PAGAMENTO - VERSÃO CORRIGIDA -->
                <div class="msc-review-section msc-review-payment">
                    <div class="msc-review-section-header">
                        <h3 class="msc-review-section-title">
                            <span class="msc-review-icon">💳</span>
                            <?php esc_html_e('Pagamento', 'multistep-checkout'); ?>
                        </h3>
                        <button type="button" class="msc-edit-btn" data-edit-step="3">
                            ✏️ <?php esc_html_e('Editar', 'multistep-checkout'); ?>
                        </button>
                    </div>
                    
                    <div class="msc-review-section-content">
                        <?php
                        // 1. Obter gateway selecionado
                        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
                        $chosen_method = WC()->session->get('chosen_payment_method');
                        
                        if ($chosen_method && isset($available_gateways[$chosen_method])) {
                            $gateway = $available_gateways[$chosen_method];
                            
                            // 2. DETECTAR MÉTODO DO PAGARME
                            $pagarme_method = 'credit_card'; // Default
                            $pagarme_method_data = array(
                                'icon' => '💳',
                                'name' => 'Cartão de Crédito',
                                'desc' => 'Pague com segurança usando seu cartão de crédito',
                                'details' => array()
                            );
                            
                            // Se for o gateway Pagarme, tentar detectar qual método
                            if ($gateway->id === 'pagarme_gateway') {
                                
                                // ESTRATÉGIA 1: Ler da sessão customizada (se implementado)
                                $session_method = WC()->session->get('pagarme_payment_method');
                                if ($session_method) {
                                    $pagarme_method = $session_method;
                                }
                                
                                // ESTRATÉGIA 2: Ler via JavaScript inline
                                ?>
                                <script>
                                (function() {
                                    var detectedMethod = null;
                                    
                                    // Verificar sessionStorage
                                    try {
                                        var savedData = sessionStorage.getItem('pagarme_payment_data_v2');
                                        if (savedData) {
                                            var data = JSON.parse(savedData);
                                            detectedMethod = data.payment_method;
                                                // Esconder todos
                                                $('.payment-method-display').hide();
                                                
                                                // Mostrar apenas o selecionado
                                                $('.payment-method-display[data-method="' + method + '"]').show();
                                        }
                                    } catch(e) {}
                                    
                                    // Se não encontrou, verificar radio marcado
                                    if (!detectedMethod) {
                                        var checked = document.querySelector('input[name="pagarme_payment_method"]:checked');
                                        if (checked) {
                                            detectedMethod = checked.value;
                                        }
                                    }
                                    
                                    // Se detectou, salvar em um campo hidden
                                    if (detectedMethod) {
                                        var input = document.getElementById('detected_pagarme_method');
                                        if (!input) {
                                            input = document.createElement('input');
                                            input.type = 'hidden';
                                            input.id = 'detected_pagarme_method';
                                            input.name = '_detected_pagarme_method';
                                            document.body.appendChild(input);
                                        }
                                        input.value = detectedMethod;
                                        
                                        // Atualizar exibição imediatamente
                                        window.pagarmeDetectedMethod = detectedMethod;
                                    }
                                })();
                                </script>
                                <?php
                                
                                // ESTRATÉGIA 3: Definir os dados de cada método
                                $methods_data = array(
                                    'boleto' => array(
                                        'icon' => '📄',
                                        'name' => 'Boleto Bancário',
                                        'desc' => 'Vencimento em ' . $gateway->get_option('boleto_days_to_pay', '3') . ' dias após a confirmação',
                                        'details' => array(
                                            'O boleto será enviado por e-mail',
                                            'Aprovação em até 2 dias úteis após o pagamento'
                                        )
                                    ),
                                    'pix' => array(
                                        'icon' => '',
                                        'name' => 'PIX',
                                        'desc' => 'Pagamento instantneo via PIX',
                                        'details' => array(
                                            'QR Code válido por ' . $gateway->get_option('pix_expiration', '30') . ' minutos',
                                            'Aprovação em até 2 minutos'
                                        )
                                    ),
                                    'credit_card' => array(
                                        'icon' => '💳',
                                        'name' => 'Cartão de Crédito',
                                        'desc' => 'Pague com segurança usando seu cartão de crédito',
                                        'details' => array(
                                            'Parcele em até ' . $gateway->get_option('max_installments', '12') . 'x'
                                        )
                                    )
                                );
                                
                                // Usar método detectado
                                if (isset($methods_data[$pagarme_method])) {
                                    $pagarme_method_data = $methods_data[$pagarme_method];
                                }
                                
                                // EXIBIR TODOS OS MÉTODOS COM ATRIBUTOS DATA
                                echo '<div class="msc-payment-methods-review">';
                                
                                foreach ($methods_data as $method_id => $method_data) {
                                    $is_active = ($method_id === $pagarme_method) ? 'active' : '';
                                    $display_style = ($method_id === $pagarme_method) ? '' : 'style="display:none;"';
                                    
                                    echo sprintf(
                                        '<div class="msc-review-payment-method pagarme-method-%s %s" data-method="%s" %s>',
                                        esc_attr($method_id),
                                        esc_attr($is_active),
                                        esc_attr($method_id),
                                        $display_style
                                    );
                                    
                                    echo '<div class="msc-review-payment-icon">';
                                    echo esc_html($method_data['icon']);
                                    echo '</div>';
                                    
                                    echo '<div class="msc-review-payment-details">';
                                    echo '<strong>' . esc_html($method_data['name']) . '</strong>';
                                    echo '<p class="msc-review-payment-desc">' . esc_html($method_data['desc']) . '</p>';
                                    
                                    if (!empty($method_data['details'])) {
                                        echo '<ul class="msc-payment-extra-info">';
                                        foreach ($method_data['details'] as $detail) {
                                            echo '<li>' . esc_html($detail) . '</li>';
                                        }
                                        echo '</ul>';
                                    }
                                    
                                    echo '</div>';
                                    echo '</div>';
                                }
                                
                                echo '</div>';
                                
                                // SCRIPT PARA MOSTRAR/ESCONDER BASEADO NO MÉTODO DETECTADO
                                ?>
                                <script>
                                jQuery(document).ready(function($) {
                                    function showCorrectPaymentMethod() {
                                        var method = window.pagarmeDetectedMethod || 'credit_card';
                                        
                                        console.log('Exibindo método no Step 4:', method);
                                        
                                        // Esconder todos
                                        $('.msc-review-payment-method').hide().removeClass('active');
                                        
                                        // Mostrar o correto
                                        $('.msc-review-payment-method.pagarme-method-' + method)
                                            .show()
                                            .addClass('active');
                                    }
                                    
                                    // Executar imediatamente
                                    showCorrectPaymentMethod();
                                    
                                    // Executar novamente quando o Step 4 for exibido
                                    $(document).on('step_shown', function(e, stepNumber) {
                                        if (stepNumber === 4) {
                                            setTimeout(showCorrectPaymentMethod, 100);
                                        }
                                    });
                                    
                                    // Também executar quando voltar do Step 3
                                    $(document).on('payment_method_updated', function() {
                                        setTimeout(showCorrectPaymentMethod, 100);
                                    });
                                });
                                </script>
                                <?php
                                
                            } else {
                                // Gateway que não é Pagarme (ex: Rede) — este bloco é só o
                                // fallback estático (ícone + título + descrição). O conteúdo
                                // rico (cartão mascarado, bandeira, parcelas, PIX/Boleto,
                                // split) é responsabilidade única de
                                // payment-step4-sync-fix.js (Step4Sync.updateStep4Display()),
                                // que sobrescreve .msc-review-section-content assim que o
                                // cliente chega ou atualiza o Step 4.
                                ?>
                                <div class="msc-review-payment-method">
                                    <div class="msc-review-payment-icon">
                                        <?php 
                                        $icon_html = '';
                                        if (method_exists($gateway, 'get_icon')) {
                                            $icon_html = $gateway->get_icon();
                                        }
                                        
                                        if (!empty($icon_html)) {
                                            echo wp_kses_post($icon_html);
                                        } else {
                                            echo '';
                                        }
                                        ?>
                                    </div>
                                    <div class="msc-review-payment-details">
                                        <strong><?php echo esc_html($gateway->get_title()); ?></strong>
                                        <?php if ($gateway->get_description()) : ?>
                                            <p class="msc-review-payment-desc"><?php echo wp_kses_post($gateway->get_description()); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<p class="msc-review-empty">' . esc_html__('Método de pagamento não selecionado', 'multistep-checkout') . '</p>';
                        }
                        ?>
                    </div>
                </div>
                                
                <!-- SEÇÃO 4: TOTAIS -->
                <div class="msc-review-section msc-review-totals">
                    <div class="msc-review-section-header">
                        <h3 class="msc-review-section-title">
                            <span class="msc-review-icon">💰</span>
                            <?php esc_html_e('Resumo de Valores', 'multistep-checkout'); ?>
                        </h3>
                    </div>
                    
                    <div class="msc-review-section-content">
                        <div class="msc-review-totals-table">
                            
                            <?php
                            // Forçar recálculo
                            WC()->cart->calculate_totals();
                            
                            // Calcular frete total
                            $shipping_total = 0;
                            $packages = WC()->shipping()->get_packages();
                            $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
                            
                            if (!empty($packages)) {
                                foreach ($packages as $i => $package) {
                                    $chosen_method_id = isset($chosen_methods[$i]) ? $chosen_methods[$i] : '';
                                    $available_methods = isset($package['rates']) ? $package['rates'] : array();
                                    
                                    if ($chosen_method_id && isset($available_methods[$chosen_method_id])) {
                                        $method = $available_methods[$chosen_method_id];
                                        $shipping_total += (float) $method->get_cost();
                                    }
                                }
                            }
                            
                            // Fallback
                            if ($shipping_total <= 0) {
                                $shipping_total = (float) WC()->cart->get_shipping_total() + (float) WC()->cart->get_shipping_tax();
                            }
                            
                            // Calcular total final
                            $cart_subtotal = (float) WC()->cart->get_subtotal();
                            $discount_total = (float) WC()->cart->get_discount_total();
                            $final_total = $cart_subtotal + $shipping_total - $discount_total;
                            ?>
                            
                            <!-- Subtotal -->
                            <div class="msc-review-total-row">
                                <span><?php esc_html_e('Subtotal', 'multistep-checkout'); ?></span>
                                <strong class="msc-review-subtotal-value">
                                    <span><?php echo wc_price($cart_subtotal); ?></span>
                                </strong>
                            </div>

                            <!-- Frete -->
                            <div class="msc-review-total-row">
                                <span><?php esc_html_e('Frete', 'multistep-checkout'); ?></span>
                                <strong class="msc-review-shipping-total">
                                    <?php
                                    if ($shipping_total > 0) {
                                        echo '<span>' . wc_price($shipping_total) . '</span>';
                                    } else {
                                        echo '<span class="msc-review-free-shipping">Gratuito</span>';
                                    }
                                    ?>
                                </strong>
                            </div>

                            <!-- Desconto -->
                            <?php if ($discount_total > 0) : ?>
                                <div class="msc-review-total-row msc-review-discount">
                                    <span><?php esc_html_e('Desconto', 'multistep-checkout'); ?></span>
                                    <strong>
                                        <span>-<?php echo wc_price($discount_total); ?></span>
                                    </strong>
                                </div>
                            <?php endif; ?>

                            <!-- Total -->
                            <div class="msc-review-total-row msc-review-total-final">
                                <span><?php esc_html_e('Total', 'multistep-checkout'); ?></span>
                                <strong class="msc-review-final-total">
                                    <span><?php echo wc_price($final_total); ?></span>
                                </strong>
                            </div>
                                                  
                        </div>
                    </div>
                </div>

                <!-- SEÇÃO 5: TERMOS E CONDIÇÕES -->
                <div class="msc-review-section msc-review-terms">
                    <div class="msc-review-section-content">
                        
                        <?php do_action('woocommerce_checkout_before_terms_and_conditions'); ?>
                        
                        <div class="woocommerce-terms-and-conditions-wrapper">
                            <?php do_action('woocommerce_checkout_terms_and_conditions'); ?>
                            
                            <p class="form-row terms wc-terms-and-conditions">
                                <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                                    <input type="checkbox" 
                                        class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" 
                                        name="terms" 
                                        <?php checked(apply_filters('woocommerce_terms_is_checked_default', isset($_POST['terms'])), true); ?> 
                                        id="terms" />
                                    <span class="woocommerce-terms-and-conditions-checkbox-text">
                                        <?php
                                        $terms_page_id = wc_terms_and_conditions_page_id();
                                        if ($terms_page_id) {
                                            echo wp_kses_post(sprintf(__('Eu li e concordo com os %s', 'woocommerce'), '<a href="' . esc_url(get_permalink($terms_page_id)) . '" class="woocommerce-terms-and-conditions-link" target="_blank">' . esc_html__('termos e condições', 'woocommerce') . '</a>'));
                                        } else {
                                            esc_html_e('Eu li e concordo com os termos e condições', 'woocommerce');
                                        }
                                        ?>
                                        <span class="required">*</span>
                                    </span>
                                </label>
                                <input type="hidden" name="terms-field" value="1" />
                            </p>
                        </div>
                        
                        <?php do_action('woocommerce_checkout_after_terms_and_conditions'); ?>
                        
                    </div>
                </div>

            </div>

            <div class="msc-step-footer">
                <button type="button" class="msc-btn msc-btn-back" data-prev-step="3">
                    ← <?php esc_html_e('Voltar', 'multistep-checkout'); ?>
                </button>
                
                <?php 
                $order_button_text = apply_filters('woocommerce_order_button_text', __('Finalizar Pedido', 'woocommerce'));
                ?>
                <button type="submit" 
                        class="msc-btn msc-btn-submit" 
                        name="woocommerce_checkout_place_order" 
                        id="place_order" 
                        value="<?php echo esc_attr($order_button_text); ?>" 
                        data-value="<?php echo esc_attr($order_button_text); ?>">
                    <?php echo esc_html($order_button_text); ?>
                </button>
            </div>
        </div>

      </div>

      <!-- Sidebar: Resumo -->
      <aside class="msc-sidebar">
        <div class="msc-summary-card">
          <h3 class="msc-summary-title"><?php esc_html_e('Resumo', 'multistep-checkout'); ?></h3>
          
          <div class="msc-summary-stats">
            
            <div class="msc-summary-row">
              <span><?php esc_html_e('Subtotal', 'multistep-checkout'); ?></span>
              <span class="msc-subtotal-value"><?php echo $cart->get_cart_subtotal(); ?></span>
            </div>
            
            <div class="msc-summary-row">
              <span><?php esc_html_e('Frete', 'multistep-checkout'); ?></span>
              <span class="msc-shipping-value" data-calculating-text="<?php esc_attr_e('A calcular', 'multistep-checkout'); ?>">
                <?php 
                $shipping = $cart->get_shipping_total();
                if ($shipping > 0) {
                    echo wc_price($shipping);
                } else {
                    echo '<span style="color: #999;">' . __('A calcular', 'multistep-checkout') . '</span>';
                }
                ?>
              </span>
            </div>
            
            <?php if ($cart->get_discount_total() > 0) : ?>
            <div class="msc-summary-row msc-discount-row">
              <span><?php esc_html_e('Desconto', 'multistep-checkout'); ?></span>
              <span class="msc-discount-value" style="color: #00a650;">
                -<?php echo wc_price($cart->get_discount_total()); ?>
              </span>
            </div>
            <?php endif; ?>
            
            <div class="msc-summary-row msc-summary-total">
              <span><?php esc_html_e('Total', 'multistep-checkout'); ?></span>
              <span class="msc-total-value"><?php echo $cart->get_total(); ?></span>
            </div>
            
          </div>
          
          <div class="msc-summary-info">
            <p class="msc-summary-secure">
              <span style="color: #00a650;"></span>
              <?php esc_html_e('Pagamento seguro', 'multistep-checkout'); ?>
            </p>
          </div>
          
        </div>
      </aside>

    </div>

    <!-- REVIEW TABLE OCULTA (apenas para cálculos, sem métodos de pagamento) -->
    <div id="msc-hidden-review-wrapper" style="position: absolute; left: -99999px; visibility: hidden; pointer-events: none;">
        <div id="order_review" class="woocommerce-checkout-review-order">
            <table class="shop_table woocommerce-checkout-review-order-table">
                <thead>
                    <tr>
                        <th class="product-name"><?php esc_html_e('Product', 'woocommerce'); ?></th>
                        <th class="product-total"><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    do_action('woocommerce_review_order_before_cart_contents');

                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                        $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);

                        if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key)) {
                            ?>
                            <tr class="<?php echo esc_attr(apply_filters('woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key)); ?>">
                                <td class="product-name">
                                    <?php echo wp_kses_post(apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key)) . '&nbsp;'; ?>
                                    <?php echo apply_filters('woocommerce_checkout_cart_item_quantity', ' <strong class="product-quantity">' . sprintf('&times;&nbsp;%s', $cart_item['quantity']) . '</strong>', $cart_item, $cart_item_key); ?>
                                    <?php echo wc_get_formatted_cart_item_data($cart_item); ?>
                                </td>
                                <td class="product-total">
                                    <?php echo apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key); ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }

                    do_action('woocommerce_review_order_after_cart_contents');
                    ?>
                </tbody>
                <tfoot>
                    <tr class="cart-subtotal">
                        <th><?php esc_html_e('Subtotal', 'woocommerce'); ?></th>
                        <td><?php wc_cart_totals_subtotal_html(); ?></td>
                    </tr>

                    <?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
                        <tr class="cart-discount coupon-<?php echo esc_attr(sanitize_title($code)); ?>">
                            <th><?php wc_cart_totals_coupon_label($coupon); ?></th>
                            <td><?php wc_cart_totals_coupon_html($coupon); ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>
                        <?php do_action('woocommerce_review_order_before_shipping'); ?>
                        <?php wc_cart_totals_shipping_html(); ?>
                        <?php do_action('woocommerce_review_order_after_shipping'); ?>
                    <?php endif; ?>

                    <?php foreach (WC()->cart->get_fees() as $fee) : ?>
                        <tr class="fee">
                            <th><?php echo esc_html($fee->name); ?></th>
                            <td><?php wc_cart_totals_fee_html($fee); ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) : ?>
                        <?php if ('itemized' === get_option('woocommerce_tax_total_display')) : ?>
                            <?php foreach (WC()->cart->get_tax_totals() as $code => $tax) : ?>
                                <tr class="tax-rate tax-rate-<?php echo esc_attr(sanitize_title($code)); ?>">
                                    <th><?php echo esc_html($tax->label); ?></th>
                                    <td><?php echo wp_kses_post($tax->formatted_amount); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr class="tax-total">
                                <th><?php echo esc_html(WC()->countries->tax_or_vat()); ?></th>
                                <td><?php wc_cart_totals_taxes_total_html(); ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php do_action('woocommerce_review_order_before_order_total'); ?>

                    <tr class="order-total">
                        <th><?php esc_html_e('Total', 'woocommerce'); ?></th>
                        <td><?php wc_cart_totals_order_total_html(); ?></td>
                    </tr>

                    <?php do_action('woocommerce_review_order_after_order_total'); ?>
                </tfoot>
            </table>
        </div>
    </div>

    <?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce'); ?>
    
  </form>

</div>

<?php do_action('woocommerce_after_checkout_form', $checkout); ?>

<!-- Scripts de Sincronização -->
<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Sincronização de Valores de Frete
    window.MSCShippingSync = {
        
        // Atualizar valores de frete no Step 4 quando mudanças ocorrem
        updateReviewShipping: function() {
            console.log('Sincronizando valores de frete no Step 4');
            
            // Para cada método de frete selecionado
            $('input[name^="shipping_method"]:checked').each(function() {
                const $input = $(this);
                const packageIndex = $input.data('index');
                const $label = $input.closest('li').find('.msc-method-cost');
                
                if ($label.length) {
                    const costHtml = $label.html();
                    
                    // Atualizar no Step 4
                    $('.msc-review-shipping-item[data-package-index="' + packageIndex + '"] .msc-review-shipping-cost').html(costHtml);
                }
            });
            
            // Recalcular total de frete
            this.recalculateShippingTotal();
        },
        
        // Recalcular total de frete
        recalculateShippingTotal: function() {
            let total = 0;
            
            $('.msc-review-shipping-item .msc-review-shipping-cost .amount').each(function() {
                const text = $(this).text().replace(/[^\d,]/g, '').replace(',', '.');
                const value = parseFloat(text) || 0;
                total += value;
            });
            
                // Atualizar na seção de totais
                if (total > 0) {
                    const formatted = total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    // CORREÇÃO: Adicionar style inline para garantir #333
                    $('.msc-review-shipping-total').html(
                        '<span class="woocommerce-Price-amount amount" style="color:#333!important">' +
                        '<bdi style="color:#333!important">' +
                        '<span class="woocommerce-Price-currencySymbol" style="color:#333!important">R$</span>' +
                        '&nbsp;' + formatted +
                        '</bdi></span>'
                    ).css('color', '#333');
                } else {
                    $('.msc-review-shipping-total').html('<span style="color:#00a650;">Gratuito</span>');
                }
            
            // Recalcular total final
            this.recalculateFinalTotal();
        },
        
        // Recalcular total final
        recalculateFinalTotal: function() {
            const subtotal = this.parsePrice($('.msc-review-subtotal-value').text());
            const shipping = this.parsePrice($('.msc-review-shipping-total').text());
            const discount = this.parsePrice($('.msc-review-discount strong').text());
            
            const total = subtotal + shipping - discount;
            
            if (total > 0) {
                const formatted = total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                // CORREÇÃO: Estrutura igual ao Subtotal (sem wrapper extra) + cor branca inline
                $('.msc-review-final-total').html(
                    '<bdi style="color:white!important">' +
                    '<span class="woocommerce-Price-currencySymbol" style="color:white!important">R$</span>' +
                    '&nbsp;' + formatted +
                    '</bdi>'
                ).css('color', 'white');
            }
        },
        
        // Parse de preço
        parsePrice: function(text) {
            if (!text) return 0;
            const clean = text.replace(/[^\d,]/g, '').replace(',', '.');
            return parseFloat(clean) || 0;
        }
    };
    
    // Event Listeners
    
    // Quando entrar no Step 3 (Pagamento)
    $(document.body).on('msc:enterStep:3', function() {
        console.log('Inicializando Step 3 - Pagamento');
        
        // Mostrar payment box do método selecionado
        const $selectedMethod = $('input[name="payment_method"]:checked');
        if ($selectedMethod.length) {
            $('.payment_box').hide();
            $('.payment_box.payment_method_' + $selectedMethod.val()).show();
        }
        
        // Trigger para plugins de pagamento
        $(document.body).trigger('init_checkout');
        
        // Se tiver Pagarme, forçar atualização
        if (typeof window.wc_pagarme_update_checkout === 'function') {
            setTimeout(function() {
                window.wc_pagarme_update_checkout();
            }, 500);
        }
    });
    
    // Quando mudar método de pagamento
    $(document).on('change', 'input[name="payment_method"]', function() {
        const methodId = $(this).val();
        
        $('.payment_box').slideUp(200);
        $('.payment_box.payment_method_' + methodId).slideDown(200);
        
        // Trigger para plugins
        $(document.body).trigger('payment_method_selected');
    });
    
    // Quando mudar método de frete
    $(document).on('change', 'input[name^="shipping_method"]', function() {
        setTimeout(function() {
            window.MSCShippingSync.updateReviewShipping();
        }, 500);
    });
    
    // Quando entrar no Step 4
    $(document.body).on('msc:enterStep:4', function() {
        setTimeout(function() {
            window.MSCShippingSync.updateReviewShipping();
        }, 500);
    });
    
    // Após atualização do checkout
    $(document.body).on('updated_checkout', function() {
        if ($('.msc-step[data-step="4"]').hasClass('active')) {
            setTimeout(function() {
                window.MSCShippingSync.updateReviewShipping();
            }, 500);
        }
    });
    
});
</script>
