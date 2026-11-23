#!/usr/bin/env python3
"""Crea la griglia HTML di un cruciverba (vuota o con soluzioni) e controlla che le parole si incrocino bene.
Uso: python3 cruciverba.py capitolo-01  → legge capitolo-01/cruciverba.json, scrive capitolo-01/cruciverba.html (+ -soluzioni)"""
import json, sys, os
cap = sys.argv[1]; d = json.load(open(os.path.join(cap, "cruciverba.json")))
g = {}
for w in d["parole"]:
    r, c = w["r"], w["c"]
    for i, ch in enumerate(w["parola"]):
        pos = (r + i, c) if w["dir"] == "giu" else (r, c + i)
        if g.get(pos, ch) != ch: sys.exit(f"incrocio sbagliato in {pos}: {g[pos]} / {ch} ({w['parola']})")
        g[pos] = ch
R = max(p[0] for p in g) + 1; C = max(p[1] for p in g) + 1
num = {(w["r"], w["c"]): w["n"] for w in d["parole"]}
def html(sol):
    t = ['<table class="cruciverba">']
    for r in range(R):
        t.append("<tr>" + "".join(
            (f'<td class="c">{f"<sup>{num[(r,c)]}</sup>" if (r,c) in num else ""}{g[(r,c)] if sol else ""}</td>' if (r, c) in g else '<td></td>')
            for c in range(C)) + "</tr>")
    return "\n".join(t + ["</table>"])
open(os.path.join(cap, "cruciverba.html"), "w").write(html(False))
open(os.path.join(cap, "cruciverba-soluzioni.html"), "w").write(html(True))
print(f"griglia {R}×{C}, {len(d['parole'])} parole: ok")
