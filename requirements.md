# Research Review Portal — Requirements & Implementation Status

> **Last updated:** March 17, 2026 — JS syntax error (`renderTimeline` missing function declaration) fixed; portal loading restored  
> **Last validated:** March 17, 2026 — `node --check` exit 0; portal confirmed loading for all roles  
> **Environment:** Azure VM (`rcgapimtest.eastus2.cloudapp.azure.com`) — HTTPS active  
> **Plugin path:** `/mnt/c/Development/CityU-Research-Tracker`  
> **Health check:** `GET /wp-json/research-portal/v1/health` → `{"ok":true}`

---

## Legend
- ✅ **Implemented & deployed**  
- 🔄 **Partially implemented**  
- ❌ **Not yet implemented**

---

## 1. User Management & Authentication

| # | Requirement | Status | Notes |
|---|---|---|---|
| 1.1 | Student / Researcher role | ✅ | `rrp_student` WP role |
| 1.2 | Reviewer role | ✅ | `rrp_reviewer` WP role |
| 1.3 | Administrator role | ✅ | `rrp_admin` WP role |
| 1.4 | Coordinator role | ✅ | `rrp_coordinator` WP role |
| 1.5 | Faculty role | ✅ | `rrp_faculty` WP role |
| 1.6 | Role-based access control (RBAC) | ✅ | Capability system across all REST endpoints |
| 1.7 | WordPress login integration | ✅ | Nonce-authenticated REST API |
| 1.8 | SSO / Microsoft Entra ID | ✅ | Entra OAuth2 fully implemented; login choice UI (Microsoft button + WP form); inactive until enabled in Portal Settings |
| 1.9 | Session timeout policies | ✅ | Configurable idle timeout (default 60 min); JS warning at 5 min remaining; WP auth cookie lifetime extended to match |
| 1.10 | Add / edit portal users (admin UI) | ✅ | Full CRUD in coordinator dashboard |
| 1.11 | User department field | ✅ | `rrp_department` user meta |
| 1.12 | Department management (config) | ✅ | Departments list in `config.json` + admin UI |
| 1.13 | Reviewer expertise + submission type mapping | ✅ | Stored in `reviewers.json` and user meta |
| 1.14 | Password reset (admin) | ✅ | `POST /portal-users/{id}/reset-password` |
| 1.15 | Reviewer onboarding wizard (student self-assign reviewers by stage) | ✅ | Step-3 of student onboarding form |

---

## 2. Document Submission System

| # | Requirement | Status | Notes |
|---|---|---|---|
| 2.1 | Submission form with validation | ✅ | Client + server-side validation |
| 2.2 | Draft saving | ✅ | `status: Draft` supported |
| 2.3 | Submission preview | ✅ | `GET /submissions/{id}/preview` |
| 2.4 | Confirmation email on submit | ✅ | `wp_mail()` on non-draft submit |
| 2.5 | Anonymous process documentation | ✅ | Public pages per submission type |
| 2.6 | Conference paper submissions | ✅ | ARS-YYYY-NNN IDs |
| 2.7 | Publication submissions | ✅ | PUB-YYYY-NNN IDs |
| 2.8 | Student project submissions | ✅ | PROJ-YYYY-NNN IDs |
| 2.9 | Grant proposal submissions | ✅ | GRN-YYYY-NNN IDs |
| 2.10 | Automated ID generation | ✅ | Year-scoped with zero-padding |
| 2.11 | Keywords & research area metadata | ✅ | Stored per submission |
| 2.12 | File upload (multiple files, max 5, 2 MB each) | ✅ | `POST /submissions/{id}/attachments` |
| 2.13 | File type restriction (PDF + DOCX only) | ✅ | MIME check via `finfo_file()` + extension whitelist |
| 2.14 | Virus scanning on upload (ClamAV) | ✅ | ClamAV 1.4.3 installed; `clamscan` called on every upload |
| 2.15 | Document viewer (inline PDF / DOCX) | ✅ | PDF via iframe; DOCX via mammoth.js → HTML |
| 2.16 | Reviewer annotated file upload | ✅ | Reviewers can upload annotated copy from decision form; tagged `uploadedByReviewer` |
| 2.17 | Version control / revision tracking | ✅ | `revisionCount` incremented; stage decisions cleared on revision; attachments tagged by revision round; revision history visible via audit log |
| 2.18 | Submission deadlines per stage | ✅ | `GET /submissions/{id}/deadlines` |
| 2.19 | Submission withdrawal | ✅ | `PATCH` with `status: Withdrawn` |

