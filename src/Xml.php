<?php


namespace Delorier\FirstAmericanXml;


class Xml
{

    public static function arrayToXml($attributes)
    {
        $element = new \SimpleXMLElement('<TRANSACTION/>');
        $fields  = $element->addChild('FIELDS');

        foreach ($attributes as $key => $value) {
            $field = $fields->addChild('FIELD', $value);
            $field->addAttribute('KEY', $key);
        }

        return $element->asXML();
    }


    /**
     * Converts an array of SimpleXMLElement associative array
     *
     * @param $elements
     *
     * @return array
     */
    public static function elementsToArray($elements)
    {
        $result = [];

        /**
         * @var \SimpleXMLElement $element help our ide :)
         */
        foreach ($elements as $element) {
            $name = (string)$element['KEY'] ?: $element->getName();

            // if the key exists then there are multiple elements
            // with the same name and this should be an array
            if (isset($result[$name]) && !is_array($result[$name])) {
                $result[$name] = [$result[$name]];
            }

            if (count($element->children())) {
                $value = self::elementsToArray($element->children());
            } else {
                $value = (string)$element;
            }

            // again if its an array push else set
            if (isset($result[$name])) {
                array_push($result[$name], $value);
            } else {
                $result[$name] = $value;
            }
        }

        return $result;
    }
}