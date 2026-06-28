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
function f_money(string $name, string $label, array $row, bool $req = false, bool $words = false): string {
    $v = e($row[$name] ?? '');
    $r = $req ? ' required' : '';
    $w = $words ? ' data-words="1"' : '';
    return '<div class="f' . err_class($name) . '"><label class="f">' . e($label) . '</label>'
        . '<input type="text" class="money" inputmode="decimal" name="' . e($name) . '" value="' . $v . '"' . $r . $w . '>'
        . ($words ? '<small class="words-preview muted"></small>' : '') . field_err($name) . '</div>';
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
        if (array_key_exists($c, $post)) {
            $val = is_string($post[$c]) ? trim($post[$c]) : $post[$c];
            $data[$c] = ($val === '') ? null : $val;
        }
    }
    foreach ($extra as $k => $v) $data[$k] = $v; // file paths, json, etc.

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
    if (column_exists($table, 'created_by')) $data['created_by'] = $uid;
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

    // logbook (single)
    if (!empty($_FILES['logbook']['name'])) {
        @mkdir($base, 0775, true);
        $fn = uniqid('logbook_') . '_' . basename($_FILES['logbook']['name']);
        if (move_uploaded_file($_FILES['logbook']['tmp_name'], "$base/$fn")) {
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
    if (!empty($_FILES['images']['name'][0])) {
        @mkdir("$base/images", 0775, true);
        foreach ($_FILES['images']['name'] as $i => $name) {
            if (!$name) continue;
            $fn = uniqid('img_') . '_' . basename($name);
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], "$base/images/$fn")) {
                $paths[] = "photos/$reg/images/$fn";
            }
        }
    }
    $all = array_merge($existing, $paths);
    $out['images'] = $all ? json_encode($all) : null;
    return $out;
}