---

## 3. Multi-Stage Review Workflow

| # | Requirement | Status | Notes |
|---|---|---|---|
| 3.1 | Predefined stages per submission type | ✅ | Conference (6), Publication (6), Student (6), Grant (7) |
| 3.2 | Automatic progression at full stage approval | ✅ | `derive_submission_status()` + next-stage email |
| 3.3 | Revision handling (send back to submitter) | ✅ | `Needs Revision` → `Revision Required` status |
| 3.4 | Parallel review (multiple reviewers per stage) | ✅ | `requiredCount` per stage |
| 3.5 | Reviewer assignment — random pool | ✅ | `assignmentMode: random` |
| 3.6 | Reviewer assignment — round-robin pool | ✅ | `assignmentMode: round_robin` |
| 3.7 | Reviewer assignment — expertise-based | ✅ | `GET /config/suggest-reviewers` matches `rrp_submission_types` expertise usermeta; shown in assignment panel |
| 3.8 | Reviewer assignment — workload-based | ✅ | `GET /analytics/workload` exposes active/completed counts per reviewer; workload column visible in assignment UI |
| 3.9 | Stage skipping (coordinator / admin) | ✅ | `POST /submissions/{id}/skip-stage` |
| 3.10 | Revision submitted → workflow reset | ✅ | All stage decisions cleared, round counter incremented |
| 3.11 | Overdue submission detection | ✅ | `GET /analytics/overdue` |
| 3.12 | Review templates per submission type | ✅ | `GET/PUT /config/review-templates` |
| 3.13 | Conflict of interest management | ✅ | Reviewer declares COI via dashboard; coordinator/admin view COI Declarations panel in sidebar; `GET /conflicts` + `POST /conflicts` endpoints |
| 3.14 | Escalation procedures for overdue | ✅ | Daily WP-Cron `rrp_escalation_check`; emails all coordinators/admins with overdue submission list |
| 3.15 | Auto-assign reviewers on submission (pool) | ✅ | `auto_assign_submission()` on submit if pool configured |
| 3.16 | Student personal reviewer defaults (by stage) | ✅ | `rrp_default_stage_reviewers` user meta applied at submit time |

---

## 4. Reviewer Management

| # | Requirement | Status | Notes |
|---|---|---|---|
| 4.1 | Reviewer dashboard with pending reviews | ✅ | Filtered by assigned reviewer email |
| 4.2 | Review history | ✅ | `GET /analytics/reviewer` returns full `reviewHistory`; per-submission decisions visible in submission detail timeline |
| 4.3 | Streamlined decision interface (Approve / Needs Revision / Reject) | ✅ | `Record Your Decision` form |
| 4.4 | Rich text feedback | ✅ | Quill.js editor (bold, italic, underline, bullet/numbered lists, blockquote); loaded from CDN on demand; feedback stored as HTML; rendered with allowlist-based sanitizer (XSS-safe) in all display views |
| 4.5 | Review criteria templates | ✅ | Stored in config; shown in decision form |
| 4.6 | Reviewer workload tracking | ✅ | `GET /analytics/workload` |
| 4.7 | Due date display in reviewer list | ✅ | Per-reviewer deadline from `assignedReviewers[].deadline` |
| 4.8 | Reviewer annotation upload (annotated document) | ✅ | `#rrp-reviewer-file` input in decision form; amber badge in doc list |
| 4.9 | Calendar integration (Google / Outlook) | ❌ | Not implemented |
| 4.10 | Scoring / rating system | ✅ | `POST /reviews/rate` with weighted `ratings` array + comments; `reviewScores` stored per reviewer per submission; rating form in reviewer decision panel |
| 4.11 | Collaborative multi-reviewer features | 🔄 | Multiple reviewers per stage exist; no real-time collaboration |

---

## 5. Status Tracking & Notifications

