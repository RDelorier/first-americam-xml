<?php


class QueryOperationsTest extends TestCase
{
    /** @test */
    public function query_response_should_combine_transaction_data()
    {
        $resp = $this->gateway->query('SALE');
        $this->assertEquals($resp->records_found, count($resp->transactions));
    }
}