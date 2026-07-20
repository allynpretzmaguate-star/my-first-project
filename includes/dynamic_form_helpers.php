<?php
/**
 * NEW FILE — save this as: includes/dynamic_form_helpers.php
 * ---------------------------------------------------------------
 * Renders form fields / detail rows straight from DocumentFieldConfig.
 * Uses your existing CSS classes (.form-grid, .form-group, .detail-list)
 * so the visual design doesn't change — only how the fields are
 * generated (dynamically, instead of hardcoded per document type).
 *
 * require_once this file after DocumentFieldConfig.php in add.php,
 * edit.php, and view.php.
 * ---------------------------------------------------------------
 */

/**
 * Renders an editable <div class="form-grid">...</div> for the given
 * document type, pre-filled with $values (assoc array keyed by db_key)
 * and annotated with OCR confidence badges from $fieldMeta if present.
 */
function render_dynamic_form_fields(string $docType, array $values, array $fieldMeta = []): void
{
    $config = DocumentFieldConfig::get($docType);
    if ($config === null) {
        echo '<p class="text-error">No field configuration found for this document type.</p>';
        return;
    }

    echo '<div class="form-grid">';
    foreach ($config['fields'] as $field) {
        $key   = $field['db_key'];
        $label = h($field['label']);
        $type  = $field['type'];
        $req   = !empty($field['required']);
        $value = h($values[$key] ?? '');

        $conf = $fieldMeta[$key]['confidence'] ?? null;
        $confBadge = '';
        if ($conf !== null) {
            $confBadge = $conf >= 85
                ? '<span class="text-success small"> (✔ ' . h((string)$conf) . '%)</span>'
                : '<span class="text-error small"> (⚠ ' . h((string)$conf) . '%)</span>';
        }

        $wrapClass = 'form-group' . ($type === 'textarea' ? ' form-group-full' : '');

        echo '<div class="' . $wrapClass . '">';
        echo '<label for="' . h($key) . '">' . $label . ($req ? ' *' : '') . $confBadge . '</label>';

        switch ($type) {
            case 'select':
                echo '<select name="' . h($key) . '" id="' . h($key) . '"' . ($req ? ' required' : '') . '>';
                echo '<option value="">-- Select --</option>';
                foreach ($field['options'] as $opt) {
                    $sel = ($values[$key] ?? '') === $opt ? ' selected' : '';
                    echo '<option value="' . h($opt) . '"' . $sel . '>' . h($opt) . '</option>';
                }
                echo '</select>';
                break;

            case 'textarea':
                echo '<textarea name="' . h($key) . '" id="' . h($key) . '" rows="2"'
                    . ($req ? ' required' : '') . '>' . $value . '</textarea>';
                break;

            case 'date':
                echo '<input type="date" name="' . h($key) . '" id="' . h($key) . '"'
                    . ' value="' . $value . '"' . ($req ? ' required' : '') . '>';
                break;

            default: // text
                echo '<input type="text" name="' . h($key) . '" id="' . h($key) . '"'
                    . ' value="' . $value . '"' . ($req ? ' required' : '') . '>';
        }

        echo '</div>';
    }
    echo '</div>';
}

/**
 * Renders a read-only <dl class="detail-list">...</dl> for view.php,
 * again driven entirely by DocumentFieldConfig — no per-type HTML.
 */
function render_dynamic_detail_fields(string $docType, array $values): void
{
    $config = DocumentFieldConfig::get($docType);
    if ($config === null) {
        return;
    }

    echo '<dl class="detail-list">';
    foreach ($config['fields'] as $field) {
        $key = $field['db_key'];
        $raw = $values[$key] ?? null;

        if ($field['type'] === 'date' && !empty($raw)) {
            $display = format_date($raw, 'F d, Y');
        } else {
            $display = ($raw !== null && $raw !== '') ? h((string)$raw) : '—';
        }

        echo '<dt>' . h($field['label']) . '</dt>';
        echo '<dd>' . $display . '</dd>';
    }
    echo '</dl>';
}

/**
 * Server-side validation using each field's 'required' flag and optional
 * 'validate' regex from DocumentFieldConfig. Returns an array of error
 * strings (empty array = valid).
 */
function validate_dynamic_fields(string $docType, array $posted): array
{
    $config = DocumentFieldConfig::get($docType);
    $errors = [];
    if ($config === null) {
        return ['Unknown or unsupported document type.'];
    }

    foreach ($config['fields'] as $field) {
        $key = $field['db_key'];
        $val = trim((string)($posted[$key] ?? ''));

        if (!empty($field['required']) && $val === '') {
            $errors[] = $field['label'] . ' is required.';
            continue;
        }
        if ($val !== '' && !empty($field['validate']) && !preg_match($field['validate'], $val)) {
            $errors[] = $field['label'] . ' has an invalid format.';
        }
        if ($field['type'] === 'select' && $val !== '' && !in_array($val, $field['options'], true)) {
            $errors[] = 'Invalid value for ' . $field['label'] . '.';
        }
    }

    return $errors;
}