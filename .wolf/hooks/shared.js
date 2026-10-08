import * as fs from "node:fs";
import * as path from "node:path";
import * as crypto from "node:crypto";
export function getWolfDir() {
    // Prefer CLAUDE_PROJECT_DIR so hooks work even if CWD changes during a session
    const projectDir = process.env.CLAUDE_PROJECT_DIR || process.cwd();
    return path.join(projectDir, ".wolf");
}
/**
 * Bail out silently if .wolf/ directory doesn't exist in the current project.
 * Call this at the top of every hook to avoid crashes in non-OpenWolf projects.
 */
export function ensureWolfDir() {
    const wolfDir = getWolfDir();
    if (!fs.existsSync(wolfDir)) {
        process.exit(0);
    }
}
export function readJSON(filePath, fallback) {
    try {
        return JSON.parse(fs.readFileSync(filePath, "utf-8"));
    }
    catch {
        return fallback;
    }
}
export function writeJSON(filePath, data) {
    const dir = path.dirname(filePath);
    if (!fs.existsSync(dir))
        fs.mkdirSync(dir, { recursive: true });
    const tmp = filePath + "." + crypto.randomBytes(4).toString("hex") + ".tmp";
    try {
        fs.writeFileSync(tmp, JSON.stringify(data, null, 2), "utf-8");
        fs.renameSync(tmp, filePath);
    }
    catch {
        // On Windows, rename can fail if another process holds a handle.
        // Fall back to direct write and clean up the tmp file.
        try {
            fs.writeFileSync(filePath, JSON.stringify(data, null, 2), "utf-8");
        }
        catch { }
        try {
            fs.unlinkSync(tmp);
        }
        catch { }
    }
}
export function readMarkdown(filePath) {
    try {
        return fs.readFileSync(filePath, "utf-8");
    }
    catch {
        return "";
    }
}
export function appendMarkdown(filePath, line) {
    const dir = path.dirname(filePath);
    if (!fs.existsSync(dir))
        fs.mkdirSync(dir, { recursive: true });
    fs.appendFileSync(filePath, line, "utf-8");
}
export function parseAnatomy(content) {
    const sections = new Map();
    let currentSection = "";
    for (const line of content.split("\n")) {
        const sm = line.match(/^## (.+)/);
        if (sm) {
            currentSection = sm[1].trim();
            if (!sections.has(currentSection))
                sections.set(currentSection, []);
            continue;
        }
        if (!currentSection)
            continue;
        const em = line.match(/^- `([^`]+)`(?:\s+—\s+(.+?))?\s*\(~(\d+)\s+tok\)$/);
        if (em) {
            sections.get(currentSection).push({
                file: em[1],
                description: em[2] || "",
                tokens: parseInt(em[3], 10),
            });
        }
    }
    return sections;
}
export function serializeAnatomy(sections, metadata) {
    const lines = [
        "# anatomy.md",
        "",
        `> Auto-maintained by OpenWolf. Last scanned: ${metadata.lastScanned}`,
        `> Files: ${metadata.fileCount} tracked | Anatomy hits: ${metadata.hits} | Misses: ${metadata.misses}`,
        "",
    ];
    const keys = [...sections.keys()].sort();
    for (const key of keys) {
        lines.push(`## ${key}`);
        lines.push("");
        const entries = sections.get(key).sort((a, b) => a.file.localeCompare(b.file));
        for (const e of entries) {
            const desc = e.description ? ` — ${e.description}` : "";
            lines.push(`- \`${e.file}\`${desc} (~${e.tokens} tok)`);
        }
        lines.push("");
    }
    return lines.join("\n");
}
export function extractDescription(filePath) {
    const MAX_DESC = 150;
    const basename = path.basename(filePath);
    const ext = path.extname(basename).toLowerCase();
    const known = {
        "package.json": "Node.js package manifest",
        "tsconfig.json": "TypeScript configuration",
        ".gitignore": "Git ignore rules",
        "README.md": "Project documentation",
        "composer.json": "PHP package manifest",
        "requirements.txt": "Python dependencies",
        "schema.sql": "Database schema",
        "Dockerfile": "Docker container definition",
        "docker-compose.yml": "Docker Compose services",
        "Cargo.toml": "Rust package manifest",
        "go.mod": "Go module definition",
        "Gemfile": "Ruby dependencies",
        "pubspec.yaml": "Dart/Flutter package manifest",
    };
    if (known[basename])
        return known[basename];
    let content;
    try {
        const fd = fs.openSync(filePath, "r");
        const buf = Buffer.alloc(12288); // 12KB
        const n = fs.readSync(fd, buf, 0, 12288, 0);
        fs.closeSync(fd);
        content = buf.subarray(0, n).toString("utf-8");
    }
    catch {
        return "";
    }
    if (!content.trim())
        return "";
    const cap = (s) => s.length <= MAX_DESC ? s : s.slice(0, MAX_DESC - 3) + "...";
    // Markdown heading
    if (ext === ".md" || ext === ".mdx") {
        const m = content.match(/^#{1,2}\s+(.+)$/m);
        if (m)
            return cap(m[1].trim());
    }
    // HTML title
    if (ext === ".html" || ext === ".htm") {
        const m = content.match(/<title[^>]*>([^<]+)<\/title>/i);
        if (m)
            return cap(m[1].trim());
    }
    // JSDoc / PHPDoc / Javadoc — first meaningful line
    const jm = content.match(/\/\*\*\s*\n?\s*\*?\s*(.+)/);
    if (jm) {
        const l = jm[1].replace(/\*\/$/, "").trim();
        if (l && !l.startsWith("@") && l.length > 5)
            return cap(l);
    }
    // Python docstring
    if (ext === ".py") {
        const dm = content.match(/^(?:#[^\n]*\n)*\s*(?:"""(.+?)"""|'''(.+?)''')/s);
        if (dm) {
            const first = (dm[1] || dm[2]).split("\n")[0].trim();
            if (first && first.length > 3)
                return cap(first);
        }
    }
    // Rust doc comments
    if (ext === ".rs") {
        const lines = content.split("\n");
        for (const line of lines.slice(0, 20)) {
            const m = line.match(/^\s*(?:\/\/\/|\/\/!)\s*(.+)/);
            if (m && m[1].length > 5)
                return cap(m[1].trim());
        }
    }
    // Go package comment
    if (ext === ".go") {
        const m = content.match(/\/\/\s*Package\s+\w+\s+(.*)/);
        if (m)
            return cap(m[1].trim());
    }
    // C# XML doc
    if (ext === ".cs") {
        const m = content.match(/<summary>\s*([\s\S]*?)\s*<\/summary>/);
        if (m) {
            const text = m[1].replace(/\/\/\/\s*/g, "").replace(/\s+/g, " ").trim();
            if (text.length > 5)
                return cap(text);
        }
    }
    // Elixir @moduledoc
    if (ext === ".ex" || ext === ".exs") {
        const m = content.match(/@moduledoc\s+"""\s*\n\s*(.*)/);
        if (m)
            return cap(m[1].trim());
    }
    // Header comment (skip generic ones)
    const hdrLines = content.split("\n");
    for (const line of hdrLines.slice(0, 15)) {
        const t = line.trim();
        if (!t || t === "<?php" || t.startsWith("#!") || t.startsWith("namespace") || t.startsWith("use ") || t.startsWith("import ") || t.startsWith("from ") || t.startsWith("require") || t.startsWith("module "))
            continue;
        const cm = t.match(/^(?:\/\/|#|--)\s*(.+)/);
        if (cm) {
            const text = cm[1].trim();
            const lower = text.toLowerCase();
            if (text.length > 5 && !lower.startsWith("copyright") && !lower.startsWith("license") && !lower.startsWith("@") && !lower.startsWith("strict") && !lower.startsWith("generated") && !lower.startsWith("eslint-") && !lower.startsWith("nolint")) {
                return cap(text);
            }
        }
        if (!t.startsWith("//") && !t.startsWith("#") && !t.startsWith("/*") && !t.startsWith("*") && !t.startsWith("--"))
            break;
    }
    // ─── PHP / Laravel ───────────────────────────────────────
    if (ext === ".php") {
        if (basename.endsWith(".blade.php")) {
            const ext2 = content.match(/@extends\(\s*['"]([^'"]+)['"]\s*\)/);
            const sections = (content.match(/@section\(\s*['"](\w+)['"]/g) || []).map(s => s.match(/['"](\w+)['"]/)?.[1]).filter(Boolean);
            const parts = [];
            if (ext2)
                parts.push(`extends ${ext2[1]}`);
            if (sections.length)
                parts.push(`sections: ${sections.join(", ")}`);
            return cap(parts.length ? `Blade: ${parts.join(", ")}` : "Blade template");
        }
        const classM = content.match(/class\s+(\w+)(?:\s+extends\s+(\w+))?/);
        const className = classM?.[1] || "";
        const parent = classM?.[2] || "";
        const pubMethods = (content.match(/public\s+function\s+(\w+)/g) || [])
            .map(m => m.match(/public\s+function\s+(\w+)/)?.[1])
            .filter(n => n && n !== "__construct" && n !== "middleware");
        if (basename.endsWith("Controller.php") || parent === "Controller") {
            if (pubMethods.length > 0) {
                const display = pubMethods.slice(0, 5).join(", ");
                return cap(pubMethods.length > 5 ? `${display} + ${pubMethods.length - 5} more` : display);
            }
        }
        if (parent === "Model" || parent === "Authenticatable") {
            const parts = [];
            const tbl = content.match(/\$table\s*=\s*['"]([^'"]+)['"]/);
            if (tbl)
                parts.push(`table: ${tbl[1]}`);
            const fill = content.match(/\$fillable\s*=\s*\[([^\]]*)\]/s);
            if (fill) {
                const c = (fill[1].match(/['"]/g) || []).length / 2;
                parts.push(`${Math.floor(c)} fields`);
            }
            const rels = (content.match(/\$this->(hasMany|hasOne|belongsTo|belongsToMany|morphMany|morphTo)\(/g) || []).length;
            if (rels)
                parts.push(`${rels} rels`);
            return cap(parts.length ? `Model — ${parts.join(", ")}` : `Model: ${className}`);
        }
        if (basename.match(/^\d{4}_\d{2}_\d{2}/)) {
            const create = content.match(/Schema::create\(\s*['"]([^'"]+)['"]/);
            if (create)
                return `Migration: create ${create[1]} table`;
            const alter = content.match(/Schema::table\(\s*['"]([^'"]+)['"]/);
            if (alter)
                return `Migration: alter ${alter[1]} table`;
            return "Database migration";
        }
        if (className && pubMethods.length > 0) {
            const display = pubMethods.slice(0, 4).join(", ");
            return cap(pubMethods.length > 4 ? `${className}: ${display} + ${pubMethods.length - 4} more` : `${className}: ${display}`);
        }
    }
    // ─── TS/JS/React/Next.js ─────────────────────────────────
    if (ext === ".ts" || ext === ".tsx" || ext === ".js" || ext === ".jsx" || ext === ".mjs" || ext === ".cjs") {
        // React component
        if (ext === ".tsx" || ext === ".jsx") {
            const comp = content.match(/(?:export\s+(?:default\s+)?)?(?:function|const)\s+(\w+)/);
            const parts = [];
            if (comp)
                parts.push(comp[1]);
            const renders = [];
            if (/<(?:form|Form)/i.test(content))
                renders.push("form");
            if (/<(?:table|Table|DataTable)/i.test(content))
                renders.push("table");
            if (/<(?:dialog|Dialog|Modal|Drawer)/i.test(content))
                renders.push("modal");
            if (renders.length)
                parts.push(`renders ${renders.join(", ")}`);
            if (parts.length)
                return cap(parts.join(" — "));
        }
        // Next.js conventions
        if (basename === "page.tsx" || basename === "page.js")
            return "Next.js page component";
        if (basename === "layout.tsx" || basename === "layout.js")
            return "Next.js layout";
        if (basename === "route.ts" || basename === "route.js") {
            const methods = [...new Set((content.match(/export\s+(?:async\s+)?function\s+(GET|POST|PUT|PATCH|DELETE)/g) || [])
                    .map(m => m.match(/(GET|POST|PUT|PATCH|DELETE)/)?.[1]))].filter(Boolean);
            return methods.length ? `Next.js API route: ${methods.join(", ")}` : "Next.js API route";
        }
        // Express/Fastify routes
        const routeHits = content.match(/\.(get|post|put|patch|delete)\s*\(\s*['"`]/g);
        if (routeHits && routeHits.length > 0) {
            const methods = [...new Set(routeHits.map(r => r.match(/\.(get|post|put|patch|delete)/)?.[1]?.toUpperCase()))];
            return cap(`API routes: ${methods.join(", ")} (${routeHits.length} endpoints)`);
        }
        // tRPC router
        if (content.includes("createTRPCRouter") || content.includes("publicProcedure")) {
            const procs = (content.match(/\.(query|mutation|subscription)\s*\(/g) || []).length;
            return procs ? `tRPC router: ${procs} procedures` : "tRPC router";
        }
        // Zod schemas
        if (content.includes("z.object") || content.includes("z.string")) {
            const schemas = (content.match(/(?:export\s+)?(?:const|let)\s+(\w+)\s*=\s*z\./g) || [])
                .map(s => s.match(/(?:const|let)\s+(\w+)/)?.[1]).filter(Boolean);
            if (schemas.length)
                return cap(`Zod schemas: ${schemas.slice(0, 4).join(", ")}${schemas.length > 4 ? ` + ${schemas.length - 4} more` : ""}`);
        }
        // Exports summary
        const exports = (content.match(/export\s+(?:async\s+)?(?:function|class|const|interface|type|enum)\s+(\w+)/g) || [])
            .map(e => e.match(/(\w+)$/)?.[1]).filter(Boolean);
        if (exports.length > 0 && exports.length <= 5)
            return `Exports ${exports.join(", ")}`;
        if (exports.length > 5)
            return cap(`Exports ${exports.slice(0, 4).join(", ")} + ${exports.length - 4} more`);
    }
    // ─── Python / Django / FastAPI / Flask ────────────────────
    if (ext === ".py") {
        // Django model
        if (content.includes("models.Model")) {
            const cls = content.match(/class\s+(\w+)\(.*models\.Model\)/);
            const fields = (content.match(/^\s+\w+\s*=\s*models\.\w+/gm) || []).length;
            return cap(`Model: ${cls?.[1] || "unknown"}, ${fields} fields`);
        }
        // FastAPI/Flask routes
        if (content.includes("@router.") || content.includes("@app.")) {
            const routes = (content.match(/@(?:router|app)\.(get|post|put|patch|delete)\s*\(/g) || []);
            return cap(routes.length ? `API: ${routes.length} endpoints` : "API router");
        }
        // Pydantic
        if (content.includes("BaseModel") && content.includes("Field(")) {
            const cls = content.match(/class\s+(\w+)\(.*BaseModel\)/);
            return cls ? `Pydantic: ${cls[1]}` : "Pydantic model";
        }
        // Celery
        if (content.includes("@shared_task") || content.includes("@app.task")) {
            const tasks = (content.match(/def\s+(\w+)/g) || []).map(m => m.match(/def\s+(\w+)/)?.[1]).filter(n => n && !n.startsWith("_"));
            return cap(tasks.length ? `Celery tasks: ${tasks.join(", ")}` : "Celery task");
        }
        // Generic
        const pyClass = content.match(/class\s+(\w+)/);
        const funcs = (content.match(/def\s+(\w+)/g) || []).map(f => f.match(/def\s+(\w+)/)?.[1]).filter(n => n && !n.startsWith("_"));
        if (pyClass && funcs.length > 0)
            return cap(funcs.length > 4 ? `${pyClass[1]}: ${funcs.slice(0, 4).join(", ")} + ${funcs.length - 4} more` : `${pyClass[1]}: ${funcs.join(", ")}`);
        if (funcs.length > 0)
            return cap(funcs.slice(0, 4).join(", "));
    }
    // ─── Go ──────────────────────────────────────────────────
    if (ext === ".go") {
        const handlers = (content.match(/func\s+(\w+)\s*\(\s*\w+\s+http\.ResponseWriter/g) || [])
            .map(m => m.match(/func\s+(\w+)/)?.[1]).filter(Boolean);
        if (handlers.length)
            return cap(`HTTP handlers: ${handlers.slice(0, 5).join(", ")}`);
        const iface = content.match(/type\s+(\w+)\s+interface\s*\{/);
        if (iface)
            return `Interface: ${iface[1]}`;
        const structM = content.match(/type\s+(\w+)\s+struct\s*\{/);
        if (structM)
            return `Struct: ${structM[1]}`;
        const funcs = (content.match(/^func\s+(\w+)/gm) || []).map(m => m.match(/func\s+(\w+)/)?.[1]).filter(n => n && n[0] === n[0].toUpperCase());
        if (funcs.length)
            return cap(funcs.slice(0, 5).join(", "));
    }
    // ─── Rust ────────────────────────────────────────────────
    if (ext === ".rs") {
        const structM = content.match(/pub\s+struct\s+(\w+)/);
        if (structM) {
            const methods = (content.match(/pub\s+(?:async\s+)?fn\s+(\w+)/g) || []).map(m => m.match(/fn\s+(\w+)/)?.[1]).filter(Boolean);
            return cap(methods.length ? `${structM[1]}: ${methods.slice(0, 4).join(", ")}` : `Struct: ${structM[1]}`);
        }
        const traitM = content.match(/pub\s+trait\s+(\w+)/);
        if (traitM)
            return `Trait: ${traitM[1]}`;
        const enumM = content.match(/pub\s+enum\s+(\w+)/);
        if (enumM)
            return `Enum: ${enumM[1]}`;
        const fns = (content.match(/pub\s+(?:async\s+)?fn\s+(\w+)/g) || []).map(m => m.match(/fn\s+(\w+)/)?.[1]).filter(Boolean);
        if (fns.length)
            return cap(fns.slice(0, 5).join(", "));
    }
    // ─── Java / Spring ───────────────────────────────────────
    if (ext === ".java") {
        const cls = content.match(/(?:public\s+)?class\s+(\w+)/);
        const className = cls?.[1] || basename.replace(".java", "");
        const annotations = (content.match(/@(RestController|Controller|Service|Repository|Component|Entity|Configuration)/g) || []).map(a => a.slice(1));
        const mappings = (content.match(/@(?:Get|Post|Put|Patch|Delete|Request)Mapping/g) || []).length;
        if (mappings)
            return cap(`${annotations[0] || "Spring"}: ${className} (${mappings} endpoints)`);
        if (annotations.length)
            return `${annotations[0]}: ${className}`;
        if (content.includes("@Entity"))
            return `Entity: ${className}`;
        const methods = (content.match(/public\s+(?:static\s+)?(?:\w+(?:<[\w,\s]+>)?)\s+(\w+)\s*\(/g) || [])
            .map(m => m.match(/(\w+)\s*\(/)?.[1]).filter(n => n && n !== className);
        if (methods.length)
            return cap(`${className}: ${methods.slice(0, 4).join(", ")}`);
        return className ? `Class: ${className}` : "";
    }
    // ─── Kotlin ──────────────────────────────────────────────
    if (ext === ".kt" || ext === ".kts") {
        const cls = content.match(/(?:data\s+)?class\s+(\w+)/);
        if (content.match(/data\s+class/))
            return `Data class: ${cls?.[1] || basename.replace(/\.kts?$/, "")}`;
        if (content.includes("routing {"))
            return "Ktor routing";
        const fns = (content.match(/fun\s+(\w+)/g) || []).map(m => m.match(/fun\s+(\w+)/)?.[1]).filter(Boolean);
        if (cls && fns.length)
            return cap(`${cls[1]}: ${fns.slice(0, 4).join(", ")}`);
        if (fns.length)
            return cap(fns.slice(0, 5).join(", "));
    }
    // ─── C# / .NET ───────────────────────────────────────────
    if (ext === ".cs") {
        const cls = content.match(/(?:public\s+)?(?:partial\s+)?class\s+(\w+)(?:\s*:\s*(\w+))?/);
        const className = cls?.[1] || basename.replace(".cs", "");
        const parent = cls?.[2] || "";
        if (parent === "Controller" || parent === "ControllerBase" || content.includes("[ApiController]")) {
            const actions = (content.match(/\[Http(Get|Post|Put|Patch|Delete)\]/g) || []).map(a => a.match(/Http(\w+)/)?.[1]).filter(Boolean);
            return cap(actions.length ? `API Controller: ${className} (${[...new Set(actions)].join(", ")})` : `Controller: ${className}`);
        }
        if (parent === "DbContext" || content.includes("DbSet<")) {
            const sets = (content.match(/DbSet<(\w+)>/g) || []).map(s => s.match(/<(\w+)>/)?.[1]).filter(Boolean);
            return cap(sets.length ? `DbContext: ${sets.join(", ")}` : `DbContext: ${className}`);
        }
        return className ? `Class: ${className}` : "";
    }
    // ─── Ruby / Rails ────────────────────────────────────────
    if (ext === ".rb") {
        const cls = content.match(/class\s+(\w+)(?:\s*<\s*(\w+(?:::\w+)?))?/);
        const className = cls?.[1] || "";
        const parent = cls?.[2] || "";
        if (parent?.includes("Controller")) {
            const actions = (content.match(/def\s+(index|show|new|create|edit|update|destroy|\w+)/g) || [])
                .map(m => m.match(/def\s+(\w+)/)?.[1]).filter(n => n && !n.startsWith("_"));
            return cap(actions.length ? `Controller: ${actions.join(", ")}` : `Controller: ${className}`);
        }
        if (parent === "ApplicationRecord" || parent === "ActiveRecord::Base")
            return `Model: ${className}`;
        if (basename.match(/^\d{14}_/)) {
            const create = content.match(/create_table\s+:(\w+)/);
            return create ? `Migration: create ${create[1]}` : "Database migration";
        }
        const methods = (content.match(/def\s+(\w+)/g) || []).map(m => m.match(/def\s+(\w+)/)?.[1]).filter(n => n && !n.startsWith("_"));
        if (cls && methods.length)
            return cap(`${className}: ${methods.slice(0, 4).join(", ")}`);
    }
    // ─── Swift ───────────────────────────────────────────────
    if (ext === ".swift") {
        if (content.includes(": View") || content.includes("some View")) {
            const name = content.match(/struct\s+(\w+)\s*:\s*View/);
            return name ? `SwiftUI view: ${name[1]}` : "SwiftUI view";
        }
        const proto = content.match(/protocol\s+(\w+)/);
        if (proto)
            return `Protocol: ${proto[1]}`;
        const struct = content.match(/(?:public\s+)?struct\s+(\w+)/);
        const cls = content.match(/(?:public\s+)?class\s+(\w+)/);
        const name = struct?.[1] || cls?.[1] || "";
        if (name)
            return `${struct ? "Struct" : "Class"}: ${name}`;
    }
    // ─── Dart / Flutter ──────────────────────────────────────
    if (ext === ".dart") {
        if (content.includes("StatefulWidget") || content.includes("StatelessWidget")) {
            const name = content.match(/class\s+(\w+)\s+extends\s+(?:Stateful|Stateless)Widget/);
            return name ? `${content.includes("StatefulWidget") ? "Stateful" : "Stateless"} widget: ${name[1]}` : "Flutter widget";
        }
        const cls = content.match(/class\s+(\w+)/);
        if (cls)
            return `Class: ${cls[1]}`;
    }
    // ─── Vue / Svelte / Astro ────────────────────────────────
    if (ext === ".vue") {
        const name = content.match(/name:\s*['"]([^'"]+)['"]/);
        const setup = content.includes("<script setup");
        const parts = [];
        if (name)
            parts.push(name[1]);
        if (setup)
            parts.push("setup");
        return cap(parts.length ? `Vue: ${parts.join(", ")}` : "Vue component");
    }
    if (ext === ".svelte")
        return `Svelte: ${basename.replace(".svelte", "")}`;
    if (ext === ".astro")
        return `Astro: ${basename.replace(".astro", "")}`;
    // ─── CSS / SCSS / Less ───────────────────────────────────
    if (ext === ".css" || ext === ".scss" || ext === ".less") {
        const rules = (content.match(/^[.#@][^\n{]+/gm) || []).length;
        const vars = (content.match(/--[\w-]+\s*:/g) || []).length;
        const parts = [];
        if (rules)
            parts.push(`${rules} rules`);
        if (vars)
            parts.push(`${vars} vars`);
        return cap(parts.length ? `Styles: ${parts.join(", ")}` : "Stylesheet");
    }
    // ─── SQL ─────────────────────────────────────────────────
    if (ext === ".sql") {
        const creates = (content.match(/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"']?(\w+)/gi) || [])
            .map(m => m.match(/(?:TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?)([`"']?\w+)/i)?.[1]?.replace(/[`"']/g, "")).filter(Boolean);
        if (creates.length)
            return cap(`SQL: tables: ${creates.slice(0, 4).join(", ")}`);
    }
    // ─── Proto / GraphQL ─────────────────────────────────────
    if (ext === ".proto") {
        const msgs = (content.match(/message\s+(\w+)/g) || []).map(m => m.match(/message\s+(\w+)/)?.[1]).filter(Boolean);
        const services = (content.match(/service\s+(\w+)/g) || []).map(m => m.match(/service\s+(\w+)/)?.[1]).filter(Boolean);
        const parts = [];
        if (msgs.length)
            parts.push(`messages: ${msgs.slice(0, 3).join(", ")}`);
        if (services.length)
            parts.push(`services: ${services.join(", ")}`);
        return cap(parts.length ? `Proto: ${parts.join(", ")}` : "");
    }
    if (ext === ".graphql" || ext === ".gql") {
        const types = (content.match(/type\s+(\w+)/g) || []).map(m => m.match(/type\s+(\w+)/)?.[1]).filter(Boolean);
        return cap(types.length ? `GraphQL: types: ${types.slice(0, 4).join(", ")}` : "GraphQL schema");
    }
    // ─── YAML ────────────────────────────────────────────────
    if (ext === ".yaml" || ext === ".yml") {
        if (content.includes("runs-on:")) {
            const name = content.match(/^name:\s*(.+)$/m);
            return cap(name ? `CI: ${name[1].trim()}` : "GitHub Actions workflow");
        }
        if (content.includes("apiVersion:") && content.includes("kind:")) {
            const kind = content.match(/kind:\s*(\w+)/);
            return cap(kind ? `K8s ${kind[1]}` : "Kubernetes manifest");
        }
        if (content.includes("services:") && (basename.includes("docker") || basename.includes("compose"))) {
            const services = (content.match(/^\s{2}\w+:/gm) || []).length;
            return `Docker Compose: ${services} services`;
        }
    }
    // ─── TOML ────────────────────────────────────────────────
    if (ext === ".toml") {
        const desc = content.match(/^description\s*=\s*"([^"]+)"/m);
        if (desc)
            return cap(desc[1]);
    }
    // ─── Elixir ──────────────────────────────────────────────
    if (ext === ".ex" || ext === ".exs") {
        const mod = content.match(/defmodule\s+([\w.]+)/);
        if (content.includes("Phoenix.LiveView"))
            return cap(mod ? `LiveView: ${mod[1]}` : "Phoenix LiveView");
        if (content.includes("Controller"))
            return cap(mod ? `Phoenix controller: ${mod[1]}` : "Phoenix controller");
        const fns = (content.match(/def\s+(\w+)/g) || []).map(m => m.match(/def\s+(\w+)/)?.[1]).filter(Boolean);
        if (mod && fns.length)
            return cap(`${mod[1]}: ${fns.slice(0, 4).join(", ")}`);
        if (mod)
            return mod[1];
    }
    // ─── Lua ─────────────────────────────────────────────────
    if (ext === ".lua") {
        const fns = (content.match(/function\s+(?:\w+[.:])?(\w+)/g) || []).map(m => m.match(/(\w+)\s*$/)?.[1]).filter(Boolean);
        if (fns.length)
            return cap(fns.slice(0, 5).join(", "));
    }
    // ─── Zig ─────────────────────────────────────────────────
    if (ext === ".zig") {
        const fns = (content.match(/pub\s+fn\s+(\w+)/g) || []).map(m => m.match(/fn\s+(\w+)/)?.[1]).filter(Boolean);
        if (fns.length)
            return cap(fns.slice(0, 5).join(", "));
    }
    // Last resort
    const declM = content.match(/(?:function|class|const|interface|type|enum)\s+(\w+)/);
    if (declM) {
        const name = declM[1];
        const methods = (content.match(/(?:public\s+)?(?:async\s+)?(?:function\s+|(?:get|set)\s+)(\w+)\s*\(/g) || [])
            .map(m => m.match(/(\w+)\s*\(/)?.[1]).filter(n => n && n !== name && n !== "__construct" && n !== "constructor");
        if (methods.length > 0 && methods.length <= 5)
            return cap(`${name}: ${methods.join(", ")}`);
        if (methods.length > 5)
            return cap(`${name}: ${methods.slice(0, 3).join(", ")} + ${methods.length - 3} more`);
        return `Declares ${name}`;
    }
    return "";
}
export function estimateTokens(text, type = "mixed") {
    const ratio = type === "code" ? 3.5 : type === "prose" ? 4.0 : 3.75;
    return Math.ceil(text.length / ratio);
}
export function timestamp() {
    return new Date().toISOString();
}
export function timeShort() {
    const d = new Date();
    return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
}
export function readStdin() {
    return new Promise((resolve) => {
        const chunks = [];
        process.stdin.on("data", (chunk) => chunks.push(chunk));
        process.stdin.on("end", () => resolve(Buffer.concat(chunks).toString("utf-8")));
        // If no stdin data after 4s, resolve with whatever we have so far.
        // On Windows, stdin delivery from Claude Code hooks can be slow.
        setTimeout(() => resolve(chunks.length ? Buffer.concat(chunks).toString("utf-8") : "{}"), 4000);
    });
}
export function normalizePath(p) {
    return p.replace(/\\/g, "/");
}
//# sourceMappingURL=shared.js.map