<?php
/**
 * MSC Fields Helper - Correção de Campos do Checkout
 * Arquivo: includes/msc-fields-helper.php
 * 
 * FUNÇÃO:
 * - Corrigir labels dos campos de endereço (APENAS PHP, sem JS)
 * - address_2 = Complemento (opcional)
 * - neighborhood = Bairro
 * - RESPEITA premissa: não manipula DOM, apenas filtros nativos WC
 */

if (!defined('ABSPATH')) exit;

/**
 * PRIORIDADE MÁXIMA: Executar DEPOIS de todos os outros plugins
 */
add_filter('woocommerce_checkout_fields', 'msc_fix_checkout_fields_final', PHP_INT_MAX - 1);
function msc_fix_checkout_fields_final($fields) {
    
    // BILLING
    if (isset($fields['billing']['billing_address_2'])) {
        $fields['billing']['billing_address_2']['label'] = 'Complemento';
        $fields['billing']['billing_address_2']['placeholder'] = 'Apartamento, sala, bloco (opcional)';
        $fields['billing']['billing_address_2']['required'] = false;
    }
    
    if (isset($fields['billing']['billing_neighborhood'])) {
        $fields['billing']['billing_neighborhood']['label'] = 'Bairro';
        $fields['billing']['billing_neighborhood']['placeholder'] = 'Nome do bairro';
    }
    
    // SHIPPING
    if (isset($fields['shipping']['shipping_address_2'])) {
        $fields['shipping']['shipping_address_2']['label'] = 'Complemento';
        $fields['shipping']['shipping_address_2']['placeholder'] = 'Apartamento, sala, bloco (opcional)';
        $fields['shipping']['shipping_address_2']['required'] = false;
    }
    
    if (isset($fields['shipping']['shipping_neighborhood'])) {
        $fields['shipping']['shipping_neighborhood']['label'] = 'Bairro';
        $fields['shipping']['shipping_neighborhood']['placeholder'] = 'Nome do bairro';
    }
    
    return $fields;
}

/**
 * FALLBACK: Aplicar também nos filtros individuais
 */
add_filter('woocommerce_billing_fields', 'msc_fix_billing_fields_final', PHP_INT_MAX - 1);
function msc_fix_billing_fields_final($fields) {
    
    if (isset($fields['billing_address_2'])) {
        $fields['billing_address_2']['label'] = 'Complemento';
        $fields['billing_address_2']['placeholder'] = 'Apartamento, sala, bloco (opcional)';
        $fields['billing_address_2']['required'] = false;
    }
    
    if (isset($fields['billing_neighborhood'])) {
        $fields['billing_neighborhood']['label'] = 'Bairro';
        $fields['billing_neighborhood']['placeholder'] = 'Nome do bairro';
    }
    
    return $fields;
}

add_filter('woocommerce_shipping_fields', 'msc_fix_shipping_fields_final', PHP_INT_MAX - 1);
function msc_fix_shipping_fields_final($fields) {
    
    if (isset($fields['shipping_address_2'])) {
        $fields['shipping_address_2']['label'] = 'Complemento';
        $fields['shipping_address_2']['placeholder'] = 'Apartamento, sala, bloco (opcional)';
        $fields['shipping_address_2']['required'] = false;
    }
    
    if (isset($fields['shipping_neighborhood'])) {
        $fields['shipping_neighborhood']['label'] = 'Bairro';
        $fields['shipping_neighborhood']['placeholder'] = 'Nome do bairro';
    }
    
    return $fields;
}

/**
 * ÚLTIMO RECURSO: Hook que executa IMEDIATAMENTE antes de renderizar
 */
add_action('woocommerce_checkout_before_customer_details', 'msc_override_field_args', 1);
function msc_override_field_args() {
    
    add_filter('woocommerce_form_field_args', 'msc_modify_form_field_args', 99999, 3);
}

function msc_modify_form_field_args($args, $key, $value) {
    
    // Corrigir address_2 para Complemento
    if ($key === 'billing_address_2' || $key === 'shipping_address_2') {
        $args['label'] = 'Complemento';
        $args['placeholder'] = 'Apartamento, sala, bloco (opcional)';
        $args['required'] = false;
    }
    
    // Manter neighborhood como Bairro
    if ($key === 'billing_neighborhood' || $key === 'shipping_neighborhood') {
        $args['label'] = 'Bairro';
        $args['placeholder'] = 'Nome do bairro';
    }
    
    return $args;
}

