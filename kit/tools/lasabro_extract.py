#!/usr/bin/env python3
"""lasabro-extract: turn a module of the Enterprise API into a LASABRO plugin, mechanically and verifiably.

  python lasabro_extract.py --root <monorepo> --plan <plan.json> [--api editions/enterprise/api] [--dry-run] [--allow-dirty]

What it does (everything is driven by the plan file, see plans/*.plan.json):
  1. refuses to start unless the working tree is clean and every reference to project code already resolves (kit/tools/refcheck.php);
  2. moves the module's classes, route files, migrations, seeders, translations and tests into plugins/<slug>/ with `git mv`,
     keeping history, and rewrites each moved class's namespace;
  3. rewrites every reference to a moved class across the API, the plugins and the tests (imports, fully qualified names, class names in strings);
  4. converts the old service provider into a plugin provider, mounts the route files, writes composer.json (manifest), resources/*.php, README;
  5. repeats refcheck and applies the missing `use` lines it reports, until nothing is unresolved;
  6. proves the routes were not touched: the statements of every moved route file are compared (imports aside) before and after.
Nothing is executed from the application: it needs only Python 3.9, git and PHP (for refcheck and `php -l`).
"""
import argparse
import fnmatch
import hashlib
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path

PHP_DIRS = ['app', 'routes', 'database', 'tests', 'config', 'bootstrap']
HISTORY_COMMENT = re.compile(
    r"(//\s*|/\*\*\s*|^\s*\*\s+)COPS Phase \d+[a-z]?(?: \([^)]*\))?(?: [a-z]+(?=:))?:?\s*(\w)", re.M)


def die(msg):
    print(f"\nERROR: {msg}\n(If files were already moved, undo with:  git reset --hard && git clean -fd plugins baseline)")
    sys.exit(1)


def run(cmd, cwd=None, check=True):
    r = subprocess.run(cmd, cwd=cwd, text=True, capture_output=True)
    if check and r.returncode != 0:
        die(f"{' '.join(map(str, cmd))}\n{r.stdout}\n{r.stderr}")
    return r


