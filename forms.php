<?php
/**
 * Form rendering + save helpers shared by bank & insurance forms.
 */
require_once __DIR__ . '/lib.php';

/** A labelled text/number/date input. */
function f_input(string $name, string $label, array $row, string $type = 'text', bool $req = false, string $attr = ''): string {
    $v = e($row[$name] ?? '');
    $r = $req ? ' required' : '';
    return '<div class="f"><label class="f">' . e($label) . '</label>'
        . '<input type="' . $type . '" name="' . e($name) . '" value="' . $v . '"' . $r . ' ' . $attr . '></div>';
}

/** A labelled textarea. */
function f_text(string $name, string $label, array $row, string $ph = ''): string {
    $v = e($row[$name] ?? '');
    return '<div class="f"><label class="f">' . e($label) . '</label>'
        . '<textarea name="' . e($name) . '" placeholder="' . e($ph) . '">' . $v . '</textarea></div>';
}

/** A labelled select fed from a lookup table. */
function f_select(string $name, string $label, string $table, array $row): string {
    return '<div class="f"><label class="f">' . e($label) . '</label>'
        . '<select name="' . e($name) . '">' . options($table, $row[$name] ?? null) . '</select></div>';
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

    if ($id) {
        $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $sql = "UPDATE `$table` SET $sets WHERE id = ?";
        $vals = array_values($data); $vals[] = $id;
        db()->prepare($sql)->execute($vals);
        return (int)$id;
    }
    $data['created_at'] = $now;
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

    // images (multiple)
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
    if ($paths) {
        $existing = !empty($row['images']) ? (json_decode($row['images'], true) ?: []) : [];
        $out['images'] = json_encode(array_merge($existing, $paths));
    } elseif (!empty($row['images'])) {
        $out['images'] = $row['images'];
    }
    return $out;
}
