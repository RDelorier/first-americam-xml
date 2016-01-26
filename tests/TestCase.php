<?php


use Delorier\FirstAmericanXml\Gateway;

class TestCase extends PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        if (file_exists(__DIR__ . '/../.env')) {
            $dotenv = new Dotenv\Dotenv(__DIR__ . '/../');
            $dotenv->load();
        }

        date_default_timezone_set('America/New_York');
    }

    /** @var  Gateway */
    protected $gateway;

    /** @var  \Faker\Generator */
    protected $faker;

    protected function setUp()
    {
        parent::setUp();
        $this->gateway = new Gateway('', '', '');
        $this->faker   = \Faker\Factory::create();
    }

    protected function assertApproved($response)
    {
        $this->assertEquals('APPROVED', $response->auth_response);

        return $this;
    }

    protected function assertDeclined($response)
    {
        $this->assertEquals('DECLINED', $response->auth_response);

        return $this;
    }

    /**
     * @return string
     */
    protected function random()
    {
        return md5(time());
    }

    protected function declinedCardData()
    {
        return array_merge($this->approvedCardData(), [
                'card_number' => $this->faker->creditCardNumber
            ]
        );
    }

    public function approvedCardData()
    {
        return [
            'card_name'   => $this->faker->creditCardType,
            'card_number' => '4111111111111111',
            'card_exp'    => date('my'),
            'card_cvv2'   => '123'
        ];
    }

    public function ownerData()
    {
        return [
            'owner_name'    => $this->faker->name,
            'owner_street'  => $this->faker->streetAddress,
            'owner_city'    => $this->faker->city,
            'owner_state'   => $this->faker->state,
            'owner_zip'     => $this->faker->postcode,
            'owner_country' => $this->faker->country,
            'owner_email'   => $this->faker->safeEmail,
            'owner_phone'   => $this->faker->phoneNumber,
        ];
    }
}