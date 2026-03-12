(function () {
  'use strict';

  var restBase = (window.RRP && window.RRP.restBase) ? window.RRP.restBase.replace(/\/$/, '') : '';
  var nonce = (window.RRP && window.RRP.nonce) || '';

  function api(method, path, body) {
    var url = restBase + path;
    var opts = {
      method: method,
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

  var SUBMISSION_TYPES = [
    { id: 'conference', title: 'Conference / Symposium', subtitle: 'Applied Research Symposium, Doctor of IT Forum' },
    { id: 'publication', title: 'Publication', subtitle: 'Journal and publication submissions' },
    { id: 'student', title: 'Student Project', subtitle: 'Capstone and student research' },
    { id: 'grant', title: 'Grant', subtitle: 'Funding and grant proposals' }
  ];

  var typeToApi = { conference: 'conference', publication: 'publication', student: 'student-project', grant: 'grant' };

  function renderSelection(container) {
    container.innerHTML =
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
  }

  function renderForm(container, type) {
    var apiType = typeToApi[type] || type;
    container.innerHTML =
      '<h1>Submit</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom: 1rem;" data-back>← Back</button>' +
      '<div id="rrp-form-errors"></div>' +
      '<form id="rrp-submit-form">' +
        '<div class="rrp-form-block"><label>Name *</label><input type="text" name="submitterName" required></div>' +
        '<div class="rrp-form-block"><label>Email *</label><input type="email" name="submitterEmail" required></div>' +
        '<div class="rrp-form-block"><label>Affiliation *</label><input type="text" name="affiliation" required></div>' +
        '<div class="rrp-form-block"><label>Title *</label><input type="text" name="title" required maxlength="200"></div>' +
        '<div class="rrp-form-block"><label>Abstract *</label><textarea name="abstract" rows="6" required></textarea></div>' +
        '<div class="rrp-form-block"><label>Keywords (3–5, comma-separated) *</label><input type="text" name="keywords" required></div>' +
        '<div class="rrp-form-block"><label>Research area / Category *</label><input type="text" name="researchArea" required></div>' +
        (apiType === 'conference' ? '<div class="rrp-form-block"><label>Presentation preference *</label><select name="presentationPreference"><option value="oral">Oral</option><option value="poster">Poster</option></select></div>' : '') +
        (apiType === 'publication' ? '<div class="rrp-form-block"><label>Publication type *</label><input type="text" name="publicationType" required></div>' : '') +
        (apiType === 'student-project' ? '<div class="rrp-form-block"><label>Project type *</label><input type="text" name="projectType" required></div>' : '') +
        (apiType === 'grant' ? '<div class="rrp-form-block"><label>Funding agency *</label><input type="text" name="fundingAgency" required></div>' : '') +
        '<button type="submit" class="rrp-btn">Submit</button>' +
      '</form>';

    container.querySelector('[data-back]').addEventListener('click', function () { renderSelection(container); });

    container.querySelector('#rrp-submit-form').addEventListener('submit', function (e) {
      e.preventDefault();
      var form = e.target;
      var errEl = document.getElementById('rrp-form-errors');
      errEl.innerHTML = '';
      var fd = new FormData(form);
      var body = { type: apiType };
      fd.forEach(function (v, k) { body[k] = v; });
      api('POST', '/submit', body)
        .then(function (res) {
          container.innerHTML =
            '<h1>Submission received</h1>' +
            '<div class="rrp-success">' + escapeHtml(res.message || 'Thank you.') + '</div>' +
            '<p><strong>Reference ID:</strong> ' + escapeHtml(res.id) + '</p>' +
            '<button type="button" class="rrp-btn secondary" data-back>← Back to portal</button>';
          container.querySelector('[data-back]').addEventListener('click', function () { renderSelection(container); });
        })
        .catch(function (err) {
          var msg = (err.data && err.data.errors && err.data.errors.length) ? err.data.errors.join(' ') : (err.data && err.data.error) || 'Submission failed.';
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
              '<button type="button" class="rrp-btn secondary" data-timeline="' + escapeHtml(s.id) + '">Timeline</button></li>';
          }).join('');
          document.getElementById('rrp-dashboard-progress').innerHTML = '<ul class="rrp-list">' + (rows || '<li>No matching submissions.</li>') + '</ul>';
          document.querySelectorAll('[data-timeline]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              renderTimeline(btn.getAttribute('data-timeline'), container);
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
          listEl.innerHTML =
            '<h2>Your submissions</h2>' +
            '<ul class="rrp-list">' +
              mine.map(function (s) {
                return '<li><span><strong>' + escapeHtml(s.title || s.id) + '</strong> · ' + escapeHtml(s.id) + '</span><span class="rrp-status">' + escapeHtml(s.status || '') + '</span></li>';
              }).join('') +
            '</ul>';
        })
        .catch(function () {
          listEl.innerHTML = '<div class="rrp-error">Could not load submissions.</div>';
        });
    });
  }

  function renderAnalytics(container) {
    container.innerHTML =
      '<h1>Analytics</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom: 1rem;" data-back>← Back</button>' +
      '<div id="rrp-analytics-content" class="rrp-analytics-content">Loading analytics…</div>' +
      '<div class="rrp-analytics-actions" style="margin-top: 1rem;"><button class="rrp-btn" id="rrp-export-csv">Export CSV</button><button class="rrp-btn" id="rrp-export-xlsx" style="margin-left:0.5rem;">Export XLSX</button></div>';

    container.querySelector('[data-back]').addEventListener('click', function () { renderSelection(container); });

    Promise.all([
      api('GET', '/analytics/workflow'),
      api('GET', '/analytics/performance')
    ]).then(function (results) {
      var w = results[0];
      var p = results[1];
      var el = document.getElementById('rrp-analytics-content');
      el.innerHTML =
        '<p><strong>Total submissions:</strong> ' + (w.totalSubmissions || 0) + '</p>' +
        '<p><strong>Average stages per submission:</strong> ' + (w.averageStages || 0) + '</p>' +
        '<p><strong>Mean reviewer load:</strong> ' + (w.meanReviewerLoad || 0) + '</p>' +
        '<p><strong>Finalized submissions:</strong> ' + (p.finalizedCount || 0) + '</p>' +
        '<p><strong>In-progress submissions:</strong> ' + (p.inProgressCount || 0) + '</p>' +
        '<p><strong>Average time to decision (days):</strong> ' + (p.averageTimeToDecisionDays || 0) + '</p>' +
        '<p><strong>Late review alerts:</strong> ' + (p.lateReviewAlerts || 0) + '</p>';

      document.getElementById('rrp-export-csv').addEventListener('click', function () {
        api('GET', '/reports/export?type=workflow&format=csv').then(function (resp) {
          var blob = new Blob([atob(resp.content)], { type: 'text/csv' });
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = resp.filename || 'rrp-workflow.csv';
          a.click();
        }).catch(function () {
          alert('Export failed');
        });
      });

      document.getElementById('rrp-export-xlsx').addEventListener('click', function () {
        api('GET', '/reports/export?type=workflow&format=xlsx').then(function (resp) {
          var blob = new Blob([atob(resp.content)], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = resp.filename || 'rrp-workflow.xlsx';
          a.click();
        }).catch(function () {
          alert('Export failed');
        });
      });

    }).catch(function (err) {
      document.getElementById('rrp-analytics-content').innerHTML = '<div class="rrp-error">Unable to load analytics. Please login and try again.</div>';
    });
  }

  function renderReviewerDashboard(container) {
    container.innerHTML =
      '<h1>Reviewer Dashboard</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom: 1rem;" data-back>← Back</button>' +
      '<div class="rrp-form-block"><label>Reviewer Email</label><input type="email" id="rrp-reviewer-email" placeholder="reviewer@cityu.edu.hk" required></div>' +
      '<button type="button" class="rrp-btn" id="rrp-reviewer-metrics-btn">Load Metrics</button>' +
      '<div id="rrp-reviewer-metrics" style="margin-top:1rem;"></div>';

    container.querySelector('[data-back]').addEventListener('click', function () { renderSelection(container); });

    document.getElementById('rrp-reviewer-metrics-btn').addEventListener('click', function () {
      var email = document.getElementById('rrp-reviewer-email').value;
      if (!email) {
        alert('Please enter reviewer email.');
        return;
      }
      var output = document.getElementById('rrp-reviewer-metrics');
      output.innerHTML = '<p class="rrp-loading">Loading reviewer metrics…</p>';

      Promise.all([
        api('GET', '/analytics/reviewer?reviewerEmail=' + encodeURIComponent(email)),
        api('GET', '/reviews?reviewerEmail=' + encodeURIComponent(email))
      ]).then(function (results) {
        var metrics = results[0];
        var submissions = results[1].submissions || [];

        output.innerHTML =
          '<p><strong>Email:</strong> ' + escapeHtml(metrics.reviewerEmail || email) + '</p>' +
          '<p><strong>Total assignments:</strong> ' + (metrics.totalAssigned || 0) + '</p>' +
          '<p><strong>Pending:</strong> ' + (metrics.pending || 0) + '</p>' +
          '<p><strong>Approved:</strong> ' + (metrics.approved || 0) + '</p>' +
          '<p><strong>Rejected:</strong> ' + (metrics.rejected || 0) + '</p>' +
          '<p><strong>Needs revision:</strong> ' + (metrics.needsRevision || 0) + '</p>' +
          '<p><strong>Overdue:</strong> ' + (metrics.overdue || 0) + '</p>';

        if (submissions.length) {
          output.innerHTML += '<h3>Assigned submissions</h3><ul class="rrp-list">' + submissions.map(function (item) {
            return '<li><strong>' + escapeHtml(item.title || item.id) + '</strong> · ' + escapeHtml(item.status || '') + '</li>';
          }).join('') + '</ul>';
        }
      }).catch(function () {
        output.innerHTML = '<div class="rrp-error">Unable to load reviewer metrics.</div>';
      });
    });
  }

  function renderPublic(container) {
    container.innerHTML =
      '<h1>Research & symposium</h1>' +
      '<button type="button" class="rrp-btn secondary" style="margin-bottom: 1rem;" data-back>← Back</button>' +
      '<p style="color: var(--rrp-text-muted);">Accepted and published work.</p>' +
      '<div id="rrp-public-list"></div>';

    container.querySelector('[data-back]').addEventListener('click', function () { renderSelection(container); });

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

  function escapeHtml(s) {
    if (s == null) return '';
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
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

  function boot() {
    var el = document.getElementById('research-review-portal');
    if (!el || !restBase) {
        if (el) el.innerHTML = '<p class="rrp-error">Portal API not configured.</p>';
        return;
    }
    renderSelection(el);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
