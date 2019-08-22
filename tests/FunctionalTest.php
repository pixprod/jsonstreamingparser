<?php

# declare(strict_types=1);

namespace JsonStreamingParser\Test;

use JsonStreamingParser\Exception\ParsingException;
use JsonStreamingParser\Parser;
use JsonStreamingParser\Test\Listener\FilePositionListener;
use JsonStreamingParser\Test\Listener\StopEarlyListener;
use JsonStreamingParser\Test\Listener\TestListener;
use PHPUnit\Framework\TestCase;

class FunctionalTest extends TestCase
{
    /**
     * @var TestListener
     */
    private $listener;

    protected function setUp()
    {
        $this->listener = new TestListener();
    }

    public function testTraverseOrder()
    {
        $parser = new Parser(fopen(__DIR__.'/data/example.json', 'rb'), $this->listener);
        $parser->parse();

        $this->assertSame(
            [
                'startDocument',
                'startArray',
                'startObject',
                'key = name',
                'value = example document for wicked fast parsing of huge json docs',
                'key = integer',
                'value = 123',
                'key = totally sweet scientific notation',
                'value = -1.23123',
                'key = unicode? you betcha!',
                'value = ú™£¢∞§♥',
                'key = zero character',
                'value = 0',
                'key = null is boring',
                'value = NULL',
                'endObject',
                'startObject',
                'key = name',
                'value = another object',
                'key = cooler than first object?',
                'value = true',
                'key = nested object',
                'startObject',
                'key = nested object?',
                'value = true',
                'key = is nested array the same combination i have on my luggage?',
                'value = true',
                'key = nested array',
                'startArray',
                'value = 1',
                'value = 2',
                'value = 3',
                'value = 4',
                'value = 5',
                'endArray',
                'endObject',
                'key = false',
                'value = false',
                'endObject',
                'endArray',
                'endDocument',
            ],
            $this->listener->order
        );
    }

    public function testListenerGetsNotifiedAboutPositionInFileOfDataRead()
    {
        $parser = new Parser(fopen(__DIR__.'/data/dateRanges.json', 'rb'), $this->listener);
        $parser->parse();

        $this->assertSame(
            [
                ['value' => '2013-10-24', 'line' => 5, 'char' => 34],
                ['value' => '2013-10-25', 'line' => 5, 'char' => 59],
                ['value' => '2013-10-26', 'line' => 6, 'char' => 34],
                ['value' => '2013-10-27', 'line' => 6, 'char' => 59],
                ['value' => '2013-11-01', 'line' => 10, 'char' => 44],
                ['value' => '2013-11-10', 'line' => 10, 'char' => 69],
            ],
            $this->listener->positions
        );
    }

    public function testCountsLongLinesCorrectly()
    {
        $value = str_repeat('!', 10000);
        $longStream = self::getMemoryStream(<<<JSON
[
  "${value}",
  "${value}"
]
JSON
        );

        $parser = new Parser($longStream, $this->listener);
        $parser->parse();

        unset($this->listener->positions[0]['value'], $this->listener->positions[1]['value']);

        $this->assertSame(
            [
                ['line' => 2, 'char' => 10004],
                ['line' => 3, 'char' => 10004],
            ],
            $this->listener->positions
        );
    }

    public function testThrowsParingError()
    {
        $parser = new Parser(self::getMemoryStream('{ invalid json }'), $this->listener);

        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Parsing error in [1:3]');
        $parser->parse();
    }

    public function testUnicodeSurrogatePair()
    {
        $parser = new Parser(self::getMemoryStream('["Treble clef: \\uD834\\uDD1E!"]'), $this->listener);
        $parser->parse();

        $this->assertSame(
            [
                'startDocument',
                'startArray',
                'value = Treble clef: 𝄞!',
                'endArray',
                'endDocument',
            ],
            $this->listener->order
        );
    }

    public function testMalformedUnicodeLowSurrogate()
    {
        $parser = new Parser(self::getMemoryStream('["\\uD834abc"]'), $this->listener);

        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage("Expected '\\u' following a Unicode high surrogate. Got: ab");
        $parser->parse();
    }

    public function testInvalidUnicodeHighSurrogate()
    {
        $parser = new Parser(self::getMemoryStream('["\\uAAAA\\uDD1E"]'), $this->listener);

        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Missing high surrogate for Unicode low surrogate.');
        $parser->parse();
    }

    public function testInvalidUnicodeLowSurrogate()
    {
        $parser = new Parser(self::getMemoryStream('["\\uD834\\uAAAA"]'), $this->listener);

        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Invalid low surrogate following Unicode high surrogate.');
        $parser->parse();
    }

    public function testFilePositionIsCalledIfDefined()
    {
        $filePositionListener = new FilePositionListener();

        $parser = new Parser(fopen(__DIR__.'/data/example.json', 'rb'), $filePositionListener);
        $parser->parse();

        $this->assertTrue($filePositionListener->called);
    }

    public function testStopEarly()
    {
        $listener = new StopEarlyListener();
        $parser = new Parser(self::getMemoryStream('["abc","def"]'), $listener);
        $listener->setParser($parser);
        $parser->parse();

        $this->assertSame(
            [
                'startDocument',
                'startArray',
            ],
            $listener->order
        );
    }

    /**
     * @dataProvider providerTestVariousErrors
     */
    public function testVariousErrors(string $data, string $errorMessage)
    {
        $parser = new Parser(self::getMemoryStream($data), $this->listener);

        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage($errorMessage);
        $parser->parse();
    }

    public function providerTestVariousErrors(): iterable
    {
        yield ['{"a"}', "Expected ':' after key."];
        yield ['{"a":"b"]', "Expected ',' or '}' while parsing object. Got: ]"];
        yield ['["a","b".', "Expected ',' or ']' while parsing array. Got: ."];
        yield ['{"price":29..95}', 'Cannot have multiple decimal points in a number.'];
        yield ['{"count":10e1.5}', 'Cannot have a decimal point in an exponent.'];
        yield ['{"count":10e15e10}', 'Cannot have multiple exponents in a number.'];
        yield ['{"count":10-15}', "Can only have '+' or '-' after the 'e' or 'E' in a number."];
        yield ['123', 'Document must start with object or array.'];
        yield ['[123,456]]', 'Expected end of document.'];
        yield ['["\x7f"]', 'Expected escaped character after backslash. Got: x'];
    }

    /**
     * @return resource
     */
    private static function getMemoryStream(string $content)
    {
        $stream = fopen('php://memory', 'rwb');
        fwrite($stream, $content);
        fseek($stream, 0);

        return $stream;
    }
}
