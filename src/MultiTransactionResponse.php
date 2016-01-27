<?php


namespace Delorier\FirstAmericanXml;


use GuzzleHttp\Message\ResponseInterface;

class MultiTransactionResponse extends Response
{
    public function __construct(ResponseInterface $response, array $attributes = null)
    {
        parent::__construct($response, $attributes);
        $this->attributes = $this->groupTransactions($this->attributes);
    }

    /**
     * Create and return a new multi transaction response from regular response.
     *
     * @param Response $response
     *
     * @return static
     */
    public static function make(Response $response)
    {
        return new static($response->response, $response->attributes);
    }

    /**
     * Group array items by their postfix index. Indexes must be in order.
     *
     * @param array $attributes
     *
     * @return array
     */
    private function groupTransactions(array $attributes)
    {
        $data         = [];
        $transactions = [];
        $index        = 1;

        foreach ($attributes as $key => $value) {
            if (ends_with($key, $index) || (count($transactions) && ends_with($key, ++$index))) {
                $newKey = str_replace($index, '', $key);
                array_set($transactions, $index . '.' . $newKey, $value);
                continue;
            }

            $data[$key] = $value;
        }

        return $data + ['transactions' => array_values($transactions)];
    }
}