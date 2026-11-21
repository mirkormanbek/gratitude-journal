/* Growth OS — Projects: clean list + project page (outcome → next step → steps → notes).
   API: projects.php (list, get, add_task, add, update, status, delete), tasks.php (toggle, update, move, delete).
   Public: window.Projects.{renderList(el, list), open(id), edit(project|null)} */
(function () {
  'use strict';
  const U = window.UI, esc = U.esc;
  const P = (a, d) => U.post('projects.php?action=' + a, d);
  const T = (a, d) => U.post('tasks.php?action=' + a, d);
  const STATUS = { active: 'Активный', paused: 'Пауза', done: 'Завершён' };

  // index.php keeps the global lists; refresh them after every change
  async function refreshGlobal() { try { if (typeof loadTasks === 'function') await loadTasks(); } catch (e) { /* ignore */ } }

  function dueText(d, done) {
    if (!d) return '';
    const td = U.today();
    if (!done && d < td) return `<span class="bad">просрочено · ${U.date(d)}</span>`;
    return U.relDate(d);
  }
  function deadlineText(p) {
    if (!p.deadline) return '';
    const days = Math.round((new Date(p.deadline + 'T12:00:00') - new Date(U.today() + 'T12:00:00')) / 864e5);
    if (p.status === 'done') return `до ${U.date(p.deadline)}`;
    if (days < 0) return `<span class="bad">дедлайн прошёл ${U.date(p.deadline)}</span>`;
    if (days === 0) return '<span class="bad">дедлайн сегодня</span>';
    return `до ${U.date(p.deadline)} · ${days} ${U.plural(days, 'день', 'дня', 'дней')}`;
  }

  /* ---------- List (Plan → Проекты) ---------- */
  function renderList(el, list) {
    if (!el) return;
    if (!list.length) { el.innerHTML = '<div class="ui-empty">Здесь пока нет проектов.<br>Проект — это результат, который нельзя закрыть одной галочкой.</div>'; return; }
    el.innerHTML = list.map(p => {
      const total = Number(p.total_tasks || 0), done = Number(p.done_tasks || 0), pct = Number(p.progress_percent || 0), next = p.next_task;
      const meta = [total ? `${done} из ${total} шагов` : 'нет шагов'];
      if (Number(p.overdue_tasks || 0)) meta.push(`<span class="bad">${p.overdue_tasks} просрочено</span>`);
      const dl = deadlineText(p); if (dl) meta.push(dl);
      return `<div class="pj-card" data-pj-open="${p.id}">
        <div class="pj-top"><div class="grow"><div class="pj-name">${esc(p.name)}</div>${p.outcome ? `<div class="pj-out">${esc(p.outcome)}</div>` : ''}</div>${p.status !== 'active' ? `<span class="ui-chip">${STATUS[p.status]}</span>` : ''}</div>
        <div class="pj-bar ${pct >= 100 ? 'full' : ''}"><i style="width:${Math.max(total ? 3 : 0, Math.min(100, pct))}%"></i></div>
        <div class="pj-meta">${meta.join('<span>·</span>')}</div>
        ${p.status === 'done' ? '' : next ? `<div class="pj-next"><button class="pj-check" data-pj-done="${next.id}" aria-label="Выполнено">✓</button><div class="grow"><div class="lbl">Следующий шаг</div><div class="tt">${esc(next.title)}</div></div></div>`
          : `<div class="pj-next"><div class="grow pj-empty-next">+ Добавь первый конкретный шаг</div></div>`}
      </div>`;
    }).join('');
  }
  document.addEventListener('click', async e => {
    const doneBtn = e.target.closest('[data-pj-done]');
    if (doneBtn && doneBtn.closest('#projectList')) {
      e.stopPropagation();
      doneBtn.classList.add('on'); U.vibrate(15);
      try { await T('toggle', { id: Number(doneBtn.dataset.pjDone), done: true }); U.toast('Шаг выполнен ✓'); await refreshGlobal(); } catch (err) { doneBtn.classList.remove('on'); U.toast('Ошибка: ' + err.message); }
      return;
    }
    const card = e.target.closest('[data-pj-open]');
    if (card && card.closest('#projectList')) open(Number(card.dataset.pjOpen));
  });

  /* ---------- Project page ---------- */
  let page = null;
  async function open(id) {
    let data;
    try { data = await P('get', { id, date: U.today() }); } catch (e) { return U.toast('Ошибка: ' + e.message); }
    if (page && page.el.isConnected) page.close();
    page = U.sheet({ full: true, title: esc(data.project.name), body: '<div id="pjBody"></div>', onClose: () => { page = null; } });
    const head = page.q('.ui-sheet-head'), more = document.createElement('button');
    more.className = 'ui-more'; more.textContent = '⋯'; more.setAttribute('aria-label', 'Действия с проектом');
    head.insertBefore(more, head.querySelector('.ui-x'));
    more.onclick = () => projectMenu(data.project);
    page.data = data;
    await renderPage();
  }
  async function reloadPage() {
    if (!page) return;
    try { page.data = await P('get', { id: page.data.project.id, date: U.today() }); page.setTitle(esc(page.data.project.name)); await renderPage(); } catch (e) { U.toast('Ошибка: ' + e.message); }
  }
  async function renderPage() {
    const { project: p, tasks } = page.data;
    const open_ = tasks.filter(t => t.status !== 'done'), done = tasks.filter(t => t.status === 'done');
    const notes = window.Notes ? await Notes.forProject(p.id) : [];
    const pct = Number(p.progress_percent || 0);
    const taskRow = t => `<div class="pj-task ${t.status === 'done' ? 'done' : ''} ${Number(t.id) === Number(p.next_task_id) ? 'next' : ''}">
      <button class="pj-check ${t.status === 'done' ? 'on' : ''}" data-t-toggle="${t.id}" aria-label="Отметить">✓</button>
      <div class="grow" data-t-edit="${t.id}"><div class="t">${esc(t.title)}</div><div class="s">${Number(t.id) === Number(p.next_task_id) ? '<b style="color:var(--blue2)">следующий шаг</b> · ' : ''}${t.status === 'done' ? 'выполнено ' + (t.completed_at ? U.date(String(t.completed_at).slice(0, 10)) : '') : dueText(t.due_date, false)}${Number(t.priority) === 1 ? ' · главное' : ''}</div></div></div>`;
    page.q('#pjBody').innerHTML = `
      <div class="pj-hero">
        <div class="pj-meta">${p.status !== 'active' ? `<span class="ui-chip">${STATUS[p.status]}</span>` : ''}<span>${p.done_tasks} из ${p.total_tasks} шагов · ${pct}%</span>${deadlineText(p) ? `<span>· ${deadlineText(p)}</span>` : ''}</div>
        <div class="pj-bar ${pct >= 100 ? 'full' : ''}"><i style="width:${Math.max(p.total_tasks ? 3 : 0, pct)}%"></i></div>
        ${p.outcome ? `<div class="pj-outcome"><b>Результат</b>${esc(p.outcome)}</div>` : `<div class="pj-outcome" data-act="edit" style="cursor:pointer"><b>Результат</b><span style="color:var(--muted)">Опиши, что считается успехом — нажми, чтобы добавить</span></div>`}
      </div>
      <div class="ui-sec"><b>Шаги</b></div>
      <div class="ui-list">${open_.map(taskRow).join('') || '<div class="ui-empty">Нет открытых шагов. Что можно сделать дальше?</div>'}
        <div class="pj-add"><input id="pjNew" maxlength="500" placeholder="Следующий конкретный шаг…" enterkeyhint="done"><select id="pjNewDate" aria-label="Когда"><option value="${U.today()}">Сегодня</option><option value="${U.addDays(U.today(), 1)}">Завтра</option><option value="${U.addDays(U.today(), 7 - ((new Date().getDay() + 6) % 7))}">След. неделя</option></select><button id="pjAdd" aria-label="Добавить">+</button></div></div>
      ${done.length ? `<details style="margin-top:10px"><summary class="ui-meta" style="cursor:pointer;padding:6px 2px">Выполнено · ${done.length}</summary><div class="ui-list" style="margin-top:6px">${done.map(taskRow).join('')}</div></details>` : ''}
      <div class="ui-sec"><b>Заметки проекта</b><button data-act="note-add">+ Заметка</button></div>
      ${notes.length ? notes.map(n => Notes.card(n)).join('') : '<div class="ui-card"><div class="ui-empty" style="padding:10px">Идеи, контакты, договорённости по проекту — всё сюда.</div></div>'}`;
    const inp = page.q('#pjNew'), add = async () => {
      const title = inp.value.trim(); if (!title) return inp.focus();
      try { await P('add_task', { project_id: p.id, title, due_date: page.q('#pjNewDate').value }); inp.value = ''; await reloadPage(); refreshGlobal(); page.q('#pjNew').focus(); } catch (e) { U.toast('Ошибка: ' + e.message); }
    };
    page.q('#pjAdd').onclick = add;
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); add(); } });
    page.body.onclick = onPageClick;
  }
  async function onPageClick(e) {
    const p = page.data.project;
    const tg = e.target.closest('[data-t-toggle]');
    if (tg) {
      const t = page.data.tasks.find(x => Number(x.id) === Number(tg.dataset.tToggle)); const done = t.status !== 'done';
      tg.classList.toggle('on', done); if (done) U.vibrate(15);
      try { await T('toggle', { id: t.id, done }); await reloadPage(); refreshGlobal(); } catch (err) { U.toast('Ошибка: ' + err.message); }
      return;
    }
    const ed = e.target.closest('[data-t-edit]');
    if (ed) return taskMenu(page.data.tasks.find(x => Number(x.id) === Number(ed.dataset.tEdit)));
    const nc = e.target.closest('[data-act="open"]');
    if (nc && window.Notes) { const n = (await Notes.forProject(p.id)).find(x => Number(x.id) === Number(nc.dataset.id)); if (n) Notes.open(n, { onSaved: reloadPage }); return; }
    const a = e.target.closest('[data-act]');
    if (a && a.dataset.act === 'note-add' && window.Notes) return Notes.open({ project_id: p.id }, { onSaved: reloadPage });
    if (a && a.dataset.act === 'edit') return edit(p);
  }
  async function taskMenu(t) {
    if (!t) return;
    const items = [{ label: 'Изменить', value: 'edit' }];
    if (t.status !== 'done') { if (t.due_date !== U.today()) items.push({ label: 'Перенести на сегодня', value: 'today' }); items.push({ label: 'Перенести на завтра', value: 'tomorrow' }); }
    items.push({ label: 'Удалить шаг', value: 'delete', danger: true });
    const v = await U.actions(items, t.title);
    try {
      if (v === 'today' || v === 'tomorrow') { await T('move', { id: t.id, due_date: v === 'today' ? U.today() : U.addDays(U.today(), 1) }); U.toast('Перенесено'); }
      if (v === 'delete') { if (!(await U.confirm('Удалить этот шаг?'))) return; await T('delete', { id: t.id }); }
      if (v === 'edit') return taskEdit(t);
      if (v) { await reloadPage(); refreshGlobal(); }
    } catch (e) { U.toast('Ошибка: ' + e.message); }
  }
  function taskEdit(t) {
    const sh = U.sheet({ title: 'Шаг проекта', body: `<div class="ui-field"><label>Что сделать</label><input id="teT" maxlength="500" value="${esc(t.title)}"></div>
      <div class="ui-two"><div class="ui-field"><label>Когда</label><input id="teD" type="date" value="${esc(t.due_date)}"></div>
      <div class="ui-field"><label>Приоритет</label><select id="teP"><option value="1" ${t.priority === 1 ? 'selected' : ''}>Главное</option><option value="2" ${t.priority === 2 ? 'selected' : ''}>Важно</option><option value="3" ${t.priority === 3 ? 'selected' : ''}>Обычное</option></select></div></div><div class="ui-err" id="teE"></div>`,
      foot: '<button class="ui-btn primary" id="teS">Сохранить</button>' });
    sh.q('#teS').onclick = async () => {
      try { await T('update', { id: t.id, title: sh.q('#teT').value, due_date: sh.q('#teD').value, priority: Number(sh.q('#teP').value) }); sh.close(); await reloadPage(); refreshGlobal(); }
      catch (e) { sh.q('#teE').textContent = e.message; }
    };
  }
  async function projectMenu(p) {
    const items = [{ label: 'Изменить название, результат, дедлайн', value: 'edit' }];
    if (p.status === 'active') items.push({ label: 'Поставить на паузу', value: 'paused' });
    if (p.status !== 'active') items.push({ label: p.status === 'done' ? 'Вернуть в активные' : 'Возобновить', value: 'active' });
    if (p.status !== 'done') items.push({ label: 'Завершить проект 🎉', value: 'done' });
    items.push({ label: 'Удалить проект', value: 'delete', danger: true });
    const v = await U.actions(items, p.name);
    try {
      if (v === 'edit') return edit(p);
      if (v === 'paused' || v === 'active' || v === 'done') { await P('status', { id: p.id, status: v }); U.toast(v === 'done' ? 'Проект завершён 🎉' : v === 'paused' ? 'На паузе' : 'Снова в работе'); await reloadPage(); refreshGlobal(); }
      if (v === 'delete') { if (!(await U.confirm('Удалить проект? Шаги останутся в задачах, но без проекта.'))) return; await P('delete', { id: p.id }); page.close(); refreshGlobal(); U.toast('Проект удалён'); }
    } catch (e) { U.toast('Ошибка: ' + e.message); }
  }

  /* ---------- Create / edit ---------- */
  function edit(p) {
    const x = p || { id: 0, name: '', outcome: '', deadline: '' };
    const sh = U.sheet({ title: x.id ? 'Проект' : 'Новый проект', body: `
      <div class="ui-field"><label>Название</label><input id="peN" maxlength="180" value="${esc(x.name)}" placeholder="Например: Запустить оптовый канал"></div>
      <div class="ui-field"><label>Какой результат считаем успехом?</label><textarea id="peO" maxlength="1200" style="min-height:80px" placeholder="Например: 5 активных оптовых клиентов">${esc(x.outcome || '')}</textarea></div>
      <div class="ui-field"><label>Дедлайн <span style="font-weight:400">(необязательно)</span></label><input id="peD" type="date" value="${esc(x.deadline || '')}"></div>
      ${x.id ? '' : '<div class="ui-field"><label>Первый шаг <span style="font-weight:400">(необязательно)</span></label><input id="peF" maxlength="500" placeholder="Самое маленькое действие, с которого начать"></div>'}
      <div class="ui-meta" style="font-size:12px">Если дело нельзя закрыть одной галочкой — это проект. Внутри него — маленькие конкретные шаги.</div><div class="ui-err" id="peE"></div>`,
      foot: `<button class="ui-btn primary" id="peS">${x.id ? 'Сохранить' : 'Создать проект'}</button>` });
    sh.q('#peS').onclick = async () => {
      const data = { id: x.id, name: sh.q('#peN').value, outcome: sh.q('#peO').value, deadline: sh.q('#peD').value };
      try {
        const j = await P(x.id ? 'update' : 'add', data);
        const id = x.id || j.id;
        const first = sh.q('#peF') && sh.q('#peF').value.trim();
        if (!x.id && first) await P('add_task', { project_id: id, title: first, due_date: U.today() });
        sh.close(); await refreshGlobal();
        if (x.id) { if (page) await reloadPage(); } else open(id);
      } catch (e) { sh.q('#peE').textContent = e.message; }
    };
    if (!x.id) setTimeout(() => sh.q('#peN').focus(), 150);
  }

  window.Projects = { renderList, open, edit };
})();
