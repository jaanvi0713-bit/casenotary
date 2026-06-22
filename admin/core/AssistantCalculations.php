<?php

declare(strict_types=1);

class AssistantCalculations
{
    public static function looksLikeCalculationQuery(string $message): bool
    {
        $message = trim($message);
        if ($message === '') {
            return false;
        }

        if (self::containsBusinessReference($message)) {
            return false;
        }

        if (preg_match('/\bcase[- ]?#?\s*[a-z0-9-]+/i', $message)) {
            return false;
        }

        if (AssistantDocuments::looksLikeCaseDocumentLoad($message)) {
            return false;
        }

        if (preg_match('/\b(calculate|calculation|compute|work out|math)\b/i', $message)) {
            return true;
        }

        if (preg_match('/\d+\s*%/', $message) || preg_match('/\bpercent(?:age)?\b/i', $message)) {
            return true;
        }

        if (preg_match('#\d+(?:\.\d+)?\s*([+*/×÷^()]|\bplus\b|\btimes\b|\bdivided\s+by\b|\bpower\b|\^)\s*\d+#i', $message)) {
            return true;
        }

        if (preg_match('/\d+(?:\.\d+)?\s+-\s+\d+(?:\.\d+)?/i', $message)) {
            return true;
        }

        if (preg_match('/\b(average|avg|mean|increase|decrease|growth|difference|ratio)\b/i', $message) && preg_match('/\d/', $message)) {
            return true;
        }

        return preg_match('/\b(revenue|outstanding|balance)\b/i', $message) && preg_match('/\d/', $message);
    }

    private static function containsBusinessReference(string $message): bool
    {
        if (preg_match('/\b(?:INV|CASE|QUO|PRO|PAY|RCP)-[A-Z0-9-]+\b/i', $message)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(?:write|draft|compose|prepare|remind|reminder|follow[- ]?up)\b.*\b(?:invoice|payment|client|case|appointment|document|quotation|quote)\b/i',
            $message
        );
    }

    /** @return array{content: string} */
    public static function handle(string $message): array
    {
        $message = assistantNormalizeUserMessage($message);

        if ($result = self::percentageOfMetric($message)) {
            return $result;
        }

        if ($result = self::percentageOfAmount($message)) {
            return $result;
        }

        if ($result = self::percentageChange($message)) {
            return $result;
        }

        if ($result = self::average($message)) {
            return $result;
        }

        if ($result = self::arithmeticExpression($message)) {
            return $result;
        }

        return [
            'content' => "I can calculate **percentages**, **multi-step arithmetic**, **averages**, **percentage change**, and **business metrics** (e.g. _10% of revenue_).\n\n"
                . "Examples:\n"
                . "• _10% of revenue_\n"
                . "• _15% of 25,000_\n"
                . "• _(125 + 75) * 3 - 40 / 2_\n"
                . "• _average 12, 18, 30, 40_\n"
                . "• _increase from 1200 to 1560_",
        ];
    }

    /** @return array{content: string}|null */
    private static function percentageOfMetric(string $message): ?array
    {
        if (!preg_match('/(\d+(?:\.\d+)?)\s*(?:%|percent(?:age)?|pct)/i', $message, $percentMatch)) {
            return null;
        }

        if (!preg_match('/\bof\b/i', $message)) {
            return null;
        }

        $percent = (float) $percentMatch[1];
        $stats = getDashboardStats();
        $haystack = strtolower($message);

        $metric = null;
        if (preg_match('/\b(monthly revenue|this month(?:\'s)? revenue|revenue this month)\b/', $haystack)) {
            $metric = [
                'label' => 'Monthly revenue',
                'value' => (float) ($stats['monthly_revenue'] ?? 0),
            ];
        } elseif (preg_match('/\b(total revenue|revenue|earnings|income)\b/', $haystack)) {
            $metric = [
                'label' => 'Total revenue',
                'value' => (float) ($stats['total_revenue'] ?? 0),
            ];
        } elseif (preg_match('/\b(outstanding balance|outstanding|balance due|unpaid balance)\b/', $haystack)) {
            $metric = [
                'label' => 'Outstanding balance',
                'value' => (float) ($stats['outstanding_balance'] ?? 0),
            ];
        } elseif (preg_match('/\b(active cases?|open cases?)\b/', $haystack)) {
            $metric = [
                'label' => 'Active cases',
                'value' => (float) ($stats['active_cases'] ?? 0),
                'format' => 'number',
            ];
        } elseif (preg_match('/\b(clients?|client count)\b/', $haystack)) {
            $metric = [
                'label' => 'Registered clients',
                'value' => (float) ($stats['total_clients'] ?? 0),
                'format' => 'number',
            ];
        }

        if ($metric === null) {
            return null;
        }

        $result = $metric['value'] * ($percent / 100);
        $isCount = ($metric['format'] ?? '') === 'number';

        return [
            'content' => "**Calculation**\n\n"
                . '• ' . $metric['label'] . ': **' . self::formatBaseValue($metric['value'], $isCount) . "**\n"
                . '• ' . $percent . '%: **' . self::formatResultValue($result, $isCount) . '**',
        ];
    }

