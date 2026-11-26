#!/usr/bin/env python3
"""Draft extraction plans from a few anchors per module (route files, service folder, provider, seeders, commands) and the class reference graph.

  python analyze_modules.py --root <monorepo> [--modules modules.json] [--write] [--only a,b]

Controllers come from the module's route files; classes in Models, Resources, Exports, Imports, Jobs, Events, Mail, Observers, Rules and Casts
follow the module when only that module uses them (repeated until nothing changes). Anything also used by the host or another module is left
where it is and listed, so you can decide. Capabilities (Approvals, Rules, Files, Hr, Clients) and other modules it uses become `requires`.
It prints a summary; with --write it writes plans/<slug>.plan.json for lasabro_extract.py.
"""
import argparse
import json
import re
import subprocess
import sys
from pathlib import Path

MOVABLE = {  # directory -> (plan destination, namespace suffix)
    'app/Models': ('src/Models', 'Models'),
    'app/Http/Controllers/Api/V1': ('src/Http/Controllers', 'Http\\Controllers'),
    'app/Http/Resources/Api/V1': ('src/Http/Resources', 'Http\\Resources'),
    'app/Exports': ('src/Exports', 'Exports'),
    'app/Imports': ('src/Imports', 'Imports'),
    'app/Jobs': ('src/Jobs', 'Jobs'),
    'app/Events': ('src/Events', 'Events'),
    'app/Mail': ('src/Mail', 'Mail'),
    'app/Observers': ('src/Observers', 'Observers'),
    'app/Rules': ('src/Rules', 'Rules'),
    'app/Casts': ('src/Casts', 'Casts'),
}
CAPABILITIES = [  # (capability, regex on the FQCN of a host class)
    ('Lasabro.Approvals', r'^App\\Services\\Approvals\\|^App\\Models\\Approval'),
    ('Lasabro.Rules', r'^App\\Services\\Rules\\|^App\\Models\\(Rule[A-Z]|Legal|PolicyRule)'),
    ('Lasabro.Files', r'^App\\Models\\File[A-Z]|^App\\Services\\(Files?\\|FileService|ClamAv)'),
    ('Lasabro.Hr', r'^App\\Models\\(Employee|Position|Grade|Employment)|^App\\Services\\Hr\\'),
    ('Lasabro.Clients', r'^App\\Models\\Client'),
]


