<?php

namespace Codesmith\FirstAmericanXml;

use GuzzleHttp\Message\ResponseInterface;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

class Response extends Fluent
{
    /**
     * @var ResponseInterface
     */
    public $response;

    /**
     * Sets attributes from response xml.
     *
     * @param ResponseInterface $response
     * @param array $attributes
     */
    public function __construct(ResponseInterface $response, array $attributes = null)
    {
        $this->response = $response;

        if (is_null($attributes)) {
            $attributes = Xml::elementsToArray(
                $response->xml()->xpath('/RESPONSE/FIELDS/*')
            );
        }

        parent::__construct($attributes);
    }

    /**
     * Check status for success.
     *
     * @return bool
     */
    public function successful()
    {
        return $this->status == '1';
    }

    /**
     * Check status for bad request.
     *
     * @return bool
     */
    public function wasRequestBad()
    {
        return $this->status == '0';
    }

    /**
     * Trims error before returning it.
     *
     * @return string
     */
    public function getErrorAttribute()
    {
        return trim($this->get('error'));
    }

    /**
     * Parse missing fields from error string.
     *
     * @return array
     */
    public function missingFields()
    {
        $label = 'Required Fields Missing:  ';
        $error = $this->error;

        if (!$this->wasRequestBad() || !Str::startsWith($error, $label)) {
            return [];
        }

        $error = substr(
            $error,
            strlen($label),
            strpos($error, '.') - strlen($label)
        );

        return explode(' ', $error);
    }

    /**
     * Parse invalid fields and messages from error string.
     *
     * @return array
     */
    public function invalidFields()
    {
        if (!$this->wasRequestBad()) {
            return [];
        }

        $errors = array_slice(explode('Invalid ', $this->error), 1);
        $errors = array_map('trim', $errors);
        $fields = [];

        foreach ($errors as $error) {
            $delimeters = [': ', '. '];

            if (strpos($error, '. ') < strpos($error, ': ')) {
                $delimeters = array_reverse($delimeters);
            }

            list($name, $error) = explode($delimeters[0], $error, 2);
            list($value, $message) = explode($delimeters[1], $error, 2);
            $fields[$name] = compact('value', 'message');
        }

        return $fields;
    }

    /**
     * Adds mutator check before calling parent.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        $method = 'get' . studly_case($key) . 'Attribute';

        if (method_exists($this, $method)) {
            return call_user_func([$this, $method]);
        }

        return parent::__get($key);
    }

}