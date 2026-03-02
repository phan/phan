package main

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"strings"
)

func (s *Server) handleReferences(raw json.RawMessage) *ToolsCallResult {
	if s.db == nil {
		return errorResult("SQLite database not available. Build it with: ./phan --plugin src/Phan/Plugin/Internal/PhoundPlugin.php and ensure plugin_config.phound_sqlite_path is set")
	}

	var args struct {
		Symbol string `json:"symbol"`
		Type   string `json:"type"`
	}
	if err := json.Unmarshal(raw, &args); err != nil {
		return errorResult("Invalid arguments: " + err.Error())
	}

	symbol := normalizeFQSEN(args.Symbol)

	rows, err := s.db.Query(
		"SELECT callsite FROM callsites WHERE element = ? AND type = ? ORDER BY callsite",
		symbol, args.Type,
	)
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	defer rows.Close()

	var results []string
	for rows.Next() {
		var callsite string
		if err := rows.Scan(&callsite); err != nil {
			continue
		}
		results = append(results, callsite)
	}

	if len(results) == 0 {
		return textResult(fmt.Sprintf("No references found for %s (type: %s)", symbol, args.Type))
	}

	return textResult(fmt.Sprintf("Found %d references to %s:\n%s", len(results), symbol, strings.Join(results, "\n")))
}

func (s *Server) handleSignature(raw json.RawMessage) *ToolsCallResult {
	if s.db == nil {
		return errorResult("SQLite database not available")
	}

	var args struct {
		Symbol string `json:"symbol"`
	}
	if err := json.Unmarshal(raw, &args); err != nil {
		return errorResult("Invalid arguments: " + err.Error())
	}

	symbol := normalizeFQSEN(args.Symbol)

	var sig struct {
		FQSEN      string
		Kind       string
		ClassFQSEN sql.NullString
		Name       string
		Type       string
		IsStatic   int
		Visibility sql.NullString
		Filepath   string
		Lineno     int
		Docblock   sql.NullString
	}

	err := s.db.QueryRow(
		"SELECT fqsen, kind, class_fqsen, name, type, is_static, visibility, filepath, lineno, docblock FROM signatures WHERE fqsen = ?",
		symbol,
	).Scan(&sig.FQSEN, &sig.Kind, &sig.ClassFQSEN, &sig.Name, &sig.Type, &sig.IsStatic, &sig.Visibility, &sig.Filepath, &sig.Lineno, &sig.Docblock)

	if err == sql.ErrNoRows {
		return textResult("No signature found for: " + symbol)
	}
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}

	var b strings.Builder
	// Header
	if sig.Visibility.Valid {
		b.WriteString(sig.Visibility.String)
		b.WriteString(" ")
	}
	if sig.IsStatic == 1 {
		b.WriteString("static ")
	}
	b.WriteString(sig.Kind)
	b.WriteString(" ")
	b.WriteString(sig.FQSEN)

	// Parameters (for functions/methods)
	if sig.Kind == "function" || sig.Kind == "method" {
		params, err := s.queryParameters(symbol)
		if err == nil && len(params) > 0 {
			b.WriteString("(")
			for i, p := range params {
				if i > 0 {
					b.WriteString(", ")
				}
				if p.Type != "" {
					b.WriteString(p.Type)
					b.WriteString(" ")
				}
				if p.IsReference {
					b.WriteString("&")
				}
				if p.IsVariadic {
					b.WriteString("...")
				}
				b.WriteString("$")
				b.WriteString(p.Name)
				if p.DefaultRepr.Valid {
					b.WriteString(" = ")
					b.WriteString(p.DefaultRepr.String)
				}
			}
			b.WriteString(")")
		} else {
			b.WriteString("()")
		}
	}

	// Return type
	if sig.Type != "" {
		b.WriteString(": ")
		b.WriteString(sig.Type)
	}

	b.WriteString("\n\nDefined in: ")
	b.WriteString(sig.Filepath)
	b.WriteString(":")
	b.WriteString(fmt.Sprintf("%d", sig.Lineno))

	if sig.Docblock.Valid && sig.Docblock.String != "" {
		b.WriteString("\n\n")
		b.WriteString(sig.Docblock.String)
	}

	return textResult(b.String())
}

type paramInfo struct {
	Name        string
	Type        string
	IsVariadic  bool
	IsReference bool
	IsOptional  bool
	DefaultRepr sql.NullString
}

