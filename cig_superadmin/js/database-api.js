/**
 * Database API Handler
 * CIG Admin Dashboard - JavaScript Database Connection
 * 
 * This file provides helper functions to communicate with the backend API
 * and handle database operations from the frontend.
 */

class DatabaseAPI {
    constructor() {
        this.baseUrl = '../api/';
        this.timeout = 10000;
    }

    /**
     * Make API Request
     */
    async request(endpoint, method = 'GET', data = null) {
        try {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                }
            };

            if (data && (method === 'POST' || method === 'PUT')) {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(this.baseUrl + endpoint, options);
            
            if (!response.ok) {
                throw new Error(`API Error: ${response.status} ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Database API Error:', error);
            throw error;
        }
    }

    // ==================
    // USER OPERATIONS
    // ==================

    async getUser(userId) {
        return this.request(`users.php?action=get&id=${userId}`);
    }

    async getAllUsers() {
        return this.request('users.php?action=getAll');
    }

    async createUser(userData) {
        return this.request('users.php?action=create', 'POST', userData);
    }

    async updateUser(userId, userData) {
        return this.request(`users.php?action=update&id=${userId}`, 'PUT', userData);
    }

    async deleteUser(userId) {
        return this.request(`users.php?action=delete&id=${userId}`, 'DELETE');
    }

    async authenticateUser(email, password) {
        return this.request('users.php?action=authenticate', 'POST', { email, password });
    }

    // ==================
    // SUBMISSION OPERATIONS
    // ==================

    async getSubmission(submissionId) {
        return this.request(`submissions.php?action=get&id=${submissionId}`);
    }

    async getAllSubmissions(status = null) {
        const endpoint = status ? `submissions.php?action=getAll&status=${status}` : 'submissions.php?action=getAll';
        return this.request(endpoint);
    }

    async getUserSubmissions(userId) {
        return this.request(`submissions.php?action=byUser&user_id=${userId}`);
    }

    async getOrgSubmissions(orgId) {
        return this.request(`submissions.php?action=byOrg&org_id=${orgId}`);
    }

    async createSubmission(submissionData) {
        return this.request('submissions.php?action=create', 'POST', submissionData);
    }

    async updateSubmission(submissionId, submissionData) {
        return this.request(`submissions.php?action=update&id=${submissionId}`, 'PUT', submissionData);
    }

    async updateSubmissionStatus(submissionId, status) {
        return this.request(`submissions.php?action=updateStatus&id=${submissionId}`, 'PUT', { status });
    }

    async deleteSubmission(submissionId) {
        return this.request(`submissions.php?action=delete&id=${submissionId}`, 'DELETE');
    }

    async getSubmissionStatistics() {
        return this.request('submissions.php?action=statistics');
    }

    // ==================
    // ORGANIZATION OPERATIONS
    // ==================

    async getOrganization(orgId) {
        return this.request(`organizations.php?action=get&id=${orgId}`);
    }

    async getAllOrganizations() {
        return this.request('organizations.php?action=getAll');
    }

    async getActiveOrganizations() {
        return this.request('organizations.php?action=getActive');
    }

    async createOrganization(orgData) {
        return this.request('organizations.php?action=create', 'POST', orgData);
    }

    async updateOrganization(orgId, orgData) {
        return this.request(`organizations.php?action=update&id=${orgId}`, 'PUT', orgData);
    }

    async deleteOrganization(orgId) {
        return this.request(`organizations.php?action=delete&id=${orgId}`, 'DELETE');
    }

    async getOrganizationStatistics(orgId) {
        return this.request(`organizations.php?action=statistics&id=${orgId}`);
    }
}

// Create global database API instance
const dbAPI = new DatabaseAPI();

/**
 * Usage Examples:
 * 
 * // Get all submissions
 * dbAPI.getAllSubmissions()
 *     .then(submissions => console.log(submissions))
 *     .catch(error => console.error(error));
 *
 * // Get submissions by status
 * dbAPI.getAllSubmissions('pending')
 *     .then(submissions => console.log(submissions))
 *     .catch(error => console.error(error));
 *
 * // Authenticate user
 * dbAPI.authenticateUser('user@email.com', 'password')
 *     .then(response => {
 *         if (response.success) {
 *             console.log('User logged in:', response.user);
 *             localStorage.setItem('user', JSON.stringify(response.user));
 *         }
 *     })
 *     .catch(error => console.error(error));
 *
 * // Create new submission
 * dbAPI.createSubmission({
 *     user_id: 1,
 *     org_id: 2,
 *     title: 'New Submission',
 *     description: 'Description here',
 *     file_name: 'document.pdf',
 *     submitted_by: 1
 * })
 *     .then(response => console.log('Created:', response))
 *     .catch(error => console.error(error));
 *
 * // Update submission status
 * dbAPI.updateSubmissionStatus(5, 'approved')
 *     .then(response => console.log('Updated:', response))
 *     .catch(error => console.error(error));
 *
 * // Get all organizations
 * dbAPI.getAllOrganizations()
 *     .then(orgs => console.log(orgs))
 *     .catch(error => console.error(error));
 */
