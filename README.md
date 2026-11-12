# Multistep Checkout Pro v4

Reescrita do [Multistep Checkout Pro](../Multistep-Checkout-Pro) que usa o checkout **nativo** do WooCommerce (em vez de refazer o formulário) e adiciona uma tela de confirmação (*thank you*) própria.

**Diferenças em relação à v3**
- Menos JavaScript de "correção" por etapa: um único `multistep-checkout-v4.js` e um handler AJAX (`class-msc-ajax-handler-v4.php`).
- Funções auxiliares para campos de checkout (`msc-fields-helper.php`) e correção de sincronização na etapa de pagamento.
- Mantém o cálculo de frete por pacotes/`CODLOCAL` da v3.
- Versionamento de assets (`MSC_VERSION`) para invalidar cache do navegador/CDN a cada mudança visual.
- Atualização via plugin Git Updater (cabeçalho `GitHub Plugin URI`).

Os nomes de empresa/cliente do projeto original foram substituídos por pseudônimos. Não foi testado fora do ambiente original.
