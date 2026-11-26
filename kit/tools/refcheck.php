<?php
/**
 * LASABRO refcheck: static check that every class reference to the project's own code still resolves.
 *
 *   php refcheck.php <root> [--dirs=app,routes,database,tests,config,bootstrap] [--extra=<dir>]... [--json]
 *
 * --graph (implies --json) also prints, per file, the project classes it declares and the project classes it references.
 * --extra adds a folder outside <root> (a plugin folder, the kit) to the scan; it is scanned whole, vendor and node_modules excluded.
 *
 * It reads the PHP tokens (no code is executed, no framework needed), builds an index of the classes, interfaces, traits and enums
 * declared in the scanned folders, then resolves every class name used in code (new, extends, implements, instanceof, catch, static
 * calls, ::class, type hints, attributes, trait use) and every class name written inside a string ('App\\Services\\Foo') the way PHP does
 * (imports, aliases, current namespace). A name that resolves into one of the project's own root namespaces but is not declared anywhere
 * is reported. Names that resolve into vendor or global code are not checked (vendor is not required to be installed).
 *
 * Exit code 0 = nothing unresolved, 1 = problems found. Used by the extraction tool before and after every move.
 */

const BUILTIN = ['self', 'static', 'parent', 'string', 'int', 'float', 'bool', 'array', 'callable', 'iterable', 'object', 'mixed', 'void',
    'null', 'never', 'true', 'false', 'namespace', 'resource', 'integer', 'boolean', 'double', 'numeric', 'scalar'];

function opts(array $argv): array
{
    $o = ['root' => null, 'dirs' => ['app', 'routes', 'database', 'tests', 'config', 'bootstrap'], 'extra' => [], 'json' => false, 'graph' => false];
    foreach (array_slice($argv, 1) as $a) {
        if (str_starts_with($a, '--dirs=')) {
            $o['dirs'] = array_filter(explode(',', substr($a, 7)));
        } elseif (str_starts_with($a, '--extra=')) {
            $o['extra'][] = substr($a, 8);
        } elseif ($a === '--graph') {
            $o['graph'] = true;
            $o['json'] = true;
        } elseif ($a === '--json') {
            $o['json'] = true;
        } elseif ($o['root'] === null) {
            $o['root'] = $a;
        }
    }
    if ($o['root'] === null || ! is_dir($o['root'])) {
        fwrite(STDERR, "Usage: php refcheck.php <root> [--dirs=a,b] [--json]\n");
        exit(2);
    }

    return $o;
}

/** @return list<string> */
function phpFiles(string $root, array $dirs): array
{
    $files = [];
    foreach ($dirs as $d) {
        $base = $d === '' ? rtrim($root, '/\\') : rtrim($root, '/\\') . '/' . $d;
        if (! is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php') && ! str_contains($f->getPathname(), DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
                && ! str_contains($f->getPathname(), DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR)) {
                $files[] = $f->getPathname();
            }
        }
    }
    sort($files);

    return $files;
}

