<?php

declare(strict_types=1);

namespace EsOrm\Tests\Unit\Support;

use EsOrm\Support\Json;
use JsonException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class JsonTest extends TestCase
{
    public function test_it_encodes_an_array_to_a_json_string(): void
    {
        self::assertSame('{"name":"Tom"}', Json::encode(['name' => 'Tom']));
    }

    public function test_it_encodes_unicode_without_escaping_when_asked(): void
    {
        self::assertSame('{"name":"张三"}', Json::encode(['name' => '张三'], JSON_UNESCAPED_UNICODE));
    }

    public function test_it_decodes_into_an_associative_array_by_default(): void
    {
        self::assertSame(['a' => ['b' => 1]], Json::decode('{"a":{"b":1}}'));
    }

    public function test_it_decodes_into_an_object_when_assoc_is_false(): void
    {
        $decoded = Json::decode('{"a":1}', false);

        self::assertInstanceOf(stdClass::class, $decoded);
        self::assertSame(1, $decoded->a);
    }

    public function test_it_throws_when_encoding_invalid_utf8(): void
    {
        $this->expectException(JsonException::class);

        Json::encode(['bad' => "\xB1\x31"]);
    }

    public function test_it_throws_when_decoding_malformed_json(): void
    {
        $this->expectException(JsonException::class);

        Json::decode('{invalid');
    }
}
