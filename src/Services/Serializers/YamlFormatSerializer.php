<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services\Serializers;

use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\FormatSerializerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\AuthBlock;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RequestBody;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;
use Symfony\Component\Yaml\Yaml;

final class YamlFormatSerializer implements FormatSerializerInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
    ) {}

    /**
     * Serialize request to OpenCollection YAML format.
     */
    public function serializeRequest(BrunoRequest $request): string
    {
        $data = [
            'info' => [
                'name' => $request->name,
                'description' => $request->description, // NO LENGTH LIMIT
                'seq' => $request->sequence,
            ],
            'http' => [
                'method' => strtoupper($request->method),
                'url' => $request->url,
            ],
        ];

        // Add headers if present
        if ($request->hasHeaders()) {
            $data['http']['headers'] = $request->headers;
        }

        // Add query params if present
        if ($request->hasQueryParams()) {
            $data['http']['params'] = ['query' => $request->queryParams];
        }

        // Add body if present
        if ($request->hasBody() && $request->body !== null) {
            $data['http']['body'] = $this->serializeBody($request->body);
        }

        // Add auth if present
        if ($request->hasAuth() && $request->auth !== null && ! $request->auth->isNone()) {
            $data['http']['auth'] = $this->serializeAuth($request->auth);
        }

        // Add runtime section for scripts and tests
        $runtime = [];
        if ($request->preRequestScript !== null) {
            $runtime['script'] = ['beforeRequest' => $request->preRequestScript];
        }
        if ($request->postResponseScript !== null) {
            $runtime['script'] = array_merge($runtime['script'] ?? [], ['afterResponse' => $request->postResponseScript]);
        }
        if ($request->tests !== null) {
            $runtime['tests'] = $request->tests;
        }
        if ($runtime !== []) {
            $data['runtime'] = $runtime;
        }

        // Add docs section (full Markdown, no truncation)
        if ($request->docs !== null) {
            $data['docs'] = $request->docs;
        }

        $indentSpaces = $this->config['advanced']['yaml_options']['indent_spaces'] ?? 2;

        return Yaml::dump($data, 4, $indentSpaces);
    }

    /**
     * Serialize body to YAML format.
     *
     * @return array<string, mixed>
     */
    private function serializeBody(RequestBody $body): array
    {
        $bodyData = [
            'mode' => $body->type->value,
        ];

        if ($body->type === BodyType::JSON && $body->content !== []) {
            $bodyData['json'] = $body->content;
        } elseif ($body->type === BodyType::FORM_URLENCODED && $body->content !== []) {
            $bodyData['formUrlencoded'] = $body->content;
        } elseif ($body->raw !== null) {
            $bodyData['raw'] = $body->raw;
        }

        return $bodyData;
    }

    /**
     * Serialize auth to YAML format.
     *
     * @return array<string, mixed>
     */
    private function serializeAuth(?AuthBlock $auth): array
    {
        if ($auth === null || $auth->isNone()) {
            return [];
        }

        return [
            'mode' => $auth->type->value,
            $auth->type->value => $auth->config,
        ];
    }

    /**
     * Serialize environment to YAML format.
     *
     * @param  array<string, string>  $variables
     */
    public function serializeEnvironment(string $name, array $variables): string
    {
        $data = [
            'name' => $name,
            'variables' => $variables,
        ];

        $indentSpaces = $this->config['advanced']['yaml_options']['indent_spaces'] ?? 2;

        return Yaml::dump($data, 4, $indentSpaces);
    }

    public function getFileExtension(): string
    {
        return '.yaml';
    }
}
