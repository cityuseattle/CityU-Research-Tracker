(function () {
  'use strict';

  // Normalize the stored WP restBase to the actual browser origin so external
  // browsers (Chrome, etc.) don't resolve "localhost" to their own machine.
  function _normalizeOrigin(url) {
    if (!url || !window.location || !window.location.origin) return url || '';
    return url.replace(/^https?:\/\/[^\/]+/, window.location.origin);
  }

  var restBase = _normalizeOrigin(
    (window.RRP && window.RRP.restBase) ? window.RRP.restBase.replace(/\/$/, '') : ''
  );
  var nonce = (window.RRP && window.RRP.nonce) || '';
  var isLoggedIn = (window.RRP && window.RRP.isLoggedIn) || false;
  var loginUrl  = _normalizeOrigin((window.RRP && window.RRP.loginUrl)  || '/wp-login.php');
  var logoutUrl = _normalizeOrigin((window.RRP && window.RRP.logoutUrl) || '/wp-login.php?action=logout');
  var _viewerIsAdmin = window.RRP && Array.isArray(window.RRP.userRoles) && window.RRP.userRoles.indexOf('Admin') !== -1;

  function api(method, path, body) {
    var url = restBase + path;
    var opts = {
      method: method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce
      }
    };
    if (body && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
      opts.body = typeof body === 'string' ? body : JSON.stringify(body);
    }
    return fetch(url, opts).then(function (r) {
      var ct = r.headers.get('Content-Type') || '';
      var next = ct.indexOf('application/json') !== -1 ? r.json() : r.text();
      return next.then(function (data) {
        if (!r.ok) throw { status: r.status, data: data };
        return data;
      });
    });
  }

  // ── Dynamic submission types (loaded from /submission-types once on boot) ────
  // Seeded with the legacy static types so pages work before the API resolves.
  var _dynTypes = [
    { id: 'conference',    label: 'Conference Paper',          stages: ['Initial Screening','Reviewer Assignment','Peer Review','Review Consolidation','Final Decision','Confirmation'] },
    { id: 'publication',   label: 'Publication',               stages: ['Administrative Check','Reviewer Matching','Expert Review','Director Assessment','Final Decision','Tracking'] },
    { id: 'student-project', label: 'Student Project',         stages: ['Advisor Matching','Advisor Consultation','Feasibility Check','Director Approval','Project Setup','Milestone Tracking'] },
    { id: 'grant',         label: 'Grant Proposal',            stages: ['Compliance Check','Review Assignment','Multi-Criteria Review','Committee Meeting','Final Decision','Development Support','Submission Tracking'] },
    { id: 'dissertation',  label: 'Doctoral Dissertation',     stages: ['Chair Review','Committee Review','Program Director Approval','Dissertation Director Sign-Off'] },
    { id: 'capstone',      label: 'Capstone Project',          stages: ['Advisor Review','Program Director Approval'] },
    { id: 'research-paper', label: 'Research Paper',           stages: ['Peer Review','Program Director Approval'] },
    { id: 'grant-proposal', label: 'Grant Proposal (Detailed)', stages: ['Multi-Criteria Review','Program Director Approval'] }
  ];
  var _dynTypesLoaded = false;

  function _refreshDynTypes(callback) {
    api('GET', '/submission-types').then(function (res) {
      if (res.submissionTypes && res.submissionTypes.length) {
        _dynTypes = res.submissionTypes;
        _dynTypesLoaded = true;
        // Rebuild legacy alias maps used in static places
        TYPE_LABEL_MAP = {};
        _dynTypes.forEach(function (t) { TYPE_LABEL_MAP[t.id] = t.label; });
        // Backward-compat aliases
        TYPE_LABEL_MAP['student'] = TYPE_LABEL_MAP['student-project'] || 'Student Project';
        TYPE_LABEL_MAP['conference paper'] = TYPE_LABEL_MAP['conference'] || 'Conference Paper';
      }
      if (callback) callback();
    }).catch(function () { if (callback) callback(); });
  }

  function dynTypeById(id) {
    return _dynTypes.find(function (t) { return t.id === id; }) || null;
  }

  function dynStagesForType(id) {
    var t = dynTypeById(id); return t ? t.stages : ['Initial Review', 'Final Approval'];
  }

  var SUBMISSION_TYPES = [
    { id: 'conference', title: 'Conference / Symposium', subtitle: 'Applied Research Symposium, Doctor of IT Forum' },
    { id: 'publication', title: 'Publication', subtitle: 'Journal and publication submissions' },
    { id: 'student', title: 'Student Project', subtitle: 'Capstone and student research' },
    { id: 'grant', title: 'Grant', subtitle: 'Funding and grant proposals' }
  ];

  var TYPE_LABEL_MAP = {
    'dissertation':    'Doctoral Dissertation',
    'capstone':        'Capstone Project',
    'student-project': 'Student Project',
    'student':         'Student Project',
    'research-paper':  'Research Paper',
    'publication':     'Publication',
    'conference':      'Conference Paper',
    'conference paper': 'Conference Paper',
    'grant':           'Grant Proposal',
    'grant-proposal':  'Grant Proposal'
  };

  function typeLabel(raw) {
    var k = (raw || '').toLowerCase();
    // Dynamic lookup first
    var dt = dynTypeById(k);
    if (dt) return dt.label;
    return TYPE_LABEL_MAP[k] || (raw || '—');
  }

  function statusBadgeCls(status) {
    var s = (status || '').toLowerCase().replace(/[\s_]+/g, '-');
    if (s === 'approved' || s === 'confirmed-for-presentation' || s === 'published' ||
        s === 'approved-for-submission' || s === 'accepted' || s === 'conditionally-accepted')
                                                               return 'rrp-dec-approved';
    if (s === 'rejected')                                      return 'rrp-dec-rejected';
    if (s === 'needs-revision' || s === 'revision-required'
        || s === 'revision' || s === 'needs-revision')         return 'rrp-dec-revision';
    if (s === 'revision-submitted')                            return 'rrp-dec-revision';
    if (s === 'draft')                                         return 'rrp-dec-draft';
    if (s === 'submitted')                                     return 'rrp-dec-submitted';
    if (s.indexOf('review') !== -1 || s.indexOf('in-progress') !== -1)
                                                               return 'rrp-dec-inreview';
    return 'rrp-dec-pending';
  }

  function isApprovedStatus(status) {
    var s = (status || '').toLowerCase().replace(/[\s_]+/g, '-');
    return s === 'approved' || s === 'confirmed-for-presentation' || s === 'published' ||
           s === 'approved-for-submission' || s === 'accepted' || s === 'conditionally-accepted';
  }

  function applyDateRange(arr, range) {
    if (!range || range === 'all') return arr;
    var ms = { today: 86400000, week: 7 * 86400000, month: 30 * 86400000, year: 365 * 86400000 }[range];
    if (!ms) return arr;
    var cutoff = Date.now() - ms;
    return arr.filter(function (s) { return s.createdAt && new Date(s.createdAt).getTime() >= cutoff; });
  }

  function sortSubmissions(arr) {
    var active = arr.filter(function (s) { return !isApprovedStatus(s.status) && (s.status || '').toLowerCase() !== 'rejected'; });
    var done   = arr.filter(function (s) { return  isApprovedStatus(s.status) || (s.status || '').toLowerCase() === 'rejected'; });
    active.sort(function (a, b) { return new Date(a.createdAt || 0) - new Date(b.createdAt || 0); });
    done.sort(function (a, b)   { return new Date(b.createdAt || 0) - new Date(a.createdAt || 0); });
    return active.concat(done);
  }

  var _drLabels = { today: 'Today', week: 'This Week', month: 'This Month', year: 'This Year', all: 'All Time' };
  function dateRangeBar(current) {
    return '<div class="rrp-date-range-bar">' +
      ['today', 'week', 'month', 'year', 'all'].map(function (r) {
        return '<button type="button" class="rrp-date-range-btn' + (current === r ? ' active' : '') + '" data-daterange="' + r + '">' + _drLabels[r] + '</button>';
      }).join('') +
    '</div>';
  }

  var typeToApi = { conference: 'conference', publication: 'publication', student: 'student-project', grant: 'grant' };

  function getQueryParam(name) {
    var match = window.location.search.match(new RegExp('[?&]' + name + '=([^&]+)'));
    return match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : null;
  }

  // ── In-portal Profile Editor ──────────────────────────────────────────────
  function renderProfilePanel(container, backFn) {
    var _backFn = backFn || function () {};

    // Seed from window.RRP (fast, no round-trip needed for initial render)
    var _profile = {
      id:         (window.RRP && window.RRP.userId)     || 0,
      name:       (window.RRP && window.RRP.userName)   || '',
      email:      (window.RRP && window.RRP.userEmail)  || '',
      firstName:  (window.RRP && window.RRP.firstName)  || '',
      lastName:   (window.RRP && window.RRP.lastName)   || '',
      degree:     (window.RRP && window.RRP.degree)     || '',
      department: (window.RRP && window.RRP.department) || '',
      expertise:  (window.RRP && window.RRP.expertise)  || '',
      portalRole: (window.RRP && (Array.isArray(window.RRP.userRoles) ? window.RRP.userRoles.join(' · ') : window.RRP.userRole)) || ''
    };

    function render(programs, departments) {
      // Build degree/program dropdown
      var degreeOptions = '<option value="">&#8212; Select program &#8212;</option>';
      if (programs && programs.length) {
        // Group by school
        var schools = [];
        programs.forEach(function(p) { if (schools.indexOf(p.school) === -1) schools.push(p.school); });
        schools.forEach(function(sch) {
          degreeOptions += '<optgroup label="' + escapeHtml(sch || 'Other') + '">';
          programs.filter(function(p) { return p.school === sch; }).forEach(function(p) {
            degreeOptions += '<option value="' + escapeHtml(p.id) + '"' + (_profile.degree === p.id || _profile.degree === p.label ? ' selected' : '') + '>' + escapeHtml(p.label) + '</option>';
          });
          degreeOptions += '</optgroup>';
        });
      } else {
        // Fallback: pre-fill what we have as a plain option
        if (_profile.degree) degreeOptions += '<option value="' + escapeHtml(_profile.degree) + '" selected>' + escapeHtml(_profile.degree) + '</option>';
      }
      // Build department dropdown
      var deptOptions = '<option value="">&#8212; Select department &#8212;</option>';
      if (departments && departments.length) {
        departments.forEach(function(d) {
          deptOptions += '<option value="' + escapeHtml(d.id) + '"' + (_profile.department === d.id || _profile.department === d.label ? ' selected' : '') + '>' + escapeHtml(d.label) + '</option>';
        });
      } else {
        if (_profile.department) deptOptions += '<option value="' + escapeHtml(_profile.department) + '" selected>' + escapeHtml(_profile.department) + '</option>';
      }
      container.innerHTML =
        '<div class="rrp-dashboard">' +
          '<div class="rrp-dash-header">' +
            '<button type="button" class="rrp-btn secondary" id="rrp-profile-back">&#8592; Back</button>' +
            '<h1>&#128100; Edit Profile</h1>' +
          '</div>' +
          '<div class="rrp-card" style="max-width:560px;">' +
            // Avatar + identity row
            '<div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding-bottom:1.25rem;border-bottom:1px solid #e5e7eb;">' +
              '<div style="width:56px;height:56px;border-radius:50%;background:#003d66;color:#fff;font-size:1.5rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">&#128100;</div>' +
              '<div>' +
                '<div style="font-weight:700;font-size:1.05rem;color:#1a202c;" id="rrp-profile-display-name">' + escapeHtml(_profile.name) + '</div>' +
                '<div style="font-size:.83rem;color:#6b7280;">' + escapeHtml(_profile.email) + '</div>' +
                '<div style="font-size:.78rem;color:#9ca3af;margin-top:.15rem;">' + escapeHtml(_profile.portalRole) + ' &middot; CityU STC</div>' +
              '</div>' +
            '</div>' +
            '<form id="rrp-profile-form">' +
              '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">' +
                '<label class="rrp-label">First Name<br><input type="text" class="rrp-input" id="rrp-pf-first" value="' + escapeHtml(_profile.firstName) + '" placeholder="First name" /></label>' +
                '<label class="rrp-label">Last Name<br><input type="text" class="rrp-input" id="rrp-pf-last" value="' + escapeHtml(_profile.lastName) + '" placeholder="Last name" /></label>' +
              '</div>' +
              '<label class="rrp-label" style="margin-top:.75rem;">Email (read-only)<br>' +
                '<input type="email" class="rrp-input" value="' + escapeHtml(_profile.email) + '" readonly style="background:#f1f5f9;cursor:not-allowed;color:#6b7280;" /></label>' +
              '<label class="rrp-label" style="margin-top:.75rem;">Degree / Program<br>' +
                '<select class="rrp-input" id="rrp-pf-degree">' + degreeOptions + '</select></label>' +
              '<label class="rrp-label" style="margin-top:.75rem;">Department / School<br>' +
                '<select class="rrp-input" id="rrp-pf-dept">' + deptOptions + '</select></label>' +
              '<label class="rrp-label" style="margin-top:.75rem;">Research Interests / Expertise<br>' +
                '<textarea class="rrp-input" id="rrp-pf-expertise" rows="3" placeholder="Briefly describe your research interests or area of expertise">' + escapeHtml(_profile.expertise) + '</textarea></label>' +
              '<details style="margin-top:1rem;">' +
                '<summary style="cursor:pointer;font-size:.88rem;font-weight:600;color:#475569;user-select:none;">Change Password</summary>' +
                '<div style="margin-top:.75rem;display:flex;flex-direction:column;gap:.5rem;">' +
                  '<label class="rrp-label">New Password (min 8 characters)<br>' +
                    '<input type="password" class="rrp-input" id="rrp-pf-pass" autocomplete="new-password" placeholder="Leave blank to keep current" /></label>' +
                  '<label class="rrp-label">Confirm Password<br>' +
                    '<input type="password" class="rrp-input" id="rrp-pf-pass2" autocomplete="new-password" placeholder="Re-enter new password" /></label>' +
                '</div>' +
              '</details>' +
              '<div style="margin-top:1.25rem;display:flex;gap:.6rem;align-items:center;">' +
                '<button type="submit" class="rrp-btn primary" id="rrp-pf-save">Save Changes</button>' +
                '<button type="button" class="rrp-btn secondary" id="rrp-pf-cancel">Cancel</button>' +
                '<span id="rrp-pf-msg" style="font-size:.85rem;display:none;"></span>' +
              '</div>' +
            '</form>' +
          '</div>' +
        '</div>';

      document.getElementById('rrp-profile-back').addEventListener('click', _backFn);
      document.getElementById('rrp-pf-cancel').addEventListener('click', _backFn);

      document.getElementById('rrp-profile-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var msgEl   = document.getElementById('rrp-pf-msg');
        var saveBtn = document.getElementById('rrp-pf-save');
        var pass1   = document.getElementById('rrp-pf-pass').value;
        var pass2   = document.getElementById('rrp-pf-pass2').value;

        if (pass1 && pass1.length < 8) {
          msgEl.style.display = 'inline'; msgEl.style.color = '#ef4444';
          msgEl.textContent = 'Password must be at least 8 characters.'; return;
        }
        if (pass1 && pass1 !== pass2) {
          msgEl.style.display = 'inline'; msgEl.style.color = '#ef4444';
          msgEl.textContent = 'Passwords do not match.'; return;
        }

        var payload = {
          firstName:  document.getElementById('rrp-pf-first').value.trim(),
          lastName:   document.getElementById('rrp-pf-last').value.trim(),
          degree:     document.getElementById('rrp-pf-degree').value.trim(),
          department: document.getElementById('rrp-pf-dept').value.trim(),
          expertise:  document.getElementById('rrp-pf-expertise').value.trim()
        };
        if (pass1) payload.password = pass1;

        saveBtn.disabled = true; saveBtn.textContent = 'Saving\u2026';
        msgEl.style.display = 'none';

        api('PATCH', '/portal-users/me', payload).then(function (res) {
          var u = res.user || {};
          // Update window.RRP so the rest of the portal reflects the name change immediately
          if (window.RRP) {
            if (u.name)  window.RRP.userName   = u.name;
            if (payload.firstName) window.RRP.firstName  = payload.firstName;
            if (payload.lastName)  window.RRP.lastName   = payload.lastName;
            if (payload.degree)    window.RRP.degree     = payload.degree;
            if (payload.department) window.RRP.department = payload.department;
            if (payload.expertise)  window.RRP.expertise  = payload.expertise;
          }
          // Update displayed name in card header
          if (u.name) {
            var dn = document.getElementById('rrp-profile-display-name');
            if (dn) dn.textContent = u.name;
          }
          saveBtn.disabled    = false;
          saveBtn.textContent = 'Save Changes';
          msgEl.style.display = 'inline'; msgEl.style.color = '#16a34a';
          msgEl.textContent   = 'Profile updated successfully.';
          // Clear password fields
          document.getElementById('rrp-pf-pass').value  = '';
          document.getElementById('rrp-pf-pass2').value = '';
        }).catch(function (err) {
          saveBtn.disabled    = false;
          saveBtn.textContent = 'Save Changes';
          msgEl.style.display = 'inline'; msgEl.style.color = '#ef4444';
          msgEl.textContent   = 'Save failed: ' + (err && err.message ? err.message : 'Please try again.');
        });
      });
    }

    // Fetch fresh profile data and config (for dropdowns) in parallel, then render
    Promise.all([
      api('GET', '/portal-users/me').catch(function() { return {}; }),
      api('GET', '/config').catch(function() { return {}; })
    ]).then(function(results) {
      var meRes  = results[0];
      var cfgRes = results[1];
      var u = meRes.user || {};
      if (u.name)       _profile.name       = u.name;
      if (u.email)      _profile.email      = u.email;
      if (u.firstName !== undefined) _profile.firstName  = u.firstName  || '';
      if (u.lastName  !== undefined) _profile.lastName   = u.lastName   || '';
      if (u.degree    !== undefined) _profile.degree     = u.degree     || '';
      if (u.department !== undefined) _profile.department = u.department || '';
      if (u.expertise !== undefined)  _profile.expertise  = u.expertise  || '';
      render(cfgRes.programs || [], cfgRes.departments || []);
    });
  }

  // ── Student Dashboard ──────────────────────────────────────────────────────
  function renderStudentDashboard(container, activeFilter) {
    var userName   = (window.RRP && window.RRP.userName)   || 'Student';
    var userEmail  = ((window.RRP && window.RRP.userEmail) || '').toLowerCase();
    activeFilter   = activeFilter || 'all';
    var activeDateRange = 'all';

    container.innerHTML =
      '<div class="rrp-student-banner">' +
        '<div class="rrp-student-banner-info">' +
          '<div class="rrp-student-banner-title">Welcome, ' + escapeHtml(userName) + '</div>' +
          '<div class="rrp-student-banner-sub">City University of Seattle &nbsp;·&nbsp; School of Technology and Computing</div>' +
        '</div>' +
        '<button type="button" class="rrp-btn rrp-new-submission-btn" id="rrp-student-new-btn">＋ New Submission</button>' +
      '</div>' +
      '<div class="rrp-student-layout">' +
        '<div class="rrp-student-main">' +
          '<div id="rrp-student-stats" class="rrp-stats-row"><p class="rrp-loading">Loading…</p></div>' +
          '<div id="rrp-student-submissions" class="rrp-dashboard-section">' +
            '<h2>My Submissions</h2><p class="rrp-loading">Loading submissions…</p>' +
          '</div>' +
        '</div>' +
        '<aside class="rrp-student-aside">' +
          '<div class="rrp-profile-card">' +
            '<div class="rrp-profile-icon">&#128100;</div>' +
            '<div class="rrp-profile-name">' + escapeHtml(userName) + '</div>' +
            '<div class="rrp-profile-email">' + escapeHtml(userEmail) + '</div>' +
            '<div class="rrp-profile-role">Student &middot; CityU STC</div>' +
            '<button type="button" class="rrp-btn secondary" id="rrp-edit-profile-btn" style="margin-top:0.75rem;width:100%;">&#9998; Edit Profile</button>' +
          '</div>' +
          '<div class="rrp-nav-card">' +
            '<div class="rrp-nav-card-title">Quick links</div>' +
            '<ul class="rrp-nav-list">' +
              '<li><button type="button" class="rrp-nav-link" data-view="public">&#127760; Public submissions</button></li>' +
            '</ul>' +
          '</div>' +
        '</aside>' +
      '</div>';

    document.getElementById('rrp-student-new-btn').addEventListener('click', function () {
      renderStudentForm(container);
    });
    document.getElementById('rrp-edit-profile-btn').addEventListener('click', function () {
      renderProfilePanel(container, function () { renderStudentDashboard(container); });
    });

    container.querySelectorAll('[data-view]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var view = btn.getAttribute('data-view');
        if (view === 'public') renderPublic(container, function () { renderStudentDashboard(container); });
      });
    });

    // Load student's own submissions
    api('GET', '/submissions')
      .then(function (res) {
        var all  = res.submissions || [];
        var mine = all.filter(function (s) {
          return (s.submitterEmail || '').toLowerCase() === userEmail;
        });

        var total    = mine.length;
        var inReview = mine.filter(function (s) { var st = (s.status || '').toLowerCase(); return st !== 'draft' && !isApprovedStatus(s.status) && st !== 'rejected'; }).length;
        var approved = mine.filter(function (s) { return isApprovedStatus(s.status); }).length;
        var drafts   = mine.filter(function (s) { return (s.status || '').toLowerCase() === 'draft'; }).length;

        document.getElementById('rrp-student-stats').innerHTML =
          '<button class="rrp-stat-card' + (activeFilter === 'all' ? ' rrp-stat-active' : '') + '" data-stat-filter="all">' +
            '<span class="rrp-stat-value">' + total + '</span>' +
            '<span class="rrp-stat-label">Total</span>' +
          '</button>' +
          '<button class="rrp-stat-card rrp-stat-blue' + (activeFilter === 'inreview' ? ' rrp-stat-active' : '') + '" data-stat-filter="inreview">' +
            '<span class="rrp-stat-value">' + inReview + '</span>' +
            '<span class="rrp-stat-label">In Review</span>' +
          '</button>' +
          '<button class="rrp-stat-card rrp-stat-green' + (activeFilter === 'approved' ? ' rrp-stat-active' : '') + '" data-stat-filter="approved">' +
            '<span class="rrp-stat-value">' + approved + '</span>' +
            '<span class="rrp-stat-label">Approved</span>' +
          '</button>' +
          '<button class="rrp-stat-card rrp-stat-muted' + (activeFilter === 'draft' ? ' rrp-stat-active' : '') + '" data-stat-filter="draft">' +
            '<span class="rrp-stat-value">' + drafts + '</span>' +
            '<span class="rrp-stat-label">Drafts</span>' +
          '</button>';

        // Stat card click → filter list
        document.querySelectorAll('[data-stat-filter]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var f = btn.getAttribute('data-stat-filter');
            renderSubmissionList(mine, f, activeDateRange);
            document.querySelectorAll('[data-stat-filter]').forEach(function (b) {
              b.classList.toggle('rrp-stat-active', b.getAttribute('data-stat-filter') === f);
            });
          });
        });

        renderSubmissionList(mine, activeFilter, activeDateRange);
      })
      .catch(function () {
        document.getElementById('rrp-student-stats').innerHTML = '<div class="rrp-error">Unable to load your submissions.</div>';
      });

    function renderSubmissionList(mine, filter, dateRange) {
      dateRange = dateRange || 'all';
      var filtered = mine;
      if (filter === 'inreview') {
        filtered = mine.filter(function (s) { var st = (s.status || '').toLowerCase(); return st !== 'draft' && st !== 'approved' && st !== 'rejected'; });
      } else if (filter === 'approved') {
        filtered = mine.filter(function (s) { return isApprovedStatus(s.status); });
      } else if (filter === 'draft') {
        filtered = mine.filter(function (s) { return (s.status || '').toLowerCase() === 'draft'; });
      }
      filtered = applyDateRange(filtered, dateRange);
      filtered = sortSubmissions(filtered);

      var listEl = document.getElementById('rrp-student-submissions');
      var heading = filter === 'inreview' ? 'Under Review' :
                    filter === 'approved' ? 'Approved' :
                    filter === 'draft'    ? 'Drafts' : 'All Submissions';

      if (filtered.length === 0) {
        listEl.innerHTML =
          dateRangeBar(dateRange) +
          '<h2>' + heading + '</h2>' +
          '<div class="rrp-empty-state">' +
            '<p>' + (mine.length === 0 ? 'You have no submissions yet.' : 'No submissions match this filter.') + '</p>' +
            (mine.length === 0 ? '<button type="button" class="rrp-btn" id="rrp-empty-new-btn">＋ Make your first submission</button>' : '') +
          '</div>';
        var emptyBtn = document.getElementById('rrp-empty-new-btn');
        if (emptyBtn) emptyBtn.addEventListener('click', function () { renderStudentForm(container); });
        listEl.querySelectorAll('[data-daterange]').forEach(function (b) {
          b.addEventListener('click', function () { activeDateRange = b.getAttribute('data-daterange'); renderSubmissionList(mine, filter, activeDateRange); });
        });
        return;
      }

      listEl.innerHTML =
        dateRangeBar(dateRange) +
        '<h2>' + heading + ' <span class="rrp-count-badge">' + filtered.length + '</span></h2>' +
        '<ul class="rrp-list rrp-submissions-list">' +
        filtered.map(function (s) {
          var firstReviewer = '';
          var _stagesS = s.reviewStages || [];
          if (_stagesS.length) {
            var _activeS = getActiveStage(_stagesS);
            if (_activeS && (_activeS.reviewers || []).length) {
              firstReviewer = _activeS.reviewers[0].name || _activeS.reviewers[0].email;
            }
          }
          return '<li class="rrp-sub-item">' +
            '<div class="rrp-sub-item-header">' +
              '<strong>' + escapeHtml(s.title || 'Untitled') + '</strong>' +
              '<span class="rrp-decision-badge ' + statusBadgeCls(s.status) + '">' + escapeHtml(s.status || 'Submitted') + '</span>' +
            '</div>' +
            '<div class="rrp-sub-item-meta">' +
              '<span class="rrp-meta-id"><span class="rrp-meta-lbl">ID</span>' + escapeHtml(s.id) + '</span>' +
              '<span><span class="rrp-meta-lbl">Category</span>' + escapeHtml(typeLabel(s.submissionType || s.type)) + '</span>' +
              (s.createdAt ? '<span><span class="rrp-meta-lbl">Submitted</span>' + new Date(s.createdAt).toLocaleDateString() + '</span>' : '') +
              (firstReviewer ? '<span><span class="rrp-meta-lbl">Reviewer</span>' + escapeHtml(firstReviewer) + '</span>' : '') +
            '</div>' +
            '<div class="rrp-sub-item-actions">' +
              '<button type="button" class="rrp-btn secondary" data-detail="' + escapeHtml(s.id) + '">View Details</button>' +
              '<button type="button" class="rrp-btn secondary rrp-btn-log" data-audit-log="' + escapeHtml(s.id) + '">&#128221; Log</button>' +
            '</div>' +
            '</li>';
        }).join('') +
        '</ul>';

      listEl.querySelectorAll('[data-detail]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          renderSubmissionDetail(btn.getAttribute('data-detail'), container, function () { renderStudentDashboard(container); });
        });
      });
      listEl.querySelectorAll('[data-audit-log]').forEach(function (btn) {
        btn.addEventListener('click', function () { openAuditLogModal(btn.getAttribute('data-audit-log')); });
      });
      listEl.querySelectorAll('[data-daterange]').forEach(function (btn) {
        btn.addEventListener('click', function () { activeDateRange = btn.getAttribute('data-daterange'); renderSubmissionList(mine, filter, activeDateRange); });
      });
    }
  }

  // ── Simplified Student Submission Form ────────────────────────────────────
  function renderStudentForm(container) {
    var userName  = (window.RRP && window.RRP.userName)  || '';
    var userEmail = (window.RRP && window.RRP.userEmail) || '';

    // Build type options from dynamic types, filtered to student's allowed types
    var allowedTypes = (window.RRP && window.RRP.allowedTypes && window.RRP.allowedTypes.length)
      ? window.RRP.allowedTypes
      : _dynTypes.map(function(t) { return t.id; });
    var typeOptions = allowedTypes.map(function (t) {
      return '<option value="' + t + '">' + escapeHtml(dynTypeById(t) ? dynTypeById(t).label : t) + '</option>';
    }).join('');

    container.innerHTML =
      '<h1>New Submission</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom:1rem;" data-back>&#8592; Back to Dashboard</button>' +
      '<div id="rrp-form-errors"></div>' +
      '<form id="rrp-submit-form" novalidate>' +
        '<div class="rrp-form-block">' +
          '<label>Submission type *</label>' +
          '<select name="submissionType" required>' +
            '<option value="">&#8212; select type &#8212;</option>' +
            typeOptions +
          '</select>' +
        '</div>' +
        '<div class="rrp-form-block">' +
          '<label>Title * <span class="rrp-hint">(max 200 characters)</span></label>' +
          '<input type="text" name="title" required maxlength="200">' +
          '<span class="rrp-counter" id="rrp-title-counter">0 / 200</span>' +
        '</div>' +
        '<div class="rrp-form-block">' +
          '<label>Research area *</label>' +
          '<input type="text" name="researchArea" required placeholder="e.g. Computer Science, Business Administration&#8230;">' +
        '</div>' +
        '<div class="rrp-form-block">' +
          '<label>Document <span class="rrp-hint">(PDF or DOCX only &#8211; max 2 MB)</span></label>' +
          '<input type="file" name="files" id="rrp-file-input" accept=".pdf,.docx">' +
          '<div id="rrp-file-list" class="rrp-file-list"></div>' +
        '</div>' +
        '<div class="rrp-form-actions">' +
          '<button type="submit" class="rrp-btn" id="rrp-submit-btn">Submit</button>' +
          '<button type="button" class="rrp-btn secondary" id="rrp-draft-btn">Save draft</button>' +
        '</div>' +
      '</form>';

    container.querySelector('[data-back]').addEventListener('click', function () { renderStudentDashboard(container); });

    var form = document.getElementById('rrp-submit-form');

    // Live title counter
    var titleInput   = form.querySelector('[name="title"]');
    var titleCounter = document.getElementById('rrp-title-counter');
    titleInput.addEventListener('input', function () {
      var len = titleInput.value.length;
      titleCounter.textContent = len + ' / 200';
      titleCounter.className = 'rrp-counter' + (len > 190 ? ' rrp-counter-warn' : (len > 0 ? ' rrp-counter-ok' : ''));
    });

    // File list preview
    var fileInput  = document.getElementById('rrp-file-input');
    var fileListEl = document.getElementById('rrp-file-list');
    fileInput.addEventListener('change', function () {
      var files = Array.from(fileInput.files || []);
      fileListEl.innerHTML = files.map(function (f) {
        var mb = (f.size / 1024 / 1024).toFixed(2);
        var ok = f.size <= 2 * 1024 * 1024;
        return '<div class="rrp-file-item' + (!ok ? ' rrp-file-item-warn' : '') + '">' +
          escapeHtml(f.name) + ' (' + mb + ' MB)' + (!ok ? ' &#9888; exceeds 2 MB limit' : '') + '</div>';
      }).join('');
    });

    // Save draft
    document.getElementById('rrp-draft-btn').addEventListener('click', function () {
      var fd   = new FormData(form);
      var sType = fd.get('submissionType') || '';
      var body  = {
        type:           sType,
        submissionType: sType,
        title:          fd.get('title')        || '',
        researchArea:   fd.get('researchArea') || '',
        submitterName:  userName,
        submitterEmail: userEmail,
        affiliation:    'City University of Seattle',
        status:         'draft'
      };
      var errEl = document.getElementById('rrp-form-errors');
      api('POST', '/submit', body)
        .then(function (res) {
          errEl.innerHTML = '<div class="rrp-info">Draft saved. Reference: <strong>' + escapeHtml(res.id) + '</strong></div>';
        })
        .catch(function () {
          errEl.innerHTML = '<div class="rrp-info">Draft saved locally (server unavailable).</div>';
        });
    });

    // Submit
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var errEl = document.getElementById('rrp-form-errors');
      errEl.innerHTML = '';
      var fd    = new FormData(form);
      var sType = fd.get('submissionType') || '';
      var title = (fd.get('title') || '').trim();

      if (!sType)  { errEl.innerHTML = '<div class="rrp-error">Please select a submission type.</div>'; return; }
      if (!title)  { errEl.innerHTML = '<div class="rrp-error">Title is required.</div>'; return; }
      if (!(fd.get('researchArea') || '').trim()) { errEl.innerHTML = '<div class="rrp-error">Research area is required.</div>'; return; }

      var body = {
        type:           sType,
        submissionType: sType,
        title:          title,
        researchArea:   (fd.get('researchArea') || '').trim(),
        submitterName:  userName,
        submitterEmail: userEmail,
        affiliation:    'City University of Seattle'
      };

      var submitBtn = document.getElementById('rrp-submit-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting\u2026';

      api('POST', '/submit', body)
        .then(function (res) {
          var files = fileInput.files;
          if (files && files.length > 0 && res.id) {
            var fileData = new FormData();
            for (var i = 0; i < files.length; i++) { fileData.append('files[]', files[i]); }
            return fetch(restBase + '/submissions/' + encodeURIComponent(res.id) + '/attachments', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'X-WP-Nonce': nonce },
              body: fileData
            }).then(function () { return res; });
          }
          return res;
        })
        .then(function (res) {
          container.innerHTML =
            '<div class="rrp-student-success">' +
              '<div class="rrp-success-icon">&#10003;</div>' +
              '<h1>Submission received!</h1>' +
              '<p>' + escapeHtml(res.message || 'Your submission has been received successfully.') + '</p>' +
              '<p><strong>Reference ID:</strong> <code>' + escapeHtml(res.id) + '</code></p>' +
              '<p>A confirmation will be sent to <strong>' + escapeHtml(userEmail) + '</strong>. Track status on your dashboard.</p>' +
              '<button type="button" class="rrp-btn" id="rrp-back-dashboard">&#8592; Back to Dashboard</button>' +
            '</div>';
          document.getElementById('rrp-back-dashboard').addEventListener('click', function () { renderStudentDashboard(container); });
        })
        .catch(function (err) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit';
          var msg = (err.data && err.data.errors && err.data.errors.length)
            ? err.data.errors.join(' ')
            : ((err.data && err.data.error) || 'Submission failed. Please try again.');
          errEl.innerHTML = '<div class="rrp-error">' + escapeHtml(msg) + '</div>';
        });
    });
  }

  // ── Coordinator / Admin Dashboard ─────────────────────────────────────────
  function renderCoordinatorDashboard(container, activeFilter) {
    var userName   = (window.RRP && window.RRP.userName)   || 'Coordinator';
    var userRole   = (window.RRP && window.RRP.userRole)   || 'Coordinator';
    activeFilter   = activeFilter || 'all';
    var activeDateRange = 'all';

    // Stage labels per submission type — reads from dynamic _dynTypes
    function stagesForSub(sub) {
      var key = (sub.submissionType || sub.type || '').toLowerCase().replace(/\s+/g, '-');
      return dynStagesForType(key);
    }

    container.innerHTML =
      '<div class="rrp-student-banner rrp-coordinator-banner">' +
        '<div class="rrp-student-banner-info">' +
          '<div class="rrp-student-banner-title">Welcome, ' + escapeHtml(userName) + '</div>' +
          '<div class="rrp-student-banner-sub">City University of Seattle &nbsp;&middot;&nbsp; ' + escapeHtml(userRole) + ' &mdash; Research Review Portal</div>' +
        '</div>' +
        '<button type="button" class="rrp-btn rrp-new-submission-btn" id="rrp-coord-auto-assign-btn">&#9881; Auto-assign All</button>' +
      '</div>' +
      '<div class="rrp-student-layout">' +
        '<div class="rrp-student-main">' +
          '<div id="rrp-coord-stats" class="rrp-stats-row"><p class="rrp-loading">Loading&hellip;</p></div>' +
          '<div id="rrp-coord-submissions" class="rrp-dashboard-section">' +
            '<h2>All Submissions</h2><p class="rrp-loading">Loading&hellip;</p>' +
          '</div>' +
        '</div>' +
        '<aside class="rrp-student-aside">' +
          '<div class="rrp-profile-card">' +
            '<div class="rrp-profile-icon">&#128101;</div>' +
            '<div class="rrp-profile-name">' + escapeHtml(userName) + '</div>' +
            '<div class="rrp-profile-role rrp-profile-role-coord">' + escapeHtml(userRole) + ' &middot; CityU STC</div>' +
            '<button type="button" class="rrp-btn secondary" id="rrp-edit-profile-btn" style="margin-top:0.75rem;width:100%;">&#9998; Edit Profile</button>' +
          '</div>' +
          '<div class="rrp-nav-card">' +
            '<div class="rrp-nav-card-title">Users</div>' +
            '<ul class="rrp-nav-list">' +
              '<li><button type="button" class="rrp-nav-link" data-view="users">&#128101; All Users</button></li>' +
            '</ul>' +
          '</div>' +
          '<div class="rrp-nav-card">' +
            '<div class="rrp-nav-card-title">Groups</div>' +
            '<ul class="rrp-nav-list">' +
              '<li><button type="button" class="rrp-nav-link" data-view="students">&#127891; Students</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="reviewers">&#128064; Reviewers</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="faculty">&#127979; Faculty</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="coordinators">&#128203; Coordinators</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="admins">&#128081; Admins</button></li>' +
            '</ul>' +
          '</div>' +
          '<div class="rrp-nav-card">' +
            '<div class="rrp-nav-card-title">Config</div>' +
            '<ul class="rrp-nav-list">' +
              '<li><button type="button" class="rrp-nav-link" data-view="workflow-stages">&#9881; Workflow Stages</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="submission-types">&#128196; Submission Types</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="programs">&#127979; Programs</button></li>' +
            '</ul>' +
          '</div>' +
          '<div class="rrp-nav-card">' +
            '<div class="rrp-nav-card-title">Tools</div>' +
            '<ul class="rrp-nav-list">' +
              '<li><button type="button" class="rrp-nav-link" data-view="analytics">&#128202; Analytics</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="overdue">&#128680; Overdue</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="extensions">&#128197; Extension Requests</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="coi">&#9888;&#65039; COI Declarations</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="public">&#127760; Public</button></li>' +
            '</ul>' +
          '</div>' +
          '<div class="rrp-nav-card">' +
            '<div class="rrp-nav-card-title">Settings</div>' +
            '<ul class="rrp-nav-list">' +
              '<li><button type="button" class="rrp-nav-link" data-view="portal-settings">&#9881; Portal Settings</button></li>' +
            '</ul>' +
          '</div>' +
          '<div id="rrp-coord-reviewer-pool"></div>' +
        '</aside>' +
      '</div>' +
      // Inline assignment panel (hidden until triggered)
      '<div id="rrp-coord-assign-panel" style="display:none;" class="rrp-assign-panel"></div>';

    // Quick-nav links
    container.querySelectorAll('[data-view]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var view = btn.getAttribute('data-view');
        if (view === 'analytics') renderAnalytics(container, function () { renderCoordinatorDashboard(container); });
        if (view === 'public')    renderPublic(container,    function () { renderCoordinatorDashboard(container); });
        if (view === 'overdue')   renderOverdue(container);
        if (view === 'extensions') renderExtensionRequests(container, function () { renderCoordinatorDashboard(container); });
        if (view === 'students')  renderStudentManagement(container,  function () { renderCoordinatorDashboard(container); });
        if (view === 'reviewers') renderReviewerManagement(container, function () { renderCoordinatorDashboard(container); });
        if (view === 'programs')  renderProgramManagement(container,  function () { renderCoordinatorDashboard(container); });
        if (view === 'users')             renderUsersPanel(container,            function () { renderCoordinatorDashboard(container); });
        if (view === 'faculty')            renderUsersPanel(container,            function () { renderCoordinatorDashboard(container); }, 'rrp_faculty');
        if (view === 'coordinators')       renderUsersPanel(container,            function () { renderCoordinatorDashboard(container); }, 'rrp_coordinator');
        if (view === 'admins')             renderUsersPanel(container,            function () { renderCoordinatorDashboard(container); }, 'rrp_admin');
        if (view === 'workflow-stages')    renderWorkflowStagesPanel(container,   function () { renderCoordinatorDashboard(container); });
        if (view === 'submission-types')   renderSubmissionTypesPanel(container,  function () { renderCoordinatorDashboard(container); });
        if (view === 'portal-settings')    renderPortalSettings(container,        function () { renderCoordinatorDashboard(container); });
        if (view === 'coi')                renderCOIPanel(container,              function () { renderCoordinatorDashboard(container); });
      });
    });

    // Manage Reviewer Pool toggle — removed (use Reviewers quick link instead)

    // Edit Profile
    document.getElementById('rrp-edit-profile-btn').addEventListener('click', function () {
      renderProfilePanel(container, function () { renderCoordinatorDashboard(container); });
    });

    // Auto-assign all submissions
    document.getElementById('rrp-coord-auto-assign-btn').addEventListener('click', function () {
      if (!confirm('Auto-assign reviewers from pool to all unassigned submissions?\n\nExisting assignments will not be overwritten.')) return;
      var btn = document.getElementById('rrp-coord-auto-assign-btn');
      btn.disabled = true; btn.textContent = 'Assigning\u2026';
      api('POST', '/config/apply-pool-to-submissions', {})
        .then(function (res) {
          btn.disabled = false; btn.textContent = '\u2699 Auto-assign All';
          alert('Done. Applied: ' + (res.applied || 0) + ', Skipped: ' + (res.skipped || 0) + ' of ' + (res.total || 0) + ' total.');
          renderCoordinatorDashboard(container, activeFilter);
        })
        .catch(function () {
          btn.disabled = false; btn.textContent = '\u2699 Auto-assign All';
          alert('Auto-assign failed. Please try again.');
        });
    });

    // Load all submissions + assignment summary in parallel
    // If coordinator has program assignments, also fetch students to scope submissions
    var _cRoles       = (window.RRP && Array.isArray(window.RRP.userRoles)) ? window.RRP.userRoles : [(window.RRP && window.RRP.userRole) || ''];
    var isCoordinator = _cRoles.indexOf('Coordinator') !== -1;
    var myProgramIds  = (window.RRP && window.RRP.programIds) || [];
    var coordFetch    = (isCoordinator && myProgramIds.length > 0)
      ? api('GET', '/portal-users?role=student')
      : Promise.resolve({ users: null });

    Promise.all([
      api('GET', '/submissions'),
      api('GET', '/assignment-summary'),
      coordFetch
    ]).then(function (results) {
      var allRaw  = results[0].submissions || [];
      var summary = {};
      (results[1].submissions || []).forEach(function (s) { summary[s.id] = s; });

      // Coordinator scoping: filter to submissions from students whose programId is in myProgramIds
      var all = allRaw;
      if (isCoordinator && myProgramIds.length > 0 && results[2].users !== null) {
        var myStudents = (results[2].users || []).filter(function (u) {
          return myProgramIds.indexOf(u.programId) !== -1;
        });
        var myStudentEmails = myStudents.map(function (u) { return (u.email || '').toLowerCase(); });
        all = allRaw.filter(function (s) {
          return myStudentEmails.indexOf((s.submitterEmail || '').toLowerCase()) !== -1;
        });
      }

      var unassigned = all.filter(function (s) {
        var sum = summary[s.id];
        if (!sum) return true;
        return !(sum.reviewStages || []).some(function (st) { return (st.reviewers || []).length > 0; });
      }).length;
      var inReview = all.filter(function (s) {
        var st = (s.status || '').toLowerCase();
        return st !== 'draft' && !isApprovedStatus(s.status) && st !== 'rejected' && st !== 'submitted';
      }).length;
      var approved = all.filter(function (s) { return isApprovedStatus(s.status); }).length;

      document.getElementById('rrp-coord-stats').innerHTML =
        '<button class="rrp-stat-card'       + (activeFilter === 'all'        ? ' rrp-stat-active' : '') + '" data-stat-filter="all">' +
          '<span class="rrp-stat-value">' + all.length + '</span><span class="rrp-stat-label">Total</span>' +
        '</button>' +
        '<button class="rrp-stat-card rrp-stat-warn'  + (activeFilter === 'unassigned' ? ' rrp-stat-active' : '') + '" data-stat-filter="unassigned">' +
          '<span class="rrp-stat-value">' + unassigned + '</span><span class="rrp-stat-label">Needs Assignment</span>' +
        '</button>' +
        '<button class="rrp-stat-card rrp-stat-blue'  + (activeFilter === 'inreview'   ? ' rrp-stat-active' : '') + '" data-stat-filter="inreview">' +
          '<span class="rrp-stat-value">' + inReview   + '</span><span class="rrp-stat-label">In Review</span>' +
        '</button>' +
        '<button class="rrp-stat-card rrp-stat-green' + (activeFilter === 'approved'   ? ' rrp-stat-active' : '') + '" data-stat-filter="approved">' +
          '<span class="rrp-stat-value">' + approved   + '</span><span class="rrp-stat-label">Approved</span>' +
        '</button>';

      document.querySelectorAll('[data-stat-filter]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var f = btn.getAttribute('data-stat-filter');
          renderSubmissionList(all, summary, f, activeDateRange);
          document.querySelectorAll('[data-stat-filter]').forEach(function (b) {
            b.classList.toggle('rrp-stat-active', b.getAttribute('data-stat-filter') === f);
          });
        });
      });

      renderSubmissionList(all, summary, activeFilter, activeDateRange);

    }).catch(function () {
      document.getElementById('rrp-coord-stats').innerHTML = '<div class="rrp-error">Unable to load submissions.</div>';
    });

    function renderSubmissionList(all, summary, filter, dateRange) {
      dateRange = dateRange || 'all';
      var filtered = all;
      if (filter === 'unassigned') {
        filtered = all.filter(function (s) {
          var sum = summary[s.id];
          if (!sum) return true;
          return !(sum.reviewStages || []).some(function (st) { return (st.reviewers || []).length > 0; });
        });
      } else if (filter === 'inreview') {
        filtered = all.filter(function (s) {
          var st = (s.status || '').toLowerCase();
          return st !== 'draft' && st !== 'approved' && st !== 'rejected' && st !== 'submitted';
        });
      } else if (filter === 'approved') {
        filtered = all.filter(function (s) { return isApprovedStatus(s.status); });
      }
      filtered = applyDateRange(filtered, dateRange);
      filtered = sortSubmissions(filtered);

      var heading = filter === 'unassigned' ? 'Needs Assignment' :
                    filter === 'inreview'   ? 'In Review' :
                    filter === 'approved'   ? 'Approved' : 'All Submissions';

      var listEl = document.getElementById('rrp-coord-submissions');
      if (!listEl) return;

      if (filtered.length === 0) {
        listEl.innerHTML = dateRangeBar(dateRange) + '<h2>' + heading + '</h2><div class="rrp-empty-state"><p>No submissions match this filter.</p></div>';
        listEl.querySelectorAll('[data-daterange]').forEach(function (b) {
          b.addEventListener('click', function () { activeDateRange = b.getAttribute('data-daterange'); renderSubmissionList(all, summary, filter, activeDateRange); });
        });
        return;
      }

      listEl.innerHTML =
        dateRangeBar(dateRange) +
        '<h2>' + heading + ' <span class="rrp-count-badge">' + filtered.length + '</span></h2>' +
        '<ul class="rrp-list rrp-submissions-list">' +
        filtered.map(function (s) {
          var sum = summary[s.id];
          var hasAssignment = sum && (sum.reviewStages || []).some(function (rs) { return (rs.reviewers || []).length > 0; });
          var isFinal = isApprovedStatus(s.status) || (s.status || '').toLowerCase() === 'rejected';
          var firstReviewer = '';
          var currentStage  = '';
          if (!isFinal && sum && (sum.reviewStages || []).length) {
            var _activeC = getActiveStage(sum.reviewStages);
            if (_activeC) {
              currentStage = _activeC.stageName;
              if ((_activeC.reviewers || []).length) {
                firstReviewer = _activeC.reviewers[0].name || _activeC.reviewers[0].email;
              }
            }
          }
          return '<li class="rrp-sub-item">' +
            '<div class="rrp-sub-item-header">' +
              '<strong>' + escapeHtml(s.title || 'Untitled') + '</strong>' +
              '<span class="rrp-decision-badge ' + statusBadgeCls(s.status) + '">' + escapeHtml(s.status || 'Submitted') + '</span>' +
            '</div>' +
            '<div class="rrp-sub-item-meta">' +
              '<span class="rrp-meta-id"><span class="rrp-meta-lbl">ID</span>' + escapeHtml(s.id) + '</span>' +
              '<span><span class="rrp-meta-lbl">Category</span>' + escapeHtml(typeLabel(s.submissionType || s.type)) + '</span>' +
              '<span><span class="rrp-meta-lbl">Submitted by</span>' + escapeHtml(s.submitterName || s.submitterEmail || '\u2014') + '</span>' +
              (s.createdAt ? '<span><span class="rrp-meta-lbl">Date</span>' + new Date(s.createdAt).toLocaleDateString() + '</span>' : '') +
            '</div>' +
            (isFinal
              ? '<div class="rrp-sub-item-review-info"><span class="rrp-review-complete">&#10003; Workflow complete</span></div>'
              : (firstReviewer || !hasAssignment
                  ? '<div class="rrp-sub-item-review-info">' +
                      (firstReviewer
                        ? '<span>&#128100; <span class="rrp-meta-lbl">Reviewer &mdash;</span> ' + escapeHtml(firstReviewer) + '</span>' +
                          (currentStage ? '<span>&#128196; <span class="rrp-meta-lbl">Stage &mdash;</span> ' + escapeHtml(currentStage) + '</span>' : '')
                        : '<span class="rrp-review-unassigned">&#9888; Not yet assigned</span>') +
                    '</div>'
                  : '')) +
            '<div class="rrp-sub-item-actions">' +
              '<button type="button" class="rrp-btn secondary" data-detail="' + escapeHtml(s.id) + '">View</button>' +
              '<button type="button" class="rrp-btn' + (hasAssignment ? ' secondary' : '') + '" data-assign="' + escapeHtml(s.id) + '">' +
                (hasAssignment ? '&#9998; Edit Assignment' : '&#43; Assign Reviewers') +
              '</button>' +
              '<button type="button" class="rrp-btn secondary rrp-btn-log" data-audit-log="' + escapeHtml(s.id) + '">&#128221; Log</button>' +
            '</div>' +
          '</li>';
        }).join('') +
        '</ul>';

      listEl.querySelectorAll('[data-detail]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          renderSubmissionDetail(btn.getAttribute('data-detail'), container, function () { renderCoordinatorDashboard(container); });
        });
      });
      listEl.querySelectorAll('[data-audit-log]').forEach(function (btn) {
        btn.addEventListener('click', function () { openAuditLogModal(btn.getAttribute('data-audit-log')); });
      });
      listEl.querySelectorAll('[data-assign]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var subId = btn.getAttribute('data-assign');
          var sub   = all.find(function (s) { return s.id === subId; });
          openAssignPanel(sub, summary[subId]);
        });
      });
      listEl.querySelectorAll('[data-daterange]').forEach(function (btn) {
        btn.addEventListener('click', function () { activeDateRange = btn.getAttribute('data-daterange'); renderSubmissionList(all, summary, filter, activeDateRange); });
      });
    }

    function openAssignPanel(sub, summaryEntry) {
      var panel = document.getElementById('rrp-coord-assign-panel');
      panel.style.display = '';
      panel.innerHTML = '<p class="rrp-loading">Loading reviewers&hellip;</p>';
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

      api('GET', '/reviewers')
        .then(function (res) {
          var reviewers = res.reviewers || [];
          var stages    = stagesForSub(sub);
          var existing  = {};
          ((summaryEntry && summaryEntry.reviewStages) || []).forEach(function (rs) {
            existing[rs.stageName] = (rs.reviewers || []).map(function (r) { return r.email; });
          });

          panel.innerHTML =
            '<div class="rrp-assign-panel-inner">' +
              '<div class="rrp-assign-panel-header">' +
                '<h2>Assign Reviewers &mdash; <span style="font-weight:400;">' + escapeHtml(sub.title || sub.id) + '</span></h2>' +
                '<div class="rrp-assign-panel-meta">' +
                  '<span><strong>ID:</strong> ' + escapeHtml(sub.id) + '</span>' +
                  '<span><strong>Type:</strong> ' + escapeHtml(typeLabel(sub.submissionType || sub.type) || '\u2014') + '</span>' +
                  '<span><strong>Submitted by:</strong> ' + escapeHtml(sub.submitterName || sub.submitterEmail || '\u2014') + '</span>' +
                  '<span><strong>Status:</strong> ' + escapeHtml(sub.status || '\u2014') + '</span>' +
                '</div>' +
              '</div>' +
              '<div id="rrp-assign-stages">' +
                stages.map(function (stageName) {
                  var currentEmails = existing[stageName] || [];
                  return '<div class="rrp-assign-stage" data-stage="' + escapeHtml(stageName) + '">' +
                    '<h3 class="rrp-assign-stage-title">' + escapeHtml(stageName) + '</h3>' +
                    '<div class="rrp-assign-reviewer-grid">' +
                    reviewers.map(function (r) {
                      var checked = currentEmails.indexOf(r.email) !== -1;
                      return '<label class="rrp-reviewer-chip' + (checked ? ' rrp-reviewer-chip-checked' : '') + '">' +
                        '<input type="checkbox" value="' + escapeHtml(r.email) + '" data-name="' + escapeHtml(r.name || r.email) + '" data-id="' + escapeHtml(r.id || '') + '"' + (checked ? ' checked' : '') + '>' +
                        '<span>' + escapeHtml(r.name || r.email) + '<small>' + escapeHtml(r.email) + '</small></span>' +
                      '</label>';
                    }).join('') +
                    (reviewers.length === 0 ? '<p style="color:var(--rrp-text-muted);font-size:.85rem;">No reviewers in pool for this submission type.<br>Add reviewers via <em>Manage Reviewer Pool</em> or update <code>data/reviewers.json</code>.</p>' : '') +
                    '</div>' +
                  '</div>';
                }).join('') +
              '</div>' +
              '<div class="rrp-assign-panel-actions">' +
                '<button type="button" class="rrp-btn" id="rrp-assign-save-btn">&#10003; Save Assignment</button>' +
                '<button type="button" class="rrp-btn secondary" id="rrp-assign-cancel-btn">Cancel</button>' +
                '<span id="rrp-assign-msg" style="margin-left:1rem;"></span>' +
              '</div>' +
            '</div>';

          // Toggle chip style on check
          panel.querySelectorAll('.rrp-reviewer-chip input').forEach(function (cb) {
            cb.addEventListener('change', function () {
              cb.closest('.rrp-reviewer-chip').classList.toggle('rrp-reviewer-chip-checked', cb.checked);
            });
          });

          document.getElementById('rrp-assign-cancel-btn').addEventListener('click', function () {
            panel.style.display = 'none';
            panel.innerHTML = '';
          });

          document.getElementById('rrp-assign-save-btn').addEventListener('click', function () {
            var msgEl  = document.getElementById('rrp-assign-msg');
            var stageData = [];
            panel.querySelectorAll('[data-stage]').forEach(function (stageEl) {
              var stageName = stageEl.getAttribute('data-stage');
              var selected  = [];
              stageEl.querySelectorAll('input[type=checkbox]:checked').forEach(function (cb) {
                selected.push({ id: cb.getAttribute('data-id'), name: cb.getAttribute('data-name'), email: cb.value });
              });
              stageData.push({ stageName: stageName, reviewers: selected });
            });

            msgEl.innerHTML = '<span class="rrp-loading">Saving\u2026</span>';
            var saveBtn = document.getElementById('rrp-assign-save-btn');
            saveBtn.disabled = true;

            api('PATCH', '/submissions/' + encodeURIComponent(sub.id), { reviewStages: stageData })
              .then(function () {
                msgEl.innerHTML = '<span class="rrp-success">Assignment saved.</span>';
                // Notify reviewers if new ones were added
                return api('POST', '/submissions/' + encodeURIComponent(sub.id) + '/skip-stage', { notifyOnly: true }).catch(function () {});
              })
              .then(function () {
                setTimeout(function () {
                  panel.style.display = 'none';
                  panel.innerHTML = '';
                  renderCoordinatorDashboard(container, activeFilter);
                }, 1200);
              })
              .catch(function (err) {
                saveBtn.disabled = false;
                msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err.data && err.data.error) || 'Save failed.') + '</span>';
              });
          });
        })
        .catch(function () {
          panel.innerHTML = '<div class="rrp-error">Unable to load reviewer list.</div>';
        });
    }

    // ── Extension Requests panel (coordinator / admin) ────────────────────
    function renderExtensionRequests(container, backFn) {
      container.innerHTML =
        '<div class="rrp-mgmt-page-header">' +
          '<button type="button" class="rrp-btn secondary" id="rrp-ext-panel-back">&#8592; Back</button>' +
          '<h1>&#128197; Extension Requests</h1>' +
        '</div>' +
        '<div id="rrp-ext-panel-body"><p class="rrp-loading">Loading&hellip;</p></div>';

      document.getElementById('rrp-ext-panel-back').addEventListener('click', function () {
        if (backFn) backFn(); else renderCoordinatorDashboard(container);
      });

      function loadRequests() {
        return api('GET', '/extension-requests').then(function (res) {
          var list = res.extensionRequests || [];
          var el   = document.getElementById('rrp-ext-panel-body');
          if (!el) return;
          if (!list.length) {
            el.innerHTML = '<p style="color:var(--rrp-text-muted);">No extension requests on record.</p>';
            return;
          }
          var pending = list.filter(function (r) { return r.request.status === 'pending'; });
          var others  = list.filter(function (r) { return r.request.status !== 'pending'; });

          function rowHtml(item) {
            var req = item.request;
            var statusBadge = req.status === 'pending'
              ? '<span class="rrp-decision-badge" style="background:#f0a000;color:#fff;">Pending</span>'
              : req.status === 'approved'
                ? '<span class="rrp-decision-badge rrp-dec-approved">Approved</span>'
                : '<span class="rrp-decision-badge rrp-dec-rejected">Denied</span>';
            var actionBtns = req.status === 'pending'
              ? '<button type="button" class="rrp-btn" data-approve-sub="' + escapeHtml(item.submissionId) + '" data-approve-req="' + escapeHtml(req.id) + '" style="margin-right:.4rem;">&#10003; Approve</button>' +
                '<button type="button" class="rrp-btn secondary" data-deny-sub="' + escapeHtml(item.submissionId) + '" data-deny-req="' + escapeHtml(req.id) + '">&#10007; Deny</button>'
              : '';
            return '<li class="rrp-sub-item">' +
              '<div class="rrp-sub-item-header">' +
                '<strong>' + escapeHtml(item.title || item.submissionId) + '</strong>' +
                statusBadge +
              '</div>' +
              '<div class="rrp-sub-item-meta">' +
                '<span>' + escapeHtml(item.submissionId) + '</span>' +
                '<span>Stage: ' + escapeHtml(item.stageName) + '</span>' +
                '<span>By: ' + escapeHtml(req.requestedBy || '') + '</span>' +
                '<span>+' + (req.requestedDays || '?') + ' days</span>' +
              '</div>' +
              '<div style="margin:.4rem 0;font-size:.9em;color:var(--rrp-text-muted);">' + escapeHtml(req.reason || '') + '</div>' +
              (actionBtns ? '<div style="margin-top:.5rem;">' + actionBtns + '<span class="rrp-ext-action-msg" style="margin-left:.5rem;font-size:.9em;"></span></div>' : '') +
            '</li>';
          }

          el.innerHTML =
            (pending.length ? '<h3 style="margin-top:0;">Pending (' + pending.length + ')</h3><ul class="rrp-list rrp-submissions-list">' + pending.map(rowHtml).join('') + '</ul>' : '') +
            (others.length  ? '<h3>Resolved (' + others.length + ')</h3><ul class="rrp-list rrp-submissions-list">' + others.map(rowHtml).join('') + '</ul>' : '');

          // Wire approve buttons
          el.querySelectorAll('[data-approve-sub]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              var subId  = btn.getAttribute('data-approve-sub');
              var reqId  = btn.getAttribute('data-approve-req');
              var msgEl  = btn.parentNode.querySelector('.rrp-ext-action-msg');
              btn.disabled = true;
              if (msgEl) msgEl.textContent = 'Approving\u2026';
              api('POST', '/submissions/' + encodeURIComponent(subId) + '/extension-requests/' + encodeURIComponent(reqId) + '/approve', {})
                .then(function () { loadRequests(); })
                .catch(function (err) {
                  btn.disabled = false;
                  if (msgEl) msgEl.textContent = (err && err.data && err.data.error) || 'Failed.';
                });
            });
          });

          // Wire deny buttons
          el.querySelectorAll('[data-deny-sub]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              var subId  = btn.getAttribute('data-deny-sub');
              var reqId  = btn.getAttribute('data-deny-req');
              var msgEl  = btn.parentNode.querySelector('.rrp-ext-action-msg');
              var reason = window.prompt('Reason for denial (optional):') || '';
              btn.disabled = true;
              if (msgEl) msgEl.textContent = 'Denying\u2026';
              api('POST', '/submissions/' + encodeURIComponent(subId) + '/extension-requests/' + encodeURIComponent(reqId) + '/deny', { reason: reason })
                .then(function () { loadRequests(); })
                .catch(function (err) {
                  btn.disabled = false;
                  if (msgEl) msgEl.textContent = (err && err.data && err.data.error) || 'Failed.';
                });
            });
          });
        }).catch(function () {
          var el = document.getElementById('rrp-ext-panel-body');
          if (el) el.innerHTML = '<div class="rrp-error">Unable to load extension requests.</div>';
        });
      }
      loadRequests();
    }

    function renderOverdue(container) {
      container.innerHTML =
        '<h1>Overdue Submissions</h1>' +
        '<button type="button" class="rrp-btn secondary" style="margin-bottom:1rem;" data-back>&#8592; Back</button>' +
        '<div id="rrp-overdue-content"><p class="rrp-loading">Loading&hellip;</p></div>';
      container.querySelector('[data-back]').addEventListener('click', function () { renderCoordinatorDashboard(container); });
      api('GET', '/analytics/overdue')
        .then(function (res) {
          var list = res.overdue || [];
          var el   = document.getElementById('rrp-overdue-content');
          if (!list.length) { el.innerHTML = '<p>No overdue submissions. &#127881;</p>'; return; }
          el.innerHTML =
            '<ul class="rrp-list rrp-submissions-list">' +
            list.map(function (s) {
              return '<li class="rrp-sub-item">' +
                '<div class="rrp-sub-item-header">' +
                  '<strong>' + escapeHtml(s.title || s.id) + '</strong>' +
                  '<span class="rrp-decision-badge rrp-dec-rejected">Overdue</span>' +
                '</div>' +
                '<div class="rrp-sub-item-meta">' +
                  '<span>' + escapeHtml(s.id) + '</span>' +
                  '<span>' + escapeHtml(typeLabel(s.submissionType || s.type)) + '</span>' +
                  '<span>' + escapeHtml(s.submitterEmail || '') + '</span>' +
                '</div>' +
                '<button type="button" class="rrp-btn secondary" data-detail="' + escapeHtml(s.id) + '">View</button>' +
                '<button type="button" class="rrp-btn secondary rrp-btn-log" data-audit-log="' + escapeHtml(s.id) + '">&#128221; Log</button>' +
              '</li>';
            }).join('') +
            '</ul>';
          el.querySelectorAll('[data-detail]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              renderSubmissionDetail(btn.getAttribute('data-detail'), container, function () { renderCoordinatorDashboard(container); });
            });
          });
          el.querySelectorAll('[data-audit-log]').forEach(function (btn) {
            btn.addEventListener('click', function () { openAuditLogModal(btn.getAttribute('data-audit-log')); });
          });
        })
        .catch(function () {
          document.getElementById('rrp-overdue-content').innerHTML = '<div class="rrp-error">Unable to load overdue data.</div>';
        });
    }

    // ── COI Declarations panel (coordinator / admin) ──────────────────────
    function renderCOIPanel(container, backFn) {
      container.innerHTML =
        '<div class="rrp-mgmt-page-header">' +
          '<button type="button" class="rrp-btn secondary" id="rrp-coi-panel-back">&#8592; Back</button>' +
          '<h1>&#9888;&#65039; Conflict of Interest Declarations</h1>' +
        '</div>' +
        '<div id="rrp-coi-panel-body"><p class="rrp-loading">Loading&hellip;</p></div>';

      document.getElementById('rrp-coi-panel-back').addEventListener('click', function () {
        if (backFn) backFn(); else renderCoordinatorDashboard(container);
      });

      api('GET', '/conflicts')
        .then(function (res) {
          var list = res.conflicts || [];
          var el   = document.getElementById('rrp-coi-panel-body');
          if (!list.length) {
            el.innerHTML = '<p style="color:var(--rrp-text-muted);">No conflict of interest declarations on record.</p>';
            return;
          }
          el.innerHTML =
            '<p style="color:var(--rrp-text-muted);margin-bottom:1rem;">Showing ' + list.length + ' declaration(s). Reviewers with declared COIs should be re-assigned.</p>' +
            '<table class="rrp-table" style="width:100%;border-collapse:collapse;">' +
              '<thead><tr>' +
                '<th style="text-align:left;padding:.5rem .75rem;border-bottom:2px solid #dde5f2;">Submission ID</th>' +
                '<th style="text-align:left;padding:.5rem .75rem;border-bottom:2px solid #dde5f2;">Reviewer Email</th>' +
                '<th style="text-align:left;padding:.5rem .75rem;border-bottom:2px solid #dde5f2;">Reason</th>' +
                '<th style="text-align:left;padding:.5rem .75rem;border-bottom:2px solid #dde5f2;">Declared</th>' +
                '<th style="text-align:left;padding:.5rem .75rem;border-bottom:2px solid #dde5f2;">Action</th>' +
              '</tr></thead>' +
              '<tbody>' +
              list.map(function (c) {
                var declaredDate = c.declaredAt ? new Date(c.declaredAt).toLocaleDateString() : '\u2014';
                return '<tr style="border-bottom:1px solid #eef1f8;">' +
                  '<td style="padding:.5rem .75rem;font-family:monospace;">' + escapeHtml(c.submissionId || '\u2014') + '</td>' +
                  '<td style="padding:.5rem .75rem;">' + escapeHtml(c.reviewerEmail || '\u2014') + '</td>' +
                  '<td style="padding:.5rem .75rem;">' + escapeHtml(c.reason || '\u2014') + '</td>' +
                  '<td style="padding:.5rem .75rem;white-space:nowrap;">' + declaredDate + '</td>' +
                  '<td style="padding:.5rem .75rem;">' +
                    (c.submissionId
                      ? '<button type="button" class="rrp-btn secondary" style="padding:.25rem .6rem;font-size:.8rem;" data-detail="' + escapeHtml(c.submissionId) + '">View Submission</button>'
                      : '') +
                  '</td>' +
                '</tr>';
              }).join('') +
              '</tbody>' +
            '</table>';

          el.querySelectorAll('[data-detail]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              renderSubmissionDetail(btn.getAttribute('data-detail'), container, backFn || function () { renderCoordinatorDashboard(container); });
            });
          });
        })
        .catch(function () {
          document.getElementById('rrp-coi-panel-body').innerHTML = '<div class="rrp-error">Unable to load COI declarations.</div>';
        });
    }
  }

  function renderSelection(container) {
    if (!isLoggedIn) {
      container.innerHTML =
        '<div class="rrp-user-banner rrp-user-banner-guest">' +
          '<span>You are not logged in. Please <a href="' + escapeHtml(loginUrl) + '">log in</a> to submit and track your research.</span>' +
          '<a class="rrp-btn" href="' + escapeHtml(loginUrl) + '">Log in</a>' +
        '</div>' +
        '<h1>Research Submission Process</h1>' +
        '<p class="rrp-info">Select a submission type below to view the process details.</p>' +
        '<p class="rrp-info">If you do not have an account, contact your administrator.</p>' +
        '<div style="margin-top:1rem;"><a class="rrp-btn" href="/">View process documentation</a></div>';
      return;
    }

    // Logged in but no portal role assigned yet — show a holding page.
    // Do NOT expose submission tiles or nav links until an admin grants a role.
    var userRolesNow = (Array.isArray(window.RRP.userRoles) && window.RRP.userRoles.length)
      ? window.RRP.userRoles
      : (window.RRP.userRole ? [window.RRP.userRole] : []);
    if (userRolesNow.length === 0) {
      container.innerHTML =
        '<div class="rrp-user-banner rrp-user-banner-loggedin">' +
          '<span>Logged in as <strong>' + escapeHtml(window.RRP.userName || 'Unknown') + '</strong></span>' +
          '<a href="' + escapeHtml(logoutUrl) + '" class="rrp-btn secondary">Logout</a>' +
        '</div>' +
        '<div class="rrp-card" style="max-width:520px;margin:3rem auto;text-align:center;padding:2.5rem;">' +
          '<div style="font-size:3rem;margin-bottom:1rem;">&#128274;</div>' +
          '<h2 style="margin-bottom:.75rem;">Account Pending Approval</h2>' +
          '<p style="color:var(--rrp-text-muted);line-height:1.6;">Your account has been created but a portal role has not been assigned yet.</p>' +
          '<p style="color:var(--rrp-text-muted);margin-top:.75rem;">Please contact your administrator to be granted access.</p>' +
        '</div>';
      return;
    }

    var headerBanner = '<div class="rrp-user-banner rrp-user-banner-loggedin">' +
      '<span>Logged in as <strong>' + escapeHtml(window.RRP.userName || 'Unknown') + '</strong> (' + escapeHtml((Array.isArray(window.RRP.userRoles) && window.RRP.userRoles.length ? window.RRP.userRoles.join(' \xb7 ') : window.RRP.userRole) || 'User') + ')</span>' +
      '<a href="' + escapeHtml(logoutUrl) + '" class="rrp-btn secondary">Logout</a>' +
      '</div>';

    container.innerHTML = headerBanner +
      '<h1>Research Review Portal</h1>' +
      '<p style="color: var(--rrp-text-muted); margin-bottom: 1rem;">Choose a submission type to get started.</p>' +
      '<div class="rrp-type-cards" id="rrp-type-cards"></div>' +
      '<div class="rrp-view-toggle" style="margin-top: 1.5rem;">' +
        '<button type="button" class="rrp-btn secondary" data-view="submit">Submit new abstract</button>' +
        '<button type="button" class="rrp-btn secondary" data-view="status">Check my submissions</button>' +
        '<button type="button" class="rrp-btn secondary" data-view="dashboard">Dashboard</button>' +
        '<button type="button" class="rrp-btn secondary" data-view="analytics">Analytics</button>' +
        '<button type="button" class="rrp-btn secondary" data-view="reviewer">Reviewer Dashboard</button>' +
        '<button type="button" class="rrp-btn secondary" data-view="public">Research & symposium (public)</button>' +
      '</div>';

    var cards = document.getElementById('rrp-type-cards');
    SUBMISSION_TYPES.forEach(function (t) {
      var div = document.createElement('div');
      div.className = 'rrp-type-card';
      div.dataset.type = t.id;
      div.innerHTML = '<h3>' + escapeHtml(t.title) + '</h3><p>' + escapeHtml(t.subtitle) + '</p>';
      div.addEventListener('click', function () {
        container.querySelectorAll('.rrp-type-card').forEach(function (c) { c.classList.remove('selected'); });
        div.classList.add('selected');
        container.dataset.selectedType = t.id;
      });
      cards.appendChild(div);
    });

    container.querySelectorAll('.rrp-view-toggle [data-view]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var view = btn.dataset.view;
        if (view === 'submit') {
          var type = container.dataset.selectedType || 'conference';
          renderForm(container, type);
        } else if (view === 'status') {
          renderStatus(container);
        } else if (view === 'dashboard') {
          renderDashboard(container);
        } else if (view === 'analytics') {
          renderAnalytics(container);
        } else if (view === 'reviewer') {
          renderReviewerDashboard(container);
        } else {
          renderPublic(container);
        }
      });
    });

    var startMode = getQueryParam('start');
    if (startMode) {
      if (['submit', 'status', 'dashboard', 'analytics', 'reviewer', 'public'].indexOf(startMode) !== -1) {
        container.querySelector('.rrp-view-toggle [data-view="' + startMode + '"]').click();
        return;
      }
      if (SUBMISSION_TYPES.some(function (t) { return t.id === startMode; })) {
        var selectedCard = container.querySelector('.rrp-type-card[data-type="' + startMode + '"]');
        if (selectedCard) {
          selectedCard.click();
        }
      }
    }

    // select first type by default
    if (!container.dataset.selectedType) {
      var firstCard = container.querySelector('.rrp-type-card');
      if (firstCard) {
        firstCard.click();
      }
    }
  }

  function renderForm(container, type) {
    var apiType = typeToApi[type] || type;
    var typeInfo = SUBMISSION_TYPES.find(function (t) { return t.id === type; }) || { title: type };
    var draftKey = 'rrp_draft_' + apiType;
    var savedDraft = null;
    try { savedDraft = JSON.parse(localStorage.getItem(draftKey) || 'null'); } catch (e) {}

    container.innerHTML =
      '<h1>Submit: ' + escapeHtml(typeInfo.title) + '</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom: 1rem;" data-back>← Back</button>' +
      (savedDraft ? '<div class="rrp-draft-notice"><strong>Draft restored</strong> – unsaved work from a previous visit. <button type="button" class="rrp-btn secondary rrp-btn-sm" id="rrp-clear-draft">Clear</button></div>' : '') +
      '<div id="rrp-form-errors"></div>' +
      '<form id="rrp-submit-form" novalidate>' +
        '<div class="rrp-form-block"><label>Name *</label><input type="text" name="submitterName" required autocomplete="name"></div>' +
        '<div class="rrp-form-block"><label>Email *</label><input type="email" name="submitterEmail" required autocomplete="email"></div>' +
        '<div class="rrp-form-block"><label>Affiliation *</label><input type="text" name="affiliation" required></div>' +
        '<div class="rrp-form-block"><label>Title * <span class="rrp-hint">(max 200 characters)</span></label>' +
          '<input type="text" name="title" required maxlength="200"><span class="rrp-counter" id="rrp-title-counter">0 / 200</span></div>' +
        '<div class="rrp-form-block"><label>Abstract * <span class="rrp-hint">' + (apiType === 'conference' ? '250–500 words recommended' : 'required') + '</span></label>' +
          '<textarea name="abstract" rows="6" required></textarea><span class="rrp-counter" id="rrp-abstract-counter">0 words</span></div>' +
        '<div class="rrp-form-block"><label>Keywords * <span class="rrp-hint">(3–5, comma-separated)</span></label>' +
          '<input type="text" name="keywords" required><span class="rrp-counter" id="rrp-keywords-counter">0 keywords</span></div>' +
        '<div class="rrp-form-block"><label>Research area / Category *</label><input type="text" name="researchArea" required></div>' +
        (apiType === 'conference' ? '<div class="rrp-form-block"><label>Presentation preference *</label><select name="presentationPreference"><option value="">— select —</option><option value="oral">Oral</option><option value="poster">Poster</option></select></div>' : '') +
        (apiType === 'publication' ? '<div class="rrp-form-block"><label>Publication type *</label><input type="text" name="publicationType" required></div>' : '') +
        (apiType === 'student-project' ? '<div class="rrp-form-block"><label>Project type *</label><input type="text" name="projectType" required></div>' : '') +
        (apiType === 'grant' ? '<div class="rrp-form-block"><label>Funding agency *</label><input type="text" name="fundingAgency" required></div>' : '') +
        '<div class="rrp-form-block"><label>Supporting files <span class="rrp-hint">(optional &#8211; PDF or DOCX only &#8211; max 2 MB each, up to 5)</span></label>' +
          '<input type="file" name="files" id="rrp-file-input" multiple accept=".pdf,.docx">' +
          '<div id="rrp-file-list" class="rrp-file-list"></div>' +
        '</div>' +
        '<div class="rrp-form-actions">' +
          '<button type="submit" class="rrp-btn" id="rrp-submit-btn">Submit</button>' +
          '<button type="button" class="rrp-btn secondary" id="rrp-draft-btn">Save draft</button>' +
          '<button type="button" class="rrp-btn secondary" id="rrp-preview-btn">Preview</button>' +
        '</div>' +
      '</form>' +
      '<div id="rrp-preview-panel" style="display:none;" class="rrp-preview-panel"></div>';

    container.querySelector('[data-back]').addEventListener('click', function () { renderSelection(container); });

    var form = document.getElementById('rrp-submit-form');

    // Restore draft values
    if (savedDraft) {
      Object.keys(savedDraft).forEach(function (k) {
        var el = form.elements[k];
        if (el) el.value = savedDraft[k] || '';
      });
    }

    // Clear draft button
    var clearBtn = document.getElementById('rrp-clear-draft');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        localStorage.removeItem(draftKey);
        var notice = container.querySelector('.rrp-draft-notice');
        if (notice) notice.remove();
      });
    }

    // Live counters
    var titleInput = form.querySelector('[name="title"]');
    var titleCounter = document.getElementById('rrp-title-counter');
    titleInput.addEventListener('input', function () {
      var len = titleInput.value.length;
      titleCounter.textContent = len + ' / 200';
      titleCounter.className = 'rrp-counter' + (len > 190 ? ' rrp-counter-warn' : (len > 0 ? ' rrp-counter-ok' : ''));
    });
    if (savedDraft && savedDraft.title) titleInput.dispatchEvent(new Event('input'));

    var abstractEl = form.querySelector('[name="abstract"]');
    var abstractCounter = document.getElementById('rrp-abstract-counter');
    abstractEl.addEventListener('input', function () {
      var words = abstractEl.value.trim() ? abstractEl.value.trim().split(/\s+/).length : 0;
      abstractCounter.textContent = words + ' word' + (words !== 1 ? 's' : '');
      var ok = apiType !== 'conference' || (words >= 250 && words <= 500);
      abstractCounter.className = 'rrp-counter' + (!ok && words > 0 ? ' rrp-counter-warn' : (ok && words > 0 ? ' rrp-counter-ok' : ''));
    });
    if (savedDraft && savedDraft.abstract) abstractEl.dispatchEvent(new Event('input'));

    var keywordsEl = form.querySelector('[name="keywords"]');
    var keywordsCounter = document.getElementById('rrp-keywords-counter');
    keywordsEl.addEventListener('input', function () {
      var kws = keywordsEl.value.split(',').map(function (k) { return k.trim(); }).filter(Boolean);
      keywordsCounter.textContent = kws.length + ' keyword' + (kws.length !== 1 ? 's' : '');
      keywordsCounter.className = 'rrp-counter' + (kws.length > 0 && (kws.length < 3 || kws.length > 5) ? ' rrp-counter-warn' : (kws.length >= 3 && kws.length <= 5 ? ' rrp-counter-ok' : ''));
    });
    if (savedDraft && savedDraft.keywords) keywordsEl.dispatchEvent(new Event('input'));

    // File list preview
    var fileInput = document.getElementById('rrp-file-input');
    var fileListEl = document.getElementById('rrp-file-list');
    fileInput.addEventListener('change', function () {
      var files = Array.from(fileInput.files || []);
      if (files.length > 5) {
        fileListEl.innerHTML = '<div class="rrp-error">Maximum 5 files allowed.</div>';
        return;
      }
      fileListEl.innerHTML = files.map(function (f) {
        var mb = (f.size / 1024 / 1024).toFixed(2);
        var ok = f.size <= 2 * 1024 * 1024;
        return '<div class="rrp-file-item' + (!ok ? ' rrp-file-item-warn' : '') + '">' +
          escapeHtml(f.name) + ' (' + mb + ' MB)' + (!ok ? ' ⚠ exceeds 2 MB limit' : '') + '</div>';
      }).join('');
    });

    // Save draft
    document.getElementById('rrp-draft-btn').addEventListener('click', function () {
      var fd = new FormData(form);
      var body = { type: apiType };
      fd.forEach(function (v, k) { if (k !== 'files') body[k] = v; });
      try { localStorage.setItem(draftKey, JSON.stringify(body)); } catch (e) {}
      body.status = 'draft';
      var errEl = document.getElementById('rrp-form-errors');
      api('POST', '/submit', body)
        .then(function (res) {
          errEl.innerHTML = '<div class="rrp-info">Draft saved. Reference: <strong>' + escapeHtml(res.id) + '</strong></div>';
        })
        .catch(function () {
          errEl.innerHTML = '<div class="rrp-info">Draft saved locally.</div>';
        });
    });

    // Preview
    document.getElementById('rrp-preview-btn').addEventListener('click', function () {
      var fd = new FormData(form);
      var body = {};
      fd.forEach(function (v, k) { if (k !== 'files') body[k] = v; });
      var panel = document.getElementById('rrp-preview-panel');
      panel.style.display = '';
      panel.innerHTML =
        '<div class="rrp-preview-header"><h2>Preview</h2>' +
          '<button type="button" class="rrp-btn secondary rrp-btn-sm" id="rrp-close-preview">✕ Close preview</button></div>' +
        '<table class="rrp-preview-table">' +
          Object.keys(body).filter(function (k) { return body[k]; }).map(function (k) {
            return '<tr><th>' + escapeHtml(k) + '</th><td>' + escapeHtml(body[k]) + '</td></tr>';
          }).join('') +
        '</table>';
      document.getElementById('rrp-close-preview').addEventListener('click', function () { panel.style.display = 'none'; });
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Submit
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var errEl = document.getElementById('rrp-form-errors');
      errEl.innerHTML = '';
      var fd = new FormData(form);
      var body = { type: apiType };
      fd.forEach(function (v, k) { if (k !== 'files') body[k] = v; });
      var submitBtn = document.getElementById('rrp-submit-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting…';

      api('POST', '/submit', body)
        .then(function (res) {
          try { localStorage.removeItem(draftKey); } catch (e) {}
          // Upload files if any were selected
          var files = fileInput.files;
          if (files && files.length > 0 && res.id) {
            var fileData = new FormData();
            for (var i = 0; i < files.length; i++) { fileData.append('files[]', files[i]); }
            return fetch(restBase + '/submissions/' + encodeURIComponent(res.id) + '/attachments', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'X-WP-Nonce': nonce },
              body: fileData
            }).then(function () { return res; });
          }
          return res;
        })
        .then(function (res) {
          container.innerHTML =
            '<h1>Submission received</h1>' +
            '<div class="rrp-success">' + escapeHtml(res.message || 'Thank you.') + '</div>' +
            '<p><strong>Reference ID:</strong> <code>' + escapeHtml(res.id) + '</code></p>' +
            '<p>A confirmation email will be sent to your address. Track status under "Check my submissions".</p>' +
            '<button type="button" class="rrp-btn secondary" data-back>← Back to portal</button>';
          container.querySelector('[data-back]').addEventListener('click', function () { renderSelection(container); });
        })
        .catch(function (err) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit';
          var msg = (err.data && err.data.errors && err.data.errors.length) ? err.data.errors.join(' ') : (err.data && err.data.error) || 'Submission failed. Please check all required fields.';
          errEl.innerHTML = '<div class="rrp-error">' + escapeHtml(msg) + '</div>';
        });
    });
  }

  function renderDashboard(container) {
    container.innerHTML =
      '<h1>Dashboard</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom: 1rem;" data-back>← Back</button>' +
      '<div class="rrp-form-block" id="rrp-dashboard-filters" style="margin-bottom: 1rem;"></div>' +
      '<div id="rrp-dashboard-content"><p class="rrp-loading">Loading dashboard…</p></div>';

    container.querySelector('[data-back]').addEventListener('click', function () { renderSelection(container); });

    function applyDashboardFilter(data) {
      var status = document.getElementById('rrp-dashboard-filter-status').value;
      var type = document.getElementById('rrp-dashboard-filter-type').value;
      var items = data.overview.progressSummary || [];
      if (status !== 'all') {
        items = items.filter(function (s) { return (s.status || '').toLowerCase() === status.toLowerCase(); });
      }
      if (type !== 'all') {
        items = items.filter(function (s) { return (s.type || '').toLowerCase() === type.toLowerCase(); });
      }
      return items;
    }

    api('GET', '/dashboard')
      .then(function (data) {
        var c = document.getElementById('rrp-dashboard-content');
        var o = data.overview || {};
        var u = data.user || {};

        var statuses = Object.keys(o.statusCounts || {});
        var types = Object.keys(o.typeCounts || {});

        document.getElementById('rrp-dashboard-filters').innerHTML =
          '<label>Status filter</label><select id="rrp-dashboard-filter-status"><option value="all">All</option>' + statuses.map(function (s) { return '<option value="' + escapeHtml(s) + '">' + escapeHtml(s) + '</option>'; }).join('') + '</select>' +
          '<label>Type filter</label><select id="rrp-dashboard-filter-type"><option value="all">All</option>' + types.map(function (t) { return '<option value="' + escapeHtml(t) + '">' + escapeHtml(t) + '</option>'; }).join('') + '</select>';

        function renderProgressList() {
          var list = applyDashboardFilter(data);
          var rows = list.slice(0, 20).map(function (s) {
            return '<li><strong>' + escapeHtml(s.title || s.id) + '</strong> (' + escapeHtml(s.id) + ') ' +
              '<span class="rrp-status">' + escapeHtml(s.status || '') + '</span> ' +
              '<span>' + (s.progress || 0) + '%</span> ' +
              '<button type="button" class="rrp-btn secondary" data-detail="' + escapeHtml(s.id) + '" style="margin-left:.25rem;">Details</button>' +
              '<button type="button" class="rrp-btn secondary" data-timeline="' + escapeHtml(s.id) + '" style="margin-left:.25rem;">Timeline</button></li>';
          }).join('');
          document.getElementById('rrp-dashboard-progress').innerHTML = '<ul class="rrp-list">' + (rows || '<li>No matching submissions.</li>') + '</ul>';
          document.querySelectorAll('[data-timeline]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              renderTimeline(btn.getAttribute('data-timeline'), container);
            });
          });
          document.querySelectorAll('[data-detail]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              renderSubmissionDetail(btn.getAttribute('data-detail'), container, function () { renderDashboard(container); });
            });
          });
        }

        c.innerHTML =
          '<div class="rrp-dashboard-grid">' +
            '<div><strong>Total submissions:</strong> ' + (o.totalSubmissions || 0) + '</div>' +
            '<div><strong>My submissions:</strong> ' + (u.mySubmissionsCount || 0) + '</div>' +
            '<div><strong>Assigned reviews:</strong> ' + (u.assignedSubmissionsCount || 0) + '</div>' +
            '<div><strong>Pending review:</strong> ' + (u.pendingReviewCount || 0) + '</div>' +
          '</div>' +
          '<div class="rrp-dashboard-section"><h3>Status counts</h3>' +
          '<ul>' + Object.keys(o.statusCounts || {}).map(function (k) { return '<li>' + escapeHtml(k) + ': ' + o.statusCounts[k] + '</li>'; }).join('') + '</ul></div>' +
          '<div class="rrp-dashboard-section"><h3>Type counts</h3>' +
          '<ul>' + Object.keys(o.typeCounts || {}).map(function (k) { return '<li>' + escapeHtml(k) + ': ' + o.typeCounts[k] + '</li>'; }).join('') + '</ul></div>' +
          '<div class="rrp-dashboard-section"><h3>Recent progress</h3><div id="rrp-dashboard-progress"></div></div>' +
          '<div id="rrp-dashboard-notifications"><h3>Loading notifications…</h3></div>';

        renderProgressList();

        document.getElementById('rrp-dashboard-filter-status').addEventListener('change', renderProgressList);
        document.getElementById('rrp-dashboard-filter-type').addEventListener('change', renderProgressList);

        return api('GET', '/notifications');
      })
      .then(function (notifyData) {
        var block = document.getElementById('rrp-dashboard-notifications');
        if (!block) return;
        var note = notifyData.notifications || [];
        if (note.length === 0) {
          block.innerHTML = '<div>No active notifications.</div>';
          return;
        }
        block.innerHTML = '<div><h3>Notifications</h3><ul>' + note.map(function (n) {
          return '<li>' + escapeHtml(n.message) + ' (' + escapeHtml(n.id) + ', ' + escapeHtml(n.status || '') + ')</li>';
        }).join('') + '</ul></div>';
      })
      .catch(function () {
        container.querySelector('#rrp-dashboard-content').innerHTML = '<div class="rrp-error">Unable to fetch dashboard data.</div>';
      });
  }

  function renderStatus(container) {
    container.innerHTML =
      '<h1>Check my submissions</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom: 1rem;" data-back>← Back</button>' +
      '<div class="rrp-form-block rrp-check-email">' +
        '<label>Your email</label>' +
        '<input type="email" id="rrp-status-email" placeholder="submitter@cityu.edu">' +
        '<button type="button" class="rrp-btn" id="rrp-status-fetch">Load my submissions</button>' +
      '</div>' +
      '<div id="rrp-status-list"></div>';

    container.querySelector('[data-back]').addEventListener('click', function () { renderSelection(container); });

    document.getElementById('rrp-status-fetch').addEventListener('click', function () {
      var email = document.getElementById('rrp-status-email').value.trim();
      var listEl = document.getElementById('rrp-status-list');
      if (!email) {
        listEl.innerHTML = '<div class="rrp-error">Enter your email.</div>';
        return;
      }
      listEl.innerHTML = '<p class="rrp-loading">Loading…</p>';
      api('GET', '/submissions')
        .then(function (res) {
          var mine = (res.submissions || []).filter(function (s) {
            return (s.submitterEmail || '').toLowerCase() === email.toLowerCase();
          });
          if (mine.length === 0) {
            listEl.innerHTML = '<p>No submissions found for this email.</p>';
            return;
          }
          listEl.innerHTML = '<h2>Your submissions</h2><ul class="rrp-list">' +
            mine.map(function (s) {
              var isDraft = (s.status || '').toLowerCase() === 'draft';
              return '<li>' +
                '<span><strong>' + escapeHtml(s.title || s.id) + '</strong>' +
                (isDraft ? ' <span class="rrp-decision-badge rrp-dec-pending">Draft</span>' : '') +
                ' · ' + escapeHtml(s.id) + '</span>' +
                '<span class="rrp-status">' + escapeHtml(s.status || '') + '</span>' +
                '<button type="button" class="rrp-btn secondary" style="margin-left:.5rem;" data-detail="' + escapeHtml(s.id) + '">View Details</button>' +
                '<button type="button" class="rrp-btn secondary rrp-btn-log" style="margin-left:.25rem;" data-audit-log="' + escapeHtml(s.id) + '">&#128221; Log</button>' +
                '</li>';
            }).join('') +
          '</ul>';
          listEl.querySelectorAll('[data-detail]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              renderSubmissionDetail(btn.getAttribute('data-detail'), container, function () { renderStatus(container); });
            });
          });
          listEl.querySelectorAll('[data-audit-log]').forEach(function (btn) {
            btn.addEventListener('click', function () { openAuditLogModal(btn.getAttribute('data-audit-log')); });
          });
        })
        .catch(function () {
          listEl.innerHTML = '<div class="rrp-error">Could not load submissions.</div>';
        });
    });
  }

  function renderAnalytics(container, backFn) {
    container.innerHTML =
      '<h1>&#128202; Analytics</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom:1rem;" data-back>&#8592; Back</button>' +
      '<div id="rrp-analytics-content"><p class="rrp-loading">Loading analytics&hellip;</p></div>' +
      '<div class="rrp-analytics-actions" style="margin-top:1rem;">' +
        '<button class="rrp-btn" id="rrp-export-csv">Export CSV</button>' +
        '<button class="rrp-btn" id="rrp-export-xlsx" style="margin-left:0.5rem;">Export XLSX</button>' +
      '</div>';

    container.querySelector('[data-back]').addEventListener('click', function () {
      if (typeof backFn === 'function') backFn(); else renderSelection(container);
    });

    Promise.all([
      api('GET', '/analytics/workflow'),
      api('GET', '/analytics/performance'),
      api('GET', '/analytics/daily').catch(function () { return { dates: [], series: [] }; })
    ]).then(function (results) {
      var w     = results[0];
      var p     = results[1];
      var daily = results[2];
      var el = document.getElementById('rrp-analytics-content');
      if (!el) return;

      // ── Status distribution bar chart ─────────────────────────────────────
      var byStatus = w.totalByStatus || {};
      var byType   = w.totalByType   || {};
      var total    = w.totalSubmissions || 0;

      var STATUS_COLORS = {
        'Submitted':          '#3b82f6',
        'Under Review':       '#f59e0b',
        'Under Initial Review': '#f59e0b',
        'Administrative Review': '#f59e0b',
        'Revision Required':  '#8b5cf6',
        'Revision Submitted': '#a78bfa',
        'Approved':           '#22c55e',
        'Confirmed for Presentation': '#22c55e',
        'Published':          '#22c55e',
        'Approved for Submission': '#22c55e',
        'Rejected':           '#ef4444',
        'Draft':              '#94a3b8'
      };
      function statusColor(s) {
        if (STATUS_COLORS[s]) return STATUS_COLORS[s];
        if (s && s.indexOf(': In Progress') !== -1) return '#f59e0b';
        return DEFAULT_COLOR;
      }
      var DEFAULT_COLOR = '#64748b';

      // Sort statuses by count desc
      var statusEntries = Object.keys(byStatus).sort(function (a, b) { return byStatus[b] - byStatus[a]; });
      var maxCount = statusEntries.length ? byStatus[statusEntries[0]] : 1;

      var barsHtml = statusEntries.map(function (status) {
        var count = byStatus[status];
        var pct   = total > 0 ? Math.round((count / total) * 100) : 0;
        var barW  = maxCount > 0 ? Math.round((count / maxCount) * 100) : 0;
        var color = statusColor(status);
        return '<div class="rrp-chart-row">' +
          '<div class="rrp-chart-label">' + escapeHtml(status) + '</div>' +
          '<div class="rrp-chart-bar-wrap">' +
            '<div class="rrp-chart-bar" style="width:' + barW + '%;background:' + color + ';" title="' + count + ' submissions"></div>' +
          '</div>' +
          '<div class="rrp-chart-value">' + count + ' <span class="rrp-chart-pct">(' + pct + '%)</span></div>' +
        '</div>';
      }).join('') || '<p style="color:var(--rrp-text-muted)">No submissions yet.</p>';

      // ── Submission type breakdown ─────────────────────────────────────────
      var typeEntries = Object.keys(byType).sort(function (a, b) { return byType[b] - byType[a]; });
      var typesHtml = typeEntries.map(function (t) {
        var count = byType[t];
        var pct   = total > 0 ? Math.round((count / total) * 100) : 0;
        var barW  = maxCount > 0 ? Math.round((count / maxCount) * 100) : 0;
        return '<div class="rrp-chart-row">' +
          '<div class="rrp-chart-label">' + escapeHtml(typeLabel(t)) + '</div>' +
          '<div class="rrp-chart-bar-wrap">' +
            '<div class="rrp-chart-bar" style="width:' + barW + '%;background:#0ea5e9;"></div>' +
          '</div>' +
          '<div class="rrp-chart-value">' + count + ' <span class="rrp-chart-pct">(' + pct + '%)</span></div>' +
        '</div>';
      }).join('') || '<p style="color:var(--rrp-text-muted)">No submissions yet.</p>';

      el.innerHTML =
        // ── Line chart: daily submissions by status ─────────────────────────
        '<div class="rrp-analytics-card">' +
          '<h2 class="rrp-analytics-card-title">Daily Submissions by Status</h2>' +
          buildLineChart(daily) +
        '</div>' +

        // ── Chart: Status distribution ──────────────────────────────────────
        '<div class="rrp-analytics-card">' +
          '<h2 class="rrp-analytics-card-title">Submissions by Status</h2>' +
          '<div class="rrp-chart">' + barsHtml + '</div>' +
        '</div>' +

        // ── Chart: By type ──────────────────────────────────────────────────
        '<div class="rrp-analytics-card">' +
          '<h2 class="rrp-analytics-card-title">Submissions by Type</h2>' +
          '<div class="rrp-chart">' + typesHtml + '</div>' +
        '</div>' +

        // ── Summary stat tiles ──────────────────────────────────────────────
        '<div class="rrp-analytics-card">' +
          '<h2 class="rrp-analytics-card-title">Summary</h2>' +
          '<div class="rrp-analytics-tiles">' +
            '<div class="rrp-analytics-tile">' +
              '<div class="rrp-analytics-tile-val">' + total + '</div>' +
              '<div class="rrp-analytics-tile-lbl">Total Submissions</div>' +
            '</div>' +
            '<div class="rrp-analytics-tile rrp-tile-green">' +
              '<div class="rrp-analytics-tile-val">' + (p.finalizedCount || 0) + '</div>' +
              '<div class="rrp-analytics-tile-lbl">Finalized</div>' +
            '</div>' +
            '<div class="rrp-analytics-tile rrp-tile-amber">' +
              '<div class="rrp-analytics-tile-val">' + (p.inProgressCount || 0) + '</div>' +
              '<div class="rrp-analytics-tile-lbl">In Progress</div>' +
            '</div>' +
            '<div class="rrp-analytics-tile rrp-tile-red">' +
              '<div class="rrp-analytics-tile-val">' + (p.lateReviewAlerts || 0) + '</div>' +
              '<div class="rrp-analytics-tile-lbl">Late Alerts</div>' +
            '</div>' +
            '<div class="rrp-analytics-tile">' +
              '<div class="rrp-analytics-tile-val">' + (p.averageTimeToDecisionDays != null ? p.averageTimeToDecisionDays + 'd' : '—') + '</div>' +
              '<div class="rrp-analytics-tile-lbl">Avg. Time to Decision</div>' +
            '</div>' +
            '<div class="rrp-analytics-tile">' +
              '<div class="rrp-analytics-tile-val">' + (w.averageStages || 0) + '</div>' +
              '<div class="rrp-analytics-tile-lbl">Avg. Stages / Submission</div>' +
            '</div>' +
            '<div class="rrp-analytics-tile">' +
              '<div class="rrp-analytics-tile-val">' + (w.meanReviewerLoad || 0) + '</div>' +
              '<div class="rrp-analytics-tile-lbl">Mean Reviewer Load</div>' +
            '</div>' +
          '</div>' +
        '</div>';

      document.getElementById('rrp-export-csv').addEventListener('click', function () {
        api('GET', '/reports/export?type=workflow&format=csv').then(function (resp) {
          var blob = new Blob([atob(resp.content)], { type: 'text/csv' });
          var a = document.createElement('a'); a.href = URL.createObjectURL(blob);
          a.download = resp.filename || 'rrp-workflow.csv'; a.click();
        }).catch(function () { alert('Export failed'); });
      });

      document.getElementById('rrp-export-xlsx').addEventListener('click', function () {
        api('GET', '/reports/export?type=workflow&format=xlsx').then(function (resp) {
          var blob = new Blob([atob(resp.content)], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
          var a = document.createElement('a'); a.href = URL.createObjectURL(blob);
          a.download = resp.filename || 'rrp-workflow.xlsx'; a.click();
        }).catch(function () { alert('Export failed'); });
      });

    }).catch(function () {
      var el = document.getElementById('rrp-analytics-content');
      if (el) el.innerHTML = '<div class="rrp-error">Unable to load analytics. Please login and try again.</div>';
    });
  }

  function renderReviewerDashboard(container, activeFilter) {
    var userName   = (window.RRP && window.RRP.userName)   || 'Reviewer';
    var userEmail  = ((window.RRP && window.RRP.userEmail) || '').toLowerCase();
    activeFilter   = activeFilter || 'all';
    var activeDateRange = 'all';

    container.innerHTML =
      '<div class="rrp-student-banner rrp-reviewer-banner">' +
        '<div class="rrp-student-banner-info">' +
          '<div class="rrp-student-banner-title">Welcome, ' + escapeHtml(userName) + '</div>' +
          '<div class="rrp-student-banner-sub">City University of Seattle &nbsp;&middot;&nbsp; Research Reviewer</div>' +
        '</div>' +
      '</div>' +
      '<div class="rrp-student-layout">' +
        '<div class="rrp-student-main">' +
          '<div id="rrp-reviewer-stats" class="rrp-stats-row"><p class="rrp-loading">Loading&hellip;</p></div>' +
          '<div id="rrp-reviewer-submissions" class="rrp-dashboard-section">' +
            '<h2>Assigned Submissions</h2><p class="rrp-loading">Loading&hellip;</p>' +
          '</div>' +
          '<div id="rrp-reviewer-coi-section" style="margin-top:1.5rem;"></div>' +
        '</div>' +
        '<aside class="rrp-student-aside">' +
          '<div class="rrp-profile-card">' +
            '<div class="rrp-profile-icon">&#128101;</div>' +
            '<div class="rrp-profile-name">' + escapeHtml(userName) + '</div>' +
            '<div class="rrp-profile-email">' + escapeHtml(userEmail) + '</div>' +
            '<div class="rrp-profile-role rrp-profile-role-reviewer">Reviewer &middot; CityU STC</div>' +
            '<button type="button" class="rrp-btn secondary" id="rrp-edit-profile-btn" style="margin-top:0.75rem;width:100%;">&#9998; Edit Profile</button>' +
          '</div>' +
          '<div class="rrp-nav-card">' +
            '<div class="rrp-nav-card-title">Reviewer tools</div>' +
            '<ul class="rrp-nav-list">' +
              '<li><button type="button" class="rrp-nav-link" id="rrp-load-templates-btn">&#128203; Criteria Templates</button></li>' +
              '<li><button type="button" class="rrp-nav-link" id="rrp-show-coi-btn">&#9888;&#65039; Declare Conflict of Interest</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="analytics">&#128202; Analytics</button></li>' +
              '<li><button type="button" class="rrp-nav-link" data-view="public">&#127760; Public submissions</button></li>' +
            '</ul>' +
          '</div>' +
          '<div id="rrp-reviewer-templates" style="margin-top:1rem;"></div>' +
        '</aside>' +
      '</div>';

    // Quick nav links
    container.querySelectorAll('[data-view]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var view = btn.getAttribute('data-view');
        if (view === 'analytics') renderAnalytics(container, function () { renderReviewerDashboard(container); });
        if (view === 'public')    renderPublic(container,    function () { renderReviewerDashboard(container); });
      });
    });

    // Criteria templates (loads into aside)
    document.getElementById('rrp-edit-profile-btn').addEventListener('click', function () {
      renderProfilePanel(container, function () { renderReviewerDashboard(container); });
    });
    document.getElementById('rrp-load-templates-btn').addEventListener('click', function () {
      var output = document.getElementById('rrp-reviewer-templates');
      if (output.innerHTML) { output.innerHTML = ''; return; }
      output.innerHTML = '<p class="rrp-loading">Loading templates&hellip;</p>';
      api('GET', '/config/review-templates').then(function (resp) {
        output.innerHTML = '<div class="rrp-nav-card"><div class="rrp-nav-card-title">Criteria Templates</div>';
        if (Array.isArray(resp) && resp.length) {
          output.innerHTML += '<ul class="rrp-list">' + resp.map(function (t) {
            return '<li style="font-size:.82rem"><strong>' + escapeHtml(t.name || 'Unnamed') + '</strong><br>' +
              '<span style="color:var(--rrp-text-muted)">' + escapeHtml((t.criteria || []).map(function (c) { return c.label + ' (' + c.weight + '%)'; }).join(', ')) + '</span></li>';
          }).join('') + '</ul>';
        } else {
          output.innerHTML += '<p style="font-size:.85rem">No templates configured.</p>';
        }
        output.innerHTML += '</div>';
      }).catch(function () {
        output.innerHTML = '<div class="rrp-error">Unable to load templates.</div>';
      });
    });

    // COI declaration (toggles inline below list)
    document.getElementById('rrp-show-coi-btn').addEventListener('click', function () {
      var section = document.getElementById('rrp-reviewer-coi-section');
      if (section.innerHTML) { section.innerHTML = ''; return; }
      section.innerHTML =
        '<div class="rrp-dashboard-section">' +
          '<h3>Declare Conflict of Interest</h3>' +
          '<div class="rrp-form-block"><label>Submission ID</label>' +
            '<input type="text" id="rrp-coi-submission-id" placeholder="e.g. PROJ-2026-001"></div>' +
          '<div class="rrp-form-block"><label>Reason</label>' +
            '<textarea id="rrp-coi-reason" rows="3" placeholder="Describe the conflict of interest&hellip;"></textarea></div>' +
          '<button type="button" class="rrp-btn" id="rrp-declare-coi-btn">Declare Conflict</button>' +
          '<div id="rrp-coi-msg" style="margin-top:.75rem;"></div>' +
        '</div>';
      document.getElementById('rrp-declare-coi-btn').addEventListener('click', function () {
        var subId  = document.getElementById('rrp-coi-submission-id').value.trim();
        var reason = document.getElementById('rrp-coi-reason').value.trim();
        var msgEl  = document.getElementById('rrp-coi-msg');
        if (!subId || !reason) { msgEl.innerHTML = '<div class="rrp-error">Both fields are required.</div>'; return; }
        api('POST', '/conflicts', { reviewerEmail: userEmail, submissionId: subId, reason: reason })
          .then(function () { msgEl.innerHTML = '<div class="rrp-success">Conflict declared successfully.</div>'; })
          .catch(function () { msgEl.innerHTML = '<div class="rrp-error">Failed to declare conflict.</div>'; });
      });
    });

    if (!userEmail) {
      document.getElementById('rrp-reviewer-stats').innerHTML = '<div class="rrp-error">Reviewer email not available. Please log in again.</div>';
      return;
    }

    // Load metrics + assigned submissions
    Promise.all([
      api('GET', '/analytics/reviewer?reviewerEmail=' + encodeURIComponent(userEmail)),
      api('GET', '/reviews?reviewerEmail='            + encodeURIComponent(userEmail))
    ]).then(function (results) {
      var metrics     = results[0];
      var submissions = results[1].submissions || [];

      var total    = metrics.totalAssigned  || 0;
      var pending  = metrics.pending        || 0;
      var approved = metrics.approved       || 0;
      var action   = (metrics.needsRevision || 0) + (metrics.rejected || 0);

      document.getElementById('rrp-reviewer-stats').innerHTML =
        '<button class="rrp-stat-card'      + (activeFilter === 'all'      ? ' rrp-stat-active' : '') + '" data-stat-filter="all">' +
          '<span class="rrp-stat-value">' + total    + '</span><span class="rrp-stat-label">Total</span>' +
        '</button>' +
        '<button class="rrp-stat-card rrp-stat-blue'  + (activeFilter === 'pending'  ? ' rrp-stat-active' : '') + '" data-stat-filter="pending">' +
          '<span class="rrp-stat-value">' + pending  + '</span><span class="rrp-stat-label">Pending</span>' +
        '</button>' +
        '<button class="rrp-stat-card rrp-stat-green' + (activeFilter === 'approved' ? ' rrp-stat-active' : '') + '" data-stat-filter="approved">' +
          '<span class="rrp-stat-value">' + approved + '</span><span class="rrp-stat-label">Approved</span>' +
        '</button>' +
        '<button class="rrp-stat-card rrp-stat-warn'  + (activeFilter === 'action'   ? ' rrp-stat-active' : '') + '" data-stat-filter="action">' +
          '<span class="rrp-stat-value">' + action   + '</span><span class="rrp-stat-label">Action Required</span>' +
        '</button>';

      document.querySelectorAll('[data-stat-filter]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var f = btn.getAttribute('data-stat-filter');
          renderSubmissionList(submissions, f, activeDateRange);
          document.querySelectorAll('[data-stat-filter]').forEach(function (b) {
            b.classList.toggle('rrp-stat-active', b.getAttribute('data-stat-filter') === f);
          });
        });
      });

      renderSubmissionList(submissions, activeFilter, activeDateRange);

    }).catch(function () {
      document.getElementById('rrp-reviewer-stats').innerHTML = '<div class="rrp-error">Unable to load reviewer data.</div>';
    });

    function classifyStatus(status) {
      if (isApprovedStatus(status)) return 'approved';
      var s = (status || '').toLowerCase();
      if (s === 'rejected' || s === 'revision required') return 'action';
      return 'pending';
    }

    function renderSubmissionList(submissions, filter, dateRange) {
      dateRange = dateRange || 'all';
      var filtered = submissions;
      if (filter === 'pending')  filtered = submissions.filter(function (s) { return classifyStatus(s.status) === 'pending'; });
      if (filter === 'approved') filtered = submissions.filter(function (s) { return classifyStatus(s.status) === 'approved'; });
      if (filter === 'action')   filtered = submissions.filter(function (s) { return classifyStatus(s.status) === 'action'; });
      filtered = applyDateRange(filtered, dateRange);
      filtered = sortSubmissions(filtered);

      var heading = filter === 'pending'  ? 'Pending Review' :
                    filter === 'approved' ? 'Approved' :
                    filter === 'action'   ? 'Action Required' : 'All Assigned Submissions';

      var listEl = document.getElementById('rrp-reviewer-submissions');
      if (!listEl) return;

      if (filtered.length === 0) {
        listEl.innerHTML =
          dateRangeBar(dateRange) +
          '<h2>' + heading + '</h2>' +
          '<div class="rrp-empty-state"><p>' +
            (submissions.length === 0 ? 'No submissions are currently assigned to you.' : 'No submissions match this filter.') +
          '</p></div>';
        listEl.querySelectorAll('[data-daterange]').forEach(function (b) {
          b.addEventListener('click', function () { activeDateRange = b.getAttribute('data-daterange'); renderSubmissionList(submissions, filter, activeDateRange); });
        });
        return;
      }

      listEl.innerHTML =
        dateRangeBar(dateRange) +
        '<h2>' + heading + ' <span class="rrp-count-badge">' + filtered.length + '</span></h2>' +
        '<ul class="rrp-list rrp-submissions-list">' +
        filtered.map(function (item) {
          var dueTxt = item.deadline ? new Date(item.deadline).toLocaleDateString() : '\u2014';
          var dueNear = item.deadline && (new Date(item.deadline) - Date.now()) < 3 * 86400000;
          return '<li class="rrp-sub-item">' +
            '<div class="rrp-sub-item-header">' +
              '<strong>' + escapeHtml(item.title || item.id) + '</strong>' +
              '<span class="rrp-decision-badge ' + statusBadgeCls(item.status) + '">' + escapeHtml(item.status || 'Pending') + '</span>' +
            '</div>' +
            '<div class="rrp-sub-item-meta">' +
              '<span class="rrp-meta-id"><span class="rrp-meta-lbl">ID</span>' + escapeHtml(item.id) + '</span>' +
              '<span><span class="rrp-meta-lbl">Category</span>' + escapeHtml(typeLabel(item.submissionType || item.type)) + '</span>' +
              (item.createdAt ? '<span><span class="rrp-meta-lbl">Submitted</span>' + new Date(item.createdAt).toLocaleDateString() + '</span>' : '') +
              '<span class="' + (dueNear ? 'rrp-due-soon' : '') + '"><span class="rrp-meta-lbl">Due</span>' + dueTxt + '</span>' +
            '</div>' +
            '<div class="rrp-sub-item-actions">' +
              '<button type="button" class="rrp-btn secondary" data-review="' + escapeHtml(item.id) + '">Review</button>' +
              '<button type="button" class="rrp-btn secondary rrp-btn-log" data-audit-log="' + escapeHtml(item.id) + '">&#128221; Log</button>' +
            '</div>' +
          '</li>';
        }).join('') +
        '</ul>';

      listEl.querySelectorAll('[data-review]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          renderSubmissionDetail(btn.getAttribute('data-review'), container, function () { renderReviewerDashboard(container); });
        });
      });
      listEl.querySelectorAll('[data-audit-log]').forEach(function (btn) {
        btn.addEventListener('click', function () { openAuditLogModal(btn.getAttribute('data-audit-log')); });
      });
      listEl.querySelectorAll('[data-daterange]').forEach(function (btn) {
        btn.addEventListener('click', function () { activeDateRange = btn.getAttribute('data-daterange'); renderSubmissionList(submissions, filter, activeDateRange); });
      });
    }
  }

  function renderPublic(container, backFn) {
    container.innerHTML =
      '<h1>Research & symposium</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom: 1rem;" data-back>← Back</button>' +
      '<p style="color: var(--rrp-text-muted);">Accepted and published work.</p>' +
      '<div id="rrp-public-list"></div>';

    container.querySelector('[data-back]').addEventListener('click', function () { if (typeof backFn === 'function') backFn(); else renderSelection(container); });

    var listEl = document.getElementById('rrp-public-list');
    listEl.innerHTML = '<p class="rrp-loading">Loading…</p>';
    api('GET', '/submissions/public')
      .then(function (res) {
        var list = res.submissions || [];
        if (list.length === 0) {
          listEl.innerHTML = '<p>No public submissions yet.</p>';
          return;
        }
        listEl.innerHTML =
          '<ul class="rrp-list rrp-public-list">' +
            list.map(function (s) {
              var abs = (s.abstract || '').substring(0, 300);
              if ((s.abstract || '').length > 300) abs += '…';
              return '<li><div><strong>' + escapeHtml(s.title || '—') + '</strong> · ' + escapeHtml(s.id) + ' <span class="rrp-status">' + escapeHtml(s.status || '') + '</span></div>' +
                (abs ? '<div class="rrp-abstract">' + escapeHtml(abs) + '</div>' : '') + '</li>';
            }).join('') +
          '</ul>';
      })
      .catch(function () {
        listEl.innerHTML = '<div class="rrp-error">Could not load public list.</div>';
      });
  }

  function getActiveStage(stages) {
    for (var i = 0; i < stages.length; i++) {
      if (!stages[i].skipped) {
        var decs = stages[i].decisions || {};
        var revs = stages[i].reviewers || [];
        if (revs.length === 0) return stages[i];
        var allApproved = revs.every(function (r) {
          return (decs[(r.email || '').toLowerCase()] || '').toLowerCase() === 'approved';
        });
        if (!allApproved) return stages[i];
      }
    }
    return stages.length ? stages[stages.length - 1] : null;
  }

  function buildReviewerDecisionForm(sub) {
    var userEmail = ((window.RRP && window.RRP.userEmail) || '').toLowerCase();
    var stages = sub.reviewStages || [];
    var pending = stages.filter(function (s) {
      var assigned = (s.reviewers || []).some(function (r) { return (r.email || '').toLowerCase() === userEmail; });
      var decided = (s.decisions || {})[userEmail];
      return assigned && !decided && !s.skipped;
    });
    if (!pending.length) return '<p>No pending stages to review.</p>';
    return '<form id="rrp-decision-form">' +
      '<div class="rrp-form-block"><label>Stage</label><select name="stageName">' +
        pending.map(function (s) { return '<option value="' + escapeHtml(s.stageName) + '">' + escapeHtml(s.stageName) + '</option>'; }).join('') +
      '</select></div>' +
      '<div class="rrp-form-block"><label>Decision *</label><select name="decision">' +
        '<option value="Approved">Approved</option>' +
        '<option value="Needs Revision">Needs Revision</option>' +
        '<option value="Rejected">Rejected</option>' +
      '</select></div>' +
      '<div class="rrp-form-block"><label>Feedback <span class="rrp-hint">(required for Needs Revision / Rejected)</span></label>' +
        '<div id="rrp-feedback-editor" style="min-height:120px;background:#fff;"></div>' +
        '<input type="hidden" name="feedbackMsg" id="rrp-feedback-hidden">' +
      '</div>' +
      '<div class="rrp-form-block">' +
        '<label>Annotated document <span class="rrp-hint">(optional &#8211; upload your copy with inline comments, PDF or DOCX, max 2 MB)</span></label>' +
        '<input type="file" id="rrp-reviewer-file" accept=".pdf,.docx">' +
      '</div>' +
      '<button type="submit" class="rrp-btn">Save Decision</button> ' +
      '<span id="rrp-decision-msg" style="margin-left:.5rem;"></span>' +
    '</form>' +
    '<details class="rrp-ext-request-block" style="margin-top:1.25rem;border:1px solid var(--rrp-border,#ddd);border-radius:.5rem;padding:.75rem 1rem;">' +
      '<summary style="cursor:pointer;font-weight:600;">&#128197; Request Deadline Extension</summary>' +
      '<form id="rrp-ext-request-form" style="margin-top:.75rem;">' +
        '<div class="rrp-form-block"><label>Stage</label>' +
          '<select name="extStageName">' +
          pending.map(function (s) { return '<option value="' + escapeHtml(s.stageName) + '">' + escapeHtml(s.stageName) + '</option>'; }).join('') +
          '</select></div>' +
        '<div class="rrp-form-block"><label>Additional days needed *</label>' +
          '<input type="number" name="extDays" value="7" min="1" max="60" style="max-width:100px;">' +
        '</div>' +
        '<div class="rrp-form-block"><label>Reason *</label>' +
          '<textarea name="extReason" rows="3" placeholder="Explain why you need more time\u2026" style="width:100%;"></textarea>' +
        '</div>' +
        '<button type="submit" class="rrp-btn secondary">Submit Request</button> ' +
        '<span id="rrp-ext-request-msg" style="margin-left:.5rem;"></span>' +
      '</form>' +
    '</details>';
  }

  function buildRevisionForm(sub) {
    return '<p style="color:var(--rrp-text-muted);font-size:.9rem;">Please revise your submission based on the reviewer feedback and resubmit.</p>' +
    '<form id="rrp-revision-form">' +
      '<div class="rrp-form-block"><label>Title</label><input type="text" name="title" value="' + escapeHtml(sub.title || '') + '" maxlength="200"></div>' +
      '<div class="rrp-form-block"><label>Research area</label><input type="text" name="researchArea" value="' + escapeHtml(sub.researchArea || '') + '"></div>' +
      '<div class="rrp-form-block"><label>Revised document <span class="rrp-hint">(optional &#8211; PDF or DOCX only, max 2 MB)</span></label>' +
        '<input type="file" name="revisionFile" accept=".pdf,.docx">' +
      '</div>' +
      '<button type="submit" class="rrp-btn">Submit Revision</button> ' +
      '<span id="rrp-revision-msg" style="margin-left:.5rem;"></span>' +
    '</form>';
  }

  function renderSubmissionDetail(submissionId, container, backFn) {
    var userEmail    = ((window.RRP && window.RRP.userEmail) || '').toLowerCase();
    var _uRoles      = (window.RRP && Array.isArray(window.RRP.userRoles)) ? window.RRP.userRoles : [(window.RRP && window.RRP.userRole) || ''];
    var isAdmin      = _uRoles.indexOf('Admin') !== -1 || _uRoles.indexOf('Coordinator') !== -1;

    container.innerHTML =
      '<h1>Submission Details</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom:1rem;" data-back>← Back</button>' +
      '<div id="rrp-detail-content"><p class="rrp-loading">Loading…</p></div>';

    container.querySelector('[data-back]').addEventListener('click', function () {
      if (typeof backFn === 'function') backFn(); else renderSelection(container);
    });

    Promise.all([
      api('GET', '/submissions/' + encodeURIComponent(submissionId)),
      api('GET', '/submissions/' + encodeURIComponent(submissionId) + '/deadlines').catch(function () { return { deadlines: [] }; })
    ]).then(function (results) {
      var sub = results[0];
      var deadlines = results[1].deadlines || [];
      var el = document.getElementById('rrp-detail-content');
      if (!el) return;

      var isReviewer = (sub.assignedReviewers || []).some(function (r) {
        return (r.email || '').toLowerCase() === userEmail;
      });
      if (!isReviewer && sub.reviewStages) {
        sub.reviewStages.forEach(function (s) {
          if ((s.reviewers || []).some(function (r) { return (r.email || '').toLowerCase() === userEmail; })) { isReviewer = true; }
        });
      }
      var isSubmitter = (sub.submitterEmail || '').toLowerCase() === userEmail;
      var needsRevision = sub.status === 'Revision Required' || sub.status === 'Rejected';

      // Stage timeline HTML
      // Find the index of the current active stage so future stages can show "Not Started"
      var _allStages = sub.reviewStages || [];
      var _activeStageIdx = -1;
      for (var _asi = 0; _asi < _allStages.length; _asi++) {
        if (_allStages[_asi].skipped) continue;
        var _asDecs = _allStages[_asi].decisions || {};
        var _asRevs = _allStages[_asi].reviewers || [];
        if (_asRevs.length === 0) { _activeStageIdx = _asi; break; }
        var _asAllApproved = _asRevs.every(function (r) {
          return (_asDecs[(r.email || '').toLowerCase()] || '').toLowerCase() === 'approved';
        });
        if (!_asAllApproved) { _activeStageIdx = _asi; break; }
      }

      var stagesHtml = _allStages.map(function (stage, i) {
        var dl = deadlines[i] || {};
        var approved = dl.approved || false;
        var skipped  = stage.skipped || false;
        var decisions = stage.decisions || {};
        var hasRevisionDec = Object.keys(decisions).some(function (k) {
          return (decisions[k] || '').toLowerCase() === 'needs revision';
        });
        var isFuture = !skipped && !approved && _activeStageIdx !== -1 && i > _activeStageIdx;
        var statusClass, statusLabel;
        if (skipped)           { statusClass = 'rrp-stage-skipped';     statusLabel = 'Skipped'; }
        else if (approved)     { statusClass = 'rrp-stage-approved';    statusLabel = '✓ Approved'; }
        else if (isFuture)     { statusClass = 'rrp-stage-not-started'; statusLabel = 'Not Started'; }
        else if (hasRevisionDec) { statusClass = 'rrp-stage-revision';  statusLabel = 'Revision Requested'; }
        else                   { statusClass = 'rrp-stage-pending';     statusLabel = 'In Progress'; }
        var reviewerRows = (stage.reviewers || []).map(function (r) {
          var em  = (r.email || '').toLowerCase();
          var dec = decisions[em] || 'Pending';
          var cls = dec === 'Approved' ? 'rrp-dec-approved' : (dec === 'Rejected' ? 'rrp-dec-rejected' : (dec === 'Needs Revision' ? 'rrp-dec-revision' : 'rrp-dec-pending'));
          return '<li>' + escapeHtml(r.name || r.email) + ' <span class="rrp-decision-badge ' + cls + '">' + escapeHtml(dec) + '</span></li>';
        }).join('');
        var feedbackHtml = (stage.feedback || []).map(function (f) {
          return '<div class="rrp-feedback-item"><span class="rrp-feedback-meta">' + escapeHtml(f.name || f.email || f.role) + ':</span> <div class="rrp-feedback-body">' + safeHtml(f.message) + '</div></div>';
        }).join('');
        return '<div class="rrp-stage-block ' + statusClass + '">' +
          '<div class="rrp-stage-header"><strong>' + escapeHtml(stage.stageName) + '</strong>' +
            '<span class="rrp-stage-status">' + statusLabel + '</span>' +
            (dl.deadline ? '<span class="rrp-deadline-badge">Due: ' + escapeHtml(new Date(dl.deadline).toLocaleDateString()) + '</span>' : '') +
          '</div>' +
          (reviewerRows ? '<ul class="rrp-reviewer-decisions">' + reviewerRows + '</ul>' : '') +
          (feedbackHtml ? feedbackHtml : '') +
        '</div>';
      }).join('');

      el.innerHTML =
        '<div class="rrp-detail-info">' +
          '<h2>' + escapeHtml(sub.title || sub.id) + '</h2>' +
          '<div class="rrp-detail-meta">' +
            '<span><strong>ID:</strong> ' + escapeHtml(sub.id) + '</span>' +
            '<span><strong>Type:</strong> <span class="rrp-type-badge">' + escapeHtml(typeLabel(sub.submissionType || sub.type) || '—') + '</span></span>' +
            '<span><strong>Status:</strong> <span class="rrp-status">' + escapeHtml(sub.status || '—') + '</span></span>' +
            '<span><strong>Submitted by:</strong> ' + escapeHtml(sub.submitterName || sub.submitterEmail || '—') + '</span>' +
            (sub.createdAt ? '<span><strong>Date:</strong> ' + escapeHtml(new Date(sub.createdAt).toLocaleDateString()) + '</span>' : '') +
          '</div>' +
          (sub.abstract ? '<div class="rrp-detail-abstract"><strong>Abstract</strong><p>' + escapeHtml(sub.abstract) + '</p></div>' : '') +
          (sub.keywords ? '<p><strong>Keywords:</strong> ' + escapeHtml(sub.keywords) + '</p>' : '') +
          (sub.researchArea ? '<p><strong>Research area:</strong> ' + escapeHtml(sub.researchArea) + '</p>' : '') +
        '</div>' +
        ((sub.attachments && sub.attachments.length) ?
          (function () {
            // Group attachments by revisionRound (0 = original, 1+ = revisions)
            var groups = {};
            sub.attachments.forEach(function (a) {
              var rr = (a.revisionRound != null) ? a.revisionRound : 0;
              if (!groups[rr]) groups[rr] = [];
              groups[rr].push(a);
            });
            var rounds = Object.keys(groups).map(Number).sort(function (a, b) { return a - b; });
            var maxRound = rounds[rounds.length - 1];
            var html = '<div class="rrp-detail-section"><h3>Documents</h3>' +
              '<div id="rrp-inline-viewer" class="rrp-inline-viewer" style="display:none;"></div>';
            rounds.forEach(function (rr) {
              var isLatest = rr === maxRound;
              var groupLabel = rr === 0 ? 'Original Submission' : 'Revision ' + rr;
              html += '<div class="rrp-attach-group' + (isLatest ? ' rrp-attach-group-latest' : '') + '">' +
                '<div class="rrp-attach-group-header">' +
                  '<span class="rrp-attach-round-badge' + (isLatest ? ' latest' : '') + '">' + escapeHtml(groupLabel) + '</span>' +
                  (isLatest ? '<span class="rrp-latest-badge">Latest</span>' : '') +
                '</div>' +
                '<ul class="rrp-list rrp-attachment-list">';
              groups[rr].forEach(function (a) {
                var fname  = (a.filename || a.name || '').toLowerCase();
                var ext    = fname.split('.').pop();
                var isPdf  = ext === 'pdf';
                var isDocx = ext === 'docx';
                var fileUrl = escapeHtml(restBase + '/submissions/' + submissionId + '/attachments/' + encodeURIComponent(a.filename || a.name) + '?_wpnonce=' + nonce);
                var inlineUrl = fileUrl + '&inline=1';
                var uploadedDate = a.uploadedAt ? new Date(a.uploadedAt).toLocaleString() : null;
                var reviewerBadge = a.uploadedByReviewer
                  ? ' <span class="rrp-reviewer-annot-badge" title="Annotated version uploaded by reviewer ' + escapeHtml(a.reviewerName || a.reviewerEmail || '') + '">&#128393; Reviewer annotation</span>'
                  : '';
                html += '<li class="rrp-attach-item' + (a.uploadedByReviewer ? ' rrp-attach-reviewer' : '') + '">' +
                  '<span class="rrp-attach-icon">&#128196;</span> ' +
                  '<span class="rrp-attach-name">' + escapeHtml(a.name || a.filename) + '</span>' +
                  reviewerBadge +
                  (uploadedDate ? '<span class="rrp-attach-date">Uploaded: ' + escapeHtml(uploadedDate) + '</span>' : '') +
                  '<span class="rrp-attach-actions">' +
                  ((isPdf || isDocx) ? '<button type="button" class="rrp-btn secondary" data-inline-url="' + inlineUrl + '" data-inline-type="' + (isPdf ? 'pdf' : 'docx') + '" data-inline-name="' + escapeHtml(a.name || a.filename) + '">&#128065; View</button> ' : '') +
                  '<a class="rrp-btn secondary" target="_blank" href="' + fileUrl + '">&#8595; Download</a>' +
                  '</span></li>';
              });
              html += '</ul></div>';
            });
            html += '</div>';
            return html;
          })() : '') +
        '<div class="rrp-detail-section"><h3>Review Progress</h3>' + (stagesHtml || '<p>No review stages assigned yet.</p>') + '</div>' +
        '<div class="rrp-detail-section rrp-annotations-section" id="rrp-annotations">' +
          '<h3>&#128221; Annotations &amp; Comments</h3>' +
          ((sub.internalComments && sub.internalComments.length) ?
            '<ul class="rrp-comment-list">' +
            sub.internalComments.map(function (c) {
              return '<li class="rrp-comment-item">' +
                '<div class="rrp-comment-meta">' +
                  '<strong>' + escapeHtml(c.by || 'Unknown') + '</strong>' +
                  (c.stage ? ' &middot; <em>' + escapeHtml(c.stage) + '</em>' : '') +
                  (c.at ? ' &middot; <span class="rrp-comment-time">' + new Date(c.at).toLocaleString() + '</span>' : '') +
                '</div>' +
                '<div class="rrp-comment-text">' + escapeHtml(c.text) + '</div>' +
              '</li>';
            }).join('') +
            '</ul>' : '<p style="color:var(--rrp-text-muted);font-size:.88rem;">No comments yet.</p>') +
          (isReviewer || isAdmin ?
            '<div class="rrp-add-comment" id="rrp-add-comment-form">' +
              '<textarea id="rrp-comment-text" rows="3" placeholder="Add annotation or comment&hellip;" style="width:100%;box-sizing:border-box;"></textarea>' +
              '<div style="display:flex;gap:.5rem;margin-top:.5rem;">' +
                '<button type="button" class="rrp-btn" id="rrp-comment-save">&#128203; Save Comment</button>' +
                '<span id="rrp-comment-msg" style="align-self:center;"></span>' +
              '</div>' +
            '</div>' : '') +
        '</div>' +
        (isReviewer ? '<div id="rrp-reviewer-action" class="rrp-detail-section"><h3>Record Your Decision</h3>' + buildReviewerDecisionForm(sub) + '</div>' : '') +
        (isSubmitter && needsRevision ? '<div id="rrp-submitter-revision" class="rrp-detail-section"><h3>Submit Revision</h3>' + buildRevisionForm(sub) + '</div>' : '') +
        (isAdmin ? '<div id="rrp-admin-controls" class="rrp-detail-section"><h3>Administrative Controls</h3>' +
          '<button type="button" class="rrp-btn secondary" id="rrp-skip-stage-btn">Skip Current Stage</button>' +
          '<span id="rrp-skip-msg" style="margin-left:.5rem;"></span>' +
        '</div>' : '');

      // Wire inline document viewer
      var viewerEl = document.getElementById('rrp-inline-viewer');
      if (viewerEl) {
        el.querySelectorAll('[data-inline-url]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var url  = btn.getAttribute('data-inline-url');
            var name = btn.getAttribute('data-inline-name');
            var type = btn.getAttribute('data-inline-type') || 'pdf';
            var isOpen = viewerEl.style.display !== 'none' && viewerEl.getAttribute('data-current') === url;
            if (isOpen) {
              viewerEl.style.display = 'none';
              viewerEl.innerHTML = '';
              viewerEl.removeAttribute('data-current');
              btn.textContent = '\u{1F441} View';
              return;
            }
            el.querySelectorAll('[data-inline-url]').forEach(function (b) { b.textContent = '\u{1F441} View'; });
            viewerEl.style.display = '';
            viewerEl.setAttribute('data-current', url);
            btn.textContent = '\u2715 Close viewer';
            var toolbar = '<div class="rrp-viewer-toolbar"><strong>' + escapeHtml(name) + '</strong>' +
              '<button type="button" class="rrp-btn secondary rrp-viewer-close-btn">&#10005; Close</button></div>';
            function wireClose() {
              var closeBtn = viewerEl.querySelector('.rrp-viewer-close-btn');
              if (closeBtn) closeBtn.addEventListener('click', function () {
                viewerEl.style.display = 'none'; viewerEl.innerHTML = ''; viewerEl.removeAttribute('data-current');
                btn.textContent = '\u{1F441} View';
              });
            }
            if (type === 'docx' && window.mammoth) {
              viewerEl.innerHTML = toolbar + '<div style="padding:1rem;color:var(--rrp-text-muted);">Loading document&hellip;</div>';
              wireClose();
              fetch(url, { headers: { 'X-WP-Nonce': nonce } })
                .then(function (r) {
                  if (!r.ok) throw new Error('HTTP ' + r.status);
                  return r.arrayBuffer();
                })
                .then(function (buf) { return window.mammoth.convertToHtml({ arrayBuffer: buf }); })
                .then(function (result) {
                  viewerEl.innerHTML = toolbar +
                    '<div class="rrp-viewer-docx-content" style="padding:1.5rem 2rem;max-height:72vh;overflow-y:auto;background:#fff;font-family:Georgia,serif;line-height:1.7;">' +
                    result.value + '</div>';
                  wireClose();
                  viewerEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                })
                .catch(function () {
                  viewerEl.innerHTML = toolbar + '<div class="rrp-error" style="padding:1rem;">Could not render document. Please use Download instead.</div>';
                  wireClose();
                });
            } else {
              viewerEl.innerHTML = toolbar +
                '<iframe src="' + url + '" class="rrp-viewer-frame" title="' + escapeHtml(name) + '"></iframe>';
              wireClose();
              viewerEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          });
        });
      }

      // Wire annotation / comment save
      var commentSaveBtn = document.getElementById('rrp-comment-save');
      if (commentSaveBtn) {
        commentSaveBtn.addEventListener('click', function () {
          var txt    = (document.getElementById('rrp-comment-text').value || '').trim();
          var msgEl  = document.getElementById('rrp-comment-msg');
          if (!txt) { msgEl.innerHTML = '<span class="rrp-error">Please enter a comment.</span>'; return; }
          var activeStage = '';
          (sub.reviewStages || []).forEach(function (rs) {
            if (!activeStage && !(rs.skipped) && rs.stageName) activeStage = rs.stageName;
          });
          msgEl.innerHTML = '<span class="rrp-loading">Saving&hellip;</span>';
          commentSaveBtn.disabled = true;
          api('POST', '/submissions/' + encodeURIComponent(submissionId) + '/comments', {
            stage: activeStage || 'General',
            text:  txt,
            by:    (window.RRP && window.RRP.userName) || userRole
          }).then(function () {
              msgEl.innerHTML = '';
              commentSaveBtn.disabled = false;
              document.getElementById('rrp-comment-text').value = '';
              renderSubmissionDetail(submissionId, container, backFn);
            }).catch(function () {
              commentSaveBtn.disabled = false;
              msgEl.innerHTML = '<span class="rrp-error">Failed to save comment.</span>';
            });
        });
      }

      // Wire reviewer decision form
      var decForm = document.getElementById('rrp-decision-form');
      if (decForm) {
        // Initialize Quill rich-text editor for the feedback field
        loadQuill().then(function () {
          var editorEl = document.getElementById('rrp-feedback-editor');
          if (!editorEl || !window.Quill) return;
          var quill = new Quill(editorEl, {
            theme: 'snow',
            placeholder: 'Your feedback to the submitter\u2026',
            modules: {
              toolbar: [
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote'],
                ['clean']
              ]
            }
          });
          quill.on('text-change', function () {
            var hiddenEl = document.getElementById('rrp-feedback-hidden');
            if (hiddenEl) {
              // Store empty string when editor is blank (just a <p><br></p>)
              hiddenEl.value = quill.getText().trim() === '' ? '' : quill.root.innerHTML;
            }
          });
        }).catch(function () {
          // Quill failed to load — fall back to a plain textarea
          var editorEl = document.getElementById('rrp-feedback-editor');
          if (editorEl) {
            var ta = document.createElement('textarea');
            ta.name = 'feedbackMsgFallback';
            ta.rows = 4;
            ta.style.width = '100%';
            ta.placeholder = 'Your feedback to the submitter\u2026';
            ta.addEventListener('input', function () {
              var hiddenEl = document.getElementById('rrp-feedback-hidden');
              if (hiddenEl) hiddenEl.value = ta.value;
            });
            editorEl.parentNode.replaceChild(ta, editorEl);
          }
        });

        decForm.addEventListener('submit', function (e) {
          e.preventDefault();
          var stageName   = decForm.querySelector('[name="stageName"]').value;
          var decision    = decForm.querySelector('[name="decision"]').value;
          var feedbackMsg = decForm.querySelector('[name="feedbackMsg"]').value;
          var fileInput   = document.getElementById('rrp-reviewer-file');
          var hasFile     = fileInput && fileInput.files && fileInput.files.length > 0;
          var msgEl = document.getElementById('rrp-decision-msg');
          msgEl.innerHTML = '<span class="rrp-loading">Saving\u2026</span>';

          // Step 1: optionally upload annotated file
          var uploadStep = hasFile
            ? (function () {
                var fd = new FormData();
                fd.append('files', fileInput.files[0]);
                return fetch(restBase + '/submissions/' + encodeURIComponent(submissionId) + '/attachments', {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: { 'X-WP-Nonce': nonce },
                  body: fd
                }).then(function (r) {
                  if (!r.ok) return r.json().then(function (d) { throw d; });
                });
              })()
            : Promise.resolve();

          // Step 2: save decision (and optional feedback)
          uploadStep
            .then(function () {
              var body = { stageDecision: { stageName: stageName, reviewerEmail: userEmail, decision: decision } };
              if (feedbackMsg && (decision === 'Needs Revision' || decision === 'Rejected')) {
                body.stageFeedback = { stageName: stageName, role: 'reviewer', email: userEmail, name: (window.RRP && window.RRP.userName) || '', message: feedbackMsg };
              }
              return api('PATCH', '/submissions/' + encodeURIComponent(submissionId), body);
            })
            .then(function () {
              msgEl.innerHTML = '<span class="rrp-success">Decision recorded.</span>';
              setTimeout(function () { renderSubmissionDetail(submissionId, container, backFn); }, 1400);
            })
            .catch(function (err) {
              msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.error) || (err && err.data && err.data.error) || 'Failed to save.') + '</span>';
            });
        });
      }

      // Wire extension request form
      var extForm = document.getElementById('rrp-ext-request-form');
      if (extForm) {
        extForm.addEventListener('submit', function (e) {
          e.preventDefault();
          var msgEl = document.getElementById('rrp-ext-request-msg');
          msgEl.innerHTML = '<span class="rrp-loading">Submitting\u2026</span>';
          var stageName    = extForm.querySelector('[name="extStageName"]').value;
          var requestedDays = parseInt(extForm.querySelector('[name="extDays"]').value || '7', 10);
          var reason       = extForm.querySelector('[name="extReason"]').value.trim();
          if (!reason) {
            msgEl.innerHTML = '<span class="rrp-error">Reason is required.</span>';
            return;
          }
          api('POST', '/submissions/' + encodeURIComponent(submissionId) + '/request-extension', { stageName: stageName, reason: reason, requestedDays: requestedDays })
            .then(function () {
              msgEl.innerHTML = '<span class="rrp-success">Extension request submitted. A coordinator will review it shortly.</span>';
              extForm.querySelector('[type="submit"]').disabled = true;
            })
            .catch(function (err) {
              msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.data && err.data.error) || 'Failed to submit request.') + '</span>';
            });
        });
      }

      // Wire revision form
      var revForm = document.getElementById('rrp-revision-form');
      if (revForm) {
        revForm.addEventListener('submit', function (e) {
          e.preventDefault();
          var updBody = {};
          ['title', 'researchArea'].forEach(function (f) {
            var el2 = revForm.querySelector('[name="' + f + '"]');
            if (el2) updBody[f] = el2.value;
          });
          var fileInput = revForm.querySelector('[name="revisionFile"]');
          var hasFile   = fileInput && fileInput.files && fileInput.files.length > 0;
          var msgEl = document.getElementById('rrp-revision-msg');
          msgEl.innerHTML = '<span class="rrp-loading">Submitting…</span>';

          var firstStageName = (sub.reviewStages && sub.reviewStages[0]) ? sub.reviewStages[0].stageName : '';

          // Step 1: upload revised file (if any)
          var uploadStep = hasFile
            ? (function () {
                var fd = new FormData();
                fd.append('files', fileInput.files[0]);
                return fetch(restBase + '/submissions/' + encodeURIComponent(submissionId) + '/attachments', {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: { 'X-WP-Nonce': nonce },
                  body: fd
                }).then(function (r) {
                  if (!r.ok) return r.json().then(function (d) { throw d; });
                });
              })()
            : Promise.resolve();

          // Step 2: update title / researchArea, then reset workflow to stage 0
          uploadStep
            .then(function () {
              return api('PATCH', '/submissions/' + encodeURIComponent(submissionId), updBody);
            })
            .then(function () {
              if (!firstStageName) return;
              return api('PATCH', '/submissions/' + encodeURIComponent(submissionId), {
                stageRevisionSubmitted: { stageName: firstStageName }
              });
            })
            .then(function () {
              msgEl.innerHTML = '<span class="rrp-success">Revision submitted. The submission has been sent back to Chair Review.</span>';
              setTimeout(function () { renderSubmissionDetail(submissionId, container, backFn); }, 1800);
            })
            .catch(function (err) {
              msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.error) || (err && err.data && err.data.error) || 'Failed to submit revision.') + '</span>';
            });
        });
      }

      // Wire skip stage button
      var skipBtn = document.getElementById('rrp-skip-stage-btn');
      if (skipBtn) {
        skipBtn.addEventListener('click', function () {
          if (!confirm('Skip the current pending stage? All assigned reviewers will be marked as Approved for that stage.')) return;
          var msgEl = document.getElementById('rrp-skip-msg');
          msgEl.innerHTML = '<span class="rrp-loading">Skipping…</span>';
          api('POST', '/submissions/' + encodeURIComponent(submissionId) + '/skip-stage', {})
            .then(function () {
              msgEl.innerHTML = '<span class="rrp-success">Stage skipped.</span>';
              setTimeout(function () { renderSubmissionDetail(submissionId, container, backFn); }, 1400);
            })
            .catch(function (err) {
              msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err.data && err.data.error) || 'Failed.') + '</span>';
            });
        });
      }

    }).catch(function () {
      var el = document.getElementById('rrp-detail-content');
      if (el) el.innerHTML = '<div class="rrp-error">Unable to load submission. You may not have permission to view it.</div>';
    });
  }

  function escapeHtml(s) {
    if (s == null) return '';
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  /**
   * Render user-supplied HTML safely by stripping any tags / attributes not
   * on the allowlist. Falls back to plain-text escaping for non-HTML strings.
   */
  function safeHtml(raw) {
    if (raw == null || raw === '') return '';
    // If it doesn't look like HTML, just escape it as plain text
    if (raw.indexOf('<') === -1) return escapeHtml(raw);
    var ALLOWED_TAGS = ['p','br','b','strong','i','em','u','s','ul','ol','li',
                        'h1','h2','h3','blockquote','span','div'];
    var tmp = document.createElement('div');
    tmp.innerHTML = raw;
    (function sanitize(node) {
      var children = Array.prototype.slice.call(node.childNodes);
      children.forEach(function (child) {
        if (child.nodeType === 3) return; // text node — safe
        if (child.nodeType === 1) {
          var tag = (child.tagName || '').toLowerCase();
          if (ALLOWED_TAGS.indexOf(tag) === -1) {
            // Replace disallowed element with its text content
            var text = document.createTextNode(child.textContent || '');
            node.replaceChild(text, child);
            return;
          }
          // Strip all attributes except class on span/div (Quill uses these)
          var attrs = Array.prototype.slice.call(child.attributes);
          attrs.forEach(function (a) {
            if (a.name !== 'class') child.removeAttribute(a.name);
          });
          sanitize(child);
        } else {
          node.removeChild(child); // remove comments, processing instructions, etc.
        }
      });
    })(tmp);
    return tmp.innerHTML;
  }

  // Dynamically load Quill rich-text editor from CDN (cached after first call)
  var _quillLoaded = false;
  var _quillLoading = null;
  function loadQuill() {
    if (_quillLoaded && window.Quill) return Promise.resolve();
    if (_quillLoading) return _quillLoading;
    _quillLoading = new Promise(function (resolve, reject) {
      // Load Quill CSS
      if (!document.getElementById('rrp-quill-css')) {
        var link = document.createElement('link');
        link.id   = 'rrp-quill-css';
        link.rel  = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css';
        document.head.appendChild(link);
      }
      // Load Quill JS
      if (!document.getElementById('rrp-quill-js')) {
        var script = document.createElement('script');
        script.id  = 'rrp-quill-js';
        script.src = 'https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js';
        script.onload  = function () { _quillLoaded = true; resolve(); };
        script.onerror = function () { reject(new Error('Quill failed to load')); };
        document.head.appendChild(script);
      } else if (window.Quill) {
        _quillLoaded = true;
        resolve();
      }
    });
    return _quillLoading;
  }

  function renderTimeline(submissionId, container) {
    container.innerHTML =
      '<h1>Timeline: ' + escapeHtml(submissionId) + '</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom: 1rem;" data-back>← Back</button>' +
      '<div id="rrp-timeline-content"><p class="rrp-loading">Loading timeline…</p></div>';

    container.querySelector('[data-back]').addEventListener('click', function () { renderDashboard(container); });

    api('GET', '/submissions/' + encodeURIComponent(submissionId) + '/timeline')
      .then(function (res) {
        var content = document.getElementById('rrp-timeline-content');
        if (!res.timeline || !res.timeline.length) {
          content.innerHTML = '<p>No timeline data available.</p>';
          return;
        }
        content.innerHTML = '<div class="rrp-dashboard-section"><h3>Review timeline</h3></div>' +
          '<ul class="rrp-list">' + res.timeline.map(function (stage) {
            var reviewerList = (stage.reviewers || []).map(function (r) {
              return '<li>' + escapeHtml(r.name || r.email) + ' - ' + escapeHtml(r.decision || 'Pending') + '</li>';
            }).join('');
            return '<li><strong>' + escapeHtml(stage.stageName) + '</strong> - ' + escapeHtml(stage.status) + '<ul>' + reviewerList + '</ul></li>';
          }).join('') + '</ul>';
      })
      .catch(function () {
        document.getElementById('rrp-timeline-content').innerHTML = '<div class="rrp-error">Unable to load timeline.</div>';
      });
  }

  // ── Multi-role dashboard switcher ─────────────────────────────────────────
  // Used when a user holds more than one portal role (e.g. a faculty member who
  // is also enrolled as a student). Shows a tab bar to switch between views;
  // each view renders into an inner #rrp-role-body div so the tab bar persists.
  function renderMultiRoleDashboard(container, userRoles) {
    var roleRenderers = {
      'Student':     function(c) { renderStudentDashboard(c); },
      'Reviewer':    function(c) { renderReviewerDashboard(c); },
      'Faculty':     function(c) { renderReviewerDashboard(c); },
      'Coordinator': function(c) { renderCoordinatorDashboard(c); },
      'Admin':       function(c) { renderCoordinatorDashboard(c); }
    };
    var tabs = userRoles.filter(function(r) { return roleRenderers[r]; });
    if (tabs.length === 0) { renderSelection(container); return; }
    if (tabs.length === 1) { roleRenderers[tabs[0]](container); return; }

    var activeRole = tabs[0];

    function applyRole(role) {
      activeRole = role;
      container.querySelectorAll('[data-role-switch]').forEach(function(b) {
        b.classList.toggle('active', b.getAttribute('data-role-switch') === role);
      });
      var body = document.getElementById('rrp-role-body');
      if (body) roleRenderers[role](body);
    }

    container.innerHTML =
      '<div class="rrp-role-switcher">' +
        '<span class="rrp-role-switcher-label">&#128100; Viewing as:</span>' +
        tabs.map(function(r) {
          var icons = { Student:'&#127891;', Reviewer:'&#128064;', Faculty:'&#127979;', Coordinator:'&#128203;', Admin:'&#128081;' };
          return '<button type="button" class="rrp-role-tab' + (r === activeRole ? ' active' : '') + '" data-role-switch="' + escapeHtml(r) + '">' +
            (icons[r] || '') + ' ' + escapeHtml(r) + '</button>';
        }).join('') +
        '<span class="rrp-role-switcher-hint">You have ' + tabs.length + ' roles &mdash; click to switch</span>' +
      '</div>' +
      '<div id="rrp-role-body"></div>';

    container.querySelectorAll('[data-role-switch]').forEach(function(btn) {
      btn.addEventListener('click', function() { applyRole(btn.getAttribute('data-role-switch')); });
    });

    applyRole(activeRole);
  }

  function boot() {
    var el = document.getElementById('research-review-portal');
    if (!el || !restBase) {
      if (el) el.innerHTML = '<p class="rrp-error">Portal API not configured.</p>';
      return;
    }

    if (!isLoggedIn) { renderSelection(el); return; }

    // Load dynamic submission types first, then render the appropriate dashboard.
    _refreshDynTypes(function () {
      // Rebuild OB_ALLOWED_TYPES array now that _dynTypes is populated.
      OB_ALLOWED_TYPES = _getOBAllowedTypes();

      // Prefer userRoles array (multi-role aware); fall back to single userRole for
      // backward compatibility with any cached page that has the old window.RRP shape.
      var userRoles = (window.RRP && Array.isArray(window.RRP.userRoles) && window.RRP.userRoles.length)
        ? window.RRP.userRoles
        : ((window.RRP && window.RRP.userRole) ? [window.RRP.userRole] : []);

      var hasRole = function(r) { return userRoles.indexOf(r) !== -1; };

      // Collect ALL portal roles the user holds (admin roles first, then others)
      var allRoleOrder = ['Admin', 'Coordinator', 'Student', 'Reviewer', 'Faculty'];
      var allActiveRoles = allRoleOrder.filter(function(r) { return hasRole(r); });

      if (allActiveRoles.length > 1) {
        // Multi-role user — show the role switcher regardless of which roles they hold
        renderMultiRoleDashboard(el, allActiveRoles);
      } else if (hasRole('Admin') || hasRole('Coordinator')) {
        renderCoordinatorDashboard(el);
      } else if (hasRole('Reviewer') || hasRole('Faculty')) {
        renderReviewerDashboard(el);
      } else if (hasRole('Student')) {
        renderStudentDashboard(el);
      } else {
        renderSelection(el);
      }
    });
  }

  // ── Shared onboarding constants ──────────────────────────────────────────
  // OB_TYPE_STAGES / OB_TYPE_LABELS / OB_ALLOWED_TYPES are now DYNAMIC functions
  // backed by _dynTypes; keep the names as thin wrappers so all existing call
  // sites that access them as objects/arrays still work without change.
  var OB_TYPE_STAGES = new Proxy({}, {
    get: function(t, key) { var dt = dynTypeById(key); return dt ? dt.stages : ['Initial Review','Final Approval']; },
    has: function(t, key) { return !!dynTypeById(key); }
  });
  // Some code accesses OB_TYPE_STAGES[key] directly — the Proxy handles that.
  // OB_TYPE_LABELS: used as OB_TYPE_LABELS[key]
  var OB_TYPE_LABELS = new Proxy({}, {
    get: function(t, key) { var dt = dynTypeById(key); return dt ? dt.label : (key || ''); }
  });
  // OB_ALLOWED_TYPES: used via .map() — must be a live array
  function _getOBAllowedTypes() { return _dynTypes.map(function(t){ return {value:t.id, label:t.label}; }); }
  // Shim: make OB_ALLOWED_TYPES behave like an array by using a getter pattern.
  // Code that calls OB_ALLOWED_TYPES.map(...) needs a real array.
  // We replace the variable each time types reload, and ensure initial value is correct.
  var OB_ALLOWED_TYPES = _getOBAllowedTypes();
  var OB_DEGREES = [
    // School of Business
    {value:'emba',                     label:"Executive Master's in Business Administration",                        level:'masters',    school:'Business'},
    {value:'mba',                      label:'Master of Business Administration',                                    level:'masters',    school:'Business'},
    {value:'ms-healthcare-admin',      label:'MS in Healthcare Administration',                                      level:'masters',    school:'Business'},
    {value:'ms-management',            label:'MS in Management',                                                     level:'masters',    school:'Business'},
    {value:'ms-management-leadership', label:'MS in Management and Leadership',                                      level:'masters',    school:'Business'},
    {value:'ms-org-leadership',        label:'MS in Organizational Leadership',                                      level:'masters',    school:'Business'},
    {value:'ms-tech-product-mgmt',     label:'MS in Technology and Product Management',                              level:'masters',    school:'Business'},
    {value:'ms-project-mgmt',          label:"Master's in Project Management",                                       level:'masters',    school:'Business'},
    {value:'ms-hrm',                   label:'MS Human Resources Management',                                        level:'masters',    school:'Business'},
    {value:'dba',                      label:'Doctor of Business Administration',                                    level:'doctoral',   school:'Business'},
    // School of Education
    {value:'med-alt-cert',             label:'M.Ed. Alternative Routes to Certification',                            level:'masters',    school:'Education'},
    {value:'med-curriculum',           label:'M.Ed. in Curriculum and Instruction',                                  level:'masters',    school:'Education'},
    {value:'mit',                      label:'Master in Teaching',                                                   level:'masters',    school:'Education'},
    {value:'med-adult-ed',             label:'M.Ed. in Adult Education and Instructional Design',                    level:'masters',    school:'Education'},
    {value:'med-ed-leadership-cert',   label:'M.Ed. in Educational Leadership: Administrator Certification',         level:'masters',    school:'Education'},
    {value:'med-elementary',           label:'M.Ed. in Elementary Education',                                        level:'masters',    school:'Education'},
    {value:'med-leadership',           label:'M.Ed. in Leadership',                                                  level:'masters',    school:'Education'},
    {value:'med-reading',              label:'M.Ed. in Reading Instruction',                                         level:'masters',    school:'Education'},
    {value:'med-special-ed',           label:'M.Ed. in Special Education',                                           level:'masters',    school:'Education'},
    {value:'edd-leadership',           label:'Doctor of Education in Leadership',                                    level:'doctoral',   school:'Education'},
    {value:'eds-leadership',           label:'Ed.S. in Leadership',                                                  level:'specialist', school:'Education'},
    // School of Technology
    {value:'ms-ai',                    label:'MS in Artificial Intelligence',                                        level:'masters',    school:'Technology'},
    {value:'ms-cs',                    label:'MS in Computer Science',                                               level:'masters',    school:'Technology'},
    {value:'ms-cybersecurity',         label:'MS in Cybersecurity',                                                  level:'masters',    school:'Technology'},
    {value:'ms-data-science',          label:'MS in Data Science',                                                   level:'masters',    school:'Technology'},
    {value:'dit',                      label:'Doctor of Information Technology',                                     level:'doctoral',   school:'Technology'}
  ];
  var OB_DEGREE_SCHOOLS = ['Business', 'Education', 'Technology'];

  var LEVEL_BADGES = {
    'masters':    '<span class="rrp-badge-masters">Master&#x2019;s</span>',
    'doctoral':   '<span class="rrp-badge-doctoral">Doctoral</span>',
    'specialist': '<span class="rrp-badge-specialist">Specialist</span>'
  };

  var LEVEL_BADGES = {
    'masters':    '<span class="rrp-badge-masters">Master&#x2019;s</span>',
    'doctoral':   '<span class="rrp-badge-doctoral">Doctoral</span>',
    'specialist': '<span class="rrp-badge-specialist">Specialist</span>'
  };

  // ── Submission Types Admin Panel ──────────────────────────────────────────
  // ── Workflow Stages Library ───────────────────────────────────────────────
  function renderWorkflowStagesPanel(container, backFn) {
    var _backFn = backFn || function () { renderCoordinatorDashboard(container); };
    var _stages = [];

    function renderList() {
      container.innerHTML =
        '<div class="rrp-dashboard">' +
          '<div class="rrp-dash-header">' +
            '<button class="rrp-btn secondary rrp-back-btn">&#8592; Back</button>' +
            '<h1>&#9881; Workflow Stages</h1>' +
            '<button class="rrp-btn primary" id="rrp-ws-add-btn">+ Add Stage</button>' +
          '</div>' +
          '<p style="color:#6b7280;max-width:640px;margin-bottom:1rem;line-height:1.5;">Define the reusable stage library. When configuring a submission type, admins pick stages from this list to build the workflow — ensuring consistent naming across all types.</p>' +
          '<p class="rrp-loading" id="rrp-ws-msg">Loading&hellip;</p>' +
          '<div id="rrp-ws-list"></div>' +
        '</div>';

      container.querySelector('.rrp-back-btn').addEventListener('click', _backFn);
      document.getElementById('rrp-ws-add-btn').addEventListener('click', function () { renderForm(null); });

      api('GET', '/workflow-stages').then(function (res) {
        _stages = res.workflowStages || [];
        document.getElementById('rrp-ws-msg').style.display = 'none';
        var listEl = document.getElementById('rrp-ws-list');
        if (!_stages.length) {
          listEl.innerHTML = '<p style="color:#6b7280;">No stages defined yet. Click <b>+ Add Stage</b> to create one.</p>';
          return;
        }
        listEl.innerHTML = _stages.map(function (s) {
          var badges = '';
          if (s.singleUser) badges += '<span class="rrp-ws-badge rrp-ws-badge-single">Single User</span>';
          if (s.multiUser)  badges += '<span class="rrp-ws-badge rrp-ws-badge-multi">Multi User</span>';
          return '<div class="rrp-type-row" id="rrp-ws-row-' + escapeHtml(s.id) + '">' +
            '<div class="rrp-type-info">' +
              '<div class="rrp-type-title" style="display:flex;align-items:center;gap:.6rem;">' +
                '<strong>' + escapeHtml(s.name) + '</strong>' + badges +
              '</div>' +
            '</div>' +
            '<div class="rrp-type-actions">' +
              '<button class="rrp-btn small secondary rrp-ws-edit-btn" data-id="' + escapeHtml(s.id) + '">Edit</button>' +
              '<button class="rrp-btn small danger rrp-ws-del-btn" data-id="' + escapeHtml(s.id) + '">Delete</button>' +
            '</div>' +
          '</div>';
        }).join('');

        listEl.querySelectorAll('.rrp-ws-edit-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var s = _stages.find(function (x) { return x.id === btn.getAttribute('data-id'); });
            if (s) renderForm(s);
          });
        });
        listEl.querySelectorAll('.rrp-ws-del-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var sid = btn.getAttribute('data-id');
            var s   = _stages.find(function (x) { return x.id === sid; });
            if (!confirm('Delete stage "' + escapeHtml(s ? s.name : sid) + '"?\n\nSubmission types using this stage will keep the stage name but you should update them.')) return;
            btn.disabled = true; btn.textContent = 'Deleting\u2026';
            api('DELETE', '/workflow-stages/' + encodeURIComponent(sid))
              .then(function () { renderList(); })
              .catch(function (e) {
                btn.disabled = false; btn.textContent = 'Delete';
                alert('Delete failed: ' + (e && e.message ? e.message : 'Unknown error'));
              });
          });
        });
      }).catch(function () {
        document.getElementById('rrp-ws-msg').textContent = 'Failed to load workflow stages.';
      });
    }

    function renderForm(stageObj) {
      var isEdit = !!stageObj;
      container.innerHTML =
        '<div class="rrp-dashboard">' +
          '<div class="rrp-dash-header">' +
            '<button class="rrp-btn secondary" id="rrp-ws-form-back">&#8592; Back to stages</button>' +
            '<h1>' + (isEdit ? 'Edit Stage' : 'Add Stage') + '</h1>' +
          '</div>' +
          '<div class="rrp-card" style="max-width:480px;">' +
            '<label class="rrp-label">Stage Name<br>' +
              '<input type="text" class="rrp-input" id="rrp-ws-name" value="' + escapeHtml(isEdit ? stageObj.name : '') + '" placeholder="e.g. Peer Review" />' +
            '</label>' +
            '<div class="rrp-label" style="margin-top:1rem;">Assignment Type' +
              '<div style="display:flex;gap:1.5rem;margin-top:.5rem;padding:.75rem;background:#f8f9fa;border-radius:6px;border:1px solid #dee2e6;">' +
                '<label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">' +
                  '<input type="checkbox" id="rrp-ws-single"' + (isEdit ? (stageObj.singleUser ? ' checked' : '') : ' checked') + '> Single User' +
                '</label>' +
                '<label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;">' +
                  '<input type="checkbox" id="rrp-ws-multi"' + (isEdit && stageObj.multiUser ? ' checked' : '') + '> Multi User' +
                '</label>' +
              '</div>' +
              '<small style="color:#6b7280;margin-top:.3rem;display:block;">Single User: only one assignee allowed. Multi User: multiple reviewers can be assigned.</small>' +
            '</div>' +
            '<div style="margin-top:1.25rem;display:flex;gap:.6rem;">' +
              '<button type="button" class="rrp-btn primary" id="rrp-ws-save-btn">' + (isEdit ? 'Save Changes' : 'Create Stage') + '</button>' +
              '<button type="button" class="rrp-btn secondary" id="rrp-ws-cancel-btn">Cancel</button>' +
            '</div>' +
            '<p id="rrp-ws-form-msg" style="min-height:1.3rem;margin-top:.5rem;"></p>' +
          '</div>' +
        '</div>';

      document.getElementById('rrp-ws-form-back').addEventListener('click', renderList);
      document.getElementById('rrp-ws-cancel-btn').addEventListener('click', renderList);
      document.getElementById('rrp-ws-save-btn').addEventListener('click', function () {
        var nameVal = document.getElementById('rrp-ws-name').value.trim();
        var single  = document.getElementById('rrp-ws-single').checked;
        var multi   = document.getElementById('rrp-ws-multi').checked;
        var msgEl   = document.getElementById('rrp-ws-form-msg');
        var saveBtn = document.getElementById('rrp-ws-save-btn');
        if (!nameVal) { msgEl.innerHTML = '<span class="rrp-error">Stage name is required.</span>'; return; }
        if (!single && !multi) { msgEl.innerHTML = '<span class="rrp-error">Select at least one assignment type.</span>'; return; }
        var stageId = isEdit ? stageObj.id : nameVal.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        saveBtn.disabled = true; saveBtn.textContent = isEdit ? 'Saving\u2026' : 'Creating\u2026';
        msgEl.innerHTML = '';
        api('PATCH', '/workflow-stages/' + encodeURIComponent(stageId), { name: nameVal, singleUser: single, multiUser: multi })
          .then(function () {
            msgEl.innerHTML = '<span style="color:#16a34a;">' + (isEdit ? 'Saved.' : 'Created.') + '</span>';
            saveBtn.disabled = false; saveBtn.textContent = isEdit ? 'Save Changes' : 'Create Stage';
            setTimeout(renderList, 900);
          })
          .catch(function (err) {
            saveBtn.disabled = false; saveBtn.textContent = isEdit ? 'Save Changes' : 'Create Stage';
            msgEl.innerHTML = '<span class="rrp-error">Save failed: ' + escapeHtml(err && err.message ? err.message : 'Unknown') + '</span>';
          });
      });
    }

    renderList();
  }

  function renderSubmissionTypesPanel(container, backFn) {
    var _backFn = backFn || function () { renderCoordinatorDashboard(container); };
    var _types  = [];   // working copy fetched from API

    // ── Build stages editor HTML for a type object (or blank) ────────────
    // allStages = array of {id, name, singleUser, multiUser} from workflow-stages library
    function stagesEditorHtml(stages, prefix, allStages, stageDaysMap) {
      var _daysMap = stageDaysMap || {};
      var rows = (stages || []).map(function (s, i) {
        var days = (_daysMap[s] != null) ? _daysMap[s] : 7;
        return '<div class="rrp-stage-row" data-index="' + i + '">' +
          '<input type="hidden" class="rrp-stage-input" value="' + escapeHtml(s) + '" />' +
          '<span class="rrp-stage-name">' + escapeHtml(s) + '</span>' +
          '<label style="display:flex;align-items:center;gap:.3rem;font-size:.82rem;color:#555;white-space:nowrap;">Due days<input type="number" class="rrp-input rrp-stage-days-input" style="width:62px;padding:.2rem .35rem;" min="1" max="365" value="' + escapeHtml(String(days)) + '"></label>' +
          '<button type="button" class="rrp-btn icon rrp-stage-up" title="Move up">&#8593;</button>' +
          '<button type="button" class="rrp-btn icon rrp-stage-down" title="Move down">&#8595;</button>' +
          '<button type="button" class="rrp-btn icon rrp-stage-del" title="Remove">&#10005;</button>' +
        '</div>';
      }).join('');
      var pickerHtml;
      if (allStages && allStages.length) {
        pickerHtml = '<select class="rrp-input rrp-stage-select" style="flex:1;">' +
          '<option value="">&#8212; Select a stage to add &#8212;</option>' +
          allStages.map(function (s) {
            var tag = s.multiUser ? ' (multi)' : ' (single)';
            return '<option value="' + escapeHtml(s.name) + '">' + escapeHtml(s.name) + tag + '</option>';
          }).join('') +
          '</select>';
      } else {
        pickerHtml = '<input type="text" class="rrp-input rrp-stage-new-input" placeholder="No library stages yet &mdash; type a name" style="flex:1;" />';
      }
      return '<div class="rrp-stages-editor" id="stages-list-' + prefix + '">' +
          rows +
        '</div>' +
        '<div style="display:flex;gap:.4rem;margin-top:.4rem;">' +
          pickerHtml +
          '<button type="button" class="rrp-btn small rrp-stage-add-btn">+ Add</button>' +
        '</div>';
    }

    // ── Wire up stages editor events inside a container element ──────────
    function bindStagesEditor(wrap) {
      var list = wrap.querySelector('.rrp-stages-editor');

      wrap.querySelector('.rrp-stage-add-btn').addEventListener('click', function () {
        // Support both select-picker (library) and text-input (fallback)
        var sel = wrap.querySelector('.rrp-stage-select');
        var inp = wrap.querySelector('.rrp-stage-new-input');
        var val = sel ? sel.value.trim() : (inp ? inp.value.trim() : ''); if (!val) return;
        var row = document.createElement('div');
        row.className = 'rrp-stage-row';
        row.innerHTML = '<input type="hidden" class="rrp-stage-input" value="' + escapeHtml(val) + '" />' +
          '<span class="rrp-stage-name">' + escapeHtml(val) + '</span>' +
          '<label style="display:flex;align-items:center;gap:.3rem;font-size:.82rem;color:#555;white-space:nowrap;">Due days<input type="number" class="rrp-input rrp-stage-days-input" style="width:62px;padding:.2rem .35rem;" min="1" max="365" value="7"></label>' +
          '<button type="button" class="rrp-btn icon rrp-stage-up" title="Move up">&#8593;</button>' +
          '<button type="button" class="rrp-btn icon rrp-stage-down" title="Move down">&#8595;</button>' +
          '<button type="button" class="rrp-btn icon rrp-stage-del" title="Remove">&#10005;</button>';
        bindRowEvents(row);
        list.appendChild(row);
        if (sel) sel.value = '';
        else if (inp) { inp.value = ''; inp.focus(); }
      });

      list.querySelectorAll('.rrp-stage-row').forEach(bindRowEvents);

      function bindRowEvents(row) {
        row.querySelector('.rrp-stage-del').addEventListener('click', function () { row.remove(); });
        row.querySelector('.rrp-stage-up').addEventListener('click', function () {
          var prev = row.previousElementSibling; if (prev) list.insertBefore(row, prev);
        });
        row.querySelector('.rrp-stage-down').addEventListener('click', function () {
          var next = row.nextElementSibling; if (next) list.insertBefore(next, row);
        });
      }
    }

    // ── Read stages from editor DOM ───────────────────────────────────────
    function readStages(wrap) {
      return Array.from(wrap.querySelectorAll('.rrp-stage-input'))
        .map(function (i) { return i.value.trim(); })
        .filter(Boolean);
    }

    // ── Read per-stage days map from editor DOM ───────────────────────────
    function readStageDays(wrap) {
      var map = {};
      wrap.querySelectorAll('.rrp-stage-row').forEach(function (row) {
        var nameInput = row.querySelector('.rrp-stage-input');
        var daysInput = row.querySelector('.rrp-stage-days-input');
        if (nameInput && daysInput) {
          var name = nameInput.value.trim();
          if (name) map[name] = Math.max(1, parseInt(daysInput.value, 10) || 7);
        }
      });
      return map;
    }

    // ── Render the type list ──────────────────────────────────────────────
    function renderList() {
      container.innerHTML =
        '<div class="rrp-dashboard">' +
          '<div class="rrp-dash-header">' +
            '<button class="rrp-btn secondary rrp-back-btn">&#8592; Back</button>' +
            '<h1>Submission Types</h1>' +
            '<button class="rrp-btn primary" id="rrp-st-add-btn">+ Add Type</button>' +
          '</div>' +
          '<p class="rrp-loading" id="rrp-st-msg">Loading&hellip;</p>' +
          '<div id="rrp-st-list"></div>' +
        '</div>';

      container.querySelector('.rrp-back-btn').addEventListener('click', _backFn);

      document.getElementById('rrp-st-add-btn').addEventListener('click', function () {
        renderForm(null);
      });

      Promise.all([
        api('GET', '/submission-types'),
        api('GET', '/config').catch(function () { return {}; })
      ]).then(function (res) {
        _types = (res[0] && (res[0].submission_types || res[0].submissionTypes)) || res[0] || [];
        var _sdd = (res[1] && res[1].stageDueDays) ? res[1].stageDueDays : {};
        document.getElementById('rrp-st-msg').style.display = 'none';
        var listEl = document.getElementById('rrp-st-list');
        if (!_types.length) {
          listEl.innerHTML = '<p style="color:#666;">No submission types defined yet. Click <b>+ Add Type</b> to create one.</p>';
          return;
        }

        listEl.innerHTML = _types.map(function (t) {
          var typeDays = (_sdd[t.id] && typeof _sdd[t.id] === 'object') ? _sdd[t.id] : {};
          var stagesHtml = (t.stages || []).map(function (s, i) {
            var d = typeDays[s] != null ? typeDays[s] : '?';
            return '<span class="rrp-stage-pill">' + (i + 1) + '. ' + escapeHtml(s) +
              ' <span style="color:#888;font-size:.8em;">(' + d + 'd)</span></span>';
          }).join(' ');
          return '<div class="rrp-type-row" id="rrp-type-row-' + escapeHtml(t.id) + '">' +
            '<div class="rrp-type-info">' +
              '<div class="rrp-type-title">' +
                '<strong>' + escapeHtml(t.label) + '</strong>' +
                ' <code class="rrp-type-id">' + escapeHtml(t.id) + '</code>' +
              '</div>' +
              (t.description ? '<div class="rrp-type-desc">' + escapeHtml(t.description) + '</div>' : '') +
              '<div class="rrp-type-stages">' + (stagesHtml || '<em style="color:#999;">No stages</em>') + '</div>' +
            '</div>' +
            '<div class="rrp-type-actions">' +
              '<button class="rrp-btn small secondary rrp-st-edit-btn" data-id="' + escapeHtml(t.id) + '">Edit</button>' +
              '<button class="rrp-btn small danger rrp-st-del-btn"  data-id="' + escapeHtml(t.id) + '">Delete</button>' +
            '</div>' +
          '</div>';
        }).join('');

        listEl.querySelectorAll('.rrp-st-edit-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var tid = btn.getAttribute('data-id');
            var t   = _types.find(function (x) { return x.id === tid; });
            if (t) renderForm(t);
          });
        });

        listEl.querySelectorAll('.rrp-st-del-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var tid = btn.getAttribute('data-id');
            var t   = _types.find(function (x) { return x.id === tid; });
            if (!confirm('Delete submission type "' + (t ? t.label : tid) + '"?\n\nThis cannot be undone. Existing submissions of this type will not be affected.')) return;
            btn.disabled = true; btn.textContent = 'Deleting…';
            api('DELETE', '/submission-types/' + encodeURIComponent(tid))
              .then(function () { renderList(); })
              .catch(function (e) {
                btn.disabled = false; btn.textContent = 'Delete';
                alert('Delete failed: ' + (e && e.message ? e.message : 'Unknown error'));
              });
          });
        });
      }).catch(function () {
        document.getElementById('rrp-st-msg').textContent = 'Failed to load submission types.';
      });
    }

    // ── Render add / edit form ────────────────────────────────────────────
    function renderForm(typeObj) {
      var isEdit  = !!typeObj;
      var formId  = 'rrp-st-form';
      var prefix  = isEdit ? typeObj.id : 'new';

      // Show a loading state while fetching the stage library + config
      container.innerHTML = '<div class="rrp-dashboard"><p class="rrp-loading">Loading stage library&hellip;</p></div>';

      Promise.all([
        api('GET', '/workflow-stages'),
        api('GET', '/config').catch(function () { return {}; })
      ]).then(function (stRes) {
        var allStages = (stRes[0] && stRes[0].workflowStages) ? stRes[0].workflowStages : [];
        var cfg       = stRes[1] || {};
        var curDays   = (isEdit && cfg.stageDueDays && cfg.stageDueDays[typeObj.id]) ? cfg.stageDueDays[typeObj.id] : {};

      container.innerHTML =
        '<div class="rrp-dashboard">' +
          '<div class="rrp-dash-header">' +
            '<button class="rrp-btn secondary" id="rrp-st-form-back">&#8592; Back to types</button>' +
            '<h1>' + (isEdit ? 'Edit Submission Type' : 'Add Submission Type') + '</h1>' +
          '</div>' +
          '<div class="rrp-card" style="max-width:640px;">' +
            '<form id="' + formId + '">' +
              '<label class="rrp-label">ID (slug)<br>' +
                '<input type="text" class="rrp-input" id="rrp-st-id" ' +
                  'value="' + escapeHtml(isEdit ? typeObj.id : '') + '" ' +
                  (isEdit ? 'readonly style="background:#f1f5f9;cursor:not-allowed;"' : '') +
                  ' placeholder="e.g. conference" required pattern="[a-z0-9-]+" />' +
                (isEdit ? '' : '<small style="color:#666;">Lowercase letters, numbers and hyphens only. Cannot be changed after creation.</small>') +
              '</label>' +
              '<label class="rrp-label" style="margin-top:.75rem;">Label<br>' +
                '<input type="text" class="rrp-input" id="rrp-st-label" value="' + escapeHtml(isEdit ? typeObj.label : '') + '" placeholder="e.g. Conference Paper" required />' +
              '</label>' +
              '<label class="rrp-label" style="margin-top:.75rem;">Description (optional)<br>' +
                '<input type="text" class="rrp-input" id="rrp-st-desc" value="' + escapeHtml(isEdit ? (typeObj.description || '') : '') + '" placeholder="Brief description shown to users" />' +
              '</label>' +
              '<div class="rrp-label" style="margin-top:.75rem;">Workflow Stages (in order)<br>' +
                '<small style="color:#6b7280;">Select stages from the library and arrange them in order. Manage the stage library under Config &rarr; Workflow Stages.</small>' +
                '<div style="margin-top:.4rem;" id="rrp-st-stages-wrap">' +
                  stagesEditorHtml(isEdit ? typeObj.stages : [], prefix, allStages, curDays) +
                '</div>' +
              '</div>' +
              '<div style="margin-top:1.25rem;display:flex;gap:.6rem;">' +
                '<button type="submit" class="rrp-btn primary" id="rrp-st-save-btn">' + (isEdit ? 'Save Changes' : 'Create Type') + '</button>' +
                '<button type="button" class="rrp-btn secondary" id="rrp-st-cancel-btn">Cancel</button>' +
              '</div>' +
              '<p class="rrp-form-msg" id="rrp-st-form-msg" style="display:none;"></p>' +
            '</form>' +
          '</div>' +
        '</div>';

      document.getElementById('rrp-st-form-back').addEventListener('click', renderList);
      document.getElementById('rrp-st-cancel-btn').addEventListener('click', renderList);

      var stagesWrap = document.getElementById('rrp-st-stages-wrap');
      bindStagesEditor(stagesWrap);

      document.getElementById(formId).addEventListener('submit', function (e) {
        e.preventDefault();
        var idVal    = document.getElementById('rrp-st-id').value.trim();
        var labelVal = document.getElementById('rrp-st-label').value.trim();
        var descVal  = document.getElementById('rrp-st-desc').value.trim();
        var stages   = readStages(stagesWrap);
        var msgEl    = document.getElementById('rrp-st-form-msg');
        var saveBtn  = document.getElementById('rrp-st-save-btn');

        if (!idVal || !labelVal) {
          msgEl.style.display = 'block'; msgEl.style.color = '#ef4444';
          msgEl.textContent = 'ID and Label are required.'; return;
        }
        if (!isEdit && !/^[a-z0-9-]+$/.test(idVal)) {
          msgEl.style.display = 'block'; msgEl.style.color = '#ef4444';
          msgEl.textContent = 'ID must be lowercase letters, numbers, and hyphens only.'; return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = isEdit ? 'Saving\u2026' : 'Creating\u2026';
        msgEl.style.display = 'none';

        var stageDaysPayload = readStageDays(stagesWrap);
        var payload = { id: idVal, label: labelVal, stages: stages };
        if (descVal) payload.description = descVal;

        api('PATCH', '/submission-types/' + encodeURIComponent(idVal), payload)
          .then(function () {
            if (Object.keys(stageDaysPayload).length) {
              var sdd = {}; sdd[idVal] = stageDaysPayload;
              return api('PUT', '/config', { stageDueDays: sdd });
            }
          })
          .then(function () {
          // Refresh _dynTypes so rest of UI reflects the change immediately
          _refreshDynTypes(function () { OB_ALLOWED_TYPES = _getOBAllowedTypes(); });
          msgEl.style.display = 'block'; msgEl.style.color = '#16a34a';
          msgEl.textContent   = isEdit ? 'Saved successfully.' : 'Submission type created.';
          saveBtn.disabled    = false;
          saveBtn.textContent = isEdit ? 'Save Changes' : 'Create Type';
          setTimeout(renderList, 1200);
          }).catch(function (err) {
            saveBtn.disabled    = false;
            saveBtn.textContent = isEdit ? 'Save Changes' : 'Create Type';
            msgEl.style.display = 'block'; msgEl.style.color = '#ef4444';
            msgEl.textContent   = 'Save failed: ' + (err && err.message ? err.message : 'Unknown error');
          });
      });

      }).catch(function () {
        // Stage library fetch failed — fall back to the text-input editor
        container.innerHTML = '<div class="rrp-dashboard"><p class="rrp-error">Failed to load stage library. Please refresh and try again.</p>' +
          '<button class="rrp-btn secondary" id="rrp-st-err-back">&#8592; Back</button></div>';
        document.getElementById('rrp-st-err-back').addEventListener('click', renderList);
      });
    }

    renderList();
  }

  // ── Admin User Management ─────────────────────────────────────────────────
  // ── Users Panel (unified list — replaces the old tabbed User Management) ─────
  function renderUsersPanel(container, backFn, initialRoleFilter) {
    var _backFn = backFn || function () { renderCoordinatorDashboard(container); };
    var searchTerm = '';
    var roleFilter = initialRoleFilter || 'all';
    var ROLE_SLUGS = ['rrp_student', 'rrp_reviewer', 'rrp_coordinator', 'rrp_admin', 'rrp_faculty'];
    var ROLE_META  = {
      rrp_student:     { label: 'Student',     color: '#3b82f6' },
      rrp_reviewer:    { label: 'Reviewer',    color: '#8b5cf6' },
      rrp_coordinator: { label: 'Coordinator', color: '#f59e0b' },
      rrp_admin:       { label: 'Admin',       color: '#ef4444' },
      rrp_faculty:     { label: 'Faculty',     color: '#10b981' }
    };
    var _allUsers = [];

    function roleBadges(wpRoles) {
      return (wpRoles || []).map(function (r) {
        var m = ROLE_META[r]; if (!m) return '';
        return '<span style="display:inline-block;padding:.15rem .5rem;border-radius:20px;' +
          'background:' + m.color + '22;color:' + m.color + ';border:1px solid ' + m.color + '44;' +
          'font-size:.73rem;font-weight:600;margin-right:.2rem;">' + m.label + '</span>';
      }).join('');
    }

    function renderPage() {
      container.innerHTML =
        '<div class="rrp-mgmt-page-header">' +
          '<button type="button" class="rrp-btn secondary" id="rrp-users-back">&#8592; Back</button>' +
          '<h1>&#128101; Users</h1>' +
          '<button type="button" class="rrp-btn" id="rrp-users-add-btn">&#43; Add User</button>' +
        '</div>' +
        '<div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap;">' +
          '<input type="search" id="rrp-users-search" class="rrp-input" placeholder="&#128269; Search name or email&hellip;" style="flex:1;min-width:180px;max-width:340px;">' +
          '<select id="rrp-users-role-filter" class="rrp-input" style="max-width:175px;">' +
            '<option value="all"' + (roleFilter === 'all' ? ' selected' : '') + '>All groups</option>' +
            '<option value="rrp_student"' + (roleFilter === 'rrp_student' ? ' selected' : '') + '>Students</option>' +
            '<option value="rrp_reviewer"' + (roleFilter === 'rrp_reviewer' ? ' selected' : '') + '>Reviewers</option>' +
            '<option value="rrp_coordinator"' + (roleFilter === 'rrp_coordinator' ? ' selected' : '') + '>Coordinators</option>' +
            '<option value="rrp_admin"' + (roleFilter === 'rrp_admin' ? ' selected' : '') + '>Admins</option>' +
            '<option value="rrp_faculty"' + (roleFilter === 'rrp_faculty' ? ' selected' : '') + '>Faculty</option>' +
          '</select>' +
          '<span id="rrp-users-count" style="font-size:.85rem;color:var(--rrp-text-muted);white-space:nowrap;"></span>' +
        '</div>' +
        '<div id="rrp-users-add-wrap" style="display:none;margin-bottom:1.25rem;"></div>' +
        '<div id="rrp-users-list"><p class="rrp-loading">Loading&hellip;</p></div>';

      document.getElementById('rrp-users-back').addEventListener('click', function () { _backFn(); });
      document.getElementById('rrp-users-add-btn').addEventListener('click', renderAddUserInline);
      document.getElementById('rrp-users-search').addEventListener('input', function () {
        searchTerm = this.value.toLowerCase(); applyFilter();
      });
      document.getElementById('rrp-users-role-filter').addEventListener('change', function () {
        roleFilter = this.value; applyFilter();
      });

      loadAllUsers();
    }

    function loadAllUsers() {
      var listEl = document.getElementById('rrp-users-list');
      if (listEl) listEl.innerHTML = '<p class="rrp-loading">Loading users&hellip;</p>';
      Promise.all([
        api('GET', '/portal-users?role=student'),
        api('GET', '/portal-users?role=reviewer'),
        api('GET', '/portal-users?role=coordinator'),
        api('GET', '/portal-users?role=admin'),
        api('GET', '/portal-users?role=faculty')
      ]).then(function (results) {
        var seen = {};
        _allUsers = [];
        results.forEach(function (res, idx) {
          var slug = ROLE_SLUGS[idx];
          (res.users || []).forEach(function (u) {
            if (u.jsonOnly) return;
            if (seen[u.id]) {
              if (seen[u.id].wpRoles.indexOf(slug) === -1) seen[u.id].wpRoles.push(slug);
            } else {
              var entry = JSON.parse(JSON.stringify(u));
              if (!Array.isArray(entry.wpRoles) || entry.wpRoles.length === 0) entry.wpRoles = [slug];
              seen[u.id] = entry;
              _allUsers.push(entry);
            }
          });
        });
        _allUsers.sort(function (a, b) { return (a.name || a.email).localeCompare(b.name || b.email); });
        applyFilter();
      }).catch(function () {
        var listEl = document.getElementById('rrp-users-list');
        if (listEl) listEl.innerHTML = '<div class="rrp-error">Unable to load users.</div>';
      });
    }

    function applyFilter() {
      var filtered = _allUsers.filter(function (u) {
        var q = searchTerm;
        var ok = !q || (u.name || '').toLowerCase().indexOf(q) !== -1 || (u.email || '').toLowerCase().indexOf(q) !== -1;
        var rOk = roleFilter === 'all' || (u.wpRoles || []).indexOf(roleFilter) !== -1;
        return ok && rOk;
      });
      var countEl = document.getElementById('rrp-users-count');
      if (countEl) countEl.textContent = filtered.length + ' user' + (filtered.length === 1 ? '' : 's');
      renderUserList(filtered);
    }

    function renderUserList(users) {
      var listEl = document.getElementById('rrp-users-list');
      if (!listEl) return;
      if (users.length === 0) {
        listEl.innerHTML = '<div class="rrp-empty-state"><p>No users match your filter.</p></div>';
        return;
      }
      listEl.innerHTML =
        '<div class="rrp-user-mgmt-table">' +
          '<div class="rrp-umr-head"><span>Name</span><span>Email</span><span>Groups</span><span>Actions</span></div>' +
          users.map(function (u) {
            return '<div class="rrp-umr-row">' +
              '<span class="rrp-umr-name"><strong>' + escapeHtml(u.name || u.email) + '</strong></span>' +
              '<span class="rrp-umr-email">' + escapeHtml(u.email) + '</span>' +
              '<span>' + roleBadges(u.wpRoles) + '</span>' +
              '<span class="rrp-umr-actions">' +
                '<button type="button" class="rrp-btn secondary small" data-up-edit="' + u.id + '">Edit</button> ' +
                '<button type="button" class="rrp-btn secondary small" data-up-reset="' + u.id + '" title="Reset password">&#128274;</button> ' +
                '<button type="button" class="rrp-btn danger small" data-up-remove="' + u.id + '">Remove</button>' +
              '</span>' +
            '</div>';
          }).join('') +
        '</div>';

      listEl.querySelectorAll('[data-up-edit]').forEach(function (btn) {
        var uid  = btn.getAttribute('data-up-edit');
        var user = _allUsers.find(function (u) { return String(u.id) === uid; });
        btn.addEventListener('click', function () { renderEditUserInline(user); });
      });
      listEl.querySelectorAll('[data-up-reset]').forEach(function (btn) {
        var uid  = btn.getAttribute('data-up-reset');
        var user = _allUsers.find(function (u) { return String(u.id) === uid; });
        btn.addEventListener('click', function () { renderResetPwInline(user); });
      });
      listEl.querySelectorAll('[data-up-remove]').forEach(function (btn) {
        var uid  = btn.getAttribute('data-up-remove');
        var user = _allUsers.find(function (u) { return String(u.id) === uid; });
        btn.addEventListener('click', function () {
          if (!confirm('Remove all portal access for ' + escapeHtml(user ? (user.name || user.email) : 'this user') + '?\n\nTheir submission history is preserved.')) return;
          api('DELETE', '/portal-users/' + uid)
            .then(function () {
              _allUsers = _allUsers.filter(function (u) { return String(u.id) !== uid; });
              applyFilter();
            })
            .catch(function (err) { alert('Failed: ' + ((err && err.data && err.data.error) || 'Please try again.')); });
        });
      });
    }

    function renderEditUserInline(user) {
      if (!user) return;
      var listEl = document.getElementById('rrp-users-list');
      if (!listEl) return;
      var currentRoles = Array.isArray(user.wpRoles) && user.wpRoles.length ? user.wpRoles : (user.wpRole ? [user.wpRole] : ['rrp_student']);
      var hasStudent   = currentRoles.indexOf('rrp_student')     !== -1;
      var hasReviewer  = currentRoles.indexOf('rrp_reviewer')    !== -1 || currentRoles.indexOf('rrp_faculty') !== -1;
      var hasCoord     = currentRoles.indexOf('rrp_coordinator') !== -1 && !hasReviewer;

      var studentFields = hasStudent ?
        '<div class="rrp-form-group"><label>Degree / Program</label>' +
        '<select id="upe-degree" class="rrp-input">' +
          '<option value="">&#8212; Select &#8212;</option>' +
          OB_DEGREE_SCHOOLS.map(function (sch) {
            return '<optgroup label="School of ' + escapeHtml(sch) + '">' +
              OB_DEGREES.filter(function (d) { return d.school === sch; }).map(function (d) {
                return '<option value="' + escapeHtml(d.value) + '"' + (user.degree === d.value ? ' selected' : '') + '>' + escapeHtml(d.label) + '</option>';
              }).join('') + '</optgroup>';
          }).join('') +
        '</select></div>' : '';

      var deptField = (hasReviewer || hasCoord) ?
        '<div class="rrp-form-group"><label>Department</label><select id="upe-dept" class="rrp-input" data-dept-val="' + escapeHtml(user.department || '') + '"><option value="">Loading&hellip;</option></select></div>' : '';

      var expertiseField = hasReviewer ?
        '<div class="rrp-form-group"><label>Expertise</label><textarea id="upe-expertise" class="rrp-input" rows="2">' + escapeHtml(user.expertise || '') + '</textarea></div>' : '';

      var nameParts = (user.name || '').split(' ');
      var fn = nameParts[0] || ''; var ln = nameParts.slice(1).join(' ') || '';

      listEl.innerHTML =
        '<div class="rrp-analytics-card" style="border:2px solid var(--rrp-primary);">' +
          '<h3 style="margin:0 0 1rem;font-size:1rem;">Edit &mdash; ' + escapeHtml(user.name || user.email) +
            ' <span style="font-size:.8rem;font-weight:400;color:var(--rrp-text-muted);">' + escapeHtml(user.email) + '</span></h3>' +
          '<div class="rrp-form-group">' +
            (_viewerIsAdmin ?
              '<label style="font-weight:600;">Groups <em style="font-weight:400;font-size:.85rem;color:var(--rrp-text-muted);">(select one or more)</em></label>' +
              '<div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:.4rem;padding:.6rem .8rem;background:var(--rrp-bg-muted,#f8f9fa);border-radius:6px;border:1px solid var(--rrp-border,#dee2e6);">' +
                Object.keys(ROLE_META).map(function (slug) {
                  var ck = currentRoles.indexOf(slug) !== -1 ? ' checked' : '';
                  return '<label class="upe-role-label" style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.93rem;">' +
                    '<input type="checkbox" class="upe-role-check" value="' + slug + '"' + ck + '> ' + ROLE_META[slug].label + '</label>';
                }).join('') +
              '</div>' +
              '<small style="color:var(--rrp-text-muted);">Multi-group users get a tab-switcher in their portal view.</small>'
            : '') +
          '</div>' +
          '<div class="rrp-form-row" style="margin-top:.75rem;">' +
            '<div class="rrp-form-group"><label>First Name</label><input type="text" id="upe-first" class="rrp-input" value="' + escapeHtml(fn) + '"></div>' +
            '<div class="rrp-form-group"><label>Last Name</label><input type="text" id="upe-last" class="rrp-input" value="' + escapeHtml(ln) + '"></div>' +
          '</div>' +
          studentFields + deptField + expertiseField +
          '<div id="upe-msg" style="min-height:1.3rem;margin:.4rem 0;"></div>' +
          '<div style="display:flex;gap:.6rem;">' +
            '<button type="button" class="rrp-btn" id="upe-save-btn">&#10003; Save Changes</button>' +
            '<button type="button" class="rrp-btn secondary" id="upe-cancel-btn">&#8592; Cancel</button>' +
          '</div>' +
        '</div>';

      var deptSel = listEl.querySelector('#upe-dept');
      if (deptSel) {
        var saved = deptSel.getAttribute('data-dept-val') || '';
        api('GET', '/config').then(function (cfg) {
          var depts = cfg.departments || [];
          deptSel.innerHTML = '<option value="">&#8212; Select department &#8212;</option>' +
            depts.map(function (d) { return '<option value="' + escapeHtml(d.id) + '"' + (d.id === saved ? ' selected' : '') + '>' + escapeHtml(d.label) + '</option>'; }).join('');
        }).catch(function () { deptSel.innerHTML = '<option value="">Unable to load</option>'; });
      }

      listEl.querySelector('#upe-cancel-btn').addEventListener('click', function () { applyFilter(); });
      listEl.querySelector('#upe-save-btn').addEventListener('click', function () {
        var saveBtn = listEl.querySelector('#upe-save-btn');
        var msgEl   = listEl.querySelector('#upe-msg');
        var payload = {};
        if (_viewerIsAdmin) {
          var selectedRoles = [];
          listEl.querySelectorAll('.upe-role-check:checked').forEach(function (cb) { selectedRoles.push(cb.value); });
          if (selectedRoles.length === 0) { msgEl.innerHTML = '<span class="rrp-error">Select at least one group.</span>'; return; }
          payload.roles = selectedRoles;
        }
        var fnEl = listEl.querySelector('#upe-first'); var lnEl = listEl.querySelector('#upe-last');
        if (fnEl) payload.firstName  = fnEl.value.trim();
        if (lnEl) payload.lastName   = lnEl.value.trim();
        var degEl  = listEl.querySelector('#upe-degree');
        var deptEl = listEl.querySelector('#upe-dept');
        var expEl  = listEl.querySelector('#upe-expertise');
        if (degEl)  payload.degree     = degEl.value;
        if (deptEl) payload.department = deptEl.value;
        if (expEl)  payload.expertise  = expEl.value;
        saveBtn.disabled = true; saveBtn.textContent = 'Saving\u2026';
        msgEl.innerHTML = '';
        api('PATCH', '/portal-users/' + user.id, payload)
          .then(function () {
            var idx = _allUsers.indexOf(user);
            if (idx !== -1) {
              if (_viewerIsAdmin && payload.roles) { _allUsers[idx].wpRoles = payload.roles; }
              if (payload.firstName || payload.lastName) {
                _allUsers[idx].name = ((payload.firstName || '') + ' ' + (payload.lastName || '')).trim();
              }
            }
            applyFilter();
          })
          .catch(function (err) {
            saveBtn.disabled = false; saveBtn.textContent = '\u2713 Save Changes';
            msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.data && err.data.error) || 'Save failed.') + '</span>';
          });
      });
    }

    function renderResetPwInline(user) {
      if (!user) return;
      var listEl = document.getElementById('rrp-users-list');
      if (!listEl) return;
      listEl.innerHTML =
        '<div class="rrp-analytics-card" style="border:2px solid #f59e0b;">' +
          '<h3 style="margin:0 0 .5rem;font-size:1rem;">&#128274; Reset Password &mdash; ' + escapeHtml(user.name || user.email) + '</h3>' +
          '<div class="rrp-form-group"><label>New Password</label>' +
            '<input type="text" id="rp-pw" class="rrp-input" placeholder="Leave blank to auto-generate"></div>' +
          '<div id="rp-msg" style="min-height:1.3rem;margin:.4rem 0;"></div>' +
          '<div style="display:flex;gap:.6rem;">' +
            '<button type="button" class="rrp-btn" id="rp-save" style="background:#f59e0b;border-color:#f59e0b;">&#128274; Reset</button>' +
            '<button type="button" class="rrp-btn secondary" id="rp-cancel">&#8592; Cancel</button>' +
          '</div>' +
        '</div>';
      listEl.querySelector('#rp-cancel').addEventListener('click', function () { applyFilter(); });
      listEl.querySelector('#rp-save').addEventListener('click', function () {
        var saveBtn = listEl.querySelector('#rp-save');
        var msgEl   = listEl.querySelector('#rp-msg');
        var pw      = listEl.querySelector('#rp-pw').value.trim();
        var payload = {}; if (pw) payload.password = pw;
        saveBtn.disabled = true; saveBtn.textContent = 'Resetting\u2026';
        api('POST', '/portal-users/' + user.id + '/reset-password', payload)
          .then(function (res) {
            var newPw = res.newPassword || '';
            msgEl.innerHTML = '<span class="rrp-success">Password reset.' +
              (newPw ? ' New password: <code style="background:#f0f0f0;padding:.1rem .35rem;border-radius:3px;">' + escapeHtml(newPw) + '</code>' : '') + '</span>';
            saveBtn.disabled = false; saveBtn.textContent = '\u128274 Reset';
          })
          .catch(function (err) {
            saveBtn.disabled = false; saveBtn.textContent = '\u128274 Reset';
            msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.data && err.data.error) || 'Reset failed.') + '</span>';
          });
      });
    }

    function renderAddUserInline() {
      var addWrap = document.getElementById('rrp-users-add-wrap');
      if (!addWrap) return;
      addWrap.style.display = 'block';
      var addGroupFields = '';

      addWrap.innerHTML =
        '<div class="rrp-analytics-card" style="margin-bottom:1.25rem;border:2px solid var(--rrp-primary);">' +
          '<h3 style="margin:0 0 1rem;font-size:1rem;">&#43; Add New User</h3>' +
          '<div class="rrp-form-group">' +
            '<label style="font-weight:600;">Groups <em style="font-weight:400;font-size:.85rem;color:var(--rrp-text-muted);">(select one or more)</em></label>' +
            '<div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:.4rem;padding:.6rem .8rem;background:var(--rrp-bg-muted,#f8f9fa);border-radius:6px;border:1px solid var(--rrp-border,#dee2e6);">' +
              Object.keys(ROLE_META).map(function (slug) {
                return '<label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.93rem;">' +
                  '<input type="checkbox" class="upa-role-check" value="' + slug + '"> ' + ROLE_META[slug].label + '</label>';
              }).join('') +
            '</div>' +
          '</div>' +
          '<div id="upa-group-fields"></div>' +
          '<div class="rrp-form-row">' +
            '<div class="rrp-form-group"><label>First Name</label><input type="text" id="upa-first" class="rrp-input" placeholder="First name"></div>' +
            '<div class="rrp-form-group"><label>Last Name</label><input type="text" id="upa-last" class="rrp-input" placeholder="Last name"></div>' +
          '</div>' +
          '<div class="rrp-form-group"><label>Email <em>*</em></label><input type="email" id="upa-email" class="rrp-input" placeholder="user@cityuniversity.edu"></div>' +
          '<div class="rrp-form-group"><label>Password <em>(optional)</em></label>' +
            '<input type="text" id="upa-password" class="rrp-input" placeholder="Leave blank to auto-generate">' +
            '<small style="color:var(--rrp-text-muted);">A secure password is auto-generated if blank.</small></div>' +
          '<div id="upa-msg" style="min-height:1.3rem;margin:.4rem 0;"></div>' +
          '<div style="display:flex;gap:.6rem;">' +
            '<button type="button" class="rrp-btn" id="upa-save-btn">&#10003; Create User</button>' +
            '<button type="button" class="rrp-btn secondary" id="upa-cancel-btn">Cancel</button>' +
          '</div>' +
        '</div>';

      function updateGroupFields() {
        var checked = [];
        addWrap.querySelectorAll('.upa-role-check:checked').forEach(function (cb) { checked.push(cb.value); });
        var hasStu = checked.indexOf('rrp_student')     !== -1;
        var hasRev = checked.indexOf('rrp_reviewer')    !== -1 || checked.indexOf('rrp_faculty') !== -1;
        var hasCo  = checked.indexOf('rrp_coordinator') !== -1 && !hasRev;
        var gf = addWrap.querySelector('#upa-group-fields'); if (!gf) return;
        var html = '';
        if (hasStu) {
          html += '<div class="rrp-form-group"><label>Degree / Program</label>' +
            '<select id="upa-degree" class="rrp-input">' +
              '<option value="">&#8212; Select &#8212;</option>' +
              OB_DEGREE_SCHOOLS.map(function (sch) {
                return '<optgroup label="School of ' + escapeHtml(sch) + '">' +
                  OB_DEGREES.filter(function (d) { return d.school === sch; }).map(function (d) {
                    return '<option value="' + escapeHtml(d.value) + '">' + escapeHtml(d.label) + '</option>';
                  }).join('') + '</optgroup>';
              }).join('') +
            '</select></div>';
        }
        if (hasRev || hasCo) {
          html += '<div class="rrp-form-group"><label>Department</label>' +
            '<select id="upa-dept" class="rrp-input"><option value="">Loading&hellip;</option></select></div>';
        }
        if (hasRev) {
          html += '<div class="rrp-form-group"><label>Expertise</label>' +
            '<textarea id="upa-expertise" class="rrp-input" rows="2" placeholder="Areas of expertise, research interests&hellip;"></textarea></div>';
        }
        gf.innerHTML = html;
        var deptSel = gf.querySelector('#upa-dept');
        if (deptSel) {
          api('GET', '/config').then(function (cfg) {
            var depts = cfg.departments || [];
            deptSel.innerHTML = '<option value="">&#8212; Select department &#8212;</option>' +
              depts.map(function (d) { return '<option value="' + escapeHtml(d.id) + '">' + escapeHtml(d.label) + '</option>'; }).join('');
          }).catch(function () { deptSel.innerHTML = '<option value="">Unable to load</option>'; });
        }
      }

      addWrap.querySelectorAll('.upa-role-check').forEach(function (cb) {
        cb.addEventListener('change', updateGroupFields);
      });
      addWrap.querySelector('#upa-cancel-btn').addEventListener('click', function () {
        addWrap.style.display = 'none';
      });
      addWrap.querySelector('#upa-save-btn').addEventListener('click', function () {
        var saveBtn = addWrap.querySelector('#upa-save-btn');
        var msgEl   = addWrap.querySelector('#upa-msg');
        var roles   = [];
        addWrap.querySelectorAll('.upa-role-check:checked').forEach(function (cb) { roles.push(cb.value); });
        if (roles.length === 0) { msgEl.innerHTML = '<span class="rrp-error">Select at least one group.</span>'; return; }
        var email = addWrap.querySelector('#upa-email') ? addWrap.querySelector('#upa-email').value.trim() : '';
        if (!email) { msgEl.innerHTML = '<span class="rrp-error">Email is required.</span>'; return; }
        var payload = {
          firstName: addWrap.querySelector('#upa-first') ? addWrap.querySelector('#upa-first').value.trim() : '',
          lastName:  addWrap.querySelector('#upa-last')  ? addWrap.querySelector('#upa-last').value.trim() : '',
          email: email, roles: roles
        };
        var pw    = addWrap.querySelector('#upa-password') ? addWrap.querySelector('#upa-password').value : '';
        var degEl  = addWrap.querySelector('#upa-degree');
        var deptEl = addWrap.querySelector('#upa-dept');
        var expEl  = addWrap.querySelector('#upa-expertise');
        if (pw)    payload.password   = pw;
        if (degEl  && degEl.value)  payload.degree     = degEl.value;
        if (deptEl && deptEl.value) payload.department = deptEl.value;
        if (expEl  && expEl.value)  payload.expertise  = expEl.value;
        saveBtn.disabled = true; saveBtn.textContent = 'Creating\u2026';
        msgEl.innerHTML = '';
        api('POST', '/portal-users', payload)
          .then(function () { addWrap.style.display = 'none'; loadAllUsers(); })
          .catch(function (err) {
            saveBtn.disabled = false; saveBtn.textContent = '\u2713 Create User';
            msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.data && err.data.error) || 'Create failed.') + '</span>';
          });
      });
    }

    renderPage();
  }

  // ── User Management (tabbed by role — legacy, kept for reference) ──────────
  function renderAdminUserManagement(container, backFn) {
    var TABS = [
      { key: 'student',     label: '&#127891; Students',     role: 'rrp_student' },
      { key: 'reviewer',    label: '&#128064; Reviewers',    role: 'rrp_reviewer' },
      { key: 'coordinator', label: '&#9994; Coordinators',  role: 'rrp_coordinator' },
      { key: 'admin',       label: '&#128737; Admins',       role: 'rrp_admin' }
    ];
    var activeTab = TABS[0].key;

    function renderPage() {
      container.innerHTML =
        '<div class="rrp-mgmt-page-header">' +
          '<button type="button" class="rrp-btn secondary" id="rrp-ums-back">&#8592; Back</button>' +
          '<h1>&#128101; User Management</h1>' +
          '<span></span>' +
        '</div>' +
        '<div class="rrp-tabs" style="margin-bottom:1.25rem;">' +
          TABS.map(function (t) {
            return '<button class="rrp-tab' + (t.key === activeTab ? ' rrp-tab-active' : '') + '" data-ums-tab="' + t.key + '">' + t.label + '</button>';
          }).join('') +
        '</div>' +
        '<div id="rrp-ums-add-wrap" style="display:none;"></div>' +
        '<div id="rrp-ums-list"><p class="rrp-loading">Loading&hellip;</p></div>';

      document.getElementById('rrp-ums-back').addEventListener('click', function () {
        if (backFn) backFn(); else renderCoordinatorDashboard(container);
      });

      container.querySelectorAll('[data-ums-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          activeTab = btn.getAttribute('data-ums-tab');
          container.querySelectorAll('[data-ums-tab]').forEach(function (b) {
            b.classList.toggle('rrp-tab-active', b.getAttribute('data-ums-tab') === activeTab);
          });
          loadTab(activeTab);
        });
      });

      loadTab(activeTab);
    }

    function loadTab(tabKey) {
      var tab = TABS.find(function (t) { return t.key === tabKey; }) || TABS[0];
      var addWrap = document.getElementById('rrp-ums-add-wrap');
      var listEl  = document.getElementById('rrp-ums-list');
      if (addWrap) addWrap.style.display = 'none';
      if (listEl) listEl.innerHTML = '<p class="rrp-loading">Loading&hellip;</p>';

      var addBtn = container.querySelector('[data-ums-add]');
      if (addBtn) addBtn.remove();

      // Insert "Add User" button into header area
      var hdr = container.querySelector('.rrp-mgmt-page-header span:last-child');
      if (hdr) {
        hdr.innerHTML = '<button type="button" class="rrp-btn" data-ums-add="' + tab.key + '">&#43; Add ' + tab.key.charAt(0).toUpperCase() + tab.key.slice(1) + '</button>';
        hdr.querySelector('[data-ums-add]').addEventListener('click', function () {
          renderAddUserForm(tab);
        });
      }

      api('GET', '/portal-users?role=' + tabKey).then(function (res) {
        var users = res.users || [];
        if (!listEl) return;
        if (users.length === 0) {
          listEl.innerHTML = '<div class="rrp-empty-state"><p>No ' + tab.key + ' accounts yet. Click <strong>&#43; Add ' + tab.key.charAt(0).toUpperCase() + tab.key.slice(1) + '</strong> to create one.</p></div>';
          return;
        }
        listEl.innerHTML =
          '<div class="rrp-user-mgmt-table">' +
            '<div class="rrp-umr-head"><span>Name</span><span>Email</span><span>Info</span><span>Actions</span></div>' +
            users.map(function (u) {
              var info = '';
              if (tabKey === 'student')     info = escapeHtml(u.degree || '—');
              if (tabKey === 'reviewer' || tabKey === 'faculty') info = escapeHtml(u.department || '—');
              if (tabKey === 'coordinator') info = (u.programIds && u.programIds.length) ? u.programIds.length + ' programs' : '—';
              if (tabKey === 'admin')       info = 'Admin';
              return '<div class="rrp-umr-row">' +
                '<span class="rrp-umr-name"><strong>' + escapeHtml(u.name || u.email) + '</strong></span>' +
                '<span class="rrp-umr-email">' + escapeHtml(u.email) + '</span>' +
                '<span style="font-size:.85rem;">' + info + '</span>' +
                '<span class="rrp-umr-actions">' +
                  '<button type="button" class="rrp-btn secondary small" data-ums-edit="' + u.id + '">Edit</button> ' +
                  '<button type="button" class="rrp-btn secondary small" data-ums-reset="' + u.id + '" title="Reset password">&#128274; Reset PW</button> ' +
                  '<button type="button" class="rrp-btn danger small" data-ums-remove="' + u.id + '">Remove</button>' +
                '</span>' +
              '</div>';
            }).join('') +
          '</div>';

        listEl.querySelectorAll('[data-ums-edit]').forEach(function (btn) {
          var uid  = btn.getAttribute('data-ums-edit');
          var user = users.find(function (u) { return String(u.id) === uid; });
          btn.addEventListener('click', function () { renderEditUserForm(tab, user); });
        });
        listEl.querySelectorAll('[data-ums-reset]').forEach(function (btn) {
          var uid  = btn.getAttribute('data-ums-reset');
          var user = users.find(function (u) { return String(u.id) === uid; });
          btn.addEventListener('click', function () { renderResetPasswordForm(user); });
        });
        listEl.querySelectorAll('[data-ums-remove]').forEach(function (btn) {
          var uid  = btn.getAttribute('data-ums-remove');
          var user = users.find(function (u) { return String(u.id) === uid; });
          btn.addEventListener('click', function () {
            if (!confirm('Remove portal access for ' + escapeHtml(user ? (user.name || user.email) : 'this user') + '?\nThey will lose the ' + tab.key + ' role but remain as a WordPress user.')) return;
            api('DELETE', '/portal-users/' + uid)
              .then(function () { loadTab(activeTab); })
              .catch(function (err) { alert('Failed: ' + ((err && err.data && err.data.error) || 'Please try again.')); });
          });
        });

      }).catch(function () {
        if (listEl) listEl.innerHTML = '<div class="rrp-error">Unable to load users.</div>';
      });
    }

    function renderAddUserForm(tab) {
      var addWrap = document.getElementById('rrp-ums-add-wrap');
      if (!addWrap) return;
      addWrap.style.display = 'block';

      var extraFields = '';
      if (tab.key === 'student') {
        extraFields =
          '<div class="rrp-form-group"><label>Degree / Program</label>' +
          '<select id="umf-degree" class="rrp-input">' +
            '<option value="">&#8212; Select &#8212;</option>' +
            OB_DEGREE_SCHOOLS.map(function (sch) {
              return '<optgroup label="School of ' + sch + '">' +
                OB_DEGREES.filter(function (d) { return d.school === sch; }).map(function (d) {
                  return '<option value="' + escapeHtml(d.value) + '">' + escapeHtml(d.label) + '</option>';
                }).join('') + '</optgroup>';
            }).join('') +
          '</select></div>';
      } else if (tab.key === 'reviewer' || tab.key === 'faculty') {
        extraFields =
          '<div class="rrp-form-group"><label>Department</label><select id="umf-department" class="rrp-input" data-dept-select><option value="">Loading&#8230;</option></select></div>' +
          '<div class="rrp-form-group"><label>Expertise</label><textarea id="umf-expertise" class="rrp-input" rows="2" placeholder="Areas of expertise, research interests\u2026"></textarea></div>';
      } else if (tab.key === 'coordinator') {
        extraFields =
          '<div class="rrp-form-group"><label>Department</label><select id="umf-department" class="rrp-input" data-dept-select><option value="">Loading&#8230;</option></select></div>';
      }

      addWrap.innerHTML =
        '<div class="rrp-analytics-card" style="margin-bottom:1.25rem;border:2px solid var(--rrp-primary);">' +
          '<h3 style="margin:0 0 1rem;font-size:1rem;">Add New ' + tab.key.charAt(0).toUpperCase() + tab.key.slice(1) + '</h3>' +
          '<div class="rrp-form-row">' +
            '<div class="rrp-form-group"><label>First Name</label><input type="text" id="umf-first" class="rrp-input" placeholder="First name"></div>' +
            '<div class="rrp-form-group"><label>Last Name</label><input type="text" id="umf-last" class="rrp-input" placeholder="Last name"></div>' +
          '</div>' +
          '<div class="rrp-form-group"><label>Email Address <em>*</em></label><input type="email" id="umf-email" class="rrp-input" placeholder="user@cityuniversity.edu"></div>' +
          '<div class="rrp-form-group">' +
            '<label>Temporary Password <em>(optional)</em></label>' +
            '<input type="text" id="umf-password" class="rrp-input" placeholder="Leave blank to auto-generate">' +
            '<small style="color:var(--rrp-text-muted)">A secure password is auto-generated if left blank.</small>' +
          '</div>' +
          extraFields +
          '<div id="umf-msg" style="min-height:1.3rem;margin:.4rem 0;"></div>' +
          '<div style="display:flex;gap:.6rem;">' +
            '<button type="button" class="rrp-btn" id="umf-save-btn">&#10003; Create ' + tab.key.charAt(0).toUpperCase() + tab.key.slice(1) + '</button>' +
            '<button type="button" class="rrp-btn secondary" id="umf-cancel-btn">Cancel</button>' +
          '</div>' +
        '</div>';

      document.getElementById('umf-cancel-btn').addEventListener('click', function () {
        addWrap.style.display = 'none';
      });

      // Populate department dropdown if present
      var deptSelAdd = addWrap.querySelector('[data-dept-select]');
      if (deptSelAdd) {
        api('GET', '/config').then(function (cfg) {
          var depts = cfg.departments || [];
          deptSelAdd.innerHTML = '<option value="">&#8212; Select department &#8212;</option>' +
            depts.map(function (d) { return '<option value="' + escapeHtml(d.id) + '">' + escapeHtml(d.label) + '</option>'; }).join('');
        }).catch(function () { deptSelAdd.innerHTML = '<option value="">Unable to load departments</option>'; });
      }

      document.getElementById('umf-save-btn').addEventListener('click', function () {
        var fn      = (document.getElementById('umf-first') ? document.getElementById('umf-first').value.trim() : '');
        var ln      = (document.getElementById('umf-last')  ? document.getElementById('umf-last').value.trim()  : '');
        var email   = (document.getElementById('umf-email') ? document.getElementById('umf-email').value.trim() : '');
        var pw      = (document.getElementById('umf-password') ? document.getElementById('umf-password').value : '');
        var msgEl   = document.getElementById('umf-msg');
        var saveBtn = document.getElementById('umf-save-btn');
        if (!email) { msgEl.innerHTML = '<span class="rrp-error">Email is required.</span>'; return; }
        var payload = { firstName: fn, lastName: ln, email: email, role: tab.role };
        if (pw) payload.password = pw;
        if (tab.key === 'student') {
          var degEl = document.getElementById('umf-degree');
          if (degEl && degEl.value) payload.degree = degEl.value;
        }
        if (tab.key === 'reviewer' || tab.key === 'faculty' || tab.key === 'coordinator') {
          var deptEl = document.getElementById('umf-department');
          if (deptEl && deptEl.value) payload.department = deptEl.value;
        }
        if (tab.key === 'reviewer' || tab.key === 'faculty') {
          var expEl = document.getElementById('umf-expertise');
          if (expEl && expEl.value) payload.expertise = expEl.value;
        }
        saveBtn.disabled = true; saveBtn.textContent = 'Creating\u2026';
        msgEl.innerHTML = '';
        api('POST', '/portal-users', payload)
          .then(function () {
            addWrap.style.display = 'none';
            loadTab(activeTab);
          })
          .catch(function (err) {
            saveBtn.disabled = false; saveBtn.textContent = '\u2713 Create ' + tab.key.charAt(0).toUpperCase() + tab.key.slice(1);
            msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.data && err.data.error) || 'Create failed.') + '</span>';
          });
      });
    }

    function renderEditUserForm(tab, user) {
      if (!user) return;
      var listEl = document.getElementById('rrp-ums-list');
      if (!listEl) return;

      var extraFields = '';
      if (tab.key === 'student') {
        extraFields =
          '<div class="rrp-form-group"><label>Degree / Program</label>' +
          '<select id="umf-degree" class="rrp-input">' +
            '<option value="">&#8212; Select &#8212;</option>' +
            OB_DEGREE_SCHOOLS.map(function (sch) {
              return '<optgroup label="School of ' + sch + '">' +
                OB_DEGREES.filter(function (d) { return d.school === sch; }).map(function (d) {
                  return '<option value="' + escapeHtml(d.value) + '"' + (user.degree === d.value ? ' selected' : '') + '>' + escapeHtml(d.label) + '</option>';
                }).join('') + '</optgroup>';
            }).join('') +
          '</select></div>';
      } else if (tab.key === 'reviewer' || tab.key === 'faculty') {
        extraFields =
          '<div class="rrp-form-group"><label>Department</label><select id="umf-department" class="rrp-input" data-dept-select data-dept-val="' + escapeHtml(user.department || '') + '"><option value="">Loading&#8230;</option></select></div>' +
          '<div class="rrp-form-group"><label>Expertise</label><textarea id="umf-expertise" class="rrp-input" rows="2">' + escapeHtml(user.expertise || '') + '</textarea></div>';
      } else if (tab.key === 'coordinator') {
        extraFields =
          '<div class="rrp-form-group"><label>Department</label><select id="umf-department" class="rrp-input" data-dept-select data-dept-val="' + escapeHtml(user.department || '') + '"><option value="">Loading&#8230;</option></select></div>';
      }

      var ROLE_OPTIONS = [
        { slug: 'rrp_student',     label: 'Student' },
        { slug: 'rrp_reviewer',    label: 'Reviewer' },
        { slug: 'rrp_coordinator', label: 'Coordinator' },
        { slug: 'rrp_admin',       label: 'Admin' }
      ];
      var currentWpRoles = Array.isArray(user.wpRoles) && user.wpRoles.length ? user.wpRoles : (user.wpRole ? [user.wpRole] : ['rrp_student']);
      var rolesHtml =
        '<div class="rrp-form-group" style="margin-top:.75rem;">' +
          '<label style="font-weight:600;">Roles <em style="font-weight:400;color:var(--rrp-text-muted);">(select one or more)</em></label>' +
          '<div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:.4rem;padding:.6rem .8rem;background:var(--rrp-bg-muted,#f8f9fa);border-radius:6px;border:1px solid var(--rrp-border,#dee2e6);">' +
            ROLE_OPTIONS.map(function(r) {
              var ck = currentWpRoles.indexOf(r.slug) !== -1 ? ' checked' : '';
              return '<label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-weight:400;font-size:.95rem;">' +
                '<input type="checkbox" class="umf-role-check" value="' + r.slug + '"' + ck + '> ' + r.label + '</label>';
            }).join('') +
          '</div>' +
          '<small style="color:var(--rrp-text-muted);">A user with multiple roles gets a tab-switcher in their portal view.</small>' +
        '</div>';

      var nameParts = (user.name || '').split(' ');
      var fn = nameParts[0] || ''; var ln = nameParts.slice(1).join(' ') || '';
      listEl.innerHTML =
        '<div class="rrp-analytics-card" style="border:2px solid var(--rrp-primary);">' +
          '<h3 style="margin:0 0 1rem;font-size:1rem;">Edit ' + escapeHtml(user.name || user.email) + '</h3>' +
          rolesHtml +
          '<div class="rrp-form-row" style="margin-top:.75rem;">' +
            '<div class="rrp-form-group"><label>First Name</label><input type="text" id="umf-first" class="rrp-input" value="' + escapeHtml(fn) + '"></div>' +
            '<div class="rrp-form-group"><label>Last Name</label><input type="text" id="umf-last" class="rrp-input" value="' + escapeHtml(ln) + '"></div>' +
          '</div>' +
          extraFields +
          '<div id="umf-msg" style="min-height:1.3rem;margin:.4rem 0;"></div>' +
          '<div style="display:flex;gap:.6rem;">' +
            '<button type="button" class="rrp-btn" id="umf-save-btn">&#10003; Save Changes</button>' +
            '<button type="button" class="rrp-btn secondary" id="umf-cancel-btn">&#8592; Back to List</button>' +
          '</div>' +
        '</div>';

      document.getElementById('umf-cancel-btn').addEventListener('click', function () { loadTab(activeTab); });

      // Populate department dropdown if present (edit form)
      var deptSelEdit = listEl.querySelector('[data-dept-select]');
      if (deptSelEdit) {
        var savedDeptVal = deptSelEdit.getAttribute('data-dept-val') || '';
        api('GET', '/config').then(function (cfg) {
          var depts = cfg.departments || [];
          deptSelEdit.innerHTML = '<option value="">&#8212; Select department &#8212;</option>' +
            depts.map(function (d) {
              return '<option value="' + escapeHtml(d.id) + '"' + (d.id === savedDeptVal ? ' selected' : '') + '>' + escapeHtml(d.label) + '</option>';
            }).join('');
        }).catch(function () { deptSelEdit.innerHTML = '<option value="">Unable to load departments</option>'; });
      }

      document.getElementById('umf-save-btn').addEventListener('click', function () {
        var saveBtn = document.getElementById('umf-save-btn');
        var msgEl   = document.getElementById('umf-msg');
        var fnEl = document.getElementById('umf-first'); var lnEl = document.getElementById('umf-last');
        var payload = {};
        if (fnEl) payload.firstName = fnEl.value.trim();
        if (lnEl) payload.lastName  = lnEl.value.trim();
        if (tab.key === 'student') {
          var degEl = document.getElementById('umf-degree');
          if (degEl) payload.degree = degEl.value;
        }
        if (tab.key === 'reviewer' || tab.key === 'faculty' || tab.key === 'coordinator') {
          var deptEl = document.getElementById('umf-department');
          if (deptEl) payload.department = deptEl.value;
        }
        if (tab.key === 'reviewer' || tab.key === 'faculty') {
          var expEl = document.getElementById('umf-expertise');
          if (expEl) payload.expertise = expEl.value;
        }
        var roleChecks = listEl ? listEl.querySelectorAll('.umf-role-check:checked') : [];
        var selectedRoles = [];
        roleChecks.forEach(function(cb) { selectedRoles.push(cb.value); });
        if (selectedRoles.length === 0) { msgEl.innerHTML = '<span class="rrp-error">Select at least one role.</span>'; return; }
        payload.roles = selectedRoles;

        saveBtn.disabled = true; saveBtn.textContent = 'Saving\u2026';
        msgEl.innerHTML = '';
        api('PATCH', '/portal-users/' + user.id, payload)
          .then(function () { loadTab(activeTab); })
          .catch(function (err) {
            saveBtn.disabled = false; saveBtn.textContent = '\u2713 Save Changes';
            msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.data && err.data.error) || 'Save failed.') + '</span>';
          });
      });
    }

    function renderResetPasswordForm(user) {
      if (!user) return;
      var listEl = document.getElementById('rrp-ums-list');
      if (!listEl) return;
      listEl.innerHTML =
        '<div class="rrp-analytics-card" style="border:2px solid #f59e0b;">' +
          '<h3 style="margin:0 0 .5rem;font-size:1rem;">&#128274; Reset Password &mdash; ' + escapeHtml(user.name || user.email) + '</h3>' +
          '<p style="font-size:.85rem;color:var(--rrp-text-muted);margin-bottom:1rem;">Enter a new password below, or leave blank to auto-generate a secure password.</p>' +
          '<div class="rrp-form-group">' +
            '<label>New Password</label>' +
            '<input type="text" id="rp-password" class="rrp-input" placeholder="Leave blank to auto-generate">' +
          '</div>' +
          '<div id="rp-msg" style="min-height:1.3rem;margin:.4rem 0;"></div>' +
          '<div style="display:flex;gap:.6rem;">' +
            '<button type="button" class="rrp-btn" id="rp-save-btn" style="background:#f59e0b;border-color:#f59e0b;">&#128274; Reset Password</button>' +
            '<button type="button" class="rrp-btn secondary" id="rp-cancel-btn">&#8592; Back to List</button>' +
          '</div>' +
        '</div>';

      document.getElementById('rp-cancel-btn').addEventListener('click', function () { loadTab(activeTab); });
      document.getElementById('rp-save-btn').addEventListener('click', function () {
        var saveBtn  = document.getElementById('rp-save-btn');
        var msgEl    = document.getElementById('rp-msg');
        var password = (document.getElementById('rp-password').value || '').trim();
        var payload  = {};
        if (password) payload.password = password;
        saveBtn.disabled = true; saveBtn.textContent = 'Resetting\u2026';
        msgEl.innerHTML = '';
        api('POST', '/portal-users/' + user.id + '/reset-password', payload)
          .then(function (res) {
            var pw = res.newPassword || '';
            msgEl.innerHTML =
              '<span class="rrp-success">Password reset. ' +
              (pw ? 'New password: <code style="background:#f0f0f0;padding:.1rem .4rem;border-radius:3px;">' + escapeHtml(pw) + '</code> &mdash; provide this to the user.' : '') +
              '</span>';
            saveBtn.disabled = false; saveBtn.textContent = '\u128274 Reset Password';
          })
          .catch(function (err) {
            saveBtn.disabled = false; saveBtn.textContent = '\u128274 Reset Password';
            msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.data && err.data.error) || 'Reset failed.') + '</span>';
          });
      });
    }

    renderPage();
  }

  // ── Student Management ────────────────────────────────────────────────────
  function renderStudentManagement(container, backFn) {
    var _studentSearch = '';
    var _allStudents   = [];

    function applyStudentFilter() {
      var q = _studentSearch;
      var filtered = _allStudents.filter(function (u) {
        return !q ||
          (u.name  || '').toLowerCase().indexOf(q) !== -1 ||
          (u.email || '').toLowerCase().indexOf(q) !== -1;
      });
      var countEl = document.getElementById('rrp-student-count');
      if (countEl) countEl.textContent = filtered.length + ' student' + (filtered.length === 1 ? '' : 's');
      renderStudentRows(filtered);
    }

    function renderStudentRows(users) {
      var el = document.getElementById('rrp-student-list');
      if (!el) return;
      if (users.length === 0) {
        el.innerHTML =
          '<div class="rrp-empty-state" style="margin-top:2rem;">' +
            '<p>' + (_studentSearch ? 'No students match your search.' : 'No students enrolled yet. Click <strong>&#43; Onboard New Student</strong> to get started.') + '</p>' +
          '</div>';
        return;
      }
      el.innerHTML =
          '<div class="rrp-user-mgmt-table">' +
            '<div class="rrp-umr-head">' +
              '<span>Name</span><span>Email</span><span>Degree</span><span>Allowed Types</span><span>Actions</span>' +
            '</div>' +
            users.map(function (u) {
              var types  = (u.allowedTypes || []).map(function (t) { return OB_TYPE_LABELS[t] || t; }).join(', ') || '—';
              var degree = OB_DEGREES.find(function (d) { return d.value === u.degree; });
              var nameCell = '<strong>' + escapeHtml(u.name || u.email) + '</strong>' +
                (u.jsonOnly ? ' <span class="rrp-badge-legacy" title="Submitter found in submissions — no portal login yet">Legacy</span>' : '');
              var degreeCell = u.jsonOnly
                ? '<em style="color:var(--rrp-text-muted);font-size:.85rem;">No portal account</em>'
                : escapeHtml(degree ? degree.label : (u.degree || '—'));
              var actionsCell = u.jsonOnly
                ? '<button type="button" class="rrp-btn small" data-import-student="' + encodeURIComponent(u.jsonStudentEmail || u.email) + '" data-student-obj="' + u.id + '">&#8659; Import</button> ' +
                  '<button type="button" class="rrp-btn danger small" data-dismiss-student="' + encodeURIComponent(u.jsonStudentEmail || u.email) + '">Dismiss</button>'
                : '<button type="button" class="rrp-btn secondary small" data-edit-student="' + u.id + '">Edit</button> ' +
                  '<button type="button" class="rrp-btn danger small" data-remove-student="' + u.id + '">Remove</button>';
              return '<div class="rrp-umr-row' + (u.jsonOnly ? ' rrp-umr-row-legacy' : '') + '">' +
                '<span class="rrp-umr-name">' + nameCell + '</span>' +
                '<span class="rrp-umr-email">' + escapeHtml(u.email) + '</span>' +
                '<span>' + degreeCell + '</span>' +
                '<span class="rrp-umr-types" title="' + escapeHtml(types) + '">' + escapeHtml(types.length > 38 ? types.substring(0, 36) + '…' : types) + '</span>' +
                '<span class="rrp-umr-actions">' + actionsCell + '</span>' +
              '</div>';
            }).join('') +
          '</div>';

        el.querySelectorAll('[data-edit-student]').forEach(function (btn) {
          var uid  = btn.getAttribute('data-edit-student');
          var user = users.find(function (u) { return String(u.id) === uid; });
          btn.addEventListener('click', function () {
            renderStudentOnboardForm(container, user, function () { renderStudentManagement(container, backFn); });
          });
        });
        el.querySelectorAll('[data-import-student]').forEach(function (btn) {
          var uid  = btn.getAttribute('data-student-obj');
          var user = users.find(function (u) { return String(u.id) === uid; });
          btn.addEventListener('click', function () {
            renderStudentOnboardForm(container, user, function () { renderStudentManagement(container, backFn); });
          });
        });
        el.querySelectorAll('[data-remove-student]').forEach(function (btn) {
          var uid  = btn.getAttribute('data-remove-student');
          var user = users.find(function (u) { return String(u.id) === uid; });
          btn.addEventListener('click', function () {
            if (!confirm('Remove portal access for ' + escapeHtml(user ? (user.name || user.email) : 'this user') + '?\nThey will lose the Student role but remain as a WordPress user.')) return;
            api('DELETE', '/portal-users/' + uid)
              .then(function ()  { renderStudentManagement(container, backFn); })
              .catch(function () { alert('Failed to remove access. Please try again.'); });
          });
        });
        el.querySelectorAll('[data-dismiss-student]').forEach(function (btn) {
          var email = decodeURIComponent(btn.getAttribute('data-dismiss-student'));
          var user  = users.find(function (u) { return (u.jsonStudentEmail || u.email) === email; });
          btn.addEventListener('click', function () {
            if (!confirm('Dismiss legacy entry for ' + escapeHtml(user ? (user.name || user.email) : email) + '?\nTheir submission history is preserved.')) return;
            api('DELETE', '/portal-users/json-student/' + encodeURIComponent(email))
              .then(function ()  { renderStudentManagement(container, backFn); })
              .catch(function () { alert('Failed to dismiss entry. Please try again.'); });
          });
        });
    } // end renderStudentRows

    // ── Page setup ────────────────────────────────────────────────────────────
    container.innerHTML =
      '<div class="rrp-mgmt-page-header">' +
        '<button type="button" class="rrp-btn secondary" id="rrp-mgmt-back">&#8592; Back</button>' +
        '<h1>&#127891; Student Management</h1>' +
        '<button type="button" class="rrp-btn" id="rrp-onboard-student-btn">&#43; Onboard New Student</button>' +
      '</div>' +
      '<div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap;">' +
        '<input type="search" id="rrp-student-search" class="rrp-input" placeholder="&#128269; Search name or email\u2026" style="flex:1;min-width:180px;max-width:340px;">' +
        '<span id="rrp-student-count" style="font-size:.85rem;color:var(--rrp-text-muted);white-space:nowrap;"></span>' +
      '</div>' +
      '<div id="rrp-student-list"><p class="rrp-loading">Loading students\u2026</p></div>';

    document.getElementById('rrp-mgmt-back').addEventListener('click', function () {
      if (backFn) backFn(); else renderCoordinatorDashboard(container);
    });
    document.getElementById('rrp-onboard-student-btn').addEventListener('click', function () {
      renderStudentOnboardForm(container, null, function () { renderStudentManagement(container, backFn); });
    });
    document.getElementById('rrp-student-search').addEventListener('input', function () {
      _studentSearch = this.value.toLowerCase().trim();
      applyStudentFilter();
    });

    api('GET', '/portal-users?role=student')
      .then(function (res) {
        _allStudents = res.users || [];
        applyStudentFilter();
      })
      .catch(function () {
        var el = document.getElementById('rrp-student-list');
        if (el) el.innerHTML = '<div class="rrp-error">Unable to load students.</div>';
      });
  }

  // ── Student Onboard Form (3-step wizard) ──────────────────────────────────
  function renderStudentOnboardForm(container, editUser, backFn) {
    var isJsonOnly = !!(editUser && editUser.jsonOnly);
    var isEdit     = !!(editUser && editUser.id) && !isJsonOnly;
    var isImport   = isJsonOnly;
    var nameParts  = (isEdit || isImport) ? (editUser.name || '').split(' ') : [];
    var initialProgram = isEdit ? (editUser.programId || editUser.degree || '') : '';
    var state = {
      firstName:             (isEdit || isImport) ? (nameParts[0] || '') : '',
      lastName:              (isEdit || isImport) ? (nameParts.slice(1).join(' ') || '') : '',
      email:                 (isEdit || isImport) ? (editUser.email || '') : '',
      password:              '',
      degree:                initialProgram,
      programId:             initialProgram,
      allowedTypes:          isEdit ? (editUser.allowedTypes || []) : [],
      defaultStageReviewers: isEdit ? (editUser.defaultStageReviewers || {}) : {},
      step: 1
    };

    function captureStep2() {
      state.allowedTypes = [];
      container.querySelectorAll('[name="ob-allowedTypes"]:checked').forEach(function (cb) {
        state.allowedTypes.push(cb.value);
      });
      var progEl = container.querySelector('#ob-program-id');
      if (progEl) {
        state.degree    = progEl.value;
        state.programId = progEl.value;
      }
    }

    function captureStep3() {
      var stageEl = container.querySelector('#rrp-ob-stage-reviewers');
      if (!stageEl) return;
      var captured = {};
      stageEl.querySelectorAll('[data-ob-type]').forEach(function (typeBlock) {
        var typeName = typeBlock.getAttribute('data-ob-type');
        captured[typeName] = {};
        typeBlock.querySelectorAll('[data-ob-stage]').forEach(function (stageBlock) {
          var stageName = stageBlock.getAttribute('data-ob-stage');
          var selected = [];
          stageBlock.querySelectorAll('input[type=checkbox]:checked').forEach(function (cb) {
            selected.push({ id: cb.getAttribute('data-id'), name: cb.getAttribute('data-name'), email: cb.value });
          });
          captured[typeName][stageName] = selected;
        });
      });
      state.defaultStageReviewers = captured;
    }

    function loadReviewersForStep3() {
      var el = container.querySelector('#rrp-ob-stage-reviewers');
      if (!el) return;
      if (state.allowedTypes.length === 0) {
        el.innerHTML = '<p class="rrp-info">No submission types selected in Step 2. Go back and select at least one type to pre-assign reviewers.</p>';
        return;
      }
      el.innerHTML = '<p class="rrp-loading">Loading reviewer pool&hellip;</p>';
      api('GET', '/reviewers')
        .then(function (res) {
          var allReviewers = res.reviewers || [];
          el.innerHTML = state.allowedTypes.map(function (typeName) {
            var stages  = OB_TYPE_STAGES[typeName] || ['Initial Review', 'Final Approval'];
            var existing = state.defaultStageReviewers[typeName] || {};
            return '<div class="rrp-ob-type-block" data-ob-type="' + escapeHtml(typeName) + '">' +
              '<h3 class="rrp-ob-type-heading">' + escapeHtml(OB_TYPE_LABELS[typeName] || typeName) + '</h3>' +
              stages.map(function (stageName) {
                var existingEmails = (existing[stageName] || []).map(function (r) { return r.email; });
                return '<div class="rrp-assign-stage" data-ob-stage="' + escapeHtml(stageName) + '">' +
                  '<h4 class="rrp-assign-stage-title">' + escapeHtml(stageName) + '</h4>' +
                  '<div class="rrp-assign-reviewer-grid">' +
                  allReviewers.map(function (r) {
                    var checked = existingEmails.indexOf(r.email) !== -1;
                    return '<label class="rrp-reviewer-chip' + (checked ? ' rrp-reviewer-chip-checked' : '') + '">' +
                      '<input type="checkbox" value="' + escapeHtml(r.email) + '" data-name="' + escapeHtml(r.name || r.email) + '" data-id="' + escapeHtml(r.id || '') + '"' + (checked ? ' checked' : '') + '>' +
                      '<span>' + escapeHtml(r.name || r.email) + '<small>' + escapeHtml(r.email) + '</small></span>' +
                    '</label>';
                  }).join('') +
                  (allReviewers.length === 0 ? '<p style="font-size:.83rem;color:var(--rrp-text-muted)">No reviewers in pool yet. Add reviewers first.</p>' : '') +
                  '</div>' +
                '</div>';
              }).join('') +
            '</div>';
          }).join('');
          el.querySelectorAll('.rrp-reviewer-chip input').forEach(function (cb) {
            cb.addEventListener('change', function () {
              cb.closest('.rrp-reviewer-chip').classList.toggle('rrp-reviewer-chip-checked', cb.checked);
            });
          });
        })
        .catch(function () { el.innerHTML = '<div class="rrp-error">Unable to load reviewers.</div>'; });
    }

    function saveStudent() {
      captureStep3();
      var msgEl   = container.querySelector('#rrp-ob-save-msg');
      var saveBtn = container.querySelector('#rrp-ob-save-btn');
      if (!msgEl || !saveBtn) return;
      msgEl.innerHTML = '<span class="rrp-loading">Saving&hellip;</span>';
      saveBtn.disabled = true;
      var payload = {
        firstName: state.firstName, lastName: state.lastName,
        degree: state.degree, allowedTypes: state.allowedTypes,
        defaultStageReviewers: state.defaultStageReviewers,
        programId: state.programId || ''
      };
      var method = isEdit ? 'PATCH' : 'POST';
      var endpoint = isEdit ? '/portal-users/' + editUser.id : '/portal-users';
      if (!isEdit) { payload.email = state.email; payload.role = 'rrp_student'; }
      if (state.password) { payload.password = state.password; }
      api(method, endpoint, payload)
        .then(function () {
          msgEl.innerHTML = '<span class="rrp-success">' + (isEdit ? 'Student profile updated.' : 'Student created successfully.') + '</span>';
          setTimeout(function () { if (backFn) backFn(); else renderCoordinatorDashboard(container); }, 1200);
        })
        .catch(function (err) {
          saveBtn.disabled = false;
          msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err.data && err.data.error) || 'Save failed. Please try again.') + '</span>';
        });
    }

    function renderStep() {
      var stepTitles = ['Basic Info', 'Academic Profile', 'Pre-assign Reviewers'];
      var stepsHtml  = stepTitles.map(function (lbl, i) {
        var cls = state.step === i + 1 ? 'active' : (state.step > i + 1 ? 'done' : '');
        return '<span class="rrp-ob-step ' + cls + '">' + (i + 1) + '. ' + lbl + '</span>';
      }).join('');

      var body = '';
      if (state.step === 1) {
        body =
          '<h2 class="rrp-ob-step-heading">Step 1: Basic Information</h2>' +
          (isEdit ? '<p class="rrp-info">Editing: <strong>' + escapeHtml(editUser.email) + '</strong></p>' : '') +
          (isImport ? '<p class="rrp-info">Creating a portal login for <strong>' + escapeHtml(editUser.name || editUser.email) + '</strong>. This student submitted work before having a portal account.</p>' : '') +
          '<div class="rrp-form-row">' +
            '<div class="rrp-form-group"><label>First Name</label><input type="text" id="ob-first-name" class="rrp-input" value="' + escapeHtml(state.firstName) + '" placeholder="First name"></div>' +
            '<div class="rrp-form-group"><label>Last Name</label><input type="text"  id="ob-last-name"  class="rrp-input" value="' + escapeHtml(state.lastName)  + '" placeholder="Last name"></div>' +
          '</div>' +
          (!isEdit ?
            '<div class="rrp-form-group"><label>Email Address <em>*</em></label><input type="email" id="ob-email" class="rrp-input" value="' + escapeHtml(state.email) + '" placeholder="student@cityuniversity.edu"></div>' +
            '<div class="rrp-form-group"><label>Temporary Password</label><input type="text" id="ob-password" class="rrp-input" value="' + escapeHtml(state.password) + '" placeholder="Leave blank to auto-generate">' +
              '<small style="color:var(--rrp-text-muted)">If blank, a secure password is generated automatically.</small></div>'
          : '<div class="rrp-form-group"><label>Change Password <em>(optional)</em></label><input type="text" id="ob-password" class="rrp-input" value="" placeholder="Leave blank to keep current password"><small style="color:var(--rrp-text-muted)">Only fill this to set a new password.</small></div>') +
          '<div id="rrp-ob-step1-msg" style="min-height:1.4rem;"></div>' +
          '<div class="rrp-onboard-actions"><button type="button" class="rrp-btn" id="rrp-ob-next-btn">Continue &#8594;</button></div>';

      } else if (state.step === 2) {
        body =
          '<h2 class="rrp-ob-step-heading">Step 2: Academic Profile</h2>' +
          '<div class="rrp-form-group">' +
            '<label>Degree Program</label>' +
            '<select id="ob-program-id" class="rrp-input"><option value="">Loading&hellip;</option></select>' +
          '</div>' +
          '<div class="rrp-form-group">' +
            '<label>Allowed Submission Types</label>' +
            '<p style="font-size:.83rem;color:var(--rrp-text-muted);margin:.25rem 0 .6rem;">Select which submission types this student may submit.</p>' +
            '<div class="rrp-checkbox-grid">' +
              OB_ALLOWED_TYPES.map(function (t) {
                var checked = state.allowedTypes.indexOf(t.value) !== -1;
                return '<label class="rrp-check-chip' + (checked ? ' checked' : '') + '">' +
                  '<input type="checkbox" name="ob-allowedTypes" value="' + escapeHtml(t.value) + '"' + (checked ? ' checked' : '') + '> ' + escapeHtml(t.label) +
                '</label>';
              }).join('') +
            '</div>' +
          '</div>' +
          '<div class="rrp-onboard-actions">' +
            '<button type="button" class="rrp-btn secondary" id="rrp-ob-prev-btn">&#8592; Previous</button>' +
            '<button type="button" class="rrp-btn" id="rrp-ob-next-btn">Continue &#8594;</button>' +
          '</div>';

      } else {
        body =
          '<h2 class="rrp-ob-step-heading">Step 3: Pre-assign Reviewers <span style="font-weight:400;font-size:.88rem;">(Optional)</span></h2>' +
          '<p class="rrp-info">Choose default reviewers for each stage per submission type. These will be auto-suggested when the student\'s submissions are routed.</p>' +
          '<div id="rrp-ob-stage-reviewers"><p class="rrp-loading">Loading&hellip;</p></div>' +
          '<div id="rrp-ob-save-msg" style="min-height:1.4rem;margin-top:.5rem;"></div>' +
          '<div class="rrp-onboard-actions">' +
            '<button type="button" class="rrp-btn secondary" id="rrp-ob-prev-btn">&#8592; Previous</button>' +
            '<button type="button" class="rrp-btn" id="rrp-ob-save-btn">' + (isEdit ? '&#10003; Save Changes' : (isImport ? '&#8659; Import &amp; Create Account' : '&#10003; Create Student')) + '</button>' +
          '</div>';
      }

      container.innerHTML =
        '<div class="rrp-onboard-wrap">' +
          '<div class="rrp-onboard-header">' +
            '<button type="button" class="rrp-btn secondary" id="rrp-ob-cancel-btn">&#8592; Back</button>' +
            '<h1>' + (isEdit ? 'Edit Student Profile' : (isImport ? 'Import Legacy Student' : 'Onboard New Student')) + '</h1>' +
            '<div class="rrp-ob-steps">' + stepsHtml + '</div>' +
          '</div>' +
          '<div class="rrp-onboard-body">' + body + '</div>' +
        '</div>';

      container.querySelector('#rrp-ob-cancel-btn').addEventListener('click', function () {
        if (backFn) backFn(); else renderCoordinatorDashboard(container);
      });

      var prevBtn = container.querySelector('#rrp-ob-prev-btn');
      if (prevBtn) {
        prevBtn.addEventListener('click', function () {
          if (state.step === 2) captureStep2();
          if (state.step === 3) captureStep3();
          state.step--;
          renderStep();
        });
      }

      if (state.step === 1) {
        container.querySelector('#rrp-ob-next-btn').addEventListener('click', function () {
          var fn = container.querySelector('#ob-first-name').value.trim();
          var ln = container.querySelector('#ob-last-name').value.trim();
          var em = (isEdit || isImport) ? (editUser.email) : (container.querySelector('#ob-email') ? container.querySelector('#ob-email').value.trim() : '');
          var pw = container.querySelector('#ob-password') ? container.querySelector('#ob-password').value : '';
          var msg = container.querySelector('#rrp-ob-step1-msg');
          if (!isEdit && !isImport && !em) { msg.innerHTML = '<span class="rrp-error">Email is required.</span>'; return; }
          state.firstName = fn; state.lastName = ln; state.email = em; state.password = pw;
          state.step = 2; renderStep();
        });
      } else if (state.step === 2) {
        container.querySelectorAll('.rrp-check-chip input').forEach(function (cb) {
          cb.addEventListener('change', function () {
            cb.closest('.rrp-check-chip').classList.toggle('checked', cb.checked);
          });
        });
        // Populate program dropdown from config (single source for degree + coordinator scoping)
        var progSelect = container.querySelector('#ob-program-id');
        if (progSelect) {
          api('GET', '/config').then(function (cfg) {
            var programs = cfg.programs || [];
            if (!programs.length) {
              progSelect.innerHTML = '<option value="">No programs configured yet — add programs first</option>';
              return;
            }
            var pSchools = {};
            programs.forEach(function (p) {
              var sch = p.school || 'Other';
              if (!pSchools[sch]) pSchools[sch] = [];
              pSchools[sch].push(p);
            });
            var LEVEL_ABBR = { doctoral: 'Doctoral', masters: "Master's", specialist: 'Specialist', undergraduate: 'Undergraduate', certificate: 'Certificate' };
            progSelect.innerHTML = '<option value="">&#8212; Select degree program &#8212;</option>' +
              Object.keys(pSchools).sort().map(function (sch) {
                return '<optgroup label="School of ' + escapeHtml(sch) + '">' +
                  pSchools[sch].map(function (p) {
                    var lvl = LEVEL_ABBR[p.level] || p.level || '';
                    return '<option value="' + escapeHtml(p.id) + '"' + (state.programId === p.id ? ' selected' : '') + '>' +
                      escapeHtml(p.label) + (lvl ? ' — ' + lvl : '') +
                    '</option>';
                  }).join('') +
                '</optgroup>';
              }).join('');
          }).catch(function () { progSelect.innerHTML = '<option value="">Unable to load programs</option>'; });
        }
        container.querySelector('#rrp-ob-next-btn').addEventListener('click', function () {
          captureStep2();
          state.step = 3;
          renderStep();
          loadReviewersForStep3();
        });
      } else {
        loadReviewersForStep3();
        container.querySelector('#rrp-ob-save-btn').addEventListener('click', saveStudent);
      }
    }

    renderStep();
  }

  // ── Reviewer Management ───────────────────────────────────────────────────
  function renderReviewerManagement(container, backFn) {
    var _reviewerSearch = '';
    var _allReviewers   = [];

    function applyReviewerFilter() {
      var q = _reviewerSearch;
      var filtered = _allReviewers.filter(function (u) {
        return !q ||
          (u.name       || '').toLowerCase().indexOf(q) !== -1 ||
          (u.email      || '').toLowerCase().indexOf(q) !== -1 ||
          (u.department || '').toLowerCase().indexOf(q) !== -1;
      });
      var countEl = document.getElementById('rrp-reviewer-count');
      if (countEl) countEl.textContent = filtered.length + ' reviewer' + (filtered.length === 1 ? '' : 's');
      renderReviewerRows(filtered);
    }

    function renderReviewerRows(users) {
      var el = document.getElementById('rrp-reviewer-list');
      if (!el) return;
      if (users.length === 0) {
        el.innerHTML =
          '<div class="rrp-empty-state" style="margin-top:2rem;">' +
            '<p>' + (_reviewerSearch ? 'No reviewers match your search.' : 'No reviewers enrolled yet. Click <strong>&#43; Add Reviewer</strong> to add one.') + '</p>' +
          '</div>';
        return;
      }
      el.innerHTML =
        '<div class="rrp-user-mgmt-table">' +
          '<div class="rrp-umr-head">' +
            '<span>Name</span><span>Email</span><span>Department</span><span>Submission Types</span><span>Actions</span>' +
          '</div>' +
          users.map(function (u) {
            var types = (u.submissionTypes || []).map(function (t) { return OB_TYPE_LABELS[t] || t; }).join(', ') || '—';
            var nameCell = '<strong>' + escapeHtml(u.name || u.email) + '</strong>' +
              (u.jsonOnly ? ' <span class="rrp-badge-legacy" title="Seeded from reviewer pool — no portal login yet">Legacy</span>' : '');
            var deptCell = u.jsonOnly
              ? '<em style="color:var(--rrp-text-muted);font-size:.85rem;">No portal account</em>'
              : escapeHtml(u.department || '—');
            var actionsCell = u.jsonOnly
              ? '<button type="button" class="rrp-btn small" data-import-reviewer="' + u.id + '">&#8659; Import</button> ' +
                '<button type="button" class="rrp-btn danger small" data-remove-json="' + u.id + '">Remove</button>'
              : '<button type="button" class="rrp-btn secondary small" data-edit-reviewer="' + u.id + '">Edit</button> ' +
                '<button type="button" class="rrp-btn danger small"     data-remove-reviewer="' + u.id + '">Remove</button>';
            return '<div class="rrp-umr-row' + (u.jsonOnly ? ' rrp-umr-row-legacy' : '') + '">' +
              '<span class="rrp-umr-name">' + nameCell + '</span>' +
              '<span class="rrp-umr-email">' + escapeHtml(u.email) + '</span>' +
              '<span>' + deptCell + '</span>' +
              '<span class="rrp-umr-types" title="' + escapeHtml(types) + '">' + escapeHtml(types.length > 38 ? types.substring(0, 36) + '\u2026' : types) + '</span>' +
              '<span class="rrp-umr-actions">' + actionsCell + '</span>' +
            '</div>';
          }).join('') +
        '</div>';

      el.querySelectorAll('[data-edit-reviewer]').forEach(function (btn) {
        var uid  = btn.getAttribute('data-edit-reviewer');
        var user = users.find(function (u) { return String(u.id) === uid; });
        btn.addEventListener('click', function () {
          renderReviewerOnboardForm(container, user, function () { renderReviewerManagement(container, backFn); });
        });
      });
      el.querySelectorAll('[data-import-reviewer]').forEach(function (btn) {
        var uid  = btn.getAttribute('data-import-reviewer');
        var user = users.find(function (u) { return String(u.id) === uid; });
        btn.addEventListener('click', function () {
          renderReviewerOnboardForm(container, user, function () { renderReviewerManagement(container, backFn); });
        });
      });
      el.querySelectorAll('[data-remove-reviewer]').forEach(function (btn) {
        var uid  = btn.getAttribute('data-remove-reviewer');
        var user = users.find(function (u) { return String(u.id) === uid; });
        btn.addEventListener('click', function () {
          if (!confirm('Remove portal access for ' + escapeHtml(user ? (user.name || user.email) : 'this user') + '?\nThey will lose the Reviewer role but remain as a WordPress user.')) return;
          api('DELETE', '/portal-users/' + uid)
            .then(function ()  { renderReviewerManagement(container, backFn); })
            .catch(function () { alert('Failed to remove access. Please try again.'); });
        });
      });
      el.querySelectorAll('[data-remove-json]').forEach(function (btn) {
        var jsonId = btn.getAttribute('data-remove-json');
        var user   = users.find(function (u) { return String(u.id) === jsonId; });
        btn.addEventListener('click', function () {
          if (!confirm('Remove ' + escapeHtml(user ? (user.name || user.email) : 'this reviewer') + ' from the reviewer pool?\nThis cannot be undone.')) return;
          api('DELETE', '/portal-users/json/' + jsonId)
            .then(function ()  { renderReviewerManagement(container, backFn); })
            .catch(function () { alert('Failed to remove reviewer. Please try again.'); });
        });
      });
    } // end renderReviewerRows

    // ── Page setup ────────────────────────────────────────────────────────────
    container.innerHTML =
      '<div class="rrp-mgmt-page-header">' +
        '<button type="button" class="rrp-btn secondary" id="rrp-mgmt-back">&#8592; Back</button>' +
        '<h1>&#128101; Reviewer Management</h1>' +
        '<button type="button" class="rrp-btn" id="rrp-onboard-reviewer-btn">&#43; Add Reviewer</button>' +
      '</div>' +
      '<div style="display:flex;gap:.75rem;align-items:center;margin-bottom:1rem;flex-wrap:wrap;">' +
        '<input type="search" id="rrp-reviewer-search" class="rrp-input" placeholder="&#128269; Search name, email or department\u2026" style="flex:1;min-width:180px;max-width:380px;">' +
        '<span id="rrp-reviewer-count" style="font-size:.85rem;color:var(--rrp-text-muted);white-space:nowrap;"></span>' +
      '</div>' +
      '<div id="rrp-reviewer-list"><p class="rrp-loading">Loading reviewers\u2026</p></div>';

    document.getElementById('rrp-mgmt-back').addEventListener('click', function () {
      if (backFn) backFn(); else renderCoordinatorDashboard(container);
    });
    document.getElementById('rrp-onboard-reviewer-btn').addEventListener('click', function () {
      renderReviewerOnboardForm(container, null, function () { renderReviewerManagement(container, backFn); });
    });
    document.getElementById('rrp-reviewer-search').addEventListener('input', function () {
      _reviewerSearch = this.value.toLowerCase().trim();
      applyReviewerFilter();
    });

    api('GET', '/portal-users?role=reviewer')
      .then(function (res) {
        _allReviewers = res.users || [];
        applyReviewerFilter();
      })
      .catch(function () {
        var el = document.getElementById('rrp-reviewer-list');
        if (el) el.innerHTML = '<div class="rrp-error">Unable to load reviewers.</div>';
      });
  }

  // ── Reviewer Onboard Form ─────────────────────────────────────────────────
  function renderReviewerOnboardForm(container, editUser, backFn) {
    var isJsonOnly = !!(editUser && editUser.jsonOnly);
    var isEdit     = !!(editUser && editUser.id) && !isJsonOnly;
    var isImport   = isJsonOnly;  // legacy pool entry: create a WP account from JSON data
    var nameParts  = (isEdit || isImport) ? (editUser.name || '').split(' ') : [];
    var modeTitle  = isImport ? 'Import Legacy Reviewer' : (isEdit ? 'Edit Reviewer Profile' : 'Add Reviewer');

    container.innerHTML =
      '<div class="rrp-onboard-wrap">' +
        '<div class="rrp-onboard-header">' +
          '<button type="button" class="rrp-btn secondary" id="rrp-ob-cancel-btn">&#8592; Back</button>' +
          '<h1>' + modeTitle + '</h1>' +
        '</div>' +
        '<div class="rrp-onboard-body">' +
          (isEdit   ? '<p class="rrp-info">Editing: <strong>' + escapeHtml(editUser.email) + '</strong></p>' : '') +
          (isImport ? '<p class="rrp-info">Creating a portal login for <strong>' + escapeHtml(editUser.name || editUser.email) + '</strong>. This reviewer exists in the pool but has no portal account yet.</p>' : '') +
          '<div class="rrp-form-row">' +
            '<div class="rrp-form-group"><label>First Name</label><input type="text" id="ob-first-name" class="rrp-input" value="' + escapeHtml(nameParts[0] || '') + '" placeholder="First name"></div>' +
            '<div class="rrp-form-group"><label>Last Name</label><input type="text" id="ob-last-name" class="rrp-input" value="' + escapeHtml(nameParts.slice(1).join(' ') || '') + '" placeholder="Last name"></div>' +
          '</div>' +
          (!isEdit ?
            '<div class="rrp-form-group"><label>Email Address <em>*</em></label><input type="email" id="ob-email" class="rrp-input" value="' + escapeHtml(isImport ? (editUser.email || '') : '') + '" placeholder="reviewer@university.edu"></div>' +
            '<div class="rrp-form-group"><label>Temporary Password</label><input type="text" id="ob-password" class="rrp-input" placeholder="Leave blank to auto-generate"><small style="color:var(--rrp-text-muted)">If blank, a secure password is generated.</small></div>'
          : '<div class="rrp-form-group"><label>Change Password <em>(optional)</em></label><input type="text" id="ob-password" class="rrp-input" value="" placeholder="Leave blank to keep current password"><small style="color:var(--rrp-text-muted)">Only fill this to set a new password.</small></div>') +
          '<div class="rrp-form-row">' +
            '<div class="rrp-form-group"><label>Department / Unit</label><select id="ob-dept" class="rrp-input" data-dept-select data-dept-val="' + escapeHtml(isEdit ? (editUser.department || '') : '') + '"><option value="">Loading&#8230;</option></select></div>' +
            '<div class="rrp-form-group"><label>Expertise / Specialization</label><input type="text" id="ob-expertise" class="rrp-input" value="' + escapeHtml(isEdit ? (editUser.expertise || '') : '') + '" placeholder="e.g. Machine Learning, HCI"></div>' +
          '</div>' +
          '<div class="rrp-form-group">' +
            '<label>Submission Types They Can Review</label>' +
            '<p style="font-size:.83rem;color:var(--rrp-text-muted);margin:.25rem 0 .6rem;">Select all types this reviewer is qualified to evaluate.</p>' +
            '<div class="rrp-checkbox-grid">' +
              OB_ALLOWED_TYPES.map(function (t) {
                var checked = (isEdit || isImport) && (editUser.submissionTypes || []).indexOf(t.value) !== -1;
                return '<label class="rrp-check-chip' + (checked ? ' checked' : '') + '">' +
                  '<input type="checkbox" name="ob-submissionTypes" value="' + escapeHtml(t.value) + '"' + (checked ? ' checked' : '') + '> ' + escapeHtml(t.label) +
                '</label>';
              }).join('') +
            '</div>' +
          '</div>' +
          '<div id="rrp-ob-save-msg" style="min-height:1.4rem;margin-top:.5rem;"></div>' +
          '<div class="rrp-onboard-actions">' +
            '<button type="button" class="rrp-btn" id="rrp-ob-save-btn">' + (isEdit ? '&#10003; Save Changes' : (isImport ? '&#8659; Import &amp; Create Account' : '&#10003; Add Reviewer')) + '</button>' +
          '</div>' +
        '</div>' +
      '</div>';

    container.querySelectorAll('.rrp-check-chip input').forEach(function (cb) {
      cb.addEventListener('change', function () {
        cb.closest('.rrp-check-chip').classList.toggle('checked', cb.checked);
      });
    });

    // Populate department dropdown in reviewer onboard form
    var obDeptSel = container.querySelector('#ob-dept[data-dept-select]');
    if (obDeptSel) {
      var savedVal = obDeptSel.getAttribute('data-dept-val') || '';
      api('GET', '/config').then(function (cfg) {
        var depts = cfg.departments || [];
        obDeptSel.innerHTML = '<option value="">&#8212; Select department &#8212;</option>' +
          depts.map(function (d) {
            return '<option value="' + escapeHtml(d.id) + '"' + (d.id === savedVal ? ' selected' : '') + '>' + escapeHtml(d.label) + '</option>';
          }).join('');
      }).catch(function () { obDeptSel.innerHTML = '<option value="">Unable to load departments</option>'; });
    }

    container.querySelector('#rrp-ob-cancel-btn').addEventListener('click', function () {
      if (backFn) backFn(); else renderCoordinatorDashboard(container);
    });

    container.querySelector('#rrp-ob-save-btn').addEventListener('click', function () {
      var msgEl   = container.querySelector('#rrp-ob-save-msg');
      var saveBtn = container.querySelector('#rrp-ob-save-btn');
      var fn      = container.querySelector('#ob-first-name').value.trim();
      var ln      = container.querySelector('#ob-last-name').value.trim();
      var em      = isEdit ? editUser.email : (container.querySelector('#ob-email') ? container.querySelector('#ob-email').value.trim() : '');
      var pw      = container.querySelector('#ob-password') ? container.querySelector('#ob-password').value : '';
      var dept    = container.querySelector('#ob-dept').value.trim();
      var exp     = container.querySelector('#ob-expertise').value.trim();
      var types   = [];
      container.querySelectorAll('[name="ob-submissionTypes"]:checked').forEach(function (cb) { types.push(cb.value); });

      if (!isEdit && !em) { msgEl.innerHTML = '<span class="rrp-error">Email is required.</span>'; return; }

      msgEl.innerHTML = '<span class="rrp-loading">Saving&hellip;</span>';
      saveBtn.disabled = true;

      var payload = { firstName: fn, lastName: ln, submissionTypes: types, department: dept, expertise: exp };
      var method   = isEdit ? 'PATCH' : 'POST';
      var endpoint = isEdit ? '/portal-users/' + editUser.id : '/portal-users';
      if (!isEdit) { payload.email = em; payload.role = 'rrp_reviewer'; }
      if (pw) { payload.password = pw; }

      api(method, endpoint, payload)
        .then(function () {
          msgEl.innerHTML = '<span class="rrp-success">' + (isEdit ? 'Reviewer updated.' : 'Reviewer added successfully.') + '</span>';
          setTimeout(function () { if (backFn) backFn(); else renderCoordinatorDashboard(container); }, 1200);
        })
        .catch(function (err) {
          saveBtn.disabled = false;
          msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err.data && err.data.error) || 'Save failed.') + '</span>';
        });
    });
  }

  // ── SVG Line Chart builder ─────────────────────────────────────────────────
  function buildLineChart(dailyData) {
    var dates  = (dailyData && dailyData.dates)  || [];
    var series = (dailyData && dailyData.series) || [];
    // Filter to only series that have at least one non-zero value
    var activeSeries = series.filter(function (s) { return s.values.some(function (v) { return v > 0; }); });
    if (!dates.length || !activeSeries.length) {
      return '<p style="color:var(--rrp-text-muted);font-size:.88rem">No submission data available yet.</p>';
    }

    var W = 700, H = 260;
    var padL = 36, padT = 14, padR = 18, padB = 46;
    var innerW = W - padL - padR;
    var innerH = H - padT - padB;
    var n = dates.length;

    var maxVal = 1;
    activeSeries.forEach(function (s) { s.values.forEach(function (v) { if (v > maxVal) maxVal = v; }); });
    // Round up maxVal to a nice number
    var yMax = Math.ceil(maxVal * 1.15) || 1;

    var labelStep = Math.max(1, Math.ceil(n / 14));

    function xPos(i)  { return padL + (n > 1 ? (i / (n - 1)) * innerW : innerW / 2); }
    function yPos(v)  { return padT + (1 - v / yMax) * innerH; }

    var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" xmlns="http://www.w3.org/2000/svg" ' +
      'style="width:100%;height:auto;display:block;overflow:visible;">\n';

    // Horizontal grid lines + Y labels
    var ySteps = 4;
    for (var yi = 0; yi <= ySteps; yi++) {
      var yv  = yMax * (1 - yi / ySteps);
      var ypx = padT + (yi / ySteps) * innerH;
      svg += '<line x1="' + padL + '" x2="' + (W - padR) + '" y1="' + ypx.toFixed(1) + '" y2="' + ypx.toFixed(1) + '" stroke="#e2e8f0" stroke-width="1"/>\n';
      svg += '<text x="' + (padL - 5) + '" y="' + (ypx + 4).toFixed(1) + '" text-anchor="end" font-size="10" fill="#94a3b8">' + Math.round(yv) + '</text>\n';
    }

    // X axis base line
    svg += '<line x1="' + padL + '" x2="' + (W - padR) + '" y1="' + (padT + innerH) + '" y2="' + (padT + innerH) + '" stroke="#cbd5e1" stroke-width="1"/>\n';

    // X axis date labels
    for (var xi = 0; xi < n; xi += labelStep) {
      var xpx = xPos(xi);
      var lbl = dates[xi] ? dates[xi].slice(5) : '';
      svg += '<text x="' + xpx.toFixed(1) + '" y="' + (padT + innerH + 14) + '" text-anchor="middle" font-size="10" fill="#94a3b8">' + escapeHtml(lbl) + '</text>\n';
    }
    // Always show last date if not already shown
    if ((n - 1) % labelStep !== 0 && n > 1) {
      svg += '<text x="' + xPos(n - 1).toFixed(1) + '" y="' + (padT + innerH + 14) + '" text-anchor="middle" font-size="10" fill="#94a3b8">' + escapeHtml(dates[n - 1].slice(5)) + '</text>\n';
    }

    // Shaded areas + polylines
    activeSeries.forEach(function (s) {
      var pts = s.values.map(function (v, i) { return xPos(i).toFixed(1) + ',' + yPos(v).toFixed(1); }).join(' ');
      // Light fill area
      var areaFirst = xPos(0).toFixed(1) + ',' + (padT + innerH);
      var areaLast  = xPos(n - 1).toFixed(1) + ',' + (padT + innerH);
      svg += '<polygon points="' + areaFirst + ' ' + pts + ' ' + areaLast + '" fill="' + s.color + '" fill-opacity="0.08"/>\n';
      // Line
      svg += '<polyline points="' + pts + '" fill="none" stroke="' + s.color + '" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>\n';
      // Dots (only when few data points)
      if (n <= 35) {
        s.values.forEach(function (v, i) {
          if (v === 0) return;
          svg += '<circle cx="' + xPos(i).toFixed(1) + '" cy="' + yPos(v).toFixed(1) + '" r="3.5" fill="' + s.color + '" stroke="#fff" stroke-width="1.5"/>\n';
        });
      }
    });

    svg += '</svg>';

    // Legend
    var legend = '<div class="rrp-linechart-legend">';
    activeSeries.forEach(function (s) {
      legend += '<span class="rrp-lc-legend-item"><span class="rrp-lc-swatch" style="background:' + s.color + '"></span>' + escapeHtml(s.status) + '</span>';
    });
    legend += '</div>';

    return svg + legend;
  }

  // ── Programs Management ───────────────────────────────────────────────────
  function renderProgramManagement(container, backFn) {
    container.innerHTML =
      '<div class="rrp-mgmt-page-header">' +
        '<button type="button" class="rrp-btn secondary" id="rrp-prog-back">&#8592; Back</button>' +
        '<h1>&#127979; Program Management</h1>' +
        '<button type="button" class="rrp-btn" id="rrp-prog-create-btn">&#43; New Program</button>' +
      '</div>' +
      '<p style="font-size:.88rem;color:var(--rrp-text-muted);margin-bottom:1rem">Manage degree programs: create, assign program directors and coordinators, or remove programs.</p>' +
      '<div id="rrp-create-prog-wrap" style="display:none;"></div>' +
      '<div id="rrp-programs-body"><p class="rrp-loading">Loading programs&hellip;</p></div>';

    document.getElementById('rrp-prog-back').addEventListener('click', function () {
      if (backFn) backFn(); else renderCoordinatorDashboard(container);
    });

    document.getElementById('rrp-prog-create-btn').addEventListener('click', function () {
      var wrap = document.getElementById('rrp-create-prog-wrap');
      if (!wrap) return;
      if (wrap.style.display !== 'none') { wrap.style.display = 'none'; return; }
      wrap.style.display = 'block';
      var SCHOOL_OPTIONS = ['Business', 'Education', 'Technology'];
      var LEVEL_OPTIONS  = [
        { value: 'doctoral',       label: 'Doctoral' },
        { value: 'masters',        label: "Master's" },
        { value: 'undergraduate',  label: 'Undergraduate' },
        { value: 'certificate',    label: 'Certificate' }
      ];
      wrap.innerHTML =
        '<div class="rrp-analytics-card" style="margin-bottom:1.25rem;border:2px solid var(--rrp-primary);">' +
          '<h3 style="margin:0 0 1rem;font-size:1rem;">Create New Program</h3>' +
          '<div class="rrp-form-row">' +
            '<div class="rrp-form-group">' +
              '<label>Program ID <em style="font-size:.8rem;font-weight:400">(unique slug, e.g. mba)</em></label>' +
              '<input type="text" id="np-id" class="rrp-input" placeholder="e.g. dba, mscis, bscs">' +
            '</div>' +
            '<div class="rrp-form-group">' +
              '<label>School</label>' +
              '<select id="np-school" class="rrp-input">' +
                SCHOOL_OPTIONS.map(function(s){ return '<option value="' + s + '">' + s + '</option>'; }).join('') +
              '</select>' +
            '</div>' +
          '</div>' +
          '<div class="rrp-form-group">' +
            '<label>Program Full Name</label>' +
            '<input type="text" id="np-label" class="rrp-input" placeholder="e.g. Master of Science in Computer Information Systems">' +
          '</div>' +
          '<div class="rrp-form-row">' +
            '<div class="rrp-form-group">' +
              '<label>Level</label>' +
              '<select id="np-level" class="rrp-input">' +
                LEVEL_OPTIONS.map(function(l){ return '<option value="' + l.value + '">' + l.label + '</option>'; }).join('') +
              '</select>' +
            '</div>' +
          '</div>' +
          '<div id="rrp-np-msg" style="min-height:1.3rem;margin:.4rem 0;"></div>' +
          '<div style="display:flex;gap:.6rem;">' +
            '<button type="button" class="rrp-btn" id="rrp-np-save-btn">&#10003; Create Program</button>' +
            '<button type="button" class="rrp-btn secondary" id="rrp-np-cancel-btn">Cancel</button>' +
          '</div>' +
        '</div>';

      document.getElementById('rrp-np-cancel-btn').addEventListener('click', function () {
        wrap.style.display = 'none';
      });
      document.getElementById('rrp-np-save-btn').addEventListener('click', function () {
        var pid   = (document.getElementById('np-id').value || '').trim().toLowerCase().replace(/[^a-z0-9\-]/g, '');
        var label = (document.getElementById('np-label').value || '').trim();
        var school = document.getElementById('np-school').value;
        var level  = document.getElementById('np-level').value;
        var msgEl  = document.getElementById('rrp-np-msg');
        if (!pid)   { msgEl.innerHTML = '<span class="rrp-error">Program ID is required.</span>'; return; }
        if (!label) { msgEl.innerHTML = '<span class="rrp-error">Program name is required.</span>'; return; }
        var saveBtn = document.getElementById('rrp-np-save-btn');
        saveBtn.disabled = true; saveBtn.textContent = 'Saving\u2026';
        api('GET', '/config').then(function (config) {
          var programs = (config.programs || []).slice();
          if (programs.some(function (p) { return p.id === pid; })) {
            msgEl.innerHTML = '<span class="rrp-error">A program with this ID already exists.</span>';
            saveBtn.disabled = false; saveBtn.textContent = '\u2713 Create Program';
            return;
          }
          programs.push({ id: pid, label: label, level: level, school: school, programDirectorId: '', coordinatorIds: [] });
          api('PUT', '/config', { programs: programs }).then(function () {
            wrap.style.display = 'none';
            renderProgramManagement(container, backFn);
          }).catch(function (err) {
            msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.data && err.data.error) || 'Save failed.') + '</span>';
            saveBtn.disabled = false; saveBtn.textContent = '\u2713 Create Program';
          });
        }).catch(function () {
          msgEl.innerHTML = '<span class="rrp-error">Failed to load config.</span>';
          saveBtn.disabled = false; saveBtn.textContent = '\u2713 Create Program';
        });
      });
    });

    Promise.all([
      api('GET', '/config'),
      api('GET', '/reviewers'),
      api('GET', '/portal-users?role=coordinator')
    ]).then(function (results) {
      var config      = results[0];
      var reviewers   = (results[1].reviewers || results[1] || []);
      var coordUsers  = (results[2].users || []);
      var programs    = (config.programs || []).slice();

      // Build reviewer id → object map
      var reviewerMap = {};
      reviewers.forEach(function (r) { reviewerMap[r.id] = r; });
      // Build WP user ID → name map for coordinators
      var coordMap = {};
      coordUsers.forEach(function (u) { coordMap[String(u.id)] = u.name || u.email; });

      var schools     = ['Business', 'Education', 'Technology'];
      var dissertDirId = config.dissertationDirectorId || '';
      var departments  = (config.departments || []);

      // Helper: build department options HTML
      function deptOptions(selectedId) {
        return '<option value="">&#8212; Select department &#8212;</option>' +
          departments.map(function (d) {
            return '<option value="' + escapeHtml(d.id) + '"' + (d.id === (selectedId || '') ? ' selected' : '') + '>' + escapeHtml(d.label) + '</option>';
          }).join('');
      }

      var html =
        // ── Departments card ───────────────────────────────────────────────
        '<div class="rrp-analytics-card" style="margin-bottom:1.5rem;">' +
          '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">' +
            '<h2 class="rrp-prog-school-title" style="margin:0;">&#127970; Departments</h2>' +
            '<button class="rrp-btn rrp-btn-sm" id="rrp-dept-add-btn">&#43; Add Department</button>' +
          '</div>' +
          '<div id="rrp-dept-add-form" style="display:none;margin-bottom:.75rem;">' +
            '<div style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;">' +
              '<div class="rrp-form-group" style="margin:0;min-width:160px;">' +
                '<label style="font-size:.82rem;">ID <em style="font-weight:400;">(slug)</em></label>' +
                '<input type="text" id="rrp-dept-new-id" class="rrp-input" placeholder="e.g. nursing" style="font-size:.83rem;">' +
              '</div>' +
              '<div class="rrp-form-group" style="margin:0;flex:1;min-width:200px;">' +
                '<label style="font-size:.82rem;">Display Name</label>' +
                '<input type="text" id="rrp-dept-new-label" class="rrp-input" placeholder="e.g. School of Nursing" style="font-size:.83rem;">' +
              '</div>' +
              '<button class="rrp-btn rrp-btn-sm" id="rrp-dept-save-btn">&#10003; Save</button>' +
              '<button class="rrp-btn secondary rrp-btn-sm" id="rrp-dept-cancel-btn">Cancel</button>' +
              '<span id="rrp-dept-msg" style="font-size:.82rem;"></span>' +
            '</div>' +
          '</div>' +
          (departments.length
            ? '<div class="rrp-user-mgmt-table">' +
                departments.map(function (d) {
                  return '<div class="rrp-umr-row" style="grid-template-columns:1fr 2fr auto;">' +
                    '<span style="font-size:.83rem;color:var(--rrp-text-muted);font-family:monospace;">' + escapeHtml(d.id) + '</span>' +
                    '<span><strong>' + escapeHtml(d.label) + '</strong></span>' +
                    '<span><button class="rrp-btn danger rrp-btn-sm" data-delete-dept="' + escapeHtml(d.id) + '">&#128465;</button></span>' +
                  '</div>';
                }).join('') +
              '</div>'
            : '<p style="color:var(--rrp-text-muted);font-size:.85rem;">No departments yet. Click <strong>+ Add Department</strong> to create one.</p>') +
        '</div>' +
        // ── University Roles card ──────────────────────────────────────────
        '<div class="rrp-analytics-card" style="margin-bottom:1.5rem;">' +
          '<h2 class="rrp-prog-school-title">&#127979; University Roles</h2>' +
          '<p style="font-size:.85rem;color:var(--rrp-text-muted);margin:.25rem 0 .75rem;">Assign university-wide roles that apply across all programs.</p>' +
          '<div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">' +
            '<div class="rrp-form-group" style="margin:0;flex:1;min-width:220px;">' +
              '<label>Dissertation Director <small style="font-weight:400;color:var(--rrp-text-muted);">(university-wide, all dissertations)</small></label>' +
              '<select id="rrp-diss-dir-sel" class="rrp-input" style="font-size:.85rem;">' +
                '<option value="">&#8212; Not assigned &#8212;</option>' +
                reviewers.map(function (r) {
                  return '<option value="' + escapeHtml(r.id) + '"' + (r.id === dissertDirId ? ' selected' : '') + '>' + escapeHtml(r.name) + '</option>';
                }).join('') +
              '</select>' +
            '</div>' +
            '<div style="display:flex;align-items:center;gap:.5rem;">' +
              '<button class="rrp-btn rrp-btn-sm" id="rrp-save-diss-dir">&#10003; Save</button>' +
              '<span id="rrp-diss-dir-msg" style="font-size:.83rem;"></span>' +
            '</div>' +
          '</div>' +
        '</div>';

      schools.forEach(function (school) {
        var schoolProgs = programs.filter(function (p) { return p.school === school; });
        if (!schoolProgs.length) return;
        html +=
          '<div class="rrp-analytics-card rrp-prog-school-section">' +
            '<h2 class="rrp-prog-school-title">School of ' + escapeHtml(school) + ' &nbsp;<small style="font-weight:400;color:var(--rrp-text-muted);font-size:.8rem">' + schoolProgs.length + ' programs</small></h2>' +
            '<div class="rrp-prog-header"><span>Program</span><span>Level</span><span>Director</span><span>Coordinators</span><span>Actions</span></div>';

        schoolProgs.forEach(function (prog) {
          var dir  = reviewerMap[prog.programDirectorId];
          var dirDisplay = dir
            ? escapeHtml(dir.name)
            : (prog.programDirectorId ? escapeHtml(prog.programDirectorId) : '<em style="color:var(--rrp-text-muted)">Not assigned</em>');
          var levelBadge = LEVEL_BADGES[prog.level] || escapeHtml(prog.level || '');

          var coordIds    = (prog.coordinatorIds || []).map(String);
          var coordNames  = coordIds.map(function (cid) { return coordMap[cid] || cid; });
          var coordDisplay = coordNames.length ? coordNames.map(function (n) { return '<span class="rrp-chip">' + escapeHtml(n) + '</span>'; }).join(' ') : '<em style="color:var(--rrp-text-muted)">None</em>';

          // Director dropdown (reviewers)
          var dirOptions = '<option value="">&#8212; None &#8212;</option>' +
            reviewers.map(function (r) {
              return '<option value="' + escapeHtml(r.id) + '"' + (r.id === (prog.programDirectorId || '') ? ' selected' : '') + '>' + escapeHtml(r.name) + '</option>';
            }).join('');

          // Coordinator multi-checkboxes
          var coordChecks = coordUsers.length
            ? coordUsers.map(function (u) {
                var uid = String(u.id);
                var checked = coordIds.indexOf(uid) !== -1;
                return '<label class="rrp-check-chip' + (checked ? ' checked' : '') + '" style="font-size:.82rem;">' +
                  '<input type="checkbox" class="rrp-prog-coord-cb" value="' + escapeHtml(uid) + '"' + (checked ? ' checked' : '') + '> ' + escapeHtml(u.name || u.email) +
                '</label>';
              }).join('')
            : '<em style="font-size:.82rem;color:var(--rrp-text-muted)">No coordinators created yet</em>';

          html +=
            '<div class="rrp-prog-row" data-prog-id="' + escapeHtml(prog.id) + '">' +
              '<span class="rrp-prog-name">' + escapeHtml(prog.label) + '</span>' +
              '<span>' + levelBadge + '</span>' +
              '<span class="rrp-prog-director" data-field="director">' + dirDisplay + '</span>' +
              '<span data-field="coordinators" style="font-size:.82rem;">' + coordDisplay + '</span>' +
              '<span>' +
                '<button class="rrp-btn rrp-btn-sm" data-edit-prog="' + escapeHtml(prog.id) + '">&#9998; Edit</button> ' +
                '<button class="rrp-btn danger rrp-btn-sm" data-delete-prog="' + escapeHtml(prog.id) + '">&#128465;</button>' +
              '</span>' +
            '</div>' +
            '<div class="rrp-prog-edit-row" id="rrp-prog-edit-' + escapeHtml(prog.id) + '" style="display:none;">' +
              '<span class="rrp-prog-name" style="font-style:italic;color:var(--rrp-text-muted)">' + escapeHtml(prog.label) + '</span>' +
              '<span>' + levelBadge + '</span>' +
              '<span><select class="rrp-input rrp-prog-dir-select" style="font-size:.83rem;padding:.3rem .5rem;">' + dirOptions + '</select></span>' +
              '<span style="display:flex;flex-wrap:wrap;gap:.3rem;align-items:center;" class="rrp-prog-coord-checks">' + coordChecks + '</span>' +
              '<span style="display:flex;flex-direction:column;gap:.3rem;">' +
                '<span style="display:flex;gap:.4rem;align-items:center;">' +
                  '<button class="rrp-btn rrp-btn-sm" data-save-prog="' + escapeHtml(prog.id) + '">&#10003; Save</button>' +
                  '<button class="rrp-btn secondary rrp-btn-sm" data-cancel-prog="' + escapeHtml(prog.id) + '">Cancel</button>' +
                '</span>' +
                '<span class="rrp-prog-save-msg" style="font-size:.78rem;"></span>' +
              '</span>' +
            '</div>';
        });

        html += '</div>';
      });

      // Also show any programs not matching a known school
      var otherProgs = programs.filter(function (p) { return schools.indexOf(p.school) === -1; });
      if (otherProgs.length) {
        html +=
          '<div class="rrp-analytics-card rrp-prog-school-section">' +
            '<h2 class="rrp-prog-school-title">Other &nbsp;<small style="font-weight:400;color:var(--rrp-text-muted);font-size:.8rem">' + otherProgs.length + ' programs</small></h2>' +
            '<div class="rrp-prog-header"><span>Program</span><span>Level</span><span>Director</span><span>Coordinators</span><span>Actions</span></div>';
        otherProgs.forEach(function (prog) {
          var dir  = reviewerMap[prog.programDirectorId];
          var dirDisplay = dir ? escapeHtml(dir.name) : (prog.programDirectorId ? escapeHtml(prog.programDirectorId) : '<em style="color:var(--rrp-text-muted)">Not assigned</em>');
          var levelBadge = LEVEL_BADGES[prog.level] || escapeHtml(prog.level || '');
          var coordIds    = (prog.coordinatorIds || []).map(String);
          var coordNames  = coordIds.map(function (cid) { return coordMap[cid] || cid; });
          var coordDisplay = coordNames.length ? coordNames.map(function (n) { return '<span class="rrp-chip">' + escapeHtml(n) + '</span>'; }).join(' ') : '<em style="color:var(--rrp-text-muted)">None</em>';
          var dirOptions = '<option value="">&#8212; None &#8212;</option>' +
            reviewers.map(function (r) { return '<option value="' + escapeHtml(r.id) + '"' + (r.id === (prog.programDirectorId || '') ? ' selected' : '') + '>' + escapeHtml(r.name) + '</option>'; }).join('');
          var coordChecks = coordUsers.length
            ? coordUsers.map(function (u) {
                var uid = String(u.id); var checked = coordIds.indexOf(uid) !== -1;
                return '<label class="rrp-check-chip' + (checked ? ' checked' : '') + '" style="font-size:.82rem;">' +
                  '<input type="checkbox" class="rrp-prog-coord-cb" value="' + escapeHtml(uid) + '"' + (checked ? ' checked' : '') + '> ' + escapeHtml(u.name || u.email) + '</label>';
              }).join('')
            : '<em style="font-size:.82rem;color:var(--rrp-text-muted)">No coordinators created yet</em>';
          html +=
            '<div class="rrp-prog-row" data-prog-id="' + escapeHtml(prog.id) + '">' +
              '<span class="rrp-prog-name">' + escapeHtml(prog.label) + '</span>' +
              '<span>' + levelBadge + '</span>' +
              '<span class="rrp-prog-director" data-field="director">' + dirDisplay + '</span>' +
              '<span data-field="coordinators" style="font-size:.82rem;">' + coordDisplay + '</span>' +
              '<span>' +
                '<button class="rrp-btn rrp-btn-sm" data-edit-prog="' + escapeHtml(prog.id) + '">&#9998; Edit</button> ' +
                '<button class="rrp-btn danger rrp-btn-sm" data-delete-prog="' + escapeHtml(prog.id) + '">&#128465;</button>' +
              '</span>' +
            '</div>' +
            '<div class="rrp-prog-edit-row" id="rrp-prog-edit-' + escapeHtml(prog.id) + '" style="display:none;">' +
              '<span class="rrp-prog-name" style="font-style:italic;color:var(--rrp-text-muted)">' + escapeHtml(prog.label) + '</span>' +
              '<span>' + levelBadge + '</span>' +
              '<span><select class="rrp-input rrp-prog-dir-select" style="font-size:.83rem;padding:.3rem .5rem;">' + dirOptions + '</select></span>' +
              '<span style="display:flex;flex-wrap:wrap;gap:.3rem;align-items:center;" class="rrp-prog-coord-checks">' + coordChecks + '</span>' +
              '<span style="display:flex;flex-direction:column;gap:.3rem;">' +
                '<span style="display:flex;gap:.4rem;align-items:center;">' +
                  '<button class="rrp-btn rrp-btn-sm" data-save-prog="' + escapeHtml(prog.id) + '">&#10003; Save</button>' +
                  '<button class="rrp-btn secondary rrp-btn-sm" data-cancel-prog="' + escapeHtml(prog.id) + '">Cancel</button>' +
                '</span>' +
                '<span class="rrp-prog-save-msg" style="font-size:.78rem;"></span>' +
              '</span>' +
            '</div>';
        });
        html += '</div>';
      }

      var body = document.getElementById('rrp-programs-body');
      if (body) body.innerHTML = html || '<div class="rrp-empty-state"><p>No programs yet. Click <strong>&#43; New Program</strong> to create one.</p></div>';

      // ── Dissertation Director save ───────────────────────────────────────
      var saveDDBtn = document.getElementById('rrp-save-diss-dir');
      if (saveDDBtn) {
        saveDDBtn.addEventListener('click', function () {
          var sel   = document.getElementById('rrp-diss-dir-sel');
          var msgEl = document.getElementById('rrp-diss-dir-msg');
          saveDDBtn.disabled = true; saveDDBtn.textContent = 'Saving\u2026';
          api('PUT', '/config', { dissertationDirectorId: sel ? sel.value : '' })
            .then(function () {
              msgEl.innerHTML = '<span class="rrp-success">Saved.</span>';
              saveDDBtn.disabled = false; saveDDBtn.textContent = '\u2713 Save';
              setTimeout(function () { msgEl.innerHTML = ''; }, 2500);
            })
            .catch(function () {
              msgEl.innerHTML = '<span class="rrp-error">Save failed.</span>';
              saveDDBtn.disabled = false; saveDDBtn.textContent = '\u2713 Save';
            });
        });
      }

      // ── Departments CRUD ─────────────────────────────────────────────────
      var deptAddBtn  = document.getElementById('rrp-dept-add-btn');
      var deptAddForm = document.getElementById('rrp-dept-add-form');
      if (deptAddBtn && deptAddForm) {
        deptAddBtn.addEventListener('click', function () {
          deptAddForm.style.display = deptAddForm.style.display === 'none' ? 'block' : 'none';
        });
        document.getElementById('rrp-dept-cancel-btn').addEventListener('click', function () {
          deptAddForm.style.display = 'none';
        });
        document.getElementById('rrp-dept-save-btn').addEventListener('click', function () {
          var idVal    = (document.getElementById('rrp-dept-new-id').value || '').trim().toLowerCase().replace(/[^a-z0-9\-]/g, '-');
          var labelVal = (document.getElementById('rrp-dept-new-label').value || '').trim();
          var msgEl    = document.getElementById('rrp-dept-msg');
          var btn      = document.getElementById('rrp-dept-save-btn');
          if (!idVal)    { msgEl.innerHTML = '<span class="rrp-error">ID required.</span>'; return; }
          if (!labelVal) { msgEl.innerHTML = '<span class="rrp-error">Name required.</span>'; return; }
          if (departments.some(function (d) { return d.id === idVal; })) {
            msgEl.innerHTML = '<span class="rrp-error">ID already exists.</span>'; return;
          }
          btn.disabled = true; btn.textContent = 'Saving\u2026';
          var updated = departments.concat([{ id: idVal, label: labelVal }]);
          api('PUT', '/config', { departments: updated })
            .then(function () { renderProgramManagement(container, backFn); })
            .catch(function () {
              btn.disabled = false; btn.textContent = '\u2713 Save';
              msgEl.innerHTML = '<span class="rrp-error">Save failed.</span>';
            });
        });
      }
      container.querySelectorAll('[data-delete-dept]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var did  = btn.getAttribute('data-delete-dept');
          var dept = departments.find(function (d) { return d.id === did; });
          if (!confirm('Delete department "' + (dept ? dept.label : did) + '"?')) return;
          var remaining = departments.filter(function (d) { return d.id !== did; });
          api('PUT', '/config', { departments: remaining })
            .then(function () { renderProgramManagement(container, backFn); })
            .catch(function () { alert('Delete failed.'); });
        });
      });

      // ── Checkbox chip toggle ──────────────────────────────────────────────
      container.querySelectorAll('.rrp-prog-coord-checks input').forEach(function (cb) {
        cb.addEventListener('change', function () {
          cb.closest('.rrp-check-chip').classList.toggle('checked', cb.checked);
        });
      });

      // ── Event listeners ──────────────────────────────────────────────────
      container.querySelectorAll('[data-edit-prog]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-edit-prog');
          container.querySelectorAll('.rrp-prog-edit-row').forEach(function (r) { r.style.display = 'none'; });
          var editRow = document.getElementById('rrp-prog-edit-' + id);
          if (editRow) editRow.style.display = 'grid';
        });
      });

      container.querySelectorAll('[data-cancel-prog]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var editRow = document.getElementById('rrp-prog-edit-' + btn.getAttribute('data-cancel-prog'));
          if (editRow) editRow.style.display = 'none';
        });
      });

      container.querySelectorAll('[data-delete-prog]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id   = btn.getAttribute('data-delete-prog');
          var prog = programs.find(function (p) { return p.id === id; });
          if (!confirm('Delete program "' + (prog ? prog.label : id) + '"?\nThis cannot be undone.')) return;
          var remaining = programs.filter(function (p) { return p.id !== id; });
          api('PUT', '/config', { programs: remaining }).then(function () {
            renderProgramManagement(container, backFn);
          }).catch(function () { alert('Delete failed. Please try again.'); });
        });
      });

      container.querySelectorAll('[data-save-prog]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id      = btn.getAttribute('data-save-prog');
          var editRow = document.getElementById('rrp-prog-edit-' + id);
          var select  = editRow ? editRow.querySelector('.rrp-prog-dir-select') : null;
          var msgEl   = editRow ? editRow.querySelector('.rrp-prog-save-msg')   : null;
          if (!editRow) return;

          var newDirId = select ? select.value : '';
          var newCoordIds = [];
          editRow.querySelectorAll('.rrp-prog-coord-cb:checked').forEach(function (cb) {
            newCoordIds.push(cb.value);
          });

          var idx = -1;
          for (var i = 0; i < programs.length; i++) { if (programs[i].id === id) { idx = i; break; } }
          if (idx === -1) return;
          programs[idx] = Object.assign({}, programs[idx], { programDirectorId: newDirId, coordinatorIds: newCoordIds });

          btn.disabled = true; btn.textContent = 'Saving\u2026';
          if (msgEl) msgEl.innerHTML = '';

          api('PUT', '/config', { programs: programs }).then(function () {
            var row = container.querySelector('[data-prog-id="' + id + '"]');
            if (row) {
              var dirCell = row.querySelector('[data-field="director"]');
              if (dirCell) {
                var updatedDir = reviewerMap[newDirId];
                dirCell.innerHTML = updatedDir ? escapeHtml(updatedDir.name) : (newDirId ? escapeHtml(newDirId) : '<em style="color:var(--rrp-text-muted)">Not assigned</em>');
              }
              var coordCell = row.querySelector('[data-field="coordinators"]');
              if (coordCell) {
                var names = newCoordIds.map(function (cid) { return coordMap[cid] || cid; });
                coordCell.innerHTML = names.length ? names.map(function (n) { return '<span class="rrp-chip">' + escapeHtml(n) + '</span>'; }).join(' ') : '<em style="color:var(--rrp-text-muted)">None</em>';
              }
            }
            if (editRow) editRow.style.display = 'none';
            btn.disabled = false; btn.textContent = '\u2713 Save';
          }).catch(function (err) {
            if (msgEl) msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml((err && err.data && err.data.error) || 'Save failed') + '</span>';
            btn.disabled = false; btn.textContent = '\u2713 Save';
          });
        });
      });

    }).catch(function () {
      var body = document.getElementById('rrp-programs-body');
      if (body) body.innerHTML = '<div class="rrp-error">Failed to load programs. Please try again.</div>';
    });
  }

  // ── Portal Settings (university branding + SSO) ───────────────────────────
  function renderPortalSettings(container, backFn) {
    var _saving = false;

    function showMsg(msg, isError) {
      var el = document.getElementById('rrp-settings-msg');
      if (!el) return;
      el.innerHTML = '<span class="' + (isError ? 'rrp-error' : 'rrp-success') + '">' + escapeHtml(msg) + '</span>';
    }

    container.innerHTML =
      '<div class="rrp-mgmt-page-header">' +
        '<button type="button" class="rrp-btn secondary" id="rrp-settings-back">&#8592; Back</button>' +
        '<h1>&#9881; Portal Settings</h1>' +
      '</div>' +
      '<div id="rrp-settings-body"><p class="rrp-loading">Loading settings\u2026</p></div>';

    document.getElementById('rrp-settings-back').addEventListener('click', function () {
      if (backFn) backFn(); else renderCoordinatorDashboard(container);
    });

    Promise.all([
      api('GET', '/settings'),
      api('GET', '/config').catch(function () { return {}; })
    ]).then(function (results) {
      var s = results[0];
      var rrpCfg = results[1] || {};

      document.getElementById('rrp-settings-body').innerHTML =
        '<form id="rrp-settings-form" style="max-width:680px;" autocomplete="off">' +

        '<fieldset class="rrp-fieldset">' +
          '<legend>University Branding</legend>' +
          '<label class="rrp-label">University Name' +
            '<input type="text" class="rrp-input" name="university_name" value="' + escapeHtml(s.university_name || '') + '" required maxlength="120">' +
          '</label>' +
          '<label class="rrp-label">Short Name / Abbreviation' +
            '<input type="text" class="rrp-input" name="university_short_name" value="' + escapeHtml(s.university_short_name || '') + '" maxlength="30">' +
          '</label>' +
          '<label class="rrp-label">Logo URL <span style="font-weight:400;font-size:.85em;">(leave blank to use default)</span>' +
            '<input type="url" class="rrp-input" name="university_logo_url" value="' + escapeHtml(s.university_logo_url || '') + '" placeholder="https://\u2026" maxlength="512">' +
          '</label>' +
          '<label class="rrp-label">Portal Name' +
            '<input type="text" class="rrp-input" name="portal_name" value="' + escapeHtml(s.portal_name || '') + '" required maxlength="120">' +
          '</label>' +
          '<label class="rrp-label">Contact Email' +
            '<input type="email" class="rrp-input" name="contact_email" value="' + escapeHtml(s.contact_email || '') + '" maxlength="254">' +
          '</label>' +
        '</fieldset>' +

        '<fieldset class="rrp-fieldset" style="margin-top:1.25rem;">' +
          '<legend>Single Sign-On (SSO)</legend>' +
          '<label class="rrp-label" style="flex-direction:row;align-items:center;gap:.6rem;">' +
            '<input type="checkbox" name="sso_enabled" id="rrp-sso-toggle"' + (s.sso_enabled ? ' checked' : '') + '>' +
            '<span>Enable SSO</span>' +
          '</label>' +
          '<div id="rrp-sso-fields" style="' + (s.sso_enabled ? '' : 'display:none;') + 'margin-top:1rem;">' +
            '<label class="rrp-label">SSO Provider' +
              '<select class="rrp-input" name="sso_provider" id="rrp-sso-provider">' +
                '<option value="entra"' + (s.sso_provider === 'entra' ? ' selected' : '') + '>Microsoft Entra ID (Azure AD)</option>' +
              '</select>' +
            '</label>' +
            '<div id="rrp-entra-fields" style="' + (s.sso_provider !== 'entra' ? 'display:none;' : '') + '">' +
              '<label class="rrp-label">Tenant ID' +
                '<input type="text" class="rrp-input" name="entra_tenant_id" value="' + escapeHtml(s.entra_tenant_id || '') + '" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" maxlength="40">' +
              '</label>' +
              '<label class="rrp-label">Client ID (Application ID)' +
                '<input type="text" class="rrp-input" name="entra_client_id" value="' + escapeHtml(s.entra_client_id || '') + '" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" maxlength="40">' +
              '</label>' +
              '<label class="rrp-label">Client Secret' +
                '<input type="password" class="rrp-input" name="entra_client_secret" value="" autocomplete="new-password" placeholder="' + (s.entra_client_secret === '[encrypted]' ? 'Leave blank to keep current secret' : 'Enter client secret') + '">' +
                '<small style="color:var(--rrp-text-muted);">Stored encrypted at rest using AES-256-GCM.</small>' +
              '</label>' +
              '<label class="rrp-label">Redirect URI' +
                '<input type="url" class="rrp-input" name="entra_redirect_uri" value="' + escapeHtml(s.entra_redirect_uri || '') + '" placeholder="https://your-domain/wp-json/research-portal/v1/auth/callback" maxlength="512">' +
              '</label>' +
              '<label class="rrp-label" style="flex-direction:row;align-items:center;gap:.6rem;">' +
                '<input type="checkbox" name="entra_auto_provision"' + (s.entra_auto_provision !== false ? ' checked' : '') + '>' +
                '<span>Auto-provision new users on first login <small style="color:var(--rrp-text-muted);">(no portal role — admin must assign)</small></span>' +
              '</label>' +
            '</div>' +
          '</div>' +
        '</fieldset>' +

        '<fieldset class="rrp-fieldset" style="margin-top:1.25rem;">' +
          '<legend>Email / SMTP</legend>' +
          '<label class="rrp-label" style="flex-direction:row;align-items:center;gap:.6rem;">' +
            '<input type="checkbox" name="smtp_enabled" id="rrp-smtp-toggle"' + (s.smtp_enabled ? ' checked' : '') + '>' +
            '<span>Enable SMTP for outgoing email</span>' +
          '</label>' +
          '<div id="rrp-smtp-fields" style="' + (s.smtp_enabled ? '' : 'display:none;') + 'margin-top:1rem;">' +
            '<label class="rrp-label">From Name' +
              '<input type="text" class="rrp-input" name="smtp_from_name" value="' + escapeHtml(s.smtp_from_name || '') + '" maxlength="100">' +
            '</label>' +
            '<label class="rrp-label">From Email' +
              '<input type="email" class="rrp-input" name="smtp_from_email" value="' + escapeHtml(s.smtp_from_email || '') + '" maxlength="254">' +
            '</label>' +
            '<label class="rrp-label">SMTP Host' +
              '<input type="text" class="rrp-input" name="smtp_host" value="' + escapeHtml(s.smtp_host || '') + '" placeholder="smtp.example.com" maxlength="253">' +
            '</label>' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">' +
              '<label class="rrp-label">Port' +
                '<input type="number" class="rrp-input" name="smtp_port" value="' + (parseInt(s.smtp_port, 10) || 587) + '" min="1" max="65535">' +
              '</label>' +
              '<label class="rrp-label">Encryption' +
                '<select class="rrp-input" name="smtp_encryption">' +
                  '<option value=""'    + (s.smtp_encryption === ''    ? ' selected' : '') + '>None</option>' +
                  '<option value="ssl"' + (s.smtp_encryption === 'ssl' ? ' selected' : '') + '>SSL/TLS (port 465)</option>' +
                  '<option value="tls"' + ((s.smtp_encryption === 'tls' || (!s.smtp_encryption && s.smtp_encryption !== '')) ? ' selected' : '') + '>STARTTLS (port 587)</option>' +
                '</select>' +
              '</label>' +
            '</div>' +
            '<label class="rrp-label" style="flex-direction:row;align-items:center;gap:.6rem;margin-top:.5rem;">' +
              '<input type="checkbox" name="smtp_auth" id="rrp-smtp-auth-toggle"' + (s.smtp_auth !== false ? ' checked' : '') + '>' +
              '<span>SMTP Authentication</span>' +
            '</label>' +
            '<div id="rrp-smtp-auth-fields" style="' + (s.smtp_auth !== false ? '' : 'display:none;') + 'margin-top:.75rem;">' +
              '<label class="rrp-label">Username' +
                '<input type="text" class="rrp-input" name="smtp_user" value="' + escapeHtml(s.smtp_user || '') + '" autocomplete="username" maxlength="254">' +
              '</label>' +
              '<label class="rrp-label">Password' +
                '<input type="password" class="rrp-input" name="smtp_password" value="" autocomplete="new-password" placeholder="' + (s.smtp_password === '[encrypted]' ? 'Leave blank to keep current password' : 'Enter SMTP password') + '">' +
                '<small style="color:var(--rrp-text-muted);">Stored encrypted at rest using AES-256-GCM.</small>' +
              '</label>' +
            '</div>' +
            '<div style="margin-top:1rem;">' +
              '<button type="button" class="rrp-btn secondary" id="rrp-smtp-test-btn">&#128231; Send Test Email</button>' +
              '<span id="rrp-smtp-test-msg" style="margin-left:.75rem;font-size:.9em;"></span>' +
            '</div>' +
          '</div>' +
        '</fieldset>' +

        '<fieldset class="rrp-fieldset" style="margin-top:1.25rem;">' +
          '<legend>Deadline Options</legend>' +
          '<label class="rrp-label" style="flex-direction:row;align-items:center;gap:.6rem;">' +
            '<input type="checkbox" name="deadline_skip_weekends" id="rrp-deadline-skip-weekends"' + ((rrpCfg.deadlineOptions && rrpCfg.deadlineOptions.skipWeekends !== false ? true : false) ? ' checked' : '') + '>' +
            '<span>Skip weekends when calculating review deadlines</span>' +
          '</label>' +
          '<label class="rrp-label" style="margin-top:.75rem;">Grace Period (days)' +
            '<input type="number" class="rrp-input" name="deadline_grace_days" id="rrp-deadline-grace-days" value="' + (rrpCfg.deadlineOptions ? (parseInt(rrpCfg.deadlineOptions.gracePeriodDays, 10) || 0) : 2) + '" min="0" max="30" style="max-width:100px;">' +
            '<small style="color:var(--rrp-text-muted);">How many days after the deadline before a submission is considered escalated.</small>' +
          '</label>' +
          '<label class="rrp-label" style="margin-top:.75rem;">Public Holidays <span style="font-weight:400;font-size:.85em;">(one YYYY-MM-DD per line)</span>' +
            '<textarea class="rrp-input" name="deadline_holidays" id="rrp-deadline-holidays" rows="5" style="font-family:monospace;font-size:.9em;" placeholder="2026-01-01&#10;2026-12-25">' + escapeHtml((rrpCfg.deadlineOptions && rrpCfg.deadlineOptions.publicHolidays ? rrpCfg.deadlineOptions.publicHolidays.join('\n') : '')) + '</textarea>' +
          '</label>' +
        '</fieldset>' +

        '<div style="display:flex;gap:.75rem;align-items:center;margin-top:1.5rem;">' +
          '<button type="submit" class="rrp-btn" id="rrp-settings-save">&#128190; Save Settings</button>' +
          '<span id="rrp-settings-msg"></span>' +
        '</div>' +
        '</form>';

      // SSO enabled toggle
      document.getElementById('rrp-sso-toggle').addEventListener('change', function () {
        document.getElementById('rrp-sso-fields').style.display = this.checked ? '' : 'none';
      });
      // Provider selector
      document.getElementById('rrp-sso-provider').addEventListener('change', function () {
        document.getElementById('rrp-entra-fields').style.display = this.value === 'entra' ? '' : 'none';
      });

      // SMTP enabled toggle
      document.getElementById('rrp-smtp-toggle').addEventListener('change', function () {
        document.getElementById('rrp-smtp-fields').style.display = this.checked ? '' : 'none';
      });
      // SMTP auth toggle
      document.getElementById('rrp-smtp-auth-toggle').addEventListener('change', function () {
        document.getElementById('rrp-smtp-auth-fields').style.display = this.checked ? '' : 'none';
      });
      // Send test email
      document.getElementById('rrp-smtp-test-btn').addEventListener('click', function () {
        var msgEl = document.getElementById('rrp-smtp-test-msg');
        var btn   = this;
        btn.disabled = true;
        if (msgEl) msgEl.innerHTML = '<span style="color:var(--rrp-text-muted);">Sending\u2026</span>';
        api('POST', '/settings/test-email', {})
          .then(function (res) {
            if (msgEl) msgEl.innerHTML = '<span class="rrp-success">' + escapeHtml(res.message || 'Test email sent.') + '</span>';
          })
          .catch(function (err) {
            var errMsg = (err && err.data && err.data.error) || 'Failed to send test email.';
            if (msgEl) msgEl.innerHTML = '<span class="rrp-error">' + escapeHtml(errMsg) + '</span>';
          })
          .finally(function () { btn.disabled = false; });
      });

      // Form submit
      document.getElementById('rrp-settings-form').addEventListener('submit', function (e) {
        e.preventDefault();
        if (_saving) return;
        _saving = true;
        var btn = document.getElementById('rrp-settings-save');
        btn.disabled = true; btn.textContent = 'Saving\u2026';

        var fd = new FormData(this);
        var payload = {
          university_name:       (fd.get('university_name')       || '').trim(),
          university_short_name: (fd.get('university_short_name') || '').trim(),
          university_logo_url:   (fd.get('university_logo_url')   || '').trim(),
          portal_name:           (fd.get('portal_name')           || '').trim(),
          contact_email:         (fd.get('contact_email')         || '').trim(),
          sso_enabled:           document.getElementById('rrp-sso-toggle').checked,
          sso_provider:          fd.get('sso_provider') || 'entra',
          entra_tenant_id:       (fd.get('entra_tenant_id')       || '').trim(),
          entra_client_id:       (fd.get('entra_client_id')       || '').trim(),
          entra_redirect_uri:    (fd.get('entra_redirect_uri')    || '').trim(),
          entra_auto_provision:  !!fd.get('entra_auto_provision'),
          // SMTP
          smtp_enabled:    document.getElementById('rrp-smtp-toggle').checked,
          smtp_from_name:  (fd.get('smtp_from_name')  || '').trim(),
          smtp_from_email: (fd.get('smtp_from_email') || '').trim(),
          smtp_host:       (fd.get('smtp_host')        || '').trim(),
          smtp_port:       parseInt(fd.get('smtp_port') || '587', 10),
          smtp_encryption: fd.get('smtp_encryption') || '',
          smtp_auth:       document.getElementById('rrp-smtp-auth-toggle').checked,
          smtp_user:       (fd.get('smtp_user')        || '').trim(),
        };
        // Only send secret if user actually typed something
        var secret = (fd.get('entra_client_secret') || '').trim();
        if (secret) payload.entra_client_secret = secret;
        // Only send SMTP password if user typed a new one
        var smtpPass = (fd.get('smtp_password') || '').trim();
        if (smtpPass) payload.smtp_password = smtpPass;

        // Collect deadline options
        var holidaysRaw = (fd.get('deadline_holidays') || '').split('\n').map(function (d) { return d.trim(); }).filter(function (d) { return /^\d{4}-\d{2}-\d{2}$/.test(d); });
        var deadlinePayload = {
          deadlineOptions: {
            skipWeekends:    document.getElementById('rrp-deadline-skip-weekends').checked,
            gracePeriodDays: Math.max(0, parseInt(fd.get('deadline_grace_days') || '2', 10)),
            publicHolidays:  holidaysRaw,
          }
        };

        Promise.all([
          api('PUT', '/settings', payload),
          api('PUT', '/config', deadlinePayload)
        ])
          .then(function () {
            showMsg('Settings saved.', false);
            btn.disabled = false; btn.textContent = '\uD83D\uDCBE Save Settings';
            _saving = false;
          })
          .catch(function (err) {
            showMsg((err && err.data && err.data.error) || 'Save failed. Please try again.', true);
            btn.disabled = false; btn.textContent = '\uD83D\uDCBE Save Settings';
            _saving = false;
          });
      });

    }).catch(function () {
      document.getElementById('rrp-settings-body').innerHTML =
        '<div class="rrp-error">Unable to load settings.</div>';
    });
  }

  // ── Audit Log modal ──────────────────────────────────────────────────────
  function openAuditLogModal(subId) {
    var existing = document.getElementById('rrp-audit-modal');
    if (existing) existing.remove();

    var overlay = document.createElement('div');
    overlay.id = 'rrp-audit-modal';
    overlay.className = 'rrp-audit-overlay';
    overlay.innerHTML =
      '<div class="rrp-audit-modal" role="dialog" aria-modal="true" aria-label="Audit log">' +
        '<div class="rrp-audit-modal-header">' +
          '<h2>&#128221; Activity Log &mdash; <span class="rrp-audit-sub-id">' + escapeHtml(subId) + '</span></h2>' +
          '<button type="button" class="rrp-audit-close" aria-label="Close">&times;</button>' +
        '</div>' +
        '<div class="rrp-audit-modal-body" id="rrp-audit-body">' +
          '<p class="rrp-loading">Loading&hellip;</p>' +
        '</div>' +
      '</div>';

    document.body.appendChild(overlay);

    overlay.querySelector('.rrp-audit-close').addEventListener('click', function () { overlay.remove(); });
    overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });
    document.addEventListener('keydown', function esc(e) {
      if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', esc); }
    });

    api('GET', '/submissions/' + encodeURIComponent(subId) + '/audit-log')
      .then(function (res) {
        var log  = res.auditLog || [];
        var body = document.getElementById('rrp-audit-body');
        if (!body) return;
        if (!log.length) {
          body.innerHTML = '<p class="rrp-audit-empty">No activity recorded yet for this submission.</p>';
          return;
        }
        var actionLabels = {
          submission_created: '&#128196; Submission Created',
          status_changed:     '&#128260; Status Changed',
          reviewers_assigned: '&#128100; Reviewers Assigned',
          decision_recorded:  '&#9989; Decision Recorded',
          feedback_added:     '&#128172; Feedback Added',
          revision_submitted: '&#128260; Revision Submitted',
          content_updated:    '&#9998; Content Updated',
          stage_skipped:      '&#9193; Stage Skipped',
          file_uploaded:      '&#128206; File Uploaded',
        };
        body.innerHTML =
          '<ol class="rrp-audit-list">' +
          log.map(function (entry) {
            var dt    = entry.at ? new Date(entry.at) : null;
            var dtStr = dt ? dt.toLocaleString() : '\u2014';
            var actor = (entry.actor && (entry.actor.name || entry.actor.email))
                          ? escapeHtml(entry.actor.name || entry.actor.email)
                          : 'System';
            var label = actionLabels[entry.action] || escapeHtml(entry.action || '');
            return '<li class="rrp-audit-entry">' +
              '<div class="rrp-audit-entry-header">' +
                '<span class="rrp-audit-action">' + label + '</span>' +
                '<time class="rrp-audit-time" datetime="' + escapeHtml(entry.at || '') + '">' + escapeHtml(dtStr) + '</time>' +
              '</div>' +
              '<div class="rrp-audit-detail">' + escapeHtml(entry.detail || '') + '</div>' +
              '<div class="rrp-audit-actor">by ' + actor + '</div>' +
            '</li>';
          }).join('') +
          '</ol>';
      })
      .catch(function () {
        var body = document.getElementById('rrp-audit-body');
        if (body) body.innerHTML = '<div class="rrp-error">Could not load activity log.</div>';
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
