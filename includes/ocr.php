<?php
/**
 * OcrEngine
 * Runs OCR by calling the persistent ocr_service.py Flask service
 * (started separately with `py -3.13 python\ocr_service.py`) instead of
 * spawning a fresh Python/PaddleOCR process on every scan. The service
 * loads PaddleOCR's models once at startup, so requests here just do
 * inference — this is what makes scans fast (seconds, not minutes).
 *
 * The service responds with the same JSON shape the old Tesseract-based
 * extractTextAndConfidence() returned:
 *   {"text": "...", "confidence": 92.4, "words": [{"text":"..","conf":92.4}, ...]}
 * So DocumentClassifier / DocumentFieldExtractor / everything downstream
 * needs no changes at all.
 *
 * IMPORTANT: ocr_service.py must be running in its own terminal window
 * (py -3.13 C:\xampp\htdocs\ScannerEncoding\python\ocr_service.py)
 * whenever you use the ScannerEncoding app. This class will throw a
 * clear error if it can't reach the service.
 */

// --- OCR service integration --------------------------------------------
if (!defined('OCR_SERVICE_URL')) {
    define('OCR_SERVICE_URL', 'http://127.0.0.1:5001/ocr');
}
if (!defined('OCR_SERVICE_HEALTH_URL')) {
    define('OCR_SERVICE_HEALTH_URL', 'http://127.0.0.1:5001/health');
}
// Generous but finite timeout for a single scan request. Once the models
// are warm, inference should take a few seconds; this just guards
// against a hung request.
if (!defined('OCR_SERVICE_TIMEOUT_SECONDS')) {
    define('OCR_SERVICE_TIMEOUT_SECONDS', 300);
}
// --------------------------------------------------------------------------

class OcrEngine
{
    public static function extractText(string $imagePath): string
    {
        return self::extractTextAndConfidence($imagePath)['text'];
    }

    public static function extractTextAndConfidence(string $imagePath): array
    {
        if (!is_file($imagePath)) {
            throw new RuntimeException('Image file not found: ' . $imagePath);
        }

        $result = self::callOcrService($imagePath);

        $output = [
            'text' => $result['text'] ?? '',
            'confidence' => $result['confidence'] ?? null,
            'words' => $result['words'] ?? [],
        ];

        self::logDebug($imagePath, $output);

        return $output;
    }

    /**
     * Writes the raw OCR text/words for every scan to logs/ocr_debug.log.
     * This is a temporary debugging aid — once name/field parsing is
     * reliable, feel free to remove calls to this method (or just stop
     * reading the log).
     */
    private static function logDebug(string $imagePath, array $result): void
    {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/ocr_debug.log';

        $entry = "==== " . date('Y-m-d H:i:s') . " ====\n";
        $entry .= "Image: $imagePath\n";
        $entry .= "Confidence: " . ($result['confidence'] ?? 'null') . "\n";
        $entry .= "--- Raw text (line by line) ---\n";
        $entry .= ($result['text'] ?? '') . "\n";
        $entry .= "--- Words with confidence ---\n";
        foreach (($result['words'] ?? []) as $w) {
            $entry .= "  [{$w['conf']}%] " . $w['text'] . "\n";
        }
        $entry .= "\n";

        @file_put_contents($logFile, $entry, FILE_APPEND);
    }

