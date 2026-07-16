<?php
/**
 * DocumentFieldExtractor
 * Given raw OCR text and a detected document type, returns only the
 * fields relevant to that type (per the spec: PhilSys, Driver's License,
 * Passport, Birth Certificate, Student ID map onto `clients`; Business
 * Card, Receipt, Invoice map onto their own tables; unknown documents
 * fall back to raw text).
 *
 * Person-identity types reuse OcrEngine::parseFields() (name/birthdate/
 * sex/address/id-number detection already exists there) and add a few
 * type-specific fields on top. Business Card / Receipt / Invoice use
 * dedicated lightweight label-and-pattern matching.
 */
class DocumentFieldExtractor
{
    public static function extract(string $documentType, string $rawText): array
    {
        return match ($documentType) {
            'national_id', 'drivers_license', 'passport', 'birth_certificate', 'student_id'
                => self::extractPersonFields($documentType, $rawText),
            'business_card' => self::extractBusinessCard($rawText),
            'receipt'        => self::extractReceipt($rawText),
            'invoice'        => self::extractInvoice($rawText),
            default          => ['raw_text' => trim($rawText)],
        };
    }

    /** Field keys that should be shown to the user for a given type (order matters for the UI) */
    public static function fieldsForType(string $documentType): array
    {
        return match ($documentType) {
            'national_id'       => ['first_name', 'middle_name', 'last_name', 'birth_date', 'sex', 'address', 'id_number'],
            'drivers_license'   => ['first_name', 'middle_name', 'last_name', 'address', 'birth_date', 'id_expiration_date', 'id_class', 'id_number'],
            'passport'          => ['id_number', 'first_name', 'middle_name', 'last_name', 'nationality', 'birth_date', 'sex', 'id_issue_date', 'id_expiration_date'],
            'birth_certificate' => ['first_name', 'middle_name', 'last_name', 'birth_date', 'birth_place', 'father_name', 'mother_name', 'id_number'],
            'student_id'        => ['first_name', 'last_name', 'id_number', 'school', 'course', 'year_level'],
            'business_card'     => ['full_name', 'company', 'position', 'phone_number', 'email', 'website', 'company_address'],
            'receipt'           => ['store_name', 'receipt_number', 'receipt_date', 'total_amount', 'tax_amount', 'payment_method'],
            'invoice'           => ['invoice_number', 'company_name', 'customer_name', 'invoice_date', 'due_date', 'total_amount'],
            default             => ['raw_text'],
        };
    }

