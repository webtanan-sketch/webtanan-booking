#!/usr/bin/env bash
set -e

echo "Creating labels..."
python3 - <<'PY'
import json, subprocess

with open("project/labels.json", "r", encoding="utf-8") as f:
    labels = json.load(f)

for item in labels:
    name = item["name"]
    color = item["color"]
    desc = item.get("description", "")
    subprocess.run([
        "gh", "label", "create", name,
        "--color", color,
        "--description", desc,
        "--force"
    ], check=False)
PY

echo "Creating issues..."
python3 - <<'PY'
import json, subprocess, tempfile, os

with open("project/issues.json", "r", encoding="utf-8") as f:
    issues = json.load(f)

for item in issues:
    title = item["title"]
    body = item["body"] + "\n\n---\n\nMilestone: `" + item["milestone"] + "`\n"
    labels = ",".join(item["labels"])

    with tempfile.NamedTemporaryFile("w", delete=False, encoding="utf-8", suffix=".md") as tmp:
        tmp.write(body)
        body_file = tmp.name

    try:
        cmd = [
            "gh", "issue", "create",
            "--title", title,
            "--body-file", body_file,
            "--label", labels
        ]
        subprocess.run(cmd, check=False)
    finally:
        os.unlink(body_file)
PY

echo "Done."
echo "Now open GitHub Projects and add the created issues to the project board."
