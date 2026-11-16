"""Giochi della Gazzettona: cruciverba (parole incrociate) e sudoku, generati e verificati."""
import random


def cruciverba(parole, lato=13, seme=1, tentativi=400):
    """parole: lista di (PAROLA, definizione). Ritorna griglia, posizioni numerate, parole messe."""
    migliore = None
    for t in range(tentativi):
        rnd = random.Random(seme * 1000 + t)
        ordine = sorted(parole, key=lambda p: -len(p[0]))
        ordine = ordine[:1] + rnd.sample(ordine[1:], len(ordine) - 1)
        g = [[None] * lato for _ in range(lato)]
        messe = []

        def puo(w, r, c, d):
            dr, dc = (0, 1) if d == 'O' else (1, 0)
            er, ec = r + dr * (len(w) - 1), c + dc * (len(w) - 1)
            if not (0 <= r < lato and 0 <= c < lato and er < lato and ec < lato):
                return -1
            pr, pc = r - dr, c - dc
            if 0 <= pr < lato and 0 <= pc < lato and g[pr][pc]: return -1
            nr, nc = er + dr, ec + dc
            if nr < lato and nc < lato and g[nr][nc]: return -1
            incroci = 0
            for i, ch in enumerate(w):
                rr, cc = r + dr * i, c + dc * i
                if g[rr][cc]:
                    if g[rr][cc] != ch: return -1
                    incroci += 1
                else:
                    for s in (-1, 1):
                        ar, ac = rr + dc * s, cc + dr * s
                        if 0 <= ar < lato and 0 <= ac < lato and g[ar][ac]: return -1
            return incroci

        def metti(w, r, c, d):
            dr, dc = (0, 1) if d == 'O' else (1, 0)
            for i, ch in enumerate(w):
                g[r + dr * i][c + dc * i] = ch

        w0, d0 = ordine[0]
        r0, c0 = lato // 2, (lato - len(w0)) // 2
        metti(w0, r0, c0, 'O'); messe.append((w0, d0, r0, c0, 'O'))
        for w, de in ordine[1:]:
            opz = []
            for r in range(lato):
                for c in range(lato):
                    for d in 'OV':
                        k = puo(w, r, c, d)
                        if k > 0: opz.append((k, rnd.random(), r, c, d))
            if opz:
                _, _, r, c, d = max(opz)
                metti(w, r, c, d); messe.append((w, de, r, c, d))
        if not migliore or len(messe) > len(migliore[1]):
            migliore = ([row[:] for row in g], messe)
        if len(messe) == len(parole): break
    g, messe = migliore
    # numerazione
    inizi = sorted({(r, c) for _, _, r, c, _ in messe})
    num = {p: i + 1 for i, p in enumerate(inizi)}
    oriz = sorted([(num[(r, c)], de, w) for w, de, r, c, d in messe if d == 'O'])
    vert = sorted([(num[(r, c)], de, w) for w, de, r, c, d in messe if d == 'V'])
    # verifica: ogni cella piena appartiene a una parola messa e coincide
    for w, _, r, c, d in messe:
        dr, dc = (0, 1) if d == 'O' else (1, 0)
        assert ''.join(g[r + dr * i][c + dc * i] for i in range(len(w))) == w
    return g, num, oriz, vert, [p[0] for p in parole if p[0] not in {m[0] for m in messe}]


def _ok(b, i, v):
    r, c = divmod(i, 9)
    for k in range(9):
        if b[r * 9 + k] == v or b[k * 9 + c] == v: return False
    br, bc = r // 3 * 3, c // 3 * 3
    return all(b[(br + a) * 9 + bc + e] != v for a in range(3) for e in range(3))


def _conta(b, lim=2):
    try:
        i = b.index(0)
    except ValueError:
        return 1
    n = 0
    for v in range(1, 10):
        if _ok(b, i, v):
            b[i] = v; n += _conta(b, lim); b[i] = 0
            if n >= lim: break
    return n


def sudoku(seme=7, indizi=30):
    rnd = random.Random(seme)
    b = [0] * 81

    def riempi(i=0):
        if i == 81: return True
        vs = list(range(1, 10)); rnd.shuffle(vs)
        for v in vs:
            if _ok(b, i, v):
                b[i] = v
                if riempi(i + 1): return True
        b[i] = 0
        return False
    riempi()
    sol = b[:]
    celle = list(range(81)); rnd.shuffle(celle)
    puz = sol[:]
    for i in celle:
        if sum(1 for x in puz if x) <= indizi: break
        t = puz[i]; puz[i] = 0
        if _conta(puz[:]) != 1: puz[i] = t
    assert _conta(puz[:]) == 1
    return puz, sol
