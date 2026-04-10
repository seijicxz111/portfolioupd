<?php
/**
 * Submissions Operations API
 * CIG Superadmin
 */

session_start();
require_once '../db/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

class SubmissionAPI {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function getSubmission($submission_id) {
        // Fix: JOIN users not organizations
        $query = "SELECT s.*, u.full_name,
                         COALESCE(u.org_name, o.org_name) as org_name
                  FROM submissions s
                  LEFT JOIN users u ON s.user_id = u.user_id
                  LEFT JOIN users o ON s.org_id = o.user_id
                  WHERE s.submission_id = ?";
        return $this->db->fetchRow($query, [$submission_id]);
    }

    public function getAllSubmissions($status = null) {
        $query = "SELECT s.*, u.full_name,
                         COALESCE(u.org_name, o.org_name) as org_name
                  FROM submissions s
                  LEFT JOIN users u ON s.user_id = u.user_id
                  LEFT JOIN users o ON s.org_id = o.user_id";
        if ($status) {
            $query .= " WHERE s.status = ?";
            return $this->db->fetchAll($query, [$status]);
        }
        $query .= " ORDER BY s.submitted_at DESC";
        return $this->db->fetchAll($query);
    }

    public function getUserSubmissions($user_id) {
        $query = "SELECT s.*, COALESCE(o.org_name, o.full_name) as org_name
                  FROM submissions s
                  LEFT JOIN users o ON s.org_id = o.user_id
                  WHERE s.user_id = ? ORDER BY s.submitted_at DESC";
        return $this->db->fetchAll($query, [$user_id]);
    }

    public function getOrgSubmissions($org_id) {
        $query = "SELECT s.*, u.full_name FROM submissions s
                  LEFT JOIN users u ON s.user_id = u.user_id
                  WHERE s.org_id = ? ORDER BY s.submitted_at DESC";
        return $this->db->fetchAll($query, [$org_id]);
    }

    public function createSubmission($data) {
        return $this->db->insert('submissions', $data);
    }

    public function updateSubmission($submission_id, $data) {
        unset($data['submission_id']);
        return $this->db->update('submissions', $data, 'submission_id = ?', [$submission_id]);
    }

    public function updateStatus($submission_id, $status) {
        return $this->db->update(
            'submissions',
            ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')],
            'submission_id = ?',
            [$submission_id]
        );
    }

    /**
     * SUPERADMIN: Forward to admin (pending → in_review) with optional remarks
     */
    public function forwardToAdmin($submission_id, $remarks = '') {
        $result = $this->updateStatus($submission_id, 'in_review');
        if (trim($remarks) !== '') {
            $this->saveRemarks($submission_id, $remarks);
        }
        $this->insertNotification($submission_id, 'forwarded', $remarks);
        return $result;
    }

    /**
     * SUPERADMIN: Reject submission
     */
    public function rejectSubmission($submission_id, $reason = '') {
        $data   = ['status' => 'rejected', 'updated_at' => date('Y-m-d H:i:s')];
        $result = $this->db->update('submissions', $data, 'submission_id = ?', [$submission_id]);
        if (trim($reason) !== '') {
            $this->saveRemarks($submission_id, $reason);
        }
        $this->insertNotification($submission_id, 'rejected', $reason);
        return $result;
    }

