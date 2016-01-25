<?php

namespace Codesmith\FirstAmericanXml;

use Codesmith\FirstAmericanXml\CimQueryResponse;
use Codesmith\FirstAmericanXml\VoidResponse;
use GuzzleHttp\Client;

/**
 * Class Gateway
 *
 * Provide access to first american xml api.
 * Follow the link below for api documentation.
 * http://www.goemerchant.com/content/pdfs/gatewayapi_dn_xml.pdf
 *
 * @package Codesmith\FirstAmericanXml
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

    /*
    |--------------------------------------------------------------------------
    | Query Operations
    |--------------------------------------------------------------------------
    |
    | Methods to allow gateway to query the transaction database over a given
    | date range.
    |
    */
    public function query($args)
    {
        $response = $this->send('Query', $args);

        $result = [
            'records_found' => (int)$response->records_found,
            'status'        => (int)$response->status,
            'error'         => (string)$response->error,
            'transactions'  => []
        ];

        $result = $this->formatMultiRecordResponse(
            $result,
            [
                'trans_type',
                'trans_status',
                'settled',
                'credit_void',
                'order_id',
                'reference_number',
                'trans_time',
                'card_type',
                'amount',
                'amount_settled',
                'amount_credited',
                'posted_by',
                'signature',
                'error'
            ],
            $response,
            $response['records_found']
        );

        return new Response($response->response, $result);
    }

    /**
     * @param $result
     * @param $keys
     * @param $response
     *
     * @return mixed
     */
    private function formatMultiRecordResponse($result, $keys, $response, $recordCount)
    {
        foreach (range(1, $recordCount) as $i) {

            $values = array_reduce($keys, function ($r, $key) use ($response, $i) {
                $r[$key] = $response->get($key . "$i");
                return $r;
            }, []);

            $result['transactions'][] = array_combine($keys, $values);
        }

        return $result;
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
     * @return VoidResponse
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

        $response = $this->send('void', $fields);

        $result = [
            'total_transactions_voided' => (int)$response->total_transactions_voided,
            'transactions'              => []
        ];

        $result = $this->formatMultiRecordResponse(
            $result,
            ['status', 'response', 'reference_number', 'error'],
            $response,
            count($response->original_args) - 1
        );

        return new Response($response->response, $result);
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
        $this->sendCim('cim_delete',[],$refNum);
    }

    /**
     * Create a new customer.
     *
     * @param $refNum
     */
    public function cimCreate($refNum)
    {
        $this->sendCim('cim_insert',[],$refNum);
    }

    /**
     * Edit an existing customer.
     *
     * @param $refNum
     * @param $args
     */
    public function cimEdit($refNum, $args)
    {
        $this->sendCim('cim_edit',$args,$refNum);
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
     * @param $args
     *
     * @return Response
     */
    public function settle($args)
    {
        $response = $this->send('settle', $args);

        $result = [
            'total_transactions_settled' => (int)$response->total_transactions_settled,
            'total_amount_settled'       => (int)$response->total_amount_settled,
            'original_args'              => $response->original_args,
            'transactions'               => []
        ];

        $result = $this->formatMultiRecordResponse(
            $result,
            ['status', 'response', 'reference_number', 'batch_number', 'settle_amount', 'error'],
            $response,
            $response->original_args['total_number_transactions']
        );

        return new Response($response->response, $result);
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
     * @param $args
     *
     * @return Response
     */
    public function credit($args)
    {
        $response = $this->send('credit', $args);

        $result = [
            'total_transactions_credited' => (int)$response->total_transactions_credited,
            'original_args'               => $response->original_args,
            'transactions'                => []
        ];

        $result = $this->formatMultiRecordResponse(
            $result,
            ['status', 'response', 'reference_number', 'credit_amount', 'error'],
            $response,
            $response->original_args['total_number_transactions']
        );

        return new Response($response->response, $result);
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
        return $this->send('recurring_modify', $args+[
                'reference_number' => $refNum,
                'is_ach' => $isAch
            ]);
    }

}