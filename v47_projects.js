(() => {
  let projectsV47 = [];
  let taskProjectMapV47 = {};
  let projectFilterV47 = 0;

  function projectEndpoint(action, data={}) {
    return fetch('projects.php?action=' + encodeURIComponent(action), {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},
      body:JSON.stringify(data)
    }).then(async r => {
      if(r.status===401){ location='login.php'; throw new Error('auth'); }
      let j={}; try{ j=await r.json(); }catch(_){}
      if(!r.ok || j.ok===false) throw new Error(j.error || ('HTTP '+r.status));
      return j;
    });
  }

  function pEsc(s){ return esc(String(s ?? '')); }
  function projectName(id){
    const p=projectsV47.find(x=>Number(x.id)===Number(id));
    return p ? p.name : '';
  }
  function projectOptions(selected=0, includeNone=true){
    let html=includeNone?'<option value="0">Без проекта</option>':'';
    html += projectsV47
      .filter(p=>p.status!=='done')
      .map(p=>`<option value="${Number(p.id)}" ${Number(p.id)===Number(selected)?'selected':''}>${pEsc(p.name)}</option>`)
      .join('');
    return html;
  }
  function v47PrettyDate(d){
    if(!d)return 'Без дедлайна';
    return new Date(d+'T12:00:00').toLocaleDateString('ru-RU',{day:'numeric',month:'short',year:'numeric'});
  }

  // ---------- UI injection ----------
  const tasksPanel=document.getElementById('tasks');
  if(tasksPanel){
    const hero=tasksPanel.querySelector('.hero');
    if(hero){
      const btn=document.createElement('button');
      btn.className='btn secondary';
      btn.id='openProjectsV47';
      btn.textContent='Проекты';
      hero.appendChild(btn);
    }

    const formGrid=tasksPanel.querySelector('.taskFormGrid');
    if(formGrid && !document.getElementById('t_project')){
      const wrap=document.createElement('div');
      wrap.className='v47ProjectField';
      wrap.innerHTML='<label>Проект</label><select id="t_project"><option value="0">Без проекта</option></select>';
      formGrid.appendChild(wrap);
    }

    const tabs=tasksPanel.querySelector('.taskTabs');
    if(tabs && !document.getElementById('taskProjectFilterV47')){
      const filter=document.createElement('div');
      filter.className='v47TaskFilter';
      filter.innerHTML='<label>Фильтр по проекту</label><select id="taskProjectFilterV47"><option value="0">Все проекты</option></select>';
      tabs.insertAdjacentElement('beforebegin',filter);
    }
  }

  const dailyPanel=document.getElementById('daily');
  if(dailyPanel && !document.getElementById('projects')){
    const section=document.createElement('section');
    section.id='projects';
    section.className='panel';
    section.innerHTML=`
      <div class="hero">
        <div>
          <h2>Проекты</h2>
          <p>Большой результат разбивается на конкретные действия. В задачах остаётся только то, что реально можно закрыть.</p>
        </div>
      </div>

      <div class="card formCard v47ProjectForm">
        <input type="hidden" id="p_id">
        <div class="v47ProjectFormGrid">
          <div>
            <label>Проект</label>
            <input id="p_name" maxlength="180" placeholder="Например: Запустить оптовый канал продаж">
          </div>
          <div>
            <label>Результат / цель проекта</label>
            <input id="p_outcome" maxlength="1200" placeholder="Например: получить первых 5 активных оптовых клиентов">
          </div>
          <div>
            <label>Дедлайн</label>
            <input id="p_deadline" type="date">
          </div>
        </div>
        <div class="v47ProjectHint">Принцип: если дело нельзя закрыть одной галочкой — это проект. В проект добавляй маленькие следующие действия.</div>
      </div>
      <div class="btnrow">
        <button class="btn primary" id="saveProjectV47">Создать проект</button>
        <button class="btn secondary" id="cancelProjectV47" hidden>Отмена</button>
      </div>
      <div class="status" id="projectStatusV47"></div>

      <div class="v47ProjectTabs">
        <button class="taskTab active" data-pstatus="active">Активные</button>
        <button class="taskTab" data-pstatus="paused">На паузе</button>
        <button class="taskTab" data-pstatus="done">Завершённые</button>
      </div>
      <div id="projectListV47" class="v47ProjectList"></div>
    `;
    dailyPanel.insertAdjacentElement('beforebegin',section);
  }

  // Desktop nav
  const sideNav=document.querySelector('.sidebar nav');
  if(sideNav && !sideNav.querySelector('[data-panel="projects"]')){
    const tasksBtn=sideNav.querySelector('[data-panel="tasks"]');
    const b=document.createElement('button');
    b.className='navbtn'; b.dataset.panel='projects';
    b.innerHTML='<span class="dot"></span>Проекты';
    tasksBtn ? tasksBtn.insertAdjacentElement('afterend',b) : sideNav.appendChild(b);
    b.onclick=()=>openPanel('projects');
  }

  // Keep panel title correct even if old titles map has no key.
  document.querySelectorAll('[data-panel="projects"]').forEach(b=>{
    b.addEventListener('click',()=>setTimeout(()=>{
      const t=document.getElementById('topTitle'); if(t)t.textContent='Проекты';
    },0));
  });

  if(document.getElementById('openProjectsV47')){
    document.getElementById('openProjectsV47').onclick=()=>openPanel('projects');
  }

  // ---------- Project data ----------
  async function loadProjectsV47(){
    try{
      const j=await projectEndpoint('list',{date:today()});
      projectsV47=j.projects||[];
      taskProjectMapV47=j.task_project_map||{};

      if(Array.isArray(tasks)){
        tasks.forEach(t=>{ t.project_id=Number(taskProjectMapV47[String(t.id)]||0); });
      }

      fillProjectSelectsV47();
      renderProjectsV47();
      try{ renderTasks(); renderTaskHome(); }catch(_){}
    }catch(e){
      const box=document.getElementById('projectListV47');
      if(box)box.innerHTML=`<div class="taskEmpty errorText">${pEsc(e.message)}</div>`;
    }
  }

  function fillProjectSelectsV47(){
    const taskSel=document.getElementById('t_project');
    if(taskSel){
      const cur=Number(taskSel.value||0);
      taskSel.innerHTML=projectOptions(cur,true);
    }
    const filter=document.getElementById('taskProjectFilterV47');
    if(filter){
      const cur=Number(filter.value||0);
      filter.innerHTML='<option value="0">Все проекты</option>' +
        projectsV47.map(p=>`<option value="${Number(p.id)}" ${Number(p.id)===cur?'selected':''}>${pEsc(p.name)}</option>`).join('');
    }
  }

  // ---------- Task integration ----------
  const v46VisibleTasks = typeof visibleTasks==='function' ? visibleTasks : null;
  if(v46VisibleTasks){
    visibleTasks=function(){
      const list=v46VisibleTasks();
      if(!projectFilterV47)return list;
      return list.filter(t=>Number(t.project_id||0)===Number(projectFilterV47));
    };
  }

  const baseTaskRowHtmlV47 = typeof taskRowHtml==='function' ? taskRowHtml : null;
  if(baseTaskRowHtmlV47){
    taskRowHtml=function(t){
      const overdue=isTaskOverdue(t), done=t.status==='done';
      const source=t.source_type==='daily'?'Из ежедневника'+(t.source_date?' · '+t.source_date:''):'Добавлено вручную';
      const pid=Number(t.project_id||0);
      return `<div class="taskRow ${done?'done':''}" data-task-id="${t.id}">
        <input class="taskCheck" type="checkbox" data-task-toggle="${t.id}" ${done?'checked':''} aria-label="Выполнено">
        <div>
          <div class="taskTitle">${esc(t.title)}</div>
          <div class="taskMeta">
            <span class="taskPill ${overdue?'overdue':''}">${overdue?'Просрочено · ':''}${esc(prettyTaskDate(t.due_date))}</span>
            <span class="taskPill ${taskPriorityClass(t.priority)}">${esc(taskPriorityName(t.priority))}</span>
            ${pid?`<span class="taskPill v47ProjectPill">${pEsc(projectName(pid))}</span>`:''}
            <span>${esc(source)}</span>
          </div>
        </div>
        <div class="taskActions">
          ${!done?`<select class="taskPriority v47ProjectSelect" data-task-project="${t.id}" title="Проект">
            ${projectOptions(pid,true)}
          </select>
          <select class="taskPriority" data-task-priority="${t.id}" aria-label="Приоритет">
            <option value="1" ${Number(t.priority)===1?'selected':''}>Главное</option>
            <option value="2" ${Number(t.priority)===2?'selected':''}>Важно</option>
            <option value="3" ${Number(t.priority)===3?'selected':''}>Обычное</option>
          </select>
          <button class="taskSmall" data-task-move="${t.id}" data-move-to="today">Сегодня</button>
          <button class="taskSmall" data-task-move="${t.id}" data-move-to="tomorrow">Завтра</button>`:''}
          ${t.source_type==='manual'&&!done?`<button class="taskSmall" data-task-edit="${t.id}">Изменить</button>`:''}
          ${t.source_type==='daily'&&!done?`<button class="taskSmall" data-task-source="${t.id}">Источник</button>`:''}
          <button class="taskSmall danger" data-task-delete="${t.id}">Удалить</button>
        </div>
      </div>`;
    };
  }

  const oldLoadTasksV47 = typeof loadTasks==='function' ? loadTasks : null;
  if(oldLoadTasksV47){
    loadTasks=async function(){
      await oldLoadTasksV47();
      await loadProjectsV47();
    };
  }

  // Change task project from row.
  const taskList=document.getElementById('taskList');
  if(taskList){
    taskList.addEventListener('change',async e=>{
      const id=e.target.dataset.taskProject;
      if(!id)return;
      try{
        await projectEndpoint('assign_task',{task_id:Number(id),project_id:Number(e.target.value||0)});
        await loadProjectsV47();
      }catch(err){
        alert('Ошибка: '+err.message);
      }
    });
  }

  // Project filter in Tasks.
  const pf=document.getElementById('taskProjectFilterV47');
  if(pf){
    pf.onchange=()=>{
      projectFilterV47=Number(pf.value||0);
      renderTasks();
    };
  }

  // Save manual task + project in one flow.
  const saveTaskBtn=document.getElementById('saveTask');
  if(saveTaskBtn){
    saveTaskBtn.onclick=async()=>{
      const id=$('t_id').value;
      const title=$('t_title').value.trim();
      const due_date=$('t_date').value;
      const priority=Number($('t_priority').value||3);
      const project_id=Number((document.getElementById('t_project')||{}).value||0);
      if(!title)return alert('Напиши задачу.');

      saveTaskBtn.disabled=true;
      const old=saveTaskBtn.textContent; saveTaskBtn.textContent='Сохраняю…';
      try{
        const j=await taskEndpoint(id?'update':'add',{id:id?Number(id):undefined,title,due_date,priority});
        const taskId=id?Number(id):Number(j.id||0);
        if(taskId) await projectEndpoint('assign_task',{task_id:taskId,project_id});
        $('taskStatus').textContent=id?'✓ Задача обновлена':'✓ Задача добавлена';
        resetTaskForm();
        await loadTasks();
      }catch(e){
        $('taskStatus').textContent='Ошибка: '+e.message;
      }finally{
        saveTaskBtn.disabled=false; saveTaskBtn.textContent=old;
      }
    };
  }

  // Extend task reset/edit.
  const oldResetTaskV47 = typeof resetTaskForm==='function' ? resetTaskForm : null;
  if(oldResetTaskV47){
    resetTaskForm=function(){
      oldResetTaskV47();
      const s=document.getElementById('t_project'); if(s)s.value='0';
    };
    const cancel=document.getElementById('cancelTaskEdit');
    if(cancel)cancel.onclick=resetTaskForm;
  }

  if(taskList){
    taskList.addEventListener('click',e=>{
      const edit=e.target.dataset.taskEdit;
      if(!edit)return;
      setTimeout(()=>{
        const t=tasks.find(x=>Number(x.id)===Number(edit));
        const s=document.getElementById('t_project');
        if(t&&s)s.value=String(Number(t.project_id||0));
      },0);
    });
  }

  // ---------- Project form ----------
  let projectStatusFilterV47='active';

  function resetProjectFormV47(){
    $('p_id').value=''; $('p_name').value=''; $('p_outcome').value=''; $('p_deadline').value='';
    $('saveProjectV47').textContent='Создать проект'; $('cancelProjectV47').hidden=true;
    $('projectStatusV47').textContent='';
  }

  $('cancelProjectV47').onclick=resetProjectFormV47;

  $('saveProjectV47').onclick=async()=>{
    const id=Number($('p_id').value||0);
    const name=$('p_name').value.trim();
    const outcome=$('p_outcome').value.trim();
    const deadline=$('p_deadline').value;
    if(!name)return alert('Напиши название проекта.');

    const b=$('saveProjectV47'); b.disabled=true;
    const old=b.textContent; b.textContent='Сохраняю…';
    try{
      await projectEndpoint(id?'update':'add',{id,name,outcome,deadline});
      $('projectStatusV47').textContent=id?'✓ Проект обновлён':'✓ Проект создан';
      resetProjectFormV47();
      await loadProjectsV47();
    }catch(e){
      $('projectStatusV47').textContent='Ошибка: '+e.message;
    }finally{
      b.disabled=false; b.textContent=old;
    }
  };

  document.querySelectorAll('.v47ProjectTabs [data-pstatus]').forEach(b=>b.onclick=()=>{
    projectStatusFilterV47=b.dataset.pstatus;
    document.querySelectorAll('.v47ProjectTabs [data-pstatus]').forEach(x=>x.classList.toggle('active',x===b));
    renderProjectsV47();
  });

  function renderProjectsV47(){
    const box=document.getElementById('projectListV47');
    if(!box)return;
    const list=projectsV47.filter(p=>p.status===projectStatusFilterV47);

    if(!list.length){
      box.innerHTML='<div class="taskEmpty">Здесь пока нет проектов.</div>';
      return;
    }

    box.innerHTML=list.map(p=>{
      const total=Number(p.total_tasks||0), done=Number(p.done_tasks||0), active=Number(p.active_tasks||0);
      const percent=Number(p.progress_percent||0);
      const next=p.next_task;
      const overdue=Number(p.overdue_tasks||0);
      return `<div class="card v47ProjectCard" data-project-id="${Number(p.id)}">
        <div class="v47ProjectHead">
          <div>
            <div class="v47ProjectName">${pEsc(p.name)}</div>
            ${p.outcome?`<div class="v47Outcome">${pEsc(p.outcome)}</div>`:''}
          </div>
          <span class="badge">${p.status==='active'?'Активный':p.status==='paused'?'Пауза':'Завершён'}</span>
        </div>

        <div class="v47ProgressLine">
          <div class="v47ProgressBar"><i style="width:${Math.max(0,Math.min(100,percent))}%"></i></div>
          <b>${done} / ${total}</b>
        </div>

        <div class="v47ProjectMeta">
          <span>${percent}% выполнено</span>
          <span>${active} активных</span>
          ${overdue?`<span class="errorText">${overdue} просрочено</span>`:''}
          <span>${p.deadline?'Дедлайн '+pEsc(v47PrettyDate(p.deadline)):'Без дедлайна'}</span>
        </div>

        <div class="v47Next">
          <b>Следующее действие</b>
          <div>${next?pEsc(next.title):'Добавь первую маленькую задачу проекта.'}</div>
        </div>

        <div class="btnrow v47ProjectActions">
          <button class="btn secondary" data-p-open="${Number(p.id)}">Задачи проекта</button>
          <button class="btn secondary" data-p-next="${Number(p.id)}">+ Следующая задача</button>
          <button class="btn secondary" data-p-edit="${Number(p.id)}">Изменить</button>
          ${p.status==='active'?`<button class="btn secondary" data-p-status="${Number(p.id)}" data-status="paused">Пауза</button>`:''}
          ${p.status==='paused'?`<button class="btn secondary" data-p-status="${Number(p.id)}" data-status="active">Возобновить</button>`:''}
          ${p.status!=='done'?`<button class="btn secondary" data-p-status="${Number(p.id)}" data-status="done">Завершить</button>`:`<button class="btn secondary" data-p-status="${Number(p.id)}" data-status="active">Вернуть</button>`}
          <button class="btn ghost danger" data-p-delete="${Number(p.id)}">Удалить</button>
        </div>
      </div>`;
    }).join('');
  }

  const projectList=document.getElementById('projectListV47');
  if(projectList){
    projectList.addEventListener('click',async e=>{
      const open=e.target.dataset.pOpen;
      const next=e.target.dataset.pNext;
      const edit=e.target.dataset.pEdit;
      const st=e.target.dataset.pStatus;
      const del=e.target.dataset.pDelete;

      if(open){
        projectFilterV47=Number(open);
        const sel=document.getElementById('taskProjectFilterV47');
        if(sel)sel.value=String(projectFilterV47);
        taskView='active';
        openPanel('tasks'); renderTasks();
      }
      if(next){
        resetTaskForm();
        const s=document.getElementById('t_project'); if(s)s.value=String(Number(next));
        $('t_date').value=today();
        openPanel('tasks'); $('t_title').focus();
      }
      if(edit){
        const p=projectsV47.find(x=>Number(x.id)===Number(edit)); if(!p)return;
        $('p_id').value=p.id; $('p_name').value=p.name||''; $('p_outcome').value=p.outcome||''; $('p_deadline').value=p.deadline||'';
        $('saveProjectV47').textContent='Сохранить проект'; $('cancelProjectV47').hidden=false;
        window.scrollTo({top:0,behavior:'smooth'}); $('p_name').focus();
      }
      if(st){
        try{
          await projectEndpoint('status',{id:Number(st),status:e.target.dataset.status});
          await loadProjectsV47();
        }catch(err){ alert('Ошибка: '+err.message); }
      }
      if(del && confirm('Удалить проект? Сами задачи останутся, но станут без проекта.')){
        try{
          await projectEndpoint('delete',{id:Number(del)});
          if(projectFilterV47===Number(del)) projectFilterV47=0;
          await loadProjectsV47();
        }catch(err){ alert('Ошибка: '+err.message); }
      }
    });
  }

  // Make Projects accessible from mobile Tasks without another bottom-nav item.
  const tasksHero=document.querySelector('#tasks .hero');
  if(tasksHero){
    const p=document.createElement('div');
    p.className='v47HierarchyNote';
    p.innerHTML='<b>Логика:</b> цель проекта → проект → маленькие задачи, которые можно закрыть одной галочкой.';
    tasksHero.insertAdjacentElement('afterend',p);
  }

  // Initial load, including old tasks.
  loadProjectsV47();

  // If refreshAll/loadTasks later replaces task objects, the wrapped loadTasks restores project mapping.
})();