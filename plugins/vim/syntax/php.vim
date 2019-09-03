" Highlights Phan's phpdoc annotations in vim.
" (in addition to what is already highlighted by vim)
"
" To use this, add this file to ~/.vim/syntax/php.vim (or the equivalent) 
syntax match phpDocTags "@\(\(phan-\(forbid-undeclared-magic-\(properties\|methods\)\|closure-scope\|file-suppress\|suppress\|\|suppress-next-line\|suppress-current-line\|method\|output-reference\|ignore-reference\|override\|param\|property-read\|template\|assert\|assert-\(true\|false\)-condition\|immutable\|side-effect-free\|read-only\|write-only\|property\|property-write\|return\|real-return\|unused-param\|pure\|var\|var-force\|var-debug\|transient\|\)\)\|transient\|property-read\|property-write\|template\)" containedin=phpComment
