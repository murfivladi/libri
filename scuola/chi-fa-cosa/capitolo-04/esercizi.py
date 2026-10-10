#!/usr/bin/env python3
"""Versioni interattive degli esercizi del capitolo 4 (stessi codici del PDF) → esercizi.json.
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
def S(t, opz, g, sp=''): return {'testo': t, 'opzioni': opz, 'giusta': opz.index(g), 'spiegazione': sp}

es('CFC-52', 38, 'Trova gli aggettivi', 'Scegli «aggettivo» e tocca tutti gli aggettivi.', 'tocca', {'categorie': cat('aggettivo'), 'frasi': [
    frase('Il mare calmo e azzurro brilla sotto il sole caldo.', {'calmo': 'aggettivo', 'azzurro': 'aggettivo', 'caldo': 'aggettivo'}),
    frase('Una vecchia barca rossa dorme sulla spiaggia deserta.', {'vecchia': 'aggettivo', 'rossa': 'aggettivo', 'deserta': 'aggettivo'}, '«vecchia» sta prima del nome, ma è comunque un aggettivo.'),
    frase('Luce è una cagnolina curiosa, allegra e golosa.', {'curiosa': 'aggettivo', 'allegra': 'aggettivo', 'golosa': 'aggettivo'})]})
es('CFC-53', 38, "L'accordo", "Scegli la forma giusta dell'aggettivo.", 'scelta', {'domande': [
    S('le scarpe …', ['nuovi', 'nuove'], 'nuove'), S('un gelato …', ['fresco', 'freschi'], 'fresco'),
    S('i ragazzi …', ['felice', 'felici'], 'felici'), S('la torta …', ['dolce', 'dolci'], 'dolce'),
    S('gli zaini …', ['pesanti', 'pesante'], 'pesanti'), S('le pareti …', ['blu', 'blue'], 'blu', '«blu» è invariabile.')]})
es('CFC-54', 41, "Accorda l'aggettivo", "Scrivi l'aggettivo accordato con il nome.", 'completa', {'frasi': [
    {'testo': 'le onde (alto) → [alte]'}, {'testo': 'i fiori (rosa) → [rosa]', 'spiegazione': '«rosa» è invariabile.'},
    {'testo': 'gli amici (simpatico) → [simpatici]'}, {'testo': 'le strade (lungo) → [lunghe]'},
    {'testo': 'i bambini (gentile) → [gentili]'}, {'testo': 'Vlad e Luce (stanco) → [stanchi]', 'spiegazione': 'Maschile + femminile → maschile plurale.'}]})
es('CFC-55', 41, 'I comparativi', 'Completa con il comparativo indicato.', 'completa', {'frasi': [
    {'testo': "(maggioranza) L'Etna è [più] alta [del] Vesuvio."},
    {'testo': '(minoranza) Il gatto è [meno] goloso [di] Luce.'},
    {'testo': '(uguaglianza) Giulia è [tanto|così] brava [quanto|come] Marco.'}]})
G = ['positivo', 'comparativo di maggioranza', 'comparativo di minoranza', 'superlativo relativo', 'superlativo assoluto']
es('CFC-56', 41, 'Di che grado è?', "Scegli il grado dell'aggettivo tra asterischi.", 'scelta', {'domande': [
    S('Questa granita è *buonissima*.', G, 'superlativo assoluto'), S('Vlad è *il più alto* della classe.', G, 'superlativo relativo'),
    S('Il mare oggi è *calmo*.', G, 'positivo'), S('Luce è *meno ubbidiente* del gatto.', G, 'comparativo di minoranza')]}, 2)
es('CFC-57', 41, 'Le forme speciali', 'Scrivi la forma speciale.', 'completa', {'frasi': [
    {'testo': 'più buono → [migliore]'}, {'testo': 'cattivissimo → [pessimo]'}, {'testo': 'più piccolo → [minore]'},
    {'testo': 'grandissimo → [massimo]'}, {'testo': 'buonissimo → [ottimo]'}, {'testo': 'più cattivo → [peggiore]'}]}, 2)
es('CFC-58', 41, 'Vero o falso', 'Decidi se ogni frase è vera o falsa.', 'vf', {'domande': [
    {'testo': '«Felice» ha quattro forme.', 'vero': False, 'spiegazione': 'È di seconda classe: felice, felici.'},
    {'testo': '«Blu» è un aggettivo invariabile.', 'vero': True},
    {'testo': '«Più migliore» è corretto.', 'vero': False, 'spiegazione': '«migliore» è già un comparativo.'},
    {'testo': '«Un vecchio amico» e «un amico vecchio» vogliono dire la stessa cosa.', 'vero': False, 'spiegazione': 'Un vecchio amico = da tanto tempo; un amico vecchio = anziano.'}]}, 2)
es('CFC-59', 41, 'Confronta due animali', 'Scrivi un comparativo di maggioranza, uno di uguaglianza e un superlativo.', 'aperta', {'domande': [
    {'testo': 'Le tue frasi:', 'esempio': "Il ghepardo è più veloce del leone. Il gatto è tanto curioso quanto Luce. La giraffa è l'animale più alto della savana."}]}, 3)
es('CFC-60', 43, 'Gli aggettivi determinativi', 'Trascina ogni aggettivo nel gruppo giusto.', 'gruppi', {'gruppi': [
    {'nome': 'possessivo', 'elementi': ['nostro', 'loro']}, {'nome': 'dimostrativo', 'elementi': ['questa', 'quegli']},
    {'nome': 'indefinito', 'elementi': ['ogni', 'qualche']}, {'nome': 'numerale', 'elementi': ['terzo', 'cinque']},
    {'nome': 'interrogativo', 'elementi': ['quale', 'quanti']}]})
AP = ['aggettivo', 'pronome']
es('CFC-61', 43, 'Aggettivo o pronome?', 'La parola tra asterischi è un aggettivo o un pronome?', 'scelta', {'domande': [
    S('*Questa* granita è al limone.', AP, 'aggettivo', 'Accompagna il nome «granita».'), S('*Questa* è la mia.', AP, 'pronome', 'Sta da sola, al posto del nome.'),
    S('*Molti* amici sono venuti.', AP, 'aggettivo'), S('*Molti* sono rimasti a casa.', AP, 'pronome'),
    S('Il *mio* cane si chiama Luce.', AP, 'aggettivo'), S('Il tuo zaino è blu, il *mio* è rosso.', AP, 'pronome', '«il mio» = il mio zaino: sta al posto del nome.')]}, 2)
Q = ['quel', 'quello', "quell'", 'quei', 'quegli', 'quella', 'quelle']
es('CFC-62', 43, 'Le forme di quello', 'Scegli la forma giusta di «quello».', 'scelta', {'domande': [
    S('… gabbiano', Q, 'quel'), S('… scoglio', Q, 'quello', 'Davanti a s + consonante: quello.'), S('… isola', Q, "quell'"),
    S('… studenti', Q, 'quegli'), S('… fiori', Q, 'quei'), S('… amici', Q, 'quegli', 'Maschile plurale davanti a vocale: quegli.')]}, 2)
es('CFC-63', 43, "L'analisi dell'aggettivo", "«Quei due gatti sono più furbi di Luce»: completa l'analisi.", 'completa', {'banca': True, 'frasi': [
    {'testo': 'Quei → aggettivo [dimostrativo], [maschile plurale]'},
    {'testo': 'due → aggettivo [numerale cardinale]'},
    {'testo': 'più furbi → aggettivo [qualificativo], grado [comparativo di maggioranza]'}]}, 3)
es('CFC-64', 43, 'Rimetti in ordine', 'Trascina le parole per formare la frase.', 'ordina', {'frasi': [
    {'testo': 'Luce è una cagnolina golosissima.'}, {'testo': 'Questo gelato è più buono di quello.'}]})
B = ['bel', 'bello', "bell'", 'bei', 'begli']
es('CFC-65', 43, "Bel, bello o bell'?", 'Scegli la forma giusta di «bello».', 'scelta', {'domande': [
    S('Che … mare!', B, 'bel'), S('Che … zaino!', B, 'bello'), S('Che … albero!', B, "bell'"),
    S('Che … occhi!', B, 'begli'), S('Che … fiori!', B, 'bei'), S('Che … spettacolo!', B, 'bello', 'Davanti a s + consonante: bello.')]}, 2, 1)
es('CFC-66', 44, 'Il concorso: hai capito?', 'Vero o falso?', 'vf', {'domande': [
    {'testo': 'Il concorso è a Catania.', 'vero': False, 'spiegazione': 'È in piazza a Taormina.'},
    {'testo': 'Luce finge di dormire.', 'vero': True},
    {'testo': 'Il gelato era alla fragola.', 'vero': False, 'spiegazione': 'Era un cono al pistacchio.'},
    {'testo': 'Luce vince una coccarda.', 'vero': True}]})
es('CFC-67', 44, 'Gli aggettivi del racconto', 'Tocca tutti gli aggettivi qualificativi.', 'tocca', {'categorie': cat('aggettivo'), 'frasi': [
    frase("C'era un barboncino bianco e profumato, un alano enorme e tranquillo, un bassotto lungo come un salame.", {'bianco': 'aggettivo', 'profumato': 'aggettivo', 'enorme': 'aggettivo', 'tranquillo': 'aggettivo', 'lungo': 'aggettivo'}),
    frase('Gli altri concorrenti erano bellissimi.', {'bellissimi': 'aggettivo'}, '«bellissimi» è un superlativo assoluto. «altri» è un aggettivo indefinito, non qualificativo.'),
    frase('Luce ha vinto una coccarda rossa e una scorta di biscotti.', {'rossa': 'aggettivo'})]}, 2)
T = ['qualificativo', 'possessivo', 'dimostrativo', 'indefinito', 'numerale', 'interrogativo']
es('CFC-69', 46, "Verifica: di che tipo è l'aggettivo?", "Scegli il tipo dell'aggettivo.", 'scelta', {'domande': [
    S('verde', T, 'qualificativo'), S('vostro', T, 'possessivo'), S('quella', T, 'dimostrativo'), S('nessun', T, 'indefinito'),
    S('sesto', T, 'numerale'), S('quale', T, 'interrogativo'), S('timido', T, 'qualificativo'), S('alcune', T, 'indefinito')]})

json.dump({'versione': 1, 'esercizi': E}, open(os.path.join(os.path.dirname(__file__), 'esercizi.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print(len(E), 'esercizi → esercizi.json')
