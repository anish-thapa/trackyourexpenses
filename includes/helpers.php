<?php
// includes/helpers.php

if (!function_exists('format_currency')) {
    /**
     * Formats an amount with the appropriate currency symbol and decimal places.
     *
     * This is a simplified version. For robust, locale-aware formatting,
     * consider using the IntlNumberFormatter class if the 'intl' PHP extension is available.
     *
     * @param float|null $amount The amount to format. Can be null for cases like uninitialized totals.
     * @param string $currencyCode The currency code (e.g., USD, EUR, NPR).
     * @return string The formatted currency string, or a placeholder if amount is null.
     */
    function format_currency(?float $amount, string $currencyCode): string
    {
        if ($amount === null) {
            // Decide how to display null amounts, e.g., for new users with no transactions
            // return '-'; // Or an empty string, or 'N/A'
            $amount = 0.00; // Default to 0.00 if null for consistent formatting
        }

        $currencyCode = strtoupper($currencyCode);
        $symbol = '';
        $decimals = 2;
        $decimal_separator = '.';
        $thousands_separator = ',';
        $symbol_position = 'before'; // 'before' or 'after' (e.g., for EUR in some locales)

        switch ($currencyCode) {
            case 'USD':
                $symbol = '$';
                break;
            case 'EUR':
                $symbol = '€';
                // Note: Some European locales use ',' as decimal separator and '.' as thousands.
                // For this simplified function, we'll keep it consistent.
                // Example for specific Euro formatting (requires intl extension ideally):
                // if (class_exists('NumberFormatter')) {
                //     $formatter = new NumberFormatter('de_DE', NumberFormatter::CURRENCY); // Example: German locale
                //     return $formatter->formatCurrency($amount, $currencyCode);
                // }
                break;
            case 'NPR':
                $symbol = 'रु'; // Nepalese Rupee
                // NPR often has a different grouping for thousands (e.g., 1,23,456.78)
                // This simplified function uses standard thousands separation.
                // An IntlNumberFormatter for 'ne_NP' locale would handle this correctly.
                break;
            default:
                // Fallback: use the currency code itself if symbol is unknown
                $symbol = htmlspecialchars($currencyCode) . ' '; // Sanitize code if displayed directly
                break;
        }

        $formatted_amount = number_format($amount, $decimals, $decimal_separator, $thousands_separator);

        if ($symbol_position === 'before') {
            return $symbol . $formatted_amount;
        } else {
            // For currencies where symbol comes after (e.g., 1.234,56 €)
            return $formatted_amount . ' ' . $symbol; // Add a space for clarity
        }
    }
}

// --- You can add other helper functions below this line as your application grows ---

// Example: A function to sanitize output (though htmlspecialchars is often used directly)
if (!function_exists('e')) {
    /**
     * Escapes HTML special characters for safe output.
     * A shorthand for htmlspecialchars.
     *
     * @param string|null $string The string to escape.
     * @return string The escaped string.
     */
    function e(?string $string): string
    {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/*
// Example of using IntlNumberFormatter (if PHP intl extension is enabled)
// This would replace the switch statement in format_currency for more accuracy.
//
if (!function_exists('format_currency_intl')) {
    function format_currency_intl(float $amount, string $currencyCode): string
    {
        if (!class_exists('NumberFormatter')) {
            // Fallback to simple version if intl is not available
            return format_currency($amount, $currencyCode); // Calls the function above
        }

        $locale = ''; // Determine locale based on currency or user preference
        switch (strtoupper($currencyCode)) {
            case 'USD':
                $locale = 'en_US';
                break;
            case 'EUR':
                // Euro has many locales, e.g., 'de_DE', 'fr_FR', 'es_ES'
                // Pick a common one or allow user to set locale preference too.
                $locale = 'de_DE'; // Example: German Euro formatting
                break;
            case 'NPR':
                $locale = 'ne_NP'; // Nepali locale
                break;
            default:
                // If no specific locale, try a generic one for the currency
                // This might not always work as expected.
                $locale = 'en_US'; // Fallback locale
        }

        try {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            if (!$formatter) {
                 error_log("Failed to create NumberFormatter for locale: {$locale}");
                 return format_currency($amount, $currencyCode); // Fallback
            }
            $formatted = $formatter->formatCurrency($amount, $currencyCode);
            if ($formatted === false) {
                error_log("formatCurrency failed for {$amount} {$currencyCode} with locale {$locale}: " . $formatter->getErrorMessage());
                return format_currency($amount, $currencyCode); // Fallback
            }
            return $formatted;
        } catch (Exception $e) {
            error_log("IntlNumberFormatter Exception: " . $e->getMessage());
            return format_currency($amount, $currencyCode); // Fallback on exception
        }
    }
}
*/

?>