<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CurrencyExtension extends AbstractExtension
{
    public function __construct(
        private RequestStack $requestStack
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_price', [$this, 'formatPrice']),
        ];
    }

    public function formatPrice($amount, string $originalCurrency = 'TND'): string
    {
        $session = $this->requestStack->getSession();
        $targetCurrency = $session->get('_currency', 'TND');

        $amount = (float) $amount;

        // Taux fixes de démonstration, exprimés en TND pour une unité.
        $rates = ['TND' => 1.0, 'EUR' => 3.4, 'USD' => 3.1];
        $originalCurrency = isset($rates[$originalCurrency]) ? $originalCurrency : 'TND';
        $targetCurrency = isset($rates[$targetCurrency]) ? $targetCurrency : 'TND';
        $amount = $amount * $rates[$originalCurrency] / $rates[$targetCurrency];

        $symbols = ['TND' => 'TND', 'EUR' => '€', 'USD' => '$'];

        return number_format($amount, 2, '.', ' ') . ' ' . $symbols[$targetCurrency];
    }
}
