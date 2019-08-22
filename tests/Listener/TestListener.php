<?php

# declare(strict_types=1);

namespace JsonStreamingParser\Test\Listener;

use JsonStreamingParser\Listener\ListenerInterface;
use JsonStreamingParser\Listener\PositionAwareInterface;

class TestListener implements ListenerInterface, PositionAwareInterface
{
    public $order = [];

    public $positions = [];

    protected $currentLine;
    protected $currentChar;

    public function setFilePosition(int $line, int $char)
    {
        $this->currentLine = $line;
        $this->currentChar = $char;
    }

    public function startDocument()
    {
        $this->order[] = __FUNCTION__;
    }

    public function endDocument()
    {
        $this->order[] = __FUNCTION__;
    }

    public function startObject()
    {
        $this->order[] = __FUNCTION__;
    }

    public function endObject()
    {
        $this->order[] = __FUNCTION__;
    }

    public function startArray()
    {
        $this->order[] = __FUNCTION__;
    }

    public function endArray()
    {
        $this->order[] = __FUNCTION__;
    }

    public function key(string $key)
    {
        $this->order[] = __FUNCTION__.' = '.self::stringify($key);
    }

    public function value($value)
    {
        $this->order[] = __FUNCTION__.' = '.self::stringify($value);
        $this->positions[] = ['value' => $value, 'line' => $this->currentLine, 'char' => $this->currentChar];
    }

    public function whitespace(string $whitespace)
    {
    }

    private static function stringify($value)
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (null === $value) {
            return 'NULL';
        }

        return '' !== $value ? $value : var_export($value, true);
    }
}
