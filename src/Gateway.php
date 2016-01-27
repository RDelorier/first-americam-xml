<?php

namespace Delorier\FirstAmericanXml;

use Delorier\FirstAmericanXml\CimQueryResponse;
use Delorier\FirstAmericanXml\VoidResponse;
use GuzzleHttp\Client;

/**
 * Class Gateway
 *
 * Provide access to first american xml api.
 * Follow the link below for api documentation.
 * http://www.goemerchant.com/content/pdfs/gatewayapi_dn_xml.pdf
 *
 * @package Delorier\FirstAmericanXml
 */
class Gateway
{
    /**
     * @var string
     */
    private $transactionCenterId;

    /**
     * @var string
     */
    private $gatewayId;

    /**
     * @var string
     */
    private $processorId;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $apiUrl = 'https://secure.goemerchant.com/secure/gateway/xmlgateway.aspx';

    /**
     * Gateway constructor.
     *
     * @param string $transactionCenterId
     * @param string $gatewayId
     * @param string $processorId
     */
    public function __construct($transactionCenterId, $gatewayId, $processorId)
    {
        $this->transactionCenterId = $transactionCenterId;
        $this->gatewayId           = $gatewayId;
        $this->processorId         = $processorId;
        $this->client              = new Client([
            'defaults' => [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=UTF8'
                ]
            ]
        ]);
    }

    /**
     * Send http request to api and return response.
     *
     * @param string $operation the operation type to perform
     * @param array $args
     *
     * @return Response
     */
    public function send($operation_type, array $args)
    {
        $fields = array_merge(
            $this->authParams(),
            $args,
            compact('operation_type')
        );

        $response = new Response(
            $this->client->post($this->apiUrl, [
                'body' => Xml::arrayToXml($fields),
            ])
        );

        $response->offsetSet('original_args', $args);

        return $response;
    }

    /**
     * Get credentials to send to api.
     *
     * @return array
     */
    private function authParams()
    {
        if (getenv('ENV') !== 'testing') {
            return [
                'transaction_center_id' => $this->transactionCenterId,
                'gateway_id'            => $this->gatewayId,
                'processor_id'          => $this->processorId
            ];
        }

        return [
            'merchant'   => '1264',
            'password'   => 'password',
            'gateway_id' => 'a91c38c3-7d7f-4d29-acc7-927b4dca0dbe'
        ];
    }

    /**
     * Flatten transactions to indexed array.
     *
     * @param $transactions
     *
     * @return array
     */
    public function flattenTransactions($transactions)
    {
        $result = [];
        $index  = 1;

        foreach ($transactions as $transaction) {
            foreach ($transaction as $key => $val) {
                $result[$key . $index] = $val;
            }
            $index++;
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Operations
    |--------------------------------------------------------------------------
    |
    | Methods to allow gateway to query the transaction database over a given
    | date range.
    |
    */
    /**
     * Query transactions database.
     *
     * @param string $type
     * @param null|string $begin
     * @param null|string $end
     * @param array $extra
     *
     * @return MultiTransactionResponse
     *
     */
    public function query($type, $begin = null, $end = null, $extra = [])
    {
        return MultiTransactionResponse::make(
            $this->send('Query', [
                    'trans_type' => $type,
                    'begin_date' => $begin ?: date('mdy'),
                    'end_date'   => $end ?: date('mdy')
                ] + $extra
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Auth/Sale Operations
    |--------------------------------------------------------------------------
    |
    | The following methods may be used to create transactions on new customers
    | which you do not want to save as cim records.
    |
    */

    /**
     * Perform Auth only request.
     *
     * @param $args
     *
     * @return Response
     */
    public function auth($args)
    {
        return $this->send('Auth', $args);
    }

    /**
     * Perform Sale, this method charges the customers card.
     *
     * @param $args
     *
     * @return Response
     */
    public function sale($args)
    {
        return $this->send('Sale', $args);
    }

    /*
    |--------------------------------------------------------------------------
    | Void Operations
    |--------------------------------------------------------------------------
    |
    | The following operations may be only be applied on pending transactions.
    | Credit Card transactions that have not been settled.
    | Ach transactions that have not been posted.
    |
    */

    /**
     * Void transactions for full amount.
     *
     * @param array|string|int $transactions Either a single id of array of ids.
     *
     * @return MultiTransactionResponse
     */
    public function void($transactions)
    {
        $transactions = is_array($transactions) ?: [$transactions];

        $fields = [
            'total_number_transactions' => count($transactions)
        ];

        foreach ($transactions as $i => $refNum) {
            $fields['reference_number' . ($i + 1)] = $refNum;
        }

        return MultiTransactionResponse::make($this->send('void', $fields));
    }

    /*
    |--------------------------------------------------------------------------
    | Customer Information System (CIM) Operations
    |--------------------------------------------------------------------------
    |
    | The following operations interact with customers through the cim database.
    |
    */

    /**
     * Send request with cim reference number.
     *
     * @param $operation
     * @param $args
     * @param $refNum
     *
     * @return Response
     */
    private function sendCim($operation, $args, $refNum)
    {
        return $this->send(
            $operation,
            $args + ['cim_ref_num' => $refNum]
        );
    }

    /**
     * Query cim users.
     *
     * @param $refNum
     *
     * @return Response
     */
    public function cimQuery($refNum)
    {
        return $this->sendCim('cim_query', [], $refNum);
    }

    /**
     * Run sale operation using existing cim data.
     *
     * @param $refNum
     * @param $args
     *
     * @return Response
     */
    public function cimSale($refNum, $args)
    {
        return $this->sendCim('cim_sale', $args, $refNum);
    }

    /**
     * Run a sale operation and create/update cim record.
     *
     * @param $refNum
     * @param $args
     *
     * @return Response
     */
    public function cimSaleAndSave($refNum, $args)
    {
        return $this->sendCim('sale', $args, $refNum);
    }

    /**
     * Delete a customer.
     *
     * @param $refNum
     */
    public function cimDelete($refNum)
    {
        $this->sendCim('cim_delete', [], $refNum);
    }

    /**
     * Create a new customer.
     *
     * @param $refNum
     */
    public function cimCreate($refNum)
    {
        $this->sendCim('cim_insert', [], $refNum);
    }

    /**
     * Edit an existing customer.
     *
     * @param $refNum
     * @param $args
     */
    public function cimEdit($refNum, $args)
    {
        $this->sendCim('cim_edit', $args, $refNum);
    }

    /*
    |--------------------------------------------------------------------------
    | SettleOperations
    |--------------------------------------------------------------------------
    |
    | Settles Auth and Sale transactions that have not yet been settled.
    |
    */

    /**
     * Settle pending transactions.
     *
     * @param string|array $transactions
     * @param string $amount
     *
     * @return MultiTransactionResponse
     */
    public function settle($transactions, $amount = null)
    {
        // this lets the developer pass in a ref number and amount
        // if they are only settling a single transaction.
        if (!is_null($amount)) {
            $transactions = [
                [
                    'reference_number' => $transactions,
                    'settle_amount'    => $amount
                ]
            ];
        };

        $request = [
                'total_number_transactions' => count($transactions)
            ] + $this->flattenTransactions($transactions);

        return MultiTransactionResponse::make($this->send('settle', $request));
    }

    /*
    |--------------------------------------------------------------------------
    | Credit Operations
    |--------------------------------------------------------------------------
    |
    | Credit a customers account from a previously settled transaction
    |
    */

    /**
     * Credit previously settle transactions.
     *
     * @param string|array $transactions
     * @param string $amount
     * @param string $fee
     *
     * @return MultiTransactionResponse
     * @throws \Exception
     *
     */
    public function credit($transactions, $amount = null, $fee = '0.00')
    {
        //todo sanitise amount
        if (!is_null($amount)) {
            $transactions = [
                [
                    'reference_number' => $transactions,
                    'credit_amount'    => $amount,
                    'conv_fee'         => $fee,
                ]
            ];
        } else if (!is_array($transactions)) {
            throw new \Exception('Amount required when transactions is not an array');
        }

        $request = [
                'total_number_transactions' => count($transactions)
            ] + $this->flattenTransactions($transactions);

        return MultiTransactionResponse::make($this->send('credit', $request));
    }


    /*
    |--------------------------------------------------------------------------
    | Recurring Billing Operations
    |--------------------------------------------------------------------------
    |
    | The following operations interact with recurring charges.
    |
    */

    /**
     * Create or Modify recurring transactions.
     *
     * @param $args
     *
     * @return Response
     */
    public function recurringModify($refNum, $args, $isAch = 0)
    {
        return $this->send('recurring_modify', $args + [
                'reference_number' => $refNum,
                'is_ach'           => $isAch
            ]);
    }

}