def rel(p, root):
    return str(Path(p).relative_to(root)).replace('\\', '/')


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--root', required=True)
    ap.add_argument('--modules', default=None)
    ap.add_argument('--write', action='store_true')
    ap.add_argument('--only', default='')
    ap.add_argument('--api', default='editions/enterprise/api')
    a = ap.parse_args()
    root = Path(a.root).resolve()
    api = root / a.api
    tools = root / 'kit/tools'
    modules = json.loads(Path(a.modules or tools / 'modules.json').read_text(encoding='utf-8'))
    only = [x for x in a.only.split(',') if x]

    r = subprocess.run(['php', str(tools / 'refcheck.php'), str(api), '--graph', f'--extra={root / "plugins"}', f'--extra={root / "kit/framework/src"}'], text=True, capture_output=True)
    if r.returncode not in (0, 1):
        sys.exit(r.stderr)
    graph = json.loads(r.stdout)['graph']
    decl = {}          # FQCN -> file
    for f, g in graph.items():
        for c in g['declares']:
            decl[c] = f
    users = {}         # FQCN -> set(files using it)
    for f, g in graph.items():
        for c in g['uses']:
            if decl.get(c) != f:
                users.setdefault(c, set()).add(f)

    owner = {}         # file -> module slug
    seed_files = {}
    for slug, m in modules.items():
        files = set()
        for rf in m['routes']:
            text = (api / rf).read_text(encoding='utf-8')
            for ctl in set(re.findall(r'\bV1\\(\w+)::class', text)):
                p = f"app/Http/Controllers/Api/V1/{ctl}.php"
                if p in graph:
                    files.add(p)
        for d in m['service_dirs']:
            files |= {f for f in graph if f.startswith(d + '/')}
        files.add(f"app/Providers/{m['provider']}.php")
        files |= {f"database/seeders/{s}.php" for s in m['seeders']}
        files |= {f"app/Console/Commands/{c}.php" for c in m['commands']}
        for f in files:
            if f not in graph:
                sys.exit(f"{slug}: anchor {f} not found")
            if f in owner and owner[f] != slug:
                sys.exit(f"{f} is claimed by both {owner[f]} and {slug}")
            owner[f] = slug
        seed_files[slug] = files

    movable_files = {f for f in graph for d in MOVABLE if f.startswith(d + '/') and f.count('/') == d.count('/') + 1}
    order = list(modules)
    seeds = set().union(*seed_files.values())
    for slug, m in modules.items():
        seeds |= {rf for rf in m['routes'] if rf in graph}          # a module's route files move with it
    cand = movable_files - seeds

    def in_plugin(path):
        return '/plugins/' in path.replace('\\', '/') or path.startswith('plugins/')

    plugin_ids = {}
    for comp in sorted((root / 'plugins').glob('*/composer.json')):
        meta = json.loads(comp.read_text(encoding='utf-8')).get('extra', {}).get('lasabro', {})
        if meta.get('id'):
            plugin_ids[comp.parent.name] = meta['id']

    # 1. A candidate that any file outside the modules (host code, config, host routes) uses stays in the host ...
    pinned, why = set(), {}
    for f in cand:
        for c in graph[f]['declares']:
            hosts = sorted(u for u in users.get(c, ()) if not u.startswith('tests/') and u not in seeds and u not in cand and not in_plugin(u))
            if hosts:
                pinned.add(f)
                why[f] = hosts
    # 2. ... and so does everything a pinned class uses (it now behaves like host code).
    changed = True
    while changed:
        changed = False
        for f in list(pinned):
            for c in graph[f]['uses']:
                t = decl.get(c)
                if t in cand and t not in pinned:
                    pinned.add(t)
                    why[t] = [f'(used by {Path(f).stem}, which stays)']
                    changed = True
    # 3. The rest goes to the first module (in dependency order) that reaches it from its own files.
    free = cand - pinned
    blocked = {}
    for slug in order:
        stack, seen = list(seed_files[slug] | {rf for rf in modules[slug]['routes'] if rf in graph}), set()
        while stack:
            f = stack.pop()
            for c in graph[f]['uses']:
                t = decl.get(c)
                if t in free and t not in seen:
                    seen.add(t)
                    stack.append(t)
        for t in seen:
            if t not in owner:
                owner[t] = slug
    for f in pinned:
        blocked[f] = ['host']

    out = {}
    for slug, m in modules.items():
        if only and slug not in only:
            continue
        files = sorted(f for f, o in owner.items() if o == slug)
        classes = []
        for d, (to, ns) in MOVABLE.items():
            stems = sorted(Path(f).stem for f in files if f.startswith(d + '/') and f.count('/') == d.count('/') + 1)
            if stems:
                classes.append({'from': d, 'include': stems, 'to': to, 'ns': ns})
        for sd in m['service_dirs']:
            classes.append({'from': sd, 'include': ['*'], 'to': 'src/Services/' + Path(sd).name if len(m['service_dirs']) > 1 else 'src/Services', 'ns': 'Services\\' + Path(sd).name if len(m['service_dirs']) > 1 else 'Services'})
        if m['commands']:
            classes.append({'from': 'app/Console/Commands', 'include': m['commands'], 'to': 'src/Console', 'ns': 'Console'})
        classes.append({'from': 'database/seeders', 'include': m['seeders'], 'to': 'database/seeders', 'ns': 'Database\\Seeders'})

        # capabilities and other modules used by the module's files
        needs, other = set(), set()
        for f in files:
            for c in graph[f]['uses']:
                tf = decl.get(c)
                if tf is None or owner.get(tf) == slug:
                    continue
                if in_plugin(tf):
                    m_ = re.search(r'plugins/([^/]+)/', tf.replace('\\', '/'))
                    if m_ and m_.group(1) in plugin_ids:
                        needs.add(plugin_ids[m_.group(1)])
                    continue
                if owner.get(tf):
                    other.add(owner[tf])
                    continue
                for cap, rx in CAPABILITIES:
                    if re.search(rx, c):
                        needs.add(cap)
        requires = {'Lasabro.Core': '^1.0'}
        requires.update({c: '^1.0' for c in sorted(needs)})
        requires.update({modules[o]['id']: '^1.0' for o in sorted(other)})

        # menu codes created by the module's seeders
        codes = set()
        for s in m['seeders']:
            text = (api / f"database/seeders/{s}.php").read_text(encoding='utf-8')
            codes |= set(re.findall(r"MenuItem::updateOrCreate\(\['code' => '([a-z_.]+)'", text))
            codes |= set(re.findall(r"\['([a-z_]+(?:\.[a-z_]+)*)',\s*'Icon[A-Za-z0-9]+'", text))
            codes |= set(re.findall(r"\['([a-z_]+(?:\.[a-z_]+)*)',\s*(?:null|'[a-z_.]+'),\s*'Icon[A-Za-z0-9]+'", text))
        plan = {
            'id': m['id'], 'slug': slug if slug != 'time' else 'time-tracking', 'namespace': m['namespace'], 'title': m['title'], 'tier': 'domain',
            'description': m['description'], 'requires': requires, 'suggests': {}, 'frontends': {},
            'declares': ['seeders', 'menu'], 'menu': sorted(codes), 'classes': classes,
            'provider': {'from': f"app/Providers/{m['provider']}.php"},
            'routes': {'files': m['routes']},
            'migrations': [], 'lang': [f"*/{x}.php" for x in m['lang']], 'lang_blocks': m.get('lang_blocks', {}), 'tests': m['tests'],
            'host_remove_lines': {
                'routes/api.php': [rf"require __DIR__ \. '/{re.escape(Path(x).name)}';" for x in m['routes']],
                'bootstrap/providers.php': [rf"App\\Providers\\{m['provider']}::class,"],
            },
        }
        mig = []
        for pat in m['migrations']:
            hits = sorted((api / 'database/migrations').glob(f"*_{pat}.php"))
            if not hits:
                sys.exit(f"{slug}: no migration *_{pat}.php")
            mig += [h.name for h in hits]
        plan['migrations'] = mig
        out[slug] = (plan, files)

        counts = {d.split('/')[-1] if 'Resources' not in d else 'Resources': len([1 for c in classes if c['from'] == d for _ in c['include']]) for d in MOVABLE if any(c['from'] == d for c in classes)}
        print(f"\n== {m['id']}  ({len(files)} files; requires {', '.join(k.replace('Lasabro.', '') for k in requires)})")
        print("   " + ', '.join(f"{k}:{v}" for k, v in counts.items()) + f"; services:{sum(1 for f in files if f.startswith('app/Services/'))}; menu codes:{len(codes)}")
        if a.write:
            (tools / 'plans').mkdir(exist_ok=True)
            (tools / 'plans' / f"{plan['slug']}.plan.json").write_text(json.dumps(plan, indent=2) + '\n', encoding='utf-8')

    mods_using = {}
    for f in pinned:
        for c in graph[f]['declares']:
            for u in users.get(c, ()):
                if owner.get(u) in modules:
                    mods_using.setdefault(f, set()).add(owner[u])
    shared = {f: sorted(v) for f, v in mods_using.items()}
    if shared:
        print("\nUsed by a module but kept in the host because host code uses them too (platform classes; decide by hand if any should move):")
        for f, who in sorted(shared.items()):
            print(f"   {f}: used by {', '.join(who)}   <- kept by: {', '.join(Path(x).stem if not x.startswith('(') else x for x in why.get(f, [])[:3])}")


if __name__ == '__main__':
    main()
