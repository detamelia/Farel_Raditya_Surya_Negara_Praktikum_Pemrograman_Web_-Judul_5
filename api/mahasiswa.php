
<?php
/**
 * Praktikum 5: Endpoint REST API Mahasiswa Berbasis MySQL & PDO
 * File: api/mahasiswa.php
 * Mendukung HTTP GET, POST, PUT, DELETE dengan respons JSON.
 */

// Header respons API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Tangani HTTP OPTIONS Pre-flight Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Memanggil konfigurasi database
require_once __DIR__ . '/../config/database.php';

// Inisialisasi koneksi database
try {
    $db = new Database();
    $pdo = $db->getConnection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal terhubung ke database.',
        'data' => null
    ]);
    exit;
}

// Routing berdasarkan HTTP Method
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

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
        header('Allow: GET, POST, PUT, DELETE, OPTIONS');
        echo json_encode([
            'status' => 'error',
            'message' => "Metode HTTP {$method} tidak diizinkan.",
            'data' => null
        ]);
        break;
}

/**
 * 1. Menangani HTTP GET
 * Mengambil semua data atau data berdasarkan ID.
 */
function handleGet(PDO $pdo, ?int $id): void
{
    if ($id !== null && $id > 0) {
        // Mengambil mahasiswa berdasarkan ID
        $stmt = $pdo->prepare(
            'SELECT id, npm, nama, program_studi, angkatan, email, created_at
             FROM mahasiswa
             WHERE id = :id
             LIMIT 1'
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

        return;
    }

    // Fitur pencarian mahasiswa
    $search = trim($_GET['search'] ?? '');

    if ($search !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, npm, nama, program_studi, angkatan, email, created_at
             FROM mahasiswa
             WHERE npm LIKE :kw1
                OR nama LIKE :kw2
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
        // Mengambil seluruh data mahasiswa
        $stmt = $pdo->query(
            'SELECT id, npm, nama, program_studi, angkatan, email, created_at
             FROM mahasiswa
             ORDER BY id DESC'
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

/**
 * 2. Menangani HTTP POST
 * Menambahkan data mahasiswa baru.
 */
function handlePost(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        $input = $_POST;
    }

    $npm = trim($input['npm'] ?? '');
    $nama = trim($input['nama'] ?? '');
    $program_studi = trim($input['program_studi'] ?? '');
    $angkatan = (int) ($input['angkatan'] ?? 0);
    $email = trim($input['email'] ?? '');

    if (
        $npm === '' ||
        $nama === '' ||
        $program_studi === '' ||
        $angkatan <= 0 ||
        $email === ''
    ) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Seluruh bidang isian wajib diisi.'
        ]);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Format email tidak valid.'
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

        $newId = (int) $pdo->lastInsertId();

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
            'message' => 'Gagal menambahkan data. Periksa NPM dan data yang dikirim.'
        ]);
    }
}

/**
 * 3. Menangani HTTP PUT
 * Memperbarui data mahasiswa berdasarkan ID.
 */
function handlePut(PDO $pdo, ?int $id): void
{
    if ($id === null || $id <= 0) {
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
    $angkatan = (int) ($input['angkatan'] ?? 0);
    $email = trim($input['email'] ?? '');

    if (
        $nama === '' ||
        $program_studi === '' ||
        $angkatan <= 0 ||
        $email === ''
    ) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Seluruh bidang isian wajib diisi.'
        ]);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Format email tidak valid.'
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

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Data mahasiswa berhasil diperbarui.'
        ]);
        return;
    }

    // Bedakan data tidak ditemukan dan data tidak berubah
    $check = $pdo->prepare(
        'SELECT id FROM mahasiswa WHERE id = :id'
    );
    $check->execute([':id' => $id]);

    if ($check->fetch()) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Data mahasiswa tetap sama, tidak ada perubahan.'
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
 * 4. Menangani HTTP DELETE
 * Menghapus data mahasiswa berdasarkan ID.
 */
function handleDelete(PDO $pdo, ?int $id): void
{
    if ($id === null || $id <= 0) {
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
            'message' => 'Gagal menghapus data mahasiswa.'
        ]);
    }
}
