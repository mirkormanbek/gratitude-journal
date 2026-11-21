<?php
require __DIR__ . '/db.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="theme-color" content="#0b0b0c">
<title>Дневник роста</title>
<style>
:root{
  --bg:#0b0b0c;
  --sidebar:#111113;
  --card:#1c1c1e;
  --card2:#242426;
  --line:#343438;
  --text:#f5f5f7;
  --muted:#9b9ba1;
  --blue:#0a84ff;
  --blue2:#409cff;
  --danger:#ff453a;
  --green:#30d158;
  --shadow:0 10px 30px rgba(0,0,0,.22);
}
*{box-sizing:border-box}
html{background:var(--bg)}
body{
  margin:0;
  background:var(--bg);
  color:var(--text);
  font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","SF Pro Text","Inter",Arial,sans-serif;
  -webkit-font-smoothing:antialiased;
}
button,input,textarea,select{font:inherit}
button{touch-action:manipulation}
.app{min-height:100vh;display:grid;grid-template-columns:240px 1fr}
.sidebar{
  position:sticky;top:0;height:100vh;padding:22px 14px;
  background:var(--sidebar);border-right:1px solid #202024;
  display:flex;flex-direction:column;gap:14px
}
.brand{padding:8px 10px 16px}
.brand h1{font-size:21px;margin:0 0 5px;letter-spacing:-.02em}
.brand .sub{font-size:12px;color:var(--muted)}
.nav{display:flex;flex-direction:column;gap:5px}
.nav button{
  width:100%;display:flex;align-items:center;gap:10px;text-align:left;
  border:0;background:transparent;color:#cfcfd4;padding:11px 12px;border-radius:11px;
  cursor:pointer;font-weight:600
}
.nav button:hover{background:#1b1b1e}
.nav button.active{background:#242427;color:white}
.nav .dot{width:8px;height:8px;border-radius:50%;background:#5b5b61}
.nav button.active .dot{background:var(--blue);box-shadow:0 0 0 4px rgba(10,132,255,.12)}
.sideFooter{margin-top:auto;padding:10px}
.sideFooter a{display:block;color:var(--muted);text-decoration:none;font-size:14px}
.main{min-width:0}
.topbar{
  position:sticky;top:0;z-index:20;
  display:flex;align-items:center;justify-content:space-between;gap:14px;
  padding:16px 26px;background:rgba(11,11,12,.82);
  backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);
  border-bottom:1px solid rgba(255,255,255,.06)
}
.topbarTitle{font-weight:700;letter-spacing:-.01em}
.sync{display:flex;align-items:center;gap:7px;color:var(--muted);font-size:13px}
.sync i{width:7px;height:7px;border-radius:50%;background:var(--green);display:inline-block}
.content{max-width:1040px;margin:0 auto;padding:28px 28px 70px}
.panel{display:none}.panel.active{display:block}
.hero{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;margin-bottom:22px}
.hero h2{font-size:34px;line-height:1.05;letter-spacing:-.035em;margin:0 0 8px}
.hero p{margin:0;color:var(--muted);line-height:1.45}
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:14px}
.card{
  background:var(--card);border:1px solid #2a2a2e;border-radius:18px;
  padding:18px;box-shadow:var(--shadow)
}
.card.flat{box-shadow:none}
.span4{grid-column:span 4}.span6{grid-column:span 6}.span8{grid-column:span 8}.span12{grid-column:span 12}
.metric{min-height:122px}
.metricLabel{font-size:13px;color:var(--muted);margin-bottom:12px}
.metricValue{font-size:30px;font-weight:750;letter-spacing:-.03em}
.metricMeta{font-size:12px;color:var(--muted);margin-top:7px}
.quick{
  background:linear-gradient(180deg,#0a84ff,#0076e8);border:0;color:white;
  display:flex;flex-direction:column;justify-content:space-between;min-height:175px
}
.quick h3{font-size:25px;margin:0;letter-spacing:-.025em}
.quick p{color:rgba(255,255,255,.82);line-height:1.45}
.btnrow{display:flex;gap:9px;flex-wrap:wrap;margin-top:14px}
.btn{
  border:0;border-radius:12px;padding:11px 15px;font-weight:700;cursor:pointer;
  transition:.16s ease;display:inline-flex;align-items:center;justify-content:center;text-decoration:none
}
.btn:active{transform:scale(.985)}
.primary{background:var(--blue);color:white}.primary:hover{background:var(--blue2)}
.secondary{background:#2c2c2e;color:#f1f1f3}.secondary:hover{background:#36363a}
.danger{background:rgba(255,69,58,.14);color:#ff6961}
.ghost{background:transparent;color:var(--blue);padding-left:0;padding-right:0}
.sectionTitle{font-size:23px;letter-spacing:-.025em;margin:0 0 6px}
.sectionSub{font-size:14px;color:var(--muted);line-height:1.5;margin-bottom:18px}
.formCard{padding:0;overflow:hidden}
.field{padding:17px 18px;border-bottom:1px solid var(--line)}
.field:last-child{border-bottom:0}
label{display:block;font-size:14px;font-weight:650;margin-bottom:9px}
textarea,input[type="date"],input[type="number"],input[type="text"],select{
  width:100%;border:1px solid #3b3b40;background:#151517;color:var(--text);
  border-radius:12px;padding:12px 13px;outline:none
}
textarea{min-height:108px;resize:vertical;line-height:1.45}
input:focus,textarea:focus,select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(10,132,255,.14)}
input[type="range"]{width:100%;accent-color:var(--blue)}
.helper{font-size:12px;color:var(--muted);margin-top:7px;line-height:1.4}
.status{min-height:20px;margin-top:10px;color:var(--green);font-size:13px}
.history{margin-top:18px}
.entry{
  background:var(--card);border:1px solid #29292d;border-radius:16px;
  margin:10px 0;overflow:hidden
}
.entryHead{
  width:100%;display:flex;justify-content:space-between;gap:12px;align-items:center;
  border:0;background:transparent;color:white;padding:15px 17px;text-align:left;cursor:pointer
}
.entryHead:hover{background:#202023}
.entryTitle{font-weight:700}
.entryMeta{color:var(--muted);font-size:12px;margin-top:4px}
.chev{color:var(--muted);transition:.2s}
.entry.open .chev{transform:rotate(180deg)}
.entryBody{display:none;padding:0 17px 17px;border-top:1px solid var(--line)}
.entry.open .entryBody{display:block}
.entryBody p{line-height:1.5;margin:13px 0}
.entryBody b{color:#dcdce1}
.tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.tag{font-size:12px;padding:5px 8px;border-radius:999px;background:#2a2a2e;color:#cfcfd4}
.searchRow{display:grid;grid-template-columns:2fr 1fr;gap:10px}
.checks{display:flex;gap:10px;flex-wrap:wrap}
.checks label{font-weight:500;margin:0;display:flex;gap:7px;align-items:center;background:#202023;padding:9px 11px;border-radius:10px}
.checks input{width:auto}
.notice{border:1px solid rgba(10,132,255,.28);background:rgba(10,132,255,.08)}
.empty{color:var(--muted);font-size:14px;padding:8px 0}


.todayStats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}
.statChip{background:#151517;border:1px solid #29292d;border-radius:14px;padding:13px 14px;min-width:0}
.statChip b{display:block;font-size:17px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.statChip span{display:block;color:var(--muted);font-size:11px;margin-top:5px}
.todayCards{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.todayCard{position:relative;overflow:hidden;min-height:220px;display:flex;flex-direction:column;justify-content:space-between}
.todayCard::before{content:"";position:absolute;left:0;right:0;top:0;height:1px;background:linear-gradient(90deg,transparent,rgba(10,132,255,.75),transparent)}
.eyebrow{font-size:12px;color:var(--blue2);font-weight:750;text-transform:uppercase;letter-spacing:.08em;margin-bottom:8px}
.todayCard h3{font-size:27px;line-height:1.08;letter-spacing:-.03em;margin:0 0 10px}
.todayLead{color:#d7d7db;font-size:15px;line-height:1.55;max-width:62ch}
.todayMeta{color:var(--muted);font-size:12px;margin-top:10px}
.todayProgress{margin-top:14px;padding-top:12px;border-top:1px solid #303034}
.progressTop{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:12px}
.progressTop span{color:var(--muted)}.progressTop b{font-size:12px;color:#dcdce1}
.progressBar{height:5px;background:#2b2b2f;border-radius:999px;overflow:hidden;margin-top:8px}.progressBar i{display:block;height:100%;width:0;background:var(--blue);border-radius:inherit;transition:width .35s ease}
.progressSub{font-size:11px;color:var(--muted);margin-top:7px}
.readerColumn{max-width:760px;margin:0 auto}
.tafsirSummary{font-size:16px;line-height:1.65;background:#151517;border:1px solid #303034;border-radius:16px;padding:18px;white-space:pre-wrap}
.tafsirDetails{margin-top:12px;border:1px solid #303034;border-radius:14px;background:#151517;overflow:hidden}.tafsirDetails summary{cursor:pointer;padding:14px 16px;font-weight:700;color:#d9d9de;list-style:none}.tafsirDetails summary::-webkit-details-marker{display:none}.tafsirDetails summary::after{content:'⌄';float:right;color:var(--muted)}.tafsirDetails[open] summary::after{transform:rotate(180deg)}.tafsirFull{padding:0 16px 17px;color:#d0d0d5;line-height:1.65;white-space:pre-wrap}
.completedBadge{background:rgba(48,209,88,.13);color:#7ee596}
.cardFoot{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px}
.cardFoot .btn{white-space:nowrap}
.badge{font-size:11px;line-height:1;padding:7px 9px;border-radius:999px;background:#29292d;color:#b8b8be;white-space:nowrap}
.aiStrip{margin-top:14px;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:15px 17px}
.aiStripText b{display:block;margin-bottom:4px}.aiStripText span{font-size:13px;color:var(--muted)}
.loadingLine{color:var(--muted);font-size:14px;padding:8px 0}
.okText{color:var(--green)}.warnText{color:#ffd60a}.errorText{color:#ff6961}
.modal{position:fixed;z-index:120;inset:0;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(0,0,0,.66);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
.modal.open{display:flex}
.modalSheet{width:min(900px,100%);max-height:calc(100vh - 36px);background:#1b1b1d;border:1px solid #37373b;border-radius:24px;box-shadow:0 30px 100px rgba(0,0,0,.6);display:flex;flex-direction:column;overflow:hidden}
.modalHead{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;padding:20px 22px 16px;border-bottom:1px solid var(--line)}
.modalHead h2{font-size:27px;line-height:1.12;margin:4px 0 0;letter-spacing:-.03em}
.modalClose{border:0;background:#2b2b2f;color:#fff;width:36px;height:36px;border-radius:50%;font-size:22px;cursor:pointer;flex:0 0 auto}
.modalBody{padding:22px;overflow:auto;-webkit-overflow-scrolling:touch}
.readingIntro{font-size:15px;color:var(--muted);line-height:1.55;margin-bottom:18px}
.verseBlock{padding:18px 0;border-bottom:1px solid #303034}.verseBlock:first-child{padding-top:0}.verseBlock:last-child{border-bottom:0}
.verseKey{font-size:12px;color:var(--blue2);font-weight:750;margin-bottom:8px}
.quranArabic{font-family:"Geeza Pro","Noto Naskh Arabic","Times New Roman",serif;font-size:32px;line-height:1.85;text-align:right;direction:rtl;margin:5px 0 12px}
.translit{color:#b9b9bf;font-size:14px;line-height:1.55;margin-bottom:9px}
.translation{font-size:16px;line-height:1.62;color:#ededf0}
.sourceNote{font-size:12px;line-height:1.5;color:var(--muted);margin-top:8px}
.modalSection{margin-top:22px;padding-top:20px;border-top:1px solid var(--line)}
.modalSection h3{font-size:18px;margin:0 0 10px}
.modalSection textarea{min-height:82px}
.qmdbNotice{padding:14px 15px;border-radius:14px;background:#151517;border:1px solid #303034;color:#d9d9dd;line-height:1.55;font-size:14px}
.qmdbNotice a{color:var(--blue);text-decoration:none}
.story{font-size:17px;line-height:1.72;color:#ededf0;white-space:pre-wrap}
.storyCallout{margin:20px 0 0;padding:16px;border-radius:15px;background:#151517;border:1px solid #303034}
.storyCallout b{display:block;margin-bottom:7px}.storyCallout div{line-height:1.55}
.aiAsk{display:grid;grid-template-columns:1fr auto;gap:9px;margin-top:12px;align-items:end}.aiAsk textarea{min-height:58px}
.aiOutput{margin-top:14px;padding:16px;border-radius:14px;background:#151517;border:1px solid #303034;line-height:1.6;white-space:pre-wrap}
.analysisChoices{display:grid;grid-template-columns:1fr 1fr;gap:10px}.analysisChoices .btn{width:100%}
.privacyNote{font-size:12px;color:var(--muted);line-height:1.5;margin-top:12px}
body.modalOpen{overflow:hidden}
@media(max-width:900px){.todayStats{grid-template-columns:repeat(4,minmax(115px,1fr));overflow-x:auto;padding-bottom:2px}.todayCards{grid-template-columns:1fr}}
@media(max-width:700px){.todayCard{min-height:190px}.todayCard h3{font-size:24px}.aiStrip{align-items:flex-start;flex-direction:column}.aiStrip .btn{width:100%}.modal{padding:0;align-items:flex-end}.modalSheet{max-height:94vh;border-radius:22px 22px 0 0;border-bottom:0}.modalHead{padding:17px 17px 14px}.modalBody{padding:18px}.quranArabic{font-size:28px}.analysisChoices{grid-template-columns:1fr}.aiAsk{grid-template-columns:1fr}.aiAsk .btn{width:100%}}

.mobileNav{display:none}
@media(max-width:900px){
  .app{display:block}
  .sidebar{display:none}
  .topbar{padding:14px 16px}
  .content{padding:22px 14px calc(138px + env(safe-area-inset-bottom))}
  .hero h2{font-size:29px}
  .span4,.span6,.span8{grid-column:span 12}
  .searchRow{grid-template-columns:1fr}
  .mobileNav{
    display:grid;grid-template-columns:repeat(5,1fr);position:fixed;z-index:80;
    left:0;right:0;bottom:0;
    background:rgba(28,28,30,.96);backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);
    border:0;border-top:1px solid #343438;border-radius:18px 18px 0 0;
    padding:7px 8px calc(7px + env(safe-area-inset-bottom));box-shadow:0 -12px 40px rgba(0,0,0,.38)
  }
  .mobileNav button{border:0;background:transparent;color:#929298;border-radius:12px;padding:9px 3px;font-size:10px;font-weight:650}
  .mobileNav button.active{background:#2c2c2e;color:white}
  .mobileNav span{display:block;font-size:17px;margin-bottom:3px}
}
@media(max-width:560px){
  .hero{display:block}.hero .btn{margin-top:14px}
  .metricValue{font-size:26px}
  .card{border-radius:16px}
}

/* v4.5 — задачи */
.taskHome{
  margin:14px 0;display:grid;grid-template-columns:minmax(180px,.8fr) minmax(260px,1.6fr) auto;
  gap:16px;align-items:center;padding:16px 18px
}
.taskHomeMain b{display:block;font-size:20px;letter-spacing:-.02em;margin:2px 0 5px}
.taskHomeMain span{font-size:12px;color:var(--muted)}
.taskMiniList{display:grid;gap:7px}
.taskMini{
  display:flex;align-items:center;gap:9px;min-width:0;font-size:13px;color:#e7e7ea
}
.taskMini input{width:17px;height:17px;accent-color:var(--blue);flex:0 0 auto}
.taskMini span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.taskMini.overdue span{color:#ff8a84}
.taskTabs{display:flex;gap:8px;flex-wrap:wrap;margin:16px 0}
.taskTab{border:1px solid #36363b;background:#1b1b1e;color:#cfcfd4;border-radius:999px;padding:8px 12px;cursor:pointer;font-weight:700;font-size:13px}
.taskTab.active{background:var(--blue);border-color:var(--blue);color:#fff}
.taskFormGrid{display:grid;grid-template-columns:1fr 170px 150px;gap:10px;align-items:end}
.taskFormGrid label{margin-bottom:7px}
.taskList{display:grid;gap:9px}
.taskRow{
  display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:start;
  padding:14px 15px;border:1px solid #2e2e33;background:var(--card);border-radius:15px
}
.taskCheck{width:21px;height:21px;accent-color:var(--blue);margin-top:2px}
.taskTitle{font-size:15px;font-weight:700;line-height:1.4}
.taskRow.done .taskTitle{text-decoration:line-through;color:#8e8e94}
.taskMeta{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-top:6px;color:var(--muted);font-size:11px}
.taskPill{display:inline-flex;align-items:center;border-radius:999px;padding:4px 7px;background:#27272b;color:#b9b9c0}
.taskPill.high{background:rgba(255,69,58,.13);color:#ff8a84}
.taskPill.medium{background:rgba(255,214,10,.12);color:#f2d96b}
.taskPill.overdue{background:rgba(255,69,58,.13);color:#ff8a84}
.taskActions{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.taskSmall{border:0;background:#2a2a2e;color:#d5d5da;border-radius:9px;padding:7px 9px;cursor:pointer;font-size:11px;font-weight:700}
.taskSmall:hover{background:#36363a}
.taskSmall.danger{color:#ff8a84;background:rgba(255,69,58,.10)}
.taskPriority{width:auto;min-width:96px;padding:7px 8px;border-radius:9px;font-size:11px}
.taskGroupTitle{font-size:13px;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;font-weight:800;margin:18px 0 8px}
.taskHint{
  margin-top:9px;padding:10px 11px;border-radius:11px;background:rgba(10,132,255,.07);
  border:1px solid rgba(10,132,255,.16);font-size:12px;color:#b8cde5;line-height:1.45
}
.inlineCheck{display:flex;align-items:center;gap:8px;margin-top:10px;font-size:12px;color:var(--muted);font-weight:500}
.inlineCheck input{width:auto;accent-color:var(--blue)}
.taskEmpty{padding:22px 10px;text-align:center;color:var(--muted);font-size:14px}
@media(max-width:820px){
  .taskHome{grid-template-columns:1fr auto}
  .taskMiniList{grid-column:1/-1;order:3}
  .taskFormGrid{grid-template-columns:1fr 1fr}
  .taskFormGrid .taskTitleField{grid-column:1/-1}
  .taskRow{grid-template-columns:auto 1fr}
  .taskActions{grid-column:2;justify-content:flex-start}
}
@media(max-width:560px){
  .taskHome{grid-template-columns:1fr;gap:11px}
  .taskHome .btn{width:100%}
  .taskFormGrid{grid-template-columns:1fr}
  .taskFormGrid .taskTitleField{grid-column:auto}
  .taskActions{gap:5px}
  .taskSmall{padding:7px 8px}
}


/* v4.8 — clean task/project UX */
.linkBtn{border:0;background:transparent;color:var(--blue);padding:0;cursor:pointer;font-weight:750;font-size:13px}
.taskHomeV48{grid-template-columns:minmax(150px,.8fr) minmax(220px,1.6fr);padding:16px 18px}
.taskHomeFoot{grid-column:1/-1;border-top:1px solid var(--line);padding-top:11px;margin-top:2px}
.workSwitch{display:grid;grid-template-columns:1fr 1fr;gap:4px;padding:4px;background:#1b1b1e;border:1px solid #2e2e33;border-radius:14px;margin-bottom:14px;max-width:360px}
.workSwitchBtn{border:0;background:transparent;color:#929298;border-radius:10px;padding:9px 14px;font-weight:750;cursor:pointer}
.workSwitchBtn.active{background:#303034;color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.28)}
.taskDashboard{display:grid;grid-template-columns:repeat(3,minmax(0,1fr)) auto;gap:9px;margin-bottom:12px;align-items:stretch}
.miniMetric{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:12px 13px;min-width:0}
.miniMetric span{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.055em;color:var(--muted);font-weight:800}
.miniMetric b{display:block;font-size:22px;line-height:1.05;margin:5px 0 4px;letter-spacing:-.03em}.miniMetric small{display:block;color:var(--muted);font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.taskAddTop{align-self:stretch;min-width:116px}
.taskToolbarV48{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:8px 0 12px}.taskToolbarV48 .taskTabs{margin:0;min-width:0}.compactSelect{width:auto;max-width:220px;padding:8px 30px 8px 10px;font-size:12px}
.cleanTaskList{gap:8px}.cleanTaskList .taskRow{grid-template-columns:auto minmax(0,1fr) auto;padding:13px 14px;border-radius:14px}.cleanTaskList .taskTitle{font-size:14px}.cleanTaskList .taskMeta{margin-top:5px;gap:5px}.cleanTaskList .taskPill{font-size:10px;padding:3px 6px}
.taskMore{position:relative}.taskMore>summary{list-style:none;width:32px;height:32px;border-radius:9px;background:#29292d;display:grid;place-items:center;cursor:pointer;color:#c9c9cf;font-size:18px;line-height:1}.taskMore>summary::-webkit-details-marker{display:none}.taskMoreMenu{position:absolute;right:0;top:38px;z-index:15;width:245px;background:#242428;border:1px solid #3a3a40;border-radius:13px;padding:10px;box-shadow:0 14px 45px rgba(0,0,0,.45)}.taskMoreMenu label{font-size:10px;color:var(--muted);margin:7px 0 4px}.taskMoreMenu select{padding:7px 9px;font-size:11px}.taskMenuBtns{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px}.taskMenuBtns .taskSmall{width:100%}
.projectTop{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:5px 0 14px}.projectTop b{display:block;font-size:18px}.projectTop span{display:block;color:var(--muted);font-size:12px;margin-top:3px}.projectTabs{display:flex;gap:7px;margin-bottom:12px;overflow-x:auto}.projectList{display:grid;gap:10px}.projectCard{padding:16px}.projectHead{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.projectName{font-size:17px;font-weight:800;letter-spacing:-.02em}.projectOutcome{font-size:12px;color:#b4b4ba;line-height:1.45;margin-top:4px}.projectProgress{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;margin-top:13px}.projectBar{height:7px;background:#29292e;border-radius:999px;overflow:hidden}.projectBar i{display:block;height:100%;background:var(--blue);border-radius:999px}.projectProgress b{font-size:12px}.projectMeta{display:flex;gap:9px;flex-wrap:wrap;font-size:10px;color:var(--muted);margin-top:7px}.projectNext{margin-top:12px;padding:10px 11px;border-radius:11px;background:#151517;border:1px solid #2c2c31}.projectNext span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:800;margin-bottom:4px}.projectNext b{font-size:13px;line-height:1.4}.projectActions{display:flex;gap:7px;align-items:center;margin-top:11px}.projectActions .btn{padding:7px 10px;font-size:11px}.projectMore{margin-left:auto;position:relative}.projectMore>summary{list-style:none;width:32px;height:32px;border-radius:9px;background:#29292d;display:grid;place-items:center;cursor:pointer;font-size:18px}.projectMore>summary::-webkit-details-marker{display:none}.projectMoreMenu{position:absolute;right:0;top:38px;z-index:15;width:170px;background:#242428;border:1px solid #3a3a40;border-radius:12px;padding:8px;box-shadow:0 14px 40px rgba(0,0,0,.45)}.projectMoreMenu .taskSmall{width:100%;text-align:left;margin:3px 0}
.smallSheet{width:min(560px,100%)}.twoFields{display:grid;grid-template-columns:1fr 1fr;gap:10px}.projectRule{font-size:12px;line-height:1.45;color:#bad0e8;background:rgba(10,132,255,.07);border:1px solid rgba(10,132,255,.16);padding:10px 12px;border-radius:11px;margin:4px 0 16px}.mutedInline{color:var(--muted);font-weight:500}.projectPill{background:rgba(10,132,255,.11)!important;color:#7dbbff!important}.miniGroup{font-size:9px;text-transform:uppercase;letter-spacing:.07em;font-weight:800;color:var(--muted);margin:6px 0 4px}.miniGroup.tomorrow{color:#65aaff}.miniMore{font-size:10px;color:var(--muted);padding-left:26px}.taskEmptySlim{padding:24px 8px;text-align:center;color:var(--muted);font-size:13px}
@media(max-width:820px){.taskDashboard{grid-template-columns:repeat(3,1fr)}.taskAddTop{grid-column:1/-1;width:100%;min-height:42px}.taskToolbarV48{display:block}.taskToolbarV48 .compactSelect{width:100%;max-width:none;margin-top:10px}.taskHomeV48{grid-template-columns:1fr}.taskHomeFoot{grid-column:auto}.projectTop{align-items:flex-end}}
@media(max-width:560px){.compactHero{margin-bottom:12px}.workSwitch{max-width:none}.taskDashboard{gap:6px}.miniMetric{padding:10px}.miniMetric b{font-size:20px}.miniMetric small{font-size:9px}.taskTabs{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none}.taskTabs::-webkit-scrollbar,.projectTabs::-webkit-scrollbar{display:none}.taskTab{white-space:nowrap;flex:0 0 auto}.cleanTaskList .taskRow{padding:12px}.taskMoreMenu{position:fixed;left:14px;right:14px;bottom:82px;top:auto;width:auto}.projectHead{display:block}.projectHead .badge{display:inline-block;margin-top:8px}.projectActions{flex-wrap:wrap}.projectMoreMenu{position:fixed;left:14px;right:14px;bottom:82px;top:auto;width:auto}.twoFields{grid-template-columns:1fr}.taskHomeV48{padding:14px}.taskHomeMain b{font-size:18px}}


/* ===== Growth OS v5 ===== */
.osSectionTitle{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em;font-weight:800;margin:2px 0 10px}
.serviceGrid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.serviceTile{
  min-height:142px;background:var(--card);border:1px solid #2d2d31;border-radius:18px;padding:16px;
  display:flex;flex-direction:column;justify-content:space-between;cursor:pointer;transition:.16s ease;color:var(--text);text-align:left
}
.serviceTile:hover{transform:translateY(-1px);border-color:#3b3b41;background:#202023}
.serviceIcon{font-size:24px;margin-bottom:16px}.serviceTile b{font-size:16px;letter-spacing:-.015em}
.serviceTile span{display:block;color:var(--muted);font-size:12px;line-height:1.4;margin-top:5px}
.hubGrid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.hubCard{min-height:170px;display:flex;flex-direction:column;justify-content:space-between}
.hubCard h3{font-size:23px;margin:0 0 7px;letter-spacing:-.025em}.hubCard p{color:var(--muted);line-height:1.45;margin:0}
.progressGrid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}
.progressCard{background:var(--card);border:1px solid #2d2d31;border-radius:16px;padding:14px;min-width:0}
.progressCard span{display:block;color:var(--muted);font-size:11px}
.progressCard b{display:block;font-size:24px;letter-spacing:-.03em;margin:7px 0 4px}
.progressCard small{color:var(--muted);font-size:10px;line-height:1.35}
.progressWide{margin-top:14px}
.planAgenda{display:grid;grid-template-columns:repeat(7,minmax(92px,1fr));gap:8px;margin:12px 0 4px;overflow-x:auto;padding:1px 1px 7px;scrollbar-width:none}
.planAgenda::-webkit-scrollbar{display:none}
.agendaDay{display:block;min-height:108px;padding:11px;border:1px solid #2d2d31;border-radius:14px;background:#151517}
.agendaDay.today{border-color:rgba(10,132,255,.55);background:rgba(10,132,255,.07)}
.agendaDate b{display:block;font-size:12px;white-space:nowrap}.agendaDate span{display:block;color:var(--muted);font-size:10px;margin-top:3px}
.agendaItems{display:grid;gap:6px;margin-top:12px}
.agendaCompactLine{font-size:11px;line-height:1.3;color:#d7d7dc;display:flex;align-items:center;gap:5px}
.agendaCompactLine.muted{color:var(--muted)}
.agendaDot{width:6px;height:6px;border-radius:50%;background:var(--blue);flex:0 0 auto}.agendaDot.workout{background:#30d158}.agendaDot.overdue{background:#ff6961}
.agendaEmpty{color:var(--muted);font-size:11px}
.workoutHome{margin:14px 0;padding:16px 18px;display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center}
.workoutHome b{display:block;font-size:18px;letter-spacing:-.02em}.workoutHome span{display:block;font-size:12px;color:var(--muted);margin-top:5px}
.workoutTop{display:grid;grid-template-columns:1.4fr .8fr;gap:14px;margin-bottom:14px}
.workoutNext h3{font-size:26px;margin:0 0 7px}.workoutNext p{color:var(--muted);margin:0;line-height:1.45}
.scheduleRow{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:14px}.scheduleRow .btn{white-space:nowrap}
.programDays{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.programDay{padding:16px}.programDay h3{font-size:18px;margin:0 0 6px}.programDay .dayMeta{font-size:11px;color:var(--muted);margin-bottom:12px}
.exerciseMini{font-size:12px;line-height:1.4;padding:7px 0;border-top:1px solid #2b2b30}.exerciseMini:first-of-type{border-top:0}
.workoutHistory{display:grid;gap:8px}.workoutHistoryRow{display:flex;justify-content:space-between;gap:12px;padding:12px 13px;border:1px solid #2d2d31;border-radius:13px;background:#151517}
.workoutHistoryRow b{font-size:13px}.workoutHistoryRow span{color:var(--muted);font-size:11px}
.workoutSessionSheet{width:min(980px,100%)}
.workoutSessionHead{position:sticky;top:0;z-index:5;background:#1b1b1d;border-bottom:1px solid var(--line);padding:14px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.restTimer{font-size:12px;color:var(--muted)}.restTimer b{color:#fff;font-size:18px;margin-left:5px}
.sessionExercise{border:1px solid #303034;border-radius:16px;background:#151517;margin-bottom:12px;overflow:hidden}
.sessionExerciseHead{padding:14px 15px;border-bottom:1px solid #29292e}.sessionExerciseHead b{font-size:15px}.sessionExerciseHead span{display:block;color:var(--muted);font-size:11px;margin-top:4px}
.setRows{padding:8px}.setRow{display:grid;grid-template-columns:42px 1fr 1fr 44px;gap:8px;align-items:center;padding:7px;border-radius:10px}
.setRow.done{background:rgba(48,209,88,.08)}.setNo{font-size:12px;color:var(--muted);text-align:center}
.setInput{width:100%;border:1px solid #38383d;background:#101012;color:#fff;border-radius:9px;padding:9px 8px;text-align:center}
.setDoneBtn{width:40px;height:40px;border:0;border-radius:10px;background:#29292d;color:#fff;font-weight:800;cursor:pointer}.setRow.done .setDoneBtn{background:rgba(48,209,88,.18);color:#7ee596}
.prevHint{font-size:10px;color:var(--muted);padding:0 15px 12px}
.serviceBadge{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:var(--muted)}
.serviceBadge i{width:6px;height:6px;border-radius:50%;background:var(--green)}
.workoutScheduleCard,.workoutCalendarCard{margin-top:14px}
.workoutCardHead{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.workoutCardHead h3{margin:0;font-size:18px}.workoutCardHead span{display:block;color:var(--muted);font-size:11px;margin-top:4px}
.weekSchedule{display:grid;grid-template-columns:repeat(7,1fr);gap:7px}.weekSlot{min-height:72px;padding:9px;border-radius:12px;background:#151517;border:1px solid #2d2d31;text-align:center}.weekSlot b{display:block;font-size:11px}.weekSlot span{display:block;color:var(--muted);font-size:10px;margin-top:7px}.weekSlot.training{border-color:rgba(10,132,255,.32);background:rgba(10,132,255,.07)}.weekSlot.training span{color:#8fc4ff;font-weight:700}
.calendarToolbar{display:grid;grid-template-columns:40px 1fr 40px;gap:8px;align-items:center;margin-bottom:11px}.calendarToolbar b{text-align:center;font-size:16px;text-transform:capitalize}.calendarNav{width:38px;height:36px;border:0;border-radius:10px;background:#29292d;color:#fff;cursor:pointer;font-size:20px}
.calendarWeekdays,.workoutCalendar{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}.calendarWeekdays div{text-align:center;color:var(--muted);font-size:9px;font-weight:800;text-transform:uppercase;padding:3px}.calendarCell{position:relative;min-height:72px;padding:8px;border-radius:11px;background:#151517;border:1px solid #29292e;cursor:default}.calendarCell.outside{opacity:.35}.calendarCell.today{box-shadow:inset 0 0 0 1px var(--blue)}.calendarCell.hasWorkout{cursor:pointer}.calendarCell.selected{border-color:var(--blue);background:rgba(10,132,255,.09)}.calendarNum{font-size:11px;color:#d9d9dd}.calendarWorkout{margin-top:9px;padding:5px 6px;border-radius:8px;font-size:9px;font-weight:750;line-height:1.2;background:rgba(10,132,255,.13);color:#82bfff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.calendarWorkout.done{background:rgba(48,209,88,.11);color:#75df92}.calendarWorkout.overdue{background:rgba(255,69,58,.11);color:#ff8c86}.calendarWorkout.in_progress{background:rgba(255,214,10,.11);color:#f1d75f}
.workoutSelected{margin-top:13px;padding:13px;border-radius:13px;background:#151517;border:1px solid #303034}.workoutSelectedTop{display:flex;justify-content:space-between;gap:12px;align-items:start}.workoutSelectedTop b{font-size:14px}.workoutSelectedTop span{display:block;color:var(--muted);font-size:11px;margin-top:4px}.workoutSelectedActions{display:flex;gap:7px;flex-wrap:wrap;margin-top:11px}.moveWorkoutRow{display:grid;grid-template-columns:1fr auto;gap:7px;max-width:360px}.workoutSelected .danger{margin-left:auto}
.scheduleEditorGrid{display:grid;gap:11px}.scheduleEditorRow{display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #2d2d31}.scheduleEditorRow:last-child{border-bottom:0}.scheduleEditorRow b{font-size:14px}.scheduleEditorRow select{padding:10px}
.workoutHistoryRow{align-items:center}.workoutHistoryActions{display:flex;align-items:center;gap:8px}.workoutDeleteSmall{border:0;background:rgba(255,69,58,.10);color:#ff8a84;border-radius:9px;padding:7px 9px;font-size:11px;font-weight:700;cursor:pointer}

@media(max-width:900px){
  .serviceGrid{grid-template-columns:repeat(2,1fr)}
  .progressGrid{grid-template-columns:repeat(2,1fr)}
  .workoutTop{grid-template-columns:1fr}
  .programDays{grid-template-columns:1fr}
}
@media(max-width:700px){
  .hubGrid{grid-template-columns:1fr}
  .workoutHome{grid-template-columns:1fr}.workoutHome .btn{width:100%}
  .planAgenda{grid-template-columns:repeat(7,88px)}
  .calendarCell{min-height:60px;padding:6px}.calendarWorkout{font-size:8px;padding:4px}.weekSchedule{grid-template-columns:repeat(7,82px);overflow-x:auto;padding-bottom:4px}.workoutSelectedActions{display:grid;grid-template-columns:1fr 1fr}.workoutSelectedActions .btn{width:100%}
}
@media(max-width:560px){
  .serviceGrid{grid-template-columns:1fr 1fr;gap:8px}
  .serviceTile{min-height:122px;padding:13px}.serviceIcon{font-size:21px;margin-bottom:10px}
  .serviceTile b{font-size:14px}.serviceTile span{font-size:10px}
  .progressGrid{grid-template-columns:1fr 1fr}
  .setRow{grid-template-columns:36px 1fr 1fr 40px;gap:6px;padding:5px}
}

</style>
<link rel="stylesheet" href="assets/ui.css?v=<?=@filemtime(__DIR__.'/assets/ui.css')?>">
<link rel="stylesheet" href="assets/notes.css?v=<?=@filemtime(__DIR__.'/assets/notes.css')?>">
<link rel="stylesheet" href="assets/projects.css?v=<?=@filemtime(__DIR__.'/assets/projects.css')?>">
<link rel="stylesheet" href="assets/workouts.css?v=<?=@filemtime(__DIR__.'/assets/workouts.css')?>">
</head>
<body>
<div class="app">

<aside class="sidebar">
  <div class="brand">
    <h1>Дневник роста</h1>
    <div class="sub">Личная система развития</div>
  </div>
  <nav class="nav">
    <button class="navbtn active" data-panel="home"><span class="dot"></span>Сегодня</button>
    <button class="navbtn" data-panel="plan"><span class="dot"></span>План</button>
    <button class="navbtn" data-panel="journalHub"><span class="dot"></span>Дневник</button>
    <button class="navbtn" data-panel="progress"><span class="dot"></span>Прогресс</button>
    <button class="navbtn" data-panel="services"><span class="dot"></span>Сервисы</button>
  </nav>
  <div class="sideFooter"><a href="logout.php">Выйти из аккаунта</a></div>
</aside>

<main class="main">
  <div class="topbar">
    <div class="topbarTitle" id="topbarTitle">Сегодня</div>
    <div class="sync"><i></i> синхронизация включена</div>
  </div>

  <div class="content">

    <section id="home" class="panel active">
      <div class="hero">
        <div>
          <h2 id="greeting">Добрый вечер</h2>
          <p id="todayLabel">Твоя система на сегодня.</p>
        </div>
        <button class="btn primary goto" data-panel="daily">Заполнить день</button>
      </div>

      <div class="todayStats">
        <div class="statChip"><b id="homeDailyState">—</b><span id="homeDailyMeta">Дневник</span></div>
        <div class="statChip"><b id="homeMood">—</b><span>Состояние /10</span></div>
        <div class="statChip"><b id="homeWeeklyCount">0</b><span>CEO Review</span></div>
        <div class="statChip"><b id="homeKnowledgeCount">0</b><span>Заметки</span></div>
      </div>


      <div class="card taskHome flat taskHomeV48" id="taskHomeCard">
        <div class="taskHomeMain">
          <div class="eyebrow">Задачи</div>
          <b id="taskHomeTitle">Загружаю…</b>
          <span id="taskHomeMeta">Сегодня и завтра</span>
        </div>
        <div class="taskMiniList" id="taskHomeList"></div>
        <div class="taskHomeFoot"><button class="linkBtn goto" data-panel="plan">Открыть план →</button></div>
      </div>


      <div class="card flat workoutHome" id="workoutHomeCard">
        <div>
          <div class="eyebrow">Тренировка</div>
          <b id="workoutHomeTitle">Загружаю следующую тренировку…</b>
          <span id="workoutHomeMeta">Программа 3 раза в неделю</span>
        </div>
        <button class="btn secondary goto" data-panel="workouts" id="workoutHomeBtn">Открыть тренировки</button>
      </div>

      <div class="todayCards">
        <div class="card todayCard" id="quranCard">
          <div>
            <div class="eyebrow">Коран сегодня</div>
            <h3 id="quranTitle">Готовлю чтение…</h3>
            <div class="loadingLine" id="quranLoading">Подбираю последовательный отрывок на 5–10 минут.</div>
            <div id="quranPreview" hidden>
              <div class="todayLead" id="quranLead"></div>
              <div class="todayMeta" id="quranMeta"></div>
              <div class="todayProgress">
                <div class="progressTop"><span>Прогресс Корана</span><b id="quranProgressText">—</b></div>
                <div class="progressBar"><i id="quranProgressBar"></i></div>
                <div class="progressSub" id="quranProgressSub">Загружаю прогресс…</div>
              </div>
            </div>
          </div>
          <div class="cardFoot">
            <span class="badge" id="quranBadge">Quran Foundation</span>
            <button class="btn primary" id="openQuran" hidden>Начать чтение</button>
          </div>
        </div>

        <div class="card todayCard" id="personCard">
          <div>
            <div class="eyebrow">Человек дня</div>
            <h3 id="personName">Подбираю человека…</h3>
            <div class="loadingLine" id="personLoading">Готовлю интересную историю о человеке и его вкладе.</div>
            <div id="personPreview" hidden>
              <div class="todayLead" id="personTeaser"></div>
              <div class="todayMeta" id="personMeta"></div>
              <div class="todayProgress">
                <div class="progressTop"><span>Прогресс</span><b id="personProgressText">—</b></div>
                <div class="progressSub" id="personProgressSub">Загружаю прогресс…</div>
              </div>
            </div>
          </div>
          <div class="cardFoot">
            <span class="badge">История дня</span>
            <button class="btn secondary" id="openPerson" hidden>Прочитать</button>
          </div>
        </div>
      </div>

      <div class="card aiStrip flat">
        <div class="aiStripText"><b>AI-разбор записей</b><span>Только выбранные тобой данные и только после нажатия.</span></div>
        <button class="btn secondary" id="openAnalysis">Разобрать мои записи</button>
      </div>
    </section>


    <section id="plan" class="panel">
      <div class="hero compactHero">
        <div>
          <h2>План</h2>
          <p>Ближайшие дни, задачи, проекты и тренировки — в одном месте.</p>
        </div>
      </div>


      <div class="card flat">
        <div class="osSectionTitle">Ближайшие 7 дней</div>
        <div id="planAgenda" class="planAgenda"><div class="empty">Загружаю план…</div></div>
      </div>

      <div class="workSwitch" role="tablist" aria-label="Задачи и проекты">
        <button class="workSwitchBtn active" data-workspace="tasks">Задачи</button>
        <button class="workSwitchBtn" data-workspace="projects">Проекты</button>
      </div>

      <div id="tasksWorkspace">
        <div class="taskDashboard">
          <div class="miniMetric"><span>Сегодня</span><b id="t_today">0</b><small id="t_today_meta">0/0 выполнено</small></div>
          <div class="miniMetric"><span>Завтра</span><b id="t_tomorrow">0</b><small>запланировано</small></div>
          <div class="miniMetric"><span>Просрочено</span><b id="t_overdue">0</b><small>требуют решения</small></div>
          <button class="btn primary taskAddTop" id="openTaskModal">+ Задача</button>
        </div>

        <div class="taskToolbarV48">
          <div class="taskTabs">
            <button class="taskTab active" data-task-view="today">Сегодня</button>
            <button class="taskTab" data-task-view="tomorrow">Завтра</button>
            <button class="taskTab" data-task-view="overdue">Просрочено</button>
            <button class="taskTab" data-task-view="active">Все</button>
            <button class="taskTab" data-task-view="done">Готово</button>
          </div>
          <select id="taskProjectFilter" class="compactSelect" aria-label="Фильтр по проекту"><option value="0">Все проекты</option></select>
        </div>
        <div id="taskList" class="taskList cleanTaskList"></div>
      </div>

      <div id="projectsWorkspace" hidden>
        <div class="projectTop">
          <div>
            <b>Активные проекты</b>
            <span>Большой результат → несколько конкретных действий.</span>
          </div>
          <button class="btn primary" id="openProjectModal">+ Проект</button>
        </div>
        <div class="projectTabs">
          <button class="taskTab active" data-project-view="active">Активные</button>
          <button class="taskTab" data-project-view="paused">Пауза</button>
          <button class="taskTab" data-project-view="done">Завершённые</button>
        </div>
        <div id="projectList" class="projectList"></div>
      </div>
    </section>


    <section id="journalHub" class="panel">
      <div class="hero">
        <div><h2>Дневник</h2><p>Осмысление дня и еженедельный взгляд на бизнес.</p></div>
      </div>
      <div class="hubGrid">
        <div class="card hubCard">
          <div><div class="eyebrow">Ежедневник</div><h3 id="journalHubDailyTitle">Сегодня</h3><p id="journalHubDailyMeta">Вечерняя фиксация состояния, благодарности и фокуса.</p></div>
          <button class="btn primary goto" data-panel="daily">Открыть ежедневник</button>
        </div>
        <div class="card hubCard">
          <div><div class="eyebrow">CEO Review</div><h3 id="journalHubWeeklyTitle">Еженедельный обзор</h3><p id="journalHubWeeklyMeta">Деньги, результат, потери фокуса и рычаг ×2.</p></div>
          <button class="btn secondary goto" data-panel="weekly">Открыть CEO Review</button>
        </div>
      </div>
    </section>

    <section id="progress" class="panel">
      <div class="hero"><div><h2>Прогресс</h2><p>Не отдельные записи, а движение по ключевым направлениям.</p></div></div>
      <div class="progressGrid">
        <div class="progressCard"><span>Задачи · 7 дней</span><b id="pgTasks">—</b><small id="pgTasksMeta">выполнение</small></div>
        <div class="progressCard"><span>Коран</span><b id="pgQuran">—</b><small id="pgQuranMeta">прочитано</small></div>
        <div class="progressCard"><span>Люди</span><b id="pgPeople">—</b><small id="pgPeopleMeta">историй</small></div>
        <div class="progressCard"><span>Тренировки · 4 недели</span><b id="pgWorkouts">—</b><small id="pgWorkoutsMeta">по плану</small></div>
        <div class="progressCard"><span>Дневник · 7 дней</span><b id="pgJournal">—</b><small id="pgJournalMeta">дней заполнено</small></div>
      </div>
      <div class="card flat progressWide">
        <div class="osSectionTitle">Система</div>
        <div id="progressNarrative" class="sectionSub" style="margin:0">Здесь будет собираться общий ритм: выполнение задач, чтение, дневник и тренировки.</div>
      </div>
    </section>

    <section id="services" class="panel">
      <div class="hero"><div><h2>Сервисы</h2><p>Все инструменты Growth OS. Нижняя навигация больше не расширяется.</p></div></div>
      <div class="serviceGrid">
        <button class="serviceTile goto" data-panel="plan"><div><div class="serviceIcon">✅</div><b>Задачи и проекты</b><span>Планирование, дедлайны и следующие действия.</span></div></button>
        <button class="serviceTile goto" data-panel="journalHub"><div><div class="serviceIcon">✎</div><b>Дневник</b><span>Ежедневник и CEO Review в одном месте.</span></div></button>
        <button class="serviceTile goto" data-panel="workouts"><div><div class="serviceIcon">🏋️</div><b>Тренировки</b><span>Программа, подходы, веса и история.</span></div></button>
        <button class="serviceTile serviceAction" data-service="quran"><div><div class="serviceIcon">📖</div><b>Коран</b><span>Чтение на 5–10 минут и прогресс.</span></div></button>
        <button class="serviceTile serviceAction" data-service="person"><div><div class="serviceIcon">👤</div><b>Человек дня</b><span>Истории людей и их вклад в мир.</span></div></button>
        <button class="serviceTile goto" data-panel="knowledge"><div><div class="serviceIcon">📝</div><b>Заметки</b><span>Мысли, идеи и база знаний.</span></div></button>
        <button class="serviceTile goto" data-panel="capital"><div><div class="serviceIcon">₸</div><b>Капитал</b><span>Ликвидность, обязательства и ресурс.</span></div></button>
        <button class="serviceTile goto" data-panel="weekly"><div><div class="serviceIcon">📊</div><b>CEO Review</b><span>Еженедельный бизнес-разбор.</span></div></button>
        <button class="serviceTile serviceAction" data-service="ai"><div><div class="serviceIcon">✨</div><b>AI-разбор</b><span>Разбор выбранных записей по запросу.</span></div></button>
        <button class="serviceTile goto" data-panel="exportPanel"><div><div class="serviceIcon">⇩</div><b>Экспорт</b><span>TXT для анализа и JSON backup.</span></div></button>
      </div>
    </section>

    <section id="workouts" class="panel"><div id="wkRoot"></div></section>

    <section id="daily" class="panel">
      <div class="hero"><div><h2>Ежедневник</h2><p>Короткая вечерняя фиксация: вера, выводы, фокус, действие.</p></div></div>
      <div class="card formCard">
        <div class="field"><label>Дата дня, который подводишь итог</label><input id="d_date" type="date"></div>
        <div class="field"><label>1. За что я благодарен Аллаху сегодня?</label><textarea id="d_allah" placeholder="Альхамдулиллях за..."></textarea></div>
        <div class="field"><label>2. За что я благодарен себе сегодня?</label><textarea id="d_self"></textarea></div>
        <div class="field"><label>3. Что я сегодня понял?</label><textarea id="d_lesson"></textarea></div>
        <div class="field"><label>4. Что я доверяю Аллаху и отпускаю?</label><textarea id="d_trust"></textarea></div>
        <div class="field">
          <label>5. Задачи на завтра</label>
          <textarea id="d_action" placeholder="Каждая строка = отдельная задача&#10;Проверить поставку&#10;Позвонить Шафику&#10;Подготовить отгрузку WB"></textarea>
          <div class="taskHint" id="dailyTaskHint">Каждая непустая строка станет отдельной задачей на следующий день.</div>
          <label class="inlineCheck"><input id="d_sync_tasks" type="checkbox" checked> Создать / обновить задачи на завтра</label>
        </div>
        <div class="field"><label>6. Что сегодня приблизило меня к цели 300 млн ₸?</label><textarea id="d_money"></textarea></div>
        <div class="field"><label>7. Где сегодня я потерял время или фокус?</label><textarea id="d_focus"></textarea></div>
        <div class="field"><label>8. Как я себя чувствую? <span id="moodVal">7</span>/10</label><input id="d_mood" type="range" min="1" max="10" value="7"></div>
      </div>
      <div class="btnrow"><button class="btn primary" id="saveDaily">Сохранить день</button><button class="btn secondary" id="resetDaily">Новая запись</button></div>
      <div class="status" id="dailyStatus"></div>
      <div class="history"><h3 class="sectionTitle">История</h3><div id="dailyHistory"></div></div>
    </section>

    <section id="weekly" class="panel">
      <div class="hero"><div><h2>CEO Review</h2><p>Раз в неделю — деньги, результат, потери фокуса и следующий рычаг.</p></div></div>
      <div class="grid" style="margin-bottom:16px">
        <div class="card metric span4"><div class="metricLabel">Обзоров</div><div class="metricValue" id="w_count">0</div></div>
        <div class="card metric span4"><div class="metricLabel">Всего за записи</div><div class="metricValue" id="w_total">0 ₸</div></div>
        <div class="card metric span4"><div class="metricLabel">Среднее</div><div class="metricValue" id="w_avg">0 ₸</div></div>
      </div>
      <div class="card formCard">
        <div class="field"><label>Дата CEO Review</label><input id="w_date" type="date"></div>
        <div class="field"><label>1. Сколько денег я заработал за неделю, ₸?</label><input id="w_money" type="number"></div>
        <div class="field"><label>2. Что принесло больше всего денег?</label><textarea id="w_best"></textarea></div>
        <div class="field"><label>3. Что съело деньги?</label><textarea id="w_leaks"></textarea></div>
        <div class="field"><label>4. Какие 3 действия дали наибольший результат?</label><textarea id="w_actions"></textarea></div>
        <div class="field"><label>5. Где я потерял время или фокус?</label><textarea id="w_time"></textarea></div>
        <div class="field"><label>6. Какой один рычаг может дать ×2?</label><textarea id="w_lever"></textarea></div>
        <div class="field"><label>7. Главная цель следующей недели</label><textarea id="w_goal"></textarea></div>
        <div class="field"><label>8. Три главные денежные задачи</label><textarea id="w_tasks"></textarea></div>
      </div>
      <div class="btnrow"><button class="btn primary" id="saveWeekly">Сохранить CEO Review</button><button class="btn secondary" id="resetWeekly">Новая запись</button></div>
      <div class="status" id="weeklyStatus"></div>
      <div class="history"><h3 class="sectionTitle">История</h3><div id="weeklyHistory"></div></div>
    </section>

    <section id="knowledge" class="panel"><div id="ntRoot"></div></section>

    <section id="capital" class="panel">
      <div class="hero"><div><h2>Капитал</h2><p>Реальная ликвидность, обязательства и доступный ресурс.</p></div></div>
      <div class="grid" style="margin-bottom:16px">
        <div class="card metric span4"><div class="metricLabel">Свободная ликвидность</div><div class="metricValue" id="c_liq">—</div></div>
        <div class="card metric span4"><div class="metricLabel">Обязательства</div><div class="metricValue" id="c_obl">—</div></div>
        <div class="card metric span4"><div class="metricLabel">Капитал</div><div class="metricValue" id="c_total">—</div></div>
      </div>
      <div class="card formCard">
        <div class="field"><label>Дата</label><input id="c_date" type="date"></div>
        <div class="field"><label>Деньги на счетах / наличные, ₸</label><input id="c_cash" type="number"></div>
        <div class="field"><label>Ожидаемые выплаты WB, ₸</label><input id="c_wb" type="number"></div>
        <div class="field"><label>Себестоимость товарных остатков, ₸</label><input id="c_stock" type="number"></div>
        <div class="field"><label>Долги поставщикам, ₸</label><input id="c_suppliers" type="number"></div>
        <div class="field"><label>Налоги, ₸</label><input id="c_taxes" type="number"></div>
        <div class="field"><label>Прочие обязательства, ₸</label><input id="c_other" type="number"></div>
        <div class="field"><label>Ближайшие закупки, ₸</label><input id="c_purchases" type="number"></div>
        <div class="field"><label>Резерв, который нельзя трогать, ₸</label><input id="c_reserve" type="number"></div>
        <div class="field"><label>Комментарий</label><textarea id="c_notes"></textarea></div>
      </div>
      <div class="btnrow"><button class="btn primary" id="saveCapital">Сохранить капитал</button><button class="btn secondary" id="resetCapital">Новая запись</button></div>
      <div class="status" id="capitalStatus"></div>
      <div class="history"><h3 class="sectionTitle">История</h3><div id="capitalHistory"></div></div>
    </section>

    <section id="exportPanel" class="panel">
      <div class="hero"><div><h2>Экспорт</h2><p>Сформируй отчёт для ChatGPT или резервную копию данных.</p></div></div>
      <div class="card formCard">
        <div class="field">
          <div class="searchRow">
            <div><label>С даты</label><input id="ex_from" type="date"></div>
            <div><label>По дату</label><input id="ex_to" type="date"></div>
          </div>
        </div>
        <div class="field">
          <label>Разделы</label>
          <div class="checks">
            <label><input type="checkbox" class="ex_section" value="daily" checked> Ежедневник</label>
            <label><input type="checkbox" class="ex_section" value="weekly" checked> CEO Review</label>
            <label><input type="checkbox" class="ex_section" value="knowledge" checked> Знания</label>
            <label><input type="checkbox" class="ex_section" value="capital" checked> Капитал</label>
          </div>
        </div>
      </div>
      <div class="btnrow">
        <button class="btn primary" id="exportText">Отчёт для ChatGPT</button>
        <button class="btn secondary" id="exportJson">Резервная копия JSON</button>
      </div>
      <div class="card notice flat" style="margin-top:18px">
        <b>TXT</b> удобен для анализа динамики со мной. <b>JSON</b> — для резервного хранения структуры данных.
      </div>
    </section>

  </div>
</main>
</div>


<div class="modal" id="taskModal" aria-hidden="true">
  <div class="modalSheet smallSheet" role="dialog" aria-modal="true" aria-labelledby="taskModalTitle">
    <div class="modalHead">
      <div><div class="eyebrow">Задача</div><h2 id="taskModalTitle">Новое действие</h2></div>
      <button class="modalClose" data-close="taskModal" aria-label="Закрыть">×</button>
    </div>
    <div class="modalBody">
      <input type="hidden" id="t_id">
      <div class="field"><label>Что нужно сделать?</label><input id="t_title" maxlength="500" placeholder="Конкретное действие, которое можно закрыть галочкой"></div>
      <div class="twoFields">
        <div class="field"><label>Когда</label><input id="t_date" type="date"></div>
        <div class="field"><label>Приоритет</label><select id="t_priority"><option value="1">Главное</option><option value="2">Важно</option><option value="3" selected>Обычное</option></select></div>
      </div>
      <div class="field"><label>Проект</label><select id="t_project"><option value="0">Без проекта</option></select></div>
      <div class="btnrow"><button class="btn primary" id="saveTask">Добавить задачу</button><button class="btn secondary" data-close="taskModal">Отмена</button></div>
      <div class="status" id="taskStatus"></div>
    </div>
  </div>
</div>

<div class="modal" id="quranModal" aria-hidden="true">
  <div class="modalSheet" role="dialog" aria-modal="true" aria-labelledby="quranModalTitle">
    <div class="modalHead">
      <div><div class="eyebrow">Чтение на сегодня</div><h2 id="quranModalTitle">Коран</h2></div>
      <button class="modalClose" data-close="quranModal" aria-label="Закрыть">×</button>
    </div>
    <div class="modalBody">
      <div class="readerColumn">
      <div class="readingIntro" id="quranModalIntro"></div>
      <div id="quranVerses"></div>
      <div class="modalSection">
        <h3 id="quranTafsirTitle">Краткое толкование</h3>
        <div class="readingIntro" style="margin-bottom:12px" id="quranTafsirNote">Краткое русское изложение на основе подключённого тафсира. Это не официальный текст ҚМДБ.</div>
        <div class="tafsirSummary" id="quranTafsir"></div>
        <details class="tafsirDetails" id="quranTafsirDetails">
          <summary>Показать подробное изложение</summary>
          <div class="tafsirFull" id="quranTafsirFull"></div>
        </details>
      </div>
      <div class="modalSection">
        <h3>Моя заметка</h3>
        <textarea id="quranTakeaway" placeholder="Что я понял, почувствовал или хочу применить после чтения?"></textarea>
        <div class="btnrow">
          <button class="btn primary" id="saveQuranTakeaway">Завершить чтение</button>
          <button class="btn secondary" id="quranToDaily">В ежедневник</button>
        </div>
        <div class="status" id="quranStatus"></div>
      </div>
      <div class="modalSection">
        <h3>Обсудить отрывок с AI</h3>
        <div class="aiAsk"><textarea id="quranQuestion" placeholder="Например: помоги понять общий смысл этого отрывка для моей повседневной жизни."></textarea><button class="btn secondary" id="askQuranAI">Спросить</button></div>
        <div class="aiOutput" id="quranAIOutput" hidden></div>
        <div class="privacyNote">AI помогает с личным размышлением, но не подменяет официальный тафсир или фетву.</div>
      </div>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="personModal" aria-hidden="true">
  <div class="modalSheet" role="dialog" aria-modal="true" aria-labelledby="personModalTitle">
    <div class="modalHead">
      <div><div class="eyebrow">Человек дня</div><h2 id="personModalTitle">История</h2></div>
      <button class="modalClose" data-close="personModal" aria-label="Закрыть">×</button>
    </div>
    <div class="modalBody">
      <div class="readingIntro" id="personIntro"></div>
      <div class="story" id="personStory"></div>
      <div class="storyCallout"><b>Что он оставил миру</b><div id="personContribution"></div></div>
      <div class="storyCallout"><b>Интересная деталь</b><div id="personFact"></div></div>
      <div class="modalSection">
        <div class="btnrow"><button class="btn primary" id="completePerson">Прочитал ✓</button><button class="btn secondary" id="personToKnowledge">Сохранить в заметки</button></div>
        <div class="status" id="personStatus"></div>
      </div>
      <div class="modalSection">
        <h3>Хочу узнать больше</h3>
        <div class="aiAsk"><textarea id="personQuestion" placeholder="Спроси что-нибудь об этом человеке, его работе или эпохе."></textarea><button class="btn secondary" id="askPersonAI">Спросить</button></div>
        <div class="aiOutput" id="personAIOutput" hidden></div>
      </div>
    </div>
  </div>
</div>

<div class="modal" id="analysisModal" aria-hidden="true">
  <div class="modalSheet" role="dialog" aria-modal="true" aria-labelledby="analysisModalTitle">
    <div class="modalHead">
      <div><div class="eyebrow">AI-разбор</div><h2 id="analysisModalTitle">Что разобрать?</h2></div>
      <button class="modalClose" data-close="analysisModal" aria-label="Закрыть">×</button>
    </div>
    <div class="modalBody">
      <div class="readingIntro">Ничего не отправляется автоматически. Выбери один набор данных.</div>
      <div class="analysisChoices">
        <button class="btn secondary aiAnalyze" data-type="today">Сегодняшний день</button>
        <button class="btn secondary aiAnalyze" data-type="week">Последние 7 дней</button>
        <button class="btn secondary aiAnalyze" data-type="ceo">Последний CEO Review</button>
        <button class="btn secondary aiAnalyze" data-type="capital">Последний капитал</button>
      </div>
      <div class="status" id="analysisStatus"></div>
      <div class="aiOutput" id="analysisOutput" hidden></div>
    </div>
  </div>
</div>


<nav class="mobileNav">
  <button class="mnav active" data-panel="home"><span>⌂</span>Сегодня</button>
  <button class="mnav" data-panel="plan"><span>▣</span>План</button>
  <button class="mnav" data-panel="journalHub"><span>✎</span>Дневник</button>
  <button class="mnav" data-panel="progress"><span>↗</span>Прогресс</button>
  <button class="mnav" data-panel="services"><span>▦</span>Сервисы</button>
</nav>

<script src="assets/ui.js?v=<?=@filemtime(__DIR__.'/assets/ui.js')?>"></script>
<script src="assets/notes.js?v=<?=@filemtime(__DIR__.'/assets/notes.js')?>"></script>
<script src="assets/projects.js?v=<?=@filemtime(__DIR__.'/assets/projects.js')?>"></script>
<script src="assets/workouts.js?v=<?=@filemtime(__DIR__.'/assets/workouts.js')?>"></script>
<script>
const $=id=>document.getElementById(id);
const today=()=>{let d=new Date(),o=d.getTimezoneOffset();return new Date(d-o*60000).toISOString().slice(0,10)};
const CSRF=<?=json_encode($_SESSION['csrf'], JSON_UNESCAPED_SLASHES)?>;
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const nl=s=>esc(s).replace(/\n/g,'<br>');
const money=n=>Number(n||0).toLocaleString('ru-RU')+' ₸';

async function api(resource,method='GET',data=null){
  const headers={};
  if(data) headers['Content-Type']='application/json';
  if(method!=='GET') headers['X-CSRF-Token']=CSRF;
  const r=await fetch('api.php?resource='+encodeURIComponent(resource),{method,headers,body:data?JSON.stringify(data):null});
  if(r.status===401){location='login.php';throw new Error('auth')}
  if(!r.ok){let j={};try{j=await r.json()}catch(e){};throw new Error(j.error||'API error')}
  return r.json();
}

const titles={home:'Сегодня',plan:'План',journalHub:'Дневник',progress:'Прогресс',services:'Сервисы',workouts:'Тренировки',daily:'Ежедневник',weekly:'CEO Review',knowledge:'Заметки',capital:'Капитал',exportPanel:'Экспорт'};
const primaryPanel=id=>({
  daily:'journalHub',weekly:'journalHub',
  workouts:'services',knowledge:'services',capital:'services',exportPanel:'services'
}[id]||id);
function openPanel(id){
  document.querySelectorAll('.panel').forEach(x=>x.classList.remove('active'));
  if(!$(id))id='home';
  $(id).classList.add('active');
  const primary=primaryPanel(id);
  document.querySelectorAll('.navbtn,.mnav').forEach(x=>x.classList.toggle('active',x.dataset.panel===primary));
  $('topbarTitle').textContent=titles[id]||'Growth OS';
  if(id==='plan')renderPlanAgenda();
  if(id==='progress')renderGrowthProgress();
  if(id==='workouts')loadWorkouts();
  if(id==='knowledge'&&window.Notes)Notes.load();
  if(window.innerWidth<900) window.scrollTo({top:0,behavior:'smooth'});
}
document.querySelectorAll('[data-panel]').forEach(b=>b.addEventListener('click',()=>openPanel(b.dataset.panel)));

['d_date','w_date','k_date','c_date','ex_to','t_date'].forEach(id=>{if($(id))$(id).value=today()});
$('d_mood').oninput=e=>$('moodVal').textContent=e.target.value;

let daily=[],weekly=[],knowledge=[],capital=[];

let tasks=[],taskStats={},taskView='today';
let projects=[],taskProjectMap={},projectView='active',projectFilter=0;
let workoutSummary=null,currentWorkoutSession=null,restTimerEnd=0,restTimerInterval=null,selectedWorkoutSession=null,workoutCalendarMonth=today().slice(0,7),workoutMainMode='none';
let aiProgressCache=null;

async function taskEndpoint(action,data={}){
  const r=await fetch('tasks.php?action='+encodeURIComponent(action),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(data)});
  if(r.status===401){location='login.php';throw new Error('auth')}
  let j={};try{j=await r.json()}catch(e){}
  if(!r.ok||j.ok===false)throw new Error(j.error||('HTTP '+r.status));
  return j;
}
async function projectEndpoint(action,data={}){
  const r=await fetch('projects.php?action='+encodeURIComponent(action),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(data)});
  if(r.status===401){location='login.php';throw new Error('auth')}
  let j={};try{j=await r.json()}catch(e){}
  if(!r.ok||j.ok===false)throw new Error(j.error||('HTTP '+r.status));
  return j;
}
async function workoutEndpoint(action,data={}){
  const r=await fetch('workouts.php?action='+encodeURIComponent(action),{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(data)});
  if(r.status===401){location='login.php';throw new Error('auth')}
  let j={};try{j=await r.json()}catch(e){}
  if(!r.ok||j.ok===false)throw new Error(j.error||('HTTP '+r.status));
  return j;
}
const addDays=(s,n)=>{const d=new Date(s+'T12:00:00');d.setDate(d.getDate()+n);return d.toISOString().slice(0,10)};
const tomorrow=()=>addDays(today(),1);
const shortDate=d=>new Date(d+'T12:00:00').toLocaleDateString('ru-RU',{day:'numeric',month:'short'});
const fullDate=d=>new Date(d+'T12:00:00').toLocaleDateString('ru-RU',{day:'numeric',month:'long'});
function taskPriorityName(p){p=Number(p);return p===1?'Главное':p===2?'Важно':'Обычное'}
function taskPriorityClass(p){p=Number(p);return p===1?'high':p===2?'medium':''}
function projectName(id){const p=projects.find(x=>Number(x.id)===Number(id));return p?p.name:''}
function prettyTaskDate(d){if(d===today())return 'Сегодня';if(d===tomorrow())return 'Завтра';return shortDate(d)}
function isTaskOverdue(t){return t.status!=='done'&&t.due_date<today()}
function projectOptions(selected=0,withNone=true){let s=withNone?'<option value="0">Без проекта</option>':'';return s+projects.filter(p=>p.status!=='done').map(p=>`<option value="${p.id}" ${Number(p.id)===Number(selected)?'selected':''}>${esc(p.name)}</option>`).join('')}
function renderTaskStats(){
  const s=taskStats||{};
  $('t_today').textContent=Number(s.today_pending||0);
  $('t_today_meta').textContent=`${Number(s.today_done||0)}/${Number(s.today_total||0)} выполнено`;
  $('t_tomorrow').textContent=tasks.filter(t=>t.status!=='done'&&t.due_date===tomorrow()).length;
  $('t_overdue').textContent=Number(s.overdue||0);
}
function visibleTasks(){
  let list=tasks.slice();const td=today(),tm=tomorrow();
  if(projectFilter)list=list.filter(t=>Number(t.project_id||0)===Number(projectFilter));
  if(taskView==='done')return list.filter(t=>t.status==='done').sort((a,b)=>String(b.completed_at||b.due_date).localeCompare(String(a.completed_at||a.due_date))).slice(0,100);
  if(taskView==='tomorrow')return list.filter(t=>t.status!=='done'&&t.due_date===tm).sort((a,b)=>Number(a.priority)-Number(b.priority)||Number(a.id)-Number(b.id));
  if(taskView==='overdue')return list.filter(t=>t.status!=='done'&&t.due_date<td).sort((a,b)=>a.due_date.localeCompare(b.due_date)||Number(a.priority)-Number(b.priority));
  if(taskView==='active')return list.filter(t=>t.status!=='done').sort((a,b)=>a.due_date.localeCompare(b.due_date)||Number(a.priority)-Number(b.priority));
  return list.filter(t=>t.status!=='done'&&t.due_date===td).sort((a,b)=>Number(a.priority)-Number(b.priority)||Number(a.id)-Number(b.id));
}
function taskRowHtml(t){
  const overdue=isTaskOverdue(t),done=t.status==='done',pid=Number(t.project_id||0);
  return `<div class="taskRow ${done?'done':''}" data-task-id="${t.id}">
    <input class="taskCheck" type="checkbox" data-task-toggle="${t.id}" ${done?'checked':''} aria-label="Выполнено">
    <div>
      <div class="taskTitle">${esc(t.title)}</div>
      <div class="taskMeta">
        <span class="taskPill ${overdue?'overdue':''}">${overdue?'Просрочено · ':''}${esc(prettyTaskDate(t.due_date))}</span>
        ${Number(t.priority)!==3?`<span class="taskPill ${taskPriorityClass(t.priority)}">${esc(taskPriorityName(t.priority))}</span>`:''}
        ${pid?`<span class="taskPill projectPill">${esc(projectName(pid))}</span>`:''}
      </div>
    </div>
    <details class="taskMore"><summary aria-label="Действия">•••</summary><div class="taskMoreMenu">
      ${!done?`<label>Проект</label><select data-task-project="${t.id}">${projectOptions(pid,true)}</select>
      <label>Приоритет</label><select data-task-priority="${t.id}"><option value="1" ${Number(t.priority)===1?'selected':''}>Главное</option><option value="2" ${Number(t.priority)===2?'selected':''}>Важно</option><option value="3" ${Number(t.priority)===3?'selected':''}>Обычное</option></select>
      <div class="taskMenuBtns"><button class="taskSmall" data-task-move="${t.id}" data-move-to="today">На сегодня</button><button class="taskSmall" data-task-move="${t.id}" data-move-to="tomorrow">На завтра</button></div>`:''}
      <div class="taskMenuBtns">
        ${t.source_type==='manual'&&!done?`<button class="taskSmall" data-task-edit="${t.id}">Изменить</button>`:''}
        ${t.source_type==='daily'&&!done?`<button class="taskSmall" data-task-source="${t.id}">Источник</button>`:''}
        <button class="taskSmall danger" data-task-delete="${t.id}">Удалить</button>
      </div>
    </div></details>
  </div>`;
}
function renderTasks(){
  renderTaskStats();
  const counts={
    today:tasks.filter(t=>t.status!=='done'&&t.due_date===today()&&(!projectFilter||Number(t.project_id||0)===projectFilter)).length,
    tomorrow:tasks.filter(t=>t.status!=='done'&&t.due_date===tomorrow()&&(!projectFilter||Number(t.project_id||0)===projectFilter)).length,
    overdue:tasks.filter(t=>t.status!=='done'&&t.due_date<today()&&(!projectFilter||Number(t.project_id||0)===projectFilter)).length,
    active:tasks.filter(t=>t.status!=='done'&&(!projectFilter||Number(t.project_id||0)===projectFilter)).length,
    done:tasks.filter(t=>t.status==='done'&&(!projectFilter||Number(t.project_id||0)===projectFilter)).length
  };
  const labels={today:'Сегодня',tomorrow:'Завтра',overdue:'Просрочено',active:'Все',done:'Готово'};
  document.querySelectorAll('[data-task-view]').forEach(b=>{b.classList.toggle('active',b.dataset.taskView===taskView);b.textContent=`${labels[b.dataset.taskView]} · ${counts[b.dataset.taskView]}`});
  const list=visibleTasks();
  const empty={today:'На сегодня задач нет.',tomorrow:'На завтра задач пока нет.',overdue:'Просроченных задач нет.',active:'Активных задач нет.',done:'Пока нет выполненных задач.'};
  $('taskList').innerHTML=list.length?list.map(taskRowHtml).join(''):`<div class="taskEmptySlim">${empty[taskView]}</div>`;
}
function renderTaskHome(){
  const td=today(),tm=tomorrow();
  const tday=tasks.filter(t=>t.status!=='done'&&t.due_date===td).sort((a,b)=>Number(a.priority)-Number(b.priority));
  const tmr=tasks.filter(t=>t.status!=='done'&&t.due_date===tm).sort((a,b)=>Number(a.priority)-Number(b.priority));
  const overdue=tasks.filter(t=>t.status!=='done'&&t.due_date<td);
  const done=Number(taskStats.today_done||0),total=Number(taskStats.today_total||0);
  $('taskHomeTitle').textContent=total?`Сегодня: ${done} из ${total} выполнено`:'Сегодня задач нет';
  $('taskHomeMeta').textContent=`Завтра: ${tmr.length}${overdue.length?' · Просрочено: '+overdue.length:''}`;
  let html='';
  if(tday.length){html+='<div class="miniGroup">Сегодня</div>'+tday.slice(0,2).map(t=>`<label class="taskMini"><input type="checkbox" data-home-task-toggle="${t.id}"><span>${esc(t.title)}</span></label>`).join('');if(tday.length>2)html+=`<div class="miniMore">+ ещё ${tday.length-2}</div>`}
  if(tmr.length){html+=`<div class="miniGroup tomorrow">Завтра · ${fullDate(tm)}</div>`+tmr.slice(0,2).map(t=>`<div class="taskMini"><span style="width:17px;text-align:center;color:#666">○</span><span>${esc(t.title)}</span></div>`).join('');if(tmr.length>2)html+=`<div class="miniMore">+ ещё ${tmr.length-2}</div>`}
  $('taskHomeList').innerHTML=html||'<div class="empty" style="padding:0">Сегодня и на завтра задач пока нет.</div>';
}
function fillProjectControls(){
  const filter=$('taskProjectFilter'),taskSel=$('t_project');
  if(filter){const cur=Number(filter.value||projectFilter||0);filter.innerHTML='<option value="0">Все проекты</option>'+projects.map(p=>`<option value="${p.id}" ${Number(p.id)===cur?'selected':''}>${esc(p.name)}</option>`).join('');filter.value=String(cur)}
  if(taskSel){const cur=Number(taskSel.value||0);taskSel.innerHTML=projectOptions(cur,true);taskSel.value=String(cur)}
}
async function loadTasks(){
  try{
    const [tj,pj]=await Promise.all([taskEndpoint('list',{date:today()}),projectEndpoint('list',{date:today()})]);
    tasks=tj.tasks||[];taskStats=tj.stats||{};projects=pj.projects||[];taskProjectMap=pj.task_project_map||{};
    tasks.forEach(t=>t.project_id=Number(taskProjectMap[String(t.id)]||0));
    fillProjectControls();renderTasks();renderTaskHome();renderProjects();
  }catch(e){
    $('taskHomeTitle').textContent='Задачи недоступны';$('taskHomeMeta').textContent=e.message;
    $('taskList').innerHTML=`<div class="taskEmptySlim errorText">${esc(e.message)}</div>`;
  }
}
function resetTaskForm(){
  $('t_id').value='';$('t_title').value='';$('t_date').value=today();$('t_priority').value='3';$('t_project').value='0';$('taskModalTitle').textContent='Новое действие';$('saveTask').textContent='Добавить задачу';$('taskStatus').textContent='';
}
$('openTaskModal').onclick=()=>{resetTaskForm();openModal('taskModal');setTimeout(()=>$('t_title').focus(),100)};
$('saveTask').onclick=async()=>{
  const id=$('t_id').value,title=$('t_title').value.trim(),due_date=$('t_date').value,priority=Number($('t_priority').value||3),project_id=Number($('t_project').value||0);
  if(!title)return alert('Напиши конкретное действие.');
  const b=$('saveTask');setBusy(b,true,'Сохраняю…');
  try{
    const j=await taskEndpoint(id?'update':'add',{id:id?Number(id):undefined,title,due_date,priority});
    const tid=id?Number(id):Number(j.id||0);if(tid)await projectEndpoint('assign_task',{task_id:tid,project_id});
    closeModal('taskModal');await loadTasks();
  }catch(e){$('taskStatus').textContent='Ошибка: '+e.message}finally{setBusy(b,false)}
};
document.querySelectorAll('[data-task-view]').forEach(b=>b.onclick=()=>{taskView=b.dataset.taskView;renderTasks()});
$('taskProjectFilter').onchange=()=>{projectFilter=Number($('taskProjectFilter').value||0);renderTasks()};
$('taskList').addEventListener('change',async e=>{
  const toggle=e.target.dataset.taskToggle,pri=e.target.dataset.taskPriority,pid=e.target.dataset.taskProject;
  try{
    if(toggle)await taskEndpoint('toggle',{id:Number(toggle),done:e.target.checked});
    else if(pri)await taskEndpoint('priority',{id:Number(pri),priority:Number(e.target.value)});
    else if(pid)await projectEndpoint('assign_task',{task_id:Number(pid),project_id:Number(e.target.value||0)});
    else return;
    await loadTasks();
  }catch(err){alert('Ошибка: '+err.message);await loadTasks()}
});
$('taskList').addEventListener('click',async e=>{
  const move=e.target.dataset.taskMove,del=e.target.dataset.taskDelete,edit=e.target.dataset.taskEdit,source=e.target.dataset.taskSource;
  try{
    if(move){await taskEndpoint('move',{id:Number(move),due_date:e.target.dataset.moveTo==='tomorrow'?tomorrow():today()});await loadTasks()}
    if(del&&confirm('Удалить задачу?')){await taskEndpoint('delete',{id:Number(del)});await loadTasks()}
    if(edit){const t=tasks.find(x=>Number(x.id)===Number(edit));if(!t)return;resetTaskForm();$('t_id').value=t.id;$('t_title').value=t.title;$('t_date').value=t.due_date;$('t_priority').value=String(t.priority||3);$('t_project').value=String(Number(t.project_id||0));$('taskModalTitle').textContent='Изменить задачу';$('saveTask').textContent='Сохранить';openModal('taskModal')}
    if(source){const t=tasks.find(x=>Number(x.id)===Number(source));if(!t||!t.source_date)return;const d=daily.find(x=>x.date===t.source_date);if(d){editDaily(d);openPanel('daily');$('d_action').focus()}else alert('Исходная запись ежедневника не найдена.')}
  }catch(err){alert('Ошибка: '+err.message)}
});
$('taskHomeList').addEventListener('change',async e=>{const id=e.target.dataset.homeTaskToggle;if(!id)return;try{await taskEndpoint('toggle',{id:Number(id),done:true});await loadTasks()}catch(err){alert('Ошибка: '+err.message)}});

// ----- Projects -----
// Projects UI lives in assets/projects.js (window.Projects)
$('openProjectModal').onclick=()=>{if(window.Projects)Projects.edit(null)};
function renderProjects(){
  document.querySelectorAll('[data-project-view]').forEach(b=>b.classList.toggle('active',b.dataset.projectView===projectView));
  const list=projects.filter(p=>p.status===projectView);
  if(window.Projects)Projects.renderList($('projectList'),list);
}
document.querySelectorAll('[data-project-view]').forEach(b=>b.onclick=()=>{projectView=b.dataset.projectView;renderProjects()});
function setWorkspace(name){
  $('tasksWorkspace').hidden=name!=='tasks';$('projectsWorkspace').hidden=name!=='projects';
  document.querySelectorAll('[data-workspace]').forEach(b=>b.classList.toggle('active',b.dataset.workspace===name));
}
document.querySelectorAll('[data-workspace]').forEach(b=>b.onclick=()=>setWorkspace(b.dataset.workspace));


function renderPlanAgenda(){
  const box=$('planAgenda');if(!box)return;
  const start=today();
  const cal=workoutSummary?.agenda_sessions||[];
  let html='';
  for(let i=0;i<7;i++){
    const d=addDays(start,i);
    const dayTasks=tasks.filter(t=>t.status!=='done'&&t.due_date===d);
    const overdue=dayTasks.filter(t=>isTaskOverdue(t)).length;
    const ws=cal.filter(x=>x.scheduled_date===d);
    const w=ws[0]||null;
    const dt=new Date(d+'T12:00:00');
    const label=i===0?'Сегодня':i===1?'Завтра':dt.toLocaleDateString('ru-RU',{weekday:'short'});
    let items='';
    if(dayTasks.length)items+=`<div class="agendaCompactLine"><i class="agendaDot ${overdue?'overdue':''}"></i><span>${dayTasks.length} ${pluralRu(dayTasks.length,'задача','задачи','задач')}</span></div>`;
    if(w)items+=`<div class="agendaCompactLine"><i class="agendaDot workout"></i><span>${esc(w.day_name)}</span></div>`;
    if(!items)items='<div class="agendaEmpty">Свободно</div>';
    html+=`<div class="agendaDay ${i===0?'today':''}"><div class="agendaDate"><b>${esc(label)}</b><span>${shortDate(d)}</span></div><div class="agendaItems">${items}</div></div>`;
  }
  box.innerHTML=html;
}
// Workouts UI lives in assets/workouts.js (window.Workouts)
async function loadWorkouts(month){if(window.Workouts)return Workouts.load(month)}
function renderJournalHub(){
  const td=daily.find(x=>x.date===today());
  $('journalHubDailyTitle').textContent=td?'Сегодня заполнено ✓':'Сегодня ещё не заполнено';
  $('journalHubDailyMeta').textContent=td?`Состояние ${td.mood}/10 · запись сохранена`:'Вечером зафиксируй состояние, благодарность, выводы и задачи на завтра.';
  const last=[...weekly].sort((a,b)=>b.date.localeCompare(a.date))[0];
  $('journalHubWeeklyTitle').textContent=last?`Последний: ${shortDate(last.date)}`:'CEO Review ещё нет';
  $('journalHubWeeklyMeta').textContent=last?`Заработано: ${money(last.money||0)} · следующая цель: ${last.goal||'—'}`:'Раз в неделю — деньги, результат, потери фокуса и следующий рычаг.';
}
function renderGrowthProgress(){
  if(!$('pgTasks'))return;
  const tp=taskStats?.week_percent;
  $('pgTasks').textContent=tp===null||tp===undefined?'—':`${Number(tp)}%`;
  $('pgTasksMeta').textContent=`${Number(taskStats?.week_done||0)} из ${Number(taskStats?.week_total||0)} задач`;
  const q=aiProgressCache?.quran||{},p=aiProgressCache?.person||{};
  $('pgQuran').textContent=q.percent!==undefined?`${Number(q.percent).toLocaleString('ru-RU',{maximumFractionDigits:2})}%`:'—';
  $('pgQuranMeta').textContent=`${Number(q.completed_verses||0)} аятов`;
  $('pgPeople').textContent=Number(p.completed_people||0);
  $('pgPeopleMeta').textContent=`серия ${Number(p.streak||0)} дн.`;
  const wc=Number(workoutSummary?.stats?.last_28_days||0),wt=Number(workoutSummary?.stats?.target_28_days||12);
  $('pgWorkouts').textContent=`${wc}/${wt}`;
  $('pgWorkoutsMeta').textContent=wt?`${Math.round(wc/wt*100)}% от плана`:'—';
  const from=addDays(today(),-6);const dc=daily.filter(x=>x.date>=from&&x.date<=today()).length;
  $('pgJournal').textContent=`${dc}/7`;$('pgJournalMeta').textContent='дней с записью';
  const bits=[];
  if(tp!==null&&tp!==undefined)bits.push(`задачи ${Number(tp)}%`);
  bits.push(`дневник ${dc}/7`);
  bits.push(`тренировки ${wc}/${wt}`);
  $('progressNarrative').textContent='Последние недели: '+bits.join(' · ')+'.';
}

async function refreshAll(){
  [daily,weekly,knowledge,capital]=await Promise.all([api('daily'),api('weekly'),api('knowledge'),api('capital')]);
  renderDaily();renderWeekly();renderCapital();renderHome();renderJournalHub();
  await Promise.allSettled([loadTasks(),loadWorkouts()]);
  renderPlanAgenda();renderGrowthProgress();
}
function dl(name,content,type){const blob=new Blob([content],{type}),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000)}
function confirmDelete(label){return confirm(`Удалить ${label}? Это действие нельзя отменить.`)}
function toggleEntry(el){el.closest('.entry').classList.toggle('open')}
document.addEventListener('click',e=>{const h=e.target.closest('.entryHead');if(h)toggleEntry(h)});

function renderHome(){
  const now=new Date(),h=now.getHours();
  $('greeting').textContent=h<12?'Доброе утро':h<18?'Добрый день':'Добрый вечер';
  $('todayLabel').textContent=now.toLocaleDateString('ru-RU',{weekday:'long',day:'numeric',month:'long'});
  const td=daily.find(x=>x.date===today());
  $('homeDailyState').textContent=td?'Заполнен':'Не заполнен';
  $('homeDailyMeta').textContent='Дневник сегодня';
  const lastDaily=[...daily].sort((a,b)=>b.date.localeCompare(a.date))[0];
  $('homeMood').textContent=lastDaily?String(lastDaily.mood):'—';
  $('homeWeeklyCount').textContent=weekly.length;
  $('homeKnowledgeCount').textContent=knowledge.length;
}


let todayQuran=null, todayPerson=null;

async function endpoint(url,method='GET',data=null){
  const headers={};
  if(data) headers['Content-Type']='application/json';
  if(method!=='GET') headers['X-CSRF-Token']=CSRF;
  const r=await fetch(url,{method,headers,body:data?JSON.stringify(data):null});
  if(r.status===401){location='login.php';throw new Error('auth')}
  let j={};try{j=await r.json()}catch(e){}
  if(!r.ok||j.ok===false) throw new Error(j.error||j.message||('HTTP '+r.status));
  return j;
}
const setBusy=(btn,busy,label='Подождите…')=>{
  if(!btn)return;
  if(busy){btn.dataset.old=btn.textContent;btn.textContent=label;btn.disabled=true}
  else{btn.textContent=btn.dataset.old||btn.textContent;btn.disabled=false}
};
function openModal(id){const m=$(id);if(!m)return;m.classList.add('open');m.setAttribute('aria-hidden','false');document.body.classList.add('modalOpen')}
function closeModal(id){const m=$(id);if(!m)return;m.classList.remove('open');m.setAttribute('aria-hidden','true');if(!document.querySelector('.modal.open'))document.body.classList.remove('modalOpen')}
document.querySelectorAll('[data-close]').forEach(b=>b.onclick=()=>closeModal(b.dataset.close));
document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModal(m.id)}));
document.addEventListener('keydown',e=>{if(e.key==='Escape'){const m=document.querySelector('.modal.open');if(m)closeModal(m.id)}});

function renderQuran(q){
  todayQuran=q;
  $('quranLoading').hidden=true;$('quranPreview').hidden=false;$('openQuran').hidden=false;
  const isFullFatiha=q.surah===1&&q.start_ayah===1&&q.end_ayah===7;
  const range=q.start_ayah===q.end_ayah?`${q.surah}:${q.start_ayah}`:`${q.surah}:${q.start_ayah}–${q.end_ayah}`;
  $('quranTitle').textContent=isFullFatiha?`${q.chapter_name} · полностью`:`${q.chapter_name} · ${range}`;
  $('quranLead').textContent=q.summary||'Последовательное чтение Корана на сегодня.';
  $('quranMeta').textContent=`${(q.verses&&q.verses.length)||q.verse_count||1} аятов · ≈ ${q.reading_minutes||5} мин`;
  $('quranBadge').textContent=q.completed?'Прочитано ✓':(q.environment==='prelive'?'Quran Foundation · Prelive':'Quran Foundation');
  $('quranBadge').classList.toggle('completedBadge',!!q.completed);
  $('quranModalTitle').textContent=isFullFatiha?`${q.chapter_name} · полностью`:`${q.chapter_name} · ${range}`;
  $('quranModalIntro').textContent=`${(q.verses&&q.verses.length)||q.verse_count||1} аятов · примерно ${q.reading_minutes||5} минут.${q.translation_source?' Перевод: '+q.translation_source+'.':''}`;
  $('quranVerses').innerHTML=(q.verses||[]).map(v=>`<div class="verseBlock"><div class="verseKey">${esc(v.verse_key)}</div><div class="quranArabic">${esc(v.arabic||'')}</div>${v.transliteration?`<div class="translit">${esc(v.transliteration)}</div>`:''}<div class="translation">${esc(v.translation||'—')}</div></div>`).join('');
  $('quranTakeaway').value=q.takeaway||'';
  const ts=q.tafsir_source||'доступный тафсир';$('quranTafsirTitle').textContent='Краткое толкование · '+ts;
  const low=ts.toLowerCase();
  if(low.includes("ma'arif")||low.includes('maarif')) $('quranTafsirNote').textContent='Краткое русское изложение на основе Ma’arif al-Qur’an. Это не официальный текст ҚМДБ.';
  else $('quranTafsirNote').textContent=`Краткое русское изложение на основе ${ts}. Это не официальный текст ҚМДБ и не подменяет позицию Муфтията.`;
  $('quranTafsir').textContent=q.tafsir||'Толкование временно не загрузилось. Текст аятов и перевод доступны.';
  $('quranTafsirFull').textContent=q.tafsir_full||q.tafsir||'';
  $('quranTafsirDetails').hidden=!(q.tafsir_full&&q.tafsir_full.trim()&&q.tafsir_full.trim()!==String(q.tafsir||'').trim());
  $('quranStatus').textContent=q.completed?'✓ Это чтение уже завершено. Следующий отрывок появится в новый день.':'';
  $('saveQuranTakeaway').textContent=q.completed?'Сохранить заметку':'Завершить чтение';
}
async function loadQuran(){
  try{const j=await endpoint('quran.php?action=today','POST',{date:today()});renderQuran(j.data)}
  catch(e){$('quranLoading').innerHTML=`<span class="errorText">Не удалось загрузить: ${esc(e.message)}</span>`}
}
$('openQuran').onclick=()=>openModal('quranModal');
$('saveQuranTakeaway').onclick=async()=>{
  if(!todayQuran)return;
  const text=$('quranTakeaway').value.trim();
  const b=$('saveQuranTakeaway');setBusy(b,true,'Сохраняю…');
  try{const j=await endpoint('quran.php?action=complete','POST',{date:today(),takeaway:text,start_ayah:todayQuran.start_ayah,end_ayah:todayQuran.end_ayah,surah:todayQuran.surah});todayQuran.takeaway=text;todayQuran.completed=true;$('quranStatus').textContent=j.advanced?'✓ Завершено. Следующее чтение — завтра.':'✓ Сохранено.';$('quranBadge').textContent='Прочитано ✓';$('quranBadge').classList.add('completedBadge');$('saveQuranTakeaway').textContent='Сохранить заметку';await loadProgress()}
  catch(e){$('quranStatus').textContent='Ошибка: '+e.message}finally{setBusy(b,false)}
};
$('quranToDaily').onclick=()=>{
  const t=$('quranTakeaway').value.trim();if(!t)return alert('Сначала запиши заметку.');
  const old=$('d_lesson').value.trim();const prefix=`Коран ${todayQuran?.surah}:${todayQuran?.start_ayah}–${todayQuran?.end_ayah}: `;
  $('d_lesson').value=old?old+'\n\n'+prefix+t:prefix+t;$('d_date').value=today();closeModal('quranModal');openPanel('daily');$('d_lesson').focus();
};
$('askQuranAI').onclick=async()=>{
  const q=$('quranQuestion').value.trim();if(!q)return alert('Напиши вопрос.');const b=$('askQuranAI');setBusy(b,true,'Думаю…');
  try{const j=await endpoint('ai.php?action=discuss','POST',{date:today(),subject:'quran',question:q});$('quranAIOutput').hidden=false;$('quranAIOutput').textContent=j.answer}
  catch(e){$('quranAIOutput').hidden=false;$('quranAIOutput').textContent='Ошибка: '+e.message}finally{setBusy(b,false)}
};

function renderPerson(p){
  todayPerson=p;$('personLoading').hidden=true;$('personPreview').hidden=false;$('openPerson').hidden=false;
  $('personName').textContent=p.name||'Человек дня';$('personTeaser').textContent=p.teaser||p.contribution||'';
  const words=String(p.story||'').trim().split(/\s+/).filter(Boolean).length;const mins=Math.max(2,Math.round(words/180));
  $('personMeta').textContent=`${p.field||'История'} · ~${mins} мин чтения`;
  $('personModalTitle').textContent=p.name||'Человек дня';$('personIntro').textContent=p.intro||p.teaser||'';$('personStory').textContent=p.story||'';$('personContribution').textContent=p.contribution||'';$('personFact').textContent=p.interesting_fact||'';
  $('completePerson').textContent=p.completed?'Прочитано ✓':'Прочитал ✓';$('completePerson').disabled=!!p.completed;if(p.completed)$('personStatus').textContent='✓ История отмечена как прочитанная.';
}
async function loadPerson(){
  try{const j=await endpoint('ai.php?action=person_day','POST',{date:today()});renderPerson(j.data)}
  catch(e){$('personLoading').innerHTML=`<span class="errorText">Не удалось загрузить: ${esc(e.message)}</span>`}
}
$('openPerson').onclick=()=>openModal('personModal');
$('completePerson').onclick=async()=>{
  if(!todayPerson)return;const b=$('completePerson');setBusy(b,true,'Сохраняю…');
  try{await endpoint('ai.php?action=person_complete','POST',{date:today()});todayPerson.completed=true;$('personStatus').textContent='✓ История отмечена как прочитанная.';b.dataset.old='Прочитано ✓';await loadProgress()}
  catch(e){$('personStatus').textContent='Ошибка: '+e.message}finally{setBusy(b,false);if(todayPerson?.completed){b.textContent='Прочитано ✓';b.disabled=true}}
};
$('personToKnowledge').onclick=()=>{
  if(!todayPerson||!window.Notes)return;closeModal('personModal');
  Notes.open({kind:'idea',title:todayPerson.name||'',body:todayPerson.contribution||todayPerson.teaser||'',why:'Хочу запомнить этого человека и его вклад.',source:'Человек дня',tags:'человек дня, история'});
};
$('askPersonAI').onclick=async()=>{
  const q=$('personQuestion').value.trim();if(!q)return alert('Напиши вопрос.');const b=$('askPersonAI');setBusy(b,true,'Думаю…');
  try{const j=await endpoint('ai.php?action=discuss','POST',{date:today(),subject:'person',question:q});$('personAIOutput').hidden=false;$('personAIOutput').textContent=j.answer}
  catch(e){$('personAIOutput').hidden=false;$('personAIOutput').textContent='Ошибка: '+e.message}finally{setBusy(b,false)}
};

$('openAnalysis').onclick=()=>openModal('analysisModal');

document.querySelectorAll('.serviceAction').forEach(b=>b.onclick=()=>{
  const s=b.dataset.service;
  if(s==='quran'){if(todayQuran)openModal('quranModal');else alert('Коран дня ещё загружается.')}
  if(s==='person'){if(todayPerson)openModal('personModal');else alert('Человек дня ещё загружается.')}
  if(s==='ai')openModal('analysisModal');
});

document.querySelectorAll('.aiAnalyze').forEach(btn=>btn.onclick=async()=>{
  document.querySelectorAll('.aiAnalyze').forEach(x=>x.disabled=true);$('analysisStatus').textContent='AI анализирует выбранные данные…';$('analysisOutput').hidden=true;
  try{const j=await endpoint('ai.php?action=analysis','POST',{date:today(),type:btn.dataset.type});$('analysisOutput').hidden=false;$('analysisOutput').textContent=j.analysis;$('analysisStatus').textContent='✓ Разбор сохранён.'}
  catch(e){$('analysisStatus').textContent='Ошибка: '+e.message}finally{document.querySelectorAll('.aiAnalyze').forEach(x=>x.disabled=false)}
});
function pluralRu(n,one,few,many){n=Math.abs(n)%100;const n1=n%10;if(n>10&&n<20)return many;if(n1>1&&n1<5)return few;if(n1===1)return one;return many}
function renderProgress(p){
  aiProgressCache=p||{};
  const q=p?.quran||{};const person=p?.person||{};
  const qp=Number(q.percent||0);$('quranProgressText').textContent=`${Number(q.completed_verses||0).toLocaleString('ru-RU')} / ${Number(q.total_verses||6236).toLocaleString('ru-RU')} аятов`;$('quranProgressBar').style.width=Math.max(0,Math.min(100,qp))+'%';
  const qs=Number(q.streak||0);$('quranProgressSub').textContent=`${qp.toLocaleString('ru-RU',{maximumFractionDigits:2})}% Корана · ${Number(q.completed_readings||0)} ${pluralRu(Number(q.completed_readings||0),'чтение','чтения','чтений')} · серия ${qs} ${pluralRu(qs,'день','дня','дней')}`;
  const pc=Number(person.completed_people||0),ps=Number(person.streak||0);$('personProgressText').textContent=`${pc} ${pluralRu(pc,'человек','человека','человек')}`;$('personProgressSub').textContent=`Прочитано историй: ${pc} · серия ${ps} ${pluralRu(ps,'день','дня','дней')}`;
  renderGrowthProgress();
}
async function loadProgress(){try{const j=await endpoint('ai.php?action=progress','POST',{date:today()});renderProgress(j.progress)}catch(e){$('quranProgressSub').textContent='Прогресс временно недоступен';$('personProgressSub').textContent='Прогресс временно недоступен'}}
async function loadTodayModules(){await Promise.allSettled([loadQuran(),loadPerson(),loadProgress()]);}


function updateDailyTaskHint(){
  const d=$('d_date').value||today();
  $('dailyTaskHint').innerHTML=`Каждая непустая строка станет отдельной задачей на <b>${fullDate(addDays(d,1))}</b>. Если заполняешь дневник после полуночи, выбери дату дня, который подводишь итог.`;
}
$('d_date').addEventListener('change',updateDailyTaskHint);

function resetDaily(){['d_allah','d_self','d_lesson','d_trust','d_action','d_money','d_focus'].forEach(id=>$(id).value='');$('d_date').value=today();$('d_mood').value=7;$('moodVal').textContent='7';$('d_sync_tasks').checked=true;updateDailyTaskHint()}
$('resetDaily').onclick=resetDaily;
function editDaily(x){$('d_date').value=x.date;$('d_allah').value=x.allah||'';$('d_self').value=x.self||'';$('d_lesson').value=x.lesson||'';$('d_trust').value=x.trust||'';$('d_action').value=x.action||'';$('d_money').value=x.money||'';$('d_focus').value=x.focusloss||'';$('d_mood').value=x.mood||7;$('moodVal').textContent=x.mood||7;updateDailyTaskHint();window.scrollTo({top:0,behavior:'smooth'})}
function renderDaily(){
  daily.sort((a,b)=>b.date.localeCompare(a.date));
  $('dailyHistory').innerHTML=daily.length?daily.map(x=>`<div class="entry">
    <button class="entryHead"><div><div class="entryTitle">${esc(x.date)}</div><div class="entryMeta">Состояние ${x.mood}/10</div></div><div class="chev">⌄</div></button>
    <div class="entryBody">
      <p><b>Благодарность Аллаху</b><br>${nl(x.allah)}</p>
      <p><b>Благодарность себе</b><br>${nl(x.self)}</p>
      <p><b>Что понял</b><br>${nl(x.lesson)}</p>
      <p><b>Доверяю Аллаху</b><br>${nl(x.trust)}</p>
      <p><b>Что зависит от меня</b><br>${nl(x.action)}</p>
      <p><b>К 300 млн ₸</b><br>${nl(x.money)}</p>
      <p><b>Потеря фокуса</b><br>${nl(x.focusloss)}</p>
      <div class="btnrow"><button class="btn secondary dEdit" data-date="${x.date}">Редактировать</button><button class="btn secondary dExport" data-date="${x.date}">Экспорт</button><button class="btn danger dDelete" data-date="${x.date}">Удалить</button></div>
    </div></div>`).join(''):'<div class="empty">Пока нет записей.</div>';
}
$('dailyHistory').onclick=async e=>{
  const d=e.target.dataset.date;if(!d)return;const x=daily.find(v=>v.date===d);
  if(e.target.classList.contains('dEdit'))editDaily(x);
  if(e.target.classList.contains('dExport'))oneExport('daily',x);
  if(e.target.classList.contains('dDelete')&&confirmDelete(`запись за ${d}`)){await api('daily','DELETE',{date:d});try{await taskEndpoint('delete_source',{source_date:d})}catch(_e){}await refreshAll()}
};
$('saveDaily').onclick=async()=>{
  const x={date:$('d_date').value,allah:$('d_allah').value,self:$('d_self').value,lesson:$('d_lesson').value,trust:$('d_trust').value,action:$('d_action').value,money:$('d_money').value,focusloss:$('d_focus').value,mood:+$('d_mood').value};
  await api('daily','POST',x);
  let taskMsg='';
  if($('d_sync_tasks').checked){
    try{
      const tj=await taskEndpoint('sync_daily',{source_date:x.date,text:x.action});
      const c=Number(tj.created||0)+Number(tj.updated||0);
      taskMsg=c?` · задачи на завтра: ${Number(tj.active||0)}`:'';
    }catch(e){taskMsg=' · дневник сохранён, но задачи: '+e.message}
  }
  $('dailyStatus').textContent='✓ Сохранено'+taskMsg;resetDaily();await refreshAll()
};

function resetWeekly(){['w_money','w_best','w_leaks','w_actions','w_time','w_lever','w_goal','w_tasks'].forEach(id=>$(id).value='');$('w_date').value=today()}
$('resetWeekly').onclick=resetWeekly;
function editWeekly(x){$('w_date').value=x.date;$('w_money').value=x.money||0;$('w_best').value=x.best||'';$('w_leaks').value=x.leaks||'';$('w_actions').value=x.actions||'';$('w_time').value=x.time||'';$('w_lever').value=x.lever||'';$('w_goal').value=x.goal||'';$('w_tasks').value=x.tasks||'';window.scrollTo({top:0,behavior:'smooth'})}
function renderWeekly(){
  weekly.sort((a,b)=>b.date.localeCompare(a.date));
  const total=weekly.reduce((s,x)=>s+Number(x.money||0),0);
  $('w_count').textContent=weekly.length;$('w_total').textContent=money(total);$('w_avg').textContent=money(weekly.length?total/weekly.length:0);
  $('weeklyHistory').innerHTML=weekly.length?weekly.map(x=>`<div class="entry">
    <button class="entryHead"><div><div class="entryTitle">${esc(x.date)}</div><div class="entryMeta">${money(x.money)}</div></div><div class="chev">⌄</div></button>
    <div class="entryBody">
      <p><b>Принесло больше всего</b><br>${nl(x.best)}</p><p><b>Съело деньги</b><br>${nl(x.leaks)}</p><p><b>3 действия</b><br>${nl(x.actions)}</p><p><b>Потеря фокуса</b><br>${nl(x.time)}</p><p><b>Рычаг ×2</b><br>${nl(x.lever)}</p><p><b>Следующая цель</b><br>${nl(x.goal)}</p><p><b>3 задачи</b><br>${nl(x.tasks)}</p>
      <div class="btnrow"><button class="btn secondary wEdit" data-date="${x.date}">Редактировать</button><button class="btn secondary wExport" data-date="${x.date}">Экспорт</button><button class="btn danger wDelete" data-date="${x.date}">Удалить</button></div>
    </div></div>`).join(''):'<div class="empty">Пока нет обзоров.</div>';
}
$('weeklyHistory').onclick=async e=>{
  const d=e.target.dataset.date;if(!d)return;const x=weekly.find(v=>v.date===d);
  if(e.target.classList.contains('wEdit'))editWeekly(x);
  if(e.target.classList.contains('wExport'))oneExport('weekly',x);
  if(e.target.classList.contains('wDelete')&&confirmDelete(`CEO Review за ${d}`)){await api('weekly','DELETE',{date:d});await refreshAll()}
};
$('saveWeekly').onclick=async()=>{
  const x={date:$('w_date').value,money:+$('w_money').value,best:$('w_best').value,leaks:$('w_leaks').value,actions:$('w_actions').value,time:$('w_time').value,lever:$('w_lever').value,goal:$('w_goal').value,tasks:$('w_tasks').value};
  await api('weekly','POST',x);$('weeklyStatus').textContent='✓ Сохранено';resetWeekly();await refreshAll()
};

// Notes / knowledge UI lives in assets/notes.js (window.Notes)

function resetCapital(){['c_cash','c_wb','c_stock','c_suppliers','c_taxes','c_other','c_purchases','c_reserve','c_notes'].forEach(id=>$(id).value='');$('c_date').value=today()}
$('resetCapital').onclick=resetCapital;
function editCapital(x){$('c_date').value=x.date;$('c_cash').value=x.cash||0;$('c_wb').value=x.wb||0;$('c_stock').value=x.stock||0;$('c_suppliers').value=x.suppliers||0;$('c_taxes').value=x.taxes||0;$('c_other').value=x.other||0;$('c_purchases').value=x.purchases||0;$('c_reserve').value=x.reserve||0;$('c_notes').value=x.notes||'';window.scrollTo({top:0,behavior:'smooth'})}
function renderCapital(){
  capital.sort((a,b)=>b.date.localeCompare(a.date));
  if(!capital.length){$('c_liq').textContent='—';$('c_obl').textContent='—';$('c_total').textContent='—';$('capitalHistory').innerHTML='<div class="empty">Пока нет данных.</div>';return}
  const x=capital[0],obl=+x.suppliers + +x.taxes + +x.other,liq=+x.cash + +x.wb - obl - +x.purchases - +x.reserve,total=+x.cash + +x.wb + +x.stock - obl;
  $('c_liq').textContent=money(liq);$('c_obl').textContent=money(obl);$('c_total').textContent=money(total);
  $('capitalHistory').innerHTML=capital.map(v=>{const o=+v.suppliers + +v.taxes + +v.other,l=+v.cash + +v.wb-o-+v.purchases-+v.reserve;return `<div class="entry">
    <button class="entryHead"><div><div class="entryTitle">${esc(v.date)}</div><div class="entryMeta">Свободно ${money(l)}</div></div><div class="chev">⌄</div></button>
    <div class="entryBody"><p>Деньги: ${money(v.cash)}</p><p>WB: ${money(v.wb)}</p><p>Остатки: ${money(v.stock)}</p><p>Поставщики: ${money(v.suppliers)}</p><p>Налоги: ${money(v.taxes)}</p><p>Прочее: ${money(v.other)}</p><p>Закупки: ${money(v.purchases)}</p><p>Резерв: ${money(v.reserve)}</p><p><b>Комментарий</b><br>${nl(v.notes)}</p>
      <div class="btnrow"><button class="btn secondary cEdit" data-date="${v.date}">Редактировать</button><button class="btn secondary cExport" data-date="${v.date}">Экспорт</button><button class="btn danger cDelete" data-date="${v.date}">Удалить</button></div>
    </div></div>`}).join('');
}
$('capitalHistory').onclick=async e=>{
  const d=e.target.dataset.date;if(!d)return;const x=capital.find(v=>v.date===d);
  if(e.target.classList.contains('cEdit'))editCapital(x);
  if(e.target.classList.contains('cExport'))oneExport('capital',x);
  if(e.target.classList.contains('cDelete')&&confirmDelete(`запись капитала за ${d}`)){await api('capital','DELETE',{date:d});await refreshAll()}
};
$('saveCapital').onclick=async()=>{
  const x={date:$('c_date').value,cash:+$('c_cash').value,wb:+$('c_wb').value,stock:+$('c_stock').value,suppliers:+$('c_suppliers').value,taxes:+$('c_taxes').value,other:+$('c_other').value,purchases:+$('c_purchases').value,reserve:+$('c_reserve').value,notes:$('c_notes').value};
  await api('capital','POST',x);$('capitalStatus').textContent='✓ Сохранено';resetCapital();await refreshAll()
};

function selectedData(){
  const from=$('ex_from').value,to=$('ex_to').value,chosen=[...document.querySelectorAll('.ex_section:checked')].map(x=>x.value),all={daily,weekly,knowledge,capital},out={};
  for(const s of chosen)out[s]=all[s].filter(x=>(!from||x.date>=from)&&(!to||x.date<=to));
  return out;
}
function reportText(data,from='',to=''){
  let t=`ДНЕВНИК РОСТА\nПериод: ${from||'с начала'} — ${to||'по сегодня'}\nСформировано: ${new Date().toLocaleString('ru-RU')}\n\n`;
  if(data.daily?.length){t+='=== ЕЖЕДНЕВНИК ===\n';for(const x of data.daily)t+=`\n${x.date} · состояние ${x.mood}/10\nБлагодарность Аллаху: ${x.allah||'—'}\nБлагодарность себе: ${x.self||'—'}\nЧто понял: ${x.lesson||'—'}\nДоверяю Аллаху: ${x.trust||'—'}\nЧто зависит от меня: ${x.action||'—'}\nК 300 млн ₸: ${x.money||'—'}\nПотеря фокуса: ${x.focusloss||'—'}\n`}
  if(data.weekly?.length){t+='\n=== CEO REVIEW ===\n';for(const x of data.weekly)t+=`\n${x.date} · заработано ${money(x.money)}\nБольше всего денег: ${x.best||'—'}\nЧто съело деньги: ${x.leaks||'—'}\n3 действия: ${x.actions||'—'}\nПотеря фокуса: ${x.time||'—'}\nРычаг ×2: ${x.lever||'—'}\nСледующая цель: ${x.goal||'—'}\n3 денежные задачи: ${x.tasks||'—'}\n`}
  if(data.knowledge?.length){t+='\n=== БАЗА ЗНАНИЙ ===\n';for(const x of data.knowledge)t+=`\n${x.date} · ${x.category} · ${x.status}\nИсточник: ${x.source||'—'}\nЧто узнал: ${x.insight||'—'}\nПочему важно: ${x.why||'—'}\nКак применить: ${x.apply||'—'}\nРезультат: ${x.result||'—'}\nТеги: ${x.tags||'—'}\n`}
  if(data.capital?.length){t+='\n=== КАПИТАЛ ===\n';for(const x of data.capital){const obl=+x.suppliers + +x.taxes + +x.other,liq=+x.cash + +x.wb-obl-+x.purchases-+x.reserve,total=+x.cash + +x.wb + +x.stock-obl;t+=`\n${x.date}\nДеньги: ${money(x.cash)}\nWB к выплате: ${money(x.wb)}\nОстатки: ${money(x.stock)}\nОбязательства: ${money(obl)}\nБлижайшие закупки: ${money(x.purchases)}\nРезерв: ${money(x.reserve)}\nСвободная ликвидность: ${money(liq)}\nКапитал после обязательств: ${money(total)}\nКомментарий: ${x.notes||'—'}\n`}}
  return t;
}
function oneExport(section,obj){dl(`${section}_${obj.date}.txt`,reportText({[section]:[obj]},obj.date,obj.date),'text/plain;charset=utf-8')}
$('exportText').onclick=()=>{const data=selectedData(),from=$('ex_from').value,to=$('ex_to').value;dl(`dnevnik_${from||'start'}_${to||today()}.txt`,reportText(data,from,to),'text/plain;charset=utf-8')};
$('exportJson').onclick=()=>{const data=selectedData(),from=$('ex_from').value,to=$('ex_to').value;dl(`dnevnik_backup_${from||'start'}_${to||today()}.json`,JSON.stringify({version:3,exported_at:new Date().toISOString(),period:{from,to},data},null,2),'application/json;charset=utf-8')};

updateDailyTaskHint();
refreshAll().then(loadTodayModules).catch(e=>alert('Ошибка загрузки данных: '+e.message));
</script>
</body>
</html>
