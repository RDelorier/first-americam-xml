<?php


class SaleTest extends TestCase
{
    /** @test */
    public function sale_and_auth_should_be_successfull()
    {
        $this->assertTrue(
            $this->gateway->sale($this->fullRequestData())->successful()
        );
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