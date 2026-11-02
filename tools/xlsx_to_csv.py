#!/usr/bin/env python3
"""
Wandelt die Produktliste (Excel) in die CSV für den Admin-Import um (Admin → Produkte → Excel/CSV-Import).

Aufruf:  python3 tools/xlsx_to_csv.py produkte.xlsx [import/produkte.csv]

Was passiert:
  * nur der deutsche Name wird genutzt (die Spalten "Name EN/AR" enthielten falsch kopierte Namen)
  * Kassen-Platzhalter (Preis 0, "Angebot 3x5€", "Nonfood", "Getränk") werden ausgelassen
  * Mengenangaben ("900g", "2L", "20 Beutel", "6x") wandern aus dem Namen in die Spalte "Einheit"
  * offensichtliche Tippfehler werden korrigiert (Liste TYPOS unten)
  * jedes Produkt bekommt anhand von Stichwörtern eine Kategorie (Liste RULES unten)
  * interne Kassen-Barcodes (beginnen mit 2) werden nicht übernommen – dafür gibt es keine Produktbilder
"""
import csv, re, sys, collections
import openpyxl

SRC = sys.argv[1] if len(sys.argv) > 1 else 'produkte.xlsx'
DST = sys.argv[2] if len(sys.argv) > 2 else 'import/produkte.csv'

PLACEHOLDER = re.compile(r'^(angebot|getränk|nonfood)\b', re.I)

# (falsch, richtig) – wird als ganzes Wort bzw. Teilwort ersetzt
TYPOS = [
    ('Ölivenöl', 'Olivenöl'), ('Bannanen', 'Bananen'), ('Tomtatenmark', 'Tomatenmark'), ('Tuhnfisch', 'Thunfisch'),
    ('Kirscherbsen', 'Kichererbsen'), ('Hänchen', 'Hähnchen'), ('Hänschen', 'Hähnchen'), ('Hänchengeschack', 'Hähnchengeschmack'),
    ('Hänchengschmack', 'Hähnchengeschmack'), ('Hänchengeschmack', 'Hähnchengeschmack'), ('Hähnchengeschack', 'Hähnchengeschmack'),
    ('Pfeige', 'Feige'), ('Peparoni', 'Peperoni'), ('Pfeefferonen', 'Pfefferoni'), ('Grieschiche', 'Griechische'), ('Wallnuss', 'Walnuss'),
    ('Läbanesische', 'Libanesische'), ('Schockolade', 'Schokolade'), ('Olivien', 'Oliven'), ('Olievenscheiben', 'Olivenscheiben'),
    ('Gr[ne', 'Grüne'), ('Karadamom', 'Kardamom'), ('Gewurzzubereitung', 'Gewürzzubereitung'), ('Weizengrüze', 'Weizengrütze'),
    ('Yörükog´lu', 'Yörükoğlu'), ('Günweizen', 'Grünweizen'), ('Peache', 'Peach'), ('Fruite', 'Fruit'), ('Pickeld', 'Pickled'),
    ('Papper', 'Pepper'), ('Pickels', 'Pickles'), ('Cofee', 'Coffee'), ('Cylon', 'Ceylon'), ('Ceylom', 'Ceylon'), ('Cappucciono', 'Cappuccino'),
    ('Clasic', 'Classic'), ('Mahnmoud', 'Mahmoud'), ('Sparking', 'Sparkling'), ('Pomegrante', 'Pomegranate'), ('Lilag', 'Lilac'),
    ('Sugerfree', 'Sugarfree'), ('Redbull', 'Red Bull'), ('Vegtarisches', 'Vegetarisches'), ('Gerösteter', 'Gerösteter'),
    ('Schrfe', 'Scharfe'), ('Kondesmilch', 'Kondensmilch'), ('Granatapfle', 'Granatapfel'), ('Multivitamie', 'Multivitamin'),
    ('Pistachio', 'Pistazie'), ('Eistee', 'Eistee'), ('Pisirme Süresi', 'Pisirme Süresi'), ('Meersalzgrob', 'Meersalz grob'),
    ('Gewürzgurken1000g', 'Gewürzgurken 1000g'), ('Tosya Reis1000g', 'Tosya Reis 1000g'), ('Edition250ml', 'Edition 250ml'),
    ('Sparkling Water', 'Sparkling Water'), ('Mate Weiß', 'Mate Weiß'), ('Erdbeeren  ', 'Erdbeeren '), ('Gilani', 'Gilani'),
    ('Zattar', 'Zaatar'), ('Sonnenblümenöl', 'Sonnenblumenöl'),
]