func (s *Server) queryParameters(fqsen string) ([]paramInfo, error) {
	rows, err := s.db.Query(
		"SELECT name, type, is_variadic, is_reference, is_optional, default_repr FROM parameters WHERE fqsen = ? ORDER BY idx",
		fqsen,
	)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var params []paramInfo
	for rows.Next() {
		var p paramInfo
		var variadic, reference, optional int
		if err := rows.Scan(&p.Name, &p.Type, &variadic, &reference, &optional, &p.DefaultRepr); err != nil {
			continue
		}
		p.IsVariadic = variadic == 1
		p.IsReference = reference == 1
		p.IsOptional = optional == 1
		params = append(params, p)
	}
	return params, nil
}

func (s *Server) handleHierarchy(raw json.RawMessage) *ToolsCallResult {
	if s.db == nil {
		return errorResult("SQLite database not available")
	}

	var args struct {
		Class string `json:"class"`
	}
	if err := json.Unmarshal(raw, &args); err != nil {
		return errorResult("Invalid arguments: " + err.Error())
	}

	class := normalizeFQSEN(args.Class)
	var b strings.Builder
	b.WriteString("Hierarchy for ")
	b.WriteString(class)
	b.WriteString("\n")

	// Parents (classes this extends)
	parents, err := s.queryColumn("SELECT parent FROM class_relationships WHERE child = ? ORDER BY parent", class)
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	if len(parents) > 0 {
		b.WriteString("\nExtends:\n")
		for _, p := range parents {
			b.WriteString("  ")
			b.WriteString(p)
			b.WriteString("\n")
		}
	}

	// Children (classes that extend this)
	children, err := s.queryColumn("SELECT child FROM class_relationships WHERE parent = ? ORDER BY child", class)
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	if len(children) > 0 {
		b.WriteString("\nExtended by:\n")
		for _, c := range children {
			b.WriteString("  ")
			b.WriteString(c)
			b.WriteString("\n")
		}
	}

	// Interfaces
	ifaces, err := s.queryColumn("SELECT interface FROM class_interfaces WHERE class = ? ORDER BY interface", class)
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	if len(ifaces) > 0 {
		b.WriteString("\nImplements:\n")
		for _, i := range ifaces {
			b.WriteString("  ")
			b.WriteString(i)
			b.WriteString("\n")
		}
	}

	// Sub-interfaces (for interfaces)
	subIfaces, err := s.queryColumn("SELECT child FROM interface_relationships WHERE parent = ? ORDER BY child", class)
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	if len(subIfaces) > 0 {
		b.WriteString("\nSub-interfaces:\n")
		for _, i := range subIfaces {
			b.WriteString("  ")
			b.WriteString(i)
			b.WriteString("\n")
		}
	}

	// Parent interfaces (for interfaces)
	parentIfaces, err := s.queryColumn("SELECT parent FROM interface_relationships WHERE child = ? ORDER BY parent", class)
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	if len(parentIfaces) > 0 {
		b.WriteString("\nExtends interfaces:\n")
		for _, i := range parentIfaces {
			b.WriteString("  ")
			b.WriteString(i)
			b.WriteString("\n")
		}
	}

	// Traits
	traits, err := s.queryColumn("SELECT trait FROM class_traits WHERE class = ? ORDER BY trait", class)
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	if len(traits) > 0 {
		b.WriteString("\nUses traits:\n")
		for _, t := range traits {
			b.WriteString("  ")
			b.WriteString(t)
			b.WriteString("\n")
		}
	}

	return textResult(b.String())
}

func (s *Server) handleImplementations(raw json.RawMessage) *ToolsCallResult {
	if s.db == nil {
		return errorResult("SQLite database not available")
	}

	var args struct {
		Symbol string `json:"symbol"`
	}
	if err := json.Unmarshal(raw, &args); err != nil {
		return errorResult("Invalid arguments: " + err.Error())
	}

	symbol := normalizeFQSEN(args.Symbol)
	var results []string

	// Check class_interfaces (classes implementing this interface)
	rows1, err := s.queryTwoColumns(
		"SELECT ci.class, c.filepath FROM class_interfaces ci LEFT JOIN classes c ON ci.class = c.name WHERE ci.interface = ? ORDER BY ci.class",
		symbol,
	)
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	for _, r := range rows1 {
		results = append(results, fmt.Sprintf("%s  (%s)", r[0], r[1]))
	}

	// Check class_relationships (classes extending this class)
	rows2, err := s.queryTwoColumns(
		"SELECT cr.child, c.filepath FROM class_relationships cr LEFT JOIN classes c ON cr.child = c.name WHERE cr.parent = ? ORDER BY cr.child",
		symbol,
	)
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	for _, r := range rows2 {
		results = append(results, fmt.Sprintf("%s  (%s)", r[0], r[1]))
	}

	if len(results) == 0 {
		return textResult("No implementations found for: " + symbol)
	}

	return textResult(fmt.Sprintf("Found %d implementations of %s:\n%s", len(results), symbol, strings.Join(results, "\n")))
}

