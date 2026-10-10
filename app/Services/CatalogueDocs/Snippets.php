<?php

declare(strict_types=1);

namespace App\Services\CatalogueDocs;

use JsonException;

/**
 * Copy-paste examples for the public read-only catalogue. No credentials.
 */
final class Snippets
{
    public static function curl(string $url): string
    {
        return 'curl -sS '.self::shell($url);
    }

    public static function javascript(string $url, bool $json): string
    {
        try {
            $literal = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $literal = '""';
        }

        $read = $json ? 'response.json()' : 'response.text()';

        return "const response = await fetch({$literal});\n"
            ."if (!response.ok) {\n"
            ."  throw new Error(\"Catalogue request failed (\" + response.status + \")\");\n"
            ."}\n"
            ."const data = await {$read};\n"
            ."console.log(data);\n";
    }

    public static function python(string $url, bool $json): string
    {
        $literal = self::pythonString($url);
        if ($json) {
            return "import json\n"
                ."from urllib.request import urlopen\n"
                ."\n"
                ."with urlopen({$literal}) as response:\n"
                ."    data = json.load(response)\n"
                ."print(data)\n";
        }

        return "from urllib.request import urlopen\n"
            ."\n"
            ."with urlopen({$literal}) as response:\n"
            ."    text = response.read().decode()\n"
            ."print(text)\n";
    }

    /**
     * @return array{cursor: string, grok: string, grok_cli: string}
     */
    public static function mcp(string $name, string $url): array
    {
        try {
            $cursor = json_encode([
                'mcpServers' => [
                    $name => ['url' => $url],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $cursor = '{}';
        }

        $quoted = str_replace(['\\', '"'], ['\\\\', '\\"'], $url);

        return [
            'cursor' => $cursor."\n",
            'grok' => "[mcp_servers.{$name}]\nurl = \"{$quoted}\"\n",
            'grok_cli' => "grok mcp add --transport http {$name} {$url}\n",
        ];
    }

    private static function shell(string $url): string
    {
        return "'".str_replace("'", "'\\''", $url)."'";
    }

    private static function pythonString(string $url): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $url).'"';
    }
}
