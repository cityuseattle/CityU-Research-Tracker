# User Manual — CityU Research Review Portal

> **Version:** 1.0.0  
> **Author:** Kiran Kumar Vejendla — [vejendlakirankumar@cityu.edu] and Jemell Garris - [] (mailto:vejendlakirankumar@cityu.edu)  
> **Institution:** City University of Seattle · School of Technology and Computing (STC)  
> **Last updated:** March 2026

---

## Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Student Guide](#student-guide)
4. [Reviewer Guide](#reviewer-guide)
5. [Coordinator Guide](#coordinator-guide)
6. [Administrator Guide](#administrator-guide)
7. [Submission Types & Review Stages](#submission-types--review-stages)
8. [Common Tasks Quick Reference](#common-tasks-quick-reference)
9. [Frequently Asked Questions](#frequently-asked-questions)

---

## Introduction

The **CityU Research Review Portal** is a centralised digital platform for submitting and tracking academic research through structured multi-stage peer review. It covers all major research output types at City University of Seattle:

| Submission Type | Who submits |
|-----------------|-------------|
| Doctoral Dissertation | PhD & research-doctoral candidates |
| Capstone Project | Graduate program students |
| Research Paper | Faculty & researchers |
| Journal Publication | Faculty & researchers |
| Grant Proposal | Faculty & researchers |

Every submission follows a type-specific workflow: it passes through a series of reviewer stages sequentially. Each stage must be approved before the submission advances. The portal sends email notifications when action is required.

---

## Getting Started

### Accessing the Portal

1. Open your browser and navigate to your institution's portal URL (e.g. `http://deployvm.cityu.edu`).
2. You will see the public homepage. Click **Login Now** or **Login to Submit Your Work**.
3. Enter your CityU credentials (WordPress username / email + password).
4. After login you are automatically redirected to your **personal dashboard**.

### Your Dashboard

When you log in you land on the dashboard appropriate for your role:

- **Students** → Submission dashboard with "New Submission" button and list of your own submissions
- **Reviewers** → Reviewer dashboard showing your assigned reviews
- **Coordinators / Admins** → Full management dashboard with all submissions

Your name, role, and a **Logout** link are visible in the navy top bar at all times.

---

## Student Guide

### Submitting New Research

1. From your dashboard click **+ New Submission** (top of page).
2. Choose a **Submission Type** from the dropdown:
   - Doctoral Dissertation (ARS)
   - Capstone Project (CAP)
   - Research Paper (ARS)
   - Journal Publication (PUB)
   - Grant Proposal (GRN)
3. Fill in all required fields:
   - **Title** — full title of your work
   - **Abstract** — 150-500 words describing your research
   - **Keywords** — 3-6 comma-separated terms
   - **Supporting Document** — upload a PDF, Word (.docx), or text file (max 64 MB)
4. Click **Submit**. The portal assigns a **reference ID** (e.g. `ARS-2026-007`) and notifies the first-stage reviewer.

> **Draft saving:** Fill in the form and click **Save Draft** to preserve your work without submitting.

### Tracking Your Submission

Each submission card on your dashboard shows:

| Field | Meaning |
|-------|---------|
| Reference ID | Unique identifier (e.g. `ARS-2026-007`) |
| Status badge | Current stage status — Pending / Under Review / Approved / Rejected |
| Current stage | Which review stage the work is at |
| Current reviewer | Name of the reviewer at the active stage |
| Due date | Expected completion date for the active stage |

Click **Details** on any card to open the full submission view with:
- Complete timeline of all stages
- Reviewer comments and feedback (once provided)
- Option to upload a revised document

### Responding to Reviewer Feedback

When a reviewer requests revisions:

1. Open the submission card and click **Details**.
2. Read the **Feedback** section carefully.
3. Prepare a revised document.
4. Click **Upload Revision** and attach the new file.
5. Add a **Response Note** explaining how you addressed each comment.
6. Click **Submit Revision**. The reviewer is notified automatically.

### Viewing the Timeline

Click the **Timeline** button on any submission to see a full chronological log of every stage decision, reviewer assignment, and system event.

### Date Range Filter

Use the **Today / This Week / This Month / This Year / All Time** pill bar at the top of your submission list to filter by date range. Submissions approaching their due date appear first; fully approved submissions appear last.

---

## Reviewer Guide

### Your Reviewer Dashboard

When you log in you see the **Reviewer Dashboard** with two sections:

- **Pending Reviews** — submissions assigned to you that require action
- **Completed Reviews** — submissions you have already reviewed

### Reviewing an Assignment

1. Click **Review** on any pending submission card.
2. The submission detail view opens showing:
   - Submitter information, title, abstract
   - Uploaded documents with an in-browser preview (PDF / Word)
   - Previous stage decisions (if any)
   - A structured **review form**
3. Read the submitted document using the built-in viewer (**Preview** button).
4. Complete the review form:
   - **Decision** — choose Approve, Request Revisions, or Reject
   - **Comments** — written feedback for the submitter (required for all decisions)
   - **Reviewer Attachment** — optionally upload a marked-up copy or supplementary file
5. Click **Submit Decision**. Your decision is recorded, the submitter is notified, and if approved, the submission automatically advances to the next stage.

> **Note:** You can save a draft of your review and return to it before clicking Submit.

### Conflict of Interest

If you have a conflict of interest with a submission, click **Declare Conflict** on the assignment card. The coordinator will be notified and will reassign the submission.

### Reviewer Rating

After a submission is fully resolved you may be asked to rate the quality of other stages' reviewers. Use the star-rating control on completed submissions to provide feedback to coordinators.

---

## Coordinator Guide

Coordinators manage the review workflow for submissions. They can view all submissions, assign reviewers, advance or skip stages, and configure deadlines.

### Coordinator Dashboard

The dashboard shows all submissions in a list with:
- Submission reference ID, title, type, submitter
- Current status and active stage
- Due date (colour-coded: red = overdue, amber = due soon, green = on track)
- Buttons: **Details** · **Timeline** · **📋 Log**

The **"✓ Workflow complete"** indicator replaces the stage/reviewer display once a submission has been fully approved or rejected.

### Assigning Reviewers

1. Open a submission and click **Details**.
2. Navigate to the **Assignment** tab.
3. For each review stage, available reviewers from the configured pool are suggested automatically based on expertise tags.
4. Select a reviewer from the dropdown and click **Assign**.
5. The reviewer receives an email notification.

You can use **Suggest Reviewers** to let the system recommend the best match from the reviewer pool, avoiding conflicts of interest.

### Advancing a Stage

Once a stage has the required number of approvals, you can advance the submission:

1. In the submission details, scroll to the stage panel.
2. Click **Advance to Next Stage**.
3. An audit log entry is created automatically.

### Skipping a Stage

In exceptional circumstances a stage may be skipped:

1. Open the submission details.
2. Under the stage to be skipped, click **Skip Stage**.
3. Enter a justification note (mandatory).
4. The stage is marked skipped and the submission advances.

### Setting / Adjusting Deadlines

1. Open a submission → click **Details** → **Deadlines** tab.
2. Enter a custom deadline date for any stage.
3. Click **Save Deadlines**.

### Audit Log

Every coordinator action is recorded automatically. Click **📋 Log** on any submission card to open the slide-in audit panel showing timestamped entries for:
- Reviewer assignments
- Stage advances / skips
- Feedback submissions
- Status changes

### Analytics Dashboard

From the main navigation, go to **Analytics** (visible to Coordinators and Admins). Available reports:

| Report | What it shows |
|--------|---------------|
| Workflow | Stage-by-stage completion rates and average time-per-stage |
| Performance | Submission throughput by type and period |
| Reviewer | Review completion rate, average turnaround, workload per reviewer |
| Overdue | All submissions past their due date with days overdue |
| Daily | New submissions and decisions per day |

---

## Administrator Guide

Administrators have all Coordinator permissions plus access to user management and system configuration.

### User Management

Go to **Admin → Users** from the portal dashboard.

#### Creating a User

1. Click **+ Add User**.
2. Enter display name, email address, and select a role.
3. Optionally fill in additional profile fields:
   - Degree, Department / Program, Expertise tags
   - Allowed submission types (for students)
   - Program IDs
4. Click **Create User**. A WordPress account is created and the user receives a password-setup email.

#### Editing / Deleting a User

- Click the edit (pencil) icon next to any user.
- Modify fields and click **Save**.
- Click the delete (trash) icon and confirm to remove the user and their reviewer pool entries.

### User Roles

| Role Slug | Display Name | Key Permissions |
|-----------|-------------|-----------------|
| `rrp_student` | Student | Submit own research, view own submissions |
| `rrp_reviewer` | Reviewer | View assigned submissions, submit decisions |
| `rrp_coordinator` | Coordinator | Manage all submissions, assign reviewers, view analytics |
| `rrp_admin` | Portal Admin | All of Coordinator + user management + full configuration |
| `administrator` | WP Admin | Full WordPress admin access |

### Configuration Panel

Go to **Admin → Configuration** from the portal dashboard.

#### Reviewer Pools

Each submission type has a reviewer pool. Configure:
- **Assignment mode** — `random` (pick randomly from the pool) or `round_robin` (distribute evenly)
- **Reviewer IDs** — WordPress user IDs of eligible reviewers

#### Stage Requirements

Each stage in each submission type can be configured with:
- **Required reviewer count** — number of approvals needed before advancing
- **Due days** — default number of days allowed per stage

#### Review Templates

Create or edit review question templates for each submission type. Template fields:
- **Question label** — text shown to the reviewer
- **Type** — text, rating, or yes/no
- **Required** — whether the field must be answered before submitting

#### Submission Types

Under **Admin → Submission Types** you can create new custom submission types or edit existing ones, defining:
- Type name and description
- Workflow stages (ordered list with stage names)
- Eligible user groups

### Conflict of Interest Management

Go to **Admin → Conflicts** to view all declared conflicts of interest and re-assign reviewers manually.

### Export Reports

From **Analytics**, click **Export** (top right of each report tab) to download a CSV of the reportdata for offline analysis.

---

## Submission Types & Review Stages

### Doctoral Dissertation (ARS)

| Stage | Description |
|-------|-------------|
| 1. Student Submits | Candidate uploads dissertation and supporting materials |
| 2. Chair Review | Dissertation chair reviews for quality; may request revisions |
| 3. Committee Review | All committee members must individually approve |
| 4. Program Director Approval | Final academic review against program requirements |
| 5. Dissertation Director Sign-Off | Institutional final approval |

### Capstone Project (CAP)

| Stage | Description |
|-------|-------------|
| 1. Student Submits | Student uploads completed capstone project |
| 2. Advisor Review | Faculty advisor reviews scope and quality |
| 3. Peer Review | Domain expert provides independent evaluation |
| 4. Program Director Approval | Confirms academic and program-level compliance |

### Research Paper (ARS)

| Stage | Description |
|-------|-------------|
| 1. Author Submits | Author uploads paper with abstract and metadata |
| 2. Desk Review (Coordinator) | Checks scope and formatting compliance |
| 3. Peer Review (×2) | Two independent peer reviewers must both approve |
| 4. Decision | Coordinator records final decision (Accept / Revise / Reject) |

### Journal Publication (PUB)

| Stage | Description |
|-------|-------------|
| 1. Author Submits | Author uploads manuscript and cover letter |
| 2. Editor Review | Editor assesses suitability for the journal |
| 3. Peer Review (×2) | Double-blind peer review |
| 4. Revision (if required) | Author addresses peer comments |
| 5. Final Decision | Editor issues Accept / Reject |

### Grant Proposal (GRN)

| Stage | Description |
|-------|-------------|
| 1. Applicant Submits | Applicant uploads proposal, budget, and CVs |
| 2. Internal Review | Internal advisor reviews institutional alignment |
| 3. Expert Panel Review | External reviewers score and comment |
| 4. Budget Review | Finance team reviews budget justification |
| 5. Dean's Approval | Final institutional sign-off |

---

## Common Tasks Quick Reference

| Task | Role | Steps |
|------|------|-------|
| New submission | Student | Dashboard → + New Submission → fill form → Submit |
| Upload revision | Student | Dashboard → Details → Upload Revision |
| View feedback | Student | Dashboard → Details → Feedback section |
| Submit review decision | Reviewer | Dashboard → Review → complete form → Submit Decision |
| Declare conflict | Reviewer | Dashboard → Declare Conflict (on assignment card) |
| Assign reviewer | Coordinator | Details → Assignment tab → select → Assign |
| Advance stage | Coordinator | Details → stage panel → Advance to Next Stage |
| Skip stage | Coordinator | Details → stage panel → Skip Stage → enter justification |
| View audit log | Coordinator/Admin | Dashboard list → 📋 Log (on submission card) |
| Create user | Admin | Admin → Users → + Add User |
| Edit configuration | Admin | Admin → Configuration → edit and save |
| Export analytics | Admin | Analytics → desired tab → Export |

---

## Frequently Asked Questions

**Q: I submitted my research but cannot see it in my dashboard.**  
A: Log out and log back in. If the submission still does not appear, contact your coordinator — the submission may be pending activation of your student account.

**Q: My uploaded file is too large.**  
A: The maximum file size is 64 MB. Compress images, remove draft comments from Word files, or use PDF compression tools to reduce file size before uploading.

**Q: I received an email asking me to review a submission, but I can't see it in the portal.**  
A: Ensure you are logged in with the same email address that received the notification. If the problem persists, contact the coordinator who may need to re-assign.

**Q: I need to revise my submission but the "Upload Revision" button is greyed out.**  
A: Revisions can only be uploaded when the submission is in a "Revisions Requested" status. If your submission is still under initial review, wait for the reviewer's response.

**Q: I made a mistake in my submission details — can I edit them?**  
A: Once submitted, core metadata (title, abstract) cannot be edited by students. Contact your coordinator to make corrections.

**Q: The portal is not loading / shows a blank screen.**  
A: Try a hard reload (`Ctrl + Shift + R`). Make sure you access the portal with the exact URL given by your institution — do not type `localhost` directly. If problems persist, contact your system administrator.

**Q: How do I know when my submission has been approved?**  
A: You will receive an automatic email notification when the final stage is approved. The submission status badge on your dashboard will also change to **Approved** (green).

**Q: Can I submit multiple research items at the same time?**  
A: Yes. There is no limit on the number of active submissions per user.

---

*For technical issues, contact: [vejendlakirankumar@cityu.edu](mailto:vejendlakirankumar@cityu.edu)*  
*For academic or policy questions, contact your program coordinator or the School of Technology and Computing.*
