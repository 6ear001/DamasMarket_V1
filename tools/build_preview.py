#!/usr/bin/env python3
"""
Erzeugt aus der PHP-Seite eine STATISCHE Vorschau im Ordner docs/ (für GitHub Pages).
Nur die öffentlichen Seiten. Verwendet die Produkte aus import/produkte.csv (falls vorhanden, sonst Beispieldaten).
Die Angebote in der Vorschau sind nur BEISPIELE (erfundene Aktionspreise), das steht im Banner.
Admin, Suche und Filter-Formulare funktionieren dort nicht.

Aufruf:  python3 tools/build_preview.py [repo-name]      (Standard: DamasMarket_V1)
"""
import os, re, shutil, subprocess, sys, tempfile, time, urllib.request, urllib.parse

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REPO = sys.argv[1] if len(sys.argv) > 1 else 'DamasMarket_V1'
PORT = 8765
OUT = os.path.join(ROOT, 'docs')
PREFIX = '/' + REPO + '/'

tmp = tempfile.mkdtemp(prefix='damas-preview-')
data = os.path.join(tmp, 'data'); os.makedirs(data)
web = os.path.join(tmp, 'web'); os.makedirs(web)
os.symlink(os.path.join(ROOT, 'public'), os.path.join(web, REPO))   # Seite unter /<repo>/ ausliefern, wie auf GitHub Pages
env = dict(os.environ, DAMAS_DATA_DIR=data)

# Daten: echte Produktliste (CSV) + Beispiel-Angebote, sonst komplette Beispieldaten
csv_path = os.path.join(ROOT, 'import', 'produkte.csv')
use_csv = os.path.exists(csv_path)
subprocess.run(['php', '-r', '''
define("APP_DIR", "%s/app"); define("PUBLIC_DIR", "%s/public");
require APP_DIR . "/bootstrap.php";
if (%s) {
    import_products_csv(file_get_contents("%s"));
    $pdo = db();
    $mon = date("Y-m-d", strtotime("monday this week")); $sun = date("Y-m-d", strtotime("sunday this week"));
    $ins = $pdo->prepare("INSERT INTO offers (product_id, title, unit_text, price_cents, old_price_cents, valid_from, valid_to, sort) VALUES (?,?,?,?,?,?,?,?)");
    $i = 0;
    foreach ($pdo->query("SELECT id, name, unit, price_cents FROM products WHERE price_cents >= 250 AND id %% 41 = 0 ORDER BY id LIMIT 8")->fetchAll() as $p) {
        $new = (int)(round($p["price_cents"] * 0.8 / 10) * 10 - 1);      // Beispiel: ca. -20 %%
        $ins->execute([$p["id"], $p["name"], $p["unit"], $new, $p["price_cents"], $mon, $sun, ++$i * 10]);
    }
} else { demo_load(); }
set_setting("announcement_on", "1");
set_setting("announcement", %s);
''' % (ROOT, ROOT, 'true' if use_csv else 'false', csv_path,
          '"Vorschau – Produkte aus eurer Liste, die Angebote sind nur Beispiele. Die echte Seite mit Admin-Bereich läuft auf PHP-Hosting."' if use_csv else '"Vorschau mit Beispieldaten – die echte Seite mit Admin-Bereich läuft auf PHP-Hosting."')], env=env, check=True)

srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{PORT}', '-t', web], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
time.sleep(1.2)

def slug(s): return re.sub(r'[^a-z0-9]+', '-', urllib.parse.unquote(s).lower()).strip('-')

def target(href):
    """Interne Adresse -> Dateiname der Vorschau (oder None, wenn nicht abbildbar)"""
    p = urllib.parse.urlparse(href.replace('&amp;', '&'))
    path = p.path
    if not path.startswith(PREFIX): return None
    name = path[len(PREFIX):] or 'index.php'
    q = urllib.parse.parse_qs(p.query)
    if name in ('index.php',): return 'index.html'
    if name == 'angebote.php': return 'angebote.html'
    if name == 'kontakt.php': return 'kontakt.html'
    if name == 'produkt.php' and q.get('id'): return f"produkt-{int(q['id'][0])}.html"
    if name == 'seite.php' and q.get('s'): return f"seite-{slug(q['s'][0])}.html"
    if name == 'produkte.php':
        if q.get('q'): return None                      # Suche gibt es in der Vorschau nicht
        page = int(q['seite'][0]) if q.get('seite') and q['seite'][0].isdigit() else 1
        sfx = f'-s{page}' if page > 1 else ''
        if q.get('kat'): return f"produkte-kat-{int(q['kat'][0])}{sfx}.html"
        if q.get('bereich'): return f"produkte-{slug(q['bereich'][0])}{sfx}.html"
        return f'produkte{sfx}.html'
    return None

def fetch(href):
    url = f'http://127.0.0.1:{PORT}' + href.replace('&amp;', '&')
    with urllib.request.urlopen(url, timeout=20) as r: return r.read().decode('utf-8')

def rewrite(html):
    # Statusanzeige ("Jetzt geöffnet") wäre in einer statischen Seite sofort veraltet -> entfernen
    html = re.sub(r'<span class="status [^"]*">.*?</span>', '', html, flags=re.S)
    html = re.sub(r'<p class="status-big [^"]*">.*?</p>', '', html, flags=re.S)
    def sub(m):
        attr, val = m.group(1), m.group(2)
        if val.startswith(PREFIX + 'assets/') or val.startswith(PREFIX + 'uploads/'):
            return f'{attr}="{val[len(PREFIX):]}"'
        t = target(val)
        if t: return f'{attr}="{t}"'
        if val.startswith(PREFIX) and attr == 'href' and 'produkte.php' in val: return f'{attr}="produkte.html"'
        if val.startswith(PREFIX) and attr == 'action': return f'{attr}="produkte.html"'
        return m.group(0)
    html = re.sub(r'\b(href|src|action)="([^"]*)"', sub, html)
    html = html.replace('<meta name="viewport"', '<meta name="robots" content="noindex">\n<meta name="viewport"', 1)
    return html

try:
    shutil.rmtree(OUT, ignore_errors=True); os.makedirs(OUT)
    seen, queue, pages = set(), [PREFIX + 'index.php'], {}
    while queue:
        href = queue.pop(0)
        t = target(href)
        if not t or t in seen: continue
        seen.add(t)
        html = fetch(href)
        for h in re.findall(r'\bhref="([^"]*)"', html):
            if h.startswith(PREFIX) and target(h) and target(h) not in seen: queue.append(h.replace('&amp;', '&'))
        pages[t] = html
    for t, html in pages.items():
        with open(os.path.join(OUT, t), 'w', encoding='utf-8') as f: f.write(rewrite(html))
    shutil.copytree(os.path.join(ROOT, 'public', 'assets'), os.path.join(OUT, 'assets'), ignore=shutil.ignore_patterns('LICENSE*'))
    shutil.copy(os.path.join(ROOT, 'public', 'assets', 'fonts', 'LICENSE-Barlow-OFL.txt'), os.path.join(OUT, 'assets', 'fonts'))
    open(os.path.join(OUT, '.nojekyll'), 'w').close()
    print(f'{len(pages)} Seiten nach docs/ geschrieben')
finally:
    srv.terminate(); shutil.rmtree(tmp, ignore_errors=True)
