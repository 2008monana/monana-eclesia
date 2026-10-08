#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Migracao do projecto para arquitectura MVC.

Para cada modules/<m>/<file>.php:
  - o "prologo" PHP (antes do primeiro `?>` que fecha a parte de logica)
    passa a ser o corpo do metodo <action>() de controllers/<M>Controller.php;
  - o resto (HTML) passa para views/<m>/<file>.php;
  - includes de config/session/database sao substituidos pelo bootstrap;
  - includes/header|sidebar|topbar|footer viram View::partial('partials/...').
"""
import os, re

ROOT = '/workspace'
MODS = sorted(d for d in os.listdir(os.path.join(ROOT, 'modules'))
              if os.path.isdir(os.path.join(ROOT, 'modules', d)))

MODULO_MAP = {'year-end': 'year_end'}

def controller_name(mod):
    parts = mod.split('-')
    return ''.join(p[:1].upper() + p[1:] for p in parts) + 'Controller'

def model_name(mod):
    parts = mod.split('-')
    return ''.join(p[:1].upper() + p[1:] for p in parts) + 'Model'

def action_name(fname):
    base = fname[:-4] if fname.endswith('.php') else fname
    if '-' not in base:
        return base
    parts = base.split('-')
    return parts[0] + ''.join(p[:1].upper() + p[1:] for p in parts[1:])

def valid_php_name(s):
    return re.fullmatch(r'[a-zA-Z_][a-zA-Z0-9_]*', s) is not None

REQUIRES_DROP = [
    re.compile(r"require_once\s*\(?\s*'\.\./\.\./config/(database|url|session)\.php';"),
    re.compile(r'require_once\s+__DIR__ \. .*\.\./config/database\.php.;'),
    re.compile(r"require_once\s+'\.\./\.\./modules/audit/functions\.php';"),
    re.compile(r"require_once\s+'\.\./audit/functions\.php';"),
    re.compile(r"require_once\s+'__DIR__ \./\.\./audit/functions\.php';".replace('__DIR__', '__DIR__')),
    re.compile(r"require_once\s*\(?'?\.\./reports/functions\.php'?\)?;"),
    re.compile(r"require_once\s*\(?'\.\./\.\./modules/reports/functions\.php';"),
    re.compile(r"require_once\s+'functions\.php';"),
    re.compile(r"require_once\s*\(?'vendor/autoload\.php';"),
    re.compile(r"require_once\s*\(?.*vendor/autoload\.php.*\)?;"),
    re.compile(r"if \(!file_exists\('\.\./\.\./vendor/autoload\.php'\)\) \{"),
    re.compile(r"die\('Instale o DOMPDF.*"),
]

PARTIAL_RE = re.compile(
    r"(require_once|include_once|include|require)\s*\(?\s*'(\.\./\.\./|\.\./)?includes/([a-z\-]+)\.php'\s*\)?;")

LOGIN_GUARD_RE = re.compile(r"header\((['\"])/ipfva-gestao/modules/auth/login\.php\1\);")

ALTQUOTE_RE = re.compile(r'<<<\s*[\'"]?(\w+)[\'"]?\s*$')

def find_prologue_end(lines):
    """indice da linha com o `?>` que fecha o prologo logico (seguido de HTML).
    Ignora `?>` e marcadores dentro de strings/heredocs PHP."""
    state = {'sq': False, 'dq': False, 'block': None}
    for i, ln in enumerate(lines):
        s = ln.strip()
        if state['block'] is not None:
            if s == state['block']:
                state['block'] = None
            continue
        m = ALTQUOTE_RE.search(ln)
        if m and (state['sq'] or state['dq']):
            # closing quote of a nowdoc/heredoc opened inside string? unlikely; skip
            pass
        if m and not state['sq'] and not state['dq']:
            state['block'] = m.group(1)
            continue
        if s == '?>' and not state['sq'] and not state['dq']:
            nxt = ''
            for j in range(i + 1, min(i + 6, len(lines))):
                nxt += lines[j]
                if lines[j].strip():
                    break
            t = nxt.lstrip()
            if t.startswith('<!DOCTYPE') or t.startswith('<html') or t.startswith('<main') \
               or t.startswith('<div') or t.startswith('<aside') or t.startswith('<?php') \
               or t.startswith('<table') or t.startswith('<section') or t.startswith('<body') \
               or t.startswith('<link') or t.startswith('<meta') or t.startswith('<style') \
               or t.startswith('<header') or t.startswith('<nav') or t.startswith('<!--'):
                return i
        # actualizar estado de aspas simples multi-linha
        if state['sq']:
            if r"';" in s or s.endswith("'") or s.endswith("';"):
                state['sq'] = False
        elif state['dq']:
            if s.endswith('";') or s.endswith('";') or s.endswith('"'):
                state['dq'] = False
        else:
            mm = re.match(r"^\s*\$[\w\[\]'\"]+\s*\.?=\s*(?:\. ?)?'(?!.*').*$", ln)
            if mm:
                state['sq'] = True
            md = re.match(r'^\s*\$[\w\[\]"\.]+\s*\.?=\s*(?:\. ?)?"(?!.*").*$', ln)
            if md:
                state['dq'] = True
    return None

moved, problems = [], []

for mod in MODS:
    mdir = os.path.join(ROOT, 'modules', mod)
    ctrl_class = controller_name(mod)
    view_dir = os.path.join(ROOT, 'views', mod)
    os.makedirs(view_dir, exist_ok=True)

    methods = []          # (action, fname, body_lines)
    shared = None         # linhas comuns a todos os prologos (imports/helpers)

    files = sorted(f for f in os.listdir(mdir) if f.endswith('.php'))
    parsed = {}
    for fname in files:
        text = open(os.path.join(mdir, fname), encoding='utf-8').read()
        lines = text.split('\n')
        end = find_prologue_end(lines)
        pure_php = end is None
        if pure_php:
            end = len(lines)
        prologue = lines[:end]
        html = lines[end:]
        if html and html[0].strip() == '?>':
            html = html[1:]
        while html and html[0].strip() == '':
            html.pop(0)
        parsed[fname] = (prologue, html, pure_php)

    # interseccao de prefixo comum entre todos os prologos => bloco "shared"
    def norm(l):
        return l.strip()
    minlen = min(len(parsed[f][0]) for f in files)
    k = 0
    first_vals = [norm(l) for l in parsed[files[0]][0]]
    while k < minlen and all(norm(parsed[f][0][k]) == first_vals[k] for f in files):
        k += 1
    shared_lines = parsed[files[0]][0][:k]

    # filtrar shared: remover guards de sessao/login e requires descartaveis
    shared_out, skip_next_exit = [], False
    i = 0
    while i < len(shared_lines):
        s = shared_lines[i].strip()
        if s == 'session_start();':
            i += 1; continue
        if s.startswith('if (!isset($_SESSION') or s.startswith('if (!isset($_SESSION'):
            # bloco guard: if (...) { header(...login...); exit; }
            j = i
            depth = 0
            while j < len(shared_lines):
                depth += shared_lines[j].count('{') - shared_lines[j].count('}')
                if depth <= 0 and '{' in ''.join(shared_lines[i:j+1]):
                    break
                j += 1
            i = j + 1
            continue
        if any(r.match(s) for r in REQUIRES_DROP):
            i += 1; continue
        if LOGIN_GUARD_RE.search(s) or s == 'exit;':
            i += 1; continue
        shared_out.append(shared_lines[i])
        i += 1
    shared_out = [l.replace('getConnection()', 'db()') for l in shared_out]

    for fname in files:
        prologue, html, pure_php = parsed[fname]
        action = action_name(fname)
        if not valid_php_name(action):
            problems.append((mod, fname, 'invalid action name')); continue

        body = []
        in_guard = False
        i = len(shared_lines)  # comeca depois da parte comum
        lines_p = prologue
        while i < len(lines_p):
            raw = lines_p[i]
            s = raw.strip()
            if s == 'session_start();':
                i += 1; continue
            if s.startswith('if (!isset($_SESSION'):
                in_guard = True
                depth = 0
                start = i
                while i < len(lines_p):
                    depth += lines_p[i].count('{') - lines_p[i].count('}')
                    if depth <= 0:
                        break
                    i += 1
                gtxt = '\n'.join(lines_p[start:i+1])
                if LOGIN_GUARD_RE.search(gtxt):
                    in_guard = False
                    i += 1
                    continue
                else:
                    body.extend(lines_p[start:i+1]); i += 1; continue
            if any(r.match(s) for r in REQUIRES_DROP):
                i += 1; continue
            m = PARTIAL_RE.search(raw)
            if m:
                indent = raw[:len(raw) - len(raw.lstrip())]
                body.append(f"{indent}View::partial('partials/{m.group(3)}');")
                i += 1; continue
            if 'vendor/autoload.php' in s or s.startswith("die('Instale o DOMPDF"):
                i += 1; continue
            if LOGIN_GUARD_RE.search(s):
                i += 1; continue
            body.append(raw.replace('getConnection()', 'db()'))
            i += 1

        # escrever vista
        with open(os.path.join(view_dir, fname), 'w', encoding='utf-8') as fh:
            fh.write('\n'.join(html).rstrip('\n') + '\n')
        methods.append((action, fname, body))
        moved.append(f'{mod}/{fname}')

    # ---------- gerar controlador ----------
    uses = set()
    for _, _, body in methods:
        for b in body:
            for u in re.findall(r'^\s*use\s+[\w\\]+;', b):
                uses.add(u.strip().rstrip(';').replace('use ', '').strip())

    modulo_val = MODULO_MAP.get(mod, mod)
    out = ['<?php',
           f'// controllers/{ctrl_class}.php',
           f'// Controlador do modulo "{mod}" - arquitectura MVC.',
           '// Logica original dos antigos modules/{0}/*.php preservada,'.format(mod),
           '// agora separada em Model (dados) / Controller (fluxo) / View (HTML).',
           '',
           f"require_once BASE_PATH . '/models/{model_name(mod)}.php';",
           f"require_once BASE_PATH . '/models/AuditModel.php';",
           ]
    for u in sorted(uses):
        out.append(f'use {u};')
    if uses:
        out.append('')
    out += ['class ' + ctrl_class + ' extends Controller', '{',
            f"    protected string $modulo = '{modulo_val}';", '']

    for action, fname, body in methods:
        while body and body[0].strip() == '':
            body.pop(0)
        out.append(f'    /** Antes: modules/{mod}/{fname} */')
        out.append(f'    public function {action}(): void')
        out.append('    {')
        for b in body:
            out.append(('    ' + b) if b.strip() else '')
        out.append('    }')
        out.append('')
    out.append('}')
    with open(os.path.join(ROOT, 'controllers', ctrl_class + '.php'), 'w', encoding='utf-8') as fh:
        fh.write('\n'.join(out) + '\n')

print('moved:', len(moved))
for p in problems:
    print('PROBLEM:', p)
