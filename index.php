<?php

error_reporting(E_ALL);
require('vendor/autoload.php');
$loader = new \Dotenv\Dotenv(__DIR__);
$loader->load();

date_default_timezone_set('America/New_York');
$gateway = new \Codesmith\FirstAmericanXml\Gateway('', '', '');
$faker   = \Faker\Factory::create();


dd($gateway->query('SALE')->toArray());
$sale = $gateway->sale([
    "order_id"     => md5(time()),
    'total'        => '1.00',
    'card_number'  => '4111111111111111',
    'card_exp'     => '0119',
    'cvv2'         => '123',
    'owner_street' => '123 Test St',
    'owner_zip'    => '12345-6789',
]);

$settle = $gateway->settle($sale->reference_number, '1.00');

$query = $gateway->query('SALE', null, null, ['order_id' => $sale->order_id]);

$credit = $gateway->credit($sale->reference_number, '1.00');



echo '<pre>';

foreach([$sale->toArray(),
         $settle->toArray(),
         $query->toArray(),
         $credit->toArray()] as $a){
    print_r($a);
}