    private static function extractPersonFields(string $documentType, string $rawText): array
    {
        // Reuse the existing, already-tested name/DOB/sex/address/ID detectors
        $base = OcrEngine::parseFields($rawText);

        $lines = array_values(array_filter(array_map('trim', explode("\n", $rawText)), fn($l) => $l !== ''));
        $fullText = implode(' ', $lines);

        $extra = [
            'id_issue_date' => '',
            'id_expiration_date' => '',
            'id_class' => '',
            'father_name' => '',
            'mother_name' => '',
            'school' => '',
            'course' => '',
            'year_level' => '',
        ];

        // Expiration / issue dates (Driver's License, Passport)
        if (preg_match('/expir\w*\s*(date)?\s*[:\-]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $fullText, $m)) {
            $extra['id_expiration_date'] = self::normalizeDate($m[2]);
        }
        if (preg_match('/issue\w*\s*(date)?\s*[:\-]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $fullText, $m)) {
            $extra['id_issue_date'] = self::normalizeDate($m[2]);
        }

        // License class (Driver's License), e.g. "Class: A" or "A1, B"
        if ($documentType === 'drivers_license' && preg_match('/(?:license\s*)?class\s*[:\-]?\s*([A-Z][0-9]?(?:\s*,\s*[A-Z][0-9]?)*)/i', $fullText, $m)) {
            $extra['id_class'] = trim($m[1]);
        }

        // Father / Mother name (Birth Certificate)
        if ($documentType === 'birth_certificate') {
            if (preg_match('/father(?:\'?s)?\s*name\s*[:\-]\s*(.+)/i', $fullText, $m)) {
                $extra['father_name'] = self::cleanName($m[1]);
            }
            if (preg_match('/mother(?:\'?s)?\s*(?:maiden\s*)?name\s*[:\-]\s*(.+)/i', $fullText, $m)) {
                $extra['mother_name'] = self::cleanName($m[1]);
            }
        }

        // School / Course / Year Level (Student ID)
        if ($documentType === 'student_id') {
            foreach ($lines as $i => $line) {
                if (preg_match('/^(school|university|college)\s*[:\-]\s*(.+)/i', $line, $m)) {
                    $extra['school'] = trim($m[2]);
                } elseif (preg_match('/^course\s*[:\-]\s*(.+)/i', $line, $m)) {
                    $extra['course'] = trim($m[1]);
                } elseif (preg_match('/^year\s*level\s*[:\-]\s*(.+)/i', $line, $m)) {
                    $extra['year_level'] = trim($m[1]);
                }
            }
        }

        return array_merge($base, $extra);
    }

    private static function extractBusinessCard(string $rawText): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $rawText)), fn($l) => $l !== ''));
        $fields = ['full_name' => '', 'company' => '', 'position' => '', 'phone_number' => '', 'email' => '', 'website' => '', 'company_address' => ''];

        $fullText = implode(' ', $lines);

        if (preg_match('/[\w.+-]+@[\w-]+\.[a-z]{2,}/i', $fullText, $m)) {
            $fields['email'] = strtolower($m[0]);
        }
        if (preg_match('/\b(?:www\.)?[a-z0-9-]+\.(?:com|ph|net|org|co)\b/i', $fullText, $m) && !str_contains($m[0], '@')) {
            $fields['website'] = strtolower($m[0]);
        }
        if (preg_match('/(?:\+63|0)9\d{2}[\-\s]?\d{3}[\-\s]?\d{4}/', $fullText, $m)) {
            $fields['phone_number'] = trim($m[0]);
        } elseif (preg_match('/\(?\d{2,4}\)?[\-\s]?\d{3,4}[\-\s]?\d{4}/', $fullText, $m)) {
            $fields['phone_number'] = trim($m[0]);
        }

        $titleWords = ['ceo', 'president', 'manager', 'director', 'founder', 'owner', 'officer', 'engineer', 'consultant', 'supervisor', 'head', 'sales', 'marketing', 'developer'];
        $companySuffixes = ['inc', 'corp', 'corporation', 'llc', 'company', 'co.', 'enterprises', 'group', 'ltd'];

        foreach ($lines as $line) {
            $lower = strtolower($line);
            if ($fields['position'] === '') {
                foreach ($titleWords as $tw) {
                    if (str_contains($lower, $tw)) { $fields['position'] = $line; break; }
                }
            }
            if ($fields['company'] === '') {
                foreach ($companySuffixes as $cs) {
                    if (str_contains($lower, $cs)) { $fields['company'] = $line; break; }
                }
            }
        }

        // Best-effort: first line that isn't the email/website/phone/company/position is likely the name
        foreach ($lines as $line) {
            if ($line === $fields['company'] || $line === $fields['position']) continue;
            if (str_contains($line, '@') || preg_match('/\d{4,}/', $line) || stripos($line, 'www.') !== false) continue;
            $fields['full_name'] = self::cleanName($line);
            break;
        }

        // Address: longest remaining line containing a number + street-like word
        foreach ($lines as $line) {
            if (preg_match('/\d+.*(?:st\.|street|ave|avenue|road|rd\.|brgy|city)/i', $line)) {
                $fields['company_address'] = $line;
                break;
            }
        }

        return $fields;
    }

    private static function extractReceipt(string $rawText): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $rawText)), fn($l) => $l !== ''));
        $fullText = implode(' ', $lines);
        $fields = ['store_name' => $lines[0] ?? '', 'receipt_number' => '', 'receipt_date' => '', 'total_amount' => '', 'tax_amount' => '', 'payment_method' => ''];

        if (preg_match('/(?:or|receipt)\s*(?:no|#)\.?\s*[:\-]?\s*([A-Z0-9\-]{4,})/i', $fullText, $m)) {
            $fields['receipt_number'] = trim($m[1]);
        }
        if (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/', $fullText, $m)) {
            $fields['receipt_date'] = self::normalizeDate($m[1]);
        }
        if (preg_match('/total\s*(?:due|amount)?\s*[:\-]?\s*(?:php|₱)?\s*([\d,]+\.\d{2})/i', $fullText, $m)) {
            $fields['total_amount'] = str_replace(',', '', $m[1]);
        }
        if (preg_match('/(?:vat|tax)\s*[:\-]?\s*(?:php|₱)?\s*([\d,]+\.\d{2})/i', $fullText, $m)) {
            $fields['tax_amount'] = str_replace(',', '', $m[1]);
        }
        foreach (['cash', 'gcash', 'credit card', 'debit card', 'paymaya', 'e-wallet'] as $method) {
            if (stripos($fullText, $method) !== false) { $fields['payment_method'] = ucwords($method); break; }
        }

        return $fields;
    }

    private static function extractInvoice(string $rawText): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $rawText)), fn($l) => $l !== ''));
        $fullText = implode(' ', $lines);
        $fields = ['invoice_number' => '', 'company_name' => $lines[0] ?? '', 'customer_name' => '', 'invoice_date' => '', 'due_date' => '', 'total_amount' => ''];

        if (preg_match('/invoice\s*(?:no|#)\.?\s*[:\-]?\s*([A-Z0-9\-]{3,})/i', $fullText, $m)) {
            $fields['invoice_number'] = trim($m[1]);
        }
        if (preg_match('/bill\s*to\s*[:\-]?\s*(.+)/i', $fullText, $m)) {
            $fields['customer_name'] = trim(explode('.', $m[1])[0]);
        }
        if (preg_match('/invoice\s*date\s*[:\-]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $fullText, $m)) {
            $fields['invoice_date'] = self::normalizeDate($m[1]);
        }
        if (preg_match('/due\s*date\s*[:\-]?\s*(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/i', $fullText, $m)) {
            $fields['due_date'] = self::normalizeDate($m[1]);
        }
        if (preg_match('/(?:total|amount\s*due)\s*[:\-]?\s*(?:php|₱)?\s*([\d,]+\.\d{2})/i', $fullText, $m)) {
            $fields['total_amount'] = str_replace(',', '', $m[1]);
        }

        return $fields;
    }

    private static function cleanName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z\'\-\. ]/', '', $name);
        return trim(ucwords(strtolower(trim($name))));
    }

    private static function normalizeDate(string $raw): string
    {
        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d', $ts) : '';
    }
}