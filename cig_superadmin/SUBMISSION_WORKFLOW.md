# CIG Submission Workflow Documentation

## Overview
The CIG Admin Dashboard now implements a streamlined three-stage submission workflow with organized status management. All pending and in-review documents flow through the **Submissions** page, approved documents move to the **Review & Approval** page organized by organization, and rejected documents are archived in the **Archive** page.

## Workflow Stages

### Stage 1: Submissions (📋 Pending & In Review)
**Location:** `/pages/submissions.php`

**Purpose:** Central hub for all new and in-review submissions awaiting initial approval.

**What shows here:**
- Only submissions with status: `pending` or `in_review`
- All newly submitted documents for initial review

**Actions available:**
- **Approve:** Moves submission to Review & Approval page for organization-specific review
- **Reject:** Moves submission to Archive page (marked as rejected)
- **Search:** Find submissions by title or organization name
- **View:** Open submission details

**Approval Flow:**
```
New Submission → Submissions Page (Pending) → Click Approve
                                              ↓
                          Moves to Review & Approval Page
```

**Rejection Flow:**
```
New Submission → Submissions Page (Pending) → Click Reject (with reason)
                                              ↓
                          Moves to Archive Page (Rejected)
```

---

### Stage 2: Review & Approval (✅ Approved Submissions)
**Location:** `/pages/review.php`

**Purpose:** Organize and manage approved submissions by organization for final review.

**What shows here:**
- Only submissions with status: `approved`
- Organized into organization folders (up to 49 organizations)

**Organization View:**
- Click on an organization folder to see all approved submissions for that org
- View submission details, review history, and submission metadata

**Submission Details:**
- Title, description, and organization affiliation
- Submitter information and submission date
- Review history (if any reviews have been added)
- Full submission metadata

**Actions available:**
- Navigate through organizations
- View submission details
- Review submission history
- Back navigation

---

### Stage 3: Archive (🗂️ Rejected Submissions)
**Location:** `/pages/archive.php`

**Purpose:** Store and manage all rejected submissions for historical reference and audit trail.

**What shows here:**
- Only submissions with status: `rejected`
- Complete rejection information with reasons if provided

**Rejection Information:**
- Rejection reason/feedback
- Original submission date
- Date of rejection
- Organization and submitter details

**Actions available:**
- View rejection details
- Review rejection reason
- Reference for audit purposes

---

## Submission Status Reference

| Status | Location | Description |
|--------|----------|-------------|
| `pending` | Submissions | Newly submitted, awaiting approval |
| `in_review` | Submissions | Currently under review |
| `approved` | Review & Approval | Approved and ready for organization-level review |
| `rejected` | Archive | Rejected submissions (permanent record) |

---

## Database Schema Considerations

The `submissions` table uses the following status values:
- `pending`: Initial submission status
- `in_review`: Submission is being reviewed
- `approved`: Submission has been approved
- `rejected`: Submission has been rejected
- `archived`: (Optional) For future use if needed

**Key submission fields:**
- `submission_id`: Unique identifier
- `user_id`: User who submitted
- `org_id`: Organization associated with submission
- `status`: Current workflow status
- `submitted_at`: Submission timestamp
- `updated_at`: Last status update

---

## Users & Permissions

### Admin/Reviewer Workflow:
1. **Check Submissions Page** - Review pending and in-review submissions
2. **Approve/Reject** - Make approval or rejection decisions
3. **Review Approved Submissions** - Navigate to Review & Approval to see organization-specific approved submissions
4. **Check Archive** - Reference rejected submissions as needed

---

## Key Features

### Search & Filter
- **Submissions Page:** Search by submission title or organization name
- Quick filtering within pending and in-review statuses

### Organization Management
- **Review & Approval Page:** Visual folder-based organization navigation
- Up to 49 organization folders supported
- Shows submission count per organization

### Audit Trail
- **Archive Page:** Complete record of all rejected submissions
- Rejection dates and reasons preserved
- Full submission metadata retained

---

## Process Flow Diagram

```
┌─────────────────────┐
│ New Submission      │
│ (pending status)    │
└──────────┬──────────┘
           │
        ┌──▼──────────────────┐
        │  SUBMISSIONS PAGE   │
        │  (Pending/In-Review)│
        └──┬──────────────┬───┘
           │              │
    ┌──────▼──────┐  ┌────▼────────┐
    │  APPROVE    │  │   REJECT    │
    │     ✅      │  │      ❌     │
    └──────┬──────┘  └────┬────────┘
           │              │
        ┌──▼──────────────┴──────┐
        │  Status Changed to     │
        │  'approved'/'rejected' │
        └──┬──────────────┬──────┘
           │              │
    ┌──────▼──────┐  ┌────▼──────────┐
    │ REVIEW &    │  │   ARCHIVE     │
    │ APPROVAL    │  │ (Rejected)    │
    │ (By Org)    │  │               │
    └─────────────┘  └───────────────┘
```

---

## API Integration

The workflow is powered by the **Submissions API** (`/api/submissions.php`):

### Key API Endpoints:

- **POST** - `/api/submissions.php`
  - `action=approve&submission_id={id}` - Approve submission
  - `action=reject&submission_id={id}&reason={reason}` - Reject submission

- **GET** - `/api/submissions.php`
  - `action=getAll&status={status}` - Get submissions by status
  - `action=statistics` - Get workflow statistics
  - `action=byOrg&org_id={id}` - Get submissions by organization

---

## Configuration

The workflow is automatic and requires no additional configuration:
- Status transitions happen via simple approval/rejection buttons
- Organization display defaults to first 49 organizations (configurable in code)
- All timestamps are automatically managed by the database

---

## Troubleshooting

### Submissions not appearing in Review & Approval?
- Verify submission status is set to `approved` in the database
- Check organization ID is valid and organization exists

### Rejected submissions not in Archive?
- Verify submission status is set to `rejected` in the database
- Check that rejection was processed through the API

### Search not working?
- Ensure organization and submission titles exist
- Try partial search terms
- Clear browser cache if recently updated

---

## Future Enhancements

Potential improvements to the workflow:
1. Add approval comments/notes
2. Multi-level approval workflows
3. Bulk approval/rejection operations
4. Email notifications on status changes
5. Advanced filtering and sorting
6. Export/download capabilities
7. Approval history with timestamps
8. User-level permission controls

