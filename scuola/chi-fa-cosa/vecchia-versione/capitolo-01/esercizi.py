#!/usr/bin/env python3
"""Versioni interattive degli esercizi del capitolo 1 (stessi codici del PDF) → esercizi.json.
Si caricano sul sito da Admin → Esercizi → Importa (scegliendo il libro «Chi fa cosa?»).
Per «tocca le parole» i segni si scrivono con le parole: lo script trova da solo la posizione."""
import json, os

C = {'articolo': '#6a1b9a', 'nome': '#1565c0', 'aggettivo': '#ef6c00', 'pronome': '#5d4037', 'verbo': '#c62828',
     'avverbio': '#558b2f', 'preposizione': '#00838f', 'congiunzione': '#546e7a', 'interiezione': '#ad1457'}
def cat(*nomi): return [{'nome': n, 'colore': C[n]} for n in nomi]
def frase(testo, segni, spiegazione=''):
    """segni = {'parola': 'categoria'} (con 'parola#2' per la seconda volta che compare)."""
    parole = testo.split()
    out, visti = {}, {}
    for chiave, c in segni.items():
        w, _, n = chiave.partition('#'); n = int(n or 1)
        trovate = [i for i, x in enumerate(parole) if x.strip('.,;:!?«»') == w]
        assert len(trovate) >= n, (testo, w)
        out[str(trovate[n - 1])] = c
    return {'testo': testo, 'segni': out, 'spiegazione': spiegazione}

E = []
def es(codice, pagina, titolo, consegna, tipo, dati, livello=1, extra=0):
    E.append({'codice': codice, 'pagina': pagina, 'titolo': titolo, 'consegna': consegna, 'tipo': tipo, 'dati': dati, 'livello': livello, 'extra': extra})

es('CFC-1', 8, 'Trova i nomi', 'Scegli «nome» e tocca tutti i nomi.', 'tocca', {'categorie': cat('nome'), 'frasi': [
    frase('Il gelato al pistacchio di Bronte piace molto alla nonna.', {'gelato': 'nome', 'pistacchio': 'nome', 'Bronte': 'nome', 'nonna': 'nome'}, 'Bronte è un nome proprio di luogo: anche lui è un nome!'),
    frase('Luce dorme sul divano accanto al tablet di Vlad.', {'Luce': 'nome', 'divano': 'nome', 'tablet': 'nome', 'Vlad': 'nome'}),
    frase('A Taormina il teatro antico guarda il mare e il vulcano.', {'Taormina': 'nome', 'teatro': 'nome', 'mare': 'nome', 'vulcano': 'nome'}, '«antico» dice com\'è il teatro: è un aggettivo.')]})
es('CFC-2', 8, "Trova l'intruso", "In ogni gruppo una parola fa un lavoro diverso: quale?", 'scelta', {'domande': [
    {'testo': 'cane · lavagna · corre · amicizia', 'opzioni': ['cane', 'lavagna', 'corre', 'amicizia'], 'giusta': 2, 'spiegazione': '«corre» è un verbo, le altre sono nomi.'},
    {'testo': 'verde · allegro · Sicilia · piccolo', 'opzioni': ['verde', 'allegro', 'Sicilia', 'piccolo'], 'giusta': 2, 'spiegazione': '«Sicilia» è un nome, le altre sono aggettivi.'},
    {'testo': 'il · una · lo · sotto', 'opzioni': ['il', 'una', 'lo', 'sotto'], 'giusta': 3, 'spiegazione': '«sotto» è una preposizione, le altre sono articoli.'},
    {'testo': 'gioca · scrive · bello · dorme', 'opzioni': ['gioca', 'scrive', 'bello', 'dorme'], 'giusta': 2, 'spiegazione': '«bello» è un aggettivo, le altre sono verbi.'},
    {'testo': 'ieri · sempre · lentamente · tavolo', 'opzioni': ['ieri', 'sempre', 'lentamente', 'tavolo'], 'giusta': 3, 'spiegazione': '«tavolo» è un nome, le altre sono avverbi.'},
    {'testo': 'e · ma · però · con', 'opzioni': ['e', 'ma', 'però', 'con'], 'giusta': 3, 'spiegazione': '«con» è una preposizione, le altre sono congiunzioni.'}]}, 2)
es('CFC-3', 11, 'Nella colonna giusta', 'Trascina ogni parola nel gruppo giusto.', 'gruppi', {'gruppi': [
    {'nome': 'nome', 'elementi': ['scuola', 'Etna', 'amica']}, {'nome': 'verbo', 'elementi': ['scrivere', 'ride', 'gioca']},
    {'nome': 'aggettivo', 'elementi': ['simpatico', 'bianco', 'rotondo']}, {'nome': 'avverbio', 'elementi': ['lì', 'domani', 'sempre']}]})
