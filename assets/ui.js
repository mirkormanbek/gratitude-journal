/* Growth OS UI kit: sheets, action sheets, toast, charts, formatting, API helper.
   Self-contained (no dependency on index.php helpers). */
(function () {
  'use strict';
  const UI = {};

  UI.esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
  UI.today = () => { const d = new Date(), o = d.getTimezoneOffset(); return new Date(d - o * 60000).toISOString().slice(0, 10); };
  UI.addDays = (s, n) => { const d = new Date(s + 'T12:00:00'); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); };
  UI.date = (d, opts) => new Date(d + 'T12:00:00').toLocaleDateString('ru-RU', opts || { day: 'numeric', month: 'short' });
  UI.relDate = d => { const t = UI.today(); if (d === t) return 'сегодня'; if (d === UI.addDays(t, -1)) return 'вчера'; if (d === UI.addDays(t, 1)) return 'завтра'; return UI.date(d); };
  UI.num = (n, max = 1) => Number(n || 0).toLocaleString('ru-RU', { maximumFractionDigits: max });
  UI.kg = n => n == null || n === '' ? '—' : UI.num(n, 2) + ' кг';
  UI.tons = n => Number(n || 0) >= 1000 ? UI.num(n / 1000, 1) + ' т' : UI.num(n, 0) + ' кг';
  UI.plural = (n, one, few, many) => { n = Math.abs(n) % 100; const n1 = n % 10; if (n > 10 && n < 20) return many; if (n1 > 1 && n1 < 5) return few; if (n1 === 1) return one; return many; };

  /** POST JSON to an endpoint with CSRF; throws Error(message) on failure. */
  UI.post = async (url, data) => {
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF || (typeof CSRF !== 'undefined' ? CSRF : '') }, body: JSON.stringify(data || {}) });
    if (r.status === 401) { location = 'login.php'; throw new Error('auth'); }
    let j = {}; try { j = await r.json(); } catch (e) { /* ignore */ }
    if (!r.ok || j.ok === false) throw new Error(j.error || ('HTTP ' + r.status));
    return j;
  };

  /* ---------- Sheets ---------- */
  const stack = [];
  UI.sheet = ({ title = '', body = '', foot = '', full = false, onClose = null, cls = '' } = {}) => {
    const wrap = document.createElement('div');
    wrap.className = 'ui-sheet-wrap';
    wrap.innerHTML = `<div class="ui-sheet ${full ? 'full' : ''} ${cls}" role="dialog" aria-modal="true">
      ${title !== null ? `<div class="ui-sheet-head"><h3>${title}</h3><button class="ui-x" data-ui-close aria-label="Закрыть">×</button></div>` : ''}
      <div class="ui-sheet-body">${body}</div>${foot ? `<div class="ui-sheet-foot">${foot}</div>` : ''}</div>`;
    document.body.appendChild(wrap);
    document.body.classList.add('ui-lock');
    requestAnimationFrame(() => wrap.classList.add('show'));
    const api = {
      el: wrap.querySelector('.ui-sheet'), body: wrap.querySelector('.ui-sheet-body'),
      q: sel => wrap.querySelector(sel), qa: sel => [...wrap.querySelectorAll(sel)],
      setTitle: t => { const h = wrap.querySelector('.ui-sheet-head h3'); if (h) h.innerHTML = t; },
      close: () => {
        if (!wrap.isConnected) return;
        wrap.classList.remove('show');
        setTimeout(() => wrap.remove(), 180);
        const i = stack.indexOf(api); if (i >= 0) stack.splice(i, 1);
        if (!stack.length) document.body.classList.remove('ui-lock');
        if (onClose) onClose();
      }
    };
    wrap.addEventListener('click', e => { if (e.target === wrap || e.target.closest('[data-ui-close]')) api.close(); });
    stack.push(api);
    return api;
  };
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && stack.length) stack[stack.length - 1].close(); });

  /** Action sheet. items: [{label, value, danger}] → Promise<value|null> */
  UI.actions = (items, label = '') => new Promise(resolve => {
    let done = false;
    const s = UI.sheet({
      title: null,
      body: `<div class="ui-actions">${label ? `<div class="lbl">${UI.esc(label)}</div>` : ''}${items.map((it, i) => `<button data-i="${i}" class="${it.danger ? 'danger' : ''}">${UI.esc(it.label)}</button>`).join('')}<button class="cancel" data-ui-close>Отмена</button></div>`,
      onClose: () => { if (!done) resolve(null); }
    });
    s.body.style.padding = '0';
    s.qa('[data-i]').forEach(b => b.onclick = () => { done = true; s.close(); resolve(items[Number(b.dataset.i)].value); });
  });

  UI.confirm = (text, okLabel = 'Удалить') => UI.actions([{ label: okLabel, value: true, danger: true }], text).then(v => v === true);

  /* ---------- Toast ---------- */
  let toastEl = null, toastT = null;
  UI.toast = msg => {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'ui-toast'; document.body.appendChild(toastEl); }
    toastEl.textContent = msg; toastEl.classList.add('show');
    clearTimeout(toastT); toastT = setTimeout(() => toastEl.classList.remove('show'), 2400);
  };

  UI.busy = async (btn, fn, label = '…') => {
    const old = btn ? btn.innerHTML : ''; if (btn) { btn.disabled = true; btn.innerHTML = label; }
    try { return await fn(); } catch (e) { UI.toast('Ошибка: ' + e.message); throw e; } finally { if (btn) { btn.disabled = false; btn.innerHTML = old; } }
  };

  /* ---------- Charts (inline SVG) ---------- */
  UI.spark = (vals, color = '#0a84ff') => {
    const v = vals.filter(x => x != null);
    if (v.length < 2) return '<svg class="ui-spark"></svg>';
    const w = 72, h = 26, min = Math.min(...v), max = Math.max(...v), span = max - min || 1;
    const pts = v.map((y, i) => `${(i / (v.length - 1) * (w - 4) + 2).toFixed(1)},${(h - 3 - (y - min) / span * (h - 6)).toFixed(1)}`).join(' ');
    const last = pts.split(' ').pop().split(',');
    return `<svg class="ui-spark" viewBox="0 0 ${w} ${h}"><polyline points="${pts}" fill="none" stroke="${color}" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round"/><circle cx="${last[0]}" cy="${last[1]}" r="2.4" fill="${color}"/></svg>`;
  };

  /** Bar chart. data: [{label, value}], opts: {target, color, height, fmt} */
  UI.bars = (data, opts = {}) => {
    const W = 340, H = opts.height || 140, pad = { l: 4, r: 4, t: 14, b: 20 };
    const max = Math.max(opts.target || 0, ...data.map(d => d.value), 1);
    const bw = (W - pad.l - pad.r) / data.length;
    const y = v => H - pad.b - v / max * (H - pad.t - pad.b);
    let s = `<svg class="ui-chart" viewBox="0 0 ${W} ${H}" preserveAspectRatio="none">`;
    data.forEach((d, i) => {
      const x = pad.l + i * bw + bw * 0.18, w = bw * 0.64, top = y(d.value), hit = opts.target && d.value >= opts.target;
      s += `<rect x="${x.toFixed(1)}" y="${top.toFixed(1)}" width="${w.toFixed(1)}" height="${Math.max(0, H - pad.b - top).toFixed(1)}" rx="3" fill="${d.value ? (hit ? '#30d158' : (opts.color || '#0a84ff')) : '#2a2a2e'}"/>`;
      if (d.value && opts.showValues !== false) s += `<text x="${(x + w / 2).toFixed(1)}" y="${(top - 3).toFixed(1)}" text-anchor="middle">${opts.fmt ? opts.fmt(d.value) : d.value}</text>`;
      const every = data.length > 8 ? 2 : 1;
      if (d.label && (data.length - 1 - i) % every === 0) s += `<text x="${(x + w / 2).toFixed(1)}" y="${H - 6}" text-anchor="middle">${UI.esc(d.label)}</text>`;
    });
    if (opts.target) s += `<line x1="${pad.l}" x2="${W - pad.r}" y1="${y(opts.target).toFixed(1)}" y2="${y(opts.target).toFixed(1)}" stroke="#ffffff" stroke-opacity=".25" stroke-dasharray="4 4"/>`;
    return s + '</svg>';
  };

  /** Line chart. series: [{label, value, highlight}] */
  UI.line = (data, opts = {}) => {
    const W = 340, H = opts.height || 160, pad = { l: 34, r: 10, t: 12, b: 22 };
    const vals = data.map(d => d.value).filter(v => v != null);
    if (!vals.length) return '<div class="ui-empty">Пока нет данных</div>';
    let min = Math.min(...vals), max = Math.max(...vals); if (min === max) { min -= 1; max += 1; }
    const span = max - min; min -= span * 0.1; max += span * 0.1;
    const x = i => pad.l + (data.length === 1 ? (W - pad.l - pad.r) / 2 : i / (data.length - 1) * (W - pad.l - pad.r));
    const y = v => pad.t + (1 - (v - min) / (max - min)) * (H - pad.t - pad.b);
    let s = `<svg class="ui-chart" viewBox="0 0 ${W} ${H}">`;
    for (let k = 0; k <= 3; k++) { const v = min + (max - min) * k / 3, yy = y(v).toFixed(1); s += `<line x1="${pad.l}" x2="${W - pad.r}" y1="${yy}" y2="${yy}" stroke="#2a2a2e"/><text x="${pad.l - 5}" y="${(+yy + 3).toFixed(1)}" text-anchor="end">${UI.num(v, 0)}</text>`; }
    const pts = data.map((d, i) => d.value == null ? null : `${x(i).toFixed(1)},${y(d.value).toFixed(1)}`).filter(Boolean);
    s += `<polyline points="${pts.join(' ')}" fill="none" stroke="${opts.color || '#0a84ff'}" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>`;
    data.forEach((d, i) => { if (d.value == null) return; s += `<circle cx="${x(i).toFixed(1)}" cy="${y(d.value).toFixed(1)}" r="${d.highlight ? 4.5 : 3}" fill="${d.highlight ? '#ffd60a' : (opts.color || '#0a84ff')}"/>`; });
    const step = Math.max(1, Math.ceil(data.length / 5));
    data.forEach((d, i) => { if (i % step === 0 || i === data.length - 1) s += `<text x="${x(i).toFixed(1)}" y="${H - 6}" text-anchor="middle">${UI.esc(d.label)}</text>`; });
    return s + '</svg>';
  };

  /** Segmented control binding: container with [data-seg] buttons */
  UI.seg = (el, onChange) => {
    el.addEventListener('click', e => {
      const b = e.target.closest('[data-seg]'); if (!b) return;
      el.querySelectorAll('[data-seg]').forEach(x => x.classList.toggle('on', x === b));
      onChange(b.dataset.seg);
    });
  };

  UI.vibrate = p => { try { if (navigator.vibrate) navigator.vibrate(p); } catch (e) { /* ignore */ } };

  window.UI = UI;
})();