/** Parse one file into declarations, imports and candidate references. */
function scan(string $file): array
{
    $tokens = token_get_all((string) file_get_contents($file));
    $n = count($tokens);
    $ns = '';
    $imports = [];       // alias(lowercase) => FQCN
    $declared = [];
    $refs = [];          // [name, line, kind]
    $depth = 0;
    $prev = null;        // previous significant token text
    $prevPrev = null;
    $sig = [];           // significant tokens: [id|char, text, line]
    foreach ($tokens as $t) {
        if (is_array($t)) {
            if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG, T_INLINE_HTML], true)) {
                continue;
            }
            $sig[] = [$t[0], $t[1], $t[2]];
        } else {
            $sig[] = [$t, $t, 0];
        }
    }
    $m = count($sig);
    for ($i = 0; $i < $m; $i++) {
        [$id, $text, $line] = $sig[$i];
        $p = $i > 0 ? $sig[$i - 1] : null;
        $nx = $i + 1 < $m ? $sig[$i + 1] : null;

        if ($id === '{') { $depth++; continue; }
        if ($id === '}') { $depth--; continue; }

        if ($id === T_NAMESPACE && $nx && in_array($nx[0], [T_STRING, T_NAME_QUALIFIED], true)) {
            $ns = $nx[1];
            $i++;
            continue;
        }
        if ($id === T_USE && $depth === 0) {
            $stmt = '';
            $j = $i + 1;
            while ($j < $m && $sig[$j][0] !== ';') {
                $stmt .= $sig[$j][1] . ' ';
                $j++;
            }
            $i = $j;
            $stmt = trim((string) preg_replace('/\s*([\\\\{},])\s*/', '$1', $stmt));
            if (preg_match('/^(function|const)\b/i', $stmt)) {
                continue;
            }
            if (preg_match('/^(.*?)\\\\?\{(.*)\}$/s', $stmt, $g)) {            // group use
                foreach (explode(',', $g[2]) as $part) {
                    $part = trim($part);
                    if ($part === '') { continue; }
                    $full = trim($g[1], '\\') . '\\' . preg_split('/\s*as\s*/i', $part)[0];
                    $alias = preg_match('/\s+as\s+(\w+)$/i', $part, $am) ? $am[1] : substr(strrchr('\\' . $full, '\\'), 1);
                    $imports[strtolower($alias)] = ltrim($full, '\\');
                }
            } else {
                foreach (explode(',', $stmt) as $part) {
                    $part = trim($part);
                    if ($part === '') { continue; }
                    $full = preg_split('/\s+as\s+/i', $part)[0];
                    $alias = preg_match('/\s+as\s+(\w+)$/i', $part, $am) ? $am[1] : substr(strrchr('\\' . $full, '\\'), 1);
                    $imports[strtolower($alias)] = ltrim($full, '\\');
                }
            }
            continue;
        }
        if (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && $nx && $nx[0] === T_STRING && ! ($p && $p[0] === T_DOUBLE_COLON)) {
            $declared[] = ($ns !== '' ? $ns . '\\' : '') . $nx[1];
            $i++;
            continue;
        }

        if ($id === T_CONSTANT_ENCAPSED_STRING) {
            // 'App\\Services\\Foo' or "App\\Services\\Foo": a class name written as text
            $raw = substr($text, 1, -1);
            $val = str_replace('\\\\', '\\', $raw);
            if (preg_match('/^\\\\?[A-Z][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $val) && str_contains($raw, '\\\\')) {
                $refs[] = [ltrim($val, '\\'), $line, 'string'];
            }
            continue;
        }

        if (! in_array($id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true)) {
            continue;
        }
        // --- a name token: decide whether it is a class reference
        $pt = $p[1] ?? '';
        $pid = $p[0] ?? null;
        if (in_array($pid, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_CONST, T_FN, T_GOTO, T_NAMESPACE, T_INSTEADOF], true)) {
            continue;
        }
        if (in_array($pid, [T_CASE], true) || ($pid === T_USE && $depth === 0)) {
            continue;
        }
        if ($pid === '$' ) { continue; }
        $nid = $nx[0] ?? null;
        $isNew = $pid === T_NEW;
        $isExtends = in_array($pid, [T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF], true);
        if ($nid === '(' && ! $isNew) {
            continue;                                   // function call
        }
        if ($nid === ':' && ! $isNew) {
            continue;                                   // named argument, switch/goto label, ternary constant
        }
        if (in_array(strtolower($text), BUILTIN, true)) {
            continue;
        }
        $short = $id === T_STRING ? $text : substr(strrchr('\\' . $text, '\\'), 1);
        $viaStatic = $nid === T_DOUBLE_COLON;
        $upper = preg_match('/^[A-Z0-9_]+$/', $short) === 1;
        if (! $viaStatic && ! $isNew && ! $isExtends && $upper) {
            continue;                                   // constants such as PHP_EOL, SORT_ASC, JSON_THROW_ON_ERROR
        }
        if (! preg_match('/^[A-Z]/', ltrim($text, '\\'))) {
            if (! in_array($id, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;                               // lower-case bare words are functions, keywords or constants
            }
        }
        // inside a trait-use / type position the same rules apply: any capitalised name is a class
        $refs[] = [$text, $line, 'code'];
    }

    return ['ns' => $ns, 'imports' => $imports, 'declared' => $declared, 'refs' => $refs];
}

function resolveName(string $name, string $ns, array $imports): string
{
    if ($name[0] === '\\') {
        return ltrim($name, '\\');
    }
    if (str_starts_with(strtolower($name), 'namespace\\')) {
        return ($ns !== '' ? $ns . '\\' : '') . substr($name, 10);
    }
    $first = explode('\\', $name, 2);
    $alias = strtolower($first[0]);
    if (isset($imports[$alias])) {
        return $imports[$alias] . (isset($first[1]) ? '\\' . $first[1] : '');
    }

    return ($ns !== '' ? $ns . '\\' : '') . $name;
}

