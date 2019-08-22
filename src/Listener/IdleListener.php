<?php

# declare(strict_types=1);

namespace JsonStreamingParser\Listener;

/**
 * Base listener which does nothing.
 */
class IdleListener implements ListenerInterface
{
    public function startDocument()
    {
    }

    public function endDocument()
    {
    }

    public function startObject()
    {
    }

    public function endObject()
    {
    }

    public function startArray()
    {
    }

    public function endArray()
    {
    }

    public function key(string $key)
    {
    }

    public function value($value)
    {
    }

    public function whitespace(string $whitespace)
    {
    }
}
