<?php

declare(strict_types=1);

describe('config/bruno-generator.php', function () {
    test('defaults output_format to yaml', function () {
        expect(config('bruno-generator.output_format'))->toBe('yaml');
    });

    test('defaults bruno_compatibility to v4', function () {
        expect(config('bruno-generator.bruno_compatibility'))->toBe('v4');
    });

    test('defaults secrets.variable_names to authToken', function () {
        expect(config('bruno-generator.secrets.variable_names'))->toBe(['authToken']);
    });
});