# Reihenfolge wichtig: erste passende Regel gewinnt. (Kategorie, Bereich, Stichwörter als Regex)
CAT = {  # Kategorie -> Bereich
    'Reis, Bulgur & Getreide': 'Lebensmittel', 'Nudeln & Teigwaren': 'Lebensmittel', 'Linsen & Hülsenfrüchte': 'Lebensmittel',
    'Konserven & Fertiggerichte': 'Lebensmittel', 'Öle, Fette & Ghee': 'Lebensmittel', 'Oliven & Eingelegtes': 'Lebensmittel',
    'Tomatenmark & Pasten': 'Lebensmittel', 'Gewürze & Kräuter': 'Lebensmittel', 'Milchprodukte & Käse': 'Lebensmittel',
    'Fleisch & Wurst (halal)': 'Lebensmittel', 'Brot & Backwaren': 'Lebensmittel', 'Backen & Zutaten': 'Lebensmittel',
    'Marmelade & Desserts': 'Lebensmittel', 'Süßwaren & Snacks': 'Lebensmittel', 'Tee & Kaffee': 'Lebensmittel',
    'Weitere Lebensmittel': 'Lebensmittel',
    'Softdrinks & Säfte': 'Getränke', 'Energy Drinks': 'Getränke', 'Ayran, Joghurtdrinks & Wasser': 'Getränke',
}
FOOD_RULES = [
    ('Fleisch & Wurst (halal)', r'sucuk|knoblauchwurst|salam\b|mortadella|sosis|tavuksosis|hackfleisch|pastirma|parmak|doyum'),
    ('Konserven & Fertiggerichte', r'sardinen|thunfisch|tuhnfisch|eintopf|humus|falafel|mousakka|cig k[öo]fte|lahmacun|su b[öo]regi|soup starter|auberginenpüree|spring roll'),
    ('Öle, Fette & Ghee', r'\böl\b|olivenöl|sonnenblumenöl|ghee'),
    ('Oliven & Eingelegtes', r'oliven|zeytin|olivensalat|olivenscheiben|salqini|eingelegt|pickle|pickled|gurken|peperoni|pfefferonen|jalapeno|pfefferoni|weinblätter|asma|yaprak\b(?! cay)|makdous|rüben|artischocke|trüffel|lupine|mischgemüse|pickels|paprika puree'),
    ('Tomatenmark & Pasten', r'tomatenmark|tomatenpaste|tomatenpuree|tomatenpüree|gehackte tomaten|paprika puree|tahin|sesambrei|sesampaste'),
    ('Milchprodukte & Käse', r'käse|kashkaval|peynir|labne|joghurt|kaymak|frischkäse|vache|puck|kiri\b|happy cow|tereyagi|nido|kondensmilch|chantilly|creamig|kombi|keshk|maya|mayasi|cream$'),
    ('Tee & Kaffee', r'\btee\b|\btea\b|kaffee|coffee|cofee|\bmate\b|nescaf|cappuc|\bcay\b|sahlep|roselle|kahva|goran|alattar'),
    ('Marmelade & Desserts', r'marmelade|jelly|pudding|dekkade|dattelmelasse|\bcream\b|zucker'),
    ('Süßwaren & Snacks', r'helva|halawa|halva|mandeln|marshmallow|chamallows|chips|popcorn|mallow\b(?! 200)'),
    ('Nudeln & Teigwaren', r'nudel|spaghetti|vermicelli|teigblätter|teigfäden|yufka|pastry|fadennudeln|indomie|hörchen|muschel|spiralen|penne'),
    ('Brot & Backwaren', r'fladen|brot\b|tortiller'),
    ('Backen & Zutaten', r'maisstärke|maismehl|maisgrieß|hartweizengrieß|grießmehl|\bgrieß\b|feinergrieß|mehl'),
    ('Linsen & Hülsenfrüchte', r'linsen|bohnen|kichererbsen|favabohnen|augenbohnen|dal\b|kidney|lupin|fasulye'),
    ('Reis, Bulgur & Getreide', r'reis\b|reis\d|basmati|bulgur|weizen|couscous|grünweizen|kurzkorn|kurykorn|grieß|prinic|prinç'),
    ('Gewürze & Kräuter', r'zaatar|zattar|thymian|kreuzkümmel|minze|oregano|curry|chili|paprika|salz|kümmel|kräuter|mallow|zarschek|gewürz'),
]
DRINK_RULES = [
    ('Energy Drinks', r'red ?bull|energy|hell\b'),
    ('Ayran, Joghurtdrinks & Wasser', r'ayran|\bwater\b|\bwasser\b|\beaude\b'),
    ('Softdrinks & Säfte', r'.'),
]

