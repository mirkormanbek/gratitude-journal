/* Growth OS — Workouts service v6
   Tabs: Обзор · Календарь · Программа · Прогресс. Gym mode for logging sets.
   API: workouts.php (summary, stats, program, session, start, save_set, delete_set, complete,
        move, delete, save_schedule, exercise_save|archive|restore|move, day_rename). */
(function () {
  'use strict';
  const U = window.UI, esc = U.esc;
  const API = (action, data) => U.post('workouts.php?action=' + encodeURIComponent(action), data);
  const WD = ['', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
  const WD_LONG = ['', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота', 'воскресенье'];
  const st = { tab: 'overview', month: U.today().slice(0, 7), summary: null, stats: null, program: null, progressMode: 'count', loading: null };
  let root = null;

  const weekdayOf = d => { const n = new Date(d + 'T12:00:00').getDay(); return n === 0 ? 7 : n; };
  const mondayOf = d => U.addDays(d, 1 - weekdayOf(d));
  const fmtW = w => w == null || w === '' ? '' : String(Number(w)).replace('.', ',');
  const parseW = v => { v = String(v || '').replace(',', '.').trim(); return v === '' ? null : Number(v); };
  const restLabel = s => s >= 60 ? `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}` : `${s} с`;
  const planText = e => `${Number(e.target_sets || 0)} × ${esc(e.target_reps || '—')}${e.default_weight ? ` · ${fmtW(e.default_weight)} кг` : ''}`;

  /* ================= Shell ================= */
  function mount() {
    root = document.getElementById('wkRoot');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';
    root.innerHTML = `
      <div class="ui-head"><div><h2>Тренировки</h2><p id="wkSub">Программа, подходы и прогресс</p></div></div>
      <div class="ui-sticky"><div class="ui-seg" id="wkTabs">
        <button data-seg="overview" class="on">Обзор</button><button data-seg="calendar">Календарь</button>
        <button data-seg="program">Программа</button><button data-seg="progress">Прогресс</button></div></div>
      <div id="wkView"></div>`;
    U.seg(root.querySelector('#wkTabs'), t => { st.tab = t; render(); if (t === 'program' && !st.program) loadProgram(); });
    root.addEventListener('click', onClick);
  }
  function setTab(t) { st.tab = t; root.querySelectorAll('#wkTabs [data-seg]').forEach(b => b.classList.toggle('on', b.dataset.seg === t)); render(); }

  async function load(month) {
    mount();
    if (month) st.month = month;
    const p = Promise.all([API('summary', { date: U.today(), month: st.month }), API('stats', { date: U.today() })]);
    st.loading = p;
    try {
      const [a, b] = await p;
      st.summary = a.summary; st.stats = b.stats;
      shareSummary();
      render();
    } catch (e) {
      if (root) root.querySelector('#wkView').innerHTML = `<div class="ui-card"><div class="ui-empty">Тренировки недоступны: ${esc(e.message)}</div></div>`;
      setHome('Тренировки недоступны', e.message, 'Открыть');
    }
  }
  async function loadProgram() {
    try { const j = await API('program'); st.program = j; if (st.tab === 'program') render(); } catch (e) { U.toast('Ошибка: ' + e.message); }
  }
  async function refresh() { st.program = null; await load(); if (st.tab === 'program') loadProgram(); }

  /* Share data with Today / Plan / Progress (index.php globals) */
  function shareSummary() {
    const s = st.summary;
    try { window.workoutSummary = s; workoutSummary = s; } catch (e) { /* not declared */ }
    const h = hero();
    setHome(h.homeTitle, h.homeMeta, s.in_progress ? 'Продолжить' : 'Открыть тренировки');
    try { if (typeof renderPlanAgenda === 'function') renderPlanAgenda(); if (typeof renderGrowthProgress === 'function') renderGrowthProgress(); } catch (e) { /* ignore */ }
  }
  function setHome(title, meta, btn) {
    const t = document.getElementById('workoutHomeTitle'), m = document.getElementById('workoutHomeMeta'), b = document.getElementById('workoutHomeBtn');
    if (t) t.textContent = title; if (m) m.textContent = meta; if (b) b.textContent = btn;
  }

  /* ================= Overview ================= */
  function hero() {
    const s = st.summary || {}, inprog = s.in_progress, todayS = s.today_session, overdue = s.overdue_sessions || [], planned = s.planned_session;
    const dayOf = id => (s.days || []).find(d => Number(d.id) === Number(id));
    if (inprog) return { kind: 'prog', eyebrow: 'В процессе', session: inprog, day: dayOf(inprog.day_id), btn: 'Продолжить тренировку', action: 'continue',
      homeTitle: `${inprog.day_name} · продолжить`, homeMeta: 'Тренировка начата — продолжи с того же места' };
    if (s.mode === 'rotation') {
      const nd = s.next_day; const last = (st.stats?.sessions || [])[0];
      const lastTxt = last ? `Последняя: ${last.day_name} · ${U.relDate(last.date)}` : 'Первая тренировка';
      return { kind: 'next', eyebrow: 'Следующая по очереди', session: nd ? { day_name: nd.name, day_id: nd.id } : null, day: nd, btn: 'Начать тренировку', action: 'start_next', lastTxt, rotation: true,
        homeTitle: nd ? `${nd.name} · следующая` : 'Тренировки', homeMeta: lastTxt };
    }
    if (todayS) return { kind: 'today', eyebrow: 'Сегодня', session: todayS, day: dayOf(todayS.day_id), btn: 'Начать тренировку', action: 'start',
      homeTitle: `${todayS.day_name} · сегодня`, homeMeta: 'По расписанию сегодня' };
    if (overdue.length) { const o = overdue[overdue.length - 1]; return { kind: 'miss', eyebrow: `Пропущено · ${U.date(o.scheduled_date)}`, session: o, day: dayOf(o.day_id), btn: 'Сделать сегодня', action: 'today', extra: overdue.length,
      homeTitle: `${o.day_name} · пропущена`, homeMeta: `Можно перенести на сегодня` }; }
    if (planned) return { kind: 'next', eyebrow: `Следующая · ${WD_LONG[weekdayOf(planned.scheduled_date)]}, ${U.date(planned.scheduled_date)}`, session: planned, day: dayOf(planned.day_id), btn: 'Начать сейчас', action: 'start',
      homeTitle: `${planned.day_name} · ${U.date(planned.scheduled_date)}`, homeMeta: `${s.program?.target_per_week || 3} раза в неделю` };
    return { kind: 'none', eyebrow: 'Расписание', session: null, day: null, btn: 'Открыть календарь', action: 'calendar', homeTitle: 'Тренировки', homeMeta: 'Расписание не настроено' };
  }

  function weekStrip() {
    const s = st.summary || {}, td = U.today(), mon = mondayOf(td);
    const byDate = {};
    (s.calendar || []).concat(s.agenda_sessions || []).forEach(x => { byDate[x.scheduled_date] = byDate[x.scheduled_date] || x; });
    (s.overdue_sessions || []).forEach(x => { byDate[x.scheduled_date] = Object.assign({ status: 'planned' }, x); });
    const done = new Set((st.stats?.sessions || []).map(x => x.date));
    let html = '';
    for (let i = 0; i < 7; i++) {
      const d = U.addDays(mon, i), x = byDate[d];
      let cls = '', mark = '';
      if (done.has(d)) { cls = 'done'; mark = '✓'; }
      else if (x && x.status === 'in_progress') { cls = 'prog'; mark = '•'; }
      else if (x && x.status === 'planned') { cls = d < td ? 'miss' : 'plan'; mark = (x.day_name || '').replace(/\D+/g, '') || '•'; }
      html += `<div class="wk-wd ${d === td ? 'today' : ''}"><span>${WD[i + 1]}</span><div class="wk-dot ${cls}">${mark || Number(d.slice(8))}</div></div>`;
    }
    return `<div class="wk-week">${html}</div>`;
  }

  function renderOverview() {
    const h = hero(), t = st.stats?.totals || {}, target = st.stats?.target_per_week || 3;
    const exN = h.day ? (h.day.exercises || []).length : 0;
    const est = h.day ? Math.round((h.day.exercises || []).reduce((a, e) => a + Number(e.target_sets || 3), 0) * 2.6) : 0;
    const weekCount = (st.stats?.weeks || []).slice(-1)[0]?.count || 0;
    const recent = (st.stats?.sessions || []).slice(0, 4);
    return `
      <div class="ui-card ui-hero">
        <div class="ui-eyebrow ${h.kind === 'miss' ? 'warn' : h.kind === 'prog' ? 'ok' : ''}">${esc(h.eyebrow)}</div>
        <div class="ui-title">${esc(h.session ? h.session.day_name : 'Тренировки')}</div>
        <div class="ui-meta">${h.day ? `${exN} ${U.plural(exN, 'упражнение', 'упражнения', 'упражнений')} · ~${est} мин` : 'Настрой расписание в календаре'}${h.extra > 1 ? ` · пропущено ${h.extra}` : ''}${h.lastTxt ? `<br>${esc(h.lastTxt)}` : ''}</div>
        ${weekStrip()}
        ${(st.summary.overdue_sessions || []).length > 1 ? `<div class="ui-meta" style="margin-top:10px;font-size:12px">Пропущенных: ${st.summary.overdue_sessions.length}. <button class="ui-btn ghost" data-act="skip-missed" style="font-size:12px;padding:0">Очистить старые пропуски</button></div>` : ''}
        <div class="wk-hero-actions"><button class="ui-btn primary" data-act="hero">${esc(h.btn)}</button>${h.day ? `<button class="ui-btn" data-act="preview" title="Посмотреть упражнения">Состав</button>` : ''}</div>
        ${h.rotation ? '<button class="ui-btn ghost" data-act="other-day" style="margin-top:8px;font-size:13px">Начать другой день…</button>' : ''}
      </div>
      <div class="ui-sec"><b>Ритм</b></div>
      <div class="ui-tiles">
        <div class="ui-tile ${weekCount >= target ? 'good' : ''}"><span>Эта неделя</span><b>${weekCount}/${target}</b><small>тренировок</small></div>
        <div class="ui-tile"><span>4 недели</span><b>${t.last_28 || 0}/${t.target_28 || target * 4}</b><small>${t.target_28 ? Math.round((t.last_28 || 0) / t.target_28 * 100) : 0}% плана</small></div>
        <div class="ui-tile"><span>Серия</span><b>${t.week_streak || 0}</b><small>${U.plural(t.week_streak || 0, 'неделя', 'недели', 'недель')} по плану</small></div>
      </div>
      <div class="ui-sec"><b>Последние</b>${recent.length ? '<button data-act="tab" data-tab="progress">Вся статистика</button>' : ''}</div>
      ${recent.length ? `<div class="ui-list">${recent.map(sessionRow).join('')}</div>` : '<div class="ui-card"><div class="ui-empty">Здесь появятся завершённые тренировки</div></div>'}`;
  }
  function sessionRow(x) {
    return `<button class="ui-item" data-act="done-session" data-id="${x.id}"><div class="grow"><div class="t">${esc(x.day_name)}${x.prs ? ` <span class="ui-chip amber">★ ${x.prs}</span>` : ''}</div>
      <div class="s">${U.relDate(x.date)} · ${x.sets} ${U.plural(x.sets, 'подход', 'подхода', 'подходов')}${x.minutes ? ` · ${x.minutes} мин` : ''}</div></div>
      <div class="end"><b>${U.tons(x.volume)}</b>объём</div></button>`;
  }

  /* ================= Calendar ================= */
  function renderCalendar() {
    const s = st.summary; if (!s) return '';
    const month = s.calendar_month, td = U.today();
    const by = {}; (s.calendar || []).forEach(x => { (by[x.scheduled_date] = by[x.scheduled_date] || []).push(x); });
    const done = new Set((st.stats?.sessions || []).map(x => x.date));
    let cells = WD.slice(1).map(d => `<div class="wdh">${d}</div>`).join('');
    for (let d = s.calendar_from; d <= s.calendar_to; d = U.addDays(d, 1)) {
      const x = (by[d] || [])[0];
      let cls = 'wk-cell' + (d.slice(0, 7) !== month ? ' out' : '') + (d === td ? ' today' : '');
      if (x) { cls += ' has ' + (x.status === 'done' || done.has(d) ? 'done' : x.status === 'in_progress' ? 'prog' : d < td ? 'miss' : 'plan'); }
      cells += x ? `<button class="${cls}" data-act="cal" data-id="${x.id}"><span>${Number(d.slice(8))}</span><i></i></button>` : `<div class="${cls}"><span>${Number(d.slice(8))}</span></div>`;
    }
    const label = new Date(month + '-01T12:00:00').toLocaleDateString('ru-RU', { month: 'long', year: 'numeric' });
    const sched = (s.schedule || []).map(x => `${WD[x.weekday]} — ${esc(x.day_name)}`).join(' · ') || 'не настроено';
    return `<div class="ui-card">
        <div class="wk-cal-head"><button class="wk-cal-nav" data-act="month" data-d="-1" aria-label="Предыдущий месяц">‹</button><b>${esc(label)}</b><button class="wk-cal-nav" data-act="month" data-d="1" aria-label="Следующий месяц">›</button></div>
        <div class="wk-cal">${cells}</div>
        <div class="wk-legend"><span><i style="background:var(--green)"></i>выполнено</span>${s.mode === 'rotation' ? '' : '<span><i style="background:var(--blue)"></i>план</span><span><i style="background:#ff453a"></i>пропущено</span>'}<span><i style="background:#ff9f0a"></i>в процессе</span></div>
      </div>
      <div class="ui-sec"><b>Как выбирается тренировка</b></div>
      <div class="ui-card"><div class="ui-seg" style="margin-bottom:10px"><button data-act="mode" data-mode="rotation" class="${s.mode === 'rotation' ? 'on' : ''}">По очереди</button><button data-act="mode" data-mode="schedule" class="${s.mode !== 'rotation' ? 'on' : ''}">По дням недели</button></div>
        <div class="ui-meta">${s.mode === 'rotation' ? 'Нажимаешь «Начать» в любой день — запускается следующий день программы после последнего выполненного (День 1 → 2 → 3 → 1). Пропусков и переносов нет.' : `Постоянные дни: ${sched}. Пропущенную тренировку можно перенести. <button class="ui-btn ghost" data-act="schedule" style="font-size:13px;padding:0">Изменить дни</button>`}</div></div>`;
  }

  async function calendarAction(id) {
    const x = (st.summary.calendar || []).find(c => Number(c.id) === Number(id)); if (!x) return;
    const td = U.today(), items = [];
    if (x.status === 'done') items.push({ label: 'Посмотреть результаты', value: 'view' });
    else {
      items.push({ label: x.status === 'in_progress' ? 'Продолжить' : 'Начать', value: 'start' });
      if (x.status === 'planned') { if (x.scheduled_date !== td) items.push({ label: 'Перенести на сегодня', value: 'today' }); items.push({ label: 'Перенести на другую дату…', value: 'move' }); }
    }
    items.push({ label: 'Удалить', value: 'delete', danger: true });
    const v = await U.actions(items, `${x.day_name} · ${U.date(x.scheduled_date, { weekday: 'long', day: 'numeric', month: 'long' })}`);
    if (v === 'view') return openDoneSession(x.id);
    if (v === 'start') return startSession(x);
    if (v === 'today') return moveSession(x.id, td);
    if (v === 'move') return moveSheet(x);
    if (v === 'delete') return deleteSession(x.id, x.status === 'done');
  }
  function moveSheet(x) {
    const td = U.today();
    const sh = U.sheet({ title: 'Перенести тренировку', body: `<div class="ui-meta" style="margin-bottom:12px">${esc(x.day_name)} с ${U.date(x.scheduled_date, { day: 'numeric', month: 'long' })}. Остальные недели не изменятся.</div>
      <div class="ui-row-btns" style="margin:0 0 12px"><button class="ui-btn" data-d="${td}">Сегодня</button><button class="ui-btn" data-d="${U.addDays(td, 1)}">Завтра</button></div>
      <div class="ui-field"><label>Или выбери дату</label><input type="date" id="wkMoveDate" value="${x.scheduled_date}"></div>`,
      foot: '<button class="ui-btn primary" id="wkMoveGo">Перенести</button>' });
    sh.qa('[data-d]').forEach(b => b.onclick = () => { sh.close(); moveSession(x.id, b.dataset.d); });
    sh.q('#wkMoveGo').onclick = () => { const d = sh.q('#wkMoveDate').value; if (!d) return; sh.close(); moveSession(x.id, d); };
  }
  async function moveSession(id, date) {
    try { await API('move', { id: Number(id), date }); U.toast(`Перенесено на ${U.date(date, { day: 'numeric', month: 'long' })}`); await load(); } catch (e) { U.toast('Ошибка: ' + e.message); }
  }
  async function deleteSession(id, isDone) {
    const ok = await U.confirm(isDone ? 'Удалить тренировку и все её подходы? Это нельзя отменить.' : 'Удалить эту тренировку из календаря?');
    if (!ok) return false;
    try { await API('delete', { id: Number(id) }); U.toast('Удалено'); await load(); return true; } catch (e) { U.toast('Ошибка: ' + e.message); return false; }
  }
  function scheduleSheet() {
    const s = st.summary, cur = {}; (s.schedule || []).forEach(x => cur[Number(x.day_id)] = Number(x.weekday));
    const opts = sel => [1, 2, 3, 4, 5, 6, 7].map(w => `<option value="${w}" ${sel === w ? 'selected' : ''}>${WD_LONG[w]}</option>`).join('');
    const sh = U.sheet({ title: 'Расписание недели', body: `<div class="ui-meta" style="margin-bottom:14px">Постоянные дни тренировок. Изменение действует с сегодняшнего дня, прошлые тренировки не меняются.</div>
      ${(s.days || []).map(d => `<div class="ui-field"><label>${esc(d.name)}</label><select data-day="${d.id}">${opts(cur[Number(d.id)] || Number(d.day_order) * 2 - 1)}</select></div>`).join('')}<div class="ui-err" id="wkSchErr"></div>`,
      foot: '<button class="ui-btn primary" id="wkSchSave">Сохранить</button>' });
    sh.q('#wkSchSave').onclick = async () => {
      const items = sh.qa('[data-day]').map(x => ({ day_id: Number(x.dataset.day), weekday: Number(x.value) }));
      try { await API('save_schedule', { items }); sh.close(); U.toast('Расписание обновлено'); await load(); } catch (e) { sh.q('#wkSchErr').textContent = e.message; }
    };
  }

  /* ================= Program editor ================= */
  function renderProgram() {
    const p = st.program;
    if (!p) return '<div class="ui-card"><div class="ui-empty">Загружаю программу…</div></div>';
    const wdByDay = {}; (p.schedule || []).forEach(x => wdByDay[Number(x.day_id)] = Number(x.weekday));
    return `<div class="ui-meta" style="margin:0 2px 12px">Нажми на упражнение, чтобы изменить подходы, повторы, вес или отдых. Убранные упражнения уходят в архив — история и прогресс по ним сохраняются.</div>` +
      p.days.map(d => {
        const act = d.exercises.filter(e => !e.is_archived), arch = d.exercises.filter(e => e.is_archived);
        return `<div class="wk-day"><div class="wk-day-head"><b>${esc(d.name)}</b>${wdByDay[d.id] ? `<span class="ui-chip blue">${WD[wdByDay[d.id]]}</span>` : '<span class="ui-chip">без дня</span>'}<button class="ui-more" data-act="day-menu" data-id="${d.id}" aria-label="Действия с днём">⋯</button></div>
          <div class="ui-list">${act.map((e, i) => exRow(e, i, act.length)).join('') || '<div class="ui-empty">Нет упражнений</div>'}</div>
          <button class="wk-add" data-act="ex-add" data-day="${d.id}">+ Упражнение</button>
          ${arch.length ? `<details style="margin-top:8px"><summary class="ui-meta" style="cursor:pointer;padding:6px 2px">Архив · ${arch.length}</summary><div class="ui-list" style="margin-top:6px">${arch.map(e => exRow(e, -1, 0)).join('')}</div></details>` : ''}
        </div>`;
      }).join('');
  }
  function exRow(e, i, n) {
    const bits = [planText(e)]; if (e.rest_seconds) bits.push('отдых ' + restLabel(e.rest_seconds));
    return `<div class="ui-item wk-ex ${e.is_archived ? 'arch' : ''}" data-act="ex-edit" data-id="${e.id}"><div class="grow"><div class="t">${esc(e.name)}</div><div class="s">${bits.join(' · ')}${e.notes ? ' · ' + esc(e.notes) : ''}</div></div>
      <button class="ui-more" data-act="ex-menu" data-id="${e.id}" data-i="${i}" data-n="${n}" aria-label="Действия">⋯</button></div>`;
  }
  const findEx = id => { for (const d of st.program.days) for (const e of d.exercises) if (Number(e.id) === Number(id)) return { e, d }; return null; };

  function exerciseSheet(dayId, ex) {
    const e = ex || { name: '', target_sets: 3, target_reps: '10', default_weight: '', rest_seconds: '', notes: '' };
    const sh = U.sheet({ title: ex ? 'Упражнение' : 'Новое упражнение', body: `
      <div class="ui-field"><label>Название</label><input id="exName" maxlength="220" value="${esc(e.name)}" placeholder="Например: Жим лёжа"></div>
      <div class="ui-three"><div class="ui-field"><label>Подходы</label><input id="exSets" type="number" inputmode="numeric" min="1" max="12" value="${esc(e.target_sets)}"></div>
        <div class="ui-field"><label>Повторы</label><input id="exReps" maxlength="60" value="${esc(e.target_reps || '')}" placeholder="8–10"></div>
        <div class="ui-field"><label>Вес, кг</label><input id="exW" inputmode="decimal" value="${esc(fmtW(e.default_weight))}" placeholder="—"></div></div>
      <div class="ui-field"><label>Отдых между подходами</label><select id="exRest">${[['', 'Стандарт (1:30)'], [60, '1:00'], [90, '1:30'], [120, '2:00'], [150, '2:30'], [180, '3:00'], [240, '4:00']].map(([v, l]) => `<option value="${v}" ${String(e.rest_seconds || '') === String(v) ? 'selected' : ''}>${l}</option>`).join('')}</select></div>
      <div class="ui-field"><label>Заметка <span style="font-weight:400">(техника, разминка)</span></label><input id="exNotes" maxlength="500" value="${esc(e.notes || '')}"></div>
      ${ex && ex.sets_logged ? `<div class="ui-meta">Записано подходов: ${ex.sets_logged}. История сохранится. Прогресс считается по названию: если переименовать, старые записи останутся под прежним названием.</div>` : ''}
      <div class="ui-err" id="exErr"></div>`,
      foot: `${ex ? `<button class="ui-btn danger" id="exArch">${ex.is_archived ? 'Вернуть' : 'В архив'}</button>` : ''}<button class="ui-btn primary" id="exSave">Сохранить</button>` });
    sh.q('#exSave').onclick = async () => {
      const data = { id: ex ? ex.id : 0, day_id: dayId, name: sh.q('#exName').value, target_sets: Number(sh.q('#exSets').value), target_reps: sh.q('#exReps').value, default_weight: sh.q('#exW').value, rest_seconds: sh.q('#exRest').value, notes: sh.q('#exNotes').value };
      try { await API('exercise_save', data); sh.close(); U.toast('Сохранено'); await refresh(); } catch (err) { sh.q('#exErr').textContent = err.message; }
    };
    const a = sh.q('#exArch'); if (a) a.onclick = async () => { sh.close(); await archiveEx(ex); };
    if (!ex) setTimeout(() => sh.q('#exName').focus(), 150);
  }
  async function archiveEx(e) {
    try { await API(e.is_archived ? 'exercise_restore' : 'exercise_archive', { id: e.id }); U.toast(e.is_archived ? 'Упражнение возвращено' : 'Упражнение в архиве'); await refresh(); } catch (err) { U.toast('Ошибка: ' + err.message); }
  }
  async function exMenu(id, i, n) {
    const f = findEx(id); if (!f) return;
    const items = [{ label: 'Изменить', value: 'edit' }];
    if (!f.e.is_archived) { if (i > 0) items.push({ label: 'Поднять выше', value: 'up' }); if (i < n - 1) items.push({ label: 'Опустить ниже', value: 'down' }); }
    items.push({ label: f.e.is_archived ? 'Вернуть из архива' : 'Убрать в архив', value: 'arch', danger: !f.e.is_archived });
    const v = await U.actions(items, f.e.name);
    if (v === 'edit') exerciseSheet(f.d.id, f.e);
    if (v === 'up' || v === 'down') { try { await API('exercise_move', { id, dir: v === 'up' ? -1 : 1 }); await refresh(); } catch (e) { U.toast('Ошибка: ' + e.message); } }
    if (v === 'arch') archiveEx(f.e);
  }
  async function dayMenu(id) {
    const d = st.program.days.find(x => Number(x.id) === Number(id)); if (!d) return;
    const v = await U.actions([{ label: 'Переименовать', value: 'rename' }, { label: 'Изменить расписание недели', value: 'schedule' }], d.name);
    if (v === 'schedule') return scheduleSheet();
    if (v !== 'rename') return;
    const sh = U.sheet({ title: 'Название дня', body: `<div class="ui-field"><label>Например: «Верх А» или «Ноги»</label><input id="dName" maxlength="120" value="${esc(d.name)}"></div><div class="ui-err" id="dErr"></div>`, foot: '<button class="ui-btn primary" id="dSave">Сохранить</button>' });
    sh.q('#dSave').onclick = async () => { try { await API('day_rename', { id, name: sh.q('#dName').value }); sh.close(); await refresh(); } catch (e) { sh.q('#dErr').textContent = e.message; } };
  }

  /* ================= Progress ================= */
  function renderProgress() {
    const s = st.stats; if (!s) return '';
    const t = s.totals, weeks = s.weeks || [];
    const data = weeks.map(w => ({ label: Number(w.week.slice(8)) + '.' + w.week.slice(5, 7), value: st.progressMode === 'count' ? w.count : Math.round(w.volume) }));
    const ex = s.exercises || [];
    return `<div class="ui-tiles four">
        <div class="ui-tile"><span>Тренировок</span><b>${t.workouts}</b><small>за всё время</small></div>
        <div class="ui-tile"><span>Подходов</span><b>${t.sets}</b><small>${t.avg_minutes ? `~${t.avg_minutes} мин/трен.` : '&nbsp;'}</small></div>
        <div class="ui-tile"><span>Объём</span><b>${U.tons(t.volume)}</b><small>вес × повторы</small></div>
        <div class="ui-tile ${t.prs ? 'good' : ''}"><span>Рекорды</span><b>${t.prs}</b><small>улучшений</small></div></div>
      <div class="ui-sec"><b>По неделям</b></div>
      <div class="ui-card"><div class="ui-seg" id="wkPm" style="margin-bottom:12px"><button data-seg="count" class="${st.progressMode === 'count' ? 'on' : ''}">Тренировки</button><button data-seg="volume" class="${st.progressMode === 'volume' ? 'on' : ''}">Объём, кг</button></div>
        ${U.bars(data, st.progressMode === 'count' ? { target: s.target_per_week } : { fmt: v => v >= 1000 ? Math.round(v / 100) / 10 + 'т' : v })}
        <div class="ui-meta" style="font-size:12px;margin-top:6px">${st.progressMode === 'count' ? `Пунктир — цель ${s.target_per_week} в неделю, зелёные недели — цель выполнена.` : 'Суммарный тоннаж за неделю.'}</div></div>
      <div class="ui-sec"><b>Упражнения</b></div>
      ${ex.length ? `<div class="ui-list">${ex.map(e => {
        const vals = e.points.map(p => e.bodyweight ? p.top_reps : (p.e1rm || p.top_weight));
        const main = e.bodyweight ? `${e.best_reps} повт.` : UI.kg(e.best_weight);
        const last = e.bodyweight ? `последний: ${e.last_top_reps} повт.` : `последний: ${fmtW(e.last_top_weight)}×${e.last_top_reps}`;
        return `<button class="ui-item wk-ex-row" data-act="ex-stats" data-key="${esc(e.key)}"><div class="grow"><div class="t">${esc(e.name)}${e.last_pr ? ' <span class="ui-chip amber">★ рекорд</span>' : ''}</div><div class="s">${last} · ${U.relDate(e.last_date)}</div></div>
          ${U.spark(vals, e.last_pr ? '#ffd60a' : '#0a84ff')}<div class="end"><b>${main}</b>лучший</div></button>`;
      }).join('')}</div>` : '<div class="ui-card"><div class="ui-empty">Прогресс появится после первой завершённой тренировки</div></div>'}`;
  }
  function exerciseStatsSheet(key) {
    const e = (st.stats.exercises || []).find(x => x.key === key); if (!e) return;
    let mode = e.bodyweight ? 'reps' : 'e1rm';
    const sh = U.sheet({ title: esc(e.name), body: '' });
    const draw = () => {
      const pts = e.points;
      const series = pts.map(p => ({ label: U.date(p.date, { day: 'numeric', month: 'numeric' }), value: mode === 'reps' ? p.top_reps : mode === 'top' ? p.top_weight : p.e1rm || null, highlight: p.pr }));
      sh.body.innerHTML = `<div class="ui-tiles" style="margin-bottom:12px">
          <div class="ui-tile"><span>Лучший вес</span><b>${e.bodyweight ? '—' : fmtW(e.best_weight)}</b><small>${e.bodyweight ? 'свой вес' : 'кг'}</small></div>
          <div class="ui-tile"><span>Оценка 1ПМ</span><b>${e.best_e1rm && !e.bodyweight ? U.num(e.best_e1rm, 1) : '—'}</b><small>по формуле Эпли</small></div>
          <div class="ui-tile"><span>Тренировок</span><b>${e.sessions}</b><small>${e.change_e1rm != null && !e.bodyweight ? (e.change_e1rm >= 0 ? '+' : '') + U.num(e.change_e1rm, 1) + ' кг к 1ПМ' : '&nbsp;'}</small></div></div>
        ${e.bodyweight ? '' : `<div class="ui-seg" id="exMode" style="margin-bottom:10px"><button data-seg="e1rm" class="${mode === 'e1rm' ? 'on' : ''}">Сила (1ПМ)</button><button data-seg="top" class="${mode === 'top' ? 'on' : ''}">Рабочий вес</button><button data-seg="reps" class="${mode === 'reps' ? 'on' : ''}">Повторы</button></div>`}
        <div class="ui-card" style="padding:10px">${U.line(series)}</div>
        <div class="ui-meta" style="font-size:12px;margin:6px 2px 14px">★ жёлтые точки — личные рекорды. 1ПМ — расчётный максимум на 1 повтор: позволяет сравнивать подходы с разным весом и повторами.</div>
        <table class="wk-set-table">${pts.slice().reverse().map(p => `<tr><td>${U.date(p.date)}</td><td><div class="wk-sets-inline">${p.sets.map(x => `<span>${x.w != null ? fmtW(x.w) + '×' : ''}${x.r}</span>`).join('')}</div></td><td>${p.pr ? '<span class="wk-pr">★</span> ' : ''}${p.volume ? U.tons(p.volume) : ''}</td></tr>`).join('')}</table>`;
      const m = sh.q('#exMode'); if (m) U.seg(m, v => { mode = v; draw(); });
    };
    draw();
  }

  async function openDoneSession(id) {
    let j; try { j = await API('session', { id: Number(id) }); } catch (e) { return U.toast('Ошибка: ' + e.message); }
    const s = j.session, ex = s.exercises.filter(e => (e.sets || []).some(x => x.completed_at));
    let vol = 0, sets = 0; ex.forEach(e => e.sets.forEach(x => { if (x.completed_at) { sets++; vol += Number(x.weight_kg || 0) * Number(x.reps || 0); } }));
    const sh = U.sheet({ title: esc(s.day_name), body: `<div class="ui-meta" style="margin-bottom:12px">${U.date(s.scheduled_date, { weekday: 'long', day: 'numeric', month: 'long' })} · ${sets} ${U.plural(sets, 'подход', 'подхода', 'подходов')} · ${U.tons(vol)}</div>
      <table class="wk-set-table">${ex.map(e => `<tr><td style="width:auto;white-space:normal;color:#fff">${esc(e.name)}</td><td><div class="wk-sets-inline">${e.sets.filter(x => x.completed_at).map(x => `<span>${x.weight_kg != null ? fmtW(x.weight_kg) + '×' : ''}${x.reps}</span>`).join('')}</div></td><td></td></tr>`).join('') || '<tr><td>Подходов не записано</td></tr>'}</table>`,
      foot: '<button class="ui-btn danger" id="dsDel">Удалить тренировку</button>' });
    sh.q('#dsDel').onclick = async () => { if (await deleteSession(s.id, true)) sh.close(); };
  }

  /* ================= Start / gym mode ================= */
  async function startSession(x) {
    try {
      let sid = Number(x.id);
      if (x.status !== 'in_progress') { const j = await API('start', { session_id: sid, date: x.scheduled_date }); sid = Number(j.session_id || sid); }
      await openGym(sid);
    } catch (e) { U.toast('Ошибка: ' + e.message); }
  }
  async function heroAction(btn) {
    const h = hero();
    await U.busy(btn, async () => {
      if (h.action === 'continue') return openGym(h.session.id);
      if (h.action === 'start') return startSession(h.session);
      if (h.action === 'start_next') return startNext();
      if (h.action === 'today') { await API('move', { id: h.session.id, date: U.today() }); await load(); const s2 = st.summary.today_session; if (s2) return startSession(s2); return; }
      setTab('calendar');
    }, 'Открываю…');
  }
  async function startNext(dayId) {
    const j = await API('start_next', Object.assign({ date: U.today() }, dayId ? { day_id: dayId } : {}));
    await openGym(Number(j.session_id));
  }
  async function otherDay() {
    const days = st.summary.days || [];
    const v = await U.actions(days.map(d => ({ label: `${d.name} · ${(d.exercises || []).length} упр.`, value: d.id })), 'Какой день начать сегодня?');
    if (v) { try { await startNext(v); } catch (e) { U.toast('Ошибка: ' + e.message); } }
  }
  function previewDay() {
    const h = hero(); if (!h.day) return;
    U.sheet({ title: esc(h.day.name), body: `<div class="ui-list">${(h.day.exercises || []).map(e => `<div class="ui-item"><div class="grow"><div class="t" style="white-space:normal">${esc(e.name)}</div><div class="s">${planText(e)}</div></div></div>`).join('')}</div>` });
  }

  let gym = null;
  async function openGym(id) {
    const j = await API('session', { id: Number(id) });
    const s = j.session;
    gym = { s, rest: { end: 0, total: 0, timer: null }, clock: null, lock: null, sheet: null };
    const sh = U.sheet({ full: true, cls: 'wk-gym', title: `${esc(s.day_name)} <div class="wk-gym-sub" id="gymClock"></div>`,
      body: `<div class="wk-prog"><i id="gymBar"></i></div><div id="gymList"></div>`,
      foot: '<button class="ui-btn" data-ui-close>Пауза</button><button class="ui-btn primary" id="gymFinish">Завершить</button>',
      onClose: () => { clearInterval(gym.clock); clearInterval(gym.rest.timer); try { gym.lock && gym.lock.release(); } catch (e) { /* ignore */ } load(); } });
    gym.sheet = sh;
    const head = sh.q('.ui-sheet-head'); const restBtn = document.createElement('button');
    restBtn.className = 'wk-rest'; restBtn.id = 'gymRest'; restBtn.textContent = 'Отдых'; head.insertBefore(restBtn, head.querySelector('.ui-x'));
    restBtn.onclick = restMenu;
    sh.q('#gymList').innerHTML = s.exercises.map(gymExercise).join('');
    sh.q('#gymList').addEventListener('click', gymClick);
    sh.q('#gymList').addEventListener('input', e => { const r = e.target.closest('.wk-setrow'); if (r && r.classList.contains('done')) r.querySelector('.wk-ok').textContent = '↻'; });
    sh.q('#gymFinish').onclick = finishGym;
    const started = s.started_at ? new Date(String(s.started_at).replace(' ', 'T')) : new Date();
    const tick = () => { const m = Math.max(0, Math.floor((Date.now() - started) / 60000)); const el = sh.q('#gymClock'); if (el) el.textContent = `${m} мин · ${U.date(s.scheduled_date, { day: 'numeric', month: 'long' })}`; };
    tick(); gym.clock = setInterval(tick, 20000);
    // keep the screen on, but never block opening the workout on it (iOS PWAs may never resolve)
    try { if (navigator.wakeLock) navigator.wakeLock.request('screen').then(l => { if (gym) gym.lock = l; }).catch(() => {}); } catch (e) { /* not supported */ }
    gymProgress();
    const first = sh.q('.wk-setrow:not(.done)'); if (first) first.closest('.wk-exc').classList.add('cur');
  }
  function gymExercise(e) {
    const sets = e.sets || [], prev = e.previous_sets || [];
    const n = Math.max(Number(e.target_sets || 3), ...sets.map(x => Number(x.set_no)));
    let rows = '';
    for (let i = 1; i <= n; i++) rows += setRow(e, i);
    const prevTxt = prev.length ? `<b>Прошлый раз</b> (${U.date(e.previous_date)}): ${prev.map(x => `${x.weight_kg != null ? fmtW(x.weight_kg) + '×' : ''}${x.reps}`).join(' · ')}` : 'В первый раз — начни с комфортного веса';
    const best = e.best ? ` · <b>рекорд</b> ${fmtW(e.best.weight_kg)}×${e.best.reps}` : '';
    const allDone = sets.filter(x => x.completed_at).length >= Number(e.target_sets || 3);
    return `<div class="wk-exc ${allDone ? 'complete' : ''}" data-ex="${e.id}"><div class="wk-exc-head"><div class="grow"><b>${esc(e.name)}</b><div class="wk-exc-plan">План: ${planText(e)} · отдых ${restLabel(e.rest_seconds || 90)}</div></div></div>
      <div class="wk-hint">${prevTxt}${best}</div>${e.notes ? `<div class="wk-note">${esc(e.notes)}</div>` : ''}
      <div class="wk-units"><span>#</span><span>вес, кг</span><span>повт.</span><span></span></div>
      <div class="wk-rows">${rows}</div><button class="wk-addset" data-add-set="${e.id}">+ подход</button></div>`;
  }
  function setRow(e, i) {
    const cur = (e.sets || []).find(x => Number(x.set_no) === i) || {}, pr = (e.previous_sets || []).find(x => Number(x.set_no) === i) || (e.previous_sets || []).slice(-1)[0] || {};
    const done = !!cur.completed_at;
    const prevSet = (e.sets || []).find(x => Number(x.set_no) === i - 1);
    const w = cur.weight_kg != null ? cur.weight_kg : (prevSet && prevSet.weight_kg != null ? prevSet.weight_kg : (pr.weight_kg != null ? pr.weight_kg : e.default_weight));
    const repsPh = pr.reps || String(e.target_reps || '').split(/[–-]/)[0] || '';
    return `<div class="wk-setrow ${done ? 'done' : ''}" data-set="${i}"><div class="wk-setno" data-act-set="${i}">${i}</div>
      <div class="wk-w"><button data-step="-1" aria-label="Меньше">−</button><input inputmode="decimal" data-w value="${esc(fmtW(w))}" placeholder="—"><button data-step="1" aria-label="Больше">+</button></div>
      <input class="wk-r" inputmode="numeric" data-r value="${esc(cur.reps != null ? cur.reps : '')}" placeholder="${esc(repsPh)}">
      <button class="wk-ok" data-ok aria-label="Подход выполнен">${done ? '✓' : '○'}</button></div>`;
  }
  function gymProgress() {
    const ex = gym.s.exercises; let total = 0, done = 0;
    gym.sheet.qa('.wk-exc').forEach(c => { const rows = c.querySelectorAll('.wk-setrow'); total += rows.length; done += c.querySelectorAll('.wk-setrow.done').length; });
    const bar = gym.sheet.q('#gymBar'); if (bar) bar.style.width = (total ? done / total * 100 : 0) + '%';
    return { total, done, ex };
  }
  async function gymClick(ev) {
    const card = ev.target.closest('.wk-exc'); if (!card) return;
    const exId = Number(card.dataset.ex), e = gym.s.exercises.find(x => Number(x.id) === exId);
    const step = ev.target.closest('[data-step]');
    if (step) {
      const inp = step.parentElement.querySelector('[data-w]'); const v = parseW(inp.value) || 0;
      const inc = v < 20 ? 1 : 2.5; const nv = Math.max(0, Math.round((v + Number(step.dataset.step) * inc) * 100) / 100);
      inp.value = fmtW(nv); const r = step.closest('.wk-setrow'); if (r.classList.contains('done')) r.querySelector('.wk-ok').textContent = '↻'; return;
    }
    if (ev.target.closest('[data-add-set]')) {
      const rows = card.querySelector('.wk-rows'), n = rows.querySelectorAll('.wk-setrow').length + 1;
      if (n > 12) return U.toast('Максимум 12 подходов');
      rows.insertAdjacentHTML('beforeend', setRow(e, n)); gymProgress(); return;
    }
    const num = ev.target.closest('[data-act-set]');
    if (num) {
      const row = num.closest('.wk-setrow'); if (!row.classList.contains('done')) return;
      const v = await U.actions([{ label: 'Удалить подход ' + row.dataset.set, value: 'del', danger: true }], e.name);
      if (v !== 'del') return;
      try { await API('delete_set', { session_id: gym.s.id, exercise_id: exId, set_no: Number(row.dataset.set) }); row.classList.remove('done', 'pr'); row.querySelector('.wk-ok').textContent = '○'; e.sets = (e.sets || []).filter(x => Number(x.set_no) !== Number(row.dataset.set)); gymProgress(); } catch (err) { U.toast('Ошибка: ' + err.message); }
      return;
    }
    const ok = ev.target.closest('[data-ok]'); if (!ok) return;
    const row = ok.closest('.wk-setrow'), setNo = Number(row.dataset.set);
    const w = parseW(row.querySelector('[data-w]').value);
    let r = row.querySelector('[data-r]').value.trim();
    if (!r) { r = row.querySelector('[data-r]').placeholder; if (!/^\d+$/.test(r)) { row.querySelector('[data-r]').focus(); return U.toast('Укажи повторы'); } row.querySelector('[data-r]').value = r; }
    if (w != null && (isNaN(w) || w < 0)) return U.toast('Некорректный вес');
    ok.disabled = true;
    try {
      await API('save_set', { session_id: gym.s.id, exercise_id: exId, set_no: setNo, weight_kg: w, reps: Number(r) });
      e.sets = (e.sets || []).filter(x => Number(x.set_no) !== setNo).concat([{ set_no: setNo, weight_kg: w, reps: Number(r), completed_at: '1' }]);
      row.classList.add('done'); ok.textContent = '✓';
      const isPr = e.best ? (w != null && (w > e.best.weight_kg || (w === e.best.weight_kg && Number(r) > e.best.reps))) : false;
      if (isPr) { row.classList.add('pr'); ok.textContent = '★'; U.toast('Новый рекорд! ' + fmtW(w) + ' кг × ' + r); U.vibrate([60, 40, 60]); e.best = { weight_kg: w, reps: Number(r) }; }
      // carry weight forward to the next empty set
      const next = row.nextElementSibling;
      if (next && !next.classList.contains('done')) { const nw = next.querySelector('[data-w]'); if (nw && w != null) nw.value = fmtW(w); }
      const doneN = card.querySelectorAll('.wk-setrow.done').length;
      if (doneN >= Number(e.target_sets || 3)) card.classList.add('complete');
      startRest(Number(e.rest_seconds || 90));
      gymProgress();
      // focus card: next unfinished
      gym.sheet.qa('.wk-exc').forEach(c => c.classList.remove('cur'));
      const nextRow = gym.sheet.q('.wk-setrow:not(.done)');
      if (nextRow) { const c = nextRow.closest('.wk-exc'); c.classList.add('cur'); if (c !== card) setTimeout(() => c.scrollIntoView({ behavior: 'smooth', block: 'start' }), 250); }
    } catch (err) { U.toast('Ошибка: ' + err.message); } finally { ok.disabled = false; }
  }
  function startRest(sec) {
    const R = gym.rest; R.total = sec; R.end = Date.now() + sec * 1000; R.buzzed = false;
    clearInterval(R.timer); R.timer = setInterval(restTick, 250); restTick();
  }
  function restTick() {
    const R = gym.rest, b = gym.sheet && gym.sheet.q('#gymRest'); if (!b) return;
    const left = Math.round((R.end - Date.now()) / 1000);
    if (left > 0) { b.className = 'wk-rest on'; b.textContent = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`; }
    else { b.className = 'wk-rest over'; b.textContent = `+${Math.floor(-left / 60)}:${String(-left % 60).padStart(2, '0')}`; if (!R.buzzed) { R.buzzed = true; U.vibrate([200, 100, 200]); } if (-left > 600) { clearInterval(R.timer); b.className = 'wk-rest'; b.textContent = 'Отдых'; } }
  }
  async function restMenu() {
    const v = await U.actions([{ label: '+30 секунд', value: 30 }, { label: 'Отдых 1:30', value: 'r90' }, { label: 'Отдых 3:00', value: 'r180' }, { label: 'Остановить таймер', value: 'stop' }], 'Таймер отдыха');
    if (v === 30) { gym.rest.end = Math.max(gym.rest.end, Date.now()) + 30000; gym.rest.buzzed = false; if (!gym.rest.timer) gym.rest.timer = setInterval(restTick, 250); restTick(); }
    if (v === 'r90') startRest(90); if (v === 'r180') startRest(180);
    if (v === 'stop') { clearInterval(gym.rest.timer); gym.rest.timer = null; const b = gym.sheet.q('#gymRest'); b.className = 'wk-rest'; b.textContent = 'Отдых'; }
  }
  async function finishGym() {
    const p = gymProgress();
    if (!p.done) { const ok = await U.confirm('Не отмечено ни одного подхода. Завершить пустую тренировку?', 'Завершить'); if (!ok) return; }
    else if (p.done < p.total) { const ok = await U.confirm(`Отмечено ${p.done} из ${p.total} подходов. Завершить тренировку?`, 'Завершить'); if (!ok) return; }
    const s = gym.s;
    try { await API('complete', { session_id: s.id }); } catch (e) { return U.toast('Ошибка: ' + e.message); }
    let vol = 0, sets = 0, prs = gym.sheet.qa('.wk-setrow.pr').length;
    s.exercises.forEach(e => (e.sets || []).forEach(x => { if (x.completed_at) { sets++; vol += Number(x.weight_kg || 0) * Number(x.reps || 0); } }));
    const started = s.started_at ? new Date(String(s.started_at).replace(' ', 'T')) : null;
    const mins = started ? Math.max(1, Math.round((Date.now() - started) / 60000)) : null;
    gym.sheet.close();
    U.sheet({ title: 'Тренировка завершена', body: `<div class="wk-done-mark">💪</div>
      <div class="wk-summary-big"><div class="ui-tile"><span>Время</span><b>${mins && mins < 600 ? mins + ' мин' : '—'}</b></div><div class="ui-tile"><span>Подходы</span><b>${sets}</b></div><div class="ui-tile"><span>Объём</span><b>${U.tons(vol)}</b></div></div>
      ${prs ? `<div class="ui-card" style="text-align:center"><b class="wk-pr">★ ${prs} ${U.plural(prs, 'новый рекорд', 'новых рекорда', 'новых рекордов')}</b></div>` : ''}`,
      foot: '<button class="ui-btn primary" data-ui-close>Готово</button>' });
  }

  /* ================= Events / render ================= */
  async function onClick(ev) {
    const b = ev.target.closest('[data-act]'); if (!b || !root.contains(b)) return;
    const a = b.dataset.act;
    if (a === 'hero') return heroAction(b);
    if (a === 'preview') return previewDay();
    if (a === 'other-day') return otherDay();
    if (a === 'mode') { try { await API('set_mode', { mode: b.dataset.mode }); U.toast(b.dataset.mode === 'rotation' ? 'Режим: по очереди' : 'Режим: по дням недели'); await load(); } catch (e) { U.toast('Ошибка: ' + e.message); } return; }
    if (a === 'skip-missed') {
      const n = (st.summary.overdue_sessions || []).length;
      if (!(await U.confirm(`Отметить ${n} ${U.plural(n, 'пропущенную тренировку', 'пропущенные тренировки', 'пропущенных тренировок')} как пропуск и убрать из календаря? Выполненные тренировки и расписание не изменятся.`, 'Очистить'))) return;
      try { const j = await API('skip_missed', { date: U.today() }); U.toast(`Убрано: ${j.skipped}`); await load(); } catch (e) { U.toast('Ошибка: ' + e.message); }
      return;
    }
    if (a === 'tab') return setTab(b.dataset.tab);
    if (a === 'done-session') return openDoneSession(b.dataset.id);
    if (a === 'month') { const d = new Date(st.month + '-01T12:00:00'); d.setMonth(d.getMonth() + Number(b.dataset.d)); st.month = d.toISOString().slice(0, 7); const r = await API('summary', { date: U.today(), month: st.month }); st.summary = r.summary; return render(); }
    if (a === 'cal') return calendarAction(b.dataset.id);
    if (a === 'schedule') return scheduleSheet();
    if (a === 'ex-add') return exerciseSheet(Number(b.dataset.day), null);
    if (a === 'ex-menu') { ev.stopPropagation(); return exMenu(Number(b.dataset.id), Number(b.dataset.i), Number(b.dataset.n)); }
    if (a === 'ex-edit') { const f = findEx(b.dataset.id); if (f) exerciseSheet(f.d.id, f.e); return; }
    if (a === 'day-menu') return dayMenu(Number(b.dataset.id));
    if (a === 'ex-stats') return exerciseStatsSheet(b.dataset.key);
  }
  function render() {
    if (!root) return;
    const v = root.querySelector('#wkView');
    if (!st.summary) { v.innerHTML = '<div class="ui-card"><div class="ui-empty">Загружаю…</div></div>'; return; }
    v.innerHTML = st.tab === 'calendar' ? renderCalendar() : st.tab === 'program' ? renderProgram() : st.tab === 'progress' ? renderProgress() : renderOverview();
    const pm = v.querySelector('#wkPm'); if (pm) U.seg(pm, m => { st.progressMode = m; render(); });
    const sub = root.querySelector('#wkSub'); const t = st.stats?.totals;
    if (sub && t) sub.textContent = `${st.summary.program?.target_per_week || 3} раза в неделю · ${t.workouts} ${U.plural(t.workouts, 'тренировка', 'тренировки', 'тренировок')} всего`;
  }

  window.Workouts = { load, openGym, refresh };
})();