| # | Requirement | Status | Notes |
|---|---|---|---|
| 5.1 | Real-time status dashboard (submitter) | ✅ | Student dashboard with status badges |
| 5.2 | Timeline view of review stages | ✅ | `GET /submissions/{id}/timeline` rendered in detail view |
| 5.3 | Audit / activity log per submission | ✅ | `GET /submissions/{id}/audit-log`; 📋 Log button in all panels; 10 event types tracked |
| 5.4 | Email notifications (status changes, decisions) | ✅ | `wp_mail()` for confirmation + stage reviewer alerts; SMTP configurable via Portal Settings (host, port, encryption, auth, from address) |
| 5.5 | In-app notification centre | ✅ | `GET /notifications` — pending review / revision alerts |
| 5.6 | Configurable notification preferences | ❌ | Not implemented — Sprint 7 |
| 5.7 | Automatic deadline reminder emails | ✅ | Daily WP-Cron `rrp_deadline_reminders`; emails reviewer 3 days before stage deadline |
| 5.8 | Escalation notifications | ✅ | Daily WP-Cron `rrp_escalation_check`; emails coordinator list of overdue submissions |
| 5.9 | Estimated completion dates | ✅ | Student dashboard shows estimated completion date per in-progress submission based on remaining stages × avg days |

---

## 6. Administrative Features

| # | Requirement | Status | Notes |
|---|---|---|---|
| 6.1 | Coordinator dashboard with all submissions | ✅ | Filter by All / Unassigned / In Review / Approved |
| 6.2 | Submission filtering & search | ✅ | Tab-based status/type filter + free-text search box (title, ID, submitter name) in coordinator and student dashboards |
| 6.3 | Reviewer assignment UI (per stage) | ✅ | Assignment panel with pool suggest |
| 6.4 | Bulk reviewer assignment from pool | ✅ | `POST /config/apply-pool-to-submissions` |
| 6.5 | System configuration UI (stages, pools, programs, departments) | ✅ | Coordinator → Program Management card |
| 6.6 | Review time analytics | ✅ | `GET /analytics/workflow` + `performance` |
| 6.7 | Reviewer workload reports | ✅ | `GET /analytics/reviewer` + `workload` |
| 6.8 | Submission trend analysis | ✅ | `GET /analytics/daily` (90-day rolling) |
| 6.9 | Export CSV / XLSX | ✅ | `GET /reports/export` + client-side XLSX download |
| 6.10 | Audit trail (per submission) | ✅ | See §5.3 |
| 6.11 | Bulk status updates | N/A | Removed — not required |
| 6.12 | Overdue submissions view | ✅ | Coordinator → Overdue tab |
| 6.13 | Conflict of interest tracking | ✅ | COI Declarations panel in coordinator/admin sidebar; full list with submission ID, reviewer, and reason |

---

## 7. Deadline Management

| # | Requirement | Status | Notes |
|---|---|---|---|
| 7.1 | Configurable review deadlines per stage | ✅ | `stageDueDays` per submission type editable per stage in Submission Types editor (inline day-count field per stage row); saved via `PUT /config` |
| 7.2 | Automatic deadline calculation from submission date | ✅ | Days-per-stage from config |
| 7.3 | Grace period / escalation | ✅ | Configurable grace period (days) in Portal Settings → Deadline Options; `get_grace_period_seconds()` applied in overdue detection and escalation emails |
| 7.4 | Weekend / holiday consideration | ✅ | Skip Weekends toggle + Public Holidays list in Portal Settings → Deadline Options; `calculate_stage_deadline()` counts only working days; extension approvals also skip weekends/holidays |
| 7.5 | Extension requests | ✅ | Reviewer can submit extension request (stage, reason, extra days) from decision form; coordinators see all requests under Extension Requests panel with Approve/Deny; approved extensions set `extensionDeadline`; email notifications to both parties |
| 7.6 | Calendar view (deadline calendar) | ❌ | Not implemented — Sprint 7 |
| 7.7 | Personal calendar sync (Google / Outlook) | ❌ | Not implemented — Sprint 7 |

---

## 8. Technical & Security

