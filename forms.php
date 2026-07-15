<?php
/**
 * Form rendering + save helpers shared by bank & insurance forms.
 */
require_once __DIR__ . '/lib.php';

/** Per-field validation errors (set by the form handler before re-render). */
$GLOBALS['__form_errors'] = [];
function set_form_errors(array $errs): void { $GLOBALS['__form_errors'] = $errs; }
function field_err(string $name): string {
    $e = $GLOBALS['__form_errors'][$name] ?? '';
    return $e ? '<small class="field-err">' . e($e) . '</small>' : '';
}
function err_class(string $name): string {
    return isset($GLOBALS['__form_errors'][$name]) ? ' has-err' : '';
}

/** A labelled text/number/date input. */
function f_input(string $name, string $label, array $row, string $type = 'text', bool $req = false, string $attr = ''): string {
    $v = e($row[$name] ?? '');
    $r = $req ? ' required' : '';
    return '<div class="f' . err_class($name) . '"><label class="f">' . e($label) . '</label>'
        . '<input type="' . $type . '" name="' . e($name) . '" value="' . $v . '"' . $r . ' ' . $attr . '>'
        . field_err($name) . '</div>';
}

/** A money input (text + class so JS can add live thousands separators). */
function f_money(string $name, string $label, array $row, bool $req = false, bool $words = false, string $fillWords = '', bool $lock = false): string {
    $v = e($row[$name] ?? '');
    $r = $req ? ' required' : '';
    $w = $words ? ' data-words="1"' : '';
    $fw = $fillWords !== '' ? ' data-words-fill="' . e($fillWords) . '"' : ''; // auto-fill another field with the amount in words
    $dis  = $lock ? ' disabled' : '';                                        // valuers can't enter figures — admin only
    $note = $lock ? '<small class="muted" style="display:block;font-size:11px;margin-top:3px">🔒 Entered by an admin</small>' : '';
    return '<div class="f' . err_class($name) . '"><label class="f">' . e($label) . '</label>'
        . '<input type="text" class="money" inputmode="decimal" name="' . e($name) . '" value="' . $v . '"' . $r . $w . $fw . $dis . '>'
        . ($words ? '<small class="words-preview muted"></small>' : '') . $note . field_err($name) . '</div>';
}

/** Like f_input, but adds a "Scan" (photo→text) button when OCR is enabled for the user.
 *  $mode: 'vin' (chassis, check-digit verified) or 'digits' (odometer etc.). */
function f_input_ocr(string $name, string $label, array $row, string $mode = 'digits', string $type = 'text', string $attrs = ''): string {
    $base = f_input($name, $label, $row, $type, false, $attrs);
    if (!function_exists('ocr_enabled_for_user') || !ocr_enabled_for_user()) return $base;
    $scan = '<div class="ocr-row"><button type="button" class="ocr-btn" data-ocr-target="' . e($name) . '" data-ocr-mode="' . e($mode) . '"><i data-lucide="camera"></i> Scan</button>'
          . '<span class="ocr-status" data-ocr-status="' . e($name) . '"></span></div>';
    return preg_replace('#</div>\s*$#', $scan . '</div>', $base, 1);
}

/** A labelled textarea. */
function f_text(string $name, string $label, array $row, string $ph = ''): string {
    $v = e($row[$name] ?? '');
    return '<div class="f' . err_class($name) . '"><label class="f">' . e($label) . '</label>'
        . '<textarea name="' . e($name) . '" placeholder="' . e($ph) . '">' . $v . '</textarea>'
        . field_err($name) . '</div>';
}

/** A labelled select fed from a lookup table. $quickAdd shows an inline "+ Add new". */
function f_select(string $name, string $label, string $table, array $row, bool $quickAdd = false): string {
    $qa = '';
    if ($quickAdd && function_exists('can_edit') && can_edit()) {
        $single = rtrim($table, 's');
        $qa = '<span class="quick-add" data-type="' . e($table) . '" data-target="' . e($name)
            . '" data-label="' . e($single) . '">+ Add new</span>';
    }
    return '<div class="f' . err_class($name) . '"><label class="f">' . e($label) . $qa . '</label>'
        . '<select name="' . e($name) . '">' . options($table, $row[$name] ?? null) . '</select>'
        . field_err($name) . '</div>';
}