es('CFC-4', 11, 'Variabile o invariabile?', 'Trascina ogni parola nel gruppo giusto.', 'gruppi', {'gruppi': [
    {'nome': 'variabile', 'elementi': ['ragazzo', 'verde', 'noi', 'corriamo']}, {'nome': 'invariabile', 'elementi': ['sotto', 'evviva', 'perché', 'volentieri']}]})
es('CFC-5', 11, 'Volgi al plurale', 'Scrivi le parole al plurale.', 'completa', {'frasi': [
    {'testo': '[Le] [compagne] [simpatiche] [arrivano] sempre presto.', 'spiegazione': '«sempre» e «presto» sono avverbi: non cambiano.'},
    {'testo': '[I] [libri] [nuovi] [sono] sopra [gli] [zaini].', 'spiegazione': '«sopra» è una preposizione: non cambia.'},
    {'testo': '[Gli] [studenti] [curiosi] [alzano] [le] [mani].', 'spiegazione': '«mano» al plurale fa «mani» (femminile).'}]}, 2)
es('CFC-6', 11, 'Vero o falso', 'Decidi se ogni frase è vera o falsa.', 'vf', {'domande': [
    {'testo': "L'avverbio cambia al plurale.", 'vero': False, 'spiegazione': "L'avverbio è invariabile."},
    {'testo': 'Il pronome prende il posto di un nome.', 'vero': True},
    {'testo': '«Città» è invariabile, quindi non è un nome.', 'vero': False, 'spiegazione': '«Città» è un nome (famiglia variabile), anche se ha la stessa forma al singolare e al plurale.'},
    {'testo': 'Il verbo cambia per persona, numero, tempo e modo.', 'vero': True},
    {'testo': 'In «Vlad la saluta», «la» è un articolo.', 'vero': False, 'spiegazione': 'Qui «la» prende il posto di un nome (la cagnolina): è un pronome.'},
    {'testo': 'Le interiezioni esprimono emozioni.', 'vero': True}]}, 2)
es('CFC-7', 11, 'Scrivi tu', 'Scrivi una frase sulla tua scuola con articolo, nome, aggettivo, verbo e avverbio.', 'aperta', {'domande': [
    {'testo': 'La tua frase:', 'esempio': 'La (articolo) palestra (nome) nuova (aggettivo) apre (verbo) domani (avverbio).'}]}, 3)
es('CFC-8', 13, 'Colora le parole', 'Scegli il colore e tocca articoli, nomi, aggettivi e verbi.', 'tocca', {'categorie': cat('articolo', 'nome', 'aggettivo', 'verbo'), 'frasi': [
    frase('Il vulcano sputa una lava rossa.', {'Il': 'articolo', 'vulcano': 'nome', 'sputa': 'verbo', 'una': 'articolo', 'lava': 'nome', 'rossa': 'aggettivo'}),
    frase('La prof gentile corregge le verifiche.', {'La': 'articolo', 'prof': 'nome', 'gentile': 'aggettivo', 'corregge': 'verbo', 'le': 'articolo', 'verifiche': 'nome'}),
    frase('Un gabbiano curioso ruba la focaccia.', {'Un': 'articolo', 'gabbiano': 'nome', 'curioso': 'aggettivo', 'ruba': 'verbo', 'la': 'articolo', 'focaccia': 'nome'})]})
es('CFC-9', 13, "Completa l'analisi", "«Ieri noi abbiamo visto un delfino nel mare»: scrivi la parte del discorso.", 'completa', {'banca': True, 'frasi': [
    {'testo': 'Ieri → [avverbio]'}, {'testo': 'noi → [pronome]'}, {'testo': 'abbiamo visto → [verbo]'}, {'testo': 'un → [articolo]'},
    {'testo': 'delfino → [nome]'}, {'testo': 'nel → [preposizione]', 'spiegazione': '«nel» = in + il: preposizione articolata.'}, {'testo': 'mare → [nome]'}]}, 2)