| # | Requirement | Status | Notes |
|---|---|---|---|
| 8.1 | WordPress 5.0+ compatibility | ✅ | Tested on current WP |
| 8.2 | PHP 7.4+ | ✅ | Uses PHP 8.x on VM |
| 8.3 | Responsive / mobile design | ✅ | CSS grid + media queries |
| 8.4 | Input validation & sanitisation (OWASP) | ✅ | All REST inputs sanitised; `escapeHtml()` in JS |
| 8.5 | File virus scanning (ClamAV) | ✅ | See §2.14 |
| 8.6 | Real MIME-type verification on upload | ✅ | `finfo_file()` check |
| 8.7 | REST API nonce authentication | ✅ | `wp_create_nonce('wp_rest')` |
| 8.8 | Role / capability checks on every endpoint | ✅ | `permission_callback` on all routes |
| 8.9 | SSL/TLS | ✅ | Let's Encrypt certificate active on Azure VM; all traffic served over HTTPS |
| 8.10 | Data backup / recovery | ❌ | JSON files only; no automated backup |
| 8.11 | REST API for external integrations | ✅ | Full REST surface exposed |
| 8.12 | mammoth.js DOCX inline viewer | ✅ | Enqueued as WP script dependency |
| 8.13 | Configurable SMTP for outgoing email | ✅ | Admin-configurable in Portal Settings → Email / SMTP fieldset; host, port, SSL/STARTTLS/None, auth credentials, from name + address; password encrypted AES-256-GCM; `POST /settings/test-email` verifies settings; `phpmailer_init` hook configures PHPMailer on every `wp_mail()` call |

---

## 9. Pending / Future Work (priority order)

