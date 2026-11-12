/**
 * Multistep Checkout v4.6 - VERSÃO CORRIGIDA COMPLETA
 * Todas as funções corretamente definidas no objeto MSC
 */

(function($) {
  'use strict';

  const MSC = {
    currentStep: 1,
    totalSteps: 4,
    _isUpdating: false,

    init() {
      console.log('MSC v4.6: Inicializando com debug de seletores');

      this.bindEvents();
      this.initWooCommerceIntegration();
      this.setupValidation();
      this.fixFieldLabels();
      this.updateSubmitButtonState();
      this.initShippingFullName();

      console.log('MSC v4.6: Inicializado');
    },

    // O design simplifica "Nome de entrega" para um único campo, mas o
    // WooCommerce continua exigindo shipping_first_name/shipping_last_name
    // separados (validação, dados do pedido). Mantemos os dois campos reais
    // no DOM (só ocultos via CSS) e sincronizamos com esse campo visível:
    // ao digitar, divide no primeiro espaço; ao carregar, junta os dois.
    initShippingFullName() {
      const $fullName = $('#msc_shipping_full_name');
      if (!$fullName.length || $fullName.is(':focus')) return;

      const first = $('#shipping_first_name').val() || '';
      const last = $('#shipping_last_name').val() || '';
      $fullName.val((first + ' ' + last).trim());
    },

    splitShippingFullName(value) {
      const trimmed = (value || '').trim();
      const spaceIndex = trimmed.indexOf(' ');
      if (spaceIndex === -1) {
        return { first: trimmed, last: '' };
      }
      return {
        first: trimmed.slice(0, spaceIndex),
        last: trimmed.slice(spaceIndex + 1).trim(),
      };
    },

    // Trava/libera o botão "Finalizar Compra" conforme o checkbox de
    // termos — puramente visual, a validação real de submit continua
    // sendo feita pelo WooCommerce/checkout.js normalmente.
    //
    // Escopado ao Step 4 de propósito: o wc-checkout.js nativo substitui
    // fragments com a classe "woocommerce-checkout-payment" a cada
    // update_checkout, o que pode injetar um #place_order/#terms extra
    // (oculto) em outro ponto do DOM. Um `$('#terms')` sem escopo pegaria
    // esse duplicado em vez do checkbox real que o cliente marcou,
    // deixando o botão travado mesmo com os termos aceitos.
    updateSubmitButtonState() {
      const $step4 = $('.msc-step[data-step="4"]');
      const $terms = $step4.find('#terms');
      const $submit = $step4.find('#place_order');
      if (!$submit.length) return;

      if ($terms.length && !$terms.is(':checked')) {
        $submit.prop('disabled', true).addClass('msc-btn-disabled');
      } else {
        $submit.prop('disabled', false).removeClass('msc-btn-disabled');
      }
    },
    
    fixFieldLabels() {
      this.updateFieldLabel('#billing_address_2_field', 'Complemento', true);
      this.updateFieldLabel('#billing_neighborhood_field', 'Bairro', false);
      this.updateFieldLabel('#shipping_address_2_field', 'Complemento', true);
      this.updateFieldLabel('#shipping_neighborhood_field', 'Bairro', false);
      $('.form-row label .optional').remove();
    },
    
    updateFieldLabel(fieldSelector, newLabel, isOptional) {
      const $field = $(fieldSelector);
      if ($field.length) {
        const $label = $field.find('label');
        const $input = $field.find('input, select, textarea');
        
        if ($label.length) {
          $label.removeClass('screen-reader-text').empty();
          const isRequired = $field.hasClass('validate-required');
          const requiredMark = isRequired ? ' <abbr class="required" title="obrigatório">*</abbr>' : '';
          const optionalMark = (isOptional && !isRequired) ? ' <span class="optional">(opcional)</span>' : '';
          $label.html(newLabel + requiredMark + optionalMark);
        }
        
        if ($input.length) {
          const placeholder = isOptional ? `${newLabel} (opcional)` : newLabel;
          $input.attr('placeholder', placeholder);
        }
      }
    },
    
    bindEvents() {
      $(document).on('click', '.msc-btn-next', (e) => {
        e.preventDefault();
        const nextStep = $(e.currentTarget).data('next-step');
        this.goToStep(nextStep);
      });
      
      $(document).on('click', '.msc-btn-back', (e) => {
        e.preventDefault();
        const prevStep = $(e.currentTarget).data('prev-step');
        this.goToStep(prevStep);
      });
      
      $(document).on('click', '.msc-progress-step', (e) => {
        const step = $(e.currentTarget).data('step');
        if (step < this.currentStep) {
          this.goToStep(step);
        }
      });
      
      $(document).on('click', '.msc-qty-btn', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        const $btn = $(e.currentTarget);
        const action = $btn.hasClass('msc-qty-minus') ? 'minus' : 'plus';
        const $container = $btn.closest('.msc-item-quantity');
        const $item = $btn.closest('.msc-cart-item');
        const $input = $container.find('input[type="number"], input.qty');
        const cartKey = $item.attr('data-cart-key');
        
        if (!$input.length || !cartKey) return;
        
        let currentQty = parseInt($input.val()) || 1;
        const maxQty = parseInt($input.attr('max')) || 999;
        const minQty = parseInt($input.attr('min')) || 1;
        let newQty = currentQty;
        
        if (action === 'plus') {
          if (currentQty < maxQty) {
            newQty = currentQty + 1;
          } else {
            $btn.css('background', '#f23d4f');
            setTimeout(() => $btn.css('background', ''), 300);
            return;
          }
        } else if (action === 'minus') {
          if (currentQty > minQty) {
            newQty = currentQty - 1;
          } else {
            $btn.css('background', '#f23d4f');
            setTimeout(() => $btn.css('background', ''), 300);
            return;
          }
        }
        
        $input.val(newQty);
        this.updateCartQuantity(cartKey, newQty, $item);
      });

      // Digitação direta no campo de quantidade (o input não é mais
      // readonly). Só dispara a atualização quando o campo perde o foco ou
      // o cliente aperta Enter, para não bater a API a cada tecla e para
      // validar/arredondar o valor antes de enviar.
      $(document).on('change', '.msc-item-quantity input.qty', (e) => {
        const $input = $(e.currentTarget);
        const $item = $input.closest('.msc-cart-item');
        const cartKey = $item.attr('data-cart-key');
        if (!cartKey) return;

        const minQty = parseInt($input.attr('min')) || 1;
        const maxQty = parseInt($input.attr('max')) || 999;
        const originalQty = parseInt($input.attr('data-original')) || minQty;

        let typedQty = parseInt($input.val(), 10);
        if (!Number.isInteger(typedQty) || typedQty < minQty) {
          typedQty = minQty;
        } else if (typedQty > maxQty) {
          typedQty = maxQty;
        }

        $input.val(typedQty);

        if (typedQty === originalQty) return;

        this.updateCartQuantity(cartKey, typedQty, $item);
      });

      $(document).on('keypress', '.msc-item-quantity input.qty', (e) => {
        if (e.which === 13) {
          e.preventDefault();
          $(e.currentTarget).trigger('change');
        }
      });
      
      $(document).on('click', '.msc-remove-item', (e) => {
        e.preventDefault();
        const cartKey = $(e.currentTarget).attr('data-cart-key');
        this.removeCartItem(cartKey);
      });
      
      $(document).on('click', '.msc-edit-btn', (e) => {
        e.preventDefault();
        const targetStep = $(e.currentTarget).data('edit-step');
        if (targetStep) this.goToStep(targetStep);
      });
      
      $(document).on('click', '.msc-apply-coupon', (e) => {
        e.preventDefault();
        this.applyCoupon();
      });
      
      $(document).on('keypress', '.msc-coupon-input', (e) => {
        if (e.which === 13) {
          e.preventDefault();
          this.applyCoupon();
        }
      });
      
      $(document).on('click', '.msc-remove-coupon', (e) => {
        e.preventDefault();
        const coupon = $(e.currentTarget).attr('data-coupon');
        this.removeCoupon(coupon);
      });
      
      $(document).on('change', '#ship-to-different-address-checkbox', (e) => {
        const $checkbox = $(e.currentTarget);
        const $shippingSection = $('.msc-shipping-section.shipping_address');
        const isChecked = $checkbox.is(':checked');

        if (isChecked) {
          $shippingSection.slideDown(300, () => {
            $shippingSection.find('input:visible').first().focus();
            this.fixFieldLabels();
            this.initShippingFullName();
          });
        } else {
          $shippingSection.slideUp(300);
        }

        this.triggerCheckoutUpdate();
      });

      $(document).on('input change', '#msc_shipping_full_name', (e) => {
        const { first, last } = this.splitShippingFullName($(e.currentTarget).val());
        $('#shipping_first_name').val(first).trigger('change');
        $('#shipping_last_name').val(last).trigger('change');
      });

      $(document).on('change', '#terms', () => {
        this.updateSubmitButtonState();
      });

      $(document.body).on('msc:enterStep:4', () => {
        this.updateSubmitButtonState();
      });

      $(document).on('click', '.msc-contact-edit-btn, .msc-address-edit-btn', (e) => {
        e.preventDefault();
        $(e.currentTarget).closest('.msc-contact-block, .msc-address-block').attr('data-mode', 'edit');
      });

      $(document).on('click', '.msc-contact-save-btn, .msc-address-save-btn', (e) => {
        e.preventDefault();
        this.saveInlineBlock($(e.currentTarget).closest('.msc-contact-block, .msc-address-block'));
      });
    },

    // Copia os valores atuais dos campos WC (email/telefone/CPF ou
    // nome/logradouro/bairro/cidade-UF) para os spans de exibição do bloco
    // e volta para o modo "display". Os inputs reais continuam no DOM
    // (dentro do painel de edição) preenchidos normalmente para o submit.
    saveInlineBlock($block) {
      if (!$block.length) return;

      $block.find('[data-display-field]').each((i, el) => {
        const $display = $(el);
        const field = $display.data('display-field');

        if (field === 'msc_address_name') {
          const name = ($('#billing_first_name').val() || '') + ' ' + ($('#billing_last_name').val() || '');
          $display.text(name.trim() || '—');
        } else if (field === 'msc_address_line') {
          const line = (($('#billing_address_1').val() || '') + ' ' + ($('#billing_address_2').val() || '')).trim();
          const neighborhood = $('#billing_neighborhood').val() || '';
          $display.text((line || '—') + (neighborhood ? ' - ' + neighborhood : ''));
        } else if (field === 'msc_address_city_state') {
          const city = $('#billing_city').val() || '';
          const state = $('#billing_state').val() || '';
          const postcode = $('#billing_postcode').val() || '';
          const cityState = (city + (state ? ' - ' + state : '')).trim();
          $display.text((cityState || '—') + (postcode ? ', ' + postcode : ''));
        } else {
          const $input = $('#' + field);
          if ($input.length) {
            $display.text($input.val() || '—');
          }
        }
      });

      $block.attr('data-mode', 'display');
      this.triggerCheckoutUpdate();
    },

    initWooCommerceIntegration() {
      // Listener GLOBAL para updated_checkout
      $(document.body).on('updated_checkout', () => {
        if (this._isUpdating) return;
        
        console.log('Evento: updated_checkout');
        this.fixFieldLabels();
        
        setTimeout(() => {
          this.updateAllStepsValues();
        }, 300);
        
        setTimeout(() => {
          this.updateAllStepsValues();
        }, 1000);
        
        if (typeof window.wc_pagarme_update_checkout === 'function') {
          setTimeout(() => {
            window.wc_pagarme_update_checkout();
          }, 500);
        }
      });
      
      // Listener para wc_fragments_refreshed
      $(document.body).on('wc_fragments_refreshed wc_fragments_loaded', () => {
        console.log('Evento: Fragments atualizados');
        
        setTimeout(() => {
          this.updateAllStepsValues();
        }, 200);
      });
      
      // Listener para mudança de frete
      $(document).on('change', 'input[name^="shipping_method"]', () => {
        console.log('Evento: Frete alterado');
        this.updateAllStepsValues();
        this.triggerCheckoutUpdate();
      });
      
      // Listener para mudança de pagamento
      $(document).on('change', 'input[name="payment_method"]', () => {
        console.log('Evento: Pagamento alterado');
        
        setTimeout(() => {
          this.updateAllStepsValues();
          
          if (typeof window.wc_pagarme_update_checkout === 'function') {
            window.wc_pagarme_update_checkout();
          }
        }, 200);
      });
      
      // Monitorar mudanças na review table
      const reviewObserver = new MutationObserver((mutations) => {
        const hasRelevantChange = mutations.some(mutation => {
          return mutation.target.classList && (
            mutation.target.classList.contains('woocommerce-checkout-review-order-table') ||
            mutation.target.closest('.woocommerce-checkout-review-order-table')
          );
        });
        
        if (hasRelevantChange) {
          console.log('Review table modificada - atualizando');
          this.updateAllStepsValues();
        }
      });
      
      setTimeout(() => {
        const $reviewTable = $('.woocommerce-checkout-review-order-table');
        if ($reviewTable.length) {
          reviewObserver.observe($reviewTable[0], {
            childList: true,
            subtree: true,
            characterData: true
          });
        }
      }, 1000);
      
      $(document.body).on('msc:enterStep:2', () => {
        const $checkbox = $('#ship-to-different-address-checkbox');
        const $shippingSection = $('.msc-shipping-section.shipping_address');
        
        if ($checkbox.is(':checked')) {
          $shippingSection.show();
          this.initShippingFullName();
        } else {
          $shippingSection.hide();
        }
        
        this.fixFieldLabels();
        this.triggerCheckoutUpdate();
        
        setTimeout(() => {
          if (typeof $.fn.select2 !== 'undefined') {
            $shippingSection.find('select:not(.select2-hidden-accessible)').each(function() {
              $(this).select2({ width: '100%' });
            });
          }
        }, 100);
      });
      
      $(document.body).on('msc:enterStep:3', () => {
        this.triggerCheckoutUpdate();
        
        setTimeout(() => {
          if (typeof window.wc_pagarme_update_checkout === 'function') {
            window.wc_pagarme_update_checkout();
          }
          
          const $installments = $('#pagarme-installments');
          if ($installments.length) {
            $installments.trigger('change');
          }
        }, 500);
      });
      
      $(document.body).on('msc:enterStep:4', () => {
        $('#payment').show();
        $('.woocommerce-terms-and-conditions-wrapper').show();
        
        setTimeout(() => {
          this.updateAllStepsValues();
          this.refreshStep4Values();
        }, 500);
      });
    },
    
    goToStep(step) {
      step = parseInt(step);
      
      if (step > this.currentStep && !this.validateCurrentStep()) {
        return;
      }
      
      console.log(`Navegando: ${this.currentStep} → ${step}`);
      
      if (this.currentStep === 2) {
        this.stopShippingGuardian();
      }
      
      $(`.msc-step[data-step="${this.currentStep}"]`).hide().removeClass('active');
      $(`.msc-step[data-step="${step}"]`).show().addClass('active');
      
      this.updateProgress(step);
      
      const oldStep = this.currentStep;
      this.currentStep = step;
      
      $(document.body).trigger('msc:stepChanged', [oldStep, step]);
      $(document.body).trigger(`msc:enterStep:${step}`);
      
      if (step === 2) {
        setTimeout(() => this.startShippingGuardian(), 500);
      }
      
      $('html, body').animate({ scrollTop: 0 }, 300);
      
      if (step >= 2) {
        this.forceStepRecalculation(step);
      } else {
        setTimeout(() => this.updateAllStepsValues(), 500);
      }
    },
    
    forceStepRecalculation(step) {
      console.log(`Forçando recálculo completo para step ${step}`);
      
      // 1. Iniciar o cálculo AJAX (dispara updated_checkout ao final)
      $(document.body).trigger('update_checkout');
      
      // 2. Chamar atualizações de valores e Pagar.me após um pequeno delay
      //    para garantir que os fragments do WC tenham sido processados.
        setTimeout(() => {
          this.updateAllStepsValues();
          
          if (step === 3) {
            if (typeof window.wc_pagarme_update_checkout === 'function') {
              console.log('Forçando atualização Pagarme');
              window.wc_pagarme_update_checkout();
            }
            
            // CORREÇÃO 3: Forçar re-renderização do Pagar.me se campos não existirem
            setTimeout(() => {
              if (typeof window.PagarMeGatewayCheckout !== 'undefined' && 
                  $('input[name="pagarme_payment_type"]').length === 0) {
                  
                console.log('MSC forçando Pagar.me re-render via forceRenderFields');
                window.PagarMeGatewayCheckout.forceRenderFields();
              }
            }, 600); // Dar tempo para a atualização WC terminar
            
            const $installments = $('#pagarme-installments');
            if ($installments.length) {
              $installments.trigger('change');
            }
          }
          
          if (step === 4) {
          this.refreshStep4Values();
        }
      }, 500);
      
      // REMOVIDO: O bloco setTimeout(..., 2000) redundante que causava o loop infinito
    },

    
    updateProgress(step) {
      $('.msc-progress-step').each((i, el) => {
        const $step = $(el);
        const stepNum = $step.data('step');
        
        $step.removeClass('active completed pending');
        
        if (stepNum < step) {
          $step.addClass('completed');
        } else if (stepNum === step) {
          $step.addClass('active');
        } else {
          $step.addClass('pending');
        }
      });
      
      const progress = ((step - 1) / (this.totalSteps - 1)) * 100;
      $('.msc-progress-bar-inner').css('width', progress + '%');
      $('.msc-checkout-wrapper').attr('data-current-step', step);
    },
    
    validateCurrentStep() {
    switch (this.currentStep) {
        case 1:
        // Validação do carrinho
        if ($('.msc-cart-item').length === 0) {
            alert('Carrinho vazio');
            return false;
        }
        return true;
        
        case 2:
        // Validação de endereço
        const useDifferentAddress = $('#ship-to-different-address-checkbox').is(':checked');
        
        if (useDifferentAddress) {
            const hasShippingAddress = $('#shipping_postcode').val() && $('#shipping_address_1').val();
            if (!hasShippingAddress) {
            alert('Preencha o endereço de entrega');
            $('#shipping_postcode').focus();
            return false;
            }
        } else {
            const hasBillingAddress = $('#billing_postcode').val() && $('#billing_address_1').val();
            if (!hasBillingAddress) {
            alert('Preencha o endereço de cobrança');
            $('#billing_postcode').focus();
            return false;
            }
        }
        
        // Validação de método de envio
        const selectedShipping = $('input[name^="shipping_method"]:checked');
        if (selectedShipping.length === 0) {
            alert('Selecione um método de envio');
            return false;
        }
        
        return true;
        
        case 3:
        // NOVA VALIDAÇÃO: Step 3 - Pagamento
        console.log('Validando Step 3 - Pagamento');
        
        // Verificar se há método de pagamento selecionado
        const selectedPayment = $('input[name="payment_method"]:checked');
        if (selectedPayment.length === 0) {
            alert('Selecione um método de pagamento');
            return false;
        }
        
        const paymentMethod = selectedPayment.val();
        console.log('Método selecionado:', paymentMethod);
        
        // Se for Pagar.me, chamar validação específica
        if (paymentMethod === 'pagarme_gateway') {
            
            // Verificar se o objeto de validação do Pagarme existe
            if (typeof window.PagarMeGatewayCheckout === 'undefined') {
            console.error('PagarMeGatewayCheckout não encontrado');
            alert('Erro ao validar pagamento. Recarregue a página.');
            return false;
            }
            
            // Chamar método de validação do gateway
            const validation = window.PagarMeGatewayCheckout.canGoForward();
            
            if (!validation.ok) {
            console.error('Validação Pagarme falhou:', validation.error);
            alert(validation.error || 'Por favor, preencha todos os dados de pagamento');
            return false;
            }
            
            console.log('Validação Pagarme passou');
        }
        
        return true;
        
        default:
        return true;
    }
    },
    
    setupValidation() {
      $('#shipping_postcode, #billing_postcode').on('blur', () => {
        this.triggerCheckoutUpdate();
      });
      
      $('input[required], select[required]').on('blur', function() {
        const $field = $(this);
        const $wrapper = $field.closest('.form-row');
        
        if (!$field.val()) {
          $wrapper.addClass('woocommerce-invalid');
        } else {
          $wrapper.removeClass('woocommerce-invalid');
        }
      });
    },
    
    triggerCheckoutUpdate() {
      if (this._isUpdating) return;
      
      this._isUpdating = true;
      console.log('Triggering update_checkout');
      $(document.body).trigger('update_checkout');
      
      setTimeout(() => {
        this._isUpdating = false;
      }, 1000);
    },
    
    // FUNÇÕES DE ATUALIZAÇÃO DE VALORES
    
    updateCartQuantity(cartKey, quantity, $item) {
      console.log(`Atualizando: ${cartKey} → ${quantity}`);
      
      $item.css('opacity', '0.6');
      const $input = $item.find('input[type="number"], input.qty');
      $input.prop('disabled', true);
      
      $.ajax({
        url: msc_params.ajax_url,
        type: 'POST',
        data: {
          action: 'msc_update_cart_item',
          nonce: msc_params.nonce,
          cart_item_key: cartKey,
          quantity: quantity
        },
        success: (response) => {
          // Envolto em try/finally: se qualquer coisa aqui lançar uma
          // exceção inesperada, o item não pode ficar preso em "carregando"
          // para sempre — o finally garante que o input volte a responder.
          try {
            if (response.success) {
              console.log('Resposta recebida:', response.data);

              // 1⃣ Atualizar item específico
              if (response.data.item_subtotal) {
                $item.find('.msc-item-subtotal').html(response.data.item_subtotal);
              }

              // 2⃣ Atualizar DIRETAMENTE com dados do backend
              this.updateValuesFromResponse(response.data);

              // 3⃣ Aplicar fragments
              if (response.data.fragments) {
                this.applyFragments(response.data.fragments);
              }

              // A quantidade confirmada pelo servidor vira a nova referência
              // para comparação (usada tanto no fallback de erro quanto para
              // detectar "sem mudança real" na digitação direta).
              $input.attr('data-original', quantity);

              // 4⃣ Forçar WooCommerce atualizar
              $(document.body).trigger('update_checkout');

            } else {
              alert(response.data.message || 'Erro ao atualizar');
              $input.val($input.attr('data-original') || 1);
            }
          } finally {
            $input.prop('disabled', false);
            $item.css('opacity', '1');
          }
        },
        error: () => {
          alert('Erro ao atualizar');
          $input.val($input.attr('data-original') || 1);
          $input.prop('disabled', false);
          $item.css('opacity', '1');
        },
        complete: () => {
          // Rede de segurança redundante: garante que o item nunca fique
          // travado "carregando" mesmo se success/error não rodarem.
          $input.prop('disabled', false);
          $item.css('opacity', '1');
        }
      });
    },
    
    updateValuesFromResponse(data) {
      console.log('Atualizando valores diretos do backend...');
      
      // Sidebar - Subtotal
      if (data.cart_subtotal) {
        $('.msc-subtotal-value').html(data.cart_subtotal);
        console.log('Subtotal:', data.cart_subtotal);
      }
      
      // Sidebar - Total
      if (data.cart_total) {
        $('.msc-total-value').html(data.cart_total);
        console.log('Total:', data.cart_total);
      }
      
      // Sidebar - Frete
      if (data.shipping_total) {
        $('.msc-shipping-value').html(data.shipping_total);
        console.log('Frete:', data.shipping_total);
      } else if (data.shipping_total_raw !== undefined) {
        if (data.shipping_total_raw > 0) {
          const shippingHtml = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">R$</span>&nbsp;' 
            + this.formatCurrencyBRL(data.shipping_total_raw) + '</bdi></span>';
          $('.msc-shipping-value').html(shippingHtml);
          console.log('Frete (raw):', data.shipping_total_raw);
        } else {
          $('.msc-shipping-value').html('<span style="color: #999;">A calcular</span>');
        }
      }
      
      // Sidebar - Desconto
      if (data.discount_total) {
        $('.msc-discount-value').html(data.discount_total).parent().show();
        console.log('Desconto:', data.discount_total);
      } else if (data.discount_total_raw !== undefined && data.discount_total_raw > 0) {
        const discountHtml = '<span style="color:#00a650;">-<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">R$</span>&nbsp;' 
          + this.formatCurrencyBRL(data.discount_total_raw) + '</bdi></span></span>';
        $('.msc-discount-value').html(discountHtml).parent().show();
      } else {
        $('.msc-discount-value').parent().hide();
      }
      
      // Atualizar subtotais por CODLOCAL
      if (data.codlocal_totals) {
        $.each(data.codlocal_totals, (codlocal, total) => {
          const selector = `.msc-codlocal-group[data-codlocal="${codlocal}"] .msc-codlocal-subtotal-value`;
          const html = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">R$</span>&nbsp;' 
            + this.formatCurrencyBRL(total) + '</bdi></span>';
          $(selector).html(html);
        });
      }
      
      // Aguardar e tentar ler da review table também (fallback)
      setTimeout(() => {
        this.updateFromReviewTable();
      }, 500);
    },
    
    updateFromReviewTable() {
      const $reviewTable = $('.woocommerce-checkout-review-order-table');
      
      if (!$reviewTable.length) {
        console.log('Review table não existe (normal no Step 1)');
        return;
      }
      
      console.log('Review table existe - usando como fallback...');
      
      // Subtotal
      const $subtotal = $reviewTable.find('.cart-subtotal .amount').last();
      if ($subtotal.length && $subtotal.text().trim()) {
        $('.msc-subtotal-value').html($subtotal.html());
      }
      
      // Total
      const $total = $reviewTable.find('.order-total .amount').last();
      if ($total.length && $total.text().trim()) {
        $('.msc-total-value').html($total.html());
      }
      
      // Frete
      let shippingTotal = this.calculateTotalShipping();
      if (shippingTotal > 0) {
        const shippingHtml = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">R$</span>&nbsp;' 
          + this.formatCurrencyBRL(shippingTotal) + '</bdi></span>';
        $('.msc-shipping-value').html(shippingHtml);
      }
      
      // Desconto
      const $discount = $reviewTable.find('.cart-discount .amount').last();
      if ($discount.length && $discount.text().trim()) {
        $('.msc-discount-value').html($discount.html()).parent().show();
      }
    },
    
    updateAllStepsValues() {
      console.log('Atualizando valores em todos os steps...');
      
      // Tentar atualizar da review table
      this.updateFromReviewTable();
      
      // Atualizar subtotais por CODLOCAL (do DOM)
      $('.msc-codlocal-group').each(function() {
        const $group = $(this);
        let groupTotal = 0;
        
        $group.find('.msc-cart-item').each(function() {
          const $item = $(this);
          const $subtotal = $item.find('.msc-item-subtotal .amount');
          if ($subtotal.length) {
            const value = MSC.parseAmountFromText($subtotal.text());
            groupTotal += value;
          }
        });
        
        if (groupTotal > 0) {
          const groupHtml = '<span class="woocommerce-Price-amount amount"><bdi><span class="woocommerce-Price-currencySymbol">R$</span>&nbsp;' 
            + MSC.formatCurrencyBRL(groupTotal) + '</bdi></span>';
          $group.find('.msc-codlocal-subtotal-value').html(groupHtml);
        }
      });
      
      // Atualizar Step 4 se estiver ativo
      if ($('.msc-step[data-step="4"]').hasClass('active')) {
        this.updateStep4AllValues();
      }
      
      // Atualizar Pagarme
      if (typeof window.wc_pagarme_update_checkout === 'function') {
        setTimeout(() => {
          window.wc_pagarme_update_checkout();
        }, 300);
      }
    },
    
    calculateTotalShipping() {
      let totalShipping = 0;
      let foundShipping = false;
      
      // Método 1: Via inputs checked
      $('input[name^="shipping_method"]:checked').each(function() {
        const $label = $(this).closest('li').find('label');
        const $amount = $label.find('.woocommerce-Price-amount').first();
        if ($amount.length) {
          const value = MSC.parseAmountFromText($amount.text());
          if (value > 0) {
            totalShipping += value;
            foundShipping = true;
          }
        }
      });
      
      // Método 2: Fallback via tabela
      if (!foundShipping) {
        const $reviewTable = $('.woocommerce-checkout-review-order-table');
        if ($reviewTable.length) {
          const $shippingRow = $reviewTable.find('tr.woocommerce-shipping-totals td').first();
          if ($shippingRow.length) {
            const $amount = $shippingRow.find('.woocommerce-Price-amount').first();
            if ($amount.length) {
              totalShipping = this.parseAmountFromText($amount.text());
              foundShipping = true;
            }
          }
        }
      }
      
      return totalShipping;
    },
    
    updateStep4AllValues() {
    console.log('Atualizando Step 4...');
    
    const $reviewTable = $('.woocommerce-checkout-review-order-table');
    if (!$reviewTable.length) {
        console.warn('Review table não existe - Step 4 não pode ser atualizado');
        return;
    }
    
    // Produtos
    $('.msc-review-product-item').each(function() {
        const $reviewItem = $(this);
        const $productName = $reviewItem.find('.msc-review-product-name');
        
        if ($productName.length) {
        const productName = $productName.text().trim();
        
        $reviewTable.find('tbody tr.cart_item').each(function() {
            const $row = $(this);
            const $nameCell = $row.find('.product-name');
            
            if ($nameCell.length && $nameCell.text().includes(productName.substring(0, 20))) {
            // Quantidade
            const qtyMatch = $nameCell.text().match(/×\s*(\d+)/);
            if (qtyMatch) {
                $reviewItem.find('.msc-review-product-meta strong').text(qtyMatch[1]);
            }
            
            // Preço
            const $priceCell = $row.find('.product-total .amount');
            if ($priceCell.length) {
                $reviewItem.find('.msc-review-product-price').html($priceCell.html());
            }
            }
        });
        }
    });
    
    // CORREÇÃO: Totais com estilo inline
    
    // Subtotal - Forçar cor do token --msc-near-black
    const $subtotal = $reviewTable.find('.cart-subtotal .amount').last();
    if ($subtotal.length) {
        const subtotalHtml = $subtotal.html();
        $('.msc-review-totals-table .msc-review-total-row:first strong')
        .html('<span style="color:#231F20!important">' + subtotalHtml + '</span>')
        .css('color', '#231F20');
    }

    // Frete - Forçar cor do token --msc-near-black
    const totalShipping = this.calculateTotalShipping();
    if (totalShipping > 0) {
        const shippingHtml = '<span class="woocommerce-Price-amount amount" style="color:#231F20!important">' +
        '<bdi style="color:#231F20!important">' +
        '<span class="woocommerce-Price-currencySymbol" style="color:#231F20!important">R$</span>' +
        '&nbsp;' + this.formatCurrencyBRL(totalShipping) +
        '</bdi></span>';
        $('.msc-review-totals-table .msc-review-total-row:nth-child(2) strong')
        .html(shippingHtml)
        .css('color', '#231F20');
    }
    
    // Total - Forçar cor BRANCA
    const $total = $reviewTable.find('.order-total .amount').last();
    if ($total.length) {
        const totalHtml = $total.html();
        $('.msc-review-totals-table .msc-review-total-final strong')
        .html('<span style="color:white!important">' + totalHtml + '</span>')
        .css('color', 'white');
    }
    
    console.log('Step 4 atualizado com cores corretas');
    },
    
    updateSummary() {
      console.log('updateSummary() chamado');
      this.updateAllStepsValues();
    },
    
    refreshStep4Values() {
      this.updateStep4AllValues();
    },
    
    // FUNÇÕES AUXILIARES
    
    parseAmountFromText(text) {
      if (!text) return 0;
      const raw = ('' + text).trim();
      if (/gr[aá]tis/i.test(raw)) return 0;
      if (/a\s*calcular/i.test(raw)) return 0;
      const clean = raw.replace(/[^\d.,-]/g, '');
      const normalized = clean.replace(/\./g, '').replace(/,/g, '.');
      const n = parseFloat(normalized);
      return isNaN(n) ? 0 : n;
    },
    
    formatCurrencyBRL(value) {
      const n = isFinite(value) ? Number(value) : 0;
      try {
        return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      } catch (e) {
        const s = n.toFixed(2).replace('.', ',');
        return s.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }
    },
    
    applyFragments(fragments) {
      console.log('Aplicando fragments:', Object.keys(fragments).length);
      
      if (!fragments || typeof fragments !== 'object') return;
      
      $.each(fragments, function(selector, html) {
        try {
          const $target = $(selector);
          if ($target.length > 0) {
            $target.replaceWith(html);
          }
        } catch (e) {
          console.error(`Erro ao aplicar ${selector}:`, e);
        }
      });
      
      $(document.body).trigger('wc_fragments_refreshed');
      $(document.body).trigger('wc_fragments_loaded');
    },
    
    removeCartItem(cartKey) {
      if (!confirm('Remover este item?')) return;
      
      const $item = $(`.msc-cart-item[data-cart-key="${cartKey}"]`);
      $item.css('opacity', '0.5');
      
      $.ajax({
        url: msc_params.ajax_url,
        type: 'POST',
        data: {
          action: 'msc_update_cart_item',
          nonce: msc_params.nonce,
          cart_item_key: cartKey,
          quantity: 0
        },
        success: (response) => {
          if (response.success) {
            $item.slideUp(300, function() {
              $(this).remove();
              if ($('.msc-cart-item').length === 0) {
                window.location.href = wc_cart_params.cart_url;
              }
            });
            
            if (response.data.fragments) {
              this.applyFragments(response.data.fragments);
            }
            
            this.triggerCheckoutUpdate();
          } else {
            alert(response.data.message || 'Erro ao remover');
            $item.css('opacity', '1');
          }
        },
        error: () => {
          alert('Erro ao remover');
          $item.css('opacity', '1');
        }
      });
    },
    
    applyCoupon() {
      const $input = $('.msc-coupon-input');
      const $btn = $('.msc-apply-coupon');
      const coupon = $input.val().trim().toUpperCase();
      
      if (!coupon) {
        alert('Digite um código de cupom');
        return;
      }
      
      $btn.prop('disabled', true).text('Aplicando...');
      
      $.ajax({
        url: wc_checkout_params.ajax_url,
        type: 'POST',
        data: {
          action: 'woocommerce_apply_coupon',
          security: wc_checkout_params.apply_coupon_nonce,
          coupon_code: coupon
        },
        success: (response) => {
          if (response && response.indexOf('error') > -1) {
            const $response = $(response);
            const errorMsg = $response.find('.woocommerce-error').text() || 'Cupom inválido';
            alert(errorMsg);
          } else {
            alert('✅ Cupom aplicado!');
            $input.val('');
            this.triggerCheckoutUpdate();
          }
        },
        error: () => {
          alert('Erro ao aplicar cupom');
        },
        complete: () => {
          $btn.prop('disabled', false).text('Aplicar');
        }
      });
    },
    
    removeCoupon(coupon) {
      $.ajax({
        url: wc_checkout_params.ajax_url,
        type: 'POST',
        data: {
          action: 'woocommerce_remove_coupon',
          security: wc_checkout_params.remove_coupon_nonce,
          coupon: coupon
        },
        success: () => {
          alert('✅ Cupom removido');
          this.triggerCheckoutUpdate();
        },
        error: () => {
          alert('Erro ao remover cupom');
        }
      });
    },
    
    ensureShippingGroupSelection() {
      const groups = {};
      $('input[name^="shipping_method"]').each(function () {
        const name = this.name;
        (groups[name] ||= []).push(this);
      });

      Object.keys(groups).forEach((name) => {
        const $grp = $(groups[name]);
        if ($grp.filter(':checked').length === 0) {
          const m = name.match(/\[(\d+)\]/);
          const idx = m ? Number(m[1]) : null;
          let $toCheck = $();
          
          if (idx !== null && this.chosenShipping && this.chosenShipping[idx]) {
            $toCheck = $grp.filter((_, el) => el.value === this.chosenShipping[idx]);
          }
          
          if ($toCheck.length === 0) $toCheck = $grp.first();
          
          $grp.prop('checked', false).removeAttr('checked');
          $toCheck.prop('checked', true).attr('checked', 'checked');
          $grp.closest('li').removeClass('is-selected');
          $toCheck.closest('li').addClass('is-selected');
        }
      });
    },
    
    startShippingGuardian() {
      if (this._guardianInterval) {
        clearInterval(this._guardianInterval);
      }
      
      this._guardianInterval = setInterval(() => {
        const isStep2 = $('.msc-step[data-step="2"]').hasClass('active');
        const hasShippingMethods = $('.msc-shipping-methods-container input[name^="shipping_method"]').length > 0;
        
        if (isStep2 && hasShippingMethods) {
          this.ensureShippingGroupSelection();
        }
      }, 200);
    },
    
    stopShippingGuardian() {
      if (this._guardianInterval) {
        clearInterval(this._guardianInterval);
        this._guardianInterval = null;
      }
    }
  };

  $(document).ready(function() {
    if (typeof wc_checkout_params !== 'undefined') {
      MSC.init();
      setTimeout(() => MSC.updateAllStepsValues(), 1000);
    } else {
      setTimeout(() => {
        MSC.init();
        setTimeout(() => MSC.updateAllStepsValues(), 1000);
      }, 500);
    }
  });

  window.MSC = MSC;

})(jQuery);

