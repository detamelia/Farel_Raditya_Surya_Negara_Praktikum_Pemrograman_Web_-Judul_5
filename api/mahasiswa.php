
<?php
/**
 * Praktikum 5: Endpoint REST API Mahasiswa Berbasis MySQL & PDO
 * File: api/mahasiswa.php
 * Mendukung metode HTTP GET, POST, PUT, DELETE dengan respon format JSON.
 */

// Header Respons API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Tangani HTTP OPTIONS Pre-flight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Inisialisasi Koneksi Database
try {
    $db = new Database();
    $pdo = $db->getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal terhubung ke database: ' . $e->getMessage(),
        'data' => null
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Routing Berdasarkan HTTP Method
switch ($method) {
    case 'GET':
        handleGet($pdo, $id);
        break;

    case 'POST':
        handlePost($pdo);
        break;

    case 'PUT':
        handlePut($pdo, $id);
        break;

    case 'DELETE':
        handleDelete($pdo, $id);
        break;

    default:
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => "Metode HTTP {$method} tidak diizinkan.",
            'data' => null
        ]);
        break;
}

/**
 * 1. Menangani HTTP GET (Ambil Semua Data atau Berdasarkan ID)
 */
function handleGet(PDO $pdo, ?int $id): void {
    if ($id !== null && $id > 0) {
        //Percobaan 5-2 Langkah 2 jika id diberikan
        $stmt = $pdo->prepare(
            'SELECT id, npm, nama, program_studi, angkatan, email, created_at
             FROM mahasiswa WHERE id = :id LIMIT 1'
        );

        $stmt->execute([':id' => $id]);
        $mahasiswa = $stmt->fetch();

        if ($mahasiswa) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'data' => $mahasiswa
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => "Mahasiswa dengan ID {$id} tidak ditemukan."
            ]);
        }
    } else {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        if (!empty($search)) {
            //Percobaan 5-2 Langkah 2 jika search ada
            $stmt = $pdo->prepare(
                'SELECT id, npm, nama, program_studi, angkatan, email, created_at
                 FROM mahasiswa
                 WHERE npm LIKE :kw1 OR nama LIKE :kw2
                 OR program_studi LIKE :kw3
                 ORDER BY id DESC'
            );

            $kw = "%{$search}%";

            $stmt->execute([
                ':kw1' => $kw,
                ':kw2' => $kw,
                ':kw3' => $kw
            ]);
        } else {
            //Percobaan 5-2 Langkah 2 jika tidak ada search
            $stmt = $pdo->query(
                'SELECT id, npm, nama, program_studi, angkatan, email, created_at
                 FROM mahasiswa ORDER BY id DESC'
            );
        }

        $result = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'total' => count($result),
            'data' => $result
        ]);
    }
}

/**
 * 2. Menangani HTTP POST (Tambah Data Mahasiswa Baru)
 */
function handlePost(PDO $pdo): void {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        $input = $_POST;
    }

    $npm = trim($input['npm'] ?? '');
    $nama = trim($input['nama'] ?? '');
    $program_studi = trim($input['program_studi'] ?? '');
    $angkatan = (int)($input['angkatan'] ?? 0);
    $email = trim($input['email'] ?? '');

    if (
        empty($npm) ||
        empty($nama) ||
        empty($program_studi) ||
        empty($angkatan) ||
        empty($email)
    ) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Seluruh bidang isian wajib diisi.'
        ]);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO mahasiswa
             (npm, nama, program_studi, angkatan, email)
             VALUES (:npm, :nama, :prodi, :angkatan, :email)'
        );

        $stmt->execute([
            ':npm' => $npm,
            ':nama' => $nama,
            ':prodi' => $program_studi,
            ':angkatan' => $angkatan,
            ':email' => $email
        ]);

        $newId = (int)$pdo->lastInsertId();

        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Data mahasiswa berhasil ditambahkan.',
            'data' => [
                'id' => $newId,
                'npm' => $npm,
                'nama' => $nama,
                'program_studi' => $program_studi,
                'angkatan' => $angkatan,
                'email' => $email
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menambahkan data: ' . $e->getMessage()
        ]);
    }
}

/**
 * 3. Menangani HTTP PUT (Perbarui Data Mahasiswa)
 */
function handlePut(PDO $pdo, ?int $id): void {
    if (!$id || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ID mahasiswa wajib disertakan.'
        ]);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Data JSON tidak valid.'
        ]);
        return;
    }

    $nama = trim($input['nama'] ?? '');
    $program_studi = trim($input['program_studi'] ?? '');
    $angkatan = (int)($input['angkatan'] ?? 0);
    $email = trim($input['email'] ?? '');

    if (
        empty($nama) ||
        empty($program_studi) ||
        empty($angkatan) ||
        empty($email)
    ) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Seluruh bidang isian wajib diisi.'
        ]);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE mahasiswa
         SET nama = :nama,
             program_studi = :prodi,
             angkatan = :angkatan,
             email = :email
         WHERE id = :id'
    );

    $stmt->execute([
        ':id' => $id,
        ':nama' => $nama,
        ':prodi' => $program_studi,
        ':angkatan' => $angkatan,
        ':email' => $email
    ]);

    $check = $pdo->prepare(
        'SELECT id FROM mahasiswa WHERE id = :id'
    );
    $check->execute([':id' => $id]);

    if ($check->fetch()) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Data mahasiswa berhasil diperbarui.'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Mahasiswa dengan ID {$id} tidak ditemukan."
        ]);
    }
}

/**
 * 4. Menangani HTTP DELETE (Hapus Data Mahasiswa)
 */
function handleDelete(PDO $pdo, ?int $id): void {
    if (!$id || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ID mahasiswa wajib disertakan.'
        ]);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM mahasiswa WHERE id = :id'
        );

        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() > 0) {
            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Data mahasiswa berhasil dihapus.'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => "Mahasiswa dengan ID {$id} tidak ditemukan."
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menghapus data: ' . $e->getMessage()
        ]);
    }
}
