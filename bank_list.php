<?php
require_once __DIR__ . '/list_view.php';
require_module('bank');
render_list([
    'table'       => 'bankvaluations',
    'title'       => 'Bank Valuations',
    'nav'         => 'bank',
    'type'        => 'bankvaluation',
    'value_field' => 'market_value',
    'value_label' => 'Market Value',
    'form_page'   => 'bank_form.php',
    'can_preview' => true,
]);
