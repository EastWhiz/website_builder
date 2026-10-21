// Regenerates public/export_assets/lead-validation-messages.json from the table in lead-validation.js.
// The PHP validator reads that JSON; tests fail if it drifts from the JS table.
// Run: node scripts/generate-lead-validation-messages.cjs
const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const src = fs.readFileSync(path.join(root, "public/export_assets/lead-validation.js"), "utf8");
const m = { exports: {} };
new Function("module", "exports", src)(m, m.exports);

const out = path.join(root, "public/export_assets/lead-validation-messages.json");
fs.writeFileSync(out, JSON.stringify(m.exports.messageTable(), null, 2) + "\n", "utf8");
console.log("wrote " + path.relative(root, out));
