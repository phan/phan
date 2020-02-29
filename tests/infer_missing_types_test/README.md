
This folder contains tests of Phan's ability to infer missing union types.
This is useful when a codebase has gaps in type information for properties, return types, etc.,
but still needs to be checked for potential bugs.

Features that this tests:

- `--analyze-twice` - Run the analysis phase on all files, twice.
  Phan gathers additional type information during the analysis phase (for return types and param types),
  and this may help detect missing methods, potential type errors, etc.
- `UnknownClassElementAccessPlugin` - Useful to know what parts of the code have unanalyzable types, even if the code gets analyzed twice.
- `UnknownElementTypePlugin` - Useful to assist with adding types to the codebase so that Phan doesn't need to infer potentially incomplete types.