// PATCH ADICIONAL: Gerenciar shipping methods escolhidos
(function($){
  'use strict';

  function ready(){
    if (!window.MSC) return;

    var NS = '.mscPatchV46';
    $(document).off(NS);
    $(document.body).off(NS);

    if (typeof MSC.chosenShipping === 'undefined') MSC.chosenShipping = {};
    var STORAGE_KEY = 'mscChosenShipping_v46';

    function loadChosenFromStorage(){
      try {
        var raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
          var obj = JSON.parse(raw);
          if (obj && typeof obj === 'object') MSC.chosenShipping = obj;
        }
      } catch(e){}
    }
    
    function saveChosenToStorage(){
      try { 
        localStorage.setItem(STORAGE_KEY, JSON.stringify(MSC.chosenShipping || {})); 
      } catch(e){}
    }

    MSC.captureChosenShipping = function(){
      var snapshot = {};
      $('input[name^="shipping_method"]:checked').each(function(){
        var idx = $(this).data('index');
        var val = $(this).val();
        if (idx !== undefined && val) {
          snapshot[idx] = val;
        }
      });
      MSC.chosenShipping = snapshot;
      saveChosenToStorage();
    };

    MSC.reapplyChosenShipping = function(){
      var map = MSC.chosenShipping || {};
      
      Object.keys(map).forEach(function(k){
        var idx = Number(k);
        var val = map[k];
        var $group = $('input[name="shipping_method['+idx+']"]');
        
        if ($group.length){
          var $match = $group.filter(function(_, el){ return $(el).val() === val; });
          
          if ($match.length){
            $group.prop('checked', false).removeAttr('checked').closest('li').removeClass('is-selected');
            $match.prop('checked', true).attr('checked', 'checked').closest('li').addClass('is-selected');
          }
        }
      });
      
      saveChosenToStorage();
    };

    $(document).on('change'+NS, 'input[name^="shipping_method"]', function(){
      MSC.captureChosenShipping();
      
      if (typeof wc_checkout_params !== 'undefined') {
        $(document.body).trigger('update_checkout');
      }
    });

    $(document.body).on('updated_checkout'+NS, function(){
      MSC.reapplyChosenShipping();
    });

    loadChosenFromStorage();
    
    setTimeout(function(){
      MSC.captureChosenShipping();
      MSC.reapplyChosenShipping();
    }, 120);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready);
  } else {
    ready();
  }

})(jQuery);