func (s *Server) handleSymbols(raw json.RawMessage) *ToolsCallResult {
	if s.db == nil {
		return errorResult("SQLite database not available")
	}

	var args struct {
		Query string `json:"query"`
		Kind  string `json:"kind"`
		Limit int    `json:"limit"`
	}
	if err := json.Unmarshal(raw, &args); err != nil {
		return errorResult("Invalid arguments: " + err.Error())
	}

	if args.Limit <= 0 {
		args.Limit = 50
	}

	var rows *sql.Rows
	var err error

	if args.Kind != "" {
		rows, err = s.db.Query(
			"SELECT fqsen, kind, filepath, lineno FROM signatures WHERE name LIKE ? AND kind = ? ORDER BY fqsen LIMIT ?",
			args.Query, args.Kind, args.Limit,
		)
	} else {
		rows, err = s.db.Query(
			"SELECT fqsen, kind, filepath, lineno FROM signatures WHERE name LIKE ? ORDER BY fqsen LIMIT ?",
			args.Query, args.Limit,
		)
	}
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	defer rows.Close()

	var results []string
	for rows.Next() {
		var fqsen, kind, filepath string
		var lineno int
		if err := rows.Scan(&fqsen, &kind, &filepath, &lineno); err != nil {
			continue
		}
		results = append(results, fmt.Sprintf("%s  [%s]  %s:%d", fqsen, kind, filepath, lineno))
	}

	if len(results) == 0 {
		return textResult("No symbols found matching: " + args.Query)
	}

	return textResult(fmt.Sprintf("Found %d symbols:\n%s", len(results), strings.Join(results, "\n")))
}

func (s *Server) handleUnused(raw json.RawMessage) *ToolsCallResult {
	if s.db == nil {
		return errorResult("SQLite database not available")
	}

	var args struct {
		File  string `json:"file"`
		Class string `json:"class"`
		Kind  string `json:"kind"`
		Limit int    `json:"limit"`
	}
	if err := json.Unmarshal(raw, &args); err != nil {
		return errorResult("Invalid arguments: " + err.Error())
	}

	if args.Limit <= 0 {
		args.Limit = 100
	}

	// Magic methods and other names that are implicitly called
	magicNames := map[string]bool{
		"__construct": true, "__destruct": true, "__call": true,
		"__callStatic": true, "__get": true, "__set": true,
		"__isset": true, "__unset": true, "__sleep": true,
		"__wakeup": true, "__serialize": true, "__unserialize": true,
		"__toString": true, "__invoke": true, "__set_state": true,
		"__clone": true, "__debugInfo": true,
	}

	// Build query - find signatures with no matching callsite
	// For methods/properties/constants, LEFT JOIN on callsites.
	// For functions, they're never in callsites so skip them unless specifically requested.
	query := `
		SELECT s.fqsen, s.kind, s.visibility, s.filepath, s.lineno, s.name
		FROM signatures s
		LEFT JOIN callsites c ON s.fqsen = c.element AND c.type = (
			CASE s.kind
				WHEN 'method' THEN 'method'
				WHEN 'property' THEN 'prop'
				WHEN 'constant' THEN 'const'
				ELSE ''
			END
		)
		WHERE c.element IS NULL
	`
	var queryArgs []any

	if args.File != "" {
		query += " AND s.filepath = ?"
		queryArgs = append(queryArgs, args.File)
	}
	if args.Class != "" {
		class := normalizeFQSEN(args.Class)
		query += " AND s.class_fqsen = ?"
		queryArgs = append(queryArgs, class)
	}
	if args.Kind != "" {
		query += " AND s.kind = ?"
		queryArgs = append(queryArgs, args.Kind)
	} else {
		// Standalone functions aren't tracked in callsites, so they'd all appear unused.
		// Exclude them unless the user explicitly asks for kind=function.
		query += " AND s.kind != 'function'"
	}

	query += " ORDER BY s.filepath, s.lineno LIMIT ?"
	queryArgs = append(queryArgs, args.Limit)

	rows, err := s.db.Query(query, queryArgs...)
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	defer rows.Close()

	var results []string
	for rows.Next() {
		var fqsen, kind, filepath, name string
		var visibility sql.NullString
		var lineno int
		if err := rows.Scan(&fqsen, &kind, &visibility, &filepath, &lineno, &name); err != nil {
			continue
		}

		// Filter out magic methods
		if kind == "method" && magicNames[name] {
			continue
		}

		// Filter out PHPUnit test methods (public methods starting with "test")
		if kind == "method" && visibility.Valid && visibility.String == "public" &&
			strings.HasPrefix(name, "test") {
			continue
		}

		// Filter out the class constant that every class has
		if kind == "constant" && name == "class" {
			continue
		}

		var b strings.Builder
		fmt.Fprintf(&b, "%s:%d  ", filepath, lineno)
		if visibility.Valid {
			b.WriteString(visibility.String)
			b.WriteString(" ")
		}
		b.WriteString(kind)
		b.WriteString(" ")
		b.WriteString(fqsen)
		results = append(results, b.String())
	}

	if len(results) == 0 {
		return textResult("No unused symbols found")
	}

	return textResult(fmt.Sprintf("Found %d potentially unused symbols:\n%s", len(results), strings.Join(results, "\n")))
}

