<?php
/**
 * Organizations Operations API
 * CIG Admin Dashboard - Organization Management
 */

require_once '../db/connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

class OrganizationAPI {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Get Organization by ID
     */
    public function getOrganization($org_id) {
        $query = "SELECT o.*, u.full_name as created_by_name FROM organizations o 
                  LEFT JOIN users u ON o.created_by = u.user_id 
                  WHERE o.org_id = ?";
        return $this->db->fetchRow($query, [$org_id]);
    }

    /**
     * Get All Organizations
     */
    public function getAllOrganizations() {
        $query = "SELECT o.*, u.full_name as created_by_name FROM organizations o 
                  LEFT JOIN users u ON o.created_by = u.user_id 
                  ORDER BY o.org_name ASC";
        return $this->db->fetchAll($query);
    }

    /**
     * Get Active Organizations
     */
    public function getActiveOrganizations() {
        $query = "SELECT * FROM organizations WHERE status = 'active' ORDER BY org_name ASC";
        return $this->db->fetchAll($query);
    }

    /**
     * Create Organization
     */
    public function createOrganization($data) {
        return $this->db->insert('organizations', $data);
    }

    /**
     * Update Organization
     */
    public function updateOrganization($org_id, $data) {
        unset($data['org_id']);
        return $this->db->update('organizations', $data, 'org_id = ?', [$org_id]);
    }

    /**
     * Delete Organization
     */
    public function deleteOrganization($org_id) {
        return $this->db->delete('organizations', 'org_id = ?', [$org_id]);
    }

    /**
     * Get Organization Statistics
     */
    public function getStatistics($org_id) {
        $query = "SELECT 
                    (SELECT COUNT(*) FROM organizations WHERE org_id = ?) as org_count,
                    (SELECT COUNT(*) FROM submissions WHERE org_id = ?) as submission_count,
                    (SELECT COUNT(*) FROM submissions WHERE org_id = ? AND status = 'approved') as approved_count,
                    (SELECT COUNT(*) FROM submissions WHERE org_id = ? AND status = 'pending') as pending_count";
        
        return $this->db->fetchRow($query, [$org_id, $org_id, $org_id, $org_id]);
    }
}

// Handle requests
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$api = new OrganizationAPI();

try {
    if ($method === 'GET') {
        if ($action === 'get' && isset($_GET['id'])) {
            echo json_encode($api->getOrganization($_GET['id']));
        } elseif ($action === 'getAll') {
            echo json_encode($api->getAllOrganizations());
        } elseif ($action === 'getActive') {
            echo json_encode($api->getActiveOrganizations());
        } elseif ($action === 'statistics' && isset($_GET['id'])) {
            echo json_encode($api->getStatistics($_GET['id']));
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($action === 'create') {
            $id = $api->createOrganization($data);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($action === 'update' && isset($_GET['id'])) {
            $rowCount = $api->updateOrganization($_GET['id'], $data);
            echo json_encode(['success' => true, 'updated' => $rowCount]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } elseif ($method === 'DELETE') {
        if ($action === 'delete' && isset($_GET['id'])) {
            $rowCount = $api->deleteOrganization($_GET['id']);
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
