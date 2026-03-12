# Research Review Portal UI Plan

## Overview
This document describes the current UI flow implemented in `research-review-portal` plugin for the CityU Research Review Portal.

The site has two main entry modes:
- Guest landing (default homepage)
- Logged-in portal (via `?portal=1`)

## Entry points

1. `/` or site homepage
   - default to guest landing page (even if logged in, it should show user specific homepage)
   - shows portal purpose, supported submission types, and process flow
   - includes login button
   - if logged in, offers "Go to your portal" button (adds `?portal=1`)

2. `/?portal=1`
   - triggers interactive portal interface
   - requires login or redirects to login CTA if anonymous
   - shows role banner: username + role (Student, Reviewer, Coordinator, Admin)
   - includes "Logout" button

3. `/?start=<value>`
   - for guest process docs: `conference` `publication` `student-project` `grant`
   - for logged-in portal: `submit`, `status`, `dashboard`, `analytics`, `reviewer`, `public`

## Guest landing UI

- Banner:
  - title: "Welcome to CityU Research Review Portal"
  - description: end-to-end research submission and review workflow
- Supported types:
  - Conference / Symposium
  - Publication
  - Student Project - capstone project
  - Doctoral Research - Dissertation
  - Grant
- Process flow section:
  - shortcode `[rrp_process_documentation type="all" style="compact"]`
  - tabbed docs for each type
- CTA: login button (WordPress login url)

## Logged-in portal UI

- Top user banner:
  - 
  - text: "Logged in as <username> (<role>)"
  - logout button
- Menu buttons:
  - Submit new abstract
  - Check my submissions
  - Dashboard
  - Analytics
  - Reviewer Dashboard
  - Research & symposium (public)
- Type card selector (conference/publication/student/grant) with process custom form behavior

## Role-based access

- Role signals computed in `wp_get_current_user()`:
  - `rrp_student` -> Student
  - `rrp_reviewer` -> Reviewer
  - `rrp_coordinator` -> Coordinator
  - `rrp_admin` / `administrator` -> Admin
- REST permissions enforce actions:
  - `rrp_submit_research` to submit
  - `rrp_view_own_submissions` / `rrp_review_submissions` / `rrp_view_all_submissions` etc.

## Notes
- Guest homepage is intentionally locking interactive portal behind `?portal=1` and login.
- The process documentation component is reused in both guest and authenticated experiences.
- If already logged in but visiting `/`, user sees the guest landing page by design, then can click through to `?portal=1`.
