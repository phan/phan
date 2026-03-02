package main

import "encoding/json"

var toolDefs = []ToolDef{
	{
		Name:        "analyze",
		Description: "Run Phan static analysis on a PHP file via the Phan daemon. Returns diagnostics (errors, warnings).",
		InputSchema: json.RawMessage(`{
			"type": "object",
			"properties": {
				"file": {"type": "string", "description": "Path to the PHP file to analyze (relative to project root)"},
				"contents": {"type": "string", "description": "Optional file contents to analyze (for unsaved changes). If omitted, the file on disk is used."}
			},
			"required": ["file"]
		}`),
	},
	{
		Name:        "references",
		Description: "Find all callsites/usages of a class element (method, property, constant). Returns file:line locations.",
		InputSchema: json.RawMessage(`{
			"type": "object",
			"properties": {
				"symbol": {"type": "string", "description": "Fully qualified element name, e.g. '\\Phan\\CodeBase::getMethodByFQSEN'"},
				"type": {"type": "string", "enum": ["method", "prop", "const"], "description": "Element type"}
			},
			"required": ["symbol", "type"]
		}`),
	},
	{
		Name:        "signature",
		Description: "Get the signature of a function, method, property, or constant including parameters, types, and docblock.",
		InputSchema: json.RawMessage(`{
			"type": "object",
			"properties": {
				"symbol": {"type": "string", "description": "Fully qualified name, e.g. '\\Phan\\CodeBase::getMethodByFQSEN' or '\\array_map'"}
			},
			"required": ["symbol"]
		}`),
	},
	{
		Name:        "hierarchy",
		Description: "Get the class hierarchy: parent classes, child classes, implemented interfaces, and used traits.",
		InputSchema: json.RawMessage(`{
			"type": "object",
			"properties": {
				"class": {"type": "string", "description": "Fully qualified class/interface/trait name, e.g. '\\Phan\\CodeBase'"}
			},
			"required": ["class"]
		}`),
	},
	{
		Name:        "implementations",
		Description: "Find all classes that implement an interface or extend a class.",
		InputSchema: json.RawMessage(`{
			"type": "object",
			"properties": {
				"symbol": {"type": "string", "description": "Fully qualified interface or class name"}
			},
			"required": ["symbol"]
		}`),
	},
	{
		Name:        "symbols",
		Description: "Search for symbols (functions, methods, properties, constants) by name pattern. Uses SQL LIKE matching.",
		InputSchema: json.RawMessage(`{
			"type": "object",
			"properties": {
				"query": {"type": "string", "description": "Search pattern (SQL LIKE syntax, e.g. '%CodeBase%' or 'get%')"},
				"kind": {"type": "string", "enum": ["function", "method", "property", "constant"], "description": "Optional: filter by element kind"},
				"limit": {"type": "integer", "description": "Max results to return (default 50)", "default": 50}
			},
			"required": ["query"]
		}`),
	},
	{
		Name:        "file_symbols",
		Description: "List all symbols (functions, methods, properties, constants) defined in a file. Useful for understanding a file's API surface.",
		InputSchema: json.RawMessage(`{
			"type": "object",
			"properties": {
				"file": {"type": "string", "description": "File path relative to project root, e.g. 'src/Phan/CodeBase.php'"},
				"kind": {"type": "string", "enum": ["function", "method", "property", "constant"], "description": "Optional: filter by element kind"}
			},
			"required": ["file"]
		}`),
	},
	{
		Name:        "unused",
		Description: "Find potentially dead code: methods, functions, properties, or constants with no references in the callsites table. Filters out magic methods, test methods, and other common false positives.",
		InputSchema: json.RawMessage(`{
			"type": "object",
			"properties": {
				"file": {"type": "string", "description": "Optional: limit search to symbols defined in this file"},
				"class": {"type": "string", "description": "Optional: limit search to symbols in this class (fully qualified)"},
				"kind": {"type": "string", "enum": ["function", "method", "property", "constant"], "description": "Optional: filter by element kind"},
				"limit": {"type": "integer", "description": "Max results to return (default 100)", "default": 100}
			}
		}`),
	},
	{
		Name:        "type_at",
		Description: "Get the type that Phan infers for a variable at a specific line in a PHP file. Injects @phan-debug-var and returns the inferred union type.",
		InputSchema: json.RawMessage(`{
			"type": "object",
			"properties": {
				"file": {"type": "string", "description": "Path to the PHP file (relative to project root)"},
				"line": {"type": "integer", "description": "Line number where the variable is in scope"},
				"variable": {"type": "string", "description": "Variable name without $ prefix, e.g. 'result' or 'this'"}
			},
			"required": ["file", "line", "variable"]
		}`),
	},
}

func dispatch(name string, args json.RawMessage, s *Server) *ToolsCallResult {
	switch name {
	case "analyze":
		return s.handleAnalyze(args)
	case "references":
		return s.handleReferences(args)
	case "signature":
		return s.handleSignature(args)
	case "hierarchy":
		return s.handleHierarchy(args)
	case "implementations":
		return s.handleImplementations(args)
	case "symbols":
		return s.handleSymbols(args)
	case "file_symbols":
		return s.handleFileSymbols(args)
	case "unused":
		return s.handleUnused(args)
	case "type_at":
		return s.handleTypeAt(args)
	default:
		return errorResult("Unknown tool: " + name)
	}
}