    /** @return array{content: string}|null */
    private static function percentageOfAmount(string $message): ?array
    {
        if (!preg_match(
            '/(\d+(?:\.\d+)?)\s*(?:%|percent(?:age)?|pct)\s+of\s+(?:[$£€]|usd|eur|gbp)?\s*([\d,]+(?:\.\d+)?)/i',
            $message,
            $matches
        )) {
            return null;
        }

        $percent = (float) $matches[1];
        $base = (float) str_replace(',', '', $matches[2]);
        $result = $base * ($percent / 100);

        return [
            'content' => "**Calculation**\n\n"
                . '• Amount: **' . formatCurrency($base) . "**\n"
                . '• ' . $percent . '%: **' . formatCurrency($result) . '**',
        ];
    }

    /** @return array{content: string}|null */
    private static function percentageChange(string $message): ?array
    {
        if (!preg_match('/\b(from|between)\b\s*([\d,]+(?:\.\d+)?)\D+\b(to|and)\b\s*([\d,]+(?:\.\d+)?)/i', $message, $matches)) {
            return null;
        }

        if (!preg_match('/\b(increase|decrease|change|growth|drop)\b/i', $message)) {
            return null;
        }

        $start = (float) str_replace(',', '', $matches[2]);
        $end = (float) str_replace(',', '', $matches[4]);
        if ($start == 0.0) {
            return ['content' => 'Cannot compute percentage change from zero.'];
        }

        $delta = $end - $start;
        $percent = ($delta / $start) * 100;
        $direction = $delta >= 0 ? 'Increase' : 'Decrease';

        return [
            'content' => "**Calculation**\n\n"
                . '• Start: **' . self::formatNumber($start) . "**\n"
                . '• End: **' . self::formatNumber($end) . "**\n"
                . '• ' . $direction . ': **' . self::formatNumber(abs($delta)) . "**\n"
                . '• Percentage change: **' . self::formatNumber($percent) . '%**',
        ];
    }

    /** @return array{content: string}|null */
    private static function average(string $message): ?array
    {
        if (!preg_match('/\b(average|avg|mean)\b/i', $message)) {
            return null;
        }

        preg_match_all('/-?\d[\d,]*(?:\.\d+)?/', $message, $matches);
        $values = array_map(
            static fn (string $n): float => (float) str_replace(',', '', $n),
            $matches[0] ?? []
        );
        if (count($values) < 2) {
            return null;
        }

        $sum = array_sum($values);
        $avg = $sum / count($values);

        return [
            'content' => "**Calculation**\n\n"
                . '• Count: **' . count($values) . "**\n"
                . '• Sum: **' . self::formatNumber($sum) . "**\n"
                . '• Average: **' . self::formatNumber($avg) . '**',
        ];
    }

    /** @return array{content: string}|null */
    private static function arithmeticExpression(string $message): ?array
    {
        $expression = self::extractExpression($message);
        if ($expression === null) {
            return null;
        }

        try {
            $result = self::evaluateExpression($expression);
        } catch (RuntimeException $e) {
            return ['content' => $e->getMessage()];
        }

        return [
            'content' => '**Calculation:** `' . $expression . '` = **' . self::formatNumber($result) . '**',
        ];
    }

