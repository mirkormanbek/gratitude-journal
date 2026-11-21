"""UI harness: render the real index.php (PHP tags stripped) with mocked endpoints and screenshot it.
Usage: python3 shoot.py <repo_dir> <out_dir> <scenario...>
"""
import json, re, sys, os, shutil, threading, http.server, socketserver, functools
from playwright.sync_api import sync_playwright

repo, out = sys.argv[1], sys.argv[2]
scen = sys.argv[3:] or ['overview']
site = '/tmp/uisite'
shutil.rmtree(site, ignore_errors=True)
shutil.copytree(os.path.join(repo, 'assets'), os.path.join(site, 'assets'))
html = open(os.path.join(repo, 'index.php'), encoding='utf-8').read()
html = html[html.index('<!doctype html>'):] if '<!doctype html>' in html else html
html = re.sub(r"<\?=json_encode\(\$_SESSION\['csrf'\][^?]*\?>", '"t"', html)
html = re.sub(r'<\?=.*?\?>', '1', html, flags=re.S)
html = re.sub(r'<\?php.*?\?>', '', html, flags=re.S)
open(os.path.join(site, 'index.html'), 'w', encoding='utf-8').write(html)
fx = json.load(open(os.path.join(os.path.dirname(__file__), 'fixtures.json'), encoding='utf-8'))
extra = {}
for f in os.listdir(os.path.dirname(__file__) or '.'):
    if f.startswith('fx_') and f.endswith('.json'):
        extra.update(json.load(open(os.path.join(os.path.dirname(__file__), f), encoding='utf-8')))

Handler = functools.partial(http.server.SimpleHTTPRequestHandler, directory=site)
class Q(http.server.SimpleHTTPRequestHandler):
    def log_message(self, *a): pass
httpd = socketserver.TCPServer(('127.0.0.1', 0), functools.partial(Q, directory=site))
port = httpd.server_address[1]
threading.Thread(target=httpd.serve_forever, daemon=True).start()

def route(r):
    url = r.request.url
    m = re.search(r'/(\w+)\.php\?(?:action|resource)=(\w+)', url)
    ep, act = (m.group(1), m.group(2)) if m else (None, None)
    body = {'ok': True}
    key = f'{ep}.{act}'
    if key in extra: body = extra[key]
    elif ep == 'workouts' and act in fx: body = fx[act]
    elif ep == 'api': body = []
    elif ep == 'tasks': body = {'ok': True, 'tasks': [], 'stats': {}}
    elif ep == 'projects': body = {'ok': True, 'projects': [], 'task_project_map': {}}
    elif ep == 'ai': body = {'ok': False, 'error': 'mock'}
    r.fulfill(status=200, content_type='application/json', body=json.dumps(body, ensure_ascii=False))

os.makedirs(out, exist_ok=True)
errors = []
with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox'])
    for sc in scen:
        width = 1280 if sc.endswith('-desk') else 390
        ctx = b.new_context(viewport={'width': width, 'height': 844}, device_scale_factor=2 if width < 900 else 1, locale='ru-RU', timezone_id='Asia/Almaty')
        pg = ctx.new_page()
        pg.on('pageerror', lambda e: errors.append(f'{sc}: {e}'))
        pg.on('console', lambda m: errors.append(f'{sc} console: {m.text}') if m.type == 'error' else None)
        pg.on('dialog', lambda d: d.dismiss())
        pg.route('**/*.php*', route)
        pg.add_init_script("Date.now=(o=>()=>o()+0)(Date.now);")
        pg.goto(f'http://127.0.0.1:{port}/index.html')
        pg.wait_for_timeout(700)
        steps = sc.replace('-desk', '').split('+')
        for st in steps:
            pg.evaluate(f"window.__step && window.__step({json.dumps(st)})") if False else None
            if st == 'overview': pg.evaluate("openPanel('workouts')")
            elif st in ('calendar', 'program', 'progress'):
                pg.evaluate("openPanel('workouts')"); pg.wait_for_timeout(300); pg.click(f'#wkTabs [data-seg="{st}"]')
            elif st == 'gym': pg.evaluate("Workouts.openGym(555)")
            elif st == 'gymset':
                pg.click('.wk-exc >> nth=0 >> [data-ok] >> nth=0'); pg.wait_for_timeout(300)
                pg.fill('.wk-exc >> nth=0 >> [data-r] >> nth=0', '8'); pg.click('.wk-exc >> nth=0 >> [data-ok] >> nth=0')
            elif st == 'exstat': pg.click('[data-act="ex-stats"] >> nth=0')
            elif st == 'exedit': pg.click('[data-act="ex-edit"] >> nth=3')
            elif st == 'calact': pg.click('[data-act="cal"] >> nth=5')
            elif st == 'home': pg.evaluate("openPanel('home')")
            elif st == 'plan': pg.evaluate("openPanel('plan')")
            elif st.startswith('eval:'): pg.evaluate(st[5:])
            elif st.startswith('click:'): pg.click(st[6:])
            elif st.startswith('fill:'):
                sel, val = st[5:].split('=', 1); pg.fill(sel, val)
            elif st == 'scroll': pg.mouse.wheel(0, 700)
            elif st == 'sheetscroll': pg.evaluate("document.querySelector('.ui-sheet-body:last-of-type') && [...document.querySelectorAll('.ui-sheet-body')].pop().scrollBy(0,600)")
            pg.wait_for_timeout(450)
        pg.screenshot(path=os.path.join(out, sc.replace('+', '_').replace(':', '').replace('/', '')[:60] + '.png'), full_page=False)
        ctx.close()
    b.close()
httpd.shutdown()
print('\n'.join(errors) if errors else 'no JS errors')
