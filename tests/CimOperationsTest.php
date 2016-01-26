<?php


class CimOperationsTest extends TestCase
{

    protected $refNum;

    /** @test */
    public function cim_query_should_locate_customer_by_ref_num_or_return_error_message()
    {
        $this->assertEquals(1, $this->gateway->cimQuery(1)->cim_record['cim_ref_num']);
        $this->assertEquals(
            'The search criteria provided returned 0 results. It must correspond to a previous customer you have submitted.',
            $this->gateway->cimQuery($this->random())->error);
    }

    /** @test */
    public function cim_sale_should_return_error_if_invalid_ref_num()
    {
        $this->assertFalse(
            $this->gateway
                ->cimSaleAndSave($this->random(), [])
                ->successful()
        );
    }

    /** @test */
    public function cim_sale_and_save_should_create_a_new_customer()
    {
        $ref = $this->random();
        $data = $this->fullRequestData(["order_id" => $ref]);

        //assert the order_id is not used already
        $this->assertFalse($this->gateway->cimQuery($ref)->successful());

        $response = $this->gateway->cimSaleAndSave($ref, $data);

        $this->assertEquals('APPROVED', $response->auth_response);
        $this->assertTrue($this->gateway->cimQuery($ref)->successful());
    }

    protected function fullRequestData($attributes = [])
    {
        return array_merge(
            $this->approvedCardData(),
            $this->ownerData(),
            [
                "order_id" => $this->random(),
                'total'    => '1.00'
            ],
            $attributes
        );
    }
}