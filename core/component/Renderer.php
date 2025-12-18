<?php
namespace App\Component;

readonly class Renderer
{
    public function __construct(
        private ComponentRegistry $registry
    ) {}

    public function renderRoot(string $selector): string
    {
        $entry = $this->registry->get($selector);

        if (!$entry) {
            throw new \RuntimeException("Component $selector not found");
        }

        $instance = new ($entry['class'])();

        return $this->renderInstance(
            $instance,
            $entry['config']
        );
    }

    private function interpolate(string $tpl, object $component): string
    {
        return preg_replace_callback(
            '/{{\s*(\w+(?:\.\w+)*)\s*}}/',
            function($m) use ($component) {
                $value = $this->resolveProperty($m[1], $component);
                return htmlspecialchars($value ?? '');
            },
            $tpl
        );
    }

    private function resolveProperty(string $path, mixed $context): mixed
    {
        $parts = explode('.', $path);
        $value = $context;

        foreach ($parts as $part) {
            if (is_object($value)) {
                try {
                    $value = $value->$part;
                } catch (\Throwable $e) {
                    return null;
                }
            } elseif (is_array($value)) {
                $value = $value[$part] ?? null;
            } else {
                return null;
            }

            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

    private function processIfDirective(string $tpl, object $component): string
    {
        $pattern = '/@if\s*\(\s*([^)]+)\s*\)\s*\{(.*?)\}(?:\s*@else\s*\{(.*?)\})?/s';

        return preg_replace_callback(
            $pattern,
            function ($m) use ($component) {
                $condition = trim($m[1]);
                $ifContent = $m[2];
                $elseContent = $m[3] ?? '';

                $result = $this->evaluateCondition($condition, $component);

                return $result ? $ifContent : $elseContent;
            },
            $tpl
        );
    }

    private function evaluateCondition(string $condition, object $component): bool
    {
        $condition = trim($condition);

        if (preg_match('/^(.+?)\s*(===|!==|==|!=|>|<|>=|<=)\s*(.+)$/', $condition, $matches)) {
            $left = $this->evaluateExpression(trim($matches[1]), $component);
            $operator = $matches[2];
            $right = $this->evaluateExpression(trim($matches[3]), $component);

            return match($operator) {
                '===' => $left === $right,
                '!==' => $left !== $right,
                '==' => $left == $right,
                '!=' => $left != $right,
                '>' => $left > $right,
                '<' => $left < $right,
                '>=' => $left >= $right,
                '<=' => $left <= $right,
                default => false
            };
        }

        if (str_starts_with($condition, '!')) {
            $value = $this->evaluateExpression(substr($condition, 1), $component);
            return !$value;
        }

        $value = $this->evaluateExpression($condition, $component);
        return (bool) $value;
    }

    private function evaluateExpression(string $expr, object $component): mixed
    {
        $expr = trim($expr);

        if ($expr === 'true') return true;
        if ($expr === 'false') return false;
        if ($expr === 'null') return null;
        if (is_numeric($expr)) return $expr + 0;
        if (preg_match('/^["\'](.+)["\']$/', $expr, $m)) return $m[1];

        return $this->resolveProperty($expr, $component);
    }

    private function processForDirective(string $tpl, object $component): string
    {
        while (preg_match('/@for\s*\(\s*(\w+)\s+of\s+(\w+(?:\.\w+)*)\s*;\s*track\s+([^)]+)\)\s*\{/s', $tpl, $match, PREG_OFFSET_CAPTURE)) {
            $itemVar = $match[1][0];
            $arrayPath = $match[2][0];
            $trackBy = trim($match[3][0]);
            $startPos = $match[0][1];
            $openBracePos = $startPos + strlen($match[0][0]);

            $braceCount = 1;
            $pos = $openBracePos;
            $contentStart = $openBracePos;

            while ($pos < strlen($tpl) && $braceCount > 0) {
                if ($tpl[$pos] === '{') {
                    $braceCount++;
                } elseif ($tpl[$pos] === '}') {
                    $braceCount--;
                }
                $pos++;
            }

            if ($braceCount !== 0) {
                throw new \RuntimeException("Unmatched braces in @for directive");
            }

            $contentEnd = $pos - 1;
            $content = substr($tpl, $contentStart, $contentEnd - $contentStart);

            $emptyContent = '';
            $afterFor = $pos;
            if (preg_match('/^\s*@empty\s*\{/', substr($tpl, $pos), $emptyMatch)) {
                $emptyStart = $pos + strlen($emptyMatch[0]);
                $braceCount = 1;
                $pos = $emptyStart;

                while ($pos < strlen($tpl) && $braceCount > 0) {
                    if ($tpl[$pos] === '{') {
                        $braceCount++;
                    } elseif ($tpl[$pos] === '}') {
                        $braceCount--;
                    }
                    $pos++;
                }

                $emptyContent = substr($tpl, $emptyStart, $pos - $emptyStart - 1);
                $afterFor = $pos;
            }

            $array = $this->resolveProperty($arrayPath, $component);

            $replacement = '';
            if (!is_array($array) || empty($array)) {
                $replacement = $emptyContent;
            } else {
                foreach ($array as $index => $item) {
                    $itemObject = is_array($item) ? (object)$item : $item;

                    // Pre-calcola le proprietà temporanee dall'item
                    $tempProps = [];
                    if (is_object($itemObject)) {
                        foreach (get_object_vars($itemObject) as $key => $value) {
                            $tempName = '__temp_' . $itemVar . '_' . $key;
                            $tempProps[$tempName] = $value;
                        }
                    }

                    // Crea il contesto del loop con accesso alle proprietà temporanee
                    $loopContext = new class($component, $itemObject, $index, count($array), $tempProps) {
                        public function __construct(
                            private object $parent,
                            private object|int|string $itemData,
                            public int $index,
                            private int $total,
                            private array $tempProperties
                        ) {}

                        public function __get($name) {
                            // Variabili speciali del loop
                            if ($name === '$index') return $this->index;
                            if ($name === '$first') return $this->index === 0;
                            if ($name === '$last') return $this->index === $this->total - 1;
                            if ($name === '$even') return $this->index % 2 === 0;
                            if ($name === '$odd') return $this->index % 2 !== 0;
                            if ($name === '$count') return $this->total;

                            // Proprietà temporanee (per loop annidati)
                            if (isset($this->tempProperties[$name])) {
                                return $this->tempProperties[$name];
                            }

                            // Proprietà dell'item corrente
                            if (is_object($this->itemData) && property_exists($this->itemData, $name)) {
                                return $this->itemData->$name;
                            }
                            if (is_array($this->itemData) && isset($this->itemData[$name])) {
                                return $this->itemData[$name];
                            }

                            // Fallback al parent
                            return $this->parent->$name ?? null;
                        }

                        public function __isset($name) {
                            if (in_array($name, ['$index', '$first', '$last', '$even', '$odd', '$count'])) {
                                return true;
                            }
                            if (isset($this->tempProperties[$name])) {
                                return true;
                            }
                            if (is_object($this->itemData)) {
                                return property_exists($this->itemData, $name);
                            }
                            if (is_array($this->itemData)) {
                                return isset($this->itemData[$name]);
                            }
                            return isset($this->parent->$name);
                        }
                    };

                    // Sostituisci riferimenti all'item nel template
                    $itemContent = $this->replaceItemReferences($content, $itemVar, $itemObject);

                    // Processa ricorsivamente eventuali @for annidati
                    $itemContent = $this->processForDirective($itemContent, $loopContext);

                    // Renderizza il contenuto
                    $renderedContent = $this->renderChildrenInLoopContext(
                        $itemContent,
                        $loopContext,
                        $itemVar,
                        $itemObject
                    );

                    $replacement .= $renderedContent;
                }
            }

            $tpl = substr($tpl, 0, $startPos) . $replacement . substr($tpl, $afterFor);
        }

        return $tpl;
    }

    private function replaceItemReferences(string $tpl, string $itemVar, object $item): string
    {
        // Sostituisci binding come [name]="user.name"
        $tpl = preg_replace_callback(
            '/\[(\w+)\]\s*=\s*"' . preg_quote($itemVar, '/') . '\.(\w+(?:\.\w+)*)"/s',
            function ($m) use ($item) {
                $propName = $m[1];
                $itemPath = $m[2];

                $value = $this->resolveProperty($itemPath, $item);

                if (is_array($value)) {
                    return "[{$propName}]=\"__array_literal:" . base64_encode(serialize($value)) . "\"";
                }

                return "[{$propName}]=\"__literal:{$value}\"";
            },
            $tpl
        );

        // Sostituisci @for annidati come @for(user of app.users...)
        $tpl = preg_replace_callback(
            '/@for\s*\(\s*(\w+)\s+of\s+' . preg_quote($itemVar, '/') . '\.(\w+(?:\.\w+)*)\s*;/s',
            function ($m) use ($itemVar) {
                $loopVar = $m[1];
                $propertyPath = $m[2];

                $tempName = '__temp_' . $itemVar . '_' . str_replace('.', '_', $propertyPath);

                return "@for ($loopVar of $tempName;";
            },
            $tpl
        );

        return $tpl;
    }

    private function renderChildrenInLoopContext(
        string $tpl,
        object $context,
        string $itemVar,
        object $item
    ): string {
        // Interpola le variabili {{ }}
        $tpl = $this->interpolateInLoopContext($tpl, $itemVar, $item, $context);

        // Renderizza i componenti
        $pattern = '/<([\w-]+)([^>]*)>(.*?)<\/\1>/s';

        return preg_replace_callback(
            $pattern,
            function ($m) use ($context) {
                $selector = $m[1];
                $attrString = $m[2];
                $content = $m[3];

                $entry = $this->registry->get($selector);
                if (!$entry) {
                    return $m[0];
                }

                $bindings = $this->parseBindings($attrString);
                $child = new ($entry['class'])();

                $this->resolveBindingsInContext($context, $child, $bindings);

                return $this->renderInstance($child, $entry['config'], 0);
            },
            $tpl
        );
    }

    private function resolveBindingsInContext(object $parent, object $child, array $bindings): void
    {
        $ref = new \ReflectionObject($child);

        foreach ($ref->getProperties() as $prop) {
            $attr = $prop->getAttributes(Input::class)[0] ?? null;
            if (!$attr) {
                continue;
            }

            $input = $attr->newInstance();
            $name = $input->alias ?? $prop->getName();

            if (!array_key_exists($name, $bindings)) {
                continue;
            }

            $expression = $bindings[$name];

            if (str_starts_with($expression, '__literal:')) {
                $value = substr($expression, 10);
            } elseif (str_starts_with($expression, '__array_literal:')) {
                $serialized = substr($expression, 16);
                $value = unserialize(base64_decode($serialized));
            } else {
                $value = $this->evaluateExpression($expression, $parent);
            }

            $prop->setAccessible(true);
            $prop->setValue($child, $value);
        }
    }

    private function interpolateInLoopContext(
        string $tpl,
        string $itemVar,
        object $item,
        object $context
    ): string {
        return preg_replace_callback(
            '/{{\s*(\$?\w+(?:\.\w+)*)\s*}}/',
            function ($m) use ($itemVar, $item, $context) {
                $path = $m[1];

                if (str_starts_with($path, '$')) {
                    return htmlspecialchars($context->$path ?? '');
                }

                if (str_starts_with($path, $itemVar)) {
                    $subPath = substr($path, strlen($itemVar));
                    if ($subPath === '') {
                        return htmlspecialchars($item);
                    }
                    if (str_starts_with($subPath, '.')) {
                        $property = substr($subPath, 1);
                        $value = $this->resolveProperty($property, $item);
                        return htmlspecialchars($value ?? '');
                    }
                }

                $value = $this->resolveProperty($path, $context);
                return htmlspecialchars($value ?? '');
            },
            $tpl
        );
    }

    private function renderChildren(string $tpl, object $parent, int $depth = 0): string
    {
        if ($depth > 50) {
            throw new \RuntimeException("Maximum component nesting depth exceeded (50 levels)");
        }

        $tpl = $this->processIfDirective($tpl, $parent);
        $tpl = $this->processForDirective($tpl, $parent);

        $pattern = '/<([\w-]+)([^>]*)>(.*?)<\/\1>/s';

        $result = preg_replace_callback(
            $pattern,
            function ($m) use ($parent, $depth) {
                $selector = $m[1];
                $attrString = $m[2];
                $content = $m[3];

                $entry = $this->registry->get($selector);
                if (!$entry) {
                    if (!empty($content)) {
                        $renderedContent = $this->renderChildren($content, $parent, $depth + 1);
                        return "<{$selector}{$attrString}>{$renderedContent}</{$selector}>";
                    }
                    return $m[0];
                }

                $bindings = $this->parseBindings($attrString);
                $child = new ($entry['class'])();

                (new InputResolver())->resolve($parent, $child, $bindings);

                return $this->renderInstance($child, $entry['config'], $depth + 1);
            },
            $tpl
        );

        return $result;
    }

    private function parseBindings(string $attrString): array
    {
        $bindings = [];
        $attrString = trim($attrString);

        if (empty($attrString)) {
            return $bindings;
        }

        preg_match_all(
            '/\[(\w+)\]\s*=\s*"([^"]+)"/s',
            $attrString,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $m) {
            $bindings[trim($m[1])] = trim($m[2]);
        }

        return $bindings;
    }

    private function renderInstance(
        object $component,
        Component $config,
        int $depth = 0
    ): string {
        $template = file_get_contents($config->template);
        $template = $this->renderChildren($template, $component, $depth);
        return $this->interpolate($template, $component);
    }
}