    /**
     * Calls the persistent ocr_service.py Flask service over HTTP instead
     * of spawning a new Python process. The service must already be
     * running (see class docblock).
     */
    private static function callOcrService(string $imagePath): array
    {
        // Resolve to an absolute path — the Python service runs as its own
        // process with its own working directory, so a relative path from
        // PHP's perspective won't necessarily resolve on the Python side.
        $absolutePath = realpath($imagePath);
        if ($absolutePath === false) {
            throw new RuntimeException('Could not resolve absolute path for image: ' . $imagePath);
        }

        $payload = json_encode(['image_path' => $absolutePath]);

        $ch = curl_init(OCR_SERVICE_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => OCR_SERVICE_TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $response = curl_exec($ch);
        $errorNo = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errorNo === CURLE_COULDNT_CONNECT || $errorNo === CURLE_OPERATION_TIMEDOUT) {
            throw new RuntimeException(
                'Could not reach the OCR service at ' . OCR_SERVICE_URL . '. ' .
                'Make sure ocr_service.py is running in its own terminal window ' .
                '(py -3.13 python\\ocr_service.py) and shows ' .
                '"PaddleOCR models loaded. Service ready on http://127.0.0.1:5001" ' .
                'before you scan.'
            );
        }

        if ($errorNo !== 0) {
            throw new RuntimeException('OCR service request failed: ' . $error);
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new RuntimeException(
                "OCR service returned invalid JSON (HTTP $httpCode):\n" . $response
            );
        }

        if ($httpCode !== 200 || isset($decoded['error'])) {
            throw new RuntimeException(
                'OCR service error (HTTP ' . $httpCode . '): ' . ($decoded['error'] ?? 'unknown error')
            );
        }

        return $decoded;
    }