es('CFC-10', 13, "L'errore di Luce", 'Luce ha analizzato «Vlad scrive bene». Ogni riga è giusta (vero) o sbagliata (falso)?', 'vf', {'domande': [
    {'testo': 'Vlad: nome proprio di persona, maschile singolare.', 'vero': True},
    {'testo': 'scrive: aggettivo, femminile singolare.', 'vero': False, 'spiegazione': '«scrive» è un verbo: indicativo presente, 3ª persona singolare.'},
    {'testo': 'bene: avverbio di modo.', 'vero': True},
    {'testo': 'Vlad: articolo determinativo.', 'vero': False, 'spiegazione': 'Vlad è un nome proprio.'}]}, 2)
P = ['articolo', 'nome', 'aggettivo', 'pronome', 'verbo', 'avverbio', 'preposizione', 'congiunzione', 'interiezione']
def q(t, g, sp=''): return {'testo': t, 'opzioni': P, 'giusta': P.index(g), 'spiegazione': sp}
es('CFC-11', 13, 'Stessa parola, lavoro diverso', 'Che parte del discorso è la parola tra asterischi?', 'scelta', {'domande': [
    q('Luce cammina *piano* per non svegliare il gatto.', 'avverbio', 'Vuol dire «lentamente»: dice come cammina.'),
    q("Al secondo *piano* c'è la palestra.", 'nome'),
    q('Il *dovere* viene prima del gioco.', 'nome', 'Ha l\'articolo davanti: è un verbo usato come nome.'),
    q('Oggi dobbiamo *studiare* molto.', 'verbo'),
    q('Vlad *la* chiama e Luce corre.', 'pronome', '«la» sta al posto di «Luce».')]}, 3)
es('CFC-12', 13, 'Rimetti in ordine', 'Trascina le parole per formare la frase.', 'ordina', {'frasi': [
    {'testo': 'Vlad mangia una granita alla mandorla.'}, {'testo': 'Luce insegue sempre i gabbiani.'}]})
es('CFC-13', 13, 'Le parti invariabili', 'Trascina ogni parola nel gruppo giusto.', 'gruppi', {'gruppi': [
    {'nome': 'avverbio', 'elementi': ['qui', 'presto', 'volentieri']}, {'nome': 'preposizione', 'elementi': ['tra', 'per', 'su']},
    {'nome': 'congiunzione', 'elementi': ['ma', 'perché', 'oppure']}, {'nome': 'interiezione', 'elementi': ['ahi', 'evviva', 'uffa']}]}, 2, 1)
es('CFC-14', 14, 'Luce e il gabbiano: hai capito?', 'Vero o falso?', 'vf', {'domande': [
    {'testo': 'La storia si svolge a Catania.', 'vero': False, 'spiegazione': 'Si svolge a Giardini Naxos.'},
    {'testo': 'Luce abbaia alle onde.', 'vero': True}, {'testo': 'Il gabbiano ruba la focaccia.', 'vero': True},
    {'testo': 'Alla fine Vlad è arrabbiato.', 'vero': False, 'spiegazione': 'Ha perso la merenda, ma ha passato un bel pomeriggio.'}]})
es('CFC-15', 14, 'Nomi e aggettivi nel racconto', 'Segna i nomi e gli aggettivi.', 'tocca', {'categorie': cat('nome', 'aggettivo'), 'frasi': [
    frase('Domenica pomeriggio io e Luce siamo andati alla spiaggia di Giardini Naxos.', {'Domenica': 'nome', 'pomeriggio': 'nome', 'Luce': 'nome', 'spiaggia': 'nome', 'Giardini': 'nome', 'Naxos': 'nome'}, '«Giardini Naxos» è un nome proprio di luogo fatto di due parole.'),
    frase("All'improvviso un gabbiano grande e grigio si è posato vicino al nostro asciugamano.", {'gabbiano': 'nome', 'grande': 'aggettivo', 'grigio': 'aggettivo', 'nostro': 'aggettivo', 'asciugamano': 'nome'}, '«nostro» è un aggettivo possessivo.'),
    frase('Il gabbiano ha preso la focaccia con il becco ed è volato via velocemente.', {'gabbiano': 'nome', 'focaccia': 'nome', 'becco': 'nome'})]}, 2)
es('CFC-17', 16, 'Verifica: che parte del discorso è?', 'Scegli la parte del discorso.', 'scelta', {'domande': [
    q('montagna', 'nome'), q('noi', 'pronome'), q('tra', 'preposizione'), q('simpatico', 'aggettivo'),
    q('ahi', 'interiezione'), q('scrivono', 'verbo'), q('oggi', 'avverbio'), q('ma', 'congiunzione')]})

json.dump({'versione': 1, 'esercizi': E}, open(os.path.join(os.path.dirname(__file__), 'esercizi.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print(len(E), 'esercizi → esercizi.json')