func (s *Server) handleFileSymbols(raw json.RawMessage) *ToolsCallResult {
	if s.db == nil {
		return errorResult("SQLite database not available")
	}

	var args struct {
		File string `json:"file"`
		Kind string `json:"kind"`
	}
	if err := json.Unmarshal(raw, &args); err != nil {
		return errorResult("Invalid arguments: " + err.Error())
	}

	var rows *sql.Rows
	var err error

	if args.Kind != "" {
		rows, err = s.db.Query(
			"SELECT fqsen, kind, type, visibility, is_static, lineno FROM signatures WHERE filepath = ? AND kind = ? ORDER BY lineno",
			args.File, args.Kind,
		)
	} else {
		rows, err = s.db.Query(
			"SELECT fqsen, kind, type, visibility, is_static, lineno FROM signatures WHERE filepath = ? ORDER BY lineno",
			args.File,
		)
	}
	if err != nil {
		return errorResult("Query error: " + err.Error())
	}
	defer rows.Close()

	var results []string
	for rows.Next() {
		var fqsen, kind, typ string
		var visibility sql.NullString
		var isStatic, lineno int
		if err := rows.Scan(&fqsen, &kind, &typ, &visibility, &isStatic, &lineno); err != nil {
			continue
		}
		var b strings.Builder
		fmt.Fprintf(&b, "L%-5d ", lineno)
		if visibility.Valid {
			b.WriteString(visibility.String)
			b.WriteString(" ")
		}
		if isStatic == 1 {
			b.WriteString("static ")
		}
		b.WriteString(kind)
		b.WriteString(" ")
		b.WriteString(fqsen)
		if typ != "" {
			b.WriteString(": ")
			b.WriteString(typ)
		}
		results = append(results, b.String())
	}

	if len(results) == 0 {
		return textResult("No symbols found in: " + args.File)
	}

	return textResult(fmt.Sprintf("%s — %d symbols:\n%s", args.File, len(results), strings.Join(results, "\n")))
}

// Helper to query a single column
func (s *Server) queryColumn(query string, arg string) ([]string, error) {
	rows, err := s.db.Query(query, arg)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var results []string
	for rows.Next() {
		var val string
		if err := rows.Scan(&val); err != nil {
			continue
		}
		results = append(results, val)
	}
	return results, nil
}

// Helper to query two columns (second column may be NULL from LEFT JOINs)
func (s *Server) queryTwoColumns(query string, arg string) ([][2]string, error) {
	rows, err := s.db.Query(query, arg)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var results [][2]string
	for rows.Next() {
		var a string
		var b sql.NullString
		if err := rows.Scan(&a, &b); err != nil {
			continue
		}
		bStr := ""
		if b.Valid {
			bStr = b.String
		}
		results = append(results, [2]string{a, bStr})
	}
	return results, nil
}

// normalizeFQSEN ensures the symbol starts with a backslash
func normalizeFQSEN(s string) string {
	if s != "" && s[0] != '\\' {
		return "\\" + s
	}
	return s
}
