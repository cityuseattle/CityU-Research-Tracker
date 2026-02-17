/* Research Review Portal - WordPress */
.rrp-portal {
  --rrp-primary: #0066aa;
  --rrp-primary-dark: #004d80;
  --rrp-complete: #2D6B4F;
  --rrp-text: #1a1a1a;
  --rrp-text-muted: #444;
  --rrp-border: #e0e0e0;
  --rrp-border-light: #e8f2fa;
  --rrp-bg: #ffffff;
  --rrp-bg-page: #f8f9fa;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
  color: var(--rrp-text);
  max-width: 900px;
  margin: 0 auto;
  padding: 1.5rem;
  background: var(--rrp-bg-page);
  border-radius: 8px;
}

.rrp-portal .rrp-loading { color: var(--rrp-text-muted); }

.rrp-portal h1 {
  font-size: 1.5rem;
  color: var(--rrp-primary);
  margin: 0 0 1rem 0;
}

.rrp-portal h2 {
  font-size: 1.2rem;
  margin: 1.25rem 0 0.5rem 0;
  color: var(--rrp-primary-dark);
}

.rrp-portal nav {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--rrp-border);
}

.rrp-portal button,
.rrp-portal .rrp-btn {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  border: none;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  background: var(--rrp-primary);
  color: #fff;
}

.rrp-portal button.secondary,
.rrp-portal .rrp-btn.secondary {
  background: var(--rrp-border-light);
  color: var(--rrp-primary);
}

.rrp-portal button:disabled { opacity: 0.6; cursor: not-allowed; }

.rrp-portal input[type="text"],
.rrp-portal input[type="email"],
.rrp-portal select,
.rrp-portal textarea {
  width: 100%;
  max-width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--rrp-border);
  border-radius: 6px;
  font-size: 0.95rem;
  margin-bottom: 0.75rem;
  box-sizing: border-box;
}

.rrp-portal label {
  display: block;
  margin-bottom: 0.25rem;
  font-weight: 500;
  color: var(--rrp-text);
}

.rrp-portal .rrp-form-block { margin-bottom: 1.25rem; }

.rrp-portal .rrp-type-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
  margin: 1rem 0;
}

.rrp-portal .rrp-type-card {
  padding: 1.25rem;
  border: 2px solid var(--rrp-border);
  border-radius: 8px;
  background: var(--rrp-bg);
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.rrp-portal .rrp-type-card:hover,
.rrp-portal .rrp-type-card.selected {
  border-color: var(--rrp-primary);
  box-shadow: 0 0 0 2px var(--rrp-border-light);
}

.rrp-portal .rrp-type-card h3 { margin: 0 0 0.35rem 0; font-size: 1rem; color: var(--rrp-primary); }
.rrp-portal .rrp-type-card p { margin: 0; font-size: 0.85rem; color: var(--rrp-text-muted); }

.rrp-portal .rrp-error {
  padding: 0.75rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 6px;
  color: #b91c1c;
  margin-bottom: 1rem;
}

.rrp-portal .rrp-success {
  padding: 0.75rem;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 6px;
  color: var(--rrp-complete);
  margin-bottom: 1rem;
}

.rrp-portal .rrp-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.rrp-portal .rrp-list li {
  padding: 0.75rem 1rem;
  border: 1px solid var(--rrp-border);
  border-radius: 6px;
  margin-bottom: 0.5rem;
  background: var(--rrp-bg);
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.rrp-portal .rrp-list li .rrp-status {
  font-size: 0.8rem;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  background: var(--rrp-border-light);
  color: var(--rrp-primary);
}

.rrp-portal .rrp-public-list .rrp-abstract {
  font-size: 0.9rem;
  color: var(--rrp-text-muted);
  margin-top: 0.35rem;
  max-height: 4em;
  overflow: hidden;
}

.rrp-portal .rrp-check-email {
  max-width: 400px;
  margin: 1rem 0;
}

.rrp-portal .rrp-view-toggle {
  margin-bottom: 1rem;
}

.rrp-portal .rrp-view-toggle button {
  margin-right: 0.5rem;
}

.rrp-portal .rrp-view-toggle button.active {
  background: var(--rrp-primary-dark);
}
