package main

import (
	"encoding/json"
	"fmt"
	"io"
	"net"
	"os"
	"regexp"
	"strings"
)

func (s *Server) handleAnalyze(raw json.RawMessage) *ToolsCallResult {
	if s.daemonAddr == "" {
		return errorResult("Phan daemon address not configured. Start the daemon with: ./phan --daemonize-tcp-port 4846")
	}

	var args struct {
		File     string  `json:"file"`
		Contents *string `json:"contents"`
	}
	if err := json.Unmarshal(raw, &args); err != nil {
		return errorResult("Invalid arguments: " + err.Error())
	}

	resp, err := s.callDaemon(args.File, args.Contents)
	if err != nil {
		return errorResult(err.Error())
	}

	if resp == "" || resp == "[]" || resp == "[]\n" {
		return textResult("No issues found in " + args.File)
	}

	return textResult(resp)
}

func (s *Server) handleTypeAt(raw json.RawMessage) *ToolsCallResult {
	if s.daemonAddr == "" {
		return errorResult("Phan daemon address not configured. Start the daemon with: ./phan --daemonize-tcp-port 4846")
	}

	var args struct {
		File     string `json:"file"`
		Line     int    `json:"line"`
		Variable string `json:"variable"`
	}
	if err := json.Unmarshal(raw, &args); err != nil {
		return errorResult("Invalid arguments: " + err.Error())
	}

	// Read file from disk
	data, err := os.ReadFile(args.File)
	if err != nil {
		return errorResult("Failed to read file: " + err.Error())
	}

	lines := strings.Split(string(data), "\n")
	if args.Line < 1 || args.Line > len(lines) {
		return errorResult(fmt.Sprintf("Line %d out of range (file has %d lines)", args.Line, len(lines)))
	}

	// Inject '@phan-debug-var $variable'; before the target line
	debugStmt := fmt.Sprintf("'@phan-debug-var $%s';", args.Variable)
	modified := make([]string, 0, len(lines)+1)
	modified = append(modified, lines[:args.Line-1]...)
	modified = append(modified, debugStmt)
	modified = append(modified, lines[args.Line-1:]...)

	modifiedContents := strings.Join(modified, "\n")
	resp, err := s.callDaemon(args.File, &modifiedContents)
	if err != nil {
		return errorResult(err.Error())
	}

	// Parse the JSON response to extract PhanDebugAnnotation descriptions
	typ := extractDebugType(resp, args.Variable)
	if typ != "" {
		return textResult(fmt.Sprintf("$%s: %s", args.Variable, typ))
	}

	return textResult(fmt.Sprintf("Could not determine type for $%s at %s:%d\n\nDaemon response:\n%s", args.Variable, args.File, args.Line, resp))
}

// extractDebugType parses the daemon JSON response and extracts the union type
// from PhanDebugAnnotation issues for the given variable.
func extractDebugType(resp string, variable string) string {
	var result struct {
		Issues []struct {
			CheckName   string `json:"check_name"`
			Description string `json:"description"`
		} `json:"issues"`
	}
	if err := json.Unmarshal([]byte(resp), &result); err != nil {
		return ""
	}

	re := regexp.MustCompile(`\$` + regexp.QuoteMeta(variable) + `\s+-\s+it has union type\s+(.+)`)
	for _, issue := range result.Issues {
		if issue.CheckName != "PhanDebugAnnotation" {
			continue
		}
		m := re.FindStringSubmatch(issue.Description)
		if m != nil {
			return m[1]
		}
	}
	return ""
}

// callDaemon sends an analyze_files request to the Phan daemon and returns the raw response.
// contents is a pointer: nil means use on-disk file, non-nil means use the provided string (even if empty).
func (s *Server) callDaemon(file string, contents *string) (string, error) {
	req := map[string]any{
		"method": "analyze_files",
		"files":  []string{file},
		"format": "json",
	}

	if contents != nil {
		req["temporary_file_mapping_contents"] = map[string]string{
			file: *contents,
		}
	}

	reqBytes, err := json.Marshal(req)
	if err != nil {
		return "", fmt.Errorf("failed to encode request: %w", err)
	}

	conn, err := net.Dial("tcp", s.daemonAddr)
	if err != nil {
		return "", fmt.Errorf("failed to connect to Phan daemon at %s: %w", s.daemonAddr, err)
	}
	defer conn.Close()

	if _, err := conn.Write(reqBytes); err != nil {
		return "", fmt.Errorf("failed to send request: %w", err)
	}
	if tc, ok := conn.(*net.TCPConn); ok {
		tc.CloseWrite()
	}

	respBytes, err := io.ReadAll(conn)
	if err != nil {
		return "", fmt.Errorf("failed to read response: %w", err)
	}

	return string(respBytes), nil
}