UNIT_RE = re.compile(r'(?<![\w.,])(\d+(?:[.,]\d+)?)\s*\.?\s*(kg|g|ml|cl|l)\b', re.I)
BEUTEL_RE = re.compile(r'(\d+)\s*beutel', re.I)
PACK_RE = re.compile(r'(?<![\w.,])(\d+)\s*x\b(?!\s*\d)', re.I)


def clean(s):
    s = ' '.join(str(s).split())
    for a, b in TYPOS:
        s = s.replace(a, b)
    return s


def pretty_unit(num, u):
    """'4500','g' -> '4,5 kg';  '1000','ml' -> '1 l';  '2.25','l' -> '2,25 l'"""
    v = float(num.replace(',', '.'))
    if u == 'g' and v >= 1000: v, u = v / 1000, 'kg'
    elif u == 'ml' and v >= 1000: v, u = v / 1000, 'l'
    txt = (f'{v:.3f}'.rstrip('0').rstrip('.')).replace('.', ',')
    return f'{txt} {u}'


def split_unit(name):
    """-> (name ohne Mengenangabe, Einheit)"""
    unit = []
    m = UNIT_RE.search(name)
    if m:
        unit.append(pretty_unit(m.group(1), m.group(2).lower()))
        name = (name[:m.start()] + name[m.end():])
    b = BEUTEL_RE.search(name)
    if b:
        unit.append(f'{b.group(1)} Beutel'); name = name[:b.start()] + name[b.end():]
    p = PACK_RE.search(name)
    if p:
        unit.append(f'{p.group(1)}er-Pack'); name = name[:p.start()] + name[p.end():]
    t = re.search(r'\s+x\s*(\d+)\s*$', name)  # "500ml x 12"
    if t:
        unit.append(f'{t.group(1)}er-Pack'); name = name[:t.start()]
    name = ' '.join(name.replace(' .', '').split()).strip(' -.,')
    return name, ' · '.join(unit)


# Feste Zuordnungen, die über die Excel-Kategorie hinweg gelten (Excel hatte z. B. Ayran unter "Lebensmittel")
OVERRIDES = [(r'\bayran\b', 'Ayran, Joghurtdrinks & Wasser'), (r'^saadi\b', 'Softdrinks & Säfte')]


def categorize(name, excel_cat):
    low = name.lower()
    for rx, cat in OVERRIDES:
        if re.search(rx, low): return cat
    if excel_cat.startswith('Getränke'):
        rules = DRINK_RULES
    elif excel_cat == 'Käse':
        return 'Milchprodukte & Käse'
    else:
        rules = FOOD_RULES
    for cat, rx in rules:
        if re.search(rx, low): return cat
    return 'Weitere Lebensmittel'


def real_barcode(bc):
    bc = str(bc).strip()
    return bc if (re.fullmatch(r'\d{8}|\d{12,14}', bc) and not bc.startswith('2') and not re.match(r'^(\d{3})\1', bc)) else ''


wb = openpyxl.load_workbook(SRC, data_only=True)
ws = wb.active
out, skipped, stats = [], [], collections.Counter()
for r in ws.iter_rows(min_row=2, values_only=True):
    bc, de, price, typ, excel_cat = r[0], r[1], r[4], r[5], str(r[6] or '')
    if not de: continue
    name = clean(de)
    if PLACEHOLDER.match(name) or not price:
        skipped.append(name); continue
    name, unit = split_unit(name)
    if 'Weight' in str(typ):
        unit = 'pro kg'
    cat = categorize(name, excel_cat)
    group = CAT[cat]
    out.append([name, '', cat, unit, f'{float(price):.2f}'.replace('.', ','), '', '', 'ja', real_barcode(bc), group])
    stats[cat] += 1

out.sort(key=lambda x: (x[2], x[0].lower()))
import os
os.makedirs(os.path.dirname(DST) or '.', exist_ok=True)
with open(DST, 'w', encoding='utf-8-sig', newline='') as f:
    w = csv.writer(f, delimiter=';')
    w.writerow(['Name', 'Name (Arabisch/Türkisch)', 'Kategorie', 'Einheit', 'Preis', 'Herkunft', 'Beschreibung', 'Sichtbar', 'Barcode', 'Bereich'])
    w.writerows(out)

print(f'{len(out)} Produkte geschrieben nach {DST}; übersprungen: {skipped}')
print('ohne Barcode-Suche möglich:', sum(1 for o in out if not o[8]))
for c, n in stats.most_common(): print(f'  {n:4d}  {c}')
if '--debug' in sys.argv:
    for o in out:
        if o[2] == 'Weitere Lebensmittel': print('  ?', o[0])
