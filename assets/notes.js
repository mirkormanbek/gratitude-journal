/* Growth OS — Notes service (notes + ideas/knowledge in one place).
   API: notes.php (list, save, pin, archive, delete). Public: window.Notes.{load, open, forProject, quickAdd} */
(function () {
  'use strict';
  const U = window.UI, esc = U.esc;
  const API = (action, data) => U.post('notes.php?action=' + encodeURIComponent(action), data);
  const STATUSES = ['Идея', 'Нужно протестировать', 'Тестируется', 'Сработало', 'Не сработало', 'Стало системой'];
  const STATUS_CHIP = { 'Идея': 'amber', 'Нужно протестировать': '', 'Тестируется': 'blue', 'Сработало': 'green', 'Не сработало': 'red', 'Стало системой': 'green' };
  const st = { notes: [], archived: [], counts: {}, filter: 'all', tag: '', q: '', loaded: false, captureIdea: false };
  let root = null;

  const firstLine = s => String(s || '').split('\n').find(x => x.trim()) || '';
  const noteTitle = n => n.title || firstLine(n.body).slice(0, 140) || 'Без названия';
  const notePreview = n => { const b = String(n.body || ''); return n.title ? b : b.split('\n').slice(1).join('\n').trim(); };
  const tagsOf = n => String(n.tags || '').split(',').map(t => t.trim()).filter(Boolean);
  // `projects` is the global list loaded by index.php (projects.php?action=list)
  function projList() { try { return Array.isArray(projects) ? projects : []; } catch (e) { return []; } }

  function mount() {
    root = document.getElementById('ntRoot');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';
    root.innerHTML = `
      <div class="ui-head"><div><h2>Заметки</h2><p>Мысли, идеи и знания — в одном месте</p></div><button class="ui-btn primary" data-act="new" style="min-height:40px;padding:9px 14px">+ Новая</button></div>
      <div class="nt-capture"><textarea id="ntQuick" rows="2" placeholder="Записать мысль…"></textarea>
        <div class="nt-capture-row"><button class="nt-toggle" id="ntQuickIdea" type="button">💡 Идея</button><div class="grow"></div><button class="ui-btn primary" id="ntQuickSave" style="min-height:38px;padding:8px 16px" disabled>Сохранить</button></div></div>
      <div class="ui-sticky" style="margin-top:12px"><input class="nt-search" id="ntSearch" type="search" placeholder="Поиск по заметкам">
        <div class="ui-seg" id="ntFilter" style="margin-top:10px"><button data-seg="all" class="on">Все</button><button data-seg="note">Заметки</button><button data-seg="idea">Идеи</button><button data-seg="pinned">Закреплённые</button><button data-seg="archive">Архив</button></div>
        <div class="nt-tags" id="ntTags"></div></div>
      <div id="ntList"></div>`;
    const q = root.querySelector('#ntQuick'), save = root.querySelector('#ntQuickSave');
    q.addEventListener('input', () => { save.disabled = !q.value.trim(); q.style.height = 'auto'; q.style.height = Math.min(240, q.scrollHeight) + 'px'; });
    q.addEventListener('keydown', e => { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); quickSave(); } });
    save.onclick = quickSave;
    root.querySelector('#ntQuickIdea').onclick = e => { st.captureIdea = !st.captureIdea; e.currentTarget.classList.toggle('on', st.captureIdea); };
    root.querySelector('#ntSearch').addEventListener('input', e => { st.q = e.target.value.trim().toLowerCase(); renderList(); });
    U.seg(root.querySelector('#ntFilter'), async f => { st.filter = f; if (f === 'archive') await loadArchived(); renderList(); });
    root.addEventListener('click', onClick);
  }

  async function load() {
    mount();
    try {
      const j = await API('list');
      st.notes = j.notes; st.counts = j.counts || {}; st.loaded = true;
      if (st.filter === 'archive') await loadArchived();
      render();
    } catch (e) { if (root) root.querySelector('#ntList').innerHTML = `<div class="ui-empty">Заметки недоступны: ${esc(e.message)}</div>`; }
    return st.notes;
  }
  async function loadArchived() { const j = await API('list', { archived: 1 }); st.archived = j.notes; }
  async function ensure() { if (!st.loaded) await load(); return st.notes; }

  async function quickSave() {
    const q = root.querySelector('#ntQuick'), text = q.value.trim(); if (!text) return;
    const btn = root.querySelector('#ntQuickSave');
    await U.busy(btn, async () => {
      await API('save', { kind: st.captureIdea ? 'idea' : 'note', body: text, date: U.today() });
      q.value = ''; q.style.height = ''; btn.disabled = true;
      st.captureIdea = false; root.querySelector('#ntQuickIdea').classList.remove('on');
      U.toast('Заметка сохранена');
      await load();
    }, '…');
  }

  function render() { renderTags(); renderList(); }
  function renderTags() {
    const counts = {};
    st.notes.forEach(n => tagsOf(n).forEach(t => counts[t] = (counts[t] || 0) + 1));
    const top = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 12).map(x => x[0]);
    if (st.tag && !top.includes(st.tag)) top.unshift(st.tag);
    root.querySelector('#ntTags').innerHTML = top.map(t => `<button data-act="tag" data-tag="${esc(t)}" class="${t === st.tag ? 'on' : ''}">#${esc(t)}</button>`).join('');
  }
  function visible() {
    let list = st.filter === 'archive' ? st.archived : st.notes;
    if (st.filter === 'note' || st.filter === 'idea') list = list.filter(n => n.kind === st.filter);
    if (st.filter === 'pinned') list = list.filter(n => n.pinned);
    if (st.tag) list = list.filter(n => tagsOf(n).includes(st.tag));
    if (st.q) list = list.filter(n => [n.title, n.body, n.why, n.apply, n.result, n.tags, n.source, n.category, n.project_name].join(' ').toLowerCase().includes(st.q));
    return list;
  }
  function card(n) {
    const prev = notePreview(n), chips = [];
    if (n.kind === 'idea') chips.push(`<span class="ui-chip ${STATUS_CHIP[n.status] || 'amber'}">💡 ${esc(n.status || 'Идея')}</span>`);
    if (n.project_name) chips.push(`<span class="ui-chip blue">${esc(n.project_name)}</span>`);
    tagsOf(n).slice(0, 3).forEach(t => chips.push(`<span class="ui-chip">#${esc(t)}</span>`));
    return `<button class="nt-card ${n.pinned ? 'pinned' : ''}" data-act="open" data-id="${n.id}"><div class="h"><b>${esc(noteTitle(n))}</b>${n.pinned ? '<span class="nt-pin">📌</span>' : ''}</div>
      ${prev ? `<div class="p">${esc(prev.slice(0, 400))}</div>` : (n.kind === 'idea' && n.apply ? `<div class="p">→ ${esc(n.apply.slice(0, 300))}</div>` : '')}
      <div class="m">${chips.join('')}<span class="d">${U.relDate(n.date)}</span></div></button>`;
  }
  function renderList() {
    const el = root.querySelector('#ntList'), list = visible();
    if (!list.length) {
      const empty = st.q || st.tag ? 'Ничего не найдено' : st.filter === 'archive' ? 'Архив пуст' : st.filter === 'idea' ? 'Идей пока нет. Отметь «💡 Идея» при записи — и проведи её по пути: идея → тест → система.' : 'Пока пусто. Запиши первую мысль сверху.';
      el.innerHTML = `<div class="ui-empty">${empty}</div>`; return;
    }
    el.innerHTML = `<div class="ui-meta" style="margin:2px 2px 8px;font-size:12px">${list.length} ${U.plural(list.length, 'запись', 'записи', 'записей')}</div>` + list.map(card).join('');
  }

  /* ---------- Editor ---------- */
  function open(note, opts = {}) {
    const n = Object.assign({ id: 0, kind: 'note', title: '', body: '', tags: '', project_id: null, status: 'Идея', why: '', apply: '', result: '', source: '', category: '', pinned: 0, date: U.today() }, note || {});
    let kind = n.kind, status = n.status || 'Идея';
    const projs = projList().filter(p => p.status !== 'done' || Number(p.id) === Number(n.project_id));
    const sh = U.sheet({ title: n.id ? 'Заметка' : 'Новая заметка', body: `
      <div class="ui-seg" id="neKind" style="margin-bottom:14px"><button data-seg="note" class="${kind === 'note' ? 'on' : ''}">📝 Заметка</button><button data-seg="idea" class="${kind === 'idea' ? 'on' : ''}">💡 Идея / знание</button></div>
      <div class="ui-field"><input id="neTitle" maxlength="200" placeholder="Заголовок (необязательно)" value="${esc(n.title || '')}" style="font-weight:650"></div>
      <div class="ui-field"><textarea id="neBody" class="nt-body" placeholder="Текст заметки…">${esc(n.body || '')}</textarea></div>
      ${n.source ? `<div class="nt-src">Источник: ${esc(n.source)}</div>` : ''}
      <div class="nt-idea" id="neIdea" ${kind === 'idea' ? '' : 'hidden'}>
        <div class="ui-field"><label>Путь идеи</label><div class="nt-flow" id="neFlow">${STATUSES.map(s => `<button type="button" data-s="${esc(s)}" class="${s === status ? 'on' : ''}">${esc(s)}</button>`).join('')}</div></div>
        <div class="ui-field"><label>Почему важно</label><textarea id="neWhy" style="min-height:70px">${esc(n.why || '')}</textarea></div>
        <div class="ui-field"><label>Как применить</label><textarea id="neApply" style="min-height:70px">${esc(n.apply || '')}</textarea></div>
        <div class="ui-field"><label>Результат</label><textarea id="neResult" style="min-height:60px" placeholder="Что получилось после применения">${esc(n.result || '')}</textarea></div>
      </div>
      <div class="ui-two"><div class="ui-field"><label>Проект</label><select id="neProject"><option value="0">Без проекта</option>${projs.map(p => `<option value="${p.id}" ${Number(p.id) === Number(n.project_id) ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select></div>
        <div class="ui-field"><label>Теги</label><input id="neTags" placeholder="опт, идеи" value="${esc(n.tags || '')}"></div></div>
      <div class="ui-err" id="neErr"></div>`,
      foot: `${n.id ? '<button class="ui-btn" id="neMore" style="flex:0 0 52px">⋯</button>' : ''}<button class="ui-btn primary" id="neSave">Сохранить</button>` });
    U.seg(sh.q('#neKind'), k => { kind = k; sh.q('#neIdea').hidden = k !== 'idea'; });
    sh.q('#neFlow').addEventListener('click', e => { const b = e.target.closest('[data-s]'); if (!b) return; status = b.dataset.s; sh.qa('#neFlow button').forEach(x => x.classList.toggle('on', x === b)); });
    sh.q('#neSave').onclick = async () => {
      const data = { id: n.id, kind, title: sh.q('#neTitle').value, body: sh.q('#neBody').value, status, why: sh.q('#neWhy').value, apply: sh.q('#neApply').value, result: sh.q('#neResult').value,
        project_id: Number(sh.q('#neProject').value), tags: sh.q('#neTags').value, source: n.source, category: n.category, pinned: n.pinned, date: n.date };
      try { const j = await API('save', data); sh.close(); U.toast('Сохранено'); await load(); if (opts.onSaved) opts.onSaved(j.note); }
      catch (e) { sh.q('#neErr').textContent = e.message; }
    };
    const more = sh.q('#neMore');
    if (more) more.onclick = async () => {
      const v = await U.actions([{ label: n.pinned ? 'Открепить' : 'Закрепить сверху', value: 'pin' }, { label: n.archived ? 'Вернуть из архива' : 'В архив', value: 'archive' }, { label: 'Удалить навсегда', value: 'delete', danger: true }]);
      try {
        if (v === 'pin') { await API('pin', { id: n.id, value: !n.pinned }); sh.close(); U.toast(n.pinned ? 'Откреплено' : 'Закреплено'); }
        if (v === 'archive') { await API('archive', { id: n.id, value: !n.archived }); sh.close(); U.toast(n.archived ? 'Возвращено' : 'Перенесено в архив'); }
        if (v === 'delete') { if (!(await U.confirm('Удалить заметку навсегда? Это нельзя отменить.'))) return; await API('delete', { id: n.id }); sh.close(); U.toast('Удалено'); }
        if (v) { await load(); if (opts.onSaved) opts.onSaved(null); }
      } catch (e) { U.toast('Ошибка: ' + e.message); }
    };
    if (!n.id && !n.body) setTimeout(() => sh.q('#neBody').focus(), 150);
    return sh;
  }

  async function onClick(e) {
    const b = e.target.closest('[data-act]'); if (!b) return;
    if (b.dataset.act === 'new') return open(null);
    if (b.dataset.act === 'tag') { st.tag = st.tag === b.dataset.tag ? '' : b.dataset.tag; return render(); }
    if (b.dataset.act === 'open') {
      const id = Number(b.dataset.id); const n = st.notes.concat(st.archived).find(x => Number(x.id) === id);
      if (n) open(n);
    }
  }

  async function forProject(pid) { await ensure(); return st.notes.filter(n => Number(n.project_id) === Number(pid)); }
  async function quickAdd(data) { const j = await API('save', Object.assign({ date: U.today() }, data)); await load(); return j.note; }

  window.Notes = { load, open, forProject, quickAdd, card, noteTitle };
})();
