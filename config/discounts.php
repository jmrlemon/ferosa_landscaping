<?php

$vatRegistration = env('BUSINESS_VAT_REGISTERED');

return [
    /*
     * Legal discount schemes are fail-closed. Enable these only after Ferosa's
     * accountant or BIR RDO confirms the business and catalogue configuration.
     */
    'enabled' => (bool) env('PH_DISCOUNTS_ENABLED', false),
    'statutory_20_enabled' => (bool) env('PH_DISCOUNT_STATUTORY_20_ENABLED', false),
    'bnpc_5_enabled' => (bool) env('PH_DISCOUNT_BNPC_5_ENABLED', false),
    // Keep BNPC unavailable until booklet, four-item-kind, and cross-channel weekly-spend checks exist.
    'bnpc_5_workflow_complete' => false,

    'vat_registered' => $vatRegistration === null
        ? null
        : filter_var($vatRegistration, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
    'prices_include_vat' => (bool) env('BUSINESS_PRICES_INCLUDE_VAT', true),
    'vat_rate_percent' => (int) env('BUSINESS_VAT_RATE_PERCENT', 12),
    // No per-product VAT class exists yet, so only show an aggregate VAT amount
    // after the accountant confirms every checkout catalogue item is VATable.
    'all_checkout_products_vatable' => (bool) env('BUSINESS_ALL_CHECKOUT_PRODUCTS_VATABLE', false),
    // Service VAT treatment is confirmed separately from the product catalogue.
    'all_services_vatable' => (bool) env('BUSINESS_ALL_SERVICES_VATABLE', false),

    // JAO No. 24-02 (2024); JMC No. 01-2022 governs online complaints.
    'bnpc_weekly_limit' => (string) env('PH_DISCOUNT_BNPC_WEEKLY_LIMIT', '2500.00'),
    'timezone' => 'Asia/Manila',
];
