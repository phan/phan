import * as path from "node:path";
import { getWolfDir, ensureWolfDir, readJSON, writeJSON, readMarkdown, parseAnatomy, estimateTokens, readStdin, normalizePath } from "./shared.js";
async function main() {
    ensureWolfDir();
    const wolfDir = getWolfDir();
    const hooksDir = path.join(wolfDir, "hooks");
    const sessionFile = path.join(hooksDir, "_session.json");
    const raw = await readStdin();
    let input;
    try {
        input = JSON.parse(raw);
    }
    catch {
        process.exit(0);
        return;
    }
    const filePath = input.tool_input?.file_path ?? input.tool_input?.path ?? "";
    const content = input.tool_output?.content ?? "";
    if (!filePath) {
        process.exit(0);
        return;
    }
    const normalizedFile = normalizePath(filePath);
    // Skip tracking for .wolf/ internal files — consistent with pre-read
    const projectDir = normalizePath(process.env.CLAUDE_PROJECT_DIR || process.cwd());
    const relToProject = normalizedFile.startsWith(projectDir)
        ? normalizedFile.slice(projectDir.length).replace(/^\//, "")
        : "";
    if (relToProject.startsWith(".wolf/") || relToProject.startsWith(".wolf\\")) {
        process.exit(0);
        return;
    }
    const ext = path.extname(filePath).toLowerCase();
    const codeExts = new Set([".ts", ".js", ".tsx", ".jsx", ".py", ".rs", ".go", ".java", ".c", ".cpp", ".css", ".json", ".yaml", ".yml"]);
    const proseExts = new Set([".md", ".txt", ".rst"]);
    const type = codeExts.has(ext) ? "code" : proseExts.has(ext) ? "prose" : "mixed";
    let tokens = content ? estimateTokens(content, type) : 0;
    // Fallback: if tool_output had no content, use anatomy token estimate
    if (tokens === 0) {
        const anatomyContent = readMarkdown(path.join(wolfDir, "anatomy.md"));
        const sections = parseAnatomy(anatomyContent);
        for (const [sectionKey, entries] of sections) {
            for (const entry of entries) {
                const entryRelPath = normalizePath(path.join(sectionKey, entry.file));
                if (normalizedFile.endsWith(entryRelPath) || normalizedFile.endsWith("/" + entryRelPath)) {
                    tokens = entry.tokens;
                    break;
                }
            }
            if (tokens > 0)
                break;
        }
    }
    const session = readJSON(sessionFile, { files_read: {} });
    if (session.files_read[normalizedFile]) {
        session.files_read[normalizedFile].tokens = tokens;
    }
    else {
        session.files_read[normalizedFile] = {
            count: 1,
            tokens,
            first_read: new Date().toISOString(),
        };
    }
    writeJSON(sessionFile, session);
    process.exit(0);
}
main().catch(() => process.exit(0));
//# sourceMappingURL=post-read.js.map