class Tool:
    def __init__(self, a):
        self.root = Path(a.root).resolve()
        self.api = self.root / a.api
        self.plan = json.loads(Path(a.plan).read_text(encoding='utf-8'))
        self.dry = a.dry_run
        self.slug = self.plan['slug']
        self.ns = f"Lasabro\\{self.plan['namespace']}"
        self.pdir = self.root / 'plugins' / self.slug
        self.refcheck = self.root / 'kit/tools/refcheck.php'
        self.moves = {}      # old FQCN -> new FQCN
        self.old_ns = {}     # new path (str) -> old namespace
        self.moved_files = []  # absolute paths of moved/created php files
        self.route_files = []
        self.git = (self.root / '.git').exists()
        self.log = []
        self.touched = set()

    # ------------------------------------------------------------------ helpers
    def say(self, msg):
        print(msg)

    def php_files(self):
        out = []
        for d in PHP_DIRS:
            out += [p for p in (self.api / d).rglob('*.php')] if (self.api / d).is_dir() else []
        plugins = self.root / 'plugins'
        if plugins.is_dir():
            out += [p for p in plugins.rglob('*.php') if 'vendor' not in p.parts and 'node_modules' not in p.parts]
        return out

    def refcheck_run(self):
        cmd = ['php', str(self.refcheck), str(self.api), '--json']
        for extra in (self.root / 'plugins', self.root / 'kit/framework/src'):
            if extra.is_dir():
                cmd.append(f'--extra={extra}')
        r = run(cmd, check=False)
        if r.returncode not in (0, 1):
            die(f"refcheck failed to run:\n{r.stdout}\n{r.stderr}")
        return json.loads(r.stdout)

    def move(self, src: Path, dst: Path):
        if self.dry:
            return
        dst.parent.mkdir(parents=True, exist_ok=True)
        if self.git:
            r = run(['git', 'mv', str(src), str(dst)], cwd=self.root, check=False)
            if r.returncode == 0:
                return
        shutil.move(str(src), str(dst))

    @staticmethod
    def declared(text):
        ns = re.search(r'^namespace\s+([^;]+);', text, re.M)
        cls = re.search(r'^(?:abstract\s+|final\s+)*(?:class|interface|trait|enum)\s+(\w+)', text, re.M)
        return (ns.group(1) if ns else ''), (cls.group(1) if cls else None)

    # ------------------------------------------------------------------ steps
    def preflight(self):
        if not self.api.is_dir():
            die(f"{self.api} not found")
        if self.pdir.exists() and any(p.is_file() for p in self.pdir.rglob('*')):
            die(f"{self.pdir} already holds files; was this plugin extracted before?")
        if self.pdir.exists():
            shutil.rmtree(self.pdir)   # an empty leftover of an interrupted run
        if self.git and not self.args.allow_dirty:
            dirty = run(['git', 'status', '--porcelain'], cwd=self.root).stdout.strip()
            if dirty:
                die("The working tree has uncommitted changes. Commit (or stash) them first, so the extraction is one reviewable commit "
                    "and `git reset --hard` can undo it. (--allow-dirty overrides.)")
        if run(['php', '-v'], check=False).returncode != 0:
            die("PHP is required (used by refcheck and php -l).")
        base = self.refcheck_run()
        if base['summary']['problems']:
            lines = '\n'.join(f"  {p['file']}:{p['line']} {p['name']} -> {p['resolved']}" for p in base['problems'][:15])
            die(f"{base['summary']['problems']} reference(s) to project code do not resolve BEFORE the extraction:\n{lines}\nFix them first.")
        self.baseline_classes = base['summary']['classes']
        self.say(f"baseline: {base['summary']['files']} files, {self.baseline_classes} classes, all references resolve")

    def plan_moves(self):
        plan = self.plan
        pending = []     # (src, dst, kind, old_ns_or_None)
        for g in plan.get('classes', []):
            src_dir = self.api / g['from']
            if not src_dir.is_dir():
                die(f"{src_dir} not found")
            files = sorted(f for f in src_dir.glob('*.php') if any(fnmatch.fnmatch(f.stem, pat) for pat in g['include'])
                           and not any(fnmatch.fnmatch(f.stem, pat) for pat in g.get('exclude', [])))
            if not files:
                die(f"no file in {g['from']} matches {g['include']}")
            for f in files:
                old_ns, cls = self.declared(f.read_text(encoding='utf-8'))
                if not cls:
                    die(f"{f} declares no class")
                new_ns = self.ns + ('\\' + g['ns'] if g.get('ns') else '')
                self.moves[f"{old_ns}\\{cls}"] = f"{new_ns}\\{cls}"
                dst = self.pdir / g['to'] / f.name
                pending.append((f, dst, 'class', old_ns, new_ns))
        # provider
        pv = plan.get('provider')
        if pv:
            f = self.api / pv['from']
            old_ns, cls = self.declared(f.read_text(encoding='utf-8'))
            self.moves[f"{old_ns}\\{cls}"] = f"{self.ns}\\{cls}"
            self.provider_class = cls
            pending.append((f, self.pdir / 'src' / f.name, 'provider', old_ns, self.ns))
        # route files, migrations, lang, tests (no namespace work except tests)
        for r in plan.get('routes', {}).get('files', []):
            f = self.api / r
            if not f.is_file():
                die(f"{f} not found")
            pending.append((f, self.pdir / 'routes' / f.name, 'route', None, None))
        for pat in plan.get('migrations', []):
            hits = sorted((self.api / 'database/migrations').glob(pat))
            if not hits:
                die(f"no migration matches {pat}")
            for f in hits:
                pending.append((f, self.pdir / 'database/migrations' / f.name, 'migration', None, None))
        for pat in plan.get('lang', []):
            for f in sorted((self.api / 'lang').glob(pat)):
                pending.append((f, self.pdir / 'resources/lang' / f.relative_to(self.api / 'lang'), 'lang', None, None))
        self.test_dirs = set()
        for t in plan.get('tests', []):
            f = self.api / t
            if not f.is_file():
                die(f"{f} not found")
            sub = f.relative_to(self.api / 'tests').parent          # Unit or Feature: the suite the test belongs to
            self.test_dirs.add(str(sub).replace('\\', '/'))
            pending.append((f, self.pdir / 'tests' / sub / f.name, 'test', None, None))
        self.pending = pending
        self.say(f"plan: {sum(1 for p in pending if p[2] in ('class', 'provider'))} classes, {len(self.plan.get('routes', {}).get('files', []))} route files, "
                 f"{sum(1 for p in pending if p[2] == 'migration')} migrations, {sum(1 for p in pending if p[2] == 'lang')} lang files, "
                 f"{sum(1 for p in pending if p[2] == 'test')} tests")

    def edit_host_first(self):
        """Remove host lines that load what is leaving (before the global rewrite would turn them into plugin references)."""
        for rel, patterns in self.plan.get('host_remove_lines', {}).items():
            f = self.api / rel
            text = f.read_text(encoding='utf-8')
            for pat in patterns:
                new, n = re.subn(rf"^[ \t]*{pat}[^\n]*\n", '', text, flags=re.M)
                if n == 0:
                    die(f"{rel}: nothing matched {pat!r}")
                text = new
            if not self.dry:
                f.write_text(text, encoding='utf-8')
            self.say(f"  host: {rel}: removed {len(patterns)} line(s)")

    def do_moves(self):
        for src, dst, kind, old_ns, new_ns in self.pending:
            text = src.read_text(encoding='utf-8') if kind in ('class', 'provider', 'route', 'test') else None
            self.move(src, dst)
            if self.dry:
                continue
            if kind in ('class', 'provider'):
                text = re.sub(r'^namespace\s+[^;]+;', lambda _m: f"namespace {new_ns};", text, count=1, flags=re.M)
                if kind == 'provider':
                    text = text.replace('use Illuminate\\Support\\ServiceProvider;', 'use Lasabro\\Framework\\PluginServiceProvider;')
                    text = re.sub(r'\bextends ServiceProvider\b', 'extends PluginServiceProvider', text)
                    text = re.sub(r'public function boot\(\)\s*:\s*void', 'protected function bootPlugin(): void', text)
                    text = re.sub(r'public function register\(\)\s*:\s*void', 'protected function registerPlugin(): void', text)
                dst.write_text(text, encoding='utf-8')
                self.old_ns[str(dst)] = old_ns
                self.moved_files.append(dst)
            elif kind == 'route':
                self.route_files.append(dst)
                self.moved_files.append(dst)
            elif kind == 'test':
                self.moved_files.append(dst)
        for g in self.plan.get('classes', []):
            d = self.api / g['from']
            if d.is_dir() and not any(d.iterdir()) and not self.dry:
                d.rmdir()

    def extract_lang_blocks(self):
        """Move named top-level blocks (`    'key' => [ ... ],`) out of shared translation groups into the plugin's file of the same name.
        Laravel merges the files of a group from every registered path, so __('notify.po_issued.title') keeps working."""
        for group, keys in self.plan.get('lang_blocks', {}).items():
            for locdir in sorted((self.api / 'lang').iterdir()):
                f = locdir / group
                if not (locdir.is_dir() and f.is_file()):
                    continue
                lines = f.read_text(encoding='utf-8').split('\n')
                taken, keep, i = [], [], 0
                while i < len(lines):
                    m = re.match(r"^    '([^']+)' => \[\s*$", lines[i])
                    if m and m.group(1) in keys:
                        j = i
                        while j < len(lines) and lines[j] != '    ],':
                            j += 1
                        taken += lines[i:j + 1]
                        i = j + 1
                        continue
                    keep.append(lines[i])
                    i += 1
                found = {re.match(r"^    '([^']+)'", l).group(1) for l in taken if re.match(r"^    '([^']+)' => \[", l)}
                if found != set(keys):
                    die(f"{f.relative_to(self.api)}: expected blocks {sorted(keys)}, found {sorted(found)}")
                if not self.dry:
                    f.write_text('\n'.join(keep), encoding='utf-8')
                    out = self.pdir / 'resources/lang' / locdir.name / group
                    out.parent.mkdir(parents=True, exist_ok=True)
                    out.write_text("<?php\n\nreturn [\n" + '\n'.join(taken) + "\n];\n", encoding='utf-8')
                    self.moved_files.append(out)
        if self.plan.get('lang_blocks'):
            self.say(f"  moved translation blocks {sorted({k for v in self.plan['lang_blocks'].values() for k in v})} out of the shared groups")

    def rewrite_references(self):
        if not self.moves or self.dry:
            return
        pairs = sorted(self.moves.items(), key=lambda kv: -len(kv[0]))
        pats = []
        for old, new in pairs:
            for o, n in ((old, new), (old.replace('\\', '\\\\'), new.replace('\\', '\\\\'))):
                pats.append((re.compile(r'(?<![A-Za-z0-9_])' + re.escape(o) + r'(?![A-Za-z0-9_])'), n))
        changed = 0
        for f in self.php_files():
            text = f.read_text(encoding='utf-8')
            new = text
            for rx, n in pats:
                new = rx.sub(lambda m, n=n: n, new)
            if new != text:
                f.write_text(new, encoding='utf-8')
                self.touched.add(f)
                changed += 1
        self.say(f"  rewrote references to {len(pairs)} moved classes in {changed} file(s)")

    def retarget_routes(self):
        controllers_ns = f"{self.ns}\\Http\\Controllers"
        moved_controllers = {n.split('\\')[-1] for o, n in self.moves.items() if n.startswith(controllers_ns + '\\')}
        for f in self.route_files:
            text = f.read_text(encoding='utf-8')
            m = re.search(r'^use (App\\Http\\Controllers\\Api\\V1)(?: as V1)?;\s*$', text, re.M)
            if not m:
                continue
            used = set(re.findall(r'\bV1\\(\w+)::class', text))
            if used and used <= moved_controllers:
                text = text.replace(m.group(0), f"use {controllers_ns} as V1;", 1)
            elif used & moved_controllers:
                text = re.sub(r'\bV1\\(' + '|'.join(sorted(used & moved_controllers)) + r')::class', r'Pc\\\1::class', text)
                text = text.replace(m.group(0), m.group(0) + f"\nuse {controllers_ns} as Pc;", 1)
            text = re.sub(r"Required (?:once )?from routes/api\.php.*?(?=\s*\*/|$)", 'Loaded by this plugin\'s routes/api.php inside the authenticated "org.context" group.', text, flags=re.M)
            f.write_text(text, encoding='utf-8')

    def clean_comments(self):
        removed = 0
        for f in self.moved_files:
            if f.suffix != '.php':
                continue
            text = f.read_text(encoding='utf-8')
            new = HISTORY_COMMENT.sub(lambda m: m.group(1) + m.group(2).upper(), text)
            removed += len(HISTORY_COMMENT.findall(text))
            if new != text:
                f.write_text(new, encoding='utf-8')
        self.say(f"  removed {removed} bundle-history comment prefix(es)")
        left = []
        for f in self.moved_files:
            if f.suffix == '.php':
                for i, line in enumerate(f.read_text(encoding='utf-8').split('\n'), 1):
                    if re.search(r'\b(COPS Phase|Phase \d+[a-z]?\b|Bundle \d+)', line):
                        left.append(f"{f.relative_to(self.root)}:{i}: {line.strip()[:110]}")
        if left:
            self.say(f"  {len(left)} comment line(s) still mention a phase/bundle; review by hand:")
            for l in left[:12]:
                self.say("    " + l)

    def generate(self):
        p, plan = self.pdir, self.plan
        ns, cls = self.ns, getattr(self, 'provider_class', None)
        manifest = {
            'id': plan['id'], 'title': plan['title'], 'tier': plan.get('tier', 'domain'),
            'provider': f"{ns}\\{cls}",
            'requires': plan.get('requires', {}), 'suggests': plan.get('suggests', {}),
            'frontends': plan.get('frontends', {}), 'declares': plan.get('declares', []),
        }
        composer = {
            'name': f"lasabro/{self.slug}", 'description': plan['description'], 'type': 'lasabro-plugin', 'license': 'MIT',
            'authors': [{'name': 'Robert MAKUTA', 'email': 'dabani.rm@gmail.com', 'homepage': 'https://dabani.github.io'}],
            'require': {'php': '^8.3', 'lasabro/framework': '^1.0'},
            'autoload': {'psr-4': {f"{ns}\\": 'src/', f"{ns}\\Database\\Seeders\\": 'database/seeders/'}},
            'extra': {'branch-alias': {'dev-main': '1.x-dev'}, 'lasabro': manifest},
        }
        (p / 'composer.json').write_text(json.dumps(composer, indent=4, ensure_ascii=False).replace('\\/', '/') + '\n', encoding='utf-8')
        (p / 'resources').mkdir(parents=True, exist_ok=True)
        if 'seeders' in plan.get('declares', []):
            order = self.seeder_order()
            uses = '\n'.join(f"use {fq};" for fq in order)
            body = ', '.join(f"{fq.split(chr(92))[-1]}::class" for fq in order)
            (p / 'resources/seeders.php').write_text(f"<?php\n\n{uses}\n\n/** Seeders run once, in this order, when the plugin is first activated (skipped with --adopt). */\nreturn [{body}];\n", encoding='utf-8')
        if 'menu' in plan.get('declares', []):
            items = ', '.join(f"'{m}'" for m in plan.get('menu', []))
            (p / 'resources/menu.php').write_text(f"<?php\n\n/** Menu items owned by this plugin (a trailing * matches a prefix). Hidden while the plugin is inactive. */\nreturn [{items}];\n", encoding='utf-8')
        if self.route_files:
            reqs = '\n'.join(f"require __DIR__ . '/{f.name}';" for f in self.route_files)
            (p / 'routes/api.php').write_text(
                "<?php\n\n/**\n * Routes of this plugin. The framework mounts this file inside the authenticated, organization-scoped group\n"
                " * (prefix and middleware come from config/lasabro.php), exactly where the application's routes/api.php used to include them.\n */\n" + reqs + "\n",
                encoding='utf-8')
        (p / 'README.md').write_text(self.readme(manifest), encoding='utf-8')
        (p / 'CHANGELOG.md').write_text(f"# Changelog\n\n## 1.0.0\n\n- Extracted from LASABRO Enterprise (formerly COPS) without functional change.\n", encoding='utf-8')

    def seeder_order(self):
        """Seeders in the order DatabaseSeeder runs them (fallback: plan order)."""
        names = [n for o, n in self.moves.items() if n.startswith(f"{self.ns}\\Database\\Seeders\\")]
        ds = (self.api / 'database/seeders/DatabaseSeeder.php').read_text(encoding='utf-8')
        pos = {n: ds.find(n.split('\\')[-1] + '::class') for n in names}
        return sorted(names, key=lambda n: (pos[n] if pos[n] >= 0 else 10 ** 9, n))

    def readme(self, m):
        req = ', '.join(f"**{k}** `{v}`" for k, v in m['requires'].items()) or 'nothing'
        need = ', '.join(m['requires']) or 'no other plugin'
        sug = ''.join(f"- **{k}**: {v}\n" for k, v in m['suggests'].items())
        return (f"# {m['title']} ({m['id']})\n\n{self.plan['description']}\n\n"
                f"> **Dependency notice:** {m['id']} requires {req}. It cannot be activated unless {need} are installed and active.\n\n"
                + (f"## Works better with\n\n{sug}\n" if sug else '')
                + "## Install\n\n```\ncomposer require lasabro/" + self.slug + "\nphp artisan migrate\nphp artisan lasabro:plugin:activate " + m['id']
                + "\n```\n\nUpgrading an installation that already holds this module's data (it predates the plugin): "
                  f"`php artisan lasabro:plugin:activate {m['id']} --adopt` records the seeders instead of running them again.\n\n"
                  "Deactivating hides the menu and unloads the routes; data are kept. `php artisan lasabro:plugin:uninstall "
                + m['id'] + " --purge` is the only command that deletes data.\n\n"
                  "## Layout\n\n`src/` models, controllers, services, provider - `routes/` - `database/migrations` and `database/seeders` - "
                  "`resources/lang` and declarations - `tests/`.\n\nAuthor: Robert MAKUTA - dabani.rm@gmail.com - https://dabani.github.io\n")

    def edit_host_composer(self):
        comp = self.api / 'composer.json'
        text = comp.read_text(encoding='utf-8')
        pkg = f"lasabro/{self.slug}"
        if f'"{pkg}"' not in text:
            text, n = re.subn(r'(\n(\s*)"lasabro/framework": "@dev",)', rf'\1\n\2"{pkg}": "@dev",', text, count=1)
            if n != 1:
                die("composer.json: lasabro/framework line not found (apply bundle 02 first).")
        if '../../../plugins/*' not in text:
            text, n = re.subn(r'(\{ "type": "path", "url": "../../../kit/framework", "options": \{ "symlink": true \} \})',
                              r'\1,\n        { "type": "path", "url": "../../../plugins/*", "options": { "symlink": true } }', text, count=1)
            if n != 1:
                die("composer.json: kit/framework path repository not found (apply bundle 02 first).")
        json.loads(text)
        comp.write_text(text, encoding='utf-8')
        px = self.api / 'phpunit.xml'
        t = px.read_text(encoding='utf-8')
        for sub in sorted(self.test_dirs):
            entry = f"<directory>../../../plugins/{self.slug}/tests/{sub}</directory>"
            anchor = f"<directory>tests/{sub}</directory>"
            if entry not in t and anchor in t:
                t = t.replace(anchor, f"{anchor}\n            {entry}", 1)
        px.write_text(t, encoding='utf-8')

    def fix_imports(self):
        """Apply the `use` lines refcheck asks for, until nothing is unresolved."""
        for round_ in range(1, 5):
            res = self.refcheck_run()
            probs = res['problems']
            if not probs:
                self.say(f"  refcheck round {round_}: everything resolves")
                return res
            index = {c.lower(): c for c in res['summary'].get('index', [])}
            old_to_new = self.moves
            changed = 0
            unfixable = []
            per_file = {}
            for pr in probs:
                if pr['kind'] != 'code' or '\\' in pr['name']:
                    unfixable.append(pr)
                    continue
                file = Path(pr['file']) if Path(pr['file']).is_absolute() else self.api / pr['file']
                short = pr['name']
                resolved = pr['resolved']                      # current ns + short
                ns_here = resolved.rsplit('\\', 1)[0]
                old_ns = self.old_ns.get(str(file), ns_here)    # moved files remember where they came from
                target_old = f"{old_ns}\\{short}"
                if target_old in old_to_new:
                    target = old_to_new[target_old]
                elif target_old.lower() in index:
                    target = index[target_old.lower()]
                else:
                    unfixable.append(pr)
                    continue
                per_file.setdefault(file, set()).add(target)
            for file, targets in per_file.items():
                text = file.read_text(encoding='utf-8')
                for t in sorted(targets):
                    text = self.add_use(text, t)
                file.write_text(text, encoding='utf-8')
                self.touched.add(file)
                changed += len(targets)
            self.say(f"  refcheck round {round_}: {len(probs)} unresolved, added {changed} import(s) in {len(per_file)} file(s)")
            if unfixable and not changed:
                lines = '\n'.join(f"  {p['file']}:{p['line']} {p['name']} -> {p['resolved']} ({p['kind']})" for p in unfixable[:20])
                die(f"{len(unfixable)} reference(s) could not be fixed automatically:\n{lines}")
        die("references still unresolved after 4 rounds")

    @staticmethod
    def add_use(text, fqcn):
        line = f"use {fqcn};"
        if re.search(rf"^use {re.escape(fqcn)}(\s+as\s+\w+)?;", text, re.M):
            return text
        lines = text.split('\n')
        idx = [i for i, l in enumerate(lines) if re.match(r'^use [^(]+;$', l) and not l.startswith('use function') and not l.startswith('use const')]
        class_at = next((i for i, l in enumerate(lines) if re.match(r'^(?:abstract\s+|final\s+)*(?:class|interface|trait|enum)\s', l)), len(lines))
        idx = [i for i in idx if i < class_at]
        if idx:
            pos = next((i for i in idx if lines[i] > line), idx[-1] + 1)
            lines.insert(pos, line)
        else:
            ns = next((i for i, l in enumerate(lines) if l.startswith('namespace ')), 0)
            lines[ns + 1:ns + 1] = ['', line]
        return '\n'.join(lines)

    @staticmethod
    def sort_use_blocks(text):
        """Alphabetise each run of consecutive `use Foo\\Bar;` lines (what Pint's ordered_imports expects)."""
        lines = text.split('\n')
        out, i = [], 0
        simple = re.compile(r'^use ([^;{(]+?)(?:\s+as\s+\w+)?;$')
        while i < len(lines):
            if simple.match(lines[i]) and not re.match(r'^use (function|const)\b', lines[i]):
                j = i
                while j < len(lines) and simple.match(lines[j]) and not re.match(r'^use (function|const)\b', lines[j]):
                    j += 1
                block = lines[i:j]
                block.sort(key=lambda l: simple.match(l).group(1).lower().replace('\\', ' '))
                out += block
                i = j
            else:
                out.append(lines[i])
                i += 1
        return '\n'.join(out)

    def sort_imports(self):
        files = set(self.touched) | {f for f in self.moved_files if f.suffix == '.php'}
        n = 0
        for f in files:
            text = f.read_text(encoding='utf-8')
            new = self.sort_use_blocks(text)
            if new != text:
                f.write_text(new, encoding='utf-8')
                n += 1
        self.say(f"  ordered the imports of {n} file(s)")

    def route_digest(self, text):
        t = re.sub(r'/\*.*?\*/', '', text, flags=re.S)
        t = re.sub(r'//[^\n]*', '', t)
        t = re.sub(r'^\s*(use|namespace)\b[^\n]*\n', '', t, flags=re.M)
        t = re.sub(r'\b(V1|Pc)\\', 'C\\\\', t)
        return hashlib.sha1(re.sub(r'\s+', '', t).encode()).hexdigest()

    def verify(self, before_digests):
        bad = []
        for f in self.route_files:
            d = self.route_digest(f.read_text(encoding='utf-8'))
            if before_digests.get(f.name) != d:
                bad.append(f.name)
        if bad:
            die(f"route statements changed in: {bad}")
        self.say(f"  route statements identical (imports aside) in {len(self.route_files)} file(s)")
        failed = 0
        for f in self.php_files():
            r = run(['php', '-l', str(f)], check=False)
            if r.returncode != 0:
                failed += 1
                self.say(f"  SYNTAX ERROR {f}: {r.stdout.strip()}")
        if failed:
            die(f"{failed} file(s) with syntax errors")
        final = self.refcheck_run()
        if final['summary']['problems']:
            die("references unresolved after extraction")
        if final['summary']['classes'] != self.baseline_classes:
            die(f"class count changed: {self.baseline_classes} -> {final['summary']['classes']}")
        self.say(f"  php -l clean; {final['summary']['classes']} classes (unchanged); all references resolve")

    def notice_text(self, plugin_id, requires):
        req = ', '.join(f"**{k}** `{v}`" for k, v in requires.items()) or 'nothing'
        need = ', '.join(requires) or 'no other plugin'
        return f"> **Dependency notice:** {plugin_id} requires {req}. It cannot be activated unless {need} are installed and active."

    def update_dependents(self):
        """Plugins extracted earlier that now use classes of this plugin must say so in their manifest (and README notice)."""
        prefix = f"Lasabro\\{self.plan['namespace']}\\"
        for comp in sorted((self.root / 'plugins').glob('*/composer.json')):
            if comp.parent == self.pdir:
                continue
            if not any(prefix in f.read_text(encoding='utf-8') for f in comp.parent.rglob('*.php')):
                continue
            data = json.loads(comp.read_text(encoding='utf-8'))
            meta = data['extra']['lasabro']
            if self.plan['id'] in meta['requires']:
                continue
            meta['requires'][self.plan['id']] = '^1.0'
            comp.write_text(json.dumps(data, indent=4, ensure_ascii=False).replace('\\/', '/') + '\n', encoding='utf-8')
            readme = comp.parent / 'README.md'
            if readme.exists():
                text = re.sub(r'^> \*\*Dependency notice:\*\*.*$', lambda _m: self.notice_text(meta['id'], meta['requires']), readme.read_text(encoding='utf-8'), count=1, flags=re.M)
                readme.write_text(text, encoding='utf-8')
            self.say(f"  {meta['id']} now requires {self.plan['id']} (its code uses this plugin's classes)")

    def host_coupling(self):
        """Host files (outside the plugin) that now name a class of the plugin: they need a contract before the plugin can be switched off."""
        out = {}
        pat = re.compile(r'\bLasabro\\' + re.escape(self.plan['namespace']) + r'\\[A-Za-z\\]+')
        for f in self.php_files():
            if (self.root / 'plugins') in f.parents:
                continue                                   # other plugins are dependents, recorded in their manifests
            text = f.read_text(encoding='utf-8')
            hits = sorted(set(pat.findall(text)))
            if hits:
                out[str(f.relative_to(self.api))] = hits
        non_seed = {k: v for k, v in out.items() if not k.startswith(('database/seeders/', 'tests/'))}
        if non_seed:
            self.say("  host code that names this plugin's classes (needs a contract before the plugin can be deactivated safely):")
            for k, v in sorted(non_seed.items()):
                self.say(f"    {k}: {', '.join(x.split(chr(92))[-1] for x in v[:5])}")
        else:
            self.say("  no host code outside seeders and tests names this plugin's classes: it can be deactivated cleanly")
        return out

    def external_report(self):
        groups = {}
        for f in self.moved_files:
            if f.suffix != '.php':
                continue
            for m in re.finditer(r'^use (App\\[^;]+);', f.read_text(encoding='utf-8'), re.M):
                fq = m.group(1)
                ns = '\\'.join(fq.split('\\')[:-1])
                groups.setdefault(ns, set()).add(fq.split('\\')[-1])
        self.say("  the plugin still depends on host (future Lasabro.Core / Approvals / Rules / Files) code:")
        for ns in sorted(groups):
            self.say(f"    {ns}: {', '.join(sorted(groups[ns])[:8])}{' ...' if len(groups[ns]) > 8 else ''}")
        return {ns: sorted(v) for ns, v in groups.items()}

    # ------------------------------------------------------------------ main
    def run(self, args):
        self.args = args
        self.preflight()
        self.plan_moves()
        if self.dry:
            self.say("dry run: nothing changed")
            return
        before = {Path(r).name: self.route_digest((self.api / r).read_text(encoding='utf-8')) for r in self.plan.get('routes', {}).get('files', [])}
        self.say("1/6 host lines"); self.edit_host_first()
        self.say("2/6 moving files"); self.do_moves(); self.extract_lang_blocks()
        self.say("3/6 rewriting references"); self.rewrite_references(); self.retarget_routes()
        self.say("4/6 cleaning comments"); self.clean_comments()
        self.say("5/6 generating plugin files"); self.generate(); self.edit_host_composer()
        self.say("6/6 resolving imports and verifying"); self.fix_imports(); self.sort_imports(); self.verify(before)
        self.update_dependents()
        deps = self.external_report()
        coupling = self.host_coupling()
        record = {'plugin': self.plan['id'], 'moved': {o: n for o, n in sorted(self.moves.items())}, 'host_dependencies': deps, 'host_code_naming_plugin': coupling}
        out = self.root / 'baseline/extractions'
        out.mkdir(parents=True, exist_ok=True)
        (out / f"{self.slug}.json").write_text(json.dumps(record, indent=2) + '\n', encoding='utf-8')
        self.say(f"\nDone. Plugin: {self.pdir.relative_to(self.root)}   record: baseline/extractions/{self.slug}.json")


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--root', required=True)
    ap.add_argument('--plan', required=True)
    ap.add_argument('--api', default='editions/enterprise/api')
    ap.add_argument('--dry-run', action='store_true')
    ap.add_argument('--allow-dirty', action='store_true')
    a = ap.parse_args()
    try:
        Tool(a).run(a)
    except SystemExit:
        raise
    except Exception:
        print("\nThe extraction stopped unexpectedly. Undo it with:  git reset --hard && git clean -fd plugins baseline", file=sys.stderr)
        raise


if __name__ == '__main__':
    main()
