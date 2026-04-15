<?php

declare (strict_types = 1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS'                                => true,
        'declare_strict_types'                   => true,
        'array_syntax'                           => ['syntax' => 'short'],
        'list_syntax'                            => ['syntax' => 'short'],
        'binary_operator_spaces'                 => ['default' => 'single_space'],
        'blank_line_after_opening_tag'           => true,
        'concat_space'                           => ['spacing' => 'one'],
        'encoding'                               => true,
        'fully_qualified_strict_types'           => [
            'import_symbols'                        => true,
            'leading_backslash_in_global_namespace' => false,
        ],
        'line_ending'                            => true,
        'multiline_whitespace_before_semicolons' => true,
        'no_closing_tag'                         => true,
        'no_extra_blank_lines'                   => true,
        'no_multiple_statements_per_line'        => true,
        'no_trailing_whitespace'                 => true,
        'no_unused_imports'                      => true,
        'no_whitespace_in_blank_line'            => true,
        'ordered_imports'                        => ['sort_algorithm' => 'alpha'],
        'phpdoc_indent'                          => true,
        'phpdoc_scalar'                          => true,
        'phpdoc_trim'                            => true,
        'phpdoc_types'                           => true,
        'single_blank_line_at_eof'               => true,
        'trailing_comma_in_multiline'            => true,
    ])
    // 💡 by default, Fixer looks for `*.php` files excluding `./vendor/` - here, you can groom this config
    ->setFinder(
        (new Finder())
        // 💡 root folder to check
            ->in(__DIR__)
            // 💡 additional files, eg bin entry file
            // ->append([__DIR__.'/bin-entry-file'])
            // 💡 folders to exclude, if any
            // ->exclude([/* ... */])
            // 💡 path patterns to exclude, if any
            // ->notPath([/* ... */])
            // 💡 extra configs
            // ->ignoreDotFiles(false) // true by default in v3, false in v4 or future mode
            // ->ignoreVCS(true) // true by default
    )
;
