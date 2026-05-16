<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$KITTENS_FILE = dirname(__DIR__) . '/kittens.json';
$IMAGE_DIR    = dirname(__DIR__) . '/image/';

// ── Helpers ──

function respond($data) {
    echo json_encode($data);
    exit;
}

function load_kittens($file) {
    if (!file_exists($file)) return [];
    $raw = file_get_contents($file);
    return json_decode($raw, true) ?: [];
}

function save_kittens($file, $kittens) {
    $json = json_encode(array_values($kittens), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents($file, $json) !== false;
}

function generate_id($desc) {
    $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($desc));
    return $slug . '_' . substr(uniqid(), -4);
}

function handle_upload($field, $image_dir) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file    = $_FILES[$field];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) {
        respond(['ok' => false, 'error' => 'File type not allowed: ' . $ext]);
    }
    $filename = 'kitty_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = $image_dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        respond(['ok' => false, 'error' => 'Upload failed — check folder permissions.']);
    }
    return 'image/' . $filename;
}

// ── Router ──

$action = $_POST['action'] ?? '';

if ($action === 'add') {

    $image_path = handle_upload('photo', $IMAGE_DIR);
    if (!$image_path) {
        respond(['ok' => false, 'error' => 'Photo is required for new kittens.']);
    }

    $desc     = trim($_POST['description'] ?? '');
    $gender   = trim($_POST['gender'] ?? 'Female');
    $fee      = (int)($_POST['fee'] ?? 0);
    $location = trim($_POST['location'] ?? '');

    if (!$desc) respond(['ok' => false, 'error' => 'Description is required.']);

    $kittens  = load_kittens($KITTENS_FILE);
    $new_id   = generate_id($desc);
    $kittens[] = [
        'id'          => $new_id,
        'image'       => $image_path,
        'description' => $desc,
        'gender'      => $gender,
        'fee'         => $fee,
        'location'    => $location,
    ];

    if (!save_kittens($KITTENS_FILE, $kittens)) {
        respond(['ok' => false, 'error' => 'Could not write kittens.json.']);
    }
    respond(['ok' => true, 'message' => 'Kitten added!', 'id' => $new_id]);

} elseif ($action === 'edit') {

    $id       = trim($_POST['id'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $gender   = trim($_POST['gender'] ?? 'Female');
    $fee      = (int)($_POST['fee'] ?? 0);
    $location = trim($_POST['location'] ?? '');

    if (!$id)   respond(['ok' => false, 'error' => 'Missing id.']);
    if (!$desc) respond(['ok' => false, 'error' => 'Description is required.']);

    $kittens = load_kittens($KITTENS_FILE);
    $found   = false;

    foreach ($kittens as &$k) {
        if ($k['id'] === $id) {
            $k['description'] = $desc;
            $k['gender']      = $gender;
            $k['fee']         = $fee;
            $k['location']    = $location;
            // Replace photo only if a new one was uploaded
            $new_image = handle_upload('photo', $IMAGE_DIR);
            if ($new_image) $k['image'] = $new_image;
            $found = true;
            break;
        }
    }
    unset($k);

    if (!$found) respond(['ok' => false, 'error' => 'Kitten not found.']);

    if (!save_kittens($KITTENS_FILE, $kittens)) {
        respond(['ok' => false, 'error' => 'Could not write kittens.json.']);
    }
    respond(['ok' => true, 'message' => 'Kitten updated!']);

} elseif ($action === 'delete') {

    $id      = trim($_POST['id'] ?? '');
    if (!$id) respond(['ok' => false, 'error' => 'Missing id.']);

    $kittens = load_kittens($KITTENS_FILE);
    $before  = count($kittens);
    $kittens = array_filter($kittens, function($k) use ($id) { return $k['id'] !== $id; });

    if (count($kittens) === $before) {
        respond(['ok' => false, 'error' => 'Kitten not found.']);
    }

    if (!save_kittens($KITTENS_FILE, $kittens)) {
        respond(['ok' => false, 'error' => 'Could not write kittens.json.']);
    }
    respond(['ok' => true, 'message' => 'Kitten deleted.']);

} else {
    respond(['ok' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);
}
