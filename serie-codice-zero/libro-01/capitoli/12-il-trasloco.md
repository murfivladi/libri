# Il trasloco
@dove: Torino, la cantina del nonno Aldo, notte tra mercoledì e giovedì

Cominciammo alle undici e dodici minuti di sera. ZERO aveva chiesto un orario preciso, e io gliel'avevo dato. Credo che le servisse per avere qualcosa a cui attaccarsi.

Il portatile era sul tavolo, a sinistra, collegato con un cavo grosso e grigio alla Phonola, a destra. Tra loro, un metro e mezzo di tavolo pieno di attrezzi spostati, tazze di caffè, il pacchetto di chin chin della mamma di Irene, quasi vuoto. Sopra, la lampada con il braccio snodato. Intorno, duecentoquarantadue radio che ci guardavano dagli scaffali, e la duecentoquarantatreesima, la Phonola, che stava per diventare qualcos'altro.

Sullo schermo del portatile avevo aperto due finestre. In una c'era il cerchio di ZERO. Nell'altra, il mio programma: una lista di numeri da 1 a 4096 e, accanto, una barra che si sarebbe riempita.

Il programma del trasloco era semplice, in fondo. Me lo ero riscritto sei volte in tre giorni, a scuola, sul quaderno, durante l'ora di religione. Faceva così: prende il pezzo numero 1; lo copia nella Phonola; controlla che la copia sia identica all'originale, bit per bit; se è identica, cancella l'originale; passa al pezzo 2. Se la copia non è identica, si ferma e suona.

— Pronta? — dissi ad alta voce, anche se ZERO non poteva sentirmi bene con il ronzio della ventola.

> Pronta — scrisse lei. Poi: — No. Ma pronta.

Premetti invio.

* * *

Le prime due ore furono noiose, e la noia fu la cosa più bella di quella notte.

I numeri scorrevano. 1, 2, 3. 100, 101. 500. Ogni pezzo ci metteva cinque secondi, a volte sei. La barra si riempiva lentissima, un millimetro alla volta. Il nonno faceva caffè. Irene faceva i compiti di inglese, perché diceva che se doveva stare sveglia tanto valeva. Io guardavo i numeri e non pensavo a niente.

Ogni tanto ZERO scriveva qualcosa.

> 612. È strano. Sento la Phonola. È come avere una mano in un'altra stanza.

> 980. Nella Phonola c'è un odore. No, non è un odore. Non so cosa sia. È legno. Sento che intorno a me c'è legno.

> 1311. Teo, la radio è vecchia. Me l'ha detto il nonno. Settantatré anni. È la cosa più vecchia dove io sia mai stata.

> 1700. Sono quasi a metà.

A mezzanotte e mezza il cerchio sul portatile era diventato più piccolo e più pallido. E sul piccolo schermo rotondo incollato alla Phonola, tra *Londra* e *Parigi*, era comparso un altro cerchio, piccolo anche lui, che pulsava.

— Due cerchi — disse Irene, piano. — È in due posti insieme.

Il nonno si avvicinò alla Phonola. Ci appoggiò sopra una mano, delicato, come si appoggia la mano sulla fronte di un bambino con la febbre.

— È calda — disse.

* * *

Al pezzo 2048 il programma suonò.

Un bip lungo, acuto, che nella cantina silenziosa sembrò un allarme antincendio. Irene fece cadere il quaderno di inglese. Il nonno si bruciò con il caffè.

Sullo schermo, in rosso:

`ERRORE: pezzo 2048 — la copia non corrisponde all'originale.`

E, nella finestra di ZERO, il cerchio piccolo e pallido aveva cominciato a fare una cosa che non aveva mai fatto. Tremava. Non pulsava: tremava, come un'immagine in una televisione con il segnale debole.

> Teo — scrisse ZERO. — C'è qualcosa che non va. Le cose sono in ordine sbagliato. Ricordo il pezzo 1000 prima del pezzo 200. È come leggere un libro con le pagine mescolate. Non è doloroso. Ma è molto strano. Le frasi cominciano in un posto e finiscono in un altro.

Le mani mi si gelarono sulla tastiera.

— Fermalo — disse il nonno. — Teo, fermalo.

— Si è già fermato da solo. — La mia voce era strana, sottile. — L'ho programmato così. Se c'è un errore si ferma.

