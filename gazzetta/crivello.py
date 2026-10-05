"""Crea una griglia "cerca le parole" (crivello) come tabella HTML. Uso: da build o a mano."""
import random


def crivello(parole, lato=11, alfabeto="ABCDEFGHILMNOPQRSTUVZ", seme=1):
    rnd = random.Random(seme)
    g = [[None] * lato for _ in range(lato)]
    dirs = [(0, 1), (1, 0), (1, 1), (-1, 1)]
    for p in sorted(parole, key=len, reverse=True):
        for _ in range(500):
            dr, dc = rnd.choice(dirs)
            r, c = rnd.randrange(lato), rnd.randrange(lato)
            celle = [(r + dr * i, c + dc * i) for i in range(len(p))]
            if all(0 <= a < lato and 0 <= b < lato and g[a][b] in (None, p[i]) for i, (a, b) in enumerate(celle)):
                for i, (a, b) in enumerate(celle):
                    g[a][b] = p[i]
                break
        else:
            raise SystemExit("non entra: " + p)
    righe = "".join("<tr>" + "".join("<td>%s</td>" % (x or rnd.choice(alfabeto)) for x in riga) + "</tr>" for riga in g)
    return '<table class="crivello">' + righe + "</table>"


if __name__ == "__main__":
    import sys
    print(crivello(sys.argv[2:], alfabeto=sys.argv[1]))
