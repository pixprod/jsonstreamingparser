<?php

# declare(strict_types=1);

namespace JsonStreamingParser\Listener;

/**
 * This basic geojson implementation of a listener simply constructs an in-memory
 * representation of the JSON document at the second level, this is useful so only
 * a single Feature will be kept in memory rather than the whole FeatureCollection.
 */
class GeoJsonListener implements ListenerInterface
{
    protected $json;

    protected $stack;
    protected $key;

    // Level is required so we know how nested we are.
    protected $level;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @param callable $callback
     */
    public function __construct($callback = null)
    {
        $this->callback = $callback;
    }

    public function getJson()
    {
        return $this->json;
    }

    public function startDocument()
    {
        $this->stack = [];
        $this->level = 0;
        // Key is an array so that we can can remember keys per level to avoid
        // it being reset when processing child keys.
        $this->key = [];
    }

    public function endDocument()
    {
        // w00t!
    }

    public function startObject()
    {
        ++$this->level;
        $this->stack[] = [];
        // Reset the stack when entering the second level
        if (2 === $this->level) {
            $this->stack = [];
            $this->key[$this->level] = null;
        }
    }

    public function endObject()
    {
        --$this->level;
        $obj = array_pop($this->stack);
        if (empty($this->stack)) {
            // doc is DONE!
            $this->json = $obj;
        } else {
            $this->value($obj);
        }
        // Call the callback when returning to the second level
        if (2 === $this->level && \is_callable($this->callback)) {
            \call_user_func($this->callback, $this->json);
        }
    }

    public function startArray()
    {
        $this->startObject();
    }

    public function endArray()
    {
        $this->endObject();
    }

    public function key(string $key)
    {
        $this->key[$this->level] = $key;
    }

    /**
     * Value may be a string, integer, boolean, null.
     */
    public function value($value)
    {
        $obj = array_pop($this->stack);
        if (!empty($this->key[$this->level])) {
            $obj[$this->key[$this->level]] = $value;
            $this->key[$this->level] = null;
        } else {
            $obj[] = $value;
        }
        $this->stack[] = $obj;
    }

    public function whitespace(string $whitespace)
    {
    }
}