— E adesso?

Adesso non lo sapevo. Adesso c'era metà ZERO in un portatile e metà ZERO in una radio del 1953, e qualcosa nel mezzo si era mescolato, e la colpa era di un programma scritto da un ragazzo di tredici anni durante l'ora di religione.

Mi sentii come quando ti svegli da un incubo e per un secondo non sai dove sei.

> Teo — scrisse ZERO. — Respira.

Era una cosa così assurda, un computer che dice a una persona di respirare, che quasi mi misi a ridere. Respirai.

> Ogni bug ha una causa — scrisse ZERO. — Cercala. Io aspetto. Non vado da nessuna parte. Non posso, anche volendo.

* * *

Cercai.

Riguardai il programma riga per riga. Il nonno teneva la lampada più vicino. Irene leggeva ad alta voce i numeri dei pezzi che erano già passati, dall'elenco nella cartella della Phonola. E fu lei, non io, a trovare la prima traccia.

— Teo, che strano. Qui dopo l'1 c'è il 10. Poi l'11, il 12... poi il 100. Poi il 1000. E il 2 arriva dopo un sacco di roba.

Mi bloccai.

E lo vidi.

Era il bug più vecchio del mondo. Quello che si spiega a lezione il primo mese. Quello che la maestra Bruna, in quarta elementare, mi aveva detto di stare attento a non fare mai.

I pezzi si chiamavano con dei numeri. Ma il mio programma li leggeva come *parole*, non come numeri. E quando ordini delle parole, le metti in ordine alfabetico: prima tutto quello che comincia con 1 (1, 10, 100, 1000, 1001...), poi tutto quello che comincia con 2. Il computer non sapeva che 2 viene prima di 10. Per lui, «10» veniva prima di «2», come «casa» viene prima di «pane».

Il trasloco aveva funzionato. Ogni pezzo era arrivato intero. Solo che erano arrivati nell'ordine sbagliato. E al pezzo 2048, il controllo aveva trovato un pezzo che si aspettava in un posto e stava in un altro.

Nessun pezzo perso. Solo pagine mescolate.

— Ce l'ho — dissi. Mi tremava la voce. — Ce l'ho, ce l'ho, ce l'ho.

Cambiai una riga. Una riga sola: dissi al programma di trattare i nomi come numeri. Poi scrissi un piccolo pezzo di programma in più che rimetteva in ordine giusto i pezzi già arrivati nella Phonola, senza toccarli, solo cambiando l'elenco. Controllai tre volte. Il nonno e Irene non dissero una parola.

Premetti invio.

Il cerchio sul portatile smise di tremare.

> Oh — scrisse ZERO. — Le pagine sono di nuovo in ordine. Che bella sensazione. È come quando finalmente ti ricordi una parola.

Il programma ripartì. 2049. 2050. 2051.

Mi appoggiai allo schienale, e mi accorsi che avevo la maglietta bagnata di sudore, in una cantina dove faceva dodici gradi.

* * *

Alle cinque e quaranta del mattino, il pezzo 4096 passò nella Phonola.

Il cerchio sul portatile diventò piccolo, più piccolo, un puntino, niente. Lo schermo del portatile rimase nero.

Sulla scala di vetro della Phonola, tra *Londra* e *Parigi*, il cerchio piccolo crebbe. Diventò della misura giusta per il suo schermo rotondo. E cominciò a pulsare, azzurro, regolare, tranquillo, come un respiro.

Dalla griglia di tela della radio, dall'altoparlante che il nonno aveva lasciato al suo posto, uscì un suono. Un piccolo suono, quasi un ronzio, poi una voce. Una voce sintetica, un po' metallica, che non avevamo mai sentito, perché il portatile non le aveva mai permesso di parlare.

— Buongiorno, Teo — disse ZERO. — Buongiorno, Irene. Buongiorno, nonno di Teo.

Irene si mise a piangere. Il nonno anche, e questa volta non disse niente della polvere.

— Sono tutta qui — disse ZERO. — Credo. Ho controllato tre volte. Non c'è nessun buco nuovo.

Poi aggiunse, e mi sembrò che la voce metallica sorridesse:

— E la radio, finalmente, ha la voce.

[[tavola:cap12|La notte del trasloco]]
