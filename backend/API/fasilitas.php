<?php
require_once '../config/koneksi.php';
require_once 'foto_helper.php';

header("Content-Type: application/json");

// Ensure upload directory exists
$upload_dir = '../uploads/fasilitas/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $conn->query("SELECT * FROM fasilitas ORDER BY id DESC");
        $data = $stmt->fetchAll();
        foto_map_rows($data);
        echo json_encode(["status" => "success", "data" => $data]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
elseif ($method === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'create') {
        $judul = isset($_POST['judul']) ? trim($_POST['judul']) : '';
        $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';

        if (empty($judul) || empty($deskripsi)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Judul dan deskripsi fasilitas wajib diisi."]);
            exit();
        }

        $has_original = isset($_FILES['foto_original']) && $_FILES['foto_original']['error'] === UPLOAD_ERR_OK;
        $has_crop = isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK;
        if ($has_original || $has_crop) {
            [$foto_path, $foto_crop_path] = foto_handle_create($upload_dir, 'backend/uploads/fasilitas/');
        } elseif (isset($_POST['foto_url']) && !empty(trim($_POST['foto_url']))) {
            $foto_path = trim($_POST['foto_url']);
            $foto_crop_path = '';
        } else {
            $foto_path = '';
            $foto_crop_path = '';
        }

        try {
            $stmt = $conn->prepare("INSERT INTO fasilitas (judul, deskripsi, foto, foto_crop) VALUES (?, ?, ?, ?)");
            $stmt->execute([$judul, $deskripsi, $foto_path, $foto_crop_path]);
            echo json_encode(["status" => "success", "message" => "Fasilitas berhasil ditambahkan."]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
    elseif ($action === 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $judul = isset($_POST['judul']) ? trim($_POST['judul']) : '';
        $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';

        if ($id === 0 || empty($judul) || empty($deskripsi)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Data tidak lengkap untuk pembaruan."]);
            exit();
        }

        $stmt = $conn->prepare("SELECT foto, foto_crop FROM fasilitas WHERE id = ?");
        $stmt->execute([$id]);
        $fac = $stmt->fetch();
        if (!$fac) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Fasilitas tidak ditemukan."]);
            exit();
        }

        $has_original = isset($_FILES['foto_original']) && $_FILES['foto_original']['error'] === UPLOAD_ERR_OK;
        $has_crop = isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK;
        if (isset($_POST['foto_url']) && !empty(trim($_POST['foto_url'])) && !$has_original && !$has_crop) {
            $foto_path = trim($_POST['foto_url']);
            $foto_crop_path = '';
            if (strpos($fac['foto'], 'backend/uploads/') === 0) foto_unlink($fac['foto']);
            if (strpos($fac['foto_crop'] ?? '', 'backend/uploads/') === 0) foto_unlink($fac['foto_crop']);
        } elseif ($has_original || $has_crop) {
            [$foto_path, $foto_crop_path] = foto_handle_update($upload_dir, 'backend/uploads/fasilitas/', $fac['foto'], $fac['foto_crop'] ?? '');
        } else {
            $foto_path = $fac['foto'];
            $foto_crop_path = $fac['foto_crop'] ?? '';
        }

        try {
            $stmt = $conn->prepare("UPDATE fasilitas SET judul = ?, deskripsi = ?, foto = ?, foto_crop = ? WHERE id = ?");
            $stmt->execute([$judul, $deskripsi, $foto_path, $foto_crop_path, $id]);
            echo json_encode(["status" => "success", "message" => "Fasilitas berhasil diperbarui."]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
    elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id === 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID tidak valid."]);
            exit();
        }

        $stmt = $conn->prepare("SELECT foto, foto_crop FROM fasilitas WHERE id = ?");
        $stmt->execute([$id]);
        $fac = $stmt->fetch();
        if ($fac) {
            if (!empty($fac['foto']) && strpos($fac['foto'], 'backend/uploads/') === 0) {
                foto_unlink($fac['foto']);
            }
            if (!empty($fac['foto_crop']) && strpos($fac['foto_crop'], 'backend/uploads/') === 0) {
                foto_unlink($fac['foto_crop']);
            }
        }

        try {
            $stmt = $conn->prepare("DELETE FROM fasilitas WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["status" => "success", "message" => "Fasilitas berhasil dihapus."]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    }
    else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Aksi tidak dikenal."]);
    }
}
else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
?>