    /**
     * Optional helper: quick check that the OCR service is up before
     * attempting a scan, so callers can show a friendly message instead
     * of waiting for a timeout. Not required, but handy to call from
     * clients/scan.php before running the OCR itself.
     */
    public static function isServiceRunning(): bool
    {
        $ch = curl_init(OCR_SERVICE_HEALTH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
        ]);
        $response = curl_exec($ch);
        $ok = curl_errno($ch) === 0 && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        return $ok && $response !== false;
    }

    public static function parseFields(string $rawText): array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $rawText)), fn($l) => $l !== ''));
        $fullText = implode(' ', $lines);

        $fields = [
            'first_name'            => '',
            'middle_name'           => '',
            'last_name'             => '',
            'suffix'                => '',
            'region'                => '',
            'province'              => '',
            'city'                  => '',
            'barangay'              => '',
            'residence'             => '',
            'street'                => '',
            'birth_date'            => '',
            'birth_place'           => '',
            'sex'                   => '',
            'civil_status'          => '',
            'religion'              => '',
            'address'               => '',
            'contact_number'        => '',
            'email'                 => '',
            'fb_messenger_name'     => '',
            'ethnic_origin'         => '',
            'language_spoken'       => '',
            'osca_id_no'            => '',
            'gsis_sss_no'           => '',
            'tin'                   => '',
            'philhealth_no'         => '',
            'sc_association_id_no'  => '',
            'other_govt_id_no'      => '',
            'employment_business'   => '',
            'has_pension'           => '',
            'capability_to_travel'  => '',
            'id_number'             => '',
            'id_type'               => '',
        ];

        self::detectName($lines, $fields);
        self::detectBirthDate($lines, $fullText, $fields);
        self::detectSex($lines, $fullText, $fields);
        self::detectAddress($lines, $fields);
        self::detectIdNumber($fullText, $fields, $lines);
        self::detectIdType($fullText, $fields);

        $labelMap = [
            'last_name'            => ['lastname', 'last name', 'apelyido'],
            'first_name'           => ['firstname', 'first name', 'pangalan', 'given name', 'given names'],
            'middle_name'          => ['middlename', 'middle name', 'gitnang pangalan', 'gitnang apelyido'],
            'suffix'                => ['extension'],
            'region'                => ['region'],
            'province'              => ['province'],
            'city'                  => ['city', 'city/municipality', 'municipality'],
            'barangay'              => ['barangay'],
            'residence'             => ['residence', 'house no', 'block/lot'],
            'street'                => ['street', 'zone/purok/sitio', 'purok'],
            'birth_place'           => ['birth place', 'birthplace', 'lugar ng kapanganakan'],
            'civil_status'          => ['marital status'],
            'religion'              => ['religion'],
            'contact_number'        => ['contact number'],
            'email'                 => ['email address', 'email'],
            'fb_messenger_name'     => ['fb messenger name', 'messenger name'],
            'ethnic_origin'         => ['ethnic origin'],
            'language_spoken'       => ['language spoken'],
            'osca_id_no'            => ['osca id no', 'osca id'],
            'gsis_sss_no'           => ['gsis/sss no', 'gsis sss no'],
            'tin'                   => ['tin'],
            'philhealth_no'         => ['philhealth no'],
            'sc_association_id_no'  => ['sc association id no'],
            'other_govt_id_no'      => ["other gov't id no", 'other govt id no'],
            'employment_business'   => ['employment / business', 'employment/business'],
            'has_pension'           => ['has pension'],
            'capability_to_travel'  => ['capability to travel'],
        ];

        foreach ($labelMap as $fieldKey => $labelVariants) {
            if ($fields[$fieldKey] !== '') {
                continue;
            }
            $value = self::findLabeledValue($lines, $labelVariants);
            if ($value !== null) {
                $fields[$fieldKey] = ($fieldKey === 'first_name' || $fieldKey === 'middle_name' || $fieldKey === 'last_name')
                    ? self::cleanName($value)
                    : $value;
            }
        }

        if ($fields['birth_date'] === '') {
            foreach ($lines as $i => $line) {
                if (preg_match('/month\s*\/\s*date\s*\/\s*year/i', $line) && isset($lines[$i + 1])) {
                    if (preg_match('/(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{4})/', $lines[$i + 1], $m)) {
                        $fields['birth_date'] = self::normalizeDate("{$m[1]}/{$m[2]}/{$m[3]}");
                    }
                }
            }
        }

        return $fields;
    }

    public static function computeFieldConfidence(array $fields, array $words): array
    {
        $confidence = [];
        $usedIndices = [];

        foreach ($fields as $key => $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $tokens = preg_split('/\s+/', trim($value));
            $matchedConfs = [];

            foreach ($tokens as $token) {
                $normToken = strtolower(preg_replace('/[^a-z0-9]/i', '', $token));
                if ($normToken === '') {
                    continue;
                }

                foreach ($words as $i => $w) {
                    if (in_array($i, $usedIndices, true)) {
                        continue;
                    }
                    $normWord = strtolower(preg_replace('/[^a-z0-9]/i', '', $w['text']));
                    if ($normWord === '') {
                        continue;
                    }
                    if ($normWord === $normToken || (strlen($normToken) > 3 && str_contains($normWord, $normToken))) {
                        $matchedConfs[] = $w['conf'];
                        $usedIndices[] = $i;
                        break;
                    }
                }
            }

            if (!empty($matchedConfs)) {
                $confidence[$key] = round(array_sum($matchedConfs) / count($matchedConfs), 1);
            }
        }

        return $confidence;
    }

    public static function suggestCorrections(array $fields): array
    {
        $suggestions = [];
        foreach ($fields as $key => $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $suggested = self::suggestFieldCorrection($key, $value);
            if ($suggested !== null) {
                $suggestions[$key] = $suggested;
            }
        }
        return $suggestions;
    }

    private static function suggestFieldCorrection(string $key, string $value): ?string
    {
        $letterFields = [
            'first_name', 'middle_name', 'last_name', 'region', 'province', 'city',
            'barangay', 'residence', 'street', 'birth_place', 'religion', 'ethnic_origin',
            'language_spoken', 'employment_business', 'fb_messenger_name', 'nationality',
            'father_name', 'mother_name', 'school', 'course',
            'full_name', 'company', 'position', 'store_name', 'company_name', 'customer_name',
        ];
        $digitFields = [
            'contact_number', 'tin', 'gsis_sss_no', 'philhealth_no',
            'osca_id_no', 'sc_association_id_no', 'other_govt_id_no', 'id_number',
            'phone_number', 'receipt_number', 'invoice_number', 'total_amount', 'tax_amount',
        ];

        $suggested = $value;

        if (in_array($key, $letterFields, true)) {
            $letterCount = preg_match_all('/[A-Za-z]/', $value);
            $digitCount = preg_match_all('/[0-9]/', $value);

            if ($letterCount > 0 && $digitCount > 0 && $letterCount >= $digitCount) {
                $map = ['0' => 'O', '1' => 'I', '5' => 'S', '8' => 'B', '6' => 'G'];
                $suggested = strtr($suggested, $map);
            }

            if ($suggested === strtoupper($suggested) || $suggested === strtolower($suggested)) {
                $titled = ucwords(strtolower($suggested));
                if ($titled !== $suggested) {
                    $suggested = $titled;
                }
            }
        } elseif (in_array($key, $digitFields, true)) {
            $letterMap = ['O' => '0', 'o' => '0', 'I' => '1', 'l' => '1', 'S' => '5', 's' => '5', 'B' => '8', 'G' => '6', 'Z' => '2', 'z' => '2'];
            $hasLetters = (bool)preg_match('/[A-Za-z]/', $value);
            if ($hasLetters) {
                $suggested = strtr($suggested, $letterMap);
            }

            if ($key === 'contact_number' || $key === 'phone_number') {
                $digitsOnly = preg_replace('/\D/', '', $suggested);
                if (strlen($digitsOnly) === 11 && str_starts_with($digitsOnly, '09')) {
                    $formatted = substr($digitsOnly, 0, 4) . '-' . substr($digitsOnly, 4, 3) . '-' . substr($digitsOnly, 7, 4);
                    if ($formatted !== $suggested) {
                        $suggested = $formatted;
                    }
                } elseif (strlen($digitsOnly) === 12 && str_starts_with($digitsOnly, '639')) {
                    $local = '0' . substr($digitsOnly, 2);
                    $suggested = substr($local, 0, 4) . '-' . substr($local, 4, 3) . '-' . substr($local, 7, 4);
                }
            }
        }

        return $suggested !== $value ? $suggested : null;
    }

    private static function findLabeledValue(array $lines, array $labelVariants): ?string
    {
        foreach ($lines as $i => $line) {
            $normalized = strtolower(preg_replace('/[^a-z0-9\/\'\s]/i', '', $line));
            foreach ($labelVariants as $variant) {
                if (str_contains($normalized, $variant)) {
                    if (preg_match('/[:\-]\s*(.+)/', $line, $m) && trim($m[1]) !== '') {
                        return trim($m[1]);
                    }
                    if (isset($lines[$i + 1]) && !self::isLikelyLabel($lines[$i + 1])) {
                        return trim($lines[$i + 1]);
                    }
                }
            }
        }
        return null;
    }

    private static function isLikelyLabel(string $line): bool
    {
        $trimmed = trim($line);

        // Numbered list style label, e.g. "1. Last Name"
        if (preg_match('/^\d{1,2}[a-z]?\.\s/i', $trimmed)) {
            return true;
        }

        // PhilSys-style bilingual labels always contain a '/' separating the
        // Filipino and English wording, e.g. "APELYIDO/LAST NAME",
        // "PETSA NG KAPANGANAKAN/DATE OF BIRTH". Actual data values (names,
        // addresses) printed in all-caps on the ID never contain a '/', so
        // this is a much safer signal than a generic "is it all caps" check
        // (which previously misclassified names like "ANGEL MAE" and
        // "BUENAVENTURA" as labels and caused them to be skipped).
        if (str_contains($trimmed, '/') && preg_match('/[A-Za-z]/', $trimmed)) {
            return true;
        }

        return false;
    }

    private static function detectName(array $lines, array &$fields): void
    {
        foreach ($lines as $line) {
            if (preg_match('/last\s*name\s*[:\-]\s*(.+)/i', $line, $m)) {
                $fields['last_name'] = self::cleanName($m[1]);
            }
            if (preg_match('/first\s*name\s*[:\-]\s*(.+)/i', $line, $m)) {
                $fields['first_name'] = self::cleanName($m[1]);
            }
            if (preg_match('/middle\s*name\s*[:\-]\s*(.+)/i', $line, $m)) {
                $fields['middle_name'] = self::cleanName($m[1]);
            }
        }
        if ($fields['first_name'] !== '' || $fields['last_name'] !== '') {
            return;
        }

        foreach ($lines as $line) {
            if (preg_match('/^([A-Z\'\-]{2,})\s*,\s*([A-Z\'\- ]{2,})$/', $line, $m)) {
                $fields['last_name'] = self::cleanName($m[1]);
                $nameParts = preg_split('/\s+/', trim($m[2]));
                $fields['first_name'] = self::cleanName($nameParts[0] ?? '');
                if (count($nameParts) > 1) {
                    $fields['middle_name'] = self::cleanName(implode(' ', array_slice($nameParts, 1)));
                }
                return;
            }
        }

        foreach ($lines as $line) {
            if (preg_match('/^name\s*[:\-]\s*(.+)/i', $line, $m)) {
                $raw = trim($m[1]);
                $parts = preg_split('/\s+/', $raw);
                $parts = array_values(array_filter($parts, fn($p) => $p !== ''));

                if (count($parts) >= 3) {
                    $fields['first_name'] = self::cleanName($parts[0]);
                    $fields['last_name']  = self::cleanName(end($parts));
                    $middle = array_slice($parts, 1, count($parts) - 2);
                    $fields['middle_name'] = self::cleanName(implode(' ', $middle));
                } elseif (count($parts) === 2) {
                    $fields['first_name'] = self::cleanName($parts[0]);
                    $fields['last_name']  = self::cleanName($parts[1]);
                } elseif (count($parts) === 1) {
                    $fields['first_name'] = self::cleanName($parts[0]);
                }
                return;
            }
        }
    }

    private static function detectBirthDate(array $lines, string $fullText, array &$fields): void
    {
        foreach ($lines as $i => $line) {
            if (preg_match('/(date\s*of\s*birth|birth\s*date|dob|petsa\s*ng\s*kapanganakan)/i', $line)) {
                if (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/', $line, $m)) {
                    $fields['birth_date'] = self::normalizeDate($m[1]);
                    return;
                }
                if (preg_match('/\b((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s*\d{4})\b/i', $line, $m)) {
                    $fields['birth_date'] = self::normalizeDate($m[1]);
                    return;
                }
                if (isset($lines[$i + 1])) {
                    $next = $lines[$i + 1];
                    if (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/', $next, $m)) {
                        $fields['birth_date'] = self::normalizeDate($m[1]);
                        return;
                    }
                    if (preg_match('/\b((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s*\d{4})\b/i', $next, $m)) {
                        $fields['birth_date'] = self::normalizeDate($m[1]);
                        return;
                    }
                }
            }
        }

        if (preg_match('/\b((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s*\d{4})\b/i', $fullText, $m)) {
            $fields['birth_date'] = self::normalizeDate($m[1]);
        } elseif (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})\b/', $fullText, $m)) {
            $fields['birth_date'] = self::normalizeDate($m[1]);
        }
    }

    private static function detectSex(array $lines, string $fullText, array &$fields): void
    {
        if (preg_match('/\bsex\s*[:\-]?\s*(male|female|M|F)\b/i', $fullText, $m)) {
            $fields['sex'] = strtoupper($m[1][0]) === 'M' ? 'Male' : 'Female';
            return;
        }

        foreach ($lines as $i => $line) {
            if (preg_match('/^sex/i', trim($line)) && isset($lines[$i + 1])) {
                if (preg_match('/^(M|F|Male|Female)$/i', trim($lines[$i + 1]), $m)) {
                    $fields['sex'] = strtoupper($m[1][0]) === 'M' ? 'Male' : 'Female';
                    return;
                }
            }
        }
    }

    private static function detectAddress(array $lines, array &$fields): void
    {
        foreach ($lines as $i => $line) {
            if (!preg_match('/\baddress\b/i', $line)) {
                continue;
            }

            $address = '';
            if (preg_match('/[:\-]\s*(.+)/', $line, $m) && trim($m[1]) !== '') {
                $address = trim($m[1]);
                $j = $i + 1;
            } else {
                $j = $i + 1;
            }

            while (isset($lines[$j]) && !self::looksLikeNewLabel($lines[$j]) && strlen($address) < 160) {
                $address .= ($address === '' ? '' : ' ') . trim($lines[$j]);
                $j++;
            }

            if ($address !== '') {
                $fields['address'] = trim(preg_replace('/\s+/', ' ', $address));
                return;
            }
        }
    }

    private static function looksLikeNewLabel(string $line): bool
    {
        return (bool)preg_match('/^(name|address|sex|date of birth|birth\s*date|dob|id\s*(type|number)|control\s*no|digital\s*id)/i', trim($line));
    }

    private static function detectIdNumber(string $fullText, array &$fields, array $lines = []): void
    {
        foreach ($lines as $i => $line) {
            if (preg_match('/digital\s*id\s*numb/i', $line)) {
                if (preg_match('/[:\-]\s*([A-Z0-9]{4,})/i', $line, $m)) {
                    $fields['id_number'] = trim($m[1]);
                    return;
                }
                if (isset($lines[$i + 1]) && preg_match('/^[A-Z0-9]{4,}$/i', trim($lines[$i + 1]))) {
                    $fields['id_number'] = trim($lines[$i + 1]);
                    return;
                }
            }
        }

        if (preg_match('/(id\s*number|pcn|control\s*no\.?)\s*[:\-]?\s*([0-9][0-9\-\s]{5,20}[0-9])/i', $fullText, $m)) {
            $fields['id_number'] = trim(preg_replace('/\s+/', ' ', $m[2]));
            return;
        }
        if (preg_match('/\b(\d{4}[\-\s]?\d{4}[\-\s]?\d{4}[\-\s]?\d{4})\b/', $fullText, $m)) {
            $fields['id_number'] = trim($m[1]);
            return;
        }
        if (preg_match('/\b(\d{4}[\-\s]?\d{4,7}[\-\s]?\d{0,4})\b/', $fullText, $m)) {
            $fields['id_number'] = trim($m[1]);
        }
    }

    private static function detectIdType(string $fullText, array &$fields): void
    {
        $idTypeMap = [
            'philippine identification' => 'National ID (PhilSys)',
            'philsys'                   => 'National ID (PhilSys)',
            'pambansang pagkakakilanlan' => 'National ID (PhilSys)',
            'senior citizen'            => 'Senior Citizen ID (OSCA)',
            'office for senior citizens' => 'Senior Citizen ID (OSCA)',
            'driver'                    => "Driver's License",
            'passport'                  => 'Passport',
            'sss'                       => 'SSS ID',
            'philhealth'                => 'PhilHealth ID',
            'unified multi-purpose'     => 'UMID',
            'voter'                     => "Voter's ID",
            'pwd'                       => 'PWD ID',
        ];
        foreach ($idTypeMap as $keyword => $label) {
            if (stripos($fullText, $keyword) !== false) {
                $fields['id_type'] = $label;
                return;
            }
        }
    }

    private static function cleanName(string $name): string
    {
        // \p{L} (Unicode "letter" category) keeps accented Filipino letters
        // like Ñ/ñ, Á, É, etc. instead of deleting them the way a plain
        // A-Za-z character class does (which was turning "PAÑOSO" into
        // "Paoso"). The /u flag makes the regex treat the string as UTF-8.
        $name = preg_replace('/[^\p{L}\'\-\. ]/u', '', $name);
        $name = trim($name);
        // mb_convert_case handles multibyte title-casing correctly (plain
        // ucwords/strtolower can mangle UTF-8 accented characters).
        return mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private static function normalizeDate(string $raw): string
    {
        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d', $ts) : '';
    }
}