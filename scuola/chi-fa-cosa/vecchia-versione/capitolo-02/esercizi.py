#!/usr/bin/env python3
"""Versioni interattive degli esercizi del capitolo 2 (stessi codici del PDF) → esercizi.json.
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
P2 = lambda t, a, b, g, sp='': {'testo': t, 'opzioni': [a, b], 'giusta': 0 if g == a else 1, 'spiegazione': sp}

es('CFC-18', 18, 'Trova i nomi', 'Scegli «nome» e tocca tutti i nomi.', 'tocca', {'categorie': cat('nome'), 'frasi': [
    frase('La mamma di Giulia prepara le arancine per la festa.', {'mamma': 'nome', 'Giulia': 'nome', 'arancine': 'nome', 'festa': 'nome'}),
    frase('Un gatto nero dorme sul tetto della chiesa.', {'gatto': 'nome', 'tetto': 'nome', 'chiesa': 'nome'}, '«nero» dice com\'è il gatto: è un aggettivo.'),
    frase('Luce ha paura del temporale ma ama la neve del vulcano.', {'Luce': 'nome', 'paura': 'nome', 'temporale': 'nome', 'neve': 'nome', 'vulcano': 'nome'}, '«paura» è un nome astratto: si prova, ma non si vede.')]})
es('CFC-19', 18, 'Maiuscola o minuscola?', 'Scegli la forma giusta.', 'scelta', {'domande': [
    P2('Il fiume … è freddo.', 'Alcantara', 'alcantara', 'Alcantara', 'Nome proprio di fiume: maiuscola.'),
    P2('Mio zio fa il …', 'Pescatore', 'pescatore', 'pescatore', 'Nome comune di persona: minuscola.'),
    P2('In estate andiamo a …', 'Siracusa', 'siracusa', 'Siracusa'),
    P2('Il mio … si chiama Luce.', 'Cane', 'cane', 'cane'),
    P2('Il mio cane si chiama …', 'Luce', 'luce', 'Luce', 'Nome proprio di animale: maiuscola.'),
    P2('Ogni … ho italiano.', 'Lunedì', 'lunedì', 'lunedì', 'In italiano i giorni della settimana vanno con la minuscola.'),
    P2('Il … bagna Taormina.', 'Mar Ionio', 'mar ionio', 'Mar Ionio')]}, 2)
es('CFC-20', 21, 'Concreto o astratto?', 'Trascina ogni nome nel gruppo giusto.', 'gruppi', {'gruppi': [
    {'nome': 'concreto', 'elementi': ['zaino', 'profumo', 'onda', 'musica', 'granita']},
    {'nome': 'astratto', 'elementi': ['coraggio', 'gioia', 'noia', 'fiducia', 'gelosia']}]})
G = ['uccelli', 'navi', 'pecore', 'musicisti', 'api', 'pini']
es('CFC-21', 21, 'I nomi collettivi', 'Di che cosa è fatto ogni gruppo?', 'scelta', {'domande': [
    {'testo': n, 'opzioni': G, 'giusta': G.index(g)} for n, g in [('gregge', 'pecore'), ('sciame', 'api'), ('stormo', 'uccelli'), ('flotta', 'navi'), ('pineta', 'pini'), ('orchestra', 'musicisti')]]})
es('CFC-22', 21, 'Il femminile', 'Scrivi il femminile.', 'completa', {'frasi': [
    {'testo': 'il maestro → la [maestra]'}, {'testo': 'il dottore → la [dottoressa]'}, {'testo': 'il pittore → la [pittrice]'},
    {'testo': 'il genero → la [nuora]', 'spiegazione': 'Nome indipendente: il femminile è una parola diversa.'},
    {'testo': 'il gallo → la [gallina]'}, {'testo': 'il collega → la [collega]', 'spiegazione': 'Nome di genere comune: cambia solo l\'articolo.'}]}, 2)
es('CFC-23', 21, 'Il plurale', 'Scrivi il plurale. Attenzione ai nomi speciali!', 'completa', {'frasi': [
    {'testo': 'il lago → i [laghi]'}, {'testo': "l'amico → gli [amici]", 'spiegazione': '«amico» è un\'eccezione: amici, non amichi.'},
    {'testo': 'la virtù → le [virtù]', 'spiegazione': 'Nome accentato: invariabile.'}, {'testo': "l'uovo → le [uova]", 'spiegazione': 'Plurale irregolare, femminile.'},
    {'testo': 'il film → i [film]', 'spiegazione': 'Nome straniero: invariabile.'}, {'testo': 'la tesi → le [tesi]'},
    {'testo': "l'uomo → gli [uomini]"}, {'testo': 'il medico → i [medici]'}]}, 2)
es('CFC-24', 21, 'Vero o falso', 'Decidi se ogni frase è vera o falsa.', 'vf', {'domande': [
    {'testo': '«Profumo» è un nome astratto perché non si tocca.', 'vero': False, 'spiegazione': 'Si sente con il naso: è concreto.'},
    {'testo': '«La tigre» si usa sia per il maschio sia per la femmina.', 'vero': True, 'spiegazione': 'È un nome di genere promiscuo.'},
    {'testo': '«Forbici» non ha il singolare.', 'vero': True, 'spiegazione': 'È un nome difettivo.'},
    {'testo': 'I giorni della settimana si scrivono con la maiuscola.', 'vero': False},
    {'testo': '«Le braccia» e «i bracci» hanno significati diversi.', 'vero': True, 'spiegazione': 'Le braccia del corpo, i bracci di un fiume o di una gru.'}]}, 2)
es('CFC-25', 23, 'I nomi alterati', 'Trascina ogni nome nel gruppo giusto.', 'gruppi', {'gruppi': [
    {'nome': 'diminutivo', 'elementi': ['casetta', 'libretto']}, {'nome': 'accrescitivo', 'elementi': ['nasone', 'omone']},
    {'nome': 'vezzeggiativo', 'elementi': ['cagnolino', 'boccuccia']}, {'nome': 'dispregiativo', 'elementi': ['ragazzaccio', 'tempaccio']}]})
es('CFC-26', 23, 'Nomi derivati', 'Scrivi il nome derivato.', 'completa', {'frasi': [
    {'testo': 'chi vende i fiori: [fioraio|fiorista]'}, {'testo': 'chi lavora sul mare: [marinaio]'}, {'testo': 'dove si fa il pane: [panificio|panetteria]'},
    {'testo': 'chi porta la posta: [postino]'}, {'testo': 'dove si vendono i libri: [libreria]'}, {'testo': 'chi suona il violino: [violinista]'}]})
es('CFC-27', 23, 'Alterato o falso alterato?', 'Scegli: alterato o falso alterato?', 'scelta', {'domande': [
    P2('mattone', 'alterato', 'falso alterato', 'falso alterato', 'Non è un grande matto!'), P2('scarpone', 'alterato', 'falso alterato', 'alterato', 'È una scarpa grande.'),
    P2('bottone', 'alterato', 'falso alterato', 'falso alterato', 'Non è una grande botte.'), P2('gattino', 'alterato', 'falso alterato', 'alterato'),
    P2('tacchino', 'alterato', 'falso alterato', 'falso alterato', 'Non è un piccolo tacco: è un animale.'), P2('fiorellino', 'alterato', 'falso alterato', 'alterato')]}, 2)
es('CFC-28', 23, 'Il plurale dei composti', 'Scrivi il plurale.', 'completa', {'frasi': [
    {'testo': 'il pomodoro → i [pomodori]'}, {'testo': 'il pescespada → i [pescispada]'}, {'testo': 'la cassaforte → le [casseforti]'},
    {'testo': 'il cavatappi → i [cavatappi]', 'spiegazione': 'Verbo + nome plurale: non cambia.'}, {'testo': 'il capolavoro → i [capolavori]'}, {'testo': 'il francobollo → i [francobolli]'}]}, 3)
es('CFC-29', 23, "L'analisi del nome", 'Fai l\'analisi grammaticale dei nomi.', 'aperta', {'domande': [
    {'testo': 'Taormina', 'esempio': 'nome proprio di luogo (città), concreto, femminile singolare'},
    {'testo': 'stormo', 'esempio': 'nome comune di animale, concreto, collettivo, maschile singolare'},
    {'testo': 'casetta', 'esempio': 'nome comune di cosa, concreto, alterato (diminutivo di casa), femminile singolare'}]}, 3)
es('CFC-30', 23, 'I nomi sovrabbondanti', 'Scegli il plurale giusto.', 'scelta', {'domande': [
    P2('Vlad ha alzato le … al cielo.', 'braccia', 'bracci', 'braccia', 'Del corpo: le braccia.'),
    P2('Il fiume si divide in due …', 'braccia', 'bracci', 'bracci'),
    P2('Le … di Siracusa sono antiche.', 'mura', 'muri', 'mura', 'Le mura di una città; i muri di una casa.'),
    P2('Luce ha nascosto gli … in giardino.', 'ossi', 'ossa', 'ossi', 'Gli ossi del cane; le ossa del corpo.'),
    P2('Ho contato sulle …', 'dita', 'diti', 'dita')]}, 2, 1)
es('CFC-31', 24, 'Luce alla Pescheria: hai capito?', 'Vero o falso?', 'vf', {'domande': [
    {'testo': 'Vlad va al mercato con la nonna.', 'vero': False, 'spiegazione': 'Ci va con Luce: la nonna lo ha mandato.'},
    {'testo': 'Luce guarda il pescespada.', 'vero': True}, {'testo': 'Vlad compra le sarde.', 'vero': True},
    {'testo': 'Luce non mangia il biscotto.', 'vero': False, 'spiegazione': 'Lo mangia in un secondo!'}]})
es('CFC-32', 24, 'I nomi del racconto', 'Tocca tutti i nomi.', 'tocca', {'categorie': cat('nome'), 'frasi': [
    frase('Sabato mattina la nonna mi ha mandato a Catania a comprare il pesce.', {'Sabato': 'nome', 'mattina': 'nome', 'nonna': 'nome', 'Catania': 'nome', 'pesce': 'nome'}, '«Sabato» qui ha la maiuscola solo perché è all\'inizio della frase.'),
    frase("Davanti a un banco c'era un pescespada enorme.", {'banco': 'nome', 'pescespada': 'nome'}, '«pescespada» è un nome composto.'),
    frase('Tornando a casa ho pensato che il mercato è come un vocabolario: ogni banco è pieno di nomi.', {'casa': 'nome', 'mercato': 'nome', 'vocabolario': 'nome', 'banco': 'nome', 'nomi': 'nome'})]}, 2)
es('CFC-34', 26, 'Verifica: che tipo di nome è?', 'Scegli il tipo giusto.', 'scelta', {'domande': [
    P2('Palermo', 'comune', 'proprio', 'proprio'), P2('tristezza', 'concreto', 'astratto', 'astratto'),
    P2('folla', 'individuale', 'collettivo', 'collettivo'), P2('giardiniere', 'primitivo', 'derivato', 'derivato'),
    P2('libraccio', 'alterato', 'composto', 'alterato'), P2('portafoglio', 'derivato', 'composto', 'composto'),
    P2('rumore', 'concreto', 'astratto', 'concreto'), P2('pesce', 'primitivo', 'alterato', 'primitivo')]})

json.dump({'versione': 1, 'esercizi': E}, open(os.path.join(os.path.dirname(__file__), 'esercizi.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print(len(E), 'esercizi → esercizi.json')
