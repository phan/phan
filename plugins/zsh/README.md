This contains completion scripts for zsh. Installation instructions can be found in [`./_phan`](./_phan).

Development
-----------

https://github.com/zsh-users/zsh-completions/blob/master/zsh-completions-howto.org has details on how to write zsh completion scripts.

`_arguments` can be replaced with the below `_debug_arguments` script if we want to extract the list of options this completes.
This is useful for keeping this in sync with Phan and the bash completion scripts.

```sh
_debug_arguments() {
	# This dumps all commands that would be passed to _arguments.
	# This is useful for keeping this in sync with _plugins/bash/phan
	for argument in "$@"; do
		# Can add `| grep '[=+]' to list options with required arguments (or just look at src/Phan/CLI.php)
		echo "$argument" | sed 's/\(^([^)]\+)\)\*\?\|\[.*//g' | grep '[=+]' | tr ',{}=+' '\n' | sed '/^$/d'
	done
}
```
