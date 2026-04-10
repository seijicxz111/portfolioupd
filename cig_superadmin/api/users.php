<?php
/**
 * User Operations API
 * CIG Admin Dashboard - User Management
 */

require_once '../db/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

class UserAPI {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Get User by ID
     */
    public function getUser($user_id) {
        $query = "SELECT user_id, username, email, full_name, role, status, created_at, last_login FROM users WHERE user_id = ?";
        return $this->db->fetchRow($query, [$user_id]);
    }

    /**
     * Get All Users
     */
    public function getAllUsers() {
        $query = "SELECT user_id, username, email, full_name, role, status, created_at, last_login FROM users ORDER BY created_at DESC";
        return $this->db->fetchAll($query);
    }

    /**
     * Create User
     */
    public function createUser($data) {
        $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        unset($data['password']);
        
        return $this->db->insert('users', $data);
    }

    /**
     * Update User
     */
    public function updateUser($user_id, $data) {
        unset($data['user_id']);
        return $this->db->update('users', $data, 'user_id = ?', [$user_id]);
    }

    /**
     * Delete User
     */
    public function deleteUser($user_id) {
        return $this->db->delete('users', 'user_id = ?', [$user_id]);
    }

    /**
     * Authenticate User
     */
    public function authenticate($email, $password) {
        $query = "SELECT user_id, username, email, full_name, role, password_hash FROM users WHERE email = ? AND status = 'active'";
        $user = $this->db->fetchRow($query, [$email]);

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            // Update last login
            $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'user_id = ?', [$user['user_id']]);
            return $user;
        }
        return false;
    }
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$api = new UserAPI();

try {
    if ($method === 'GET') {
        if ($action === 'get' && isset($_GET['id'])) {
            echo json_encode($api->getUser($_GET['id']));
        } elseif ($action === 'getAll') {
            echo json_encode($api->getAllUsers());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($action === 'create') {
            $id = $api->createUser($data);
            echo json_encode(['success' => true, 'id' => $id]);
        } elseif ($action === 'authenticate') {
            $user = $api->authenticate($data['email'], $data['password']);
            if ($user) {
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($action === 'update' && isset($_GET['id'])) {
            $rowCount = $api->updateUser($_GET['id'], $data);
            echo json_encode(['success' => true, 'updated' => $rowCount]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } elseif ($method === 'DELETE') {
        if ($action === 'delete' && isset($_GET['id'])) {
            $rowCount = $api->deleteUser($_GET['id']);
            echo json_encode(['success' => true, 'deleted' => $rowCount]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
