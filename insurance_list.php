<?php
require_once __DIR__ . '/list_view.php';
require_module('insurance');
render_list([
    'table'       => 'valuations',
    'title'       => 'Insurance Valuations',
    'nav'         => 'insurance',
    'type'        => 'valuation',
    'value_field' => 'assessed_value',
    'value_label' => 'Assessed Value',
    'form_page'   => 'insurance_form.php',
    'can_preview' => false,
]);
