#!/bin/bash
#
# Usage: /path/to/phan/tool/make_ctags_for_phan_project [options for phpctags]
#
# This combines Phan and phpctags to generate a ctags file for your Phan project.
# (This includes only the files you parse and analyze)
#
# phpctags can be obtained from https://github.com/vim-php/phpctags
#
# This may be useful if you already have whitelists and/or blacklists for excluding:
#
# - classes that aren't direct dependencies of your codebase
# - `examples/` folders and test folders in `vendor/` and other third party code
#
# The resulting tags file may be combined with tags for JS, CSS, etc.
function usage() {
	echo "Usage: $0 [options for phpctags]" 1>&2
	echo "You may wish to run ./phan --dump-ctags=basic instead" 1>&2
}

if ! type phpctags ; then
	echo "$0: Could not find phpctags in $PATH" 1>&2
	echo
	usage
	exit 1
fi
if [ ! -f .phan/config.php ]; then
	echo "Must run this from the root of a project configured to use Phan (could not find $PWD/.phan/config.php)" 1>&2
	usage
	exit 1
fi
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo "Generating file list with Phan and running phpctags"

# Run phpctags on the files that Phan would parse or analyze.
# This can be a long list. Choose a default larger than 128KB (2MB) -- (run getconf ARG_MAX for the actual limit)
# Unfortunately, phpctags doesn't have an option to read file arguments from a list or stdin
# Note: Use a default memory limit higher than 128M - This is needed to parse FunctionSignatureMap.php
"$DIR/../phan" --dump-parsed-file-list | xargs --exit -s 2000000 phpctags --memory=2G --verbose "$@"
