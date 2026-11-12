<?php
/**
 * Template: Confirmação (Step 5) - Cliente8
 * Arquivo: templates/checkout/msc-thankyou.php
 *
 * Substitui checkout/thankyou.php via o mesmo filtro wc_get_template que
 * já troca checkout/form-checkout.php pelo template multistep. WooCommerce
 * passa $order já extraído dos args (pode ser false se o pedido não for
 * encontrado/válido).
 */

if (!defined('ABSPATH')) exit;

do_action('woocommerce_before_thankyou', $order ? $order->get_id() : 0);
?>

<div class="msc-confirmation-wrapper">
  <div class="msc-confirmation-card">

    <?php if ($order && $order->has_status('failed')) : ?>

      <div class="msc-confirmation-icon msc-confirmation-icon-failed">✕</div>
      <h1 class="msc-confirmation-title"><?php esc_html_e('Não foi possível confirmar o pagamento', 'multistep-checkout'); ?></h1>
      <p class="msc-confirmation-order-number">
        <?php printf(esc_html__('Pedido #%s', 'multistep-checkout'), esc_html($order->get_order_number())); ?>
      </p>
      <p class="msc-confirmation-desc">
        <?php esc_html_e('Você pode tentar pagar novamente ou usar outro método de pagamento.', 'multistep-checkout'); ?>
      </p>
      <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" class="msc-btn msc-btn-submit msc-confirmation-cta">
        <?php esc_html_e('Tentar novamente', 'multistep-checkout'); ?>
      </a>

    <?php elseif ($order) : ?>

      <div class="msc-confirmation-icon">✓</div>
      <h1 class="msc-confirmation-title"><?php esc_html_e('Pedido confirmado!', 'multistep-checkout'); ?></h1>
      <p class="msc-confirmation-order-number">
        <?php printf(esc_html__('Pedido #%s', 'multistep-checkout'), esc_html($order->get_order_number())); ?>
      </p>

      <div class="msc-confirmation-details">
        <?php
        // Ponto de extensão padrão do WooCommerce — é aqui que o gateway
        // Rede (WC_Rede_Gateway::thankyou_page, já hookado em
        // woocommerce_thankyou) desenha o QR Code do PIX quando aplicável.
        // Qualquer outro gateway/plugin hookado em woocommerce_thankyou
        // também renderiza normalmente dentro deste painel.
        do_action('woocommerce_thankyou', $order->get_id());
        ?>
      </div>

      <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="msc-btn msc-btn-submit msc-confirmation-cta">
        <?php esc_html_e('Voltar para a loja', 'multistep-checkout'); ?>
      </a>

    <?php else : ?>

      <div class="msc-confirmation-icon msc-confirmation-icon-failed">✕</div>
      <h1 class="msc-confirmation-title"><?php esc_html_e('Pedido não encontrado', 'multistep-checkout'); ?></h1>
      <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="msc-btn msc-btn-submit msc-confirmation-cta">
        <?php esc_html_e('Voltar para a loja', 'multistep-checkout'); ?>
      </a>

    <?php endif; ?>

  </div>
</div>
