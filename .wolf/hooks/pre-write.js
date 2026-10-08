import * as fs from "node:fs";
import * as path from "node:path";
import { getWolfDir, ensureWolfDir, readJSON, readMarkdown, readStdin } from "./shared.js";
async function main() {
    ensureWolfDir();
    const wolfDir = getWolfDir();
    const raw = await readStdin();
    let input;
    try {
        input = JSON.parse(raw);
    }
    catch {
        process.exit(0);
        return;
    }
    // For Edit tool, the meaningful content is old_string + new_string
    const content = input.tool_input?.content ?? "";
    const oldStr = input.tool_input?.old_string ?? "";
    const newStr = input.tool_input?.new_string ?? "";
    const filePath = input.tool_input?.file_path ?? input.tool_input?.path ?? "";
    const allContent = [content, oldStr, newStr].join("\n");
    if (!allContent.trim()) {
        process.exit(0);
        return;
    }
    // 1. Cerebrum Do-Not-Repeat check
    checkCerebrum(wolfDir, allContent);
    // 2. Bug log: search for similar past bugs when editing code
    // This fires when Claude is about to edit a file — if the edit looks like a fix
    // (changing error handling, modifying catch blocks, etc.), check the bug log
    if (filePath && (oldStr || content)) {
        checkBugLog(wolfDir, filePath, oldStr, newStr, content);
    }
    process.exit(0);
}
function checkCerebrum(wolfDir, content) {
    const cerebrumContent = readMarkdown(path.join(wolfDir, "cerebrum.md"));
    const doNotRepeatSection = cerebrumContent.split("## Do-Not-Repeat")[1];
    if (!doNotRepeatSection)
        return;
    const entries = doNotRepeatSection.split("## ")[0];
    const lines = entries.split("\n").filter((l) => l.trim().startsWith("[") || l.trim().startsWith("-"));
    for (const line of lines) {
        const trimmed = line.trim().replace(/^[-*]\s*/, "").replace(/^\[[\d-]+\]\s*/, "");
        if (!trimmed)
            continue;
        const patterns = [];
        const quotedMatches = trimmed.match(/"([^"]+)"/g) || trimmed.match(/'([^']+)'/g) || trimmed.match(/`([^`]+)`/g);
        if (quotedMatches) {
            for (const qm of quotedMatches) {
                patterns.push(qm.replace(/["'`]/g, ""));
            }
        }
        const neverMatch = trimmed.match(/(?:never use|avoid|don't use|do not use)\s+(\w+)/i);
        if (neverMatch)
            patterns.push(neverMatch[1]);
        for (const pattern of patterns) {
            try {
                const regex = new RegExp(`\\b${pattern.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}\\b`, "i");
                if (regex.test(content)) {
                    process.stderr.write(`⚠️ OpenWolf cerebrum warning: "${trimmed}" — check your code before proceeding.\n`);
                }
            }
            catch { }
        }
    }
}
// Common words that appear in most code — must be excluded from similarity matching
const STOP_WORDS = new Set([
    "error", "function", "return", "const", "this", "that", "with", "from",
    "import", "export", "class", "interface", "type", "undefined", "null",
    "true", "false", "string", "number", "object", "array", "value",
    "file", "path", "name", "data", "response", "request", "result",
    "should", "must", "does", "have", "been", "will", "would", "could",
    "when", "then", "else", "each", "some", "every", "only",
]);
function checkBugLog(wolfDir, filePath, oldStr, newStr, content) {
    const bugLogPath = path.join(wolfDir, "buglog.json");
    if (!fs.existsSync(bugLogPath))
        return;
    const bugLog = readJSON(bugLogPath, { version: 1, bugs: [] });
    if (bugLog.bugs.length === 0)
        return;
    const basename = path.basename(filePath);
    // ONLY surface bugs that match the SAME file being edited.
    // Cross-file matching is too noisy and risks misdirecting Claude.
    const fileMatches = bugLog.bugs.filter(b => {
        const bugBasename = path.basename(b.file);
        return bugBasename === basename;
    });
    if (fileMatches.length === 0)
        return;
    // Further filter: require tag or error_message overlap with the edit content
    const editText = (oldStr + " " + newStr + " " + content).toLowerCase();
    const editTokens = tokenize(editText);
    const relevant = fileMatches.filter(bug => {
        // Check if any bug tag appears in the edit content
        const tagHit = bug.tags.some(t => editText.includes(t.toLowerCase()));
        if (tagHit)
            return true;
        // Check meaningful word overlap (excluding stop words)
        const bugTokens = tokenize(bug.error_message + " " + bug.root_cause);
        const overlap = [...editTokens].filter(t => bugTokens.has(t));
        // Require at least 3 meaningful overlapping words
        return overlap.length >= 3;
    });
    if (relevant.length === 0)
        return;
    // Surface as a FYI, not a directive — Claude should evaluate, not blindly apply
    process.stderr.write(`📋 OpenWolf buglog: ${relevant.length} past bug(s) found for ${basename} — review for context, do NOT apply blindly:\n`);
    for (const bug of relevant.slice(0, 2)) {
        process.stderr.write(`   [${bug.id}] "${bug.error_message.slice(0, 70)}"\n   Cause: ${bug.root_cause.slice(0, 80)}\n   Fix: ${bug.fix.slice(0, 80)}\n`);
    }
}
function tokenize(text) {
    return new Set(text.replace(/[^\w\s]/g, " ").split(/\s+/)
        .filter(w => w.length > 3 && !STOP_WORDS.has(w.toLowerCase()))
        .map(w => w.toLowerCase()));
}
main().catch(() => process.exit(0));
//# sourceMappingURL=pre-write.js.map