<?php

declare(strict_types=1);

namespace App\GraphQL\Resolver;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\CustomScalarType;

class ScalarResolver
{
    public static function createJSONType(): CustomScalarType
    {
        return new CustomScalarType([
            'name' => 'JSON',
            'description' => 'The JSON scalar type represents JSON values as specified by ECMA-404',
            'serialize' => function ($value) {
                if ($value === null) {
                    return null;
                }
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return $value; // Return as string if not valid JSON
                    }
                    return $decoded;
                }
                return $value;
            },
            'parseValue' => function ($value) {
                if ($value === null) {
                    return null;
                }
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Error('Value is not a valid JSON string: ' . json_last_error_msg());
                    }
                    return $decoded;
                }
                return $value;
            },
            'parseLiteral' => function (Node $valueNode) {
                if ($valueNode instanceof StringValueNode) {
                    $decoded = json_decode($valueNode->value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Error('Value is not a valid JSON string: ' . json_last_error_msg());
                    }
                    return $decoded;
                }
                throw new Error('Can only parse strings to JSON but got: ' . $valueNode->kind);
            }
        ]);
    }
}