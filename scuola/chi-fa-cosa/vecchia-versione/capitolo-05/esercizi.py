#!/usr/bin/env python3
"""Versioni interattive degli esercizi del capitolo 5 (stessi codici del PDF) → esercizi.json.
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

es('CFC-70', 48, 'Trova i pronomi personali', 'Scegli «pronome» e tocca i pronomi personali.', 'tocca', {'categorie': cat('pronome'), 'frasi': [
    frase('Io vado al mare, tu vieni con me?', {'Io': 'pronome', 'tu': 'pronome', 'me': 'pronome'}),
    frase('Noi studiamo, loro giocano in cortile.', {'Noi': 'pronome', 'loro': 'pronome'}),
    frase('Luce ha visto la vicina e la saluta.', {'la#2': 'pronome'}, 'Il primo «la» è un articolo (la vicina); il secondo sta al posto di «la vicina»: è un pronome.')]})
GL = ['gli', 'le']
es('CFC-71', 48, 'Gli o le?', 'Scegli la forma giusta.', 'scelta', {'domande': [
    S('Ho visto Marco e … ho dato il libro.', GL, 'gli', 'a lui = gli'), S('Telefono alla nonna e … dico ciao.', GL, 'le', 'a lei = le'),
    S('Luce ha fame: … do le crocchette.', GL, 'le', 'Luce è femmina: a lei = le.'), S('Il prof è arrivato: … consegno il compito.', GL, 'gli')]})
es('CFC-72', 51, 'I pronomi deboli', 'Scrivi il pronome debole al posto delle parole tra parentesi.', 'completa', {'frasi': [
    {'testo': 'Vlad chiama (Luce). → Vlad [la] chiama.'}, {'testo': 'Mangio (i biscotti). → [Li] mangio.'},
    {'testo': 'Scrivo (a Marco). → [Gli] scrivo.'}, {'testo': 'Porto il gelato (a Giulia). → [Le] porto il gelato.'},
    {'testo': 'Guardo (le stelle). → [Le] guardo.', 'spiegazione': '«le» qui è complemento oggetto (le stelle), non «a lei».'}]})
AP = ['aggettivo', 'pronome']
es('CFC-73', 51, 'Aggettivo o pronome?', 'La parola tra asterischi è un aggettivo o un pronome?', 'scelta', {'domande': [
    S('*Quello* zaino è mio.', AP, 'aggettivo'), S('*Quello* è mio.', AP, 'pronome'), S('*Alcuni* sono stanchi.', AP, 'pronome'),
    S('*Alcuni* ragazzi sono stanchi.', AP, 'aggettivo'), S('*Quale* preferisci?', AP, 'pronome'), S('La *tua* idea è ottima.', AP, 'aggettivo')]})
es('CFC-74', 51, 'Che tipo di pronome?', 'Trascina ogni pronome nel gruppo giusto.', 'gruppi', {'gruppi': [
    {'nome': 'personale', 'elementi': ['noi', 'te']}, {'nome': 'possessivo', 'elementi': ['il loro', 'il nostro']},
    {'nome': 'dimostrativo', 'elementi': ['ciò', 'questo']}, {'nome': 'indefinito', 'elementi': ['qualcuno', 'niente']},
    {'nome': 'interrogativo', 'elementi': ['chi', 'che cosa']}]}, 2)
es('CFC-75', 51, 'Vero o falso', 'Decidi se ogni frase è vera o falsa.', 'vf', {'domande': [
    {'testo': '«Gli» significa «a lei».', 'vero': False, 'spiegazione': '«gli» = a lui (o a loro); «le» = a lei.'},
    {'testo': '«Ciò» è sempre un pronome.', 'vero': True},
    {'testo': 'Il pronome soggetto si deve scrivere sempre.', 'vero': False, 'spiegazione': 'Spesso si sottintende: la desinenza del verbo dice già chi fa l\'azione.'},
    {'testo': 'In «Vieni con me», «me» è una forma forte.', 'vero': True}]}, 2)
es('CFC-76', 51, 'Racconta con i pronomi', 'Racconta in tre frasi che cosa hai fatto ieri con un amico, usando almeno tre pronomi diversi.', 'aperta', {'domande': [
    {'testo': 'Il tuo racconto:', 'esempio': "Ieri ho incontrato Marco e gli ho prestato un libro. Lui mi ha detto che questo è il suo preferito. Poi qualcuno ci ha chiamati per giocare."}]}, 3)
es('CFC-77', 53, 'Che o cui?', 'Completa con «che» oppure con preposizione + «cui».', 'completa', {'frasi': [
    {'testo': 'Ho letto il libro [che] mi hai prestato.'},
    {'testo': 'Questa è la spiaggia [in cui|a cui] vado ogni estate.'},
    {'testo': "Saluto l'amica [con cui] esco."},
    {'testo': 'Luce ha un osso [che] è enorme.'}]})
RC = ['pronome relativo', 'congiunzione']
es('CFC-78', 53, '«Che» pronome o congiunzione?', 'Prova a sostituire con «il quale / la quale».', 'scelta', {'domande': [
    S('Il gatto *che* ruba è grigio.', RC, 'pronome relativo', '= il gatto il quale ruba.'), S('Penso *che* pioverà.', RC, 'congiunzione', 'Non si può dire «penso il quale».'),
    S('La torta *che* hai fatto è ottima.', RC, 'pronome relativo'), S('Spero *che* tu venga.', RC, 'congiunzione')]}, 2)
es('CFC-79', 53, 'Il quale', 'Scrivi la forma giusta di «il quale» (con la preposizione se serve).', 'completa', {'frasi': [
    {'testo': 'le amiche che ho invitato → le amiche [le quali] ho invitato'},
    {'testo': 'il paese in cui sono nato → il paese [nel quale] sono nato', 'spiegazione': 'in + il quale = nel quale.'},
    {'testo': 'i ragazzi con cui gioco → i ragazzi [con i quali|coi quali] gioco'}]}, 2)
es('CFC-80', 53, "L'analisi del pronome", "«Chi ha visto Luce? Io la cerco, ma nessuno mi risponde»: completa l'analisi.", 'completa', {'banca': True, 'frasi': [
    {'testo': 'Chi → pronome [interrogativo]'}, {'testo': 'Io → pronome [personale], 1ª persona singolare, soggetto'},
    {'testo': 'la → pronome [personale], 3ª persona singolare, femminile'}, {'testo': 'nessuno → pronome [indefinito]'},
    {'testo': 'mi → pronome [personale], 1ª persona singolare, forma debole'}]}, 3)
es('CFC-81', 53, 'Rimetti in ordine', 'Trascina le parole per formare la frase.', 'ordina', {'frasi': [
    {'testo': 'Il gatto che ha preso il panino è grigio.'}, {'testo': 'Vlad le regala un biscotto.'}]})
es('CFC-82', 53, 'Basta ripetere!', 'Scrivi il pronome al posto del nome ripetuto.', 'completa', {'frasi': [
    {'testo': 'Ho un gatto. [Lo] porto dal veterinario.'}, {'testo': 'Vedo la nonna e [le] porto i fiori.'},
    {'testo': 'I miei amici arrivano: [li] aspetto alla stazione.'}, {'testo': 'Marco ha fame: [gli] preparo un panino.'},
    {'testo': 'Ho comprato delle pesche e [le] ho mangiate.'}]}, 2, 1)
es('CFC-83', 54, 'Il segreto della nonna: hai capito?', 'Vero o falso?', 'vf', {'domande': [
    {'testo': 'La nonna tiene la ricetta in un quaderno.', 'vero': True},
    {'testo': 'La nonna dice subito il segreto a Vlad.', 'vero': False, 'spiegazione': '«Te lo dico quando sarai grande».'},
    {'testo': 'Manca un cannolo.', 'vero': True}, {'testo': 'Alla fine la nonna si arrabbia.', 'vero': False, 'spiegazione': 'Ride tanto!'}]})
es('CFC-84', 54, 'I pronomi del racconto', 'Tocca i pronomi.', 'tocca', {'categorie': cat('pronome'), 'frasi': [
    frase("Lei mi ha dato la ricotta e io l'ho mescolata con lo zucchero.", {'Lei': 'pronome', 'mi': 'pronome', 'io': 'pronome'}, "Anche in «l'ho» c'è un pronome: l' = la ricotta."),
    frase('Nessuno conosce la sua ricetta.', {'Nessuno': 'pronome'}, '«sua» qui è un aggettivo possessivo: accompagna «ricetta».'),
    frase('Lei ha sorriso: «Te lo dico quando sarai grande».', {'Lei': 'pronome', 'Te': 'pronome', 'lo': 'pronome'})]}, 2)
T = ['personale', 'possessivo', 'dimostrativo', 'indefinito', 'interrogativo', 'relativo']
es('CFC-86', 56, 'Verifica: che tipo di pronome?', 'Scegli il tipo del pronome.', 'scelta', {'domande': [
    S('voi', T, 'personale'), S('cui', T, 'relativo'), S('ciò', T, 'dimostrativo'), S('chiunque', T, 'indefinito'),
    S('il vostro', T, 'possessivo'), S('che cosa?', T, 'interrogativo'), S('ti', T, 'personale'), S('quello (da solo)', T, 'dimostrativo')]})

json.dump({'versione': 1, 'esercizi': E}, open(os.path.join(os.path.dirname(__file__), 'esercizi.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
print(len(E), 'esercizi → esercizi.json')
