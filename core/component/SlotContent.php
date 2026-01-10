<?php
namespace Sophia\Component;

use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class SlotContent
{
    // ⚡ Twig condiviso per tutti gli slot (creato una volta sola)
    private static ?Environment $sharedTwig = null;

    public function __construct(
        public readonly string $html,
        public readonly ?string $slotName = null
    ) {}

    public function isEmpty(): bool
    {
        return trim($this->html) === '';
    }

    /**
     * ⚡ OTTIMIZZATO: Usa una singola istanza Twig per tutti gli slot
     */
    public function render(array $context): string
    {
        if ($this->isEmpty()) return '';
        if (empty($context)) return $this->html;

        try {
            // ⚡ Inizializza Twig condiviso solo la prima volta
            if (self::$sharedTwig === null) {
                $loader = new ArrayLoader([]);
                self::$sharedTwig = new Environment($loader, [
                    'autoescape' => false,
                    'strict_variables' => false,
                    'cache' => false, // Slot semplici, no cache su disco
                    'optimizations' => -1, // Max ottimizzazioni
                ]);
            }

            // ⚡ Usa hash del contenuto come chiave template
            $templateKey = 'slot_' . md5($this->html);

            // Aggiorna il loader con il template (leggero)
            $loader = self::$sharedTwig->getLoader();
            if ($loader instanceof ArrayLoader) {
                $loader->setTemplate($templateKey, $this->html);
            }

            return self::$sharedTwig->render($templateKey, $context);

        } catch (Throwable $e) {
            error_log('Slot render error: ' . $e->getMessage());
            return $this->html;
        }
    }

    /**
     * ⚡ Metodo statico per pulire la cache (sviluppo)
     */
    public static function clearCache(): void
    {
        self::$sharedTwig = null;
    }


}