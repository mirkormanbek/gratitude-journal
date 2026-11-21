(() => {
  const V46 = '4.6';

  function localDatePlus(dateStr, days) {
    const d = new Date(dateStr + 'T12:00:00');
    d.setDate(d.getDate() + days);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  function tomorrowDate() {
    return localDatePlus(today(), 1);
  }

  function ruDayMonth(dateStr) {
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('ru-RU', {day:'numeric', month:'long'});
  }

  function applyDailyLabels() {
    const dateInput = document.getElementById('d_date');
    const action = document.getElementById('d_action');

    if (dateInput) {
      const field = dateInput.closest('.field');
      const label = field ? field.querySelector('label') : null;
      if (label) label.textContent = 'Дата дня, который подводишь итог';
    }

    if (action) {
      const field = action.closest('.field');
      const label = field ? field.querySelector('label') : null;
      if (label) label.textContent = '5. Задачи на завтра';
      action.placeholder = 'Каждая строка = отдельная задача\nПроверить поставку\nПозвонить Шафику\nПодготовить отгрузку WB';

      let hint = field ? field.querySelector('.taskHint') : null;
      if (!hint && field) {
        hint = document.createElement('div');
        hint.className = 'taskHint';
        action.insertAdjacentElement('afterend', hint);
      }
      if (hint) {
        const source = dateInput?.value || today();
        const due = localDatePlus(source, 1);
        hint.innerHTML = `Каждая непустая строка = отдельная задача. <b>Будут добавлены на ${ruDayMonth(due)}</b>. Если сохраняешь дневник после полуночи, укажи дату дня, который подводишь итог.`;
      }
    }
  }

  // ---- Новые вкладки задач ----
  const tabs = document.querySelector('.taskTabs');
  if (tabs) {
    tabs.innerHTML = `
      <button class="taskTab active" data-task-view="today">Сегодня</button>
      <button class="taskTab" data-task-view="tomorrow">Завтра</button>
      <button class="taskTab" data-task-view="overdue">Просрочено</button>
      <button class="taskTab" data-task-view="active">Все активные</button>
      <button class="taskTab" data-task-view="done">Выполнено</button>
    `;
  }

  // На главной карточка больше не называется только "сегодня".
  const homeEyebrow = document.querySelector('#taskHomeCard .eyebrow');
  if (homeEyebrow) homeEyebrow.textContent = 'Задачи';

  // ---- Переопределяем выбор задач для вкладок ----
  visibleTasks = function() {
    const list = tasks.slice();
    const td = today();
    const tm = tomorrowDate();

    if (taskView === 'done') {
      return list
        .filter(t => t.status === 'done')
        .sort((a,b) => String(b.completed_at || b.due_date).localeCompare(String(a.completed_at || a.due_date)))
        .slice(0,80);
    }

    if (taskView === 'tomorrow') {
      return list
        .filter(t => t.status !== 'done' && t.due_date === tm)
        .sort((a,b) => Number(a.priority)-Number(b.priority) || Number(a.id)-Number(b.id));
    }

    if (taskView === 'overdue') {
      return list
        .filter(t => t.status !== 'done' && t.due_date < td)
        .sort((a,b) => a.due_date.localeCompare(b.due_date) || Number(a.priority)-Number(b.priority) || Number(a.id)-Number(b.id));
    }

    if (taskView === 'active') {
      return list
        .filter(t => t.status !== 'done')
        .sort((a,b) => {
          const ao = a.due_date < td ? 0 : (a.due_date === td ? 1 : 2);
          const bo = b.due_date < td ? 0 : (b.due_date === td ? 1 : 2);
          return ao-bo || a.due_date.localeCompare(b.due_date) || Number(a.priority)-Number(b.priority) || Number(a.id)-Number(b.id);
        });
    }

    // Сегодня — только сегодняшний день. Просрочка теперь отдельной вкладкой.
    return list
      .filter(t => t.status !== 'done' && t.due_date === td)
      .sort((a,b) => Number(a.priority)-Number(b.priority) || Number(a.id)-Number(b.id));
  };

  renderTasks = function() {
    renderTaskStats();

    const td = today();
    const tm = tomorrowDate();
    const counts = {
      today: tasks.filter(t => t.status !== 'done' && t.due_date === td).length,
      tomorrow: tasks.filter(t => t.status !== 'done' && t.due_date === tm).length,
      overdue: tasks.filter(t => t.status !== 'done' && t.due_date < td).length,
      active: tasks.filter(t => t.status !== 'done').length,
      done: tasks.filter(t => t.status === 'done').length
    };

    document.querySelectorAll('.taskTab').forEach(b => {
      const v = b.dataset.taskView;
      b.classList.toggle('active', v === taskView);
      const labels = {
        today:'Сегодня',
        tomorrow:'Завтра',
        overdue:'Просрочено',
        active:'Все активные',
        done:'Выполнено'
      };
      b.textContent = `${labels[v] || v} · ${counts[v] ?? 0}`;
    });

    const list = visibleTasks();
    const empty = {
      today:'На сегодня задач нет.',
      tomorrow:'На завтра задач пока нет.',
      overdue:'Просроченных задач нет.',
      active:'Активных задач нет.',
      done:'Пока нет выполненных задач.'
    };

    $('taskList').innerHTML = list.length
      ? list.map(taskRowHtml).join('')
      : `<div class="taskEmpty">${empty[taskView] || 'Задач нет.'}</div>`;
  };

  // ---- Главная: видно и сегодня, и завтра ----
  renderTaskHome = function() {
    const td = today();
    const tm = tomorrowDate();

    const todayPending = tasks
      .filter(t => t.status !== 'done' && t.due_date === td)
      .sort((a,b) => Number(a.priority)-Number(b.priority) || Number(a.id)-Number(b.id));

    const tomorrowPending = tasks
      .filter(t => t.status !== 'done' && t.due_date === tm)
      .sort((a,b) => Number(a.priority)-Number(b.priority) || Number(a.id)-Number(b.id));

    const overdue = tasks
      .filter(t => t.status !== 'done' && t.due_date < td)
      .sort((a,b) => a.due_date.localeCompare(b.due_date) || Number(a.priority)-Number(b.priority));

    const done = Number(taskStats.today_done || 0);
    const total = Number(taskStats.today_total || 0);

    $('taskHomeTitle').textContent = total
      ? `Сегодня: ${done} из ${total} выполнено`
      : 'Сегодня задач нет';

    const meta = [];
    meta.push(`Завтра: ${tomorrowPending.length}`);
    if (overdue.length) meta.push(`Просрочено: ${overdue.length}`);
    $('taskHomeMeta').textContent = meta.join(' · ');

    let html = '';

    if (todayPending.length) {
      html += `<div class="v46MiniGroup">Сегодня</div>`;
      html += todayPending.slice(0,2).map(t => `
        <label class="taskMini">
          <input type="checkbox" data-home-task-toggle="${t.id}">
          <span>${esc(t.title)}</span>
        </label>`).join('');
      if (todayPending.length > 2) html += `<div class="v46More">+ ещё ${todayPending.length-2}</div>`;
    }

    if (tomorrowPending.length) {
      html += `<div class="v46MiniGroup tomorrow">Завтра · ${ruDayMonth(tm)}</div>`;
      html += tomorrowPending.slice(0,2).map(t => `
        <label class="taskMini">
          <input type="checkbox" disabled title="Отметить выполненной можно завтра или в разделе задач">
          <span>${esc(t.title)}</span>
        </label>`).join('');
      if (tomorrowPending.length > 2) html += `<div class="v46More">+ ещё ${tomorrowPending.length-2}</div>`;
    }

    if (!html) {
      html = '<div class="empty" style="padding:0">Сегодня и на завтра задач пока нет.</div>';
    }

    $('taskHomeList').innerHTML = html;
  };

  // Навешиваем новые вкладки после замены HTML.
  document.querySelectorAll('.taskTab').forEach(b => {
    b.onclick = () => {
      taskView = b.dataset.taskView;
      renderTasks();
    };
  });

  // Подсказка должна следовать за датой дневника.
  const dateInput = document.getElementById('d_date');
  if (dateInput) {
    dateInput.addEventListener('input', applyDailyLabels);
    dateInput.addEventListener('change', applyDailyLabels);
  }

  // При открытии старой записи/сбросе формы дата меняется программно.
  if (typeof editDaily === 'function') {
    const oldEditDaily = editDaily;
    editDaily = function(x) {
      oldEditDaily(x);
      setTimeout(applyDailyLabels, 0);
    };
  }
  if (typeof resetDaily === 'function') {
    const oldResetDaily = resetDaily;
    resetDaily = function() {
      oldResetDaily();
      setTimeout(applyDailyLabels, 0);
    };
  }

  applyDailyLabels();

  // Если данные уже успели загрузиться до подключения патча — сразу перерисуем.
  try {
    if (Array.isArray(tasks)) {
      renderTasks();
      renderTaskHome();
    }
  } catch (_) {}
})();