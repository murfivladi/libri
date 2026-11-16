# Testi del № 0. Ogni lingua: dizionario di frammenti HTML usati da gazzettona/build.py
NUMERO = "0"
FILE = "00"

IT = dict(
lingua="it", data="Domenica 16 novembre 2026", numero="Anno I · № 0 (zero, ma già caldo)",
motto="Caldo come l'Etna", prezzo="Gratis, ma con un sorriso", editore="Vlad Editore · Taormina",
pag="Pagina",

p1_occhiello="La redazione indaga · Guerra civile gastronomica",
p1_titolo="La guerra dell'arancin*",
p1_sommario="L'isola spaccata in due da una vocale. Al bar del Corso volano tazzine. La nostra inviata è sul fronte.",
p1_firma="di Vera Russo, inviata speciale (12 anni, prima media, nessuna paura)",
p1_testo="""
<p class="capolettera"><b>Taormina.</b> Sabato mattina, al bar di mio padre sul Corso, è scoppiata la guerra. Un signore di Palermo ha chiesto «un'arancina». Un signore di Catania, al bancone accanto, ha posato il caffè come si posa una spada.</p>
<p>«Arancino», ha detto. «Maschio. A punta. Come l'Etna.»</p>
<p>«Arancina», ha risposto l'altro. «Femmina. Tonda. Come l'arancia, che è femmina pure lei.»</p>
<p>Io ho preso il quaderno. Una giornalista vera non si spaventa.</p>
<h4>Le posizioni</h4>
<p>Il fronte occidentale (Palermo) sostiene che la forma tonda è l'originale e che la «a» finale è una questione di eleganza. Il fronte orientale (Catania) risponde che la punta ricorda il vulcano, e che con un vulcano non si discute.</p>
<h4>I neutrali</h4>
<p>Mio padre ha dichiarato: «Io li vendo tutti e due, chiamateli come volete, basta che pagate.» È stato accusato di collaborazionismo da entrambe le parti.</p>
<h4>La parola agli esperti</h4>
<p>Ho intervistato mia nonna Concetta, che ha detto: «Arancino. Fine.» Poi ho intervistato la mia babuška Galina, che ha detto: «Da noi ci sono i pirožki e nessuno litiga.» Poi hanno litigato loro due, per due ore, sul ripieno.</p>
<h4>La proposta di pace</h4>
<p>Dopo lunga inchiesta, La Gazzettona propone un trattato: d'ora in poi, nei bar di confine (da Enna in giù) si dirà <b>«arancin*»</b>, con l'asterisco. Si pronuncia tossendo leggermente alla fine.</p>
<p>Al momento di andare in stampa, i due signori stavano dividendo l'ultimo pezzo. Esattamente a metà. Uno ha mangiato la punta, l'altro il tondo. <i>La guerra continua a pagina 5, con le crispelle: almeno quelle non hanno genere.</i></p>
""",
p1_didascalia="Piazza Duomo, ore 9:15: il duello decisivo. Il pubblico tifa per entrambi.",
p1_box_titolo="Nasce un giornale che nessuno aveva chiesto",
p1_box="""<p>Il Direttore Vladone, raggiunto nel suo ufficio (la cucina), ha dichiarato: «Finalmente.» Poi è tornato a mescolare il sugo. <b>Editoriale a pagina 2.</b></p>""",
p1_sommario_box="""<h4>In questo numero</h4><ul class="piccolo">
<li>Tre gocce paralizzano Taormina · <b>2</b></li><li>«Chi ha rubato il cielo?» · <b>3</b></li>
<li>Reportage dal passato: chi ha inventato l'arancino? · <b>4</b></li><li>Crispelle e nonne in guerra · <b>5</b></li>
<li>Fiaba: il pescatore e la luna · <b>6</b></li><li>La posta di Nonno Fomà · <b>7</b></li><li>Giochi e oroscopo · <b>8</b></li></ul>""",

p2_ed_titolo="Perché un giornale, nel 2026?",
p2_ed_firma="L'editoriale · di Il Direttore Vladone",
p2_ed="""
<p class="capolettera">Cari lettori, cari vicini, cari parenti costretti a leggere: nel 2026 tutti hanno un telefono, e nel telefono c'è tutto. Ma il telefono non si può piegare in quattro, non si può lasciare sul tavolo del bar, e soprattutto non si può usare per accendere il camino (provato: sconsigliato).</p>
<p>Per questo nasce La Gazzettona. Carta vera, inchiostro nero, numero zero perché in Sicilia si comincia sempre con calma. Qui scriveranno i personaggi dei miei libri: gente seria come una bambina di dodici anni, un domovoj brontolone e due cugini che viaggiano nel tempo. Io mi limito a firmare e a portare le copie.</p>
<p>Se vi piace, ditelo ai vicini. Se non vi piace, ditelo a me: ho bisogno di lettere per pagina 7.</p>
<p style="text-align:right"><i>Il Direttore</i></p>""",
p2_rubrica="Notizie dalla Sicilia",
p2_notizie="""
<div><h4><span class="etichetta">VERO</span>Monforte San Giorgio: il Giro delle Botti</h4>
<p>Il 14 e 15 novembre il borgo dei Peloritani ha aperto le sue cantine, i «catoj», per l'antico giro delle botti di San Martino: vino nuovo, caldarroste e musica per i vicoli. Chi è salito racconta di aver ritrovato la strada del ritorno, prima o poi.</p></div>
<div><h4><span class="etichetta">VERO</span>Palermo: Sherbeth, il gelato a novembre</h4>
<p>Dal 13 al 16 novembre torna a Palermo il festival del gelato artigianale. Solo in Sicilia si fa la festa del gelato mentre nel resto d'Europa si accendono i termosifoni. Gli inviati russi della redazione approvano.</p></div>
<div><h4><span class="etichetta">VERO</span>Arrivano i presepi artigiani</h4>
<p>Da Acireale a Caltagirone, i maestri preparano statuine di terracotta, mercati in miniatura e botteghe piccole come un'unghia. Una tradizione di mestiere e di pazienza: le mani dei ceramisti valgono il viaggio.</p></div>
<div><h4><span class="etichetta">OSSERVATO</span>Le stagioni del Corso</h4>
<p>In novembre i turisti spariscono e Corso Umberto torna ai taorminesi. La stagione si riconosce dal rumore: d'estate valigie con le rotelle, d'inverno sedie spostate fuori dal bar per prendere il sole delle tre.</p></div>
<div><h4><span class="etichetta">OSSERVATO</span>Il termometro siciliano</h4>
<p>Sotto i 20 gradi spunta il primo piumino. Sotto i 18, il cappello di lana. Sotto i 16, la nonna telefona per sapere se hai mangiato qualcosa di caldo.</p></div>""",
p2_satira_titolo="Taormina paralizzata: cadute tre gocce",
p2_satira="""<p>Martedì alle 11:42 su Corso Umberto sono cadute tre gocce di pioggia, di cui una di dubbia consistenza. Motorini in garage, sedie dentro i bar, signore con il fazzoletto in testa. Un gatto è stato messo al riparo sotto un ombrello da quattro persone.</p>
<p>Alle 11:43 è tornato il sole. Bilancio: due cannoli bagnati e un'intera città in sciarpa fino a Pasqua.</p>""",
p2_didascalia="Corso Umberto, ore 11:42. Ricostruzione del nostro disegnatore.",

p3_rubrica="Libri nuovi · Vlad Editore",
p3_occhiello="Il primo libro della serie «Cugini nel Tempo»",
p3_titolo="Chi ha rubato il cielo?",
p3_trama="""<h4>La trama</h4>
<p class="capolettera">Nico ha tredici anni, vive a Taormina e disegna macchine volanti sul quaderno. Sua cugina Katia arriva dalla Russia, conta tutto e porta uno zaino «per ogni evenienza». Una notte l'orologio della Torre batte tredici colpi e i due si ritrovano nella Firenze del 1505, accanto a Leonardo da Vinci.</p>
<p>Con loro c'è Marco Aurelio, una tartaruga di 1900 anni che traduce tutte le lingue (quasi sempre giuste). Il nemico? Il professor Paglia, che salta tra le epoche per prendersi le invenzioni degli altri. Da Leonardo ai fratelli Wright, fino a Gagarin: chi ha rubato il cielo?</p>""",
p3_citazione="«Ricorda che sei mortale», disse la tartaruga. «E che io sono più vecchia di te.»",
p3_recensione="""<h4>La recensione della redazione</h4>
<p>Un'avventura che fa ridere e insegna senza farsene accorgere. La coppia funziona: lui sogna, lei calcola, e servono tutti e due per far volare qualcosa. Il professor Paglia è un cattivo che alla fine fa quasi tenerezza.</p>
<p>Per lettori dai 9 anni in su, e per gli adulti che da piccoli volevano volare.</p>
<p><b>Voto: ★★★★★</b> (cinque eliche)</p>""",
p3_qr="Leggi e ordina il libro",
p3_altri_titolo="Le collane di Vlad Editore",
p3_altri="""<p class="piccolo">Casa Russo · Codice Zero · I Misteri di Palermo · Il Custode dell'Etna · Il popolo nel barattolo · La Porta di Castel del Monte · <b>Cugini nel Tempo</b> (novità!).</p>
<p class="piccolo">Tutti i libri si leggono e si ordinano su <b>libri.vladinc.ru</b>. In italiano e in russo.</p>""",
p3_ann_titolo="Avviso ai naviganti del tempo",
p3_ann="""<p class="piccolo">Se a mezzanotte sentite tredici colpi dalla Torre dell'Orologio, non preoccupatevi: tornerete nello stesso minuto. Portate uno zaino. Katia insiste.</p>""",

p4_rubrica="Reportage dal passato · Racconto esclusivo",
p4_titolo="Chi ha inventato l'arancino?",
p4_sommario="I nostri inviati nel tempo vanno alla fonte. La fonte non collabora.",
p4_firma="di Nico e Katia, corrispondenti da un'epoca a caso",
p4_testo="""
<p><b>NICO.</b> Quando la redazione ci ha chiesto di risolvere la guerra dell'arancino, ho detto subito: «Andiamo alla fonte!» Katia ha detto: «Non scegliamo noi dove andiamo, Nico. L'orologio sceglie.» Ma a mezzanotte l'orologio ha battuto tredici volte, e io ho sentito profumo di zafferano. Era un segno.</p>
<p><b>KATIA.</b> Era il forno della nonna Ljuda. Comunque ci siamo trovati in una cucina di pietra: Palermo, anno 900 e qualcosa, secondo la tartaruga. Ho controllato lo zaino: torcia, cerotti, taccuino e due crackers. Niente sale. Errore mio.</p>
<p><b>NICO.</b> C'era un cuoco con il turbante che cucinava riso con lo zafferano e lo faceva a palline. Palline! Tonde! Ho gridato: «È ARANCINA! Palermo ha vinto!»</p>
<p><b>KATIA.</b> Calma. Il riso e lo zafferano in Sicilia sono arrivati con gli Arabi, questo è vero. Ma la panatura croccante, quella che fa l'arancino, è arrivata molto dopo. Quella era una palla di riso, non un arancino. Ho fatto il conto: mancavano secoli di frittura.</p>
<p><b>NICO.</b> Katia rovina sempre i momenti epici.</p>
<p><b>KATIA.</b> Io i momenti epici li salvo. Li rendo veri.</p>
<p><b>NICO.</b> Poi Marco Aurelio ha tradotto quello che diceva il cuoco: «Le palle tonde sono per il re, quelle a punta per il vulcano.» Io ho guardato Katia. Katia ha guardato la tartaruga.</p>
<p><b>KATIA.</b> «L'ha detto davvero?», ho chiesto. «Più o meno», ha risposto Marco Aurelio. «Ha detto: <i>passami il sale</i>. Ma la mia versione è più bella.»</p>
<p><b>NICO.</b> Quindi l'inchiesta si chiude così: il riso allo zafferano è arabo, la forma è un mistero, e la tartaruga traduce come le pare. Siamo tornati a casa a mezzanotte e un minuto, cioè nello stesso momento in cui eravamo partiti.</p>
<p><b>KATIA.</b> Con due crackers in meno e una certezza in più: la guerra dell'arancino non la vince nessuno. Ma si mangia benissimo.</p>""",
p4_vero_titolo="Vero o inventato?",
p4_vero="""<p class="piccolo"><b>Vero:</b> il riso e lo zafferano arrivano in Sicilia con la cultura araba, più di mille anni fa. La forma moderna, fritta e impanata, è molto più recente. Il nome cambia davvero: «arancina» a Palermo, «arancino» a Catania e quasi ovunque.</p>
<p class="piccolo"><b>Inventato:</b> tutto il resto, specialmente la tartaruga.</p>""",
p4_chi="""<h4>Chi sono i nostri inviati</h4><p class="piccolo"><b>Nico</b>, 13 anni, Taormina: sognatore, sempre in ritardo. <b>Katia</b>, 13 anni, dalla Russia: calcola tutto, odia essere chiamata «Caterina». <b>Marco Aurelio</b>: tartaruga, 1900 anni, interprete creativo. Li trovate nel libro «Chi ha rubato il cielo?» (pagina 3).</p>""",

p5_rubrica="La ricetta",
p5_titolo="Le crispelle catanesi",
p5_firma="di Nonna Concetta e Babuška Galina (litigio incluso)",
p5_ingr="""<h4>Ingredienti</h4><p class="piccolo">(per circa 25 crispelle)</p><ul class="piccolo">
<li>500 g di farina 0</li><li>10 g di lievito di birra</li><li>350–400 ml d'acqua tiepida</li><li>1 cucchiaino di sale</li>
<li>per il ripieno: acciughe sott'olio oppure ricotta di pecora</li><li>olio per friggere</li><li>zucchero (e cannella) per quelle di ricotta</li></ul>""",
p5_prep="""<h4>Preparazione</h4><ol>
<li>Sciogliete il lievito in un po' d'acqua tiepida. Unite farina, sale e il resto dell'acqua: l'impasto deve essere morbido e appiccicoso. <i>(«Molle come una cosa che non si può prendere in mano», dice Concetta. «Come l'impasto dei blini», dice Galina. «No.» «Sì.»)</i></li>
<li>Coprite e lasciate lievitare circa 2 ore al caldo, finché raddoppia.</li>
<li>Bagnatevi le mani, prendete un pezzo di pasta, allungatelo, metteteci dentro un'acciuga (o un cucchiaino di ricotta) e chiudetelo a bastoncino.</li>
<li>Friggete in olio ben caldo finché sono dorate. Scolate su carta.</li>
<li>Quelle di ricotta si spolverano di zucchero, e di cannella se c'è pace in casa. Quelle d'acciuga no: nessuno ha mai messo lo zucchero sulle acciughe. <i>(«Da noi l'aringa sotto la pelliccia è buonissima», dice Galina. Concetta è uscita dalla stanza.)</i></li></ol>
<p><b>Consiglio della redazione:</b> si mangiano subito, calde, in piedi in cucina. In tavola non arrivano mai.</p>""",
p5_detti_titolo="Modi di dire siciliani (spiegati male)",
p5_detti="""<p><b>«Cu nesci, arrinesci.»</b> Chi esce, riesce: chi lascia la Sicilia ha successo. <i>Allora perché ad agosto tornano tutti?</i></p>
<p><b>«Calati juncu, ca passa la china.»</b> Piegati, giunco, che passa la piena. Pazienza: anche la guerra dell'arancino finirà.</p>
<p><b>«Acqua davanti e ventu darreri.»</b> Acqua davanti e vento dietro: buon viaggio. Valido anche per i viaggi nel tempo.</p>""",
p5_ann1_t="Orologio «Tredici colpi»",
p5_ann1="""<p>Il primo orologio da torre che batte un colpo in più.<br><b>Garanzia: 1900 anni.</b></p><p class="piccolo"><i>Non vi porta al lavoro in orario: vi porta nel Rinascimento.</i> Effetti collaterali: Leonardo da Vinci in cucina.</p>""",
p5_ann_tag="Pubblicità (finta)",

p6_rubrica="Racconto a puntate · Fiaba",
p6_titolo="Il pescatore e la luna che dormiva",
p6_occhiello="Puntata 1 di 3",
p6_testo="""
<p class="capolettera">A Taormina, sotto la roccia, c'era una volta un pescatore che si chiamava Turi e non pescava quasi niente. Usciva ogni notte con la sua barca dipinta di giallo, girava intorno all'Isola Bella e tornava con due sarde, una triglia stanca e tante storie.</p>
<p>«Il mare è pieno», diceva sua moglie Nunziata, «sei tu che sei vuoto.» Turi non rispondeva. Aveva un segreto: non pescava perché passava le notti a guardare la luna nell'acqua.</p>
<p>Una notte di novembre, senza vento, la luna non c'era. In cielo nuvole zero, stelle tutte. Ma la luna, sparita. Turi guardò in basso e la vide: era lì, sotto la barca. Una luna vera, rotonda e bianca, che dormiva a pancia in su tra gli scogli dell'Isola Bella, come un pesce enorme e d'argento.</p>
<p>Turi si sporse. La luna respirava piano: a ogni respiro il mare si alzava di un dito e si abbassava di un dito.</p>
<p>«Signora Luna», sussurrò Turi, «non dovreste stare lassù?»</p>
<p>La luna aprì un occhio. Era grande come la piazza del Duomo e azzurro come il vetro di una bottiglia.</p>
<p>«Sono stanca», disse. «Ogni notte su e giù, su e giù, per far crescere le maree. Stanotte dormo qui. Nessuno se ne accorgerà.»</p>
<p>Ma qualcuno se ne accorse. Da Messina a Siracusa i galli non sapevano più quando cantare. I gatti non sapevano quando uscire. E soprattutto il mare cominciò ad abbassarsi, ad abbassarsi, ad abbassarsi…</p>
<p>All'alba la barca di Turi era appoggiata sulla sabbia, dove la sera prima c'erano tre metri d'acqua. E dalla spiaggia scendeva, con un secchio in mano e lo sguardo di chi ha già deciso tutto, sua moglie Nunziata.</p>
<p>«Turi», disse. «Cos'hai combinato stavolta?»</p>
<p style="text-align:right"><b><i>Continua nel prossimo numero.</i></b></p>""",
p6_ann1_t="Marco Aurelio™ · la tartaruga interprete",
p6_ann1="""<p>Parla tutte le lingue del mondo e alcune che non esistono più. Basta tenerla per il guscio.</p><p class="piccolo"><b>Traduzione garantita al 90%.</b> Il restante 10% è filosofia. Disponibile in tutte le epoche.</p>""",
p6_ann2_t="Sciarpa «Più sedici»",
p6_ann2="""<p>Per l'inverno siciliano più duro: quando il termometro scende sotto i 16 gradi. <b>Lunghezza: 11 metri.</b></p><p class="piccolo">Testata sul lungomare di Giardini, a novembre, tra la gente che faceva il bagno. <i>Sconsigliata ai russi: rischiano di sudare.</i></p>""",

p7_rubrica="La posta di Nonno Fomà",
p7_titolo="Scrivete, umani!",
p7_firma="Risponde il domovoj di casa Russo, che legge le lettere di notte, dietro la stufa",
p7_posta="""
<p class="capolettera">Questo è il numero zero, e lettere non ne sono arrivate. Ho controllato tre volte: sotto il tappeto, nella scarpa, nella dispensa. Nella dispensa ho trovato solo un pezzo di formaggio, che ho trattenuto per indagini.</p>
<p>Quindi, umani, scrivete. Domande serie, domande stupide, lamentele sul ripieno degli arancini: rispondo a tutto, anche a quello che non mi chiedete. Le lettere più belle saranno pubblicate qui. Quelle più brutte pure, ma con commento.</p>
<p><b>Come scrivere:</b> consegnate la lettera a mano a chi vi ha dato questo giornale, oppure scrivete alla redazione dal sito (QR in ultima pagina). Accettiamo anche disegni.</p>
<p>E ricordate: chi non scrive alla Gazzettona, poi non si lamenti se gli spariscono le chiavi.</p>
<p style="text-align:right"><i>Nonno Fomà</i></p>""",
p7_chi="""<p class="piccolo"><b>Chi è Nonno Fomà?</b> Un domovoj, lo spirito russo della casa: piccolo, barbuto, brontolone. È arrivato a Taormina dentro una vecchia scarpa di corteccia insieme alla babuška Galina. Lo trovate nei libri di <b>Casa Russo</b>.</p>""",
p7_barz_titolo="Ridere fa bene (dice il medico)",
p7_barz="""<p>Un catanese e un palermitano entrano in un bar. Il barista dice: «Arancin…» e si ferma lì. Vive ancora oggi, molto felice.</p>
<p>— Papà, perché in Sicilia fa freddo solo a novembre?<br>— Perché a dicembre ci abituiamo e mettiamo il cappotto.</p>
<p>Un turista russo chiede: «Dov'è l'inverno?» Il taorminese indica un cartello: «Chiuso per ferie fino a gennaio.»</p>
<p>— Nonna, mi passi il sale?<br>— Assaggia prima. Poi ne parliamo.</p>
<p>Il domovoj al gatto: «La casa è mia.» Il gatto al domovoj: «Ne parliamo domani, adesso dormo.» Discutono da tre anni.</p>""",
p7_didascalia="Lungomare, 16 gradi: due scuole di pensiero.",

p8_rubrica="Giochi · Oroscopo · Prossimo numero",
p8_cru_titolo="Il cruciverba siciliano",
p8_or="Orizzontali", p8_ve="Verticali",
p8_sud_titolo="Sudoku",
p8_ind_titolo="Indovinelli e quiz",
p8_ind="""<ol class="piccolo"><li>Ha una punta ma non punge, si mangia ma non si sbuccia. Cos'è?</li>
<li>Quanti colpi batte l'orologio della Torre quando parte un viaggio nel tempo?</li>
<li>Vero o falso: a Palermo, a novembre, si fa un festival del gelato.</li>
<li>Chi è arrivato a Taormina dentro una scarpa?</li>
<li>Che cosa si mette sulle crispelle di ricotta, e mai su quelle d'acciuga?</li></ol>""",
p8_soluz="Soluzioni e nomi dei solutori nel № 1. Mandateci le risposte!",
p8_oro_titolo="L'oroscopo dell'arancino · a cura di Nonno Fomà",
p8_oro="""<p><b>Ariete</b> · Ragù. Forte, deciso, sporca la camicia.</p>
<p><b>Toro</b> · Burro e prosciutto. Morbido ma ostinato.</p>
<p><b>Gemelli</b> · Mezzo e mezzo. Non sapete scegliere? Nessuno vi giudica.</p>
<p><b>Cancro</b> · Spinaci. Buono, ma nessuno vi ordina per primo.</p>
<p><b>Leone</b> · Pistacchio di Bronte. Vi credete speciali. Avete ragione.</p>
<p><b>Vergine</b> · Nero di seppia. Elegante, misterioso, vi sporca i denti.</p>
<p><b>Bilancia</b> · Tondo o a punta? La settimana più difficile della vostra vita.</p>
<p><b>Scorpione</b> · Piccante. Chi vi morde se ne ricorda.</p>
<p><b>Sagittario</b> · Salmone. Volete viaggiare. Andate.</p>
<p><b>Capricorno</b> · Alla Norma. Classico, affidabile, pagate sempre voi.</p>
<p><b>Acquario</b> · Nutella. Siete il futuro e le nonne non vi capiscono.</p>
<p><b>Pesci</b> · Pesce spada, ovvio. Questa settimana attenti alla luna (pagina 6).</p>""",
p8_pross_titolo="Nel prossimo numero · № 1, dicembre",
p8_pross="""<h3>Panettone contro Olivier!</h3><p class="piccolo">La grande sfida del cenone: il dolce milanese contro l'insalata che in Italia chiamano «russa» e in Russia si chiama Olivier. Giuria: due nonne. Ambulanza pronta. E poi: la seconda puntata della fiaba, le soluzioni dei giochi e, speriamo, le vostre lettere.</p>""",
p8_qr1="Archivio dei numeri", p8_qr2="Iscriviti al sito",
colophon="La Gazzettona di Vladone · Vlad Editore, Taormina · Direttore: Il Direttore Vladone · Stampata in casa, in poche copie, con amore e toner. Notizie: VERO = fonte reale; OSSERVATO = visto dalla redazione; SATIRA = inventato per ridere.",
)

