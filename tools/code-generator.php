#!/usr/bin/env php
<?php
require __DIR__ . '/functions.php';

$swoole_c = __DIR__ . '/../swoole.c';
$swoole_c_content = file_get_contents($swoole_c);
$error_h = __DIR__ . '/../include/error.h';
$error_h_content = file_get_contents($error_h);
if (!preg_match_all('/SW_ERROR_[0-9A-Z_]+/', $error_h_content, $matches, PREG_PATTERN_ORDER) || empty($matches[0])) {
    swoole_error('Match ERROR enums error!');
}
// trim start and end
array_shift($matches[0]);
array_pop($matches[0]);

// generate ERROR constants
$define_output = '';
foreach ($matches[0] as $match) {
    // convert error code to define
    $constant = str_replace('SW_', '', $match);
    $define_output .= "{$_space()}SWOOLE_DEFINE({$constant});\n";
}
$swoole_c_content = preg_replace(
    '/ *?(?:SWOOLE_DEFINE\(ERROR_[0-9A-Z_]+?\);\n *)+/',
    $define_output, $swoole_c_content, 1, $is_ok
);
swoole_check($is_ok, 'Generate ERROR constants');
file_put_contents($swoole_c, $swoole_c_content);

// generate ERROR strings
$swoole_error_cc = __DIR__ . '/../src/core/error.cc';
$swoole_error_cc_content = file_get_contents($swoole_error_cc);
$swstrerror_output = "{$_space()}switch(code)\n{$_space()}{\n";
foreach ($matches[0] as $match) {
    // convert error code to swstrerror
    $sw_error_str = implode(' ', explode('_', strtolower(str_replace('SW_ERROR_', '', $match))));
    $replaces = [
        'co ' => 'Coroutine ',
        'php ' => 'PHP ',
        'ssl ' => 'SSL ',
        'dnslookup ' => 'DNS Lookup '
    ];
    $sw_error_str = str_replace(array_keys($replaces), array_values($replaces), $sw_error_str);
    $sw_error_str{0} = strtoupper($sw_error_str{0});
    $swstrerror_output .= "{$_space()}case {$match}:\n{$_space(8)}return \"{$sw_error_str}\";\n";
}
$swstrerror_output .= <<<CPP
    default:
        static char buffer[32];
#ifndef __MACH__
        snprintf(buffer, sizeof(buffer), "Unknown error %d", code);
#else
        snprintf(buffer, sizeof(buffer), "Unknown error: %d", code);
#endif
        return buffer;
    }
CPP;
$swstrerror_output .= "\n";
$swoole_error_cc_content = preg_replace(
    '/(\* swstrerror \{\{\{\*\/\n)([\s\S]+?)(\/\*\}\}\}\*\/)/',
    '${1}' . $swstrerror_output . '${3}',
    $swoole_error_cc_content, 1, $is_ok
);
swoole_check($is_ok, 'Generate ERROR stringify');
file_put_contents($swoole_error_cc, $swoole_error_cc_content);

swoole_success('Generate all source codes OK!');
