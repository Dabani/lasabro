<?php
/** Prints "<sha1>  <file>" of each PHP file's token stream with comments and whitespace removed. Used to prove a comment-only edit changed no code. */
foreach (array_slice($argv, 1) as $file) {
    $out = '';
    foreach (token_get_all((string) file_get_contents($file)) as $t) {
        if (is_array($t)) {
            if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE, T_OPEN_TAG, T_CLOSE_TAG], true)) {
                continue;
            }
            $out .= $t[0] . ':' . $t[1] . "\n";
        } else {
            $out .= $t . "\n";
        }
    }
    echo sha1($out), '  ', $file, "\n";
}
