<?php

declare(strict_types=1);

namespace ShahGhasiAdil\LaravelBrunoGenerator\Services\Serializers;

use ShahGhasiAdil\LaravelBrunoGenerator\Contracts\FormatSerializerInterface;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\AuthBlock;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\BrunoRequest;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\EnvironmentVariable;
use ShahGhasiAdil\LaravelBrunoGenerator\DTO\RequestBody;
use ShahGhasiAdil\LaravelBrunoGenerator\Enums\BodyType;
use Symfony\Component\Yaml\Yaml;

/**
 * Serializes requests and environments to the OpenCollection YAML format
 * used by Bruno (https://docs.usebruno.com/opencollection-yaml/overview).
 */
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
            'info' => $this->serializeInfo($request),
            'http' => $this->serializeHttp($request),
        ];

        $runtime = $this->serializeRuntime($request);
        if ($runtime !== []) {
            $data['runtime'] = $runtime;
        }

        // Add settings section
        if ($request->settings !== null) {
            $data['settings'] = $request->settings->toArray();
        }

        // Add docs section (full Markdown, no truncation)
        if ($request->docs !== null) {
            $data['docs'] = $request->docs;
        }

        $indentSpaces = $this->config['advanced']['yaml_options']['indent_spaces'] ?? 2;

        return Yaml::dump($data, 4, $indentSpaces);
    }

    /**
     * Serialize the info section.
     *
     * @return array<string, mixed>
     */
    private function serializeInfo(BrunoRequest $request): array
    {
        $info = [
            'name' => $request->name,
            'type' => 'http',
            'seq' => $request->sequence,
        ];

        if ($request->tags !== []) {
            $info['tags'] = array_values($request->tags);
        }

        return $info;
    }

    /**
     * Serialize the http section.
     *
     * @return array<string, mixed>
     */
    private function serializeHttp(BrunoRequest $request): array
    {
        $http = [
            'method' => strtoupper($request->method),
            'url' => $request->url,
        ];

        $params = $this->serializeParams($request);
        if ($params !== []) {
            $http['params'] = $params;
        }

        if ($request->hasHeaders()) {
            $http['headers'] = $this->serializeHeaders($request->headers);
        }

        if ($request->hasBody() && $request->body !== null) {
            $http['body'] = $this->serializeBody($request->body);
        }

        if ($request->auth !== null && ! $request->auth->isNone()) {
            $http['auth'] = $request->auth->isInherit() ? 'inherit' : $this->serializeAuth($request->auth);
        }

        return $http;
    }

    /**
     * Serialize query and path params to a flat OpenCollection params array.
     *
     * @return array<int, array<string, string>>
     */
    private function serializeParams(BrunoRequest $request): array
    {
        $params = [];

        foreach ($request->queryParams as $name => $value) {
            $params[] = ['name' => $name, 'value' => $value, 'type' => 'query'];
        }

        foreach ($request->pathVariables as $name => $value) {
            $params[] = ['name' => $name, 'value' => $value, 'type' => 'path'];
        }

        return $params;
    }

    /**
     * Serialize headers to OpenCollection's array-of-objects shape.
     *
     * @param  array<string, string>  $headers
     * @return array<int, array<string, string>>
     */
    private function serializeHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $name => $value) {
            $result[] = ['name' => $name, 'value' => $value];
        }

        return $result;
    }

    /**
     * Serialize body to OpenCollection's {type, data} shape.
     *
     * @return array<string, mixed>
     */
    private function serializeBody(RequestBody $body): array
    {
        $data = ['type' => $body->type->value];

        if (in_array($body->type, [BodyType::FORM_URLENCODED, BodyType::MULTIPART_FORM], true) && $body->content !== []) {
            $entries = [];
            foreach ($body->content as $key => $value) {
                $entries[] = ['name' => $key, 'value' => $this->stringifyFieldValue($value)];
            }
            $data['data'] = $entries;

            return $data;
        }

        if ($body->raw !== null) {
            $data['data'] = $body->raw;

            return $data;
        }

        if ($body->content !== []) {
            $json = json_encode($body->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $data['data'] = $json === false ? '' : $json;
        }

        return $data;
    }

    /**
     * Stringify a form/multipart field value. Arrays and objects are
     * JSON-encoded rather than cast, which would otherwise produce the
     * literal string "Array" and a PHP warning for nested/array rules
     * (e.g. `tags.*`, `user.name`) combined with a file/image field.
     */
    private function stringifyFieldValue(mixed $value): string
    {
        if (is_array($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES);

            return $json === false ? '' : $json;
        }

        return (string) $value;
    }

    /**
     * Serialize auth to OpenCollection's flat {type, ...credentials} shape.
     *
     * @return array<string, mixed>
     */
    private function serializeAuth(AuthBlock $auth): array
    {
        return array_merge(['type' => $auth->type->value], $auth->config);
    }

    /**
     * Serialize scripts and tests into runtime.scripts.
     *
     * @return array<string, mixed>
     */
    private function serializeRuntime(BrunoRequest $request): array
    {
        $scripts = [];

        if ($request->preRequestScript !== null) {
            $scripts[] = ['type' => 'before-request', 'code' => $request->preRequestScript];
        }

        if ($request->postResponseScript !== null) {
            $scripts[] = ['type' => 'after-response', 'code' => $request->postResponseScript];
        }

        if ($request->tests !== null) {
            $scripts[] = ['type' => 'tests', 'code' => $request->tests];
        }

        return $scripts === [] ? [] : ['scripts' => $scripts];
    }

    /**
     * Serialize environment to OpenCollection YAML format.
     *
     * @param  array<string, string>  $variables
     */
    /**
     * @param  array<int, EnvironmentVariable>  $variables
     */
    public function serializeEnvironment(string $name, array $variables): string
    {
        $vars = [];

        foreach ($variables as $var) {
            $entry = [
                'name' => $var->name,
                // Secret values are never written to disk; Bruno manages
                // them separately (OS keychain / AES256 fallback).
                'value' => $var->secret ? '' : $var->value,
                'enabled' => true,
                'secret' => $var->secret,
            ];

            if ($var->description !== null) {
                $entry['description'] = $var->description;
            }

            $vars[] = $entry;
        }

        $data = [
            'name' => $name,
            'variables' => $vars,
        ];

        $indentSpaces = $this->config['advanced']['yaml_options']['indent_spaces'] ?? 2;

        return Yaml::dump($data, 4, $indentSpaces);
    }

    /**
     * Collection-level auth for YAML collections needs the (currently
     * unverified) opencollection.yml collection-root schema; not yet
     * implemented. See ROADMAP.md Phase 2.
     */
    public function serializeCollectionAuth(AuthBlock $auth): ?string
    {
        return null;
    }

    public function getFileExtension(): string
    {
        return '.yml';
    }
}