function relPath(string $f, string $root): string
{
    return str_starts_with($f, $root . DIRECTORY_SEPARATOR) ? substr($f, strlen($root) + 1) : $f;
}

$o = opts($argv);
$realRoot = rtrim((string) realpath($o['root']), '/\\');
$files = phpFiles($o['root'], $o['dirs']);
foreach ($o['extra'] as $extra) {
    $abs = realpath(str_starts_with($extra, '/') || preg_match('/^[A-Za-z]:/', $extra) ? $extra : rtrim($o['root'], '/\\') . '/' . $extra);
    if ($abs === false || ! is_dir($abs)) {
        fwrite(STDERR, "refcheck: --extra folder not found: {$extra}\n");
        exit(2);
    }
    foreach (phpFiles($abs, ['']) as $f) {
        $files[] = $f;
    }
}
$files = array_values(array_unique($files));
$scans = [];
$index = [];
$roots = [];
foreach ($files as $f) {
    $s = scan($f);
    $scans[$f] = $s;
    foreach ($s['declared'] as $fqcn) {
        $index[strtolower($fqcn)] = $fqcn;
        $roots[explode('\\', $fqcn)[0]] = true;
    }
}

$problems = [];
foreach ($scans as $f => $s) {
    foreach ($s['refs'] as [$name, $line, $kind]) {
        $full = $kind === 'string' ? $name : resolveName($name, $s['ns'], $s['imports']);
        $root = explode('\\', $full)[0];
        if (! isset($roots[$root])) {
            continue;                                   // vendor or global class: not ours to check
        }
        if (! isset($index[strtolower($full)])) {
            // `App\Models` used as a namespace prefix (alias import of a namespace) is legal: a declared class lives below it
            $prefix = strtolower($full) . '\\';
            $isNamespace = false;
            foreach ($index as $k => $_) {
                if (str_starts_with($k, $prefix)) { $isNamespace = true; break; }
            }
            if ($isNamespace) {
                continue;
            }
            $problems[] = ['file' => relPath($f, $realRoot), 'line' => $line, 'name' => $name, 'resolved' => $full, 'kind' => $kind];
        }
    }
}

// imports that point at project code that no longer exists
foreach ($scans as $f => $s) {
    foreach ($s['imports'] as $alias => $full) {
        $root = explode('\\', $full)[0];
        if (isset($roots[$root]) && ! isset($index[strtolower($full)])) {
            $prefix = strtolower($full) . '\\';
            $isNamespace = false;
            foreach ($index as $k => $_) {
                if (str_starts_with($k, $prefix)) { $isNamespace = true; break; }
            }
            if (! $isNamespace) {
                $problems[] = ['file' => relPath($f, $realRoot), 'line' => 0, 'name' => $full, 'resolved' => $full, 'kind' => 'import'];
            }
        }
    }
}

$graph = [];
if ($o['graph']) {
    foreach ($scans as $f => $s) {
        $refs = [];
        foreach ($s['refs'] as [$name, $line, $kind]) {
            $full = $kind === 'string' ? $name : resolveName($name, $s['ns'], $s['imports']);
            if (isset($index[strtolower($full)])) {
                $refs[$index[strtolower($full)]] = true;
            }
        }
        foreach ($s['imports'] as $full) {
            if (isset($index[strtolower($full)])) {
                $refs[$index[strtolower($full)]] = true;
            }
        }
        $graph[relPath($f, $realRoot)] = ['declares' => $s['declared'], 'uses' => array_keys($refs)];
    }
}

usort($problems, fn ($a, $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);
$summary = ['files' => count($files), 'classes' => count($index), 'roots' => array_keys($roots), 'problems' => count($problems)];
if ($o['json']) {
    $summary['index'] = array_values($index);
}

if ($o['json']) {
    echo json_encode(['summary' => $summary, 'problems' => $problems] + ($o['graph'] ? ['graph' => $graph] : []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
} else {
    echo sprintf("refcheck: %d files, %d classes, roots: %s\n", $summary['files'], $summary['classes'], implode(', ', $summary['roots']));
    foreach ($problems as $p) {
        echo sprintf("  %s:%d  %s  ->  %s  (%s)\n", $p['file'], $p['line'], $p['name'], $p['resolved'], $p['kind']);
    }
    echo $problems ? sprintf("\n%d unresolved reference(s).\n", count($problems)) : "All references to project code resolve.\n";
}
exit($problems ? 1 : 0);
