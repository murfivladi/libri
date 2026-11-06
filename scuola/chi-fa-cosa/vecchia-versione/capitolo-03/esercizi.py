#!/usr/bin/env python3
"""Versioni interattive degli esercizi del capitolo 3 (stessi codici del PDF) → esercizi.json.
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

es('CFC-35', 28, "L'articolo giusto", "Scegli l'articolo giusto.", 'scelta', {'domande': [
    S('… sport', ['il', 'lo'], 'lo', 's + consonante: lo.'), S('… orologio', ['il', "l'"], "l'", 'Davanti a vocale: l\'.'),
    S('… estate', ['la', "l'"], "l'"), S('… zaini', ['i', 'gli'], 'gli', 'Davanti a z: gli.'),
    S('… gnomo', ['il', 'lo'], 'lo', 'Davanti a gn: lo.'), S('… cani', ['i', 'gli'], 'i'),
    S('… psicologo', ['un', 'uno'], 'uno', 'Davanti a ps: uno.'), S('… isola', ['un', "un'"], "un'", 'Isola è femminile: un\' con l\'apostrofo.')]})
es('CFC-36', 28, 'Trova gli articoli', 'Scegli «articolo» e tocca tutti gli articoli.', 'tocca', {'categorie': cat('articolo'), 'frasi': [
    frase('Il nonno ha comprato una bicicletta e uno zainetto per la nipote.', {'Il': 'articolo', 'una': 'articolo', 'uno': 'articolo', 'la': 'articolo'}),
    frase('Gli studenti leggono un racconto con la maestra.', {'Gli': 'articolo', 'un': 'articolo', 'la': 'articolo'}),
    frase('Luce ha nascosto le scarpe sotto il letto.', {'le': 'articolo', 'il': 'articolo'}, '«sotto» è una preposizione, non un articolo.')]})
es('CFC-37', 31, 'Articolo e plurale', "Scrivi l'articolo determinativo al singolare e al plurale.", 'completa', {'frasi': [
    {'testo': '[lo] specchio → [gli] specchi'}, {'testo': "[l'] elefante → [gli] elefanti"}, {'testo': '[la] zucchina → [le] zucchine'},
    {'testo': '[lo] yogurt → [gli] yogurt', 'spiegazione': 'Davanti a y: lo / gli. «yogurt» è straniero: invariabile.'},
    {'testo': '[il] pesce → [i] pesci'}, {'testo': "[l'] onda → [le] onde", 'spiegazione': 'Al plurale niente apostrofo: le onde.'}]})
es('CFC-38', 31, "Un, uno, una o un'?", "Scegli l'articolo indeterminativo.", 'scelta', {'domande': [
    S(f'… {n}', ['un', 'uno', 'una', "un'"], g, sp) for n, g, sp in [('aquila', "un'", 'Femminile davanti a vocale.'), ('aereo', 'un', 'Maschile: niente apostrofo.'),
    ('scoiattolo', 'uno', ''), ('arancia', "un'", ''), ('zebra', 'una', ''), ('uovo', 'un', 'Maschile: un uovo, senza apostrofo.'), ('zaino', 'uno', ''), ('ape', "un'", '')]]})
es('CFC-39', 31, 'Partitivo o preposizione?', 'La parola tra asterischi è un articolo partitivo o una preposizione articolata?', 'scelta', {'domande': [
    S(t, ['articolo partitivo', 'preposizione articolata'], g, sp) for t, g, sp in [
    ('Al mercato ho comprato *delle* arance.', 'articolo partitivo', '= alcune arance.'),
    ('Il profumo *delle* arance riempie la cucina.', 'preposizione articolata', '= di + le: il profumo di che cosa?'),
    ('Vuoi *dello* zucchero nel caffè?', 'articolo partitivo', '= un po\' di zucchero.'),
    ('La casa *dei* nonni è vicino al mare.', 'preposizione articolata', '= di + i: la casa di chi?'),
    ('In spiaggia abbiamo visto *dei* granchi.', 'articolo partitivo', '= alcuni granchi.')]]}, 2)
es('CFC-40', 31, 'Vero o falso', 'Decidi se ogni frase è vera o falsa.', 'vf', {'domande': [
    {'testo': '«Un\'amico» è scritto in modo corretto.', 'vero': False, 'spiegazione': '«amico» è maschile: un amico, senza apostrofo.'},
    {'testo': "L'articolo indeterminativo non ha il plurale.", 'vero': True},
    {'testo': 'Si dice «lo gnocco» e «gli gnocchi».', 'vero': True},
    {'testo': 'Davanti a «nuovo zaino» si mette «lo».', 'vero': False, 'spiegazione': 'Conta la parola che segue subito: «nuovo» → il nuovo zaino.'}]}, 2)
es('CFC-41', 31, 'La lista della spesa', 'Scrivi la lista della spesa per la festa di Luce con tre partitivi e un indeterminativo.', 'aperta', {'domande': [
    {'testo': 'La tua lista:', 'esempio': 'Compriamo del pane, dello zucchero, delle arance e una torta.'}]}, 3)
es('CFC-42', 33, 'Con o senza articolo?', 'Scegli la forma giusta.', 'scelta', {'domande': [
    S('… è un vulcano.', ['Etna', "L'Etna"], "L'Etna", 'I nomi dei monti vogliono l\'articolo.'),
    S('… è una città.', ['Catania', 'La Catania'], 'Catania', 'I nomi di città non vogliono l\'articolo.'),
    S('… è un fiume.', ['Po', 'Il Po'], 'Il Po'), S("… è un'isola.", ['Sicilia', 'La Sicilia'], 'La Sicilia'),
    S('… è mia amica.', ['Giulia', 'La Giulia'], 'Giulia', 'Con i nomi di persona l\'articolo non si usa (in alcune regioni si sente, ma non è corretto nella lingua scritta).'),
    S('Abito vicino …', ['a Palermo', 'al Palermo'], 'a Palermo')]})
es('CFC-43', 33, 'I nomi di parentela', 'Scegli la forma giusta.', 'scelta', {'domande': [
    S('… fa il pescatore.', ['Mio zio', 'Il mio zio'], 'Mio zio', 'Parente al singolare con «mio»: niente articolo.'),
    S('… vivono a Messina.', ['Miei cugini', 'I miei cugini'], 'I miei cugini', 'Al plurale l\'articolo ci vuole.'),
    S('… dorme.', ['Mia sorellina', 'La mia sorellina'], 'La mia sorellina', 'Nome alterato: l\'articolo ci vuole.'),
    S('… lavora a scuola.', ['Suo padre', 'Il suo padre'], 'Suo padre'),
    S('… cucina bene.', ['Loro nonna', 'La loro nonna'], 'La loro nonna', 'Con «loro» l\'articolo ci vuole sempre.'),
    S('… è gentile.', ['Tua madre', 'La tua madre'], 'Tua madre')]}, 2)
P = ['verbo', 'aggettivo', 'congiunzione', 'avverbio']
es('CFC-44', 33, "L'articolo trasforma le parole", 'Con l\'articolo queste parole sono diventate nomi. Che cosa erano prima?', 'scelta', {'domande': [
    S('Il *dormire* fa bene.', P, 'verbo'), S('Mi piace il *blu* del mare.', P, 'aggettivo'),
    S('Non capisco il *perché*.', P, 'congiunzione'), S('Il *bello* deve ancora venire.', P, 'aggettivo')]}, 2)
es('CFC-45', 33, "L'analisi dell'articolo", "«Lo zio ha portato degli gnocchi e un'anguria»: completa l'analisi.", 'completa', {'banca': True, 'frasi': [
    {'testo': 'Lo → articolo [determinativo], [maschile singolare]'},
    {'testo': 'degli → articolo [partitivo], [maschile plurale]'},
    {'testo': "un' → articolo [indeterminativo], [femminile singolare]"}]}, 3)
es('CFC-46', 33, 'Rimetti in ordine', 'Trascina le parole per formare la frase.', 'ordina', {'frasi': [
    {'testo': 'Luce nasconde dei biscotti nello zaino.'}, {'testo': 'Luce invita gli amici alla festa.'}]})
es('CFC-47', 33, "Caccia all'errore", "In ogni frase c'è un articolo sbagliato o giusto? Decidi.", 'vf', {'domande': [
    {'testo': 'Ho visto uno zebra allo zoo.', 'vero': False, 'spiegazione': 'zebra è femminile: una zebra.'},
    {'testo': 'Gli gnomi abitano nel bosco.', 'vero': True},
    {'testo': "Luce ha un'occhiale rotto.", 'vero': False, 'spiegazione': '«occhiali» si usa solo al plurale: gli occhiali.'},
    {'testo': 'Ho regalato un orologio a mio padre.', 'vero': True},
    {'testo': 'Il zio di Vlad vive a Siracusa.', 'vero': False, 'spiegazione': 'Davanti a z: lo zio.'},
    {'testo': "Ho incontrato un'amica in piazza.", 'vero': True}]}, 2, 1)
es('CFC-48', 34, "Luce sull'Etna: hai capito?", 'Vero o falso?', 'vf', {'domande': [
    {'testo': 'Luce aveva già visto la neve.', 'vero': False, 'spiegazione': 'La vede per la prima volta.'},
    {'testo': 'Le pietre di lava sono pesanti.', 'vero': False, 'spiegazione': 'Sono nere e leggere, piene di piccoli buchi.'},
    {'testo': 'Nel rifugio bevono della cioccolata.', 'vero': True}, {'testo': 'Al ritorno Luce dorme.', 'vero': True}]})
es('CFC-49', 34, 'Gli articoli del racconto', 'Tocca tutti gli articoli.', 'tocca', {'categorie': cat('articolo'), 'frasi': [
    frase('Dal finestrino della macchina si vedevano gli alberi coperti di neve.', {'gli': 'articolo'}, '«Dal» e «della» sono preposizioni articolate, non articoli.'),
    frase('Appena scesa, Luce ha visto la neve per la prima volta.', {'la': 'articolo', 'la#2': 'articolo'}),
    frase('Abbiamo bevuto della cioccolata calda e mangiato dei biscotti.', {'della': 'articolo', 'dei': 'articolo'}, 'Qui «della» e «dei» sono partitivi: un po\' di cioccolata, alcuni biscotti.')]}, 2)
es('CFC-51', 36, "Verifica: l'articolo giusto", "Scegli l'articolo giusto.", 'scelta', {'domande': [
    S('… scoglio', ['il', 'lo'], 'lo'), S('… orologio', ['un', "un'"], 'un'), S('… psicologi', ['gli', 'i'], 'gli'), S('… amaca', ['la', "l'"], "l'"),
    S('… zoo', ['uno', 'un'], 'uno'), S('… altalena', ['un', "un'"], "un'"), S('… orsi', ['i', 'gli'], 'gli'), S('… nuovo stadio', ['il', 'lo'], 'il', 'Conta «nuovo»: il nuovo stadio.')]})

json.dump({'versione': 1, 'esercizi': E}, open(os.path.join(os.path.dirname(__file__), 'esercizi.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print(len(E), 'esercizi → esercizi.json')