    private function saveRemarks($submission_id, $remarks) {
        if (trim($remarks) === '') return;
        try {
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $existing = $this->db->fetchRow(
                "SELECT review_id FROM reviews WHERE submission_id = ?",
                [$submission_id]
            );
            if ($existing) {
                $this->db->update('reviews',
                    ['feedback' => $remarks, 'reviewed_at' => date('Y-m-d H:i:s')],
                    'submission_id = ?', [$submission_id]
                );
            } else {
                $this->db->insert('reviews', [
                    'submission_id' => $submission_id,
                    'reviewer_id'   => $admin_id,
                    'feedback'      => $remarks,
                    'status'        => 'completed',
                    'reviewed_at'   => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (Exception $e) {
            error_log('Remarks save failed: ' . $e->getMessage());
        }
    }

    private function insertNotification($submission_id, $action, $remarks = '') {
        try {
            $sub = $this->db->fetchRow(
                "SELECT title, user_id FROM submissions WHERE submission_id = ?",
                [$submission_id]
            );
            if (!$sub) return;

            switch ($action) {
                case 'forwarded':
                    $notifTitle   = 'Submission Under Review';
                    $notifMessage = "Your submission \"{$sub['title']}\" is now under review by admin.";
                    if ($remarks) $notifMessage .= " Remarks: {$remarks}";
                    $notifType    = 'info';
                    break;
                case 'rejected':
                    $notifTitle   = 'Document Rejected';
                    $notifMessage = "Your submission \"{$sub['title']}\" has been rejected. Please review and resubmit.";
                    if ($remarks) $notifMessage .= " Reason: {$remarks}";
                    $notifType    = 'error';
                    break;
                default:
                    return;
            }

            $this->db->insert('notifications', [
                'user_id' => $sub['user_id'],
                'title'   => $notifTitle,
                'message' => $notifMessage,
                'type'    => $notifType,
            ]);
        } catch (Exception $e) {
            error_log('Notification insert failed: ' . $e->getMessage());
        }
    }

    public function deleteSubmission($submission_id) {
        return $this->db->delete('submissions', 'submission_id = ?', [$submission_id]);
    }


    /**
     * BULK FORWARD — multiple pending → in_review
     */
    public function bulkForward(array $ids) {
        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if (!$id) continue;
            try {
                $this->updateStatus($id, 'in_review');
                $this->insertNotification($id, 'forwarded', '');
                $count++;
            } catch (Exception $e) {
                error_log('Bulk forward failed for id=' . $id . ': ' . $e->getMessage());
            }
        }
        return $count;
    }

    /**
     * BULK REJECT — multiple submissions → rejected
     */
    public function bulkReject(array $ids) {
        $count = 0;
        foreach ($ids as $id) {
            $id = (int)$id;
            if (!$id) continue;
            try {
                $this->db->update('submissions', ['status' => 'rejected', 'updated_at' => date('Y-m-d H:i:s')], 'submission_id = ?', [$id]);
                $this->insertNotification($id, 'rejected', '');
                $count++;
            } catch (Exception $e) {
                error_log('Bulk reject failed for id=' . $id . ': ' . $e->getMessage());
            }
        }
        return $count;
    }
    public function getStatistics() {
        $query = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved'  THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected'  THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'in_review' THEN 1 ELSE 0 END) as in_review
                  FROM submissions";
        return $this->db->fetchRow($query);
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;
$api    = new SubmissionAPI();

try {
    if ($method === 'GET') {
        if ($action === 'get' && isset($_GET['id'])) {
            echo json_encode($api->getSubmission($_GET['id']));
        } elseif ($action === 'download' && isset($_GET['submission_id'])) {
            $sub = $api->getSubmission($_GET['submission_id']);
            if ($sub) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="submission_' . $_GET['submission_id'] . '.json"');
                echo json_encode($sub, JSON_PRETTY_PRINT);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Submission not found']);
            }
        } elseif ($action === 'getAll') {
            echo json_encode($api->getAllSubmissions($_GET['status'] ?? null));
        } elseif ($action === 'byUser' && isset($_GET['user_id'])) {
            echo json_encode($api->getUserSubmissions($_GET['user_id']));
        } elseif ($action === 'byOrg' && isset($_GET['org_id'])) {
            echo json_encode($api->getOrgSubmissions($_GET['org_id']));
        } elseif ($action === 'statistics') {
            echo json_encode($api->getStatistics());
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }

    } elseif ($method === 'POST' && !empty($_POST)) {
        $action        = $_POST['action']        ?? null;
        $submission_id = $_POST['submission_id'] ?? null;
        $remarks       = $_POST['reason']        ?? '';

        if ($action === 'forward' && $submission_id) {
            $api->forwardToAdmin($submission_id, $remarks);
            echo json_encode(['success' => true, 'message' => 'Submission forwarded to Admin for final review']);

        } elseif ($action === 'reject' && $submission_id) {
            $api->rejectSubmission($submission_id, $remarks);
            echo json_encode(['success' => true, 'message' => 'Submission rejected']);


        } elseif ($action === 'bulk_forward' && !empty($_POST['submission_ids'])) {
            $ids   = $_POST['submission_ids'];
            $count = $api->bulkForward($ids);
            echo json_encode(['success' => true, 'count' => $count, 'message' => "{$count} submissions forwarded to Admin"]);

        } elseif ($action === 'bulk_reject' && !empty($_POST['submission_ids'])) {
            $ids   = $_POST['submission_ids'];
            $count = $api->bulkReject($ids);
            echo json_encode(['success' => true, 'count' => $count, 'message' => "{$count} submissions rejected"]);

        } elseif ($action === 'approve' && $submission_id) {
            // Legacy — treat as forward for superadmin context
            $api->forwardToAdmin($submission_id, $remarks);
            echo json_encode(['success' => true, 'message' => 'Submission forwarded to Admin']);

        } elseif ($action === 'download' && $submission_id) {
            $sub = $api->getSubmission($submission_id);
            if ($sub) {
                echo json_encode($sub);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Submission not found']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action: ' . ($action ?? 'none')]);
        }

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (($data['action'] ?? '') === 'create') {
            echo json_encode(['success' => true, 'id' => $api->createSubmission($data)]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }

    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($action === 'update' && isset($_GET['id'])) {
            echo json_encode(['success' => true, 'updated' => $api->updateSubmission($_GET['id'], $data)]);
        } elseif ($action === 'updateStatus' && isset($_GET['id'])) {
            echo json_encode(['success' => true, 'updated' => $api->updateStatus($_GET['id'], $data['status'])]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }

    } elseif ($method === 'DELETE') {
        if ($action === 'delete' && isset($_GET['id'])) {
            echo json_encode(['success' => true, 'deleted' => $api->deleteSubmission($_GET['id'])]);
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

// ── BULK ACTIONS (added at bottom of POST handler) ────────────────────────
// These are injected into the existing POST block via a patch