/**
 * Credentik Embed Widget v1.0
 *
 * Usage:
 *   <div id="credentik-widget"></div>
 *   <script src="https://api.credentik.com/embed.js"
 *           data-agency="your-slug"
 *           data-widget="booking|testimonials|eligibility"
 *           data-theme="light|dark">
 *   </script>
 */
(function () {
  'use strict';

  const SCRIPT = document.currentScript;
  const SLUG = SCRIPT?.getAttribute('data-agency');
  const WIDGET = SCRIPT?.getAttribute('data-widget') || 'booking';
  const THEME = SCRIPT?.getAttribute('data-theme');
  const CONTAINER_ID = SCRIPT?.getAttribute('data-container') || 'credentik-widget';
  const API_BASE = SCRIPT?.src ? new URL(SCRIPT.src).origin + '/api' : '';

  if (!SLUG) {
    console.error('[Credentik] Missing data-agency attribute');
    return;
  }

  // ── Styles ──
  function injectStyles(config) {
    const primary = config.primary_color || '#2C4A5A';
    const accent = config.accent_color || '#D4A855';
    const theme = THEME || config.embed_theme || 'light';
    const isDark = theme === 'dark';

    const style = document.createElement('style');
    style.textContent = `
      .ck-widget {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: ${isDark ? '#f3f4f6' : '#111827'};
        background: ${isDark ? '#1f2937' : '#ffffff'};
        border: 1px solid ${isDark ? '#374151' : '#e5e7eb'};
        border-radius: 12px;
        padding: 24px;
        max-width: 480px;
        box-sizing: border-box;
      }
      .ck-widget * { box-sizing: border-box; }
      .ck-widget h3 {
        margin: 0 0 4px; font-size: 18px; font-weight: 700;
        color: ${isDark ? '#f9fafb' : '#111827'};
      }
      .ck-widget .ck-sub { margin: 0 0 16px; font-size: 13px; color: ${isDark ? '#9ca3af' : '#6b7280'}; }
      .ck-widget label {
        display: block; font-size: 13px; font-weight: 500; margin-bottom: 4px;
        color: ${isDark ? '#d1d5db' : '#374151'};
      }
      .ck-widget input, .ck-widget select, .ck-widget textarea {
        width: 100%; padding: 8px 12px; font-size: 14px;
        border: 1px solid ${isDark ? '#4b5563' : '#d1d5db'}; border-radius: 8px;
        background: ${isDark ? '#374151' : '#fff'}; color: ${isDark ? '#f3f4f6' : '#111827'};
        margin-bottom: 12px; outline: none; transition: border-color .15s;
      }
      .ck-widget input:focus, .ck-widget select:focus, .ck-widget textarea:focus {
        border-color: ${primary};
      }
      .ck-widget .ck-row { display: flex; gap: 12px; }
      .ck-widget .ck-row > * { flex: 1; }
      .ck-widget .ck-btn {
        display: inline-block; width: 100%; padding: 10px 20px; font-size: 14px;
        font-weight: 600; color: #fff; background: ${primary}; border: none;
        border-radius: 8px; cursor: pointer; text-align: center; transition: opacity .15s;
      }
      .ck-widget .ck-btn:hover { opacity: .9; }
      .ck-widget .ck-btn:disabled { opacity: .5; cursor: not-allowed; }
      .ck-widget .ck-success {
        text-align: center; padding: 24px 0;
      }
      .ck-widget .ck-success .ck-check {
        width: 48px; height: 48px; margin: 0 auto 12px;
        background: ${accent}; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 24px;
      }
      .ck-widget .ck-error { color: #ef4444; font-size: 13px; margin: 8px 0; }
      .ck-widget .ck-powered {
        text-align: center; margin-top: 16px; font-size: 11px;
        color: ${isDark ? '#6b7280' : '#9ca3af'};
      }
      .ck-widget .ck-powered a { color: ${primary}; text-decoration: none; }
      .ck-widget .ck-stars { color: ${accent}; font-size: 20px; letter-spacing: 2px; }
      .ck-widget .ck-testimonial {
        border-bottom: 1px solid ${isDark ? '#374151' : '#f3f4f6'};
        padding: 12px 0;
      }
      .ck-widget .ck-testimonial:last-child { border-bottom: none; }
      .ck-widget .ck-testimonial .ck-name { font-weight: 600; font-size: 14px; }
      .ck-widget .ck-testimonial .ck-text { font-size: 14px; margin: 4px 0 0; line-height: 1.5; }
      .ck-widget .ck-logo { height: 32px; margin-bottom: 12px; }
    `;
    document.head.appendChild(style);
  }

  // ── API Helper ──
  async function api(path, opts = {}) {
    const url = `${API_BASE}/public/${SLUG}${path}`;
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      ...opts,
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.message || json.error || 'Request failed');
    return json.data;
  }

  // ── Booking Widget ──
  function renderBooking(container, config) {
    container.innerHTML = `
      <div class="ck-widget" id="ck-booking">
        ${config.logo_url ? `<img class="ck-logo" src="${config.logo_url}" alt="${config.name}">` : ''}
        <h3>Book an Appointment</h3>
        <p class="ck-sub">Schedule a consultation with ${config.name}</p>
        <form id="ck-booking-form">
          <div class="ck-row">
            <div><label>First Name *</label><input name="patient_first_name" required></div>
            <div><label>Last Name *</label><input name="patient_last_name" required></div>
          </div>
          <label>Email *</label><input name="patient_email" type="email" required>
          <label>Phone</label><input name="patient_phone" type="tel">
          <div class="ck-row">
            <div><label>Date *</label><input name="date" type="date" required min="${new Date().toISOString().split('T')[0]}"></div>
            <div><label>Time *</label><input name="time" type="time" required></div>
          </div>
          <label>Service Type</label>
          <select name="service_type">
            <option value="">Select...</option>
            <option value="Initial Consultation">Initial Consultation</option>
            <option value="Follow-up">Follow-up</option>
            <option value="Therapy Session">Therapy Session</option>
            <option value="Medication Management">Medication Management</option>
            <option value="Other">Other</option>
          </select>
          <label>Insurance</label><input name="insurance" placeholder="Payer / Member ID">
          <label>Reason for Visit</label><textarea name="reason" rows="2" maxlength="500"></textarea>
          <div class="ck-error" id="ck-booking-error" style="display:none"></div>
          <button type="submit" class="ck-btn">Book Appointment</button>
        </form>
        <div class="ck-powered">Powered by <a href="https://credentik.com" target="_blank" rel="noopener">Credentik</a></div>
      </div>
    `;

    const form = document.getElementById('ck-booking-form');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('.ck-btn');
      const err = document.getElementById('ck-booking-error');
      btn.disabled = true;
      btn.textContent = 'Booking...';
      err.style.display = 'none';

      try {
        const fd = new FormData(form);
        const body = Object.fromEntries(fd.entries());
        const result = await api('/book', { method: 'POST', body: JSON.stringify(body) });

        document.getElementById('ck-booking').innerHTML = `
          <div class="ck-success">
            <div class="ck-check">✓</div>
            <h3>Appointment Booked!</h3>
            <p class="ck-sub">Confirmation code: <strong>${result.confirmation_code}</strong></p>
            <p class="ck-sub">We've sent a confirmation email with all the details.</p>
          </div>
          <div class="ck-powered">Powered by <a href="https://credentik.com" target="_blank" rel="noopener">Credentik</a></div>
        `;
      } catch (error) {
        err.textContent = error.message;
        err.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Book Appointment';
      }
    });
  }

  // ── Testimonials Widget ──
  async function renderTestimonials(container, config) {
    container.innerHTML = `
      <div class="ck-widget" id="ck-testimonials">
        ${config.logo_url ? `<img class="ck-logo" src="${config.logo_url}" alt="${config.name}">` : ''}
        <h3>What Our Patients Say</h3>
        <p class="ck-sub">Reviews for ${config.name}</p>
        <div id="ck-testimonials-list"><p class="ck-sub">Loading reviews...</p></div>
        <div class="ck-powered">Powered by <a href="https://credentik.com" target="_blank" rel="noopener">Credentik</a></div>
      </div>
    `;

    try {
      const testimonials = await api('/testimonials');
      const list = document.getElementById('ck-testimonials-list');

      if (!testimonials.length) {
        list.innerHTML = '<p class="ck-sub">No reviews yet.</p>';
        return;
      }

      list.innerHTML = testimonials.map(t => `
        <div class="ck-testimonial">
          <div class="ck-stars">${'★'.repeat(t.rating)}${'☆'.repeat(5 - t.rating)}</div>
          <div class="ck-name">${escHtml(t.display_name)}</div>
          <div class="ck-text">${escHtml(t.text)}</div>
        </div>
      `).join('');
    } catch (error) {
      document.getElementById('ck-testimonials-list').innerHTML =
        `<p class="ck-error">${error.message}</p>`;
    }
  }

  // ── Eligibility Widget ──
  function renderEligibility(container, config) {
    container.innerHTML = `
      <div class="ck-widget" id="ck-eligibility">
        ${config.logo_url ? `<img class="ck-logo" src="${config.logo_url}" alt="${config.name}">` : ''}
        <h3>Check Your Insurance</h3>
        <p class="ck-sub">Verify coverage with ${config.name}</p>
        <form id="ck-elig-form">
          <div class="ck-row">
            <div><label>First Name *</label><input name="patient_first_name" required></div>
            <div><label>Last Name *</label><input name="patient_last_name" required></div>
          </div>
          <label>Date of Birth *</label><input name="patient_dob" type="date" required>
          <label>Insurance Payer *</label><input name="insurance_payer" required placeholder="e.g. Aetna, BlueCross">
          <label>Member ID *</label><input name="member_id" required>
          <div class="ck-error" id="ck-elig-error" style="display:none"></div>
          <button type="submit" class="ck-btn">Check Eligibility</button>
        </form>
        <div class="ck-powered">Powered by <a href="https://credentik.com" target="_blank" rel="noopener">Credentik</a></div>
      </div>
    `;

    const form = document.getElementById('ck-elig-form');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('.ck-btn');
      const err = document.getElementById('ck-elig-error');
      btn.disabled = true;
      btn.textContent = 'Checking...';
      err.style.display = 'none';

      try {
        const fd = new FormData(form);
        const body = Object.fromEntries(fd.entries());
        const result = await api('/eligibility', { method: 'POST', body: JSON.stringify(body) });

        const active = result.is_active;
        document.getElementById('ck-eligibility').innerHTML = `
          <div class="ck-success">
            <div class="ck-check" style="background:${active ? config.accent_color || '#D4A855' : '#ef4444'}">${active ? '✓' : '✕'}</div>
            <h3>${active ? 'Coverage Verified!' : 'Coverage Not Found'}</h3>
            <p class="ck-sub">${active
              ? 'Your insurance appears to be active. Please contact us to schedule.'
              : 'We could not verify active coverage. Please contact us for assistance.'
            }</p>
          </div>
          <div class="ck-powered">Powered by <a href="https://credentik.com" target="_blank" rel="noopener">Credentik</a></div>
        `;
      } catch (error) {
        err.textContent = error.message;
        err.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Check Eligibility';
      }
    });
  }

  // ── Utility ──
  function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
  }

  // ── Init ──
  async function init() {
    const container = document.getElementById(CONTAINER_ID);
    if (!container) {
      console.error(`[Credentik] Container #${CONTAINER_ID} not found`);
      return;
    }

    try {
      const config = await api('/embed-config');
      injectStyles(config);

      switch (WIDGET) {
        case 'booking':
          renderBooking(container, config);
          break;
        case 'testimonials':
          await renderTestimonials(container, config);
          break;
        case 'eligibility':
          renderEligibility(container, config);
          break;
        default:
          console.error(`[Credentik] Unknown widget type: ${WIDGET}`);
      }
    } catch (error) {
      container.innerHTML = `<p style="color:#ef4444;font-size:14px;">Failed to load widget: ${error.message}</p>`;
    }
  }

  // Run when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
