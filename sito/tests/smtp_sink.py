#!/usr/bin/env python3
"""Server SMTP finto per i test: accetta tutto (con AUTH PLAIN/LOGIN) e salva ogni email in una cartella.
Uso: python3 smtp_sink.py PORTA CARTELLA   (scrive 1.eml, 2.eml, ...; il file PRONTO segnala l'avvio)"""
import os
import socketserver
import sys

porta, cartella = int(sys.argv[1]), sys.argv[2]
os.makedirs(cartella, exist_ok=True)
contatore = [0]


class Gestore(socketserver.StreamRequestHandler):
    def invia(self, s):
        self.wfile.write((s + "\r\n").encode())

    def handle(self):
        self.invia("220 sink ESMTP")
        dati = False
        corpo = []
        mittente = destinatari = None
        destinatari = []
        while True:
            riga = self.rfile.readline()
            if not riga:
                return
            t = riga.decode("utf-8", "replace").rstrip("\r\n")
            if dati:
                if t == ".":
                    contatore[0] += 1
                    n = contatore[0]
                    with open(os.path.join(cartella, f"{n}.eml"), "w", encoding="utf-8") as f:
                        f.write(f"X-Sink-From: {mittente}\nX-Sink-To: {','.join(destinatari)}\n" + "\n".join(corpo) + "\n")
                    dati = False
                    corpo = []
                    self.invia("250 ok")
                else:
                    corpo.append(t[1:] if t.startswith("..") else t)
                continue
            c = t.upper()
            if c.startswith("EHLO") or c.startswith("HELO"):
                self.wfile.write(b"250-sink\r\n250-AUTH PLAIN LOGIN\r\n250 8BITMIME\r\n")
            elif c.startswith("AUTH PLAIN"):
                self.invia("235 ok")
            elif c.startswith("AUTH LOGIN"):
                self.invia("334 VXNlcm5hbWU6")
                self.rfile.readline()
                self.invia("334 UGFzc3dvcmQ6")
                self.rfile.readline()
                self.invia("235 ok")
            elif c.startswith("MAIL FROM"):
                mittente = t[10:].strip()
                destinatari = []
                self.invia("250 ok")
            elif c.startswith("RCPT TO"):
                destinatari.append(t[8:].strip().strip("<>"))
                self.invia("250 ok")
            elif c == "DATA":
                dati = True
                self.invia("354 vai")
            elif c == "QUIT":
                self.invia("221 ciao")
                return
            else:
                self.invia("250 ok")


class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


with Server(("127.0.0.1", porta), Gestore) as s:
    open(os.path.join(cartella, "PRONTO"), "w").close()
    s.serve_forever()
