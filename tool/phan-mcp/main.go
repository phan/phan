package main

import (
	"bufio"
	"database/sql"
	"encoding/json"
	"flag"
	"fmt"
	"os"

	_ "modernc.org/sqlite"
)

type Server struct {
	db         *sql.DB
	daemonAddr string
}

func main() {
	dbPath := flag.String("db", "", "Path to Phound SQLite database")
	daemonAddr := flag.String("daemon", "", "Phan daemon address (host:port)")
	flag.Parse()

	s := &Server{
		daemonAddr: *daemonAddr,
	}

	fmt.Fprintf(os.Stderr, "phan-mcp v0.1.0 — MCP server for Phan static analysis\n")
	fmt.Fprintf(os.Stderr, "Transport: stdio (JSON-RPC 2.0, newline-delimited)\n")

	if *dbPath != "" {
		if _, err := os.Stat(*dbPath); err != nil {
			fmt.Fprintf(os.Stderr, "SQLite: %s not found\n", *dbPath)
		} else if db, err := sql.Open("sqlite", *dbPath+"?mode=ro"); err != nil {
			fmt.Fprintf(os.Stderr, "SQLite: FAILED to open %s: %v\n", *dbPath, err)
		} else if err := db.Ping(); err != nil {
			fmt.Fprintf(os.Stderr, "SQLite: FAILED to read %s: %v\n", *dbPath, err)
			db.Close()
		} else {
			s.db = db
			defer db.Close()
			var sigCount, callCount int
			if err := s.db.QueryRow("SELECT count(*) FROM signatures").Scan(&sigCount); err != nil {
				fmt.Fprintf(os.Stderr, "SQLite: FAILED to read signatures from %s: %v\n", *dbPath, err)
			} else if err := s.db.QueryRow("SELECT count(*) FROM callsites").Scan(&callCount); err != nil {
				fmt.Fprintf(os.Stderr, "SQLite: FAILED to read callsites from %s: %v\n", *dbPath, err)
			} else {
				fmt.Fprintf(os.Stderr, "SQLite: %s (%d signatures, %d callsites)\n", *dbPath, sigCount, callCount)
			}
		}
	} else {
		fmt.Fprintf(os.Stderr, "SQLite: not configured (use --db <path>)\n")
	}

	if *daemonAddr != "" {
		fmt.Fprintf(os.Stderr, "Daemon: %s\n", *daemonAddr)
	} else {
		fmt.Fprintf(os.Stderr, "Daemon: not configured (use --daemon <host:port>)\n")
	}

	fmt.Fprintf(os.Stderr, "Ready — reading from stdin\n")

	scanner := bufio.NewScanner(os.Stdin)
	// Allow large messages (16MB)
	scanner.Buffer(make([]byte, 0, 64*1024), 16*1024*1024)

	for scanner.Scan() {
		line := scanner.Bytes()
		if len(line) == 0 {
			continue
		}

		var req Request
		if err := json.Unmarshal(line, &req); err != nil {
			writeError(nil, -32700, "Parse error: "+err.Error())
			continue
		}

		s.handle(&req)
	}

	if err := scanner.Err(); err != nil {
		fmt.Fprintf(os.Stderr, "stdin read error: %v\n", err)
		os.Exit(1)
	}
}

func (s *Server) handle(req *Request) {
	switch req.Method {
	case "initialize":
		writeResponse(req.ID, InitializeResult{
			ProtocolVersion: "2024-11-05",
			Capabilities: ServerCaps{
				Tools: &ToolsCap{ListChanged: false},
			},
			ServerInfo: ServerInfo{
				Name:    "phan-mcp",
				Version: "0.1.0",
			},
			Instructions: "Phan static analysis tools for PHP. Use 'symbols' to search, 'signature' for details, 'references' for usages, 'hierarchy' for class relationships, 'implementations' for interface implementors, and 'analyze' for live diagnostics.",
		})

	case "notifications/initialized":
		// No response needed for notifications

	case "ping":
		writeResponse(req.ID, map[string]any{})

	case "tools/list":
		if req.ID != nil {
			writeResponse(req.ID, ToolsListResult{Tools: toolDefs})
		}

	case "tools/call":
		if req.ID == nil {
			return // notifications don't get responses
		}
		var params ToolsCallParams
		if err := json.Unmarshal(req.Params, &params); err != nil {
			writeError(req.ID, -32602, "Invalid params: "+err.Error())
			return
		}
		result := dispatch(params.Name, params.Arguments, s)
		writeResponse(req.ID, result)

	default:
		if req.ID != nil {
			writeError(req.ID, -32601, "Method not found: "+req.Method)
		}
		// Ignore unknown notifications (no ID)
	}
}

func writeResponse(id *json.RawMessage, result any) {
	resp := Response{
		JSONRPC: "2.0",
		ID:      id,
		Result:  result,
	}
	data, err := json.Marshal(resp)
	if err != nil {
		fmt.Fprintf(os.Stderr, "marshal error: %v\n", err)
		writeError(id, -32603, "Internal error: failed to marshal response")
		return
	}
	fmt.Fprintf(os.Stdout, "%s\n", data)
}

func writeError(id *json.RawMessage, code int, message string) {
	resp := Response{
		JSONRPC: "2.0",
		ID:      id,
		Error:   &RPCError{Code: code, Message: message},
	}
	data, err := json.Marshal(resp)
	if err != nil {
		fmt.Fprintf(os.Stderr, "marshal error: %v\n", err)
		return
	}
	fmt.Fprintf(os.Stdout, "%s\n", data)
}