    private static function extractExpression(string $message): ?string
    {
        if (self::containsBusinessReference($message)) {
            return null;
        }

        $normalized = strtolower($message);
        $normalized = preg_replace('/\bdivided\s+by\b/', '/', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bplus\b/', '+', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bminus\b/', '-', $normalized) ?? $normalized;
        $normalized = preg_replace('/\btimes\b|\bmultipl(?:y|ied)\s+by\b/', '*', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b(power|to\s+the\s+power\s+of)\b/', '^', $normalized) ?? $normalized;
        $normalized = str_replace([',', '$', '£', '€'], '', $normalized);

        if (!preg_match('/[\d\)\(]\s*[\+\-\*\/\^]/', $normalized) && !preg_match('/[\+\-\*\/\^]\s*[\d\(\)]/', $normalized)) {
            return null;
        }

        if (!preg_match_all('/\d+(?:\.\d+)?|[()+\-*\/\^]/', $normalized, $tokens)) {
            return null;
        }

        return implode(' ', $tokens[0]);
    }

    private static function evaluateExpression(string $expression): float
    {
        preg_match_all('/\d+(?:\.\d+)?|[()+\-*\/\^]/', $expression, $tokenMatches);
        $tokens = $tokenMatches[0] ?? [];
        if ($tokens === []) {
            throw new RuntimeException('I could not parse the expression.');
        }

        $output = [];
        $operators = [];

        foreach ($tokens as $token) {
            if (preg_match('/^\d+(?:\.\d+)?$/', $token)) {
                $output[] = (float) $token;
                continue;
            }

            if ($token === '(') {
                $operators[] = $token;
                continue;
            }

            if ($token === ')') {
                while ($operators !== [] && end($operators) !== '(') {
                    self::applyTopOperator($output, $operators);
                }
                if ($operators === [] || array_pop($operators) !== '(') {
                    throw new RuntimeException('Mismatched parentheses in expression.');
                }
                continue;
            }

            while (
                $operators !== []
                && end($operators) !== '('
                && self::precedence((string) end($operators)) >= self::precedence($token)
            ) {
                self::applyTopOperator($output, $operators);
            }
            $operators[] = $token;
        }

        while ($operators !== []) {
            if (end($operators) === '(') {
                throw new RuntimeException('Mismatched parentheses in expression.');
            }
            self::applyTopOperator($output, $operators);
        }

        if (count($output) !== 1) {
            throw new RuntimeException('I could not evaluate that expression.');
        }

        return (float) $output[0];
    }

    private static function precedence(string $op): int
    {
        return match ($op) {
            '+', '-' => 1,
            '*', '/' => 2,
            '^' => 3,
            default => 0,
        };
    }

    /** @param list<float> $output @param list<string> $operators */
    private static function applyTopOperator(array &$output, array &$operators): void
    {
        $op = array_pop($operators);
        if ($op === null) {
            throw new RuntimeException('Invalid expression.');
        }
        if (count($output) < 2) {
            throw new RuntimeException('Invalid expression.');
        }

        $right = (float) array_pop($output);
        $left = (float) array_pop($output);
        $value = match ($op) {
            '+' => $left + $right,
            '-' => $left - $right,
            '*' => $left * $right,
            '/' => $right == 0.0 ? throw new RuntimeException('Cannot divide by zero.') : $left / $right,
            '^' => $left ** $right,
            default => throw new RuntimeException('Unsupported operator in expression.'),
        };

        $output[] = $value;
    }

    private static function formatBaseValue(float $value, bool $isCount): string
    {
        return $isCount ? self::formatNumber($value) : formatCurrency($value);
    }

    private static function formatResultValue(float $value, bool $isCount): string
    {
        if ($isCount) {
            return self::formatNumber($value);
        }

        return formatCurrency($value);
    }

    private static function formatNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.00001) {
            return number_format((int) round($value));
        }

        return number_format($value, 2);
    }
}
