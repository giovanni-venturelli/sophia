<?php

namespace App\Component;

use ReflectionObject;

class InputResolver
{
    public function resolve(
        object $parent,
        object $child,
        array  $bindings
    ): void
    {
        $ref = new ReflectionObject($child);

        foreach ($ref->getProperties() as $prop) {
            $attr = $prop->getAttributes(Input::class)[0] ?? null;
            if (!$attr) {
                continue;
            }

            /** @var Input $input */
            $input = $attr->newInstance();
            $name = $input->alias ?? $prop->getName();

            if (!array_key_exists($name, $bindings)) {
                continue;
            }

            $value = $this->evaluateExpression(
                $bindings[$name],
                $parent
            );

            $prop->setAccessible(true);
            $prop->setValue($child, $value);
        }
    }

    private function evaluateExpression(string $expr, object $context): mixed
    {
        $parts = explode('.', $expr);
        $value = $context;

        foreach ($parts as $part) {
            if (is_object($value)) {
                $value = $value->$part ?? null;
            } elseif (is_array($value)) {
                $value = $value[$part] ?? null;
            } else {
                return null;
            }
        }

        return $value;
    }
}