/** A Yes/No (1/0) radio pair for the insurance checklist. */
function f_yn(string $name, string $label, array $row): string {
    $v = isset($row[$name]) ? (string)$row[$name] : '';
    $y = $v === '1' ? ' checked' : '';
    $n = $v === '0' ? ' checked' : '';
    return '<div class="f"><label class="f">' . e($label) . '</label><div class="yn">'
        . '<label><input type="radio" name="' . e($name) . '" value="1"' . $y . '> Yes</label>'
        . '<label><input type="radio" name="' . e($name) . '" value="0"' . $n . '> No</label></div></div>';
}

/**
 * Persist a row to $table using only the whitelisted $columns present in $_POST.
 * Returns the row id (existing $id for update, lastInsertId for insert).
 */
function save_row(string $table, array $columns, array $post, $id = null, array $extra = []): int {
    $data = [];
    foreach ($columns as $c) {
        if (array_key_exists($c, $post) && column_exists($table, $c)) {
            $val = is_string($post[$c]) ? trim($post[$c]) : $post[$c];
            $data[$c] = ($val === '') ? null : $val;
        }
    }
    foreach ($extra as $k => $v) if (column_exists($table, $k)) $data[$k] = $v;

    $now = date('Y-m-d H:i:s');
    $data['updated_at'] = $now;
    $uid = current_user()['id'] ?? null;
    if (column_exists($table, 'updated_by')) $data['updated_by'] = $uid;

    if ($id) {
        $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $sql = "UPDATE `$table` SET $sets WHERE id = ?";
        $vals = array_values($data); $vals[] = $id;
        db()->prepare($sql)->execute($vals);
        return (int)$id;
    }
    $data['created_at'] = $now;
    if (column_exists($table, 'created_by') && !array_key_exists('created_by', $data)) $data['created_by'] = $uid;
    $cols = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
    $ph   = implode(', ', array_fill(0, count($data), '?'));
    db()->prepare("INSERT INTO `$table` ($cols) VALUES ($ph)")->execute(array_values($data));
    return (int)db()->lastInsertId();
}

/** Load a single row or return []. */
function load_row(string $table, $id): array {
    if (!$id) return [];
    $st = db()->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    return $st->fetch() ?: [];
}

/**
 * Handle uploaded photos for a valuation. Saves files under uploads/photos/{reg}/...
 * Returns ['logbook' => path|null, 'images' => json|null] for any newly uploaded files,
 * preserving existing values from $row when nothing new is uploaded.
 */
function handle_uploads(string $regNo, array $row): array {
    $reg = preg_replace('/[^A-Za-z0-9_-]/', '_', $regNo ?: 'unknown');
    $base = UPLOAD_DIR . "/photos/$reg";
    $out = [];

    // logbook (single) — resized & compressed
    if (!empty($_FILES['logbook']['name'])) {
        @mkdir($base, 0775, true);
        $fn = uniqid('logbook_') . '.jpg';
        if (save_resized_jpeg($_FILES['logbook']['tmp_name'], "$base/$fn", 1600, 82)) {
            $out['logbook'] = "photos/$reg/$fn";
        }
    } elseif (!empty($row['logbook'])) {
        $out['logbook'] = $row['logbook'];
    }

    // images (multiple): start from existing, drop any removed, add new uploads
    $existing = !empty($row['images']) ? (json_decode($row['images'], true) ?: []) : [];
    $removed  = (array)($_POST['removed_images'] ?? []);
    if ($removed) {
        foreach ($existing as $p) {
            if (in_array($p, $removed, true)) { @unlink(UPLOAD_DIR . '/' . ltrim($p, '/')); }
        }
        $existing = array_values(array_filter($existing, fn($p) => !in_array($p, $removed, true)));
    }
    $paths = [];
    // Accept both the gallery picker (images[]) and the camera capture (camera_photos[]).
    foreach (['images', 'camera_photos'] as $field) {
        if (empty($_FILES[$field]['name'][0])) continue;
        @mkdir("$base/images", 0775, true);
        foreach ($_FILES[$field]['name'] as $i => $name) {
            if (!$name) continue;
            $fn = uniqid('img_') . '.jpg';
            if (save_resized_jpeg($_FILES[$field]['tmp_name'][$i], "$base/images/$fn", 1600, 82)) {
                $paths[] = "photos/$reg/images/$fn";
            }
        }
    }
    $all = array_merge($existing, $paths);
    $out['images'] = $all ? json_encode($all) : null;
    return $out;
}