/**
 * MELHORIA DE USABILIDADE: Área de clique do frete
 * Expande a área clicável para todo o <li> do método de frete.
 */
jQuery(document).ready(function($) {
    // Usamos 'body' para garantir que o evento funcione mesmo
    // depois que as opções de frete são atualizadas via AJAX.
    $('body').on('click', '.msc-shipping-method-item', function(e) {
        // Encontra o botão de rádio dentro do <li> que foi clicado.
        var $radio = $(this).find('input.shipping_method');

        // Se o radio não estiver selecionado e o clique não foi diretamente nele ou na label...
        if ($radio.length && !$radio.is(':checked')) {
            // ...seleciona o radio...
            $radio.prop('checked', true);
            
            // ...e dispara o evento 'change' para que o WooCommerce
            // atualize os totais, como se o utilizador tivesse clicado diretamente no botão.
            $radio.trigger('change');
        }
    });
});

// DEBUG TEMPORÁRIO - Step 3
jQuery(document).ready(function($) {
    $(document.body).on('msc:enterStep:3', function() {
        console.log('===== DEBUG STEP 3 =====');
        console.log('Payment div existe?', $('#payment').length);
        console.log('Payment methods existem?', $('.wc_payment_methods').length);
        console.log('Payment box existe?', $('.payment_box').length);
        console.log('Pagarme gateway existe?', $('.payment_method_pagarme_gateway').length);
        
        // Forçar exibição
        $('#payment').css({
            'display': 'block !important',
            'visibility': 'visible !important',
            'opacity': '1 !important'
        });
        
        $('.payment_box').css({
            'display': 'block !important',
            'visibility': 'visible !important'
        });
        
        // Trigger eventos
        $(document.body).trigger('init_checkout');
        $(document.body).trigger('payment_method_selected');
        
        console.log('===== FIM DEBUG =====');
    });
});
