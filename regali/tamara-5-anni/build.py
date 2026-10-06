import subprocess, sys, pathlib
SVG = {
'cover': '''<svg viewBox="0 0 300 300"><rect width="300" height="300" fill="#fde6f1"/><circle cx="240" cy="60" r="30" fill="#ffe28a"/>
<path d="M0 230 Q150 180 300 230 V300 H0Z" fill="#b8e6b0"/>
<g fill="#fff" stroke="#c98bb0" stroke-width="3"><ellipse cx="150" cy="200" rx="60" ry="30"/><path d="M190 190 L215 140 L235 150 L210 200Z"/><circle cx="225" cy="140" r="18"/></g>
<path d="M230 124 L245 85 L238 128Z" fill="#ffd34d"/><path d="M200 140 q-15 20 0 40 q-10-20 10-40" fill="#d9a3f0"/>
<g stroke="#c98bb0" stroke-width="3"><line x1="110" y1="225" x2="110" y2="255"/><line x1="130" y1="225" x2="130" y2="255"/><line x1="170" y1="225" x2="170" y2="255"/><line x1="190" y1="225" x2="190" y2="255"/></g>
<circle cx="140" cy="140" r="16" fill="#ffd9b8"/><path d="M128 132 l6-14 6 8 6-8 6 14z" fill="#ffd34d"/><path d="M125 160 h30 l10 35 h-50z" fill="#f08cbf"/>
<g fill="#9fd3ff"><path d="M60 80 q-15-15-20 0 q5 15 20 0 q15-15 20 0 q-5 15-20 0"/></g><g fill="#ffb3d9"><path d="M90 50 q-10-10-14 0 q4 10 14 0 q10-10 14 0 q-4 10-14 0"/></g></svg>''',
'bfly': '''<svg viewBox="0 0 300 160"><g fill="#ffb3d9"><path d="M80 80 q-40-40-50 0 q10 40 50 0 q40-40 50 0 q-10 40-50 0"/></g><g fill="#9fd3ff"><path d="M220 60 q-30-30-38 0 q8 30 38 0 q30-30 38 0 q-8 30-38 0"/></g><g fill="#ffe28a"><path d="M170 120 q-20-20-26 0 q6 20 26 0 q20-20 26 0 q-6 20-26 0"/></g></svg>''',
'star': '''<svg viewBox="0 0 300 120"><g fill="#ffd34d"><polygon points="150,10 162,45 198,45 168,66 180,100 150,79 120,100 132,66 102,45 138,45"/><circle cx="60" cy="40" r="5"/><circle cx="240" cy="30" r="6"/><circle cx="260" cy="90" r="4"/><circle cx="40" cy="95" r="4"/></g></svg>''',
'cake': '''<svg viewBox="0 0 300 180"><rect x="80" y="90" width="140" height="70" rx="10" fill="#f6b6d3"/><rect x="80" y="90" width="140" height="18" fill="#fff"/><g fill="#9fd3ff"><rect x="105" y="55" width="8" height="35"/><rect x="128" y="55" width="8" height="35"/><rect x="151" y="55" width="8" height="35"/><rect x="174" y="55" width="8" height="35"/><rect x="197" y="55" width="8" height="35"/></g><g fill="#ffb02e"><ellipse cx="109" cy="48" rx="5" ry="9"/><ellipse cx="132" cy="48" rx="5" ry="9"/><ellipse cx="155" cy="48" rx="5" ry="9"/><ellipse cx="178" cy="48" rx="5" ry="9"/><ellipse cx="201" cy="48" rx="5" ry="9"/></g></svg>''',
}
T = {
'it': dict(file='la-principessa-mara-e-l-unicorno', serie='Una fiaba di compleanno', titolo='La Principessa Mara<br>e l’Unicorno delle Farfalle', autore='Vlad', ded_t='Per Tamara', ded='Buon compleanno, Tamara!<br>Oggi compi cinque anni:<br>che il tuo giorno sia pieno di farfalle,<br>di sorrisi e di magia.<br><br><i>Con tanto affetto,<br>Mara</i>', fine='Fine', edit='Vlad Editore · Taormina'),
'ru': dict(file='princessa-mara-i-edinorog', serie='Сказка на день рождения', titolo='Принцесса Мара<br>и Единорог Бабочек', autore='Влад', ded_t='Для Тамары', ded='С днём рождения, Тамара!<br>Сегодня тебе пять лет:<br>пусть твой день будет полон бабочек,<br>улыбок и волшебства.<br><br><i>С любовью,<br>Мара</i>', fine='Конец', edit='Vlad Editore · Таормина'),
}
pages = {'it': [
('bfly','C’era una volta, in un piccolo regno pieno di fiori, una principessa di nome <b>Mara</b>. Aveva una coroncina d’oro un po’ storta, un vestito rosa e un sorriso grande grande.'),
(None,'Ogni mattina Mara correva nel giardino del castello a salutare le farfalle: quelle rosa, quelle azzurre e quelle gialle come il sole. «Buongiorno, farfalle!» diceva. E le farfalle le giravano intorno come un girotondo.'),
('star','Una mattina, però, le farfalle erano tristi. Volavano basse basse e non giocavano più. «Che cosa succede?» chiese Mara. Una farfallina azzurra le si posò sul naso e sussurrò: «Abbiamo perso i nostri colori più belli. Solo l’Unicorno delle Farfalle può aiutarci.»'),
(None,'Mara non ci pensò due volte. Prese il suo cestino, ci mise dentro una mela, un biscotto e un nastro rosa, e partì. Le farfalle le mostravano la strada: oltre il ponticello, oltre il prato delle margherite, fino al bosco che profumava di fragole.'),
('cover','In mezzo al bosco, accanto a un ruscello, c’era lui: un unicorno bianco come la neve, con un corno d’oro e una criniera di tutti i colori. Ma anche l’unicorno era triste. «Mi sono punto con una spina» disse piano, «e senza la mia magia le farfalle perdono i loro colori.»'),
(None,'Mara si inginocchiò vicino a lui. Con le mani piano piano tolse la spina, poi legò il suo nastro rosa intorno alla zampa, come un piccolo cerotto. «Ecco fatto» disse. «Adesso ti faccio una carezza, così passa tutto.» E gli diede anche la sua mela.'),
('bfly','L’unicorno sorrise. Il suo corno d’oro cominciò a brillare, sempre di più, sempre di più… e una pioggia di scintille colorate volò su tutto il bosco. Le farfalle si riempirono di colori: rosa, azzurro, giallo, viola e verde. Erano più belle di prima!'),
(None,'«Grazie, principessa Mara» disse l’unicorno. «Hai un cuore gentile, e la gentilezza è la magia più forte di tutte. Sali sulla mia groppa: ti riporto a casa.» E volarono sopra il bosco, sopra il prato e sopra il ponticello, con mille farfalle intorno.'),
('cake','Al castello era pronta una grande festa. C’era una torta con cinque candeline, e tutti cantavano. Le farfalle danzavano nel cielo e l’unicorno faceva brillare le stelle. Mara soffiò sulle candeline ed espresse un desiderio. Quale? È un segreto… ma si è avverato.'),
('star','E da quel giorno, ogni volta che vedi una farfalla colorata, è il saluto della principessa Mara e del suo amico unicorno. Ti stanno dicendo: «Buon compleanno!»'),
], 'ru': [
('bfly','Жила-была в маленьком цветочном королевстве принцесса по имени <b>Мара</b>. У неё была золотая коронка, чуть-чуть набекрень, розовое платье и большая-пребольшая улыбка.'),
(None,'Каждое утро Мара бежала в сад замка здороваться с бабочками: с розовыми, с голубыми и с жёлтыми, как солнышко. «Доброе утро, бабочки!» — говорила она. И бабочки кружились вокруг неё, как в хороводе.'),
('star','Но однажды утром бабочки были грустные. Они летали низко-низко и совсем не играли. «Что случилось?» — спросила Мара. Голубая бабочка села ей на нос и прошептала: «Мы потеряли наши самые красивые цвета. Помочь нам может только Единорог Бабочек».'),
(None,'Мара ни минуты не раздумывала. Она взяла корзинку, положила в неё яблоко, печенье и розовую ленточку — и отправилась в путь. Бабочки показывали дорогу: через мостик, через ромашковый луг, до самого леса, который пах земляникой.'),
('cover','Посреди леса, у ручейка, стоял он: единорог, белый как снег, с золотым рогом и разноцветной гривой. Но и единорог был грустный. «Я уколол ногу колючкой, — тихо сказал он, — а без моего волшебства бабочки теряют свои цвета».'),
(None,'Мара присела рядом. Осторожно-осторожно она вынула колючку, а потом завязала на ноге розовую ленточку, как маленький пластырь. «Вот и всё, — сказала она. — Сейчас поглажу, и всё пройдёт». И отдала ему своё яблоко.'),
('bfly','Единорог улыбнулся. Его золотой рог засиял, всё ярче и ярче… и над лесом посыпался дождь из разноцветных искорок. Бабочки снова стали яркими: розовыми, голубыми, жёлтыми, фиолетовыми и зелёными. Ещё красивее, чем раньше!'),
(None,'«Спасибо, принцесса Мара, — сказал единорог. — У тебя доброе сердце, а доброта — самое сильное волшебство на свете. Садись ко мне на спину: я отвезу тебя домой». И они полетели над лесом, над лугом и над мостиком, а вокруг кружилась тысяча бабочек.'),
('cake','В замке уже ждал большой праздник. Был торт с пятью свечками, и все пели песни. Бабочки танцевали в небе, а единорог зажигал звёзды. Мара задула свечки и загадала желание. Какое? Это секрет… но оно сбылось.'),
('star','И с того самого дня, когда ты видишь яркую бабочку, — это привет от принцессы Мары и её друга единорога. Они говорят тебе: «С днём рождения!»'),
]}
BG=['#fff0f7','#eef7ff','#fffbe6','#f3edff','#ecfbef']
CSS = '''.p img{width:100%;border-radius:6mm;margin-bottom:7mm;box-shadow:0 2mm 4mm #0002}@page{size:148mm 210mm;margin:0}body{margin:0;font-family:Georgia,serif;color:#3a2a33}
.p{width:148mm;height:210mm;box-sizing:border-box;padding:18mm 16mm;page-break-after:always;display:flex;flex-direction:column;justify-content:center;background:#fffaf5;position:relative}
.p svg{width:100%;max-height:80mm;margin-bottom:8mm}.t{font-size:15.5pt;line-height:1.6;text-align:left}
.n{position:absolute;bottom:9mm;left:0;right:0;text-align:center;font-size:9pt;color:#c98bb0}
.cov{background:#fde6f1;text-align:center;justify-content:flex-start;padding-top:12mm}.cov h1{font-size:19pt !important;text-shadow:0 0 6px #fff,0 0 12px #fff;font-size:25pt;color:#a8437a;margin:4mm 0}.cov .s{color:#8c5a2b;letter-spacing:2px;font-size:10pt}
.cov svg{max-height:110mm;margin:6mm 0}.c{text-align:center}.c h2{color:#a8437a;font-size:20pt}.c p{font-size:14pt;line-height:1.7}'''
def build(l):
    d=T[l]; h=[f'<div class="p cov" style="background:url(../immagini/cover.jpg) center/cover"><div class="s">{d["serie"].upper()}</div><h1>{d["titolo"]}</h1><div style="flex:1"></div><div class="s" style="background:#fffc;border-radius:8px;padding:2mm">{d["autore"]} · {d["edit"]}</div></div>',
       f'<div class="p c"><h2>{d["ded_t"]}</h2><p>{d["ded"]}</p></div>']
    for i,(img,txt) in enumerate(pages[l],1):
        im=pathlib.Path(f'immagini/p{i}.jpg'); pic=f'<img src="../{im}">' if im.exists() else SVG[img or 'bfly']
        h.append(f'<div class="p" style="background:{BG[i%len(BG)]}">{pic}<div class="t">{txt}</div><div class="n">{i}</div></div>')
    h.append(f'<div class="p c">{SVG["bfly"]}<h2>{d["fine"]}</h2><p style="font-size:10pt;color:#8c5a2b">{d["edit"]}</p></div>')
    pathlib.Path('build').mkdir(exist_ok=True); f=pathlib.Path(f'build/{l}.html')
    f.write_text(f'<!doctype html><meta charset=utf-8><style>{CSS}</style>'+''.join(h))
    subprocess.run(['/opt/pw-browsers/chromium-1194/chrome-linux/chrome','--headless','--no-sandbox','--no-pdf-header-footer',f'--print-to-pdf=build/{d["file"]}.pdf',f.resolve().as_uri()],check=True,capture_output=True)
for l in T: build(l)
# copertina come immagine unica: il titolo appare uguale in ogni lettore PDF
for l,d in T.items():
    p=f'build/{d["file"]}.pdf'
    subprocess.run(['pdftoppm','-r','250','-f','1','-l','1','-jpeg','-singlefile',p,'build/cov'],check=True)
    pathlib.Path('build/cov.html').write_text('<!doctype html><style>@page{size:148mm 210mm;margin:0}body{margin:0}img{width:148mm;height:210mm;display:block}</style><img src="cov.jpg">')
    subprocess.run(['/opt/pw-browsers/chromium-1194/chrome-linux/chrome','--headless','--no-sandbox','--no-pdf-header-footer','--print-to-pdf=build/cov.pdf',pathlib.Path('build/cov.html').resolve().as_uri()],check=True,capture_output=True)
    subprocess.run(['pdfseparate','-f','2',p,'build/s-%03d.pdf'],check=True)
    s=sorted(pathlib.Path('build').glob('s-*.pdf'))
    subprocess.run(['pdfunite','build/cov.pdf',*map(str,s),p],check=True)
    for f in s+[pathlib.Path(x) for x in ('build/cov.pdf','build/cov.jpg','build/cov.html')]: f.unlink()
