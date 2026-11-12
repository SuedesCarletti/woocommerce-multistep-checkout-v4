/**
 * SINCRONIZAÇÃO STEP 3 → STEP 4 - CORRIGIDO
 * 
 * Versão: 4.1.0 - MULTIPAGAMENTO CORRIGIDO
 * 1. Lê dados diretamente do DOM ao invés do cache
 * 2. Salva estrutura completa do multipagamento
 * 3. Renderiza corretamente no Step 4
 * 
 * Data: 27/11/2025
 */

(function($) {
    'use strict';
    
    const STORAGE_KEY = 'pagarme_payment_data';
    const MULTI_PAY_KEY = 'pagarme_multipay_protected_v5'; // Key used by pagarme-multipayment-fix.js
    let debugMode = false;
    
    /**
     * Sistema de logs inteligente
     */
    function log(message, data, level) {
        level = level || 'info';
        
        // Verificar debug via URL
        if (!debugMode) {
            debugMode = window.location.search.indexOf('debug=1') !== -1;
        }
        
        // Só mostrar logs se debug ativo (exceto erros)
        if (!debugMode && level !== 'error') return;
        
        const prefix = '🔷 Step4Sync: ';
        
        switch(level) {
            case 'error':
                console.error(prefix + message, data || '');
                break;
            case 'warn':
                console.warn(prefix + message, data || '');
                break;
            default:
                console.log(prefix + message, data || '');
        }
    }
    
    log('Módulo carregado');

    /**
     * Detecção de bandeira por prefixo BIN, espelhando a mesma lógica do
     * preview de cartão em rede-checkout.js — usada aqui só para o texto
     * do resumo do Step 4.
     */
    function detectRedeCardBrand(digits) {
        if (/^4/.test(digits)) return 'Visa';
        if (/^(5[1-5]|2[2-7])/.test(digits)) return 'Mastercard';
        if (/^3[47]/.test(digits)) return 'Amex';
        if (/^(636368|438935|504175|451416|509|627780|636297)/.test(digits)) return 'Elo';
        return '';
    }
    
    /**
     * GERENCIADOR DE SINCRONIZAÇÃO
     */
    const Step4Sync = {
        
        /**
         * Inicializar
         */
        init: function() {
            log('Inicializando sincronizador Step 3 → Step 4');
            
            // Bind de eventos
            this.bindEvents();
            
            // Se já estiver no Step 4, atualizar
            setTimeout(() => {
                if (this.isStep4Visible()) {
                    log('Step 4 já visível - atualizando');
                    this.updateStep4Display();
                }
            }, 500);
        },
        
        /**
         * Verificar se Step 4 está visível
         */
        isStep4Visible: function() {
            const $step4 = $('.msc-step[data-step="4"]');
            return $step4.length > 0 && $step4.is(':visible');
        },
        
        /**
         * Bind de eventos
         */
        bindEvents: function() {
            const self = this;
            
            // CORRIGIDO: Escutar mudança no radio correto
            $(document).on('change', 'input[name="pagarme_payment_type"], input[name="rede_payment_method"], input[name="payment_method"]', function() {
                const method = $(this).val();
                log('Método alterado: ' + method, null, 'debug');
                self.saveCurrentPaymentMethod();
            });
            
            // Mudança nos campos de cartão
            $(document).on('change blur', '#pagarme-card-holder-name, #pagarme-card-number, #pagarme-installments, input[name="rede_card_name"], input[name="rede_card_number"], #rede_card_installments', function() {
                log('Campo de cartão alterado', null, 'debug');
                self.saveCurrentPaymentMethod();
            });

            // Split de pagamento da Rede
            $(document).on('change blur', '#rede_is_multipayment, .rede-multi-method, .rede-multi-amount', function() {
                log('Split de pagamento da Rede alterado', null, 'debug');
                self.saveCurrentPaymentMethod();
            });
            
            // Botão de navegação para Step 4
            $(document).on('click', '.msc-btn-next[data-next-step="4"]', function() {
                log('Navegando para Step 4 - salvando dados');
                self.saveCurrentPaymentMethod();
                setTimeout(() => self.updateStep4Display(), 500);
            });
            
            // Observer de mudanças nos steps
            this.observeStepChanges();
        },
        
        /**
         * Observer de mudanças nos steps
         */
        observeStepChanges: function() {
            const self = this;
            const $step4 = $('.msc-step[data-step="4"]');
            
            if ($step4.length === 0) return;
            
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === 'style') {
                        const isVisible = $step4.is(':visible');
                        if (isVisible) {
                            log('Step 4 visível - atualizando display', null, 'debug');
                            setTimeout(() => self.updateStep4Display(), 200);
                        }
                    }
                });
            });
            
            observer.observe($step4[0], {
                attributes: true,
                attributeFilter: ['style']
            });
        },
        
        /**
         * CORRIGIDO: Salvar método de pagamento atual
         */
        saveCurrentPaymentMethod: function() {
            try {
                // 0. Split de pagamento da Rede (rede_is_multipayment=1)
                const redeGatewayChecked = $('input[name="payment_method"]:checked').val() === 'rede_credit_card';
                const redeMultiEnabled = redeGatewayChecked && $('#rede_is_multipayment').val() === '1';

                if (redeMultiEnabled) {
                    const legs = [1, 2].map(function(n) {
                        return {
                            method: $('#rede_multi_method_' + n).val() || '',
                            amount: parseFloat($('#rede_multi_amount_' + n).val() || 0)
                        };
                    });

                    const dataToSave = {
                        payment_method: 'rede_multi',
                        woo_payment_method: 'rede',
                        multi_payments: legs,
                        timestamp: Date.now()
                    };
                    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(dataToSave));
                    log('✅ Dados de split da Rede salvos', dataToSave, 'debug');
                    return;
                }

                // 1. Verificar se o Multi-pagamento está ativo (Pagar.me)
                const isMultiEnabled = $('#enable-multipayment').is(':checked');

                if (isMultiEnabled) {
                    log('🔄 Multipagamento detectado - coletando dados do DOM', null, 'debug');
                    
                    // LER DIRETAMENTE DO DOM ao invés do cache
                    const methods = [];
                    
                    $('.multipay-checkbox:checked').each(function() {
                        const method = $(this).val();
                        const $amountInput = $(`.multipay-amount[data-method="${method}"]`);
                        const amount = parseFloat($amountInput.val() || 0);
                        
                        if (amount > 0) {
                            const paymentData = {
                                method: method,
                                amount: amount
                            };
                            
                            // Adicionar dados de cartão se existir
                            if (['credit_card', 'debit_card', 'voucher'].includes(method)) {
                                const cardNumber = $(`.multipay-card-number[data-method="${method}"]`).val() || '';
                                const cardHolder = $(`.multipay-card-holder[data-method="${method}"]`).val() || '';
                                const cardExpiry = $(`.multipay-card-expiry[data-method="${method}"]`).val() || '';
                                const cardCvv = $(`.multipay-card-cvv[data-method="${method}"]`).val() || '';
                                
                                if (cardNumber) {
                                    paymentData.card = {
                                        number: cardNumber,
                                        holder: cardHolder,
                                        expiry: cardExpiry,
                                        cvv: cardCvv
                                    };
                                    
                                    // Adicionar parcelas para credit_card e voucher
                                    if (method === 'credit_card' || method === 'voucher') {
                                        const installments = $(`.multipay-installments[data-method="${method}"]`).val() || '1';
                                        paymentData.card.installments = installments;
                                    }
                                }
                            }
                            
                            methods.push(paymentData);
                            log(`✅ Método coletado: ${method} - R$ ${amount.toFixed(2)}`, paymentData, 'debug');
                        }
                    });
                    
                    if (methods.length > 0) {
                        // Salvar a estrutura COMPLETA do multi-pagamento no nosso STORAGE_KEY
                        const dataToSave = {
                            payment_method: 'pagarme_gateway_multi', // Flag para identificação
                            woo_payment_method: 'pagarme_gateway',
                            multi_payments: methods,
                            timestamp: Date.now()
                        };
                        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(dataToSave));
                        log('✅ Dados MULTI-PAGAMENTO salvos com sucesso', dataToSave, 'debug');
                        log(`📊 Total de métodos salvos: ${methods.length}`, null, 'debug');
                        return;
                    } else {
                        log('⚠️ Multipagamento habilitado mas nenhum método selecionado', null, 'warn');
                    }
                }
                
                // 2. Lógica de Single Payment (Fallback)
                
                let data = {
                    timestamp: Date.now()
                };
                
                const mainGateway = $('input[name="payment_method"]:checked').val();
                let method = '';
                
                if (mainGateway === 'rede_credit_card') {
                    const redeMethod = $('input[name="rede_payment_method"]').val() || 'rede-tab-cc';

                    if (redeMethod === 'rede-tab-cc') {
                        method = 'credit_card';
                        data.payment_method = method;
                        data.woo_payment_method = 'rede';
                        data.card_holder_name = $('input[name="rede_card_name"]').val() || '';
                        data.installments = $('#rede_card_installments').val() || '1';

                        const cardNumber = $('input[name="rede_card_number"]').val() || '';
                        const cleanNumber = cardNumber.replace(/\D/g, '');
                        data.card_last_4 = cleanNumber.length >= 4
                            ? cleanNumber.substring(cleanNumber.length - 4)
                            : '';
                        data.card_brand = detectRedeCardBrand(cleanNumber);
                    } else if (redeMethod === 'rede-tab-pix') {
                        method = 'pix';
                        data.payment_method = method;
                        data.woo_payment_method = 'rede';
                    } else if (redeMethod === 'rede-tab-boleto') {
                        method = 'boleto';
                        data.payment_method = method;
                        data.woo_payment_method = 'rede';
                    }
                } else {
                    const $checkedRadio = $('input[name="pagarme_payment_type"]:checked');
                    if ($checkedRadio.length === 0) {
                        log('⚠️ Nenhum método selecionado (e multi-pagamento desativado/vazio)', null, 'warn');
                        return;
                    }
                    method = $checkedRadio.val();
                    data.payment_method = method;
                    data.woo_payment_method = 'pagarme_gateway';
                }
                
                log('Salvando método: ' + method, null, 'debug');
                
                // Adicionar dados específicos por método se for Pagar.me
                if (data.woo_payment_method === 'pagarme_gateway') {
                    if (method === 'credit_card') {
                        data.card_holder_name = $('#pagarme-card-holder-name').val() || '';
                        data.card_expiry = $('#pagarme-card-expiry').val() || '';
                        data.installments = $('#pagarme-installments').val() || '1';
                        
                        const cardNumber = $('#pagarme-card-number').val() || '';
                        const cleanNumber = cardNumber.replace(/\D/g, '');
                        data.card_last_4 = cleanNumber.length >= 4 
                            ? cleanNumber.substring(cleanNumber.length - 4) 
                            : '';
                            
                    } else if (method === 'debit_card') {
                        data.card_holder_name = $('#pagarme-card-holder-name').val() || '';
                        data.card_expiry = $('#pagarme-card-expiry').val() || '';
                        
                        const cardNumber = $('#pagarme-card-number').val() || '';
                        const cleanNumber = cardNumber.replace(/\D/g, '');
                        data.card_last_4 = cleanNumber.length >= 4 
                            ? cleanNumber.substring(cleanNumber.length - 4) 
                            : '';
                            
                    } else if (method === 'voucher') {
                        data.card_holder_name = $('#pagarme-card-holder-name').val() || '';
                        data.holder_document = $('#pagarme-card-document').val() || '';
                        
                        const cardNumber = $('#pagarme-card-number').val() || '';
                        const cleanNumber = cardNumber.replace(/\D/g, '');
                        data.card_last_4 = cleanNumber.length >= 4 
                            ? cleanNumber.substring(cleanNumber.length - 4) 
                            : '';
                    }
                }
                
                // Salvar no sessionStorage
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
                log('Dados SINGLE PAYMENT salvos com sucesso', data, 'debug');
                
            } catch (error) {
                log('❌ Erro ao salvar dados', error, 'error');
            }
        },
        
        /**
         * Atualizar display do Step 4
         */
        updateStep4Display: function() {
            try {
                log('Atualizando Step 4', null, 'debug');
                
                // Buscar container do Step 4
                const $container = $('.msc-review-payment .msc-review-section-content');
                
                if ($container.length === 0) {
                    log('⚠️ Container do Step 4 não encontrado', null, 'warn');
                    return;
                }
                
                // Recuperar dados do sessionStorage
                const storedData = sessionStorage.getItem(STORAGE_KEY);
                
                if (!storedData) {
                    log('⚠️ Nenhum dado no sessionStorage', null, 'warn');
                    $container.html('<p>⚠️ Nenhum método de pagamento selecionado</p>');
                    return;
                }
                
                const data = JSON.parse(storedData);
                log('Dados recuperados', data, 'debug');
                
                // Gerar HTML baseado no método
                const html = this.generatePaymentReviewHTML(data);
                $container.html(html);
                
                log('✅ Step 4 atualizado com sucesso');
                
            } catch (error) {
                log('❌ Erro ao atualizar Step 4', error, 'error');
            }
        },
        
        /**
         * Gerar HTML da revisão de pagamento
         */
        generatePaymentReviewHTML: function(data) {
            const methodNames = {
                'credit_card': '💳 Cartão de Crédito',
                'debit_card': '💳 Cartão de Débito',
                'pix': '⚡ PIX',
                'boleto': '📄 Boleto Bancário',
                'voucher': '🎟 Voucher',
                'bank_transfer': '🏦 Transferência Bancária',
                'cash': '💸 Dinheiro',
                'safetypay': '🔒 SafetyPay',
                'pagarme_gateway_multi': '💰 Múltiplos Pagamentos',
                'rede_multi': '💰 Pagamento Dividido',
                'cc': '💳 Cartão de Crédito'
            };

            // --- Split de pagamento da Rede ---
            if (data.payment_method === 'rede_multi' && data.multi_payments) {
                let html = '<div class="pagarme-multi-payment-review">';
                html += '<h4>🎯 Pagamento Dividido</h4>';

                data.multi_payments.forEach((payment) => {
                    const label = methodNames[payment.method] || payment.method;
                    const amount = payment.amount || 0;
                    html += `<p class="payment-method-item"><strong>${label}:</strong> R$ ${amount.toFixed(2).replace('.', ',')}</p>`;
                });

                html += '</div>';
                return html;
            }

            // --- NOVO: Lógica de Multi-Pagamento ---
            if (data.payment_method === 'pagarme_gateway_multi' && data.multi_payments) {
                let html = '<div class="pagarme-multi-payment-review">';
                html += '<h4>🎯 Múltiplos Métodos de Pagamento</h4>';
                
                data.multi_payments.forEach((payment, index) => {
                    const method = payment.method;
                    const amount = payment.amount || 0;
                    
                    let methodHtml = `<p class="payment-method-item"><strong>${methodNames[method] || method}:</strong> R$ ${amount.toFixed(2).replace('.', ',')}`;
                    
                    // Detalhes do cartão (se houver)
                    if (payment.card && payment.card.number) {
                        const last4 = payment.card.number.slice(-4);
                        const installments = payment.card.installments || 1;
                        
                        methodHtml += `<br><em>Cartão (****${last4})</em>`;
                        if (method === 'credit_card' || method === 'voucher') {
                            const instText = installments === '1' || installments === 1 ? 'À vista' : installments + 'x';
                            methodHtml += `<br><em>Parcelas: ${instText}</em>`;
                        }
                    } else if (method === 'pix') {
                        methodHtml += `<br><em>Pagamento instantâneo</em>`;
                    } else if (method === 'boleto') {
                        methodHtml += `<br><em>Aguardando emissão do boleto</em>`;
                    }
                    
                    methodHtml += `</p>`;
                    html += methodHtml;
                });
                
                html += '</div>';
                return html;
            }
            // --- FIM Lógica de Multi-Pagamento ---
            
            // --- Lógica de Single Payment (existente) ---
            
            let html = '<div class="pagarme-payment-review">';
            html += '<h4>🎯 Método de Pagamento</h4>';
            
            // Nome do método
            html += '<p class="payment-method-selected">';
            html += '<strong>' + (methodNames[data.payment_method] || data.payment_method) + '</strong>';
            html += '</p>';
            
            // Detalhes específicos
            if (data.card_holder_name) {
                html += '<p><strong>Titular:</strong> ' + data.card_holder_name + '</p>';
            }
            
            if (data.card_last_4) {
                const brandSuffix = data.card_brand ? ' (' + data.card_brand + ')' : '';
                html += '<p><strong>Cartão:</strong> •••• •••• •••• ' + data.card_last_4 + brandSuffix + '</p>';
            }
            
            if (data.card_expiry) {
                html += '<p><strong>Validade:</strong> ' + data.card_expiry + '</p>';
            }
            
            // Mostrar parcelas APENAS para credit_card
            if (data.payment_method === 'credit_card' && data.installments) {
                const installmentsText = data.installments === '1' || data.installments === 1 
                    ? 'À vista' 
                    : data.installments + 'x';
                html += '<p><strong>Parcelas:</strong> ' + installmentsText + '</p>';
            }
            
            if (data.payment_method === 'pix') {
                html += '<p><em>Aprovação instantânea.</em></p>';
            }
            
            if (data.payment_method === 'boleto') {
                html += '<p><em>Aprovação em 1-2 dias úteis.</em></p>';
            }
            
            if (data.payment_method === 'voucher' && data.holder_document) {
                 html += '<p><strong>CPF do Titular:</strong> ' + data.holder_document + '</p>';
            }
            
            html += '</div>';
            
            return html;
        }
    };
    
    /**
     * INICIALIZAÇÃO
     */
    $(document).ready(function() {
        setTimeout(function() {
            Step4Sync.init();
        }, 800);
    });
    
    // Expor globalmente
    window.Step4Sync = Step4Sync;
    
})(jQuery);