1. ~~**Microsoft Entra ID SSO**~~ ✅ Implemented
2. ~~**Automated deadline reminder emails**~~ ✅ Implemented
3. ~~**Escalation notifications**~~ ✅ Implemented
4. ~~**Free-text search** across submission title / author / ID~~ ✅ Implemented
5. ~~**Bulk status updates**~~ — Removed (not required)
6. ~~**Conflict of interest declaration**~~ ✅ Implemented (reviewer + coordinator view)
7. ~~**Expertise-based / workload-based reviewer auto-assignment**~~ ✅ Implemented
8. ~~**Rich text feedback editor**~~ ✅ Implemented (Quill.js, stored as HTML, sanitized on display)
9. ~~**Deadline edit UI**~~ ✅ Implemented in Portal Settings
10. **Calendar view & personal calendar sync** — Sprint 7
11. ~~**Scoring / rating system**~~ ✅ Implemented
12. ~~**Extension request workflow** for reviewers~~ ✅ Implemented (7.5)
13. ~~**HTTPS configuration**~~ ✅ Active (Let's Encrypt)
14. **Automated JSON backup** (WP-Cron → Azure Blob) — Sprint 8
15. **Configurable notification preferences** per user — Sprint 7
16. ~~**Configurable SMTP / outgoing email**~~ ✅ Implemented (Portal Settings → Email / SMTP; test-email endpoint; AES-256-GCM password encryption)

---

## Deployment Reference

| Item | Value |
|---|---|
| VM | `rcgapimtest.eastus2.cloudapp.azure.com` |
| WP admin | `admin` / `admin123` (default fallback — disabled when real rrp_admin users exist) |
| VM user | `azureadmin` / `Microsoft12345` |
| Plugin path (VM) | `/var/www/html/wp-content/plugins/research-review-portal/` |
| Local path | `d:\Development\CityU-Research-Tracker` |
| Health endpoint | `GET /wp-json/rrp/v1/health` |
| ClamAV | `/usr/bin/clamscan` v1.4.3, defs 27940 |
| JS syntax check | `node --check assets/portal.js` — exit 0 (verified March 17, 2026) |


### 10. Implementation Priorities

#### Phase 1: Core Enhancements (High Priority)
1. Enhanced authentication and user management
2. Improved reviewer dashboard with due dates
3. Enhanced student status tracking
4. Basic notification system
5. Administrative oversight dashboard

#### Phase 2: Workflow Automation (Medium Priority)
1. Automatic workflow progression
2. Advanced deadline management
3. Comprehensive notification system
4. Reporting and analytics
5. Calendar integration

#### Phase 3: Advanced Features (Low Priority)
1. SSO integration
2. Advanced analytics and reporting
3. Mobile application
4. API expansions for third-party integrations
5. Advanced collaboration features

### 11. Success Criteria

- **100% of submissions** can be tracked from submission to final decision
- **All reviewers** have access to user-friendly dashboards with clear deadlines
- **All students** can track their submission status in real-time
- **Administrators** have complete oversight of all submissions and system performance
- **Average review time** is reduced by 30% through automation and better workflow management
- **User satisfaction** rating of 4.5/5 or higher from all user groups

### 12. Constraints & Assumptions

#### Technical Constraints
- Must work within WordPress plugin architecture
- Must maintain compatibility with existing data structure
- Must support current JSON-based data storage (with option for database migration)

#### Business Assumptions
- University email system available for notifications
- Users have basic computer literacy
- Reviewers are available for timely reviews
- Administrative support for system maintenance and user training

### 13. Future Enhancements

- **Machine learning** for automatic reviewer assignment based on expertise
- **Advanced analytics** with predictive modeling for review times
- **Integration** with research databases and academic systems
- **Blockchain** integration for document verification and audit trails
- **AI-powered** initial document screening and quality checks

---

## Implementation Plan

### Phase 1: Foundation & Core Features (Weeks 1-6)

#### Sprint 1: Infrastructure & Authentication (Weeks 1-2)
**Tasks:**
- [ ] **Code Audit & Documentation** (3 days)
  - Review existing WordPress plugin architecture
  - Document current API endpoints and data flow
  - Identify technical debt and improvement areas
  - Create development environment setup guide

- [ ] **Enhanced User Management** (5 days)
  - Extend WordPress user roles with custom capabilities
  - Implement role-based access control (RBAC)
  - Create user profile extensions for department/expertise
  - Add bulk user import functionality

- [ ] **Anonymous Process Documentation** (4 days)
  - Create comprehensive process explanation pages for each submission type
  - Build interactive workflow visualization
  - Add estimated timeline information for each stage
  - Implement public access without authentication requirements

**Deliverables:**
- Updated plugin with enhanced authentication
- User management documentation
- SSO configuration guide
- Development environment ready

#### Sprint 2: Enhanced Submission System (Weeks 3-4)
**Tasks:**
- [ ] **Improved Submission UI** (6 days)
  - Redesign submission form with modern UI/UX
  - Add real-time field validation
  - Implement draft saving functionality
  - Create submission preview feature

- [ ] **File Management Enhancement** (4 days)
  - Improve file upload with progress indicators
  - Add file type validation and virus scanning
  - Implement file versioning system
  - Create secure file access controls

**Deliverables:**
- Enhanced submission interface
- Improved file handling system
- User testing feedback incorporated

#### Sprint 3: Basic Dashboard & Status Tracking (Weeks 5-6)
**Tasks:**
- [ ] **Student Dashboard** (5 days)
  - Create submission status tracking interface
  - Build timeline view for review progress
  - Add notification center
  - Implement search and filter functionality

- [ ] **Basic Reviewer Dashboard** (5 days)
  - List pending reviews with due dates
  - Create simple review decision interface
  - Add basic feedback submission form
  - Implement reviewer notification preferences

**Deliverables:**
- Student dashboard with status tracking
- Basic reviewer interface
- Initial notification system

### Phase 2: Advanced Workflow & Features (Weeks 7-12)

#### Sprint 4: Workflow Automation (Weeks 7-8)
**Tasks:**
- [ ] **Automated Stage Progression** (6 days)
  - Implement automatic workflow advancement logic
  - Create revision request handling
  - Build reviewer assignment algorithms
  - Add stage skipping for administrators

- [ ] **Deadline Management** (4 days)
  - Implement configurable deadline calculation
  - Create escalation procedures for overdue items
  - Add grace period and extension handling
  - Build deadline notification system

**Deliverables:**
- Fully automated workflow engine
- Deadline management system
- Escalation procedures implemented

**Sprint 4 Status**: ✅ Completed (implementation of workflow analytics, performance metrics, CSV/XLSX reports, scheduled email summaries)

#### Sprint 5: Advanced Reviewer Features (Weeks 9-10)
**Tasks:**
- [x] **Enhanced Reviewer Dashboard** (6 days)
  - Add calendar integration for deadlines (basic date fields and workload alerts added)
  - Create review criteria templates (config endpoint + UI list)
  - Implement scoring/rating system (review rating endpoint + UI form)
  - Build collaborative review features (review workload + shared reviewer status)

- [x] **Review Process Optimization** (4 days)
  - Add conflict of interest management (COI declaration endpoint + UI)
  - Create review history tracking (reviewer timeline and history incorporated)
  - Implement workload balancing (workload analysis endpoint)
  - Build reviewer analytics (expanded reviewer metrics + workload endpoints)

**Deliverables:**
- Advanced reviewer dashboard
- Comprehensive review process
- Reviewer analytics system

#### Sprint 6: Administrative Features (Weeks 11-12)
**Tasks:**
- [ ] **Admin Dashboard** (6 days)
  - Create system-wide statistics dashboard
  - Build submission management interface
  - Add bulk action capabilities
  - Implement system configuration UI

- [ ] **Reporting & Analytics** (4 days)
  - Create review time analytics
  - Build reviewer performance metrics
  - Add submission trend analysis
  - Implement data export functionality

**Deliverables:**
- Comprehensive admin dashboard
- Reporting and analytics system
- System configuration interface

### Phase 3: Polish & Optimization (Weeks 13-16)

#### Sprint 7: Notification & Communication (Weeks 13-14)
**Tasks:**
- [ ] **Advanced Notification System** (6 days)
  - Implement email notification templates
  - Create in-app notification center
  - Add notification preference management
  - Build automated reminder system

- [ ] **Communication Features** (4 days)
  - Add reviewer-submitter communication
  - Create internal messaging system
  - Implement comment threading
  - Build notification delivery tracking

**Deliverables:**
- Complete notification system
- Enhanced communication features
- Email template library

#### Sprint 8: Performance & Security (Weeks 15-16)
**Tasks:**
- [ ] **Performance Optimization** (5 days)
  - Optimize database queries and caching
  - Implement file storage optimization
  - Add pagination for large datasets
  - Create performance monitoring

- [ ] **Security Hardening** (3 days)
  - Implement comprehensive input validation
  - Add file security scanning
  - Create audit trail system
  - Conduct security testing

- [ ] **Microsoft Entra ID Integration** (4 days)
  - Configure Entra ID application registration
  - Implement SAML/OAuth2 authentication flow
  - Create fallback authentication for external users
  - Test SSO integration and user provisioning

- [ ] **Final System Integration** (3 days)
  - Complete SSO testing and validation
  - Integrate all authentication methods
  - Finalize security configurations
  - Prepare production deployment

**Deliverables:**
- Optimized system performance
- Enhanced security measures
- Complete SSO integration with Microsoft Entra ID
- Production-ready system with all authentication methods
- Audit trail implementation

### Implementation Timeline

```mermaid
gantt
    title Research Review Portal Implementation Timeline
    dateFormat  YYYY-MM-DD
    section Phase 1
    Infrastructure & Auth    :sprint1, 2026-03-03, 2w
    Enhanced Submission     :sprint2, after sprint1, 2w
    Basic Dashboards       :sprint3, after sprint2, 2w
    section Phase 2
    Workflow Automation    :sprint4, after sprint3, 2w
    Advanced Reviewer      :sprint5, after sprint4, 2w
    Admin Features        :sprint6, after sprint5, 2w
    section Phase 3
    Notifications         :sprint7, after sprint6, 2w
    Performance & Security :sprint8, after sprint7, 2w
```

### Resource Requirements

#### Development Team
- **1 Senior Full-Stack Developer** (WordPress/PHP expert)
- **1 Frontend Developer** (React/JavaScript specialist)
- **1 UI/UX Designer** (part-time, first 8 weeks)
- **1 DevOps/Security Specialist** (part-time, weeks 9-16)

#### Technical Infrastructure
- **Development Environment**: WordPress development setup with version control
- **Testing Environment**: Staging server for integration testing
- **CI/CD Pipeline**: Automated testing and deployment
- **Monitoring Tools**: Performance and error monitoring

### Risk Assessment & Mitigation

#### High Risk
🔴 **Microsoft Entra ID Integration Complexity**
- *Risk*: SSO integration delays due to university IT policies
- *Mitigation*: Start Entra ID setup early, have fallback authentication ready

🔴 **Data Migration from Current System**
- *Risk*: Data loss or corruption during enhancement
- *Mitigation*: Comprehensive backup strategy, incremental migration

#### Medium Risk
🟡 **Performance with Large Data Sets**
- *Risk*: System slowdown with hundreds of submissions
- *Mitigation*: Early performance testing, database optimization

🟡 **User Adoption Resistance**
- *Risk*: Users preferring old system or manual processes
- *Mitigation*: User training program, gradual rollout strategy

#### Low Risk
🟢 **Browser Compatibility Issues**
- *Risk*: Features not working in older browsers
- *Mitigation*: Progressive enhancement, fallback features

### Testing Strategy

#### Automated Testing (Weeks 1-16)
- **Unit Tests**: 80% code coverage for core functionality
- **Integration Tests**: API endpoint testing with automated scripts
- **Performance Tests**: Load testing with simulated user traffic

#### User Acceptance Testing (Weeks 11-16)
- **Alpha Testing** (Week 11): Internal team testing
- **Beta Testing** (Weeks 12-14): Limited user group (10-15 users)
- **Production Testing** (Weeks 15-16): Soft launch with monitoring

#### Security Testing (Weeks 15-16)
- **Penetration Testing**: Third-party security assessment
- **Vulnerability Scanning**: Automated security scanning
- **Code Review**: Security-focused code review

### Deployment Strategy

#### Pre-Production (Week 15)
- **Staging Deployment**: Full system testing in production-like environment
- **Data Migration Testing**: Verify all existing data transfers correctly
- **Performance Baseline**: Establish performance metrics

#### Production Rollout (Week 16)
- **Soft Launch**: Limited user access (25% of users)
- **Monitoring Phase**: 48-hour intensive monitoring
- **Full Launch**: Complete system activation
- **Post-Launch Support**: 2-week intensive support period

### Success Metrics

#### Technical Metrics
- **System Uptime**: 99.9% availability
- **Response Time**: < 2 seconds for all page loads
- **Error Rate**: < 0.1% of all requests

#### Business Metrics
- **User Adoption**: 90% of reviewers using new system within 4 weeks
- **Review Time Reduction**: 30% faster average review completion
- **User Satisfaction**: 4.5/5 rating from user surveys

#### Quality Metrics
- **Bug Reports**: < 5 critical bugs in first month
- **Support Tickets**: < 10% of users requiring support
- **Training Completion**: 95% of users complete onboarding

### Post-Implementation Support

#### Immediate Support (Weeks 17-20)
- Daily system monitoring and issue resolution
- User support and training assistance
- Performance optimization based on usage patterns
- Bug fixes and minor feature adjustments

#### Ongoing Maintenance
- **Weekly**: System health checks and performance monitoring
- **Monthly**: User feedback review and feature planning
- **Quarterly**: Security updates and system optimization
- **Annually**: Major feature releases and technology updates

---

## Next Steps

1. **Stakeholder Approval** - Present implementation plan to university leadership
2. **Team Assembly** - Recruit and onboard development team
3. **Environment Setup** - Prepare development and staging environments
4. **Kick-off Meeting** - Initiate Sprint 1 with full team
5. **Entra ID Coordination** - Begin Microsoft Entra ID integration setup with IT department

This comprehensive implementation plan provides a structured approach to enhancing the Research Review Portal while minimizing risks and ensuring successful delivery of all required features.## Implementation and Validation Status (Sprint 1-5)

- [x] Sprint 1: Core plugin architecture, submission endpoints, data storage, REST API. ✅ implemented and verified with test runner.
- [x] Sprint 2: Enhanced submission validation, review stages, timeline/notifications, draft workflow. ✅ implemented and verified with test runner.
- [x] Sprint 3: Reviewer dashboard analytics, user metrics, workflow status endpoints. ✅ implemented and verified with direct WP REST check.
- [x] Sprint 4: Workflow/performance analytics endpoints, report export, scheduled reports. ✅ implemented and verified with direct WP REST check and feature tests.
- [x] Sprint 5: Reviewer workload analytics, COI tracking, criteria templates, rating API/UI. ✅ implemented and verified with direct WP REST check and feature tests.

### Homepage setup

- Created page "Research Review Portal" with `[research_review_portal]` in WordPress (ID 5).
- Set front page to page ID 5 (the portal page) via WP options.
- Confirmed root `/` now returns full WordPress page with stylesheet, scripts and portal embed.

### Runtime check results

- `/wp-json/research-portal/v1/health` → 200 OK
- `/wp-json/research-portal/v1/analytics/workload` → 401 (permission guarded, route exists)
- `/wp-json/research-portal/v1/conflicts` → 200 (as admin internal), route exists
- `/wp-json/research-portal/v1/config/review-templates` → 200, returns templates
- `reviews/rate` POST endpoint exists and permission protected.

### Notes

- WP-CLI default PHP (8.0.30) lacked mysqli; this is an environment path issue and not code issue.
- Direct PHP script bootstrap uses /usr/bin/php (8.3.6 with mysqli) and verifies plugin functionality.

