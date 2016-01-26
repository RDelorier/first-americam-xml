<?php


use Codesmith\FirstAmericanXml\Xml;

class XmlTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | ArrayToXml
    |--------------------------------------------------------------------------
    */
    /** @test */
    public function empty_array_should_produce_valid_xml()
    {
        $this->assertArrayToXml('',[]);
    }

    /** @test */
    public function array_key_should_map_tp_key_KEY_attribute()
    {
        $this->assertArrayToXml('<FIELD KEY="foo">bar</FIELD>', ['foo' => 'bar']);
    }

    /**
     * Assert that the Xml::arrayToXml function correctly converts
     * the attributes to the expected string.
     *
     * @param string $expected should be field nodes to be wrapped in transaction
     * @param array $attributes
     */
    protected function assertArrayToXml($expected,$attributes)
    {
        $expected = <<<XML
<?xml version="1.0"?>
<TRANSACTION><FIELDS>$expected</FIELDS></TRANSACTION>
XML;

        $this->assertXmlStringEqualsXmlString(
            $expected,
            Xml::arrayToXml($attributes)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | XmlToArray
    |--------------------------------------------------------------------------
    */

    /** @test */
    public function array_key_should_map_to_element_name()
    {
        $element = new SimpleXMLElement('<test/>');
        $this->assertEquals(['test' => ''], Xml::elementsToArray([$element]));
    }

    /** @test */
    public function value_should_become_array_if_element_has_children()
    {
        $element = new SimpleXMLElement('<foo/>');
        $element->addChild('bar');

        $this->assertArrayHasKey('bar', Xml::elementsToArray([$element])['foo']);
    }

    /** @test */
    public function value_should_become_array_if_duplicate_elements_exist()
    {
        $elements = [
            new SimpleXMLElement('<field>1</field>'),
            new SimpleXMLElement('<field>2</field>')
        ];

        $this->assertEquals(['field' => [1,2]], Xml::elementsToArray($elements));
    }

    /** @test */
    public function key_attribute_should_override_element_name()
    {
        $elements = [
            new SimpleXMLElement('<field KEY="bar"></field>')
        ];

        $this->assertArrayHasKey('bar', Xml::elementsToArray($elements));
    }
}