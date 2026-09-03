<?php
require_once '../config/koneksi.php';
require_once 'foto_helper.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $conn->query("SELECT setting_key, setting_value FROM pengaturan_sekolah");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $tahun_ajaran = isset($settings['tahun_ajaran']) ? $settings['tahun_ajaran'] : '2025/2026';
        $link_ppdb = isset($settings['link_ppdb']) ? $settings['link_ppdb'] : '';
        $email_sekolah = isset($settings['email_sekolah']) ? $settings['email_sekolah'] : 'sdnmulyoagung01@gmail.com';
        $telepon_sekolah = isset($settings['telepon_sekolah']) ? $settings['telepon_sekolah'] : '(0341) 466-730';
        $whatsapp_sekolah = isset($settings['whatsapp_sekolah']) ? $settings['whatsapp_sekolah'] : '08123456789';
        $alamat_sekolah = isset($settings['alamat_sekolah']) ? $settings['alamat_sekolah'] : '';
        
        $medsos_links = [];
        if (isset($settings['medsos_links'])) {
            $decoded = json_decode($settings['medsos_links'], true);
            if (is_array($decoded)) {
                $medsos_links = $decoded;
            }
        }

        $homepage_sections = [];
        if (isset($settings['homepage_sections'])) {
            $decoded = json_decode($settings['homepage_sections'], true);
            if (is_array($decoded)) {
                $homepage_sections = $decoded;
            }
        }

        $hero_title = isset($settings['hero_title']) ? $settings['hero_title'] : '';
        $hero_subtitle = isset($settings['hero_subtitle']) ? $settings['hero_subtitle'] : '';
        $hero_bg = isset($settings['hero_bg']) ? $settings['hero_bg'] : '';
        $video_url = isset($settings['video_url']) ? $settings['video_url'] : '';
        $profil_visi = isset($settings['profil_visi']) ? $settings['profil_visi'] : '';
        
        $profil_misi = [];
        if (isset($settings['profil_misi'])) {
            $decoded = json_decode($settings['profil_misi'], true);
            if (is_array($decoded)) {
                $profil_misi = $decoded;
            }
        }
        $profil_sejarah = isset($settings['profil_sejarah']) ? $settings['profil_sejarah'] : '';

        echo json_encode([
            "status" => "success",
            "tahun_ajaran" => $tahun_ajaran,
            "link_ppdb" => $link_ppdb,
            "email_sekolah" => $email_sekolah,
            "telepon_sekolah" => $telepon_sekolah,
            "whatsapp_sekolah" => $whatsapp_sekolah,
            "alamat_sekolah" => $alamat_sekolah,
            "medsos_links" => $medsos_links,
            "homepage_sections" => $homepage_sections,
            "hero_title" => $hero_title,
            "hero_subtitle" => $hero_subtitle,
            "hero_bg" => $hero_bg,
            "video_url" => $video_url,
            "profil_visi" => $profil_visi,
            "profil_misi" => $profil_misi,
            "profil_sejarah" => $profil_sejarah,
            "data" => $settings
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    $keys_to_save = [
        'tahun_ajaran', 'link_ppdb', 'email_sekolah', 'telepon_sekolah', 
        'whatsapp_sekolah', 'alamat_sekolah', 'medsos_links',
        'homepage_sections', 'hero_title', 'hero_subtitle', 'video_url', 
        'profil_visi', 'profil_misi', 'profil_sejarah'
    ];

    try {
        $stmt = $conn->prepare("INSERT INTO pengaturan_sekolah (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        
        foreach ($keys_to_save as $key) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                $stmt->execute([$key, $val, $val]);
            }
        }

        // Handle upload hero background if present
        $upload_dir = '../uploads/hero/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (foto_has_upload('hero_bg')) {
            $stmtOld = $conn->prepare("SELECT setting_value FROM pengaturan_sekolah WHERE setting_key = 'hero_bg'");
            $stmtOld->execute();
            $old_bg = $stmtOld->fetchColumn();
            if ($old_bg) {
                foto_unlink($old_bg);
            }
            
            $new_bg = foto_save_file('hero_bg', $upload_dir, 'backend/uploads/hero/');
            if ($new_bg) {
                $stmt->execute(['hero_bg', $new_bg, $new_bg]);
            }
        }

        echo json_encode([
            "status" => "success",
            "message" => "Pengaturan halaman utama & kontak sekolah berhasil diperbarui."
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Metode request tidak diizinkan."]);
}
?>