/**
 * Validação do bairro
 */
add_action('woocommerce_checkout_process', 'msc_validate_neighborhood_field');
function msc_validate_neighborhood_field() {
    
    if (empty($_POST['billing_neighborhood'])) {
        $billing_fields = WC()->checkout()->get_checkout_fields('billing');
        
        if (isset($billing_fields['billing_neighborhood']) && 
            !empty($billing_fields['billing_neighborhood']['required'])) {
            
            wc_add_notice('Por favor, informe o bairro de cobrança.', 'error');
        }
    }
    
    if (!empty($_POST['ship_to_different_address']) && empty($_POST['shipping_neighborhood'])) {
        $shipping_fields = WC()->checkout()->get_checkout_fields('shipping');
        
        if (isset($shipping_fields['shipping_neighborhood']) && 
            !empty($shipping_fields['shipping_neighborhood']['required'])) {
            
            wc_add_notice('Por favor, informe o bairro de entrega.', 'error');
        }
    }
}

/**
 * Salvar campos no pedido
 */
add_action('woocommerce_checkout_update_order_meta', 'msc_save_custom_checkout_fields', 10, 1);
function msc_save_custom_checkout_fields($order_id) {
    
    if (!empty($_POST['billing_neighborhood'])) {
        update_post_meta($order_id, '_billing_neighborhood', sanitize_text_field($_POST['billing_neighborhood']));
    }
    
    if (!empty($_POST['billing_address_2'])) {
        update_post_meta($order_id, '_billing_address_2', sanitize_text_field($_POST['billing_address_2']));
    }
    
    if (!empty($_POST['ship_to_different_address'])) {
        
        if (!empty($_POST['shipping_neighborhood'])) {
            update_post_meta($order_id, '_shipping_neighborhood', sanitize_text_field($_POST['shipping_neighborhood']));
        }
        
        if (!empty($_POST['shipping_address_2'])) {
            update_post_meta($order_id, '_shipping_address_2', sanitize_text_field($_POST['shipping_address_2']));
        }
    } else {
        if (!empty($_POST['billing_neighborhood'])) {
            update_post_meta($order_id, '_shipping_neighborhood', sanitize_text_field($_POST['billing_neighborhood']));
        }
        
        if (!empty($_POST['billing_address_2'])) {
            update_post_meta($order_id, '_shipping_address_2', sanitize_text_field($_POST['billing_address_2']));
        }
    }
}

/**
 * Exibir no admin
 */
add_filter('woocommerce_admin_billing_fields', 'msc_admin_billing_fields');
function msc_admin_billing_fields($fields) {
    
    $fields['neighborhood'] = array(
        'label' => 'Bairro',
        'show' => true,
    );
    
    $fields['address_2'] = array(
        'label' => 'Complemento',
        'show' => true,
    );
    
    return $fields;
}

add_filter('woocommerce_admin_shipping_fields', 'msc_admin_shipping_fields');
function msc_admin_shipping_fields($fields) {
    
    $fields['neighborhood'] = array(
        'label' => 'Bairro',
        'show' => true,
    );
    
    $fields['address_2'] = array(
        'label' => 'Complemento',
        'show' => true,
    );
    
    return $fields;
}

/**
 * Formatar endereço
 */
add_filter('woocommerce_localisation_address_formats', 'msc_address_format');
function msc_address_format($formats) {
    $formats['BR'] = "{name}\n{company}\n{address_1}, {number}\n{address_2}\n{neighborhood}\n{city}, {state}\n{postcode}\n{country}";
    return $formats;
}

add_filter('woocommerce_formatted_address_replacements', 'msc_address_replacements', 10, 2);
function msc_address_replacements($replacements, $args) {
    $replacements['{neighborhood}'] = !empty($args['neighborhood']) ? $args['neighborhood'] : '';
    $replacements['{number}'] = !empty($args['number']) ? $args['number'] : '';
    return $replacements;
}
