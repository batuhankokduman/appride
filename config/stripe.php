<?php
require_once __DIR__ . '/../vendor/autoload.php';

$stripeSecret = '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';  // Gerçek anahtarlarını yaz
$stripePublic = 'pk_live_51NbHyTAEDyzUakYaugUv7KQ3IuMwqflDkhWAYGFu1o7bHFR4K78FLBEdgvPY54RMq4usjf7qnYgLZ43hvnXV3FNM00ukT4imbd';

\Stripe\Stripe::setApiKey($stripeSecret);
