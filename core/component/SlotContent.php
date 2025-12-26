<?php
namespace App\Component;

use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class SlotContent
{
    private ?Environment $twig = null;

    public function __construct(
        public readonly string $html,
        public readonly ?string $slotName = null
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->html) === '';
    }

    public function render(array $context): string
    {
        if ($this->isEmpty()) return '';
        if (empty($context)) return $this->html;

        try {
            $loader = new ArrayLoader(['slot_template' => $this->html]);
            $twig = new Environment($loader, [
                'autoescape' => false,
                'strict_variables' => false
            ]);
            return $twig->render('slot_template', $context);
        } catch (Throwable $e) {
            error_log('Slot render error: ' . $e->getMessage());
            return $this->html;
        }
    }


}