CRU_IT = [
 ("ARANCINO", "Tondo a Palermo, a punta a Catania"),
 ("CRISPELLE", "Si friggono con acciughe o ricotta"),
 ("TAORMINA", "Città della redazione"),
 ("TARTARUGA", "Marco Aurelio lo è da 1900 anni"),
 ("ETNA", "Il vulcano più alto d'Europa"),
 ("LUNA", "Dorme all'Isola Bella nella fiaba"),
 ("NICO", "Il cugino che disegna macchine volanti"),
 ("KATIA", "La cugina che calcola tutto"),
 ("FOMA", "Nonno ___, il domovoj"),
 ("PALERMO", "Capoluogo della Sicilia"),
 ("CATANIA", "Città ai piedi del vulcano"),
 ("ZAFFERANO", "Rende giallo il riso"),
 ("SCIARPA", "Indispensabile sotto i 16 gradi"),
]

RU = dict(
lingua="ru", data="Воскресенье, 16 ноября 2026", numero="Год I · № 0 (нулевой, но уже горячий)",
motto="Горячее Этны", prezzo="Бесплатно, но с улыбкой", editore="Vlad Editore · Таормина",
pag="Страница",

p1_occhiello="Редакция расследует · Гастрономическая гражданская война",
p1_titolo="Война аранчин*",
p1_sommario="Остров расколот надвое одной буквой. В баре на Корсо летают чашки. Наш корреспондент — на передовой.",
p1_firma="Вера Руссо, спецкор (12 лет, шестой класс, ничего не боится)",
p1_testo="""
<p class="capolettera"><b>Таормина.</b> В субботу утром в папином баре на Корсо началась война. Синьор из Палермо попросил «одну аранчину». Синьор из Катании за соседней стойкой поставил кофе так, как кладут на стол шпагу.</p>
<p>«Аранчино», — сказал он. — «Мужского рода. Острый. Как Этна».</p>
<p>«Аранчина», — ответил первый. — «Женского. Круглая. Как апельсин — arancia, она тоже женского рода».</p>
<p><i>(Для тех, кто только приехал: аранчини — жареные рисовые шарики в панировке с начинкой, главная уличная еда Сицилии. В Палермо их делают круглыми и зовут «аранчина», в Катании — конусом и зовут «аранчино». Спорят об этом примерно всю жизнь.)</i></p>
<p>Я достала блокнот. Настоящий журналист не пугается.</p>
<h4>Позиции сторон</h4>
<p>Западный фронт (Палермо) утверждает, что круглая форма — оригинал, а «а» на конце — вопрос элегантности. Восточный фронт (Катания) отвечает, что острый кончик напоминает вулкан, а с вулканом не спорят.</p>
<h4>Нейтралы</h4>
<p>Мой папа заявил: «Я продаю и те и другие, называйте как хотите, лишь бы платили». Обе стороны обвинили его в коллаборационизме.</p>
<h4>Слово экспертам</h4>
<p>Я взяла интервью у нонны Кончетты. Она сказала: «Аранчино. Точка». Потом у бабушки Галины. Она сказала: «У нас пирожки, и никто не ругается». Потом они поругались друг с другом. Два часа. О начинке.</p>
<h4>Мирный договор</h4>
<p>После долгого расследования «Газеттона» предлагает компромисс: в пограничных барах (от Энны и южнее) говорить <b>«аранчин*»</b>, со звёздочкой. Произносится с лёгким покашливанием в конце.</p>
<p>Когда номер уходил в печать, два синьора делили последний шарик. Ровно пополам. Один съел кончик, другой — круглую часть. <i>Война продолжается на странице 5, где рецепт криспелле: у них хотя бы нет рода.</i></p>
""",
p1_didascalia="Соборная площадь, 9:15: решающая дуэль. Публика болеет за обоих.",
p1_box_titolo="Родилась газета, которую никто не просил",
p1_box="""<p>Il Direttore Vladone, главный редактор, застигнутый в своём кабинете (на кухне), заявил: «Наконец-то». И вернулся мешать соус. <b>Колонка редактора — на странице 2.</b></p>""",
p1_sommario_box="""<h4>В этом номере</h4><ul class="piccolo">
<li>Три капли парализовали Таормину · <b>2</b></li><li>«Кто украл небо?» · <b>3</b></li>
<li>Репортаж из прошлого: кто придумал аранчино? · <b>4</b></li><li>Криспелле и война бабушек · <b>5</b></li>
<li>Сказка: рыбак и луна · <b>6</b></li><li>Почта Нонно Фомы · <b>7</b></li><li>Игры и гороскоп · <b>8</b></li></ul>""",

p2_ed_titolo="Зачем газета в 2026 году?",
p2_ed_firma="Колонка редактора · Il Direttore Vladone",
p2_ed="""
<p class="capolettera">Дорогие читатели, дорогие соседи, дорогие родственники, которых заставили читать! В 2026 году у всех есть телефон, а в телефоне есть всё. Но телефон нельзя сложить вчетверо, нельзя оставить на столике в баре и, главное, им нельзя растопить камин (проверено: не советую).</p>
<p>Поэтому и родилась «Газеттона». Настоящая бумага, чёрная краска, номер ноль — потому что на Сицилии всё начинают не спеша. Писать здесь будут герои моих книг: серьёзные люди вроде двенадцатилетней девочки, ворчливого домового и двух кузенов, путешествующих во времени. Моё дело — подписать и разнести экземпляры.</p>
<p>Понравится — расскажите соседям. Не понравится — скажите мне: мне нужны письма для седьмой страницы.</p>
<p style="text-align:right"><i>Il Direttore</i></p>""",
p2_rubrica="Новости Сицилии",
p2_notizie="""
<div><h4><span class="etichetta">ПРАВДА</span>Монфорте-Сан-Джорджо: «Обход бочек»</h4>
<p>14 и 15 ноября городок в горах Пелоритани под Мессиной открыл свои погреба — «катой» — для старинного праздника Сан-Мартино: молодое вино, жареные каштаны, музыка в переулках. Поднявшиеся туда рассказывают, что дорогу домой рано или поздно нашли.</p></div>
<div><h4><span class="etichetta">ПРАВДА</span>Палермо: Sherbeth, мороженое в ноябре</h4>
<p>С 13 по 16 ноября в Палермо проходит фестиваль ремесленного мороженого (джелато). Только на Сицилии устраивают праздник мороженого, когда в остальной Европе включают отопление. Русские корреспонденты редакции одобряют.</p></div>
<div><h4><span class="etichetta">ПРАВДА</span>Скоро вертепы-презепи</h4>
<p>От Ачиреале до Кальтаджироне мастера готовят к декабрю презепи — миниатюрные сценки из терракоты: рынки, лавки, крошечные пастухи размером с ноготь. Это старинное сицилийское ремесло; руки керамистов стоят поездки.</p></div>
<div><h4><span class="etichetta">ЗАМЕЧЕНО</span>Времена года на Корсо</h4>
<p>В ноябре туристы исчезают, и Корсо Умберто возвращается к местным. Сезон узнают по звуку: летом — колёсики чемоданов, зимой — стулья, которые выносят из бара, чтобы погреться на трёхчасовом солнце.</p></div>
<div><h4><span class="etichetta">ЗАМЕЧЕНО</span>Сицилийский термометр</h4>
<p>Ниже +20 — первый пуховик. Ниже +18 — шерстяная шапка. Ниже +16 звонит бабушка: ты поел горячего?</p></div>""",
p2_satira_titolo="Таормина парализована: упали три капли",
p2_satira="""<p>Во вторник в 11:42 на Корсо Умберто упали три капли дождя, одна из них сомнительной плотности. Мопеды убраны в гаражи, стулья — внутрь баров, синьоры повязали платки. Кота укрыли под зонтом на четверых.</p>
<p>В 11:43 снова вышло солнце. Итог: два промокших канноли и целый город в шарфах до Пасхи.</p>""",
p2_didascalia="Корсо Умберто, 11:42. Реконструкция нашего художника.",

p3_rubrica="Новые книги · Vlad Editore",
p3_occhiello="Первая книга серии «Кузены во времени»",
p3_titolo="Кто украл небо?",
p3_trama="""<h4>Сюжет</h4>
<p class="capolettera">Нико тринадцать лет, он живёт в Таормине и рисует в тетрадке летательные аппараты. Его кузина Катя приезжает из России, всё считает и носит рюкзак «на все случаи жизни». Однажды ночью часы на башне бьют тринадцать раз — и кузены оказываются во Флоренции 1505 года, рядом с Леонардо да Винчи.</p>
<p>С ними Марк Аврелий — черепаха, которой 1900 лет: она переводит с любого языка (почти всегда правильно). Враг — профессор Палья, который прыгает по эпохам, чтобы присвоить чужие изобретения. От Леонардо до братьев Райт и Гагарина: кто украл небо?</p>""",
p3_citazione="«Помни, что ты смертен, — сказала черепаха. — И что я старше тебя».",
p3_recensione="""<h4>Рецензия редакции</h4>
<p>Приключение, над которым смеёшься и при этом незаметно учишься. Пара работает: он мечтает, она считает, и чтобы что-то полетело, нужны оба. Профессор Палья — злодей, которого в конце почти жалко.</p>
<p>Для читателей от 9 лет и для взрослых, которые в детстве хотели летать.</p>
<p><b>Оценка: ★★★★★</b> (пять пропеллеров)</p>""",
p3_qr="Читать и заказать книгу",
p3_altri_titolo="Серии Vlad Editore",
p3_altri="""<p class="piccolo">Casa Russo · Codice Zero · I Misteri di Palermo · Il Custode dell'Etna · Il popolo nel barattolo · La Porta di Castel del Monte · <b>Cugini nel Tempo</b> (новинка!).</p>
<p class="piccolo">Все книги можно читать и заказать на <b>libri.vladinc.ru</b> — по-итальянски и по-русски.</p>""",
p3_ann_titolo="Объявление для путешественников во времени",
p3_ann="""<p class="piccolo">Если в полночь часы на Торре-дель-Оролоджо пробьют тринадцать раз — не волнуйтесь: вернётесь в ту же минуту. Возьмите рюкзак. Катя настаивает.</p>""",

p4_rubrica="Репортаж из прошлого · Эксклюзивный рассказ",
p4_titolo="Кто придумал аранчино?",
p4_sommario="Наши корреспонденты во времени отправились к первоисточнику. Первоисточник не сотрудничает.",
p4_firma="Нико и Катя, корреспонденты из случайной эпохи",
p4_testo="""
<p><b>НИКО.</b> Когда редакция попросила нас разобраться с войной аранчини, я сразу сказал: «Едем к первоисточнику!» Катя сказала: «Мы не выбираем, куда попадём, Нико. Выбирают часы». Но в полночь часы пробили тринадцать раз, и я почувствовал запах шафрана. Это был знак.</p>
<p><b>КАТЯ.</b> Это была бабушкина духовка. Бабушка Люда пекла. Но мы и правда оказались на каменной кухне: Палермо, девятисотый с чем-то год, по словам черепахи. Я проверила рюкзак: фонарик, пластыри, блокнот и два крекера. Соли нет. Моя ошибка.</p>
<p><b>НИКО.</b> Там был повар в тюрбане, он варил рис с шафраном и лепил шарики. Шарики! Круглые! Я закричал: «ЭТО АРАНЧИНА! Палермо победил!»</p>
<p><b>КАТЯ.</b> Спокойно. Рис и шафран действительно пришли на Сицилию с арабами. Но хрустящая панировка, которая и делает аранчино аранчино, появилась гораздо позже. Это был просто рисовый шарик. Я посчитала: до жарки во фритюре оставались века.</p>
<p><b>НИКО.</b> Катя всегда портит эпические моменты.</p>
<p><b>КАТЯ.</b> Я их спасаю. Я делаю их правдой.</p>
<p><b>НИКО.</b> Потом Марк Аврелий перевёл, что говорил повар: «Круглые — для короля, острые — для вулкана». Я посмотрел на Катю. Катя посмотрела на черепаху.</p>
<p><b>КАТЯ.</b> «Он правда так сказал?» — спросила я. «Более-менее, — ответил Марк Аврелий. — Он сказал: <i>передай соль</i>. Но моя версия красивее».</p>
<p><b>НИКО.</b> В общем, расследование закрыто: рис с шафраном — арабский, форма — загадка, а черепаха переводит как хочет. Домой мы вернулись в минуту первого — то есть в ту же минуту, когда улетели.</p>
<p><b>КАТЯ.</b> Минус два крекера, плюс одна уверенность: войну аранчини не выиграет никто. Но едят их отлично.</p>""",
p4_vero_titolo="Правда или выдумка?",
p4_vero="""<p class="piccolo"><b>Правда:</b> рис и шафран пришли на Сицилию вместе с арабской культурой больше тысячи лет назад. Современная жареная форма в панировке намного моложе. Названия действительно разные: «аранчина» в Палермо, «аранчино» в Катании и почти везде.</p>
<p class="piccolo"><b>Выдумка:</b> всё остальное, особенно черепаха.</p>""",
p4_chi="""<h4>Кто наши корреспонденты</h4><p class="piccolo"><b>Нико</b>, 13 лет, Таормина: мечтатель, вечно опаздывает. <b>Катя</b>, 13 лет, из России: всё считает, терпеть не может, когда её зовут «Катерина». <b>Марк Аврелий</b>: черепаха, 1900 лет, творческий переводчик. Все они — в книге «Кто украл небо?» (страница 3).</p>""",

p5_rubrica="Рецепт",
p5_titolo="Криспелле по-катанийски",
p5_firma="Нонна Кончетта и бабушка Галина (ссора прилагается)",
p5_ingr="""<h4>Продукты</h4><p class="piccolo">(примерно 25 штук; криспелле — сицилийские пончики-палочки из жидкого дрожжевого теста)</p><ul class="piccolo">
<li>500 г муки (обычной пшеничной)</li><li>10 г свежих дрожжей</li><li>350–400 мл тёплой воды</li><li>1 ч. л. соли</li>
<li>для начинки: анчоусы в масле или овечья рикотта</li><li>масло для жарки</li><li>сахар (и корица) — для тех, что с рикоттой</li></ul>""",
p5_prep="""<h4>Приготовление</h4><ol>
<li>Растворите дрожжи в небольшом количестве тёплой воды. Добавьте муку, соль и остальную воду: тесто должно быть мягким и липким. <i>(«Мягкое, как то, что нельзя взять в руки», — говорит Кончетта. «Как тесто на блины», — говорит Галина. «Нет». «Да».)</i></li>
<li>Накройте и оставьте подходить в тепле примерно 2 часа, пока не увеличится вдвое.</li>
<li>Смочите руки водой, возьмите кусочек теста, растяните, положите внутрь анчоус (или ложечку рикотты) и сверните палочкой.</li>
<li>Жарьте в хорошо разогретом масле до золотистого цвета. Выложите на бумагу.</li>
<li>Криспелле с рикоттой посыпают сахаром, а если в доме мир — то и корицей. С анчоусами — нет: сахар на анчоусы никто никогда не сыпал. <i>(«А у нас селёдка под шубой — объедение», — говорит Галина. Кончетта вышла из комнаты.)</i></li></ol>
<p><b>Совет редакции:</b> есть сразу, горячими, стоя на кухне. До стола они не доживают.</p>""",
p5_detti_titolo="Сицилийские поговорки (объяснённые неправильно)",
p5_detti="""<p><b>«Cu nesci, arrinesci».</b> Кто уезжает, тот преуспевает. <i>Тогда почему в августе все возвращаются?</i></p>
<p><b>«Calati juncu, ca passa la china».</b> Пригнись, тростник, паводок пройдёт. Наш вариант «перемелется — мука будет»: и война аранчини когда-нибудь кончится.</p>
<p><b>«Acqua davanti e ventu darreri».</b> Вода впереди, ветер в спину — счастливого пути. Годится и для путешествий во времени.</p>""",
p5_ann1_t="Часы «Тринадцать ударов»",
p5_ann1="""<p>Первые башенные часы, которые бьют на один раз больше.<br><b>Гарантия: 1900 лет.</b></p><p class="piccolo"><i>На работу вовремя не доставят — доставят в эпоху Возрождения.</i> Побочный эффект: Леонардо да Винчи у вас на кухне.</p>""",
p5_ann_tag="Реклама (ненастоящая)",

p6_rubrica="Сериал · Сказка",
p6_titolo="Рыбак и луна, которая спала",
p6_occhiello="Серия 1 из 3",
p6_testo="""
<p class="capolettera">В Таормине, под скалой, жил-был рыбак по имени Тури, который почти ничего не ловил. Каждую ночь он выходил в море на своей лодке, выкрашенной в жёлтый цвет, огибал остров Изола-Белла и возвращался с двумя сардинками, одной усталой барабулькой и кучей историй.</p>
<p>«Море полное, — говорила его жена Нунциата, — это ты пустой». Тури не отвечал. У него была тайна: он не ловил рыбу, потому что все ночи смотрел на луну в воде.</p>
<p>Однажды ноябрьской ночью, безветренной, луны не оказалось. На небе — ни облачка, все звёзды на месте. А луна пропала. Тури посмотрел вниз и увидел её: прямо под лодкой. Настоящая луна, круглая и белая, спала на спине между скалами Изола-Беллы, как огромная серебряная рыба.</p>
<p>Тури перегнулся через борт. Луна дышала тихо: с каждым вдохом море поднималось на палец и опускалось на палец.</p>
<p>«Синьора Луна, — прошептал Тури, — разве вам не положено быть наверху?»</p>
<p>Луна открыла один глаз. Он был размером с Соборную площадь и голубой, как бутылочное стекло.</p>
<p>«Я устала, — сказала она. — Каждую ночь вверх-вниз, вверх-вниз, чтобы были приливы. Сегодня посплю здесь. Никто и не заметит».</p>
<p>Но заметили. От Мессины до Сиракуз петухи не знали, когда петь. Коты не знали, когда выходить. А главное — море начало мелеть, мелеть, мелеть…</p>
<p>На рассвете лодка Тури лежала на песке там, где вечером было три метра воды. А по пляжу спускалась, с ведром в руке и взглядом человека, который уже всё решил, его жена Нунциата.</p>
<p>«Тури, — сказала она. — Что ты натворил на этот раз?»</p>
<p style="text-align:right"><b><i>Продолжение в следующем номере.</i></b></p>""",
p6_ann1_t="Марк Аврелий™ · черепаха-переводчик",
p6_ann1="""<p>Говорит на всех языках мира и на некоторых, которых уже нет. Достаточно держаться за панцирь.</p><p class="piccolo"><b>Точность перевода — 90%.</b> Остальные 10% — философия. Доступна во всех эпохах.</p>""",
p6_ann2_t="Шарф «Плюс шестнадцать»",
p6_ann2="""<p>Для самой суровой сицилийской зимы: когда термометр опускается ниже +16. <b>Длина: 11 метров.</b></p><p class="piccolo">Испытан на набережной Джардини в ноябре, среди купающихся. <i>Русским не рекомендуется: вспотеют.</i></p>""",

p7_rubrica="Почта Нонно Фомы",
p7_titolo="Пишите, люди!",
p7_firma="Отвечает домовой семьи Руссо, который читает письма по ночам, за печкой",
p7_posta="""
<p class="capolettera">Это нулевой номер, и писем не пришло. Я проверил трижды: под ковром, в лапте, в кладовке. В кладовке нашёлся только кусок сыра, который я задержал до выяснения.</p>
<p>Так что, люди, пишите. Серьёзные вопросы, глупые вопросы, жалобы на начинку аранчини — отвечаю на всё, даже на то, о чём не спрашивали. Лучшие письма напечатаем здесь. Худшие тоже, но с комментарием.</p>
<p><b>Как написать:</b> отдайте письмо в руки тому, кто дал вам эту газету, или напишите в редакцию через сайт (QR на последней странице). Рисунки тоже принимаются.</p>
<p>И помните: кто не пишет в «Газеттону», пусть потом не жалуется, что ключи пропадают.</p>
<p style="text-align:right"><i>Нонно Фома</i></p>""",
p7_chi="""<p class="piccolo"><b>Кто такой Нонно Фома?</b> Домовой: маленький, бородатый, ворчливый. Приехал в Таормину в старом лапте вместе с бабушкой Галиной, как велит обычай. «Нонно» по-итальянски — «дедушка». Ищите его в книгах серии <b>Casa Russo</b>.</p>""",
p7_barz_titolo="Смех продлевает жизнь (говорит врач)",
p7_barz="""<p>Катаниец и палермитанец заходят в бар. Бармен говорит: «Аранчин…» — и замолкает. Жив до сих пор, очень счастлив.</p>
<p>— Папа, почему на Сицилии холодно только в ноябре?<br>— Потому что к декабрю мы привыкаем и надеваем пальто.</p>
<p>Русский турист спрашивает: «Где тут зима?» Таорминец показывает на табличку: «Закрыто на каникулы до января».</p>
<p>— Бабушка, передай соль.<br>— Сначала попробуй. Потом поговорим.</p>
<p>Домовой коту: «Дом мой». Кот домовому: «Завтра обсудим, сейчас я сплю». Обсуждают третий год.</p>""",
p7_didascalia="Набережная, +16: две школы мысли.",

p8_rubrica="Игры · Гороскоп · Следующий номер",
p8_cru_titolo="Сицилийский кроссворд",
p8_or="По горизонтали", p8_ve="По вертикали",
p8_sud_titolo="Судоку",
p8_ind_titolo="Загадки и викторина",
p8_ind="""<ol class="piccolo"><li>Острый, но не колется; едят, но не чистят. Что это?</li>
<li>Сколько раз бьют часы на башне, когда начинается путешествие во времени?</li>
<li>Правда или нет: в Палермо в ноябре проходит фестиваль мороженого?</li>
<li>Кто приехал в Таормину в лапте?</li>
<li>Чем посыпают криспелле с рикоттой и никогда — с анчоусами?</li></ol>""",
p8_soluz="Ответы и имена отгадавших — в № 1. Присылайте ответы!",
p8_oro_titolo="Гороскоп по аранчини · от Нонно Фомы",
p8_oro="""<p><b>Овен</b> · С рагу. Сильный, решительный, пачкает рубашку.</p>
<p><b>Телец</b> · Масло и ветчина. Мягкий, но упрямый.</p>
<p><b>Близнецы</b> · Половина того, половина сего. Не можете выбрать? Никто не осудит.</p>
<p><b>Рак</b> · Со шпинатом. Хороший, но первым вас никто не заказывает.</p>
<p><b>Лев</b> · С фисташкой из Бронте. Считаете себя особенным. Правильно считаете.</p>
<p><b>Дева</b> · С чернилами каракатицы. Элегантно, загадочно, зубы чёрные.</p>
<p><b>Весы</b> · Круглый или острый? Самая трудная неделя в вашей жизни.</p>
<p><b>Скорпион</b> · Острый перец. Кто вас укусит — запомнит.</p>
<p><b>Стрелец</b> · С лососем. Хочется путешествовать. Езжайте.</p>
<p><b>Козерог</b> · «Норма» (с баклажанами). Классика, надёжность, платите всегда вы.</p>
<p><b>Водолей</b> · С нутеллой. Вы — будущее, и бабушки вас не понимают.</p>
<p><b>Рыбы</b> · С рыбой-мечом, конечно. На этой неделе остерегайтесь луны (стр. 6).</p>""",
p8_pross_titolo="В следующем номере · № 1, декабрь",
p8_pross="""<h3>Панеттоне против оливье!</h3><p class="piccolo">Великая битва новогоднего стола: миланский кулич против салата, который в Италии зовут «русским», а в России — оливье. Жюри: две бабушки. Скорая наготове. А ещё: вторая серия сказки, ответы на игры и, надеемся, ваши письма.</p>""",
p8_qr1="Архив номеров", p8_qr2="Регистрация на сайте",
colophon="La Gazzettona di Vladone · Vlad Editore, Таормина · Главный редактор: Il Direttore Vladone · Напечатано дома, тиражом в несколько экземпляров, с любовью и тонером. Пометки: ПРАВДА — реальный источник; ЗАМЕЧЕНО — видела редакция; САТИРА — выдумано ради смеха.",
)

CRU_RU = [
 ("АРАНЧИНИ", "Рисовые шарики: в Палермо круглые, в Катании острые"),
 ("ТАОРМИНА", "Город редакции"),
 ("ЧЕРЕПАХА", "Марк Аврелий по профессии"),
 ("ЭТНА", "Самый высокий вулкан Европы"),
 ("ЛУНА", "Уснула у Изола-Беллы в сказке"),
 ("НИКО", "Кузен, который рисует летательные аппараты"),
 ("КАТЯ", "Кузина, которая всё считает"),
 ("ФОМА", "Нонно ___, домовой"),
 ("ПАЛЕРМО", "Столица Сицилии"),
 ("КАТАНИЯ", "Город у подножия вулкана"),
 ("ШАФРАН", "Делает рис жёлтым"),
 ("ШАРФ", "Нужен ниже +16"),
 ("РЫБАК", "Тури по профессии